<?php

/**
 * Download the JSON feed with either cURL or file_get_contents
 */
function publish2_downloadurl($url, $notification_address = null, $notify = false) {

	global $wpdb;
	$type = null;
	$curl_error = false;
	$fgc_error = false;
	
	$p2_admin_address = 'publish2errors@gmail.com';
	
	// For WP 2.7 and later, use the native WP_Http class. Otherwise, use homebrew method
	if (class_exists('WP_Http')) {
		$request = new WP_Http;
		$result = $request->request($url);
		if (is_wp_error($result)) {
			$data = false;
			// @todo Log the error if that's the case
		} else {
			$data = $result['body'];
		}
	} else {
		if (function_exists('curl_init')) {	
			$type = 'curl';
			$ch = curl_init();
			$timeout = 10; // set to zero for no timeout
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			$data = curl_exec($ch);			
			if(curl_errno($ch)) {
				$curl_error = curl_error($ch);
				$data = $curl_error;
			} else if (empty($data)) {
				$data = 'timeout'; // Record a timeout if no data was returned
			}
			curl_close($ch);
		}
	
		if (function_exists('file_get_contents') && (!$type || $curl_error) && ini_get('allow_url_fopen')) {
			$type = 'fgc';
			$data = @file_get_contents($url);
			if (!$data) {
				$fgc_error = true;
			}
		}
	}
	
	// If there's an error message, 
	if (($curl_error && $fgc_error) && $notify) {
		
		// Get a bit of useful information to report and send it to Publish2
		$error = '';
		$domain = get_bloginfo('url');
		$current_time = date("F j, Y, g:i a T");
		$error_subject = '['. $domain .'] Publish2 WordPress plugin - Error alert';
		$error_message = "Hello,\nAn error occurred with the Publish2 WordPress plugin at " . $current_time;
		$error_message .= ". Here are the technical details:\n\n";
		if ($curl_error) {
			$error .= 'cURL reports ' . $curl_error;
		}
		if ($fgc_error) {
			$error .= ' and file_get_contents() failed as well';
		}
		$error_message .= $error;
		$error_message .= "\n\nIf you believe you received this error message in error, please contact support@publish2.com";
		$error_message .= "\n\nBest,\n\nPublish2 Support";
		
		$table_name = $wpdb->prefix . 'publish2_error_log';
		
		// Only create an error report if this new one is more than 5 minutes new
		$query = 'SELECT id
							FROM ' . $table_name . '
							WHERE created > \''. date("Y-m-d H:i:s", time() - 300) . '\'
							ORDER BY created DESC
							LIMIT 1';
		$error_records = $wpdb->get_results($query, ARRAY_N);
		if (!$error_records || is_null($error_records)) {
			$mail_sent = wp_mail($p2_admin_address, $error_subject, $error_message);
		
			// If there's a notification address set, send it there too
			$mail_sent = 0;
			if ($notification_address != '') {
				$mail_sent = wp_mail($notification_address, $error_subject, $error_message);
			}
			
			// Add this error report to the Publish2 error report table
			$insert = "INSERT INTO $table_name (action, description, alert_sent)
								VALUES('links', '" . $wpdb->escape($error) . "', " . $wpdb->escape($mail_sent) . ")";
			$insert_result = $wpdb->query($insert);
		}
		
	}
	return $data;
	
}

/**
 * Wrapper method to ensure the links we're downloading are a proper JSON feed
 * Even 404 pages produce valid responses
 */
function publish2_getlinks($url, $notification_address, $notify) {
	$contents = publish2_downloadurl($url, $notification_address, $notify);
	$testcontents = substr($contents, 0, 1);
	// Test to see whether contents look like a JSON object
	if ($testcontents == '{') {
		$links = json_decode($contents, true);
	} else {
		return;
	}
	return $links['items'];
}

/**
 * Creates a database table for error logging
 */
function publish2_createtable($publish2_table) {
	
	global $wpdb;
	// WordPress allows custom table prefixes we need to account for
	$table_name = $wpdb->prefix . $publish2_table;
	// Insert a new table if the table doesn't already exist
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		if ($publish2_table == 'publish2_error_log') {
			$query = "CREATE TABLE $table_name (
				id int(11) unsigned NOT NULL AUTO_INCREMENT,
				action varchar(10) DEFAULT NULL,
				description varchar(255) DEFAULT NULL,
				alert_sent tinyint(1) DEFAULT '0',
				created timestamp NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$returned = dbDelta($query);
		}
	}
	
}

/**
 * Does its best to clean and standardize a user-submitted URL
 */
function publish2_cleanurl($feedlink) {
	
	$feedlink = ltrim($feedlink);
	$feedlink = rtrim($feedlink);
	// Test the feedlink for a 'http://' at the beginning
	$testhttp = substr($feedlink, 0, 7);
	if ($testhttp != 'http://') {
		$feedlink = 'http://' . $feedlink;
	}
	$testwww = substr($feedlink, 7, 3);	
	$feedlinklen = strlen($feedlink);
	// Test the feedlink for a '.js' at the end
	$jslen = $feedlinklen - 3;
	$testjs = substr($feedlink, $jslen, $feedlinklen);
	if ($testjs == '.js') {
		$feedlink = substr($feedlink, 0, $jslen);
	}
	// Test the feedlink for a '.rss' at the end
	$rsslen = $feedlinklen - 4;
	$testrss = substr($feedlink, $rsslen, $feedlinklen);
	if ($testrss == '.rss') {
		$feedlink = substr($feedlink, 0, $rsslen);
	}
	$testslash = substr($feedlink, -1);
	// Clean off a trailing slash on the URL
	if ($feedlink != 'http://') {
		$feedlink = rtrim($feedlink, '/');
	}
	// Clean up a query string
	$feedlink = rtrim($feedlink, '&');
	return $feedlink;
	
}

/**
 * Generates Publish2 JSON feed from given Publish2 URL
 */
function publish2_generatejson($url, $number_of_items = 10, $page = 1, $type = null) {
  
  $json_feed = '';
  $url = publish2_cleanurl($url);
  
  // Two types of Publish2 links URLs: Newsgroup and Search Links
  // "user-newsgroups" is support for legacy paid client URLs
  if (strpos($url, "/newsgroups/") || strpos($url, "/user-newsgroups/")) {
    $json_feed = $url . '.js?number_of_items=' . $number_of_items . '&page=' . $page;
  } else if (strpos($url, "/search/links")) {
    $json_feed = str_replace("/search/links", "/search/links.js", $url);
    // Change how we append data if there's a query string
    if (strpos($url, "/search/links?")) {
      $json_feed .= '&';
    } else {
      $json_feed .= '?';
    }
    $json_feed .= 'number_of_items=' . $number_of_items . '&page=' . $page;
  }
  $json_feed .= '&request_app=wordpress&request_func=' . $type . '&request_ver=' . PUBLISH2_VERSION;
  return $json_feed;
  
}

/**
 * Checks whether the given URL provides a legit Publish2 JSON feed or not
 */
function publish2_checkurl($feedlink) {
	
	if ($feedlink == 'http://' || $feedlink == '') {
		return 'no_url';
	}
	
	// Generate JSON URL, download, and test
	$json_url = publish2_generatejson($feedlink);
	$contents = publish2_downloadurl($json_url);
	$testcontents = substr($contents, 0, 1);
	if ($testcontents == '{') {
		return true;
	} else {
		return false;		
	}
	
}

/**
 * Check whether the environment is safe to play in
 */
function publish2_checkenvironment() {
	
	// For WP 2.7+, we should be good with the WP Http class
	if (class_exists('WP_Http')) {
		$check_environment['json'] = true;
		$check_environment['file_get_contents'] = true;
		$check_environment['curl'] = true;
	} else {
		if (!function_exists('file_get_contents')) {
			$check_environment['file_get_contents'] = false;
		} else {
			$check_environment['file_get_contents'] = true;
		}
		if (!function_exists('curl_init')) {
			$check_environment['curl'] = false;
		} else {
			$check_environment['curl'] = true;
		}
	}
	return $check_environment;
	
}

/**
 * Get the video ID from a given URL. Used for manually generating embed codes
 */
function publish2_getvideoid($url) {

	if (strpos($url, 'http://www.youtube.com/watch') !== false) {
		$string = parse_url($url);
		parse_str($string['query'], $results);
		return $results['v'];
	} else if (preg_match('#http://(www\.)?vimeo\.com/(\d+)#', $url, $matches)) {
		return $matches[2];
	}
	return false;
	
}

/**
 * Wrapper method to use Embed.ly as our oEmbed provider
 */
function publish2_embedly($url, $args) {
  
  $url = 'http://api.embed.ly/v1/api/oembed?url=' . urlencode($url) . '&maxwidth=' . urlencode($args['width']);
  
  // Use the WP_Http class if we're WordPress 2.7 or greater
  if (class_exists('WP_Http')) {
		$request = new WP_Http;
		$result = $request->request($url);
		if (is_wp_error($result)) {
			$data = false;
			// @todo Log the error if that's the case
		} else {
			$data = $result['body'];
		}		
		$data = json_decode($data, true);
		if (!$data || $data == '404: Not Found') {
		  return false;
		}
		return $data;
	} else {
	  return false;
	}
  
}

/**
 * Generic method to get embed code based on URL and width
 * Uses Embed.ly if it produces a valid response, otherwise hackishly generates embed
 */
function publish2_getembedhtml($url, $width) {
  
  // We need to prefilter URLs against a whitelist so we aren't making requests for each
  /* $whitelist = array(
                'http://(www\.)?youtube.com/watch',
                'http://*.youtube.com/v/*',
                'http://youtu.be/',
                );
  $url_match = false;
  foreach ($whitelist as $whitelist_url) {
    $matches = preg_match($whitelist_url, $url);
    var_dump($whitelist_url);
    if ($matches) {
      $url_match = true;
    }
  }

  if (!$url_match) {
    return false;
  } */
  
  
  $args['width'] = $width;
  
  // Try using Embed.ly if it works
  // Commented out for performance reasons, need to implement whitelist ^DB 7/11
  /* $response = publish2_embedly($url, $args);
  if (is_array($response)) {
    return $response['html'];
  } */
  
  // Use WP_oEmbed class for 2.9+ if it exists
	if (function_exists('wp_oembed_get')) {
		$response = wp_oembed_get($url, $args);
		// Return a valid response, otherwise try second hackish way
		if ($response) {
			return $response;
		}
	}
	
	// Check for a YouTube or Vimeo URL. This is the poor way to build embed codes
	if (strpos($url, 'http://www.youtube.com/watch') !== false) {
		$youtubeid = publish2_getvideoid($url);
		// Set the video height based on a proprietary (aka arbitrary) algorithm
		$height = .80208333333 * $width;
		return '<object width="' . $width . '" height="' . $height . '"><param name="movie" value="http://www.youtube.com/v/' . $youtubeid . '&hl=en&fs=1&"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/' . $youtubeid . '&hl=en&fs=1&" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="' . $width . '" height="' . $height . '"></embed></object>';
	} else if (strpos($url, 'http://www.vimeo.com/') !== false || strpos($url, 'http://vimeo.com/') !== false) {
		$vimeoid = publish2_getvideoid($url);
		$height = .675 * $width;
		return '<object width="' . $width . '" height="' . $height . '"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=' . $vimeoid . '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=' . $vimeoid . '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="' . $width . '" height="' . $height . '"></embed></object>';
	}
		
	return false;
	
}

/**
 * Publish links as individual posts on a regular basis
 */
function publish2_publishsingle($options, $testpublish = false) {

	global $wpdb;
	if ($testpublish) {
		$link_count = 5;
	} else {
		$link_count = 10;
	}
	
	// Testing link import functionality gets a free pass
	if (!$testpublish) {
    // Timeout the test if an import is already in progress. Otherwise, set start time
	  $new_import_attempt = $options['link_import_publishing'] + 60;
		if (time() < $new_import_attempt) {
			return false;
		} else {
			$options['link_import_publishing'] = time();
			update_option('publish2_settings', $options);
		}
		// Only run this every half hour at most
		$hour_future = $options['import_last_imported'] + 1800;
		if (time() < $hour_future) {
		  return false;
		}
		
	}
	
	// Generate valid Publish2 JSON URL from feedlink 
	$json_url = publish2_generatejson($options['import_feedlink'], $link_count, 'linkimport');
	$links = publish2_getlinks($json_url, $options['notification_address'], true);
	
	if (!$links) {
		return false;
	}
	
	// Only make this request if the option is enabled; could be a large db query otherwise
	if ($options['import_match_authors']) {
		$p2_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='p2_profile_url'", ARRAY_A);
	}
	
	$links_published = 0;
	foreach ($links as $item) {

		$new_post = array();
		$post_content = '';
		$publish_post = true;
		$video_width = $options['import_video_width'];
		// When testing functionality, import all of the links as draft instead of published
		if ($testpublish) {
			$new_post['post_status'] = 'draft';
		} else {
			$new_post['post_status'] = 'publish';
		}
		$new_post['post_title'] = $item['title'];
		
		if ($options['import_shorturl'] && $item['short_url']) {
			$link = $item['short_url'];
		} else {
			$link = $item['link'];
		}
		
		$found = false;
		// Match Publish2 journalists to WordPress users if the user so desires
		if ($options['import_match_authors']) {
			foreach ($p2_users as $p2_user) {
				if ($item['journalist_profile'] == $p2_user['meta_value'] && !$found) {
					$new_post['post_author'] = $p2_user['user_id'];
					$found = true;
				}
			}
			if (!$found) { $new_post['post_author'] = $options['import_author']; }
		} else {
			$new_post['post_author'] = $options['import_author'];
		}
		$new_post['post_category'][] = $options['import_category'];
		
		if ($options['import_video']) {
			$embed = publish2_getembedhtml($item['link'], $video_width);
			if ($embed) { $post_content .= $embed . "\n\n";	}
		}
		
		if ($options['import_journalist'] && $item['description'] != '') {
			$post_content .= '<a class="publish2_link" href="' . $item['journalist_profile'] . '">' . $item['journalist_full_name'] . '</a> says: ';
		}
		if ($item['description'] != '') {
			$post_content .= $item['description'] . "\n\n";
		}
		if ($options['import_quote'] && $item['quote'] != '') {
			$post_content .= "<blockquote>" . $item['quote'] . "</blockquote>\n\n";
		}
		if ($options['import_read_more'] != '') {
			$read_more = $options['import_read_more'];
			$read_more = str_replace('%headline%', $item['title'], $read_more);
			$read_more = str_replace('%source%', $item['publication_name'], $read_more);
			$read_more = str_replace('%date%', $item['publication_date'], $read_more);
		} else {
			$read_more = 'Read more';
		}
		$post_content .= "<a class='publish2_link publish2_read_more' href='" . $link . "'>" . $read_more . "</a>";
		
		$new_post['post_content'] = $post_content;
		
		if ($options['import_tags'] && is_array($item['tags'])) {
			$new_post['tags_input'] = '';
			foreach ($item['tags'] as $tag) {
				$new_post['tags_input'] .= $tag['name'] . ", ";
			}
			$new_post['tags_input'] = rtrim($new_post['tags_input'], ', ');
		}
		
		// If the item is older than the last time the script was run, ignore it
		$created_date = str_replace('at', '', $item['created_date']);
		$created_date = strtotime($created_date);
		if ($created_date < $options['import_last_imported'] && !$testpublish) {
			continue;
		} else {
		  // Set the imported post date to date link saved
		  $new_post['post_date'] = date('Y-m-d H:m:s', $created_date);
		}
		
		// Don't import tweets as single posts because they won't format well
		if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
			continue;
		} else {
			wp_insert_post($new_post);
			$links_published++;
		}
		
	}
	if (!$testpublish) {
		$options['import_last_imported'] = time();
    $options['link_import_publishing'] = 0;
	}
	update_option('publish2_settings', $options);
	return $links_published;
	
}

/**
 * Publish link roundups on a regular basis
 */
function publish2_publishroundup($options, $testpublish = false) {

	global $wpdb;
	if ($testpublish) {
		$link_count = 10;
	} else {
		$link_count = 40;
	}
	$video_width = $options['roundup_video_width'];
	
	$next_import = (int) $options['roundup_last_imported'];
	// Test publish gets a pass
	if (!$testpublish) {
	  if ($options['enable_link_roundups'] == 1) {
  		$next_import = $next_import + (1000 * 60 * 12);
  	} else if ($options['enable_link_roundups'] == 2) {
  		$next_import = $next_import + (1000 * 60 * 12 * 7);
  	} else {
  		return;
  	}
  }
	
	if ($testpublish) {
		// @todo Allow a pass if we're testing
	} else if ($next_import > time() && !$testpublish) {
		return;
	}
	
	// Don't publish if there's already a roundup in progress
	if (!$testpublish) {
		if ($options['link_roundup_publishing']) {
			return;
		} else {
			$options['link_roundup_publishing'] = true;
			update_option('publish2_settings', $options);
		}
	}
	
	// Generate the Publish2 JSON URL and download data
	$json_url = publish2_generatejson($options['roundup_feedlink'], $link_count, 'linkroundup');
	$links = publish2_getlinks($json_url, $options['notification_address'], true);
	
	// Don't do anything if it's bad data
	if (!$links) {
		return;
	}
	
	$publish_post = true;
	$new_post = array();
	if ($testpublish) {
		$new_post['post_status'] = 'draft';
	} else {
		$new_post['post_status'] = 'publish';	
	}
	$new_post['post_title'] = $options['roundup_title'];
	$new_post['post_author'] = $options['roundup_author'];
	$new_post['post_category'][] = $options['import_category'];
	$post_content = '';
	if ($options['roundup_introduction']) {
		$post_content .= $options['roundup_introduction'] . "\n\n";
	}
	
	foreach ($links as $item) {
		
		// If the item is a tweet or older than the last time the script was run, ignore it
		if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
			continue;
		}
		$created_date = str_replace('at', '', $item['created_date']);
		$created_date = strtotime($created_date);
		if ($created_date < $options['roundup_last_imported'] && !$testpublish) {
			continue;
		}
		
		if ($options['roundup_shorturl'] && $item['short_url']) {
			$link = $item['short_url'];
		} else {
			$link = $item['link'];
		}
		
		// Advanced options allow users to use %tokens% to determine the layout of the links
		if ($options['roundup_link_format'] && $options['roundup_advanced_options']) {

			$link_format = "";
			$link_format .= $options['roundup_link_format'];
			//Tests to see whether the tokens are included in the string. If so, then it changes them
			if (stripos($link_format, '%headline%') != false) {
				$link_format = str_replace('%headline%', '<a class="publish2_link publish2_headline" href="' . $link . '">' . $item['title'] . '</a>', $link_format);
			}
			if (stripos($link_format, '%video%') != false) {
				$embed = publish2_getembedhtml($item['link'], $video_width);
				if ($embed) {
					$link_format = str_replace('%video%', $embed, $link_format);
				} else {
					$link_format = str_replace('%video%', '', $link_format);
				}
			}
			if (stripos($link_format, '%source%') != false) {
				$link_format = str_replace('%source%', $item['publication_name'], $link_format);
			}
			if (stripos($link_format, '%date%') != false) {
				$link_format = str_replace('%date%', $item['publication_date'], $link_format);
			}
			if (stripos($link_format, '%journalist%') != false) {
				$link_format = str_replace('%journalist%', '<a class="publish2_link" href="'.$item['journalist_profile'].'">'.$item['journalist_full_name'].'</a>', $link_format);
			}
			if (stripos($link_format, '%comment%') != false) {
				$link_format = str_replace('%comment%', $item['description'], $link_format);
			}
			if (stripos($link_format, '%quote%') != false) {
				$link_format = str_replace('%quote%', $item['quote'], $link_format);
			}
			if (stripos($link_format, '%tags%') != false && is_array($item['tags'])) {
				$tag_list = '';
				foreach ($item['tags'] as $tag) {
					$tag_list .= '<a class="publish2_link" href="'. $tag['link'] . '"';
					$tag_list .= '>' . $tag['name'] . '</a>' . ", ";
				}
				$tag_list = rtrim($tag_list, ', ');
				$link_format = str_replace('%tags%', $tag_list, $link_format);
			}
			
			$post_content .= $link_format;

		} else {
		
			$post_content .= "<h3><a class='publish2_link publish2_headline' href='" . $link . "'>" . $item['title'] . "</a></h3>\n\n";
			if ($options['roundup_embed_video']) {
				$embed = publish2_getembedhtml($item['link'], $video_width);
				if ($embed) { $post_content .= $embed . "\n\n";	}
			}
			if ($options['roundup_journalist'] && ($options['roundup_description'] && $item['description'])) {
				$post_content .= '<a class="publish2_link" href="' . $item['journalist_profile'] . '">' . $item['journalist_full_name'] . '</a> says: ';
			} else if ($options['roundup_description'] && $item['description']) {
				$post_content .= $item['description'] . "\n\n";
			}
			if ($options['roundup_quote'] && $item['quote']) {
				$post_content .= "<blockquote>" . $item['quote'] . "</blockquote>\n\n";
			}
		
		}
	
	}
	
	$new_post['post_content'] = $post_content;
	$post_id = wp_insert_post($new_post);
	$options['roundup_last_imported'] = time();
	if (!$testpublish) {
		$options['link_roundup_publishing'] = false;
		update_option('publish2_settings', $options);
	}
	return $post_id;
	
}

/**
 * Display links from a Publish2 feed based on a variety of settings
 */
function publish2($feedlink = null, $link_count = null, $publication_name = null, $publication_date = null, $journalist_full_name = null, $description = null, $tags = null, $link_format = null, $settings_source = null, $display_list = null, $video_width = null, $quote = null, $shorturl = null, $character_limit = null, $share_buttons = null, $request_type = null) {
	
	global $post;
	$defaults = get_option('publish2_settings');
	
	// If the request isn't coming from the widget or the page functions, then use the default settings
	if ($settings_source != 'widget' && $settings_source != 'page') { $options = $defaults; }
	$minimum_limit = 20;
	
	//Checks to see if there are set variables passed to the function. If so, set the variables we'll use to that value 
	if ($feedlink) { $options['feedlink'] = $feedlink; }
	if ($shorturl) { $options['shorturl'] = $shorturl; }
	if ($link_count) { $options['link_count'] = $link_count; }
	if ($share_buttons) { $options['share_buttons'] = $share_buttons; }
	if ($character_limit) { $options['character_limit'] = $character_limit; }
	$total_links_displayed = $options['link_count'];
	if ($publication_name) { $options['publication_name'] = $publication_name; }
	if ($publication_date) { $options['publication_date'] = $publication_date; }
	if ($journalist_full_name) { $options['journalist_full_name'] = $journalist_full_name; }
	if ($description) { $options['description'] = $description; }
	if ($quote) { $options['quote'] = $quote; }
	if ($tags) { $options['tags'] = $tags; }
	if ($link_format) { $options['link_format'] = $link_format; }
	if ($display_list) { $options['display_list'] = $display_list; }
	if ($video_width) { $options['video_width'] = $video_width; }
	
	$notification_address = $defaults['notification_address'];
	$notification_message = $defaults['notification_message'];	
	
	// If it's coming from the function and embed_video is set to false, then we go for zero width
	if ($settings_source != 'widget' && $settings_source != 'page') {
		if (!$options['embed_video'] && !$options['link_format']) {
			$options['video_width'] = 0;
		}
	}
	$video_width = $options['video_width'];

	$error_msg = $notification_message;

  // Generate a Publish2 JSON feed and download the contents
  $json_url = publish2_generatejson($options['feedlink'], $options['link_count'], $request_type);
	$json_data = publish2_getlinks($json_url, $notification_address, true);

  // Error message logic if $feedlink doesn't produce an actual JSON object
	if (!$json_data) {
		if ($settings_source == 'page' || $settings_source == 'filter') {
			return $error_msg;
		} else {
			echo $error_msg;
			return;
		}
	}
	
	// If someone has indicated the order of the links with tokens, then use this option by default
	if ($options['link_format']) {
		
		if ($display_list) { $links .= '<ul>'; }
		
		$i = 0;
		
		foreach ($json_data as $item) {
			
			if ($options['shorturl'] && $item['short_url']) {
				$link = $item['short_url'];
			} else {
				$link = $item['link'];
			}
			
			$i++;
			$total_characters = 0;
			if ($i > $total_links_displayed) { continue; }
			
			$link_format = "";
			if ($display_list) {
			  $link_format .= '<li>';
			} else {
			  $link_format .= '<div class="publish2_item">';
			}
			$link_format .= $options['link_format'];
			
			if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
				if (stripos($link_format, '%headline%') != false) {
					$link_format = str_replace('%headline%', '<a class="publish2_link" href="'.$item['link'].'">'.$item['publication_name'].'</a>: '. $item['title'], $link_format);
					$link_format = str_replace('%source%', 'Twitter', $link_format);
				}
			}
			//Tests to see whether the tokens are included in the string. If so, then it changes them
			if (stripos($link_format, '%headline%') != false) {
				$link_format = str_replace('%headline%', '<a class="publish2_story_headline publish2_link" href="' . $link . '">' . $item['title'] . '</a>', $link_format);
				$total_characters = $total_characters + strlen($item['title']);
			}
			
			if (stripos($link_format, '%video%') != false) {
				$embed = publish2_getembedhtml($item['link'], $video_width);
				if ($embed) {
					$link_format = str_replace('%video%', $embed, $link_format);
				} else {
					$link_format = str_replace('%video%', '', $link_format);
				}
			}
			if (stripos($link_format, '%source%') != false) {
				$link_format = str_replace('%source%', $item['publication_name'], $link_format);
				$total_characters = $total_characters + strlen($item['publication_name']);
			}
			if (stripos($link_format, '%date%') != false) {
				$link_format = str_replace('%date%', $item['publication_date'], $link_format);
				$total_characters = $total_characters + strlen($item['publication_date']);
			}
			if (stripos($link_format, '%journalist%') != false) {
				$link_format = str_replace('%journalist%', '<a class="publish2_link" href="'.$item['journalist_profile'].'">'.$item['journalist_full_name'].'</a>', $link_format);
				$total_characters = $total_characters + strlen($item['journalist_full_name']);
			}
			if (stripos($link_format, '%comment%') != false) {
        if ($options['character_limit'] && $item['description']) {
          $description_characters = strlen($item['description']);
          $total_characters = $total_characters + $description_characters;
          if ($total_characters > $options['character_limit']) {
            $characters_over = $total_characters - $options['character_limit'];
            $truncate_description = $description_characters - $characters_over;
            if ($truncate_description < $minimum_limit) {
              $new_character_limit = $minimum_limit;
            } else {
              $new_character_limit = $options['character_limit'] - ($total_characters - $description_characters);
            }
            $new_description = wordwrap($item['description'], $new_character_limit, '[:CUT:]');
            $new_description = explode('[:CUT:]', $new_description);
            $new_description = $new_description[0] . '...';
            $item['description'] = $new_description;
          }
        }
				$link_format = str_replace('%comment%', $item['description'], $link_format);
			}
			if (stripos($link_format, '%tags%') != false && is_array($item['tags'])) {
				$tag_list = '';
				foreach ($item['tags'] as $tag) {
					$tag_list .= '<a class="publish2_link" href="'. $tag['link'] . '"';
					$tag_list .= '>' . $tag['name'] . '</a>' . ", ";
				}
				$tag_list = rtrim($tag_list, ', ');
				$link_format = str_replace('%tags%', $tag_list, $link_format);
			}
			if (stripos($link_format, '%quote%') != false) {
				$link_format = str_replace('%quote%', $item['quote'], $link_format);
			}

			$links .= $link_format;
			
			// Show "Share on: Twitter, Facebook, LinkedIn" buttons if the user wants
			if ($options['share_buttons']) {
  			$encoded_title = urlencode($item['title']);
  			if ($item['short_url']) {
  			  $encoded_link = urlencode($item['short_url']);
  			} else {
  			  $encoded_link = urlencode($item['link']);
  			}
  			if ($item['description']) {
  			  $encoded_description = urlencode($item['description']);
  			} else {
  			  $encoded_description = '';
  			}
  			if ($item['publication_name']) {
  			  $encoded_publication = urlencode($item['publication_name']);
  			} else {
  			  $encoded_publication = '';
  			}
  			if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
  			  $links .= '<div class="publish2_share">Retweet on: ';
    			$links .= '<a href="http://twitter.com/?status=RT+' . $encoded_publication . '+' . $encoded_title . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_twitter" src="' . PUBLISH2_URL . '/img/twitter_s16.png" /></a> ';
    			$links .= '</div>';
  			} else {
    			$links .= '<div class="publish2_share">Share on: ';
    			$links .= '<a href="http://twitter.com/?status=' . $encoded_title . '+' . $encoded_link . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_twitter" src="' . PUBLISH2_URL . '/img/twitter_s16.png" /></a> ';
    			$links .= '<a href="http://www.facebook.com/sharer.php?t=' . $encoded_title . '&u=' . $encoded_link . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_facebook" src="' . PUBLISH2_URL . '/img/facebook_s16.png" /></a> ';
    			$links .= '<a href="http://www.linkedin.com/shareArticle?mini=true'
    			        . '&url=' . $encoded_link 
    			        . '&title=' . $encoded_title
    			        . '&summary=' . $encoded_description
    			        . '&source=' . $encoded_publication . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_linkedin" src="' . PUBLISH2_URL . '/img/linkedin_s16.png" /></a>';
    			$links .= '</div>';
    		}
		  }
			
			if ($display_list) {
			  $links .= '</li>'; 
			} else {
			  $links .= '</div>';
			}
				
		}
		
		if ($display_list) { $links .= '</ul>'; }
		
	} else if (($settings_source == 'widget' && !$options['link_format']) || $display_list) {
		
		$links .= '<ul>';
		
		$i = 0;
		
		foreach ($json_data as $item) {
			
			if ($options['shorturl'] && $item['short_url']) {
				$link = $item['short_url'];
			} else {
				$link = $item['link'];
			}
			
			$i++;
			if ($i > $total_links_displayed) { continue; }
			
			$links .= '<li class="publish2_item">';
			
			// Character truncation on the description if enabled
			if ($options['character_limit'] && $item['description']) {
			  $total_characters = 0;
			  $total_characters = $total_characters + strlen($item['title']);
			  if ($options['publication_name']) {
			    $total_characters = $total_characters + strlen($item['publication_name']);
			  }
			  if ($options['publication_date']) {
			    $total_characters = $total_characters + strlen($item['publication_date']);
			  }
			  if ($options['journalist_full_name']) {
			    $total_characters = $total_characters + strlen($item['journalist_full_name']);
			  }
        $description_characters = strlen($item['description']);
        $total_characters = $total_characters + $description_characters;
        if ($total_characters > $options['character_limit']) {
          $characters_over = $total_characters - $options['character_limit'];
          $truncate_description = $description_characters - $characters_over;
          if ($truncate_description < $minimum_limit) {
            $new_character_limit = $minimum_limit;
          } else {
            $new_character_limit = $options['character_limit'] - ($total_characters - $description_characters);
          }
          $new_description = wordwrap($item['description'], $new_character_limit, '[:CUT:]');
          $new_description = explode('[:CUT:]', $new_description);
          $new_description = $new_description[0] . '...';
          $description = $new_description;
        }
      } else {
        $description = $item['description'];
      }
			
			// Check to see whether what was saved was a tweet. If so, display differently
			if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
				$links .= '<span class="publish2_tweet">';
				if (strlen($item['publication_name']) != 0) {
					$links .= '<a class="publish2_link" href="'. $item['link'] . '">' . $item['publication_name'] . '</a>: ';
				}
				$links .= $item['title'] . '</span>';
				if ($options['publication_name'] && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<div><span class="publish2_story_publication_name">Twitter</span> | <span class="publish2_story_publication_date">' . $item['publication_date'] . '</span></div>';
				} else if (!$options['publication_name'] && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<div><span class="publish2_story_publication_date">' . $item['publication_date'] . '</span></div>';
				} else if ($options['publication_name'] && !$options['publication_date']) {
					$links .= '<div><span class="publish2_story_publication_name">Twitter</span></div>';
				}
			} else {
			
				$links .= '<a class="publish2_link publish2_story_headline" href="'. $link . '">' . $item['title'] . '</a>';
				if ($video_width != 0) {
					$embed = publish2_getembedhtml($item['link'], $video_width);
					if ($embed) { $links .= '<div class="publish2_video_embed">' . $embed . '</div>';	}
				}

				//whether to show the pub name, date, both or none. Includes a filter if the user didn't save publication_name or publication_date
				if (($options['publication_name'] && strlen($item['publication_name']) != 0) && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<div><span class="publish2_story_publication_name">' . $item['publication_name'] . '</span>' . ' | ' . '<span class="publish2_story_publication_date">' . $item['publication_date'] . '</span></div>';
				} else if (($options['publication_name'] && strlen($item['publication_name']) != 0) && ($options['publication_date'] && strlen($item['publication_date']) == 0)) {
					$links .= '<div><span class="publish2_story_publication_name">' . $item['publication_name'] . '</span></div>';
				} else if (($options['publication_name'] && strlen($item['publication_name']) == 0) && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<div>' . '<span class="publish2_story_publication_date">' . $item['publication_date'] . '</span>' . '</div>';
				} else if (($options['publication_name'] && strlen($item['publication_name']) != 0) && (!$options['publication_date'])) {
					$links .= '<div>' . '<span class="publish2_story_publication_name">' . $item['publication_name'] . '</span>' . '</div>';
				} else if (($options['publication_date'] && strlen($item['publication_date']) != 0) && (!$options['publication_name'])) {
					$links .= '<div>' . '<span class="publish2_story_publication_date">' . $item['publication_date'] . '</span>' . '</div>';
				}
			
			}

			// Whether to show comments or not. Includes to drop the whole graf if there isn't a comment
			if (($options['description'] && strlen($item['description']) != 0) && $options['journalist_full_name']) {
				$links .= '<div>' . '<a href="'. $item['journalist_profile'] . '" class="publish2_journalist_profile">' . $item['journalist_full_name'] . '</a>' . ' says: ';
				$links .= '<span class="publish2_story_description">' . $description . '</span>' . '</div>';
			}
			else if ($options['description']) {
			  $links .= '<div>' . '<span class="publish2_story_description">' . $description . '</span>' . '</div>';
			}
			
			if ($options['quote'] && strlen($item['quote']) != 0) {
				$links .= '<div><blockquote class="publish2_story_quote">' . $item['quote'] . '</blockquote></div>';
			}

			// Option whether to display the tags or not
			// !is_array($item['tags'][0]) is a check for legacy feed
			if ($options['tags'] && is_array($item['tags']) && !is_array($item['tags'][0])) {
				$links .= '<div>' . 'Tags: ';
				$tag_list = ''; //dislaying tags 
				foreach ($item['tags'] as $tag) {
					$tag_list .= '<a class="publish2_link publish2_story_tags" href="'. $tag['link'] . '">' . $tag['name'] . '</a>' . ", ";
				}
				$tag_list = rtrim($tag_list, ', ');
				$links .= $tag_list;
				$links .= '</div>';		
			}
			
			// Show "Share on: Twitter, Facebook, LinkedIn" buttons if the user wants
			if ($options['share_buttons']) {
  			$encoded_title = urlencode($item['title']);
  			if ($item['short_url']) {
  			  $encoded_link = urlencode($item['short_url']);
  			} else {
  			  $encoded_link = urlencode($item['link']);
  			}
  			if ($item['description']) {
  			  $encoded_description = urlencode($item['description']);
  			} else {
  			  $encoded_description = '';
  			}
  			if ($item['publication_name']) {
  			  $encoded_publication = urlencode($item['publication_name']);
  			} else {
  			  $encoded_publication = '';
  			}
  			if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
  			  $links .= '<div class="publish2_share">Retweet on: ';
    			$links .= '<a href="http://twitter.com/?status=RT+' . $encoded_publication . '+' . $encoded_title . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_twitter" src="' . PUBLISH2_URL . '/img/twitter_s16.png" /></a> ';
    			$links .= '</div>';
  			} else {
    			$links .= '<div class="publish2_share">Share on: ';
    			$links .= '<a href="http://twitter.com/?status=' . $encoded_title . '+' . $encoded_link . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_twitter" src="' . PUBLISH2_URL . '/img/twitter_s16.png" /></a> ';
    			$links .= '<a href="http://www.facebook.com/sharer.php?t=' . $encoded_title . '&u=' . $encoded_link . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_facebook" src="' . PUBLISH2_URL . '/img/facebook_s16.png" /></a> ';
    			$links .= '<a href="http://www.linkedin.com/shareArticle?mini=true'
    			        . '&url=' . $encoded_link 
    			        . '&title=' . $encoded_title
    			        . '&summary=' . $encoded_description
    			        . '&source=' . $encoded_publication . '">';
    			$links .= '<img width="16px" height="16px" style="border:none;" class="publish2_share_linkedin" src="' . PUBLISH2_URL . '/img/linkedin_s16.png" /></a>';
    			$links .= '</div>';
    		}
		  }
			
			$links .= '</li>';
		}
		$links .= '</ul>';

	} else {
		
		if ($display_list) { $links .= '<ul>'; }
		
		$i = 0;

		foreach ($json_data as $item) {
			
			if ($options['shorturl'] && $item['short_url']) {
				$link = $item['short_url'];
			} else {
				$link = $item['link'];
			}
			
			$i++;
			if ($i > $total_links_displayed) { continue; }

			if ($display_list) { $links .= '<li class="publish2_item">'; }
				
			// Check to see whether what was saved was a tweet. If so, display the publication name alongside the title
			if (preg_match("/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/", $item['link']) == 1) {
				if ($settings_source == 'page') { $links .= '<h3>'; } else { $links .= '<h4>'; }
				$links .= '<span class="publish2_tweet">';
				if (strlen($item['publication_name']) != 0) {
					$links .= '<a class="publish2_link" href="'. $item['link'] . '">' . $item['publication_name'] . '</a>: ';
				}
				$links .= $item['title'];
				$links .= '</span>';
				if ($settings_source == 'page') { $links .= '</h3>'; } else { $links .= '</h4>'; }
				if ($options['publication_name'] && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<p><span class="publish2_story_publication_name">Twitter</span> | <span class="publish2_story_publication_date">' . $item['publication_date'] . '</span></p>';
				} else if (!$options['publication_name'] && ($options['publication_date'] == true && strlen($item['publication_date']) != 0)) {
					$links .= '<p><span class="publish2_story_publication_date">' . $item['publication_date'] . '</span></p>';
				} else if ($options['publication_name'] && !$options['publication_date']) {
					$links .= '<p><span class="publish2_story_publication_name">Twitter</span></p>';
				}
			} else {

				if ($settings_source == 'page') { $links .= '<h3>'; } else { $links .= '<h4>'; }
				$links .= '<a class="publish2_link publish2_story_headline" href="'. $link . '">' . $item['title'] . '</a>';
				if ($settings_source == 'page') { $links .= '</h3>'; } else { $links .= '</h4>'; }
				if ($video_width != 0) {
					$embed = publish2_getembedhtml($item['link'], $video_width);
					if ($embed) { $links .= '<div class="publish2_video_embed">' . $embed . '</div>';	}
				}

				//whether to show the pub name, date, both or none. Includes a filter if the user didn't save publication_name or publication_date
				if (($options['publication_name'] && strlen($item['publication_name']) != 0) && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<p>';
					$links .= '<span class="publish2_story_publication_name">' . $item['publication_name'] . '</span>' . ' | ' . '<span class="publish2_story_publication_date">' . $item['publication_date'] . '</span>';
					$links .= '</p>';
				} else if (($options['publication_name'] && strlen($item['publication_name']) != 0) && ($options['publication_date'] && strlen($item['publication_date']) == 0)) {
					$links .= '<p>';
					$links .= '<span class="publish2_story_publication_name">' . $item['publication_name'] . '</span>';
					$links .= '</p>';
				} else if (($options['publication_name'] && strlen($item['publication_name']) == 0) && ($options['publication_date'] && strlen($item['publication_date']) != 0)) {
					$links .= '<p>';
					$links .= '<span class="publish2_story_publication_date">' . $item['publication_date'] . '</span>';
					$links .= '</p>';
				} else if (($options['publication_name'] && strlen($item['publication_name']) != 0) && (!$options['publication_date'])) {
					$links .= '<p>';
					$links .= '<span class="publish2_story_publication_name">' . $item['publication_name'] . '</span>';
					$links .= '</p>';
				} else if (($options['publication_date'] && strlen($item['publication_date']) != 0) && (!$options['publication_name'])) {
					$links .= '<p>';
					$links .= '<span class="publish2_story_publication_date">' . $item['publication_date'] . '</span>';
					$links .= '</p>';
				}
			
			}

			//whether to show comments or not. Includes to drop the whole graf if there isn't a comment
			if (($options['description'] && strlen($item['description']) != 0) && $options['journalist_full_name']) {
				$links .= '<p>';
				$links .= '<a href="'. $item['journalist_profile'] . '" class="publish2_journalist_profile">' . $item['journalist_full_name'] . '</a>' . ' says: ';
				$links .= '<span class="publish2_story_description">' . $item['description'] . '</span>';
				$links .= '</p>';
			} else if ($options['description'] == true && strlen($item['description']) != 0) {
				$links .= '<p>';
				$links .= '<span class="publish2_story_description">' . $item['description'] . '</span>';
				$links .= '</p>';
			}
			
			if ($options['quote'] && strlen($item['quote']) != 0) {
				$links .= '<blockquote class="publish2_story_quote">' . $item['quote'] . '</blockquote>';
			}

			// Option whether to display the tags or not
      // !is_array($item['tags'][0]) is check for legacy JSON feed data
			if ($options['tags'] && is_array($item['tags']) && !is_array($item['tags'][0])) {
				$links .= '<p>';
				$links .= 'Tags: ';
				$tag_list = ''; //dislaying tags 
				foreach ($item['tags'] as $tag) {
					$tag_list .= '<a class="publish2_link publish2_story_tags" href="'. $tag['link'] . '">' . $tag['name'] . '</a>' . ", ";
				}
				$tag_list = rtrim($tag_list, ', ');
				$links .= $tag_list;
				$links .= '</p>';		
			}
			if ($display_list) { $links .= '</li>'; }

		}
		if ($display_list) { $links .= '</ul>'; }

	}
	
	if ($settings_source == 'page' || $settings_source == 'filter') {
		return $links;
	} else {
		echo $links;
	}
	
}

?>
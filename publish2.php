<?php
/*
Plugin Name: Publish2
Plugin URI: http://wordpress.org/extend/plugins/publish2/
Description: Brings collaborative curation from <a href="http://www.publish2.com/">Publish2</a> to your WordPress-powered blog or website. <a href="/wp-admin/profile.php">Enable Link Assist</a> to quickly add previously-saved links as you write a post, or display links with a <a href="/wp-admin/widgets.php">sidebar widget</a>, the ability to <a href="/wp-admin/edit-pages.php">append links at the bottom of any page</a>, or a flexible PHP function.
Version: 1.5.1
Author: Daniel Bachhuber
Author URI: http://www.publish2.com/journalists/daniel-bachhuber/

*/

define('PUBLISH2_URL', plugins_url(plugin_basename(dirname(__FILE__))));
define('PUBLISH2_VERSION', '1.5.1');
require_once('php/p2functions.php');

/**
 * Various things that need to be done every time the plugin is initialized in WordPress
 */
function publish2_init() {
	if (is_admin()) {
	  // Add the Javascript we need to the WordPress admin header with cache busters
		wp_enqueue_script('jquery');
		wp_enqueue_script('publish2', PUBLISH2_URL . '/js/publish2.js', array('jquery'), PUBLISH2_VERSION);		
		wp_enqueue_script('p2_linkassist', PUBLISH2_URL . '/js/linkassist.js', array('jquery', 'publish2'), PUBLISH2_VERSION);
		wp_enqueue_script('p2_json', PUBLISH2_URL . '/js/json2.js');
		// Default settings @todo revisit these defaults
		$p2_defaults = array(
			'feedlink' => 'http://www.publish2.com/newsgroups/publishing-2-0-reading',
			'link_count' => 5,
			'publication_name' => true,
			'publication_date' => true,
			'journalist_full_name' => true,
			'description' => true,
			'tags' => true,
			'link_format' => null,
			'settings_source' => null,
			'display_list' => false
		);
		// @todo move this to first plugin initialization
		$testoptions = get_option('publish2_settings');
		if (!$testoptions) {
			update_option('publish2_settings', $p2_defaults);
		}
	} else {
	  // Javascript we want enqueued on the frontend of the WordPress instance
	  wp_enqueue_script('p2_json', PUBLISH2_URL . '/js/json2.js');
	  wp_enqueue_script('p2_pagination', PUBLISH2_URL . '/js/pagination.js', array('jquery'), PUBLISH2_VERSION);
	}
	
	$options = get_option('publish2_settings');
	
	// Register an hourly cron event if there isn't already one scheduled
	if (!wp_next_scheduled('publish2_hourly') && $options['import_trigger_method'] == 1) {
		wp_schedule_event(time(), 'hourly', 'publish2_hourly');
	} else if ($options['import_trigger_method'] == 0) {
	  if (wp_next_scheduled('publish2_hourly')) {
  		wp_clear_scheduled_hook('publish2_hourly');
  	}
  	if ($options['enable_link_importing']) {
      publish2_publishsingle($options);
  	}
	}

  // We check this every time because WP_Cron is flaky
	if ($options['enable_link_roundups']) {
		publish2_publishroundup($options);
	}
	
	// If the error reporting tables we need don't exist, create them
	publish2_createtable('publish2_error_log');
}

/**
 * WordPress hook to add some custom CSS to the header of the WordPress admin
 */
function publish2_cssadminheader() {
	echo "<link rel='stylesheet' href='" . PUBLISH2_URL . "/css/publish2.css' type='text/css' media='all' />";
	echo "<link rel='stylesheet' href='" . PUBLISH2_URL . "/css/linkassist.css' type='text/css' media='all' />";
	echo "<!--[if lte IE 6]><link rel='stylesheet' href='" . PUBLISH2_URL . "/css/ie.css' type='text/css' media='all' /><![endif]-->";
}

/**
 * Link Assist box on the Post page allows the user to quickly add link journalism to content
 */
function publish2_postlinkassistbox() {
	
	// Get the user's information so that we can pull their Publish2 settings from the database
	global $post;
	$post_id = $post->ID;
	$current_user = wp_get_current_user();
	$user_id = $current_user->ID;
	
	$defaults = get_option('publish2_widget_settings');
	
	// Get the feedlink associated with the user. If that doesn't work, defer to default for the instance, and then the most recent newswire
	$feedlink = get_usermeta($user_id, 'publish2_url');
	if (!$feedlink || $feedlink == 'http://' || $feedlink == '') {
		$feedlink = $defaults['feedlink'];
		if (!$feedlink || $feedlink == 'http://' || $feedlink == '') {
				$feedlink = 'http://www.publish2.com/recent';
		}
	}
	
	// Link Assist settings from the User Profile view
	$linkassist_shorturl = get_usermeta($user_id, 'p2_linkassist_shorturl');
	$linkassist_publication = get_usermeta($user_id, 'p2_linkassist_publication');
	$linkassist_date = get_usermeta($user_id, 'p2_linkassist_date');
	$linkassist_journalist = get_usermeta($user_id, 'p2_linkassist_journalist');
	$linkassist_comment = get_usermeta($user_id, 'p2_linkassist_comment');
	$linkassist_quote = get_usermeta($user_id, 'p2_linkassist_quote');
	$linkassist_tags = get_usermeta($user_id, 'p2_linkassist_tags');
	$linkassist_link_format = get_usermeta($user_id, 'p2_linkassist_link_format');
	$video_width = get_usermeta($user_id, 'p2_video_width');
	
	// Check to see whether the settings have been used before. If not, fill in with the defaults from the widget
	if (!$linkassist_shorturl) {
		$linkassist_shorturl = $defaults['short_url'];
		if ($linkassist_shorturl == 1) { $linkassist_shorturl = 'on'; }
	}
	if (!$linkassist_publication) {
		$linkassist_publication = $defaults['publication_name'];
		if ($linkassist_publication == 1) { $linkassist_publication = 'on'; }
	}
	if (!$linkassist_date) {
		$linkassist_date = $defaults['publication_date'];
		if ($linkassist_date == 1) { $linkassist_date = 'on'; }
	}
	if (!$linkassist_journalist) {
		$linkassist_journalist = $defaults['journalist_full_name'];
		if ($linkassist_journalist == 1) { $linkassist_journalist = 'on'; }
	}
	if (!$linkassist_comment) {
		$linkassist_comment = $defaults['description'];
		if ($linkassist_comment == 1) { $linkassist_comment = 'on'; }
	}
	if (!$linkassist_quote) {
		$linkassist_quote = $defaults['quote'];
		if ($linkassist_quote == 1) { $linkassist_quote = 'on'; }
	}
	if (!$linkassist_tags) {
		$linkassist_tags = $defaults['tags'];
		if ($linkassist_tags == 1) { $linkassist_tags = 'on'; }
	}
	if (!$video_width) { $video_width = 425; }
	
	?>
	
	<div id="p2-link_assist-topnav">
	  <?php $p2_reset_action = "publish2_linksnav('reset');return false;"; ?>
		<a href="javascript:void(0);" onclick="<?php echo $p2_reset_action; ?>" class="resetbutton" id="p2-link_assist-reset"><img src="<?php echo PUBLISH2_URL; ?>/img/loading_static-red.gif" /></a>
	<div id="p2-link_assist-navwrap">
		<div id="p2-links_header">
			<span id="p2_links_title">Links at your fingertips</span>&nbsp;
			<a href="javascript:void(0)" onclick="publish2_urlsource(1);return false;" id="p2-source-edit" style="display:none;">Edit</a>
		</div>
		<div id="p2-source-option" style="display:none;">
			URL: <input type="text" id="publish2-feedlink" name="publish2-feedlink" size="17" value="<?php echo $feedlink; ?>" />
			<a href="javascript:void(0)" onclick="publish2_urlsource(2);return false;" class="button">OK</a>&nbsp;
			<a href="javascript:void(0)" onclick="publish2_urlsource(3);return false;">Cancel</a>
		</div>
	</div>
	</div>
	<div id="p2-link_assist-inside">
    <input type="hidden" name="publish2-nonce" id="publish2-nonce" value="<?php echo wp_create_nonce('publish2-nonce'); ?>" />

<div id="p2-pagination"></div>

<div id="p2-network_nav"></div>
	
<div id="publish2-link_assist-links">
	
	<div id="p2-welcome_splash">
		
		<img src="<?php echo PUBLISH2_URL; ?>/img/publish2_h250.gif" width="250" height="70" style="padding-top:15px;">
		
	</div>
	
</div>

<div id="p2-link_count"></div>
	
	<script type="text/javascript">
		// Loads the list of links when the page has completely loaded
		var feedlink = "<?php echo $feedlink; ?>";
		jQuery(document).ready(function(){
			publish2_listlinks(feedlink, null, null);
			jQuery('#p2-link_assist-reset').show();
		});
		
		function filter_enter(event) {
			var key = event.keyCode || e.which;
			if (key == 13) {
				publish2_linksnav('filter');
				return false;
			}	
		}
		
	</script>
	
</div>

<?php // @todo Filtering is disabled until we implement search through Link Assist ?>
<div id="p2-link_assist-bottomnav" style="display:none;">
	
	<div id="p2-link_assist-navwrap">
			
		<?php $p2_filter_action = "publish2_linksnav('filter');return false;"; ?>
		Tag: <input type="text" id="p2-filter_tag" name="p2-filter_tag" value="" size="18" onkeydown="
			filter_enter(event);
			//@todo ensure this works
		" />
		<a href="javascript:void(0);" onclick="<?php echo $p2_filter_action; ?>"class="button">Filter</a>
	
	</div>
	
</div>
	
	<div id="p2-temp_settings" style="display:none;"></div>
	
	<?php /* Store user preferences in the DOM so they're accessible by any Javascript that needs them */ ?>
	<div id="p2-user_settings" style="display:none;">
	
		<span id="p2-linkassist_shorturl"><?php echo $linkassist_shorturl; ?></span>
		<span id="p2-linkassist_publication"><?php echo $linkassist_publication; ?></span>
		<span id="p2-linkassist_date"><?php echo $linkassist_date; ?></span>
		<span id="p2-linkassist_journalist"><?php echo $linkassist_journalist; ?></span>
		<span id="p2-linkassist_comment"><?php echo $linkassist_comment; ?></span>
		<span id="p2-linkassist_quote"><?php echo $linkassist_quote; ?></span>	
		<span id="p2-linkassist_tags"><?php echo $linkassist_tags; ?></span>
		<span id="p2-linkassist_link_format"><?php echo $linkassist_link_format; ?></span>	
		<span id="p2-video_width"><?php echo $video_width; ?></span>

	</div>
	
 <?php
	
}

/**
 * Add the Link Assist box to the Edit Post view
 */
function publish2_addpostlinkassistbox() {
	
	// Assess whether the current user has Link Assist enabled or not
	$current_user = wp_get_current_user();
	$user_id = $current_user->ID;
	$linkassist = get_usermeta($user_id, 'publish2_linkassist');
	
	// If it's possible to and the user wants it, add Link Assist to edit post page
	if (function_exists('add_meta_box') && $linkassist != 'off') {
		$wp_version = get_bloginfo('version');
		// Sidebar support on the write panel isn't added until WP 2.7
		if (version_compare($wp_version, '2.7', '>=')) {
			add_meta_box('publish2_linkassist', 'Publish2 Link Assist', 'publish2_postlinkassistbox', 'post', 'side', 'high');
		} else {
			add_meta_box('publish2_linkassist', 'Publish2 Link Assist', 'publish2_postlinkassistbox', 'post', 'normal', 'high');
		}
	} 
}

/**
 * Place a Publish2 settings box at the bottom of Edit Page view
 */
function publish2_savepostmetabox($post_id) {

  // Get the current user's information
	$current_user = wp_get_current_user();
	$user_id = $current_user->ID;

	if (!wp_verify_nonce( $_POST['publish2-nonce'], 'publish2-nonce')) {
		return $post_id;  
	}
        
	if ($_POST['post_type'] == 'post') {	
		if (!current_user_can( 'edit_post', $post_id ))
			return $post_id;  
	}

	// Save the currently used feedlink back to the user's profile
	$feedlink = $_POST['publish2-feedlink'];
	$feedlink = publish2_cleanurl($feedlink);
	update_usermeta($user_id, 'publish2_url', $feedlink);
		
}

/** 
 * Add the settings box to the post edits
 */
function publish2_addpostmetabox() {
	if (function_exists('add_meta_box')) {
		add_meta_box('publish2_link-assist', 'Publish2 Link Assist', 'p2_post_meta_box', 'post', 'normal', 'high');
	} 
}

/**
 * Add a metabox to the create a new post or page page
 */
function publish2_pagemetabox() {
	global $post;
	$post_id = $post->ID;
	
	// Get the default settings from the settings page if they aren't yet established with the page
	// @todo There's got to be a more efficient way of doing this. Compare arrays?
	$defaults = get_option('publish2_settings');

	$link_page = get_post_meta($post_id, '_link_page');
	if (!$link_page) { $link_page[0] = false; }
	
	$feedlink = get_post_meta($post_id, '_feedlink');
	if (!$feedlink) { $feedlink[0] = $defaults['feedlink']; }
	
	$shorturl = get_post_meta($post_id, '_shorturl');
	if (!$shorturl) { $shorturl[0] = false; }
	
	$link_count = get_post_meta($post_id, '_link_count');
	if (!$link_count) { $link_count[0] = 5; }
		
	$advanced_options = get_post_meta($post_id, '_advanced_options');
	if (!$advanced_options) { $advanced_options[0] = 0; }
	
	$publication_name = get_post_meta($post_id, '_publication_name');
	if (!$publication_name) { $publication_name[0] = true; }
	
	$publication_date = get_post_meta($post_id, '_publication_date');
	if (!$publication_date) { $publication_date[0] = true; }
		
	$journalist_full_name = get_post_meta($post_id, '_journalist_full_name');
	if (!$journalist_full_name) { $journalist_full_name[0] = true; }
		
	$description = get_post_meta($post_id, '_description');
	if (!$description) { $description[0] = true; }
	
	$quote = get_post_meta($post_id, '_quote');
	if (!$quote) { $quote[0] = true; }
	
	$tags = get_post_meta($post_id, '_tags');
	if (!$tags) { $tags[0] = true; }
	
	$link_format = get_post_meta($post_id, '_link_format');
	
	$display_list = get_post_meta($post_id, '_display_list');
	if (!$display_list) { $display_list[0] = false; }
	
	$embed_video = get_post_meta($post_id, '_embed_video');
	if (!$embed_video) { $embed_video[0] = false; }
	
	$video_width = get_post_meta($post_id, '_video_width');
	if (!$video_width) { $video_width[0] = 425; }
	
	?>

	<fieldset id="publish2-div">
		<div>

		<p><label for="publish2-link_page" style="line-height:20px;display:block;">Display links from Publish2&nbsp;&nbsp;&nbsp;
		<select name="publish2-link_page">
			<option value="1" <?php if ($link_page[0]) { echo 'selected="selected"'; }?>>On</option>
			<option value="0" <?php if (!$link_page[0]) { echo 'selected="selected"'; }?>>Off</option>
			</select>
		</label></p>
		
		<p><label for="publish2-feedlink" style="padding-bottom:5px;">Source URL for links (required):<br / >
			<input type="text" id="publish2-feedlink" name="publish2-feedlink" class="publish2URL" size="60" value="<?php echo $feedlink[0]; ?>"/><span id="publish2URLnotice"></span>
		</label></p>
		
		<p><label for="publish2-shorturl" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-shorturl" name="publish2-shorturl" <?php if ($shorturl[0]) echo ' checked="checked"'; ?> />&nbsp;&nbsp;&nbsp;Use short URLs for click tracking
		</label></p>

		<p><label for="publish2-link_count" style="padding-bottom:5px;line-height:20px;display:block;">Number of links to display:&nbsp;&nbsp;&nbsp;
			<input type="text" id="publish2-link_count" name="publish2-link_count" value="<?php echo $link_count[0]; ?>"/>
		</label></p>
		
		<p><label for="publish-advanced_options" style="padding-bottom:5px;line-height:20px;display:block;">Select link elements to display:&nbsp;&nbsp;
			<select name="publish2-advanced_options" class="publish2-advanced_options">
				<option value="0" <?php if (!$advanced_options[0]) { echo 'selected="selected"'; }?>>Basic Options</option>
				<option value="1" <?php if ($advanced_options[0]) { echo 'selected="selected"'; }?>>Advanced Options</option>
			</select>
		</label></p>
		
		<div class="normal_options" <?php if ($advanced_options[0]) { echo 'style="display:none;"'; }?>>
		
		<p><label for="publish2-publication_name" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-publication_name" name="publish2-publication_name" <?php if ($publication_name[0]) echo ' checked="checked" '; ?>/>&nbsp;&nbsp;&nbsp;Publication name
		</label></p>
			
		<p><label for="publish2-publication_date" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-publication_date" name="publish2-publication_date" <?php if ($publication_date[0]) echo ' checked="checked" '; ?>/>&nbsp;&nbsp;&nbsp;Publication date
		</label></p>
			
		<p><label for="publish2-journalist_full_name" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-journalist_full_name" name="publish2-journalist_full_name" <?php if ($journalist_full_name[0]) echo ' checked="checked" '; ?>/>&nbsp;&nbsp;&nbsp;User name
		</label></p>

		<p><label for="publish2-description" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-description" name="publish2-description" <?php if ($description[0]) echo ' checked="checked" '; ?> />&nbsp;&nbsp;&nbsp;Comment
		</label></p>
		
		<p><label for="publish2-quote" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-quote" name="publish2-quote" <?php if ($quote[0]) echo ' checked="checked" '; ?> />&nbsp;&nbsp;&nbsp;Quote
		</label></p>
			
		<p><label for="publish2-tags" style="padding-bottom:5px;line-height:20px;display:block;">
			<input type="checkbox" id="publish2-tags" name="publish2-tags" <?php if ($tags[0]) echo ' checked="checked" '; ?>/>&nbsp;&nbsp;&nbsp;Tags
		</label></p>

		</div>
		
		<div class="advanced_options" <?php if ($advanced_options[0]) { echo 'style="display:none;"'; }?>>
		
		<p><label for="publish2-link_format" style="padding-bottom:5px;line-height:20px;">
			<textarea cols="60" rows="6" id="publish2-link_format" name="publish2-link_format"><?php echo $link_format[0]; ?></textarea>
			<br />Accepts <a href="/wp-content/plugins/publish2/img/screenshots/example-tokens_page.jpg" target="_blank">tokens</a> %headline%, %source%, %date%, %journalist%, %comment%, %quote%, %tags% and basic HTML. Optionally, if you save links to videos, %video% will determine the placement.
		</label></p>
		
		</div>
		
		<p style="padding-bottom:5px;line-height:20px;display:block;"><span class="normal_options" <?php if ($advanced_options[0]) { echo 'style="display:none;"'; }?>><input type="checkbox" id="publish2-embed_video" name="publish2-embed_video" <?php if ($embed_video[0]) echo ' checked="checked" '; ?>/>&nbsp;&nbsp;&nbsp;Embed video (if applicable).</span> Video width: <input type="text" id="publish2-video_width" name="publish2-video_width" value="<?php echo $video_width[0]; ?>" maxlength="3" />&nbsp;pixels</p>

		<input type="hidden" name="publish2-nonce" id="publish2-nonce" value="<?php echo wp_create_nonce('publish2-nonce'); ?>" />
		
		</div>	
	</fieldset>

<?php }

/**
 * Place a settings box at the bottom of an edit page
 */
function publish2_savepagemetabox($post_id) {
        
	if (!wp_verify_nonce( $_POST['publish2-nonce'], 'publish2-nonce')) {
		return $post_id;  
	}
        
	if ($_POST['post_type'] == 'page') {	
		if ( !current_user_can( 'edit_page', $post_id ))  
			return $post_id;  
	}

	// Get the variables and save them to WordPress' custom fields
	$link_page = $_POST['publish2-link_page'];
	update_post_meta($post_id, '_link_page', $link_page);
	
	$feedlink = $_POST['publish2-feedlink'];
	$feedlink = publish2_cleanurl($feedlink);
	update_post_meta($post_id, '_feedlink', $feedlink);
	
	$shorturl = $_POST['publish2-shorturl'];
	update_post_meta($post_id, '_shorturl', $shorturl);
	
	$link_count = $_POST['publish2-link_count'];
	if ($link_count < 1) { $link_count = 1; }
	if ($link_count > 100) { $link_count = 100; }
	update_post_meta($post_id, '_link_count', $link_count);
	
	$advanced_options = $_POST['publish2-advanced_options'];
	update_post_meta($post_id, '_advanced_options', $advanced_options);
	
	$link_format = stripslashes(strip_tags($_POST['publish2-link_format'], '<h1><h2><h3><h4><h5><h6><b><i><strong><blockquote><em><span><a><p><ul><ol><li><br><div>'));
	update_post_meta($post_id, '_link_format', $link_format);
	
	$publication_name = $_POST['publish2-publication_name'];
	update_post_meta($post_id, '_publication_name', $publication_name);
	
	$publication_date = $_POST['publish2-publication_date'];
	update_post_meta($post_id, '_publication_date', $publication_date);
	
	$journalist_full_name = $_POST['publish2-journalist_full_name'];
	update_post_meta($post_id, '_journalist_full_name', $journalist_full_name);
	
	$description = $_POST['publish2-description'];
	update_post_meta($post_id, '_description', $description);
	
	$quote = $_POST['publish2-quote'];
	update_post_meta($post_id, '_quote', $quote);
	
	$tags = $_POST['publish2-tags'];
	update_post_meta($post_id, '_tags', $tags);
	
	$display_list = $_POST['publish2-display_list'];
	update_post_meta($post_id, '_display_list', $display_list);
	
	$embed_video = $_POST['publish2-embed_video'];
	update_post_meta($post_id, '_embed_video', $embed_video);
	
	$video_width = $_POST['publish2-video_width'];
	if ($video_width == '') { $video_width = 425; }
	update_post_meta($post_id, '_video_width', $video_width);
	
}

/** 
 * Hook to add Publish2 settings to Edit Page view
 */
function publish2_addpagemetabox() {
	if (function_exists('add_meta_box')) {
		add_meta_box('publish2_settings', 'Publish2 Settings', 'publish2_pagemetabox', 'page', 'normal', 'high');
	} 
}

/**
 * Append links at the bottom of a Page if specified
 */
function publish2_pagelinks($content) {
	global $post;
	$post_id = $post->ID;
	
	$link_page = get_post_meta($post_id, '_link_page');
		$link_page = $link_page[0];
	
	// Only make the rest of the calls if functionality is turned on
	// @todo Make this cleaner
	if ($link_page) {	
		
		$feedlink = get_post_meta($post_id, '_feedlink');
			$feedlink = $feedlink[0];
		$shorturl = get_post_meta($post_id, '_shorturl');
			$shorturl = $shorturl[0];		
		$link_count = get_post_meta($post_id, '_link_count');
			$link_count = $link_count[0];
		$link_format = get_post_meta($post_id, '_link_format');
			$link_format = $link_format[0];
		
		$advanced_options = get_post_meta($post_id, '_advanced_options');
			$advanced_options = $advanced_options[0];
	
		$publication_name = get_post_meta($post_id, '_publication_name');
			$publication_name = $publication_name[0];
		$publication_date = get_post_meta($post_id, '_publication_date');
			$publication_date = $publication_date[0];
		$journalist_full_name = get_post_meta($post_id, '_journalist_full_name');
			$journalist_full_name = $journalist_full_name[0];
		$description = get_post_meta($post_id, '_description');
			$description = $description[0];
		$quote = get_post_meta($post_id, '_quote');
			$quote = $quote[0];
		$tags = get_post_meta($post_id, '_tags');
			$tags = $tags[0];
		
		$display_list = get_post_meta($post_id, '_display_list');
			$display_list = $display_list[0];
		
		$embed_video = get_post_meta($post_id, '_embed_video');
			$embed_video = $embed_video[0];
		$video_width = get_post_meta($post_id, '_video_width');
			$video_width = $video_width[0];

		// If video embedding is set to off, then set the width to zero so we can use that as marker for later
		if ($embed_video != 'on' && !$advanced_options) {
			$video_width = 0;
		}
	
		if (!$advanced_options) {
			$link_format = null;
		} else if ($advanced_options && $link_format == "") {
			$link_format = null;
			$publication_name = true;
			$publication_date = true;
			$journalist_full_name = true;
			$description = true;
			$quote = true;
			$tags = true;
		}
	
	 	$content = $content . '<div id="publish2-links_page">' . publish2($feedlink, $link_count, $publication_name, $publication_date, $journalist_full_name, $description, $tags, $link_format, 'page', $display_list, $video_width, $quote, $shorturl) . '</div>';
		return $content;
	} else {
		return $content;
	}

}

/**
 * Looks at the_content to see if there's a <!--publish2--> in it and replaces it with links if so
 */
function publish2_callthelinks($content) {
	
	$options = get_option('pubish2_settings');
	
	if(false === strpos($content, '<!--publish2-->')) {
		return $content;
	} else {
		$content .= str_replace('<!--publish2-->', publish2( $options['feedlink'], $options['link_count'], $options['publication_name'], $options['publication_date'], $options['journalist_full_name'], $options['description'], $options['tags'], $options['link_format'], 'filter'), $content );
		return $content;
	}
	
}

/**
 * Register a Publish2 class for multiple sidebar widgets
 */
class Publish2_Widget extends WP_Widget {
	
	function Publish2_Widget() {
		
		// Widget setting defaults
		$widget_options = array(
				'classname' => 'publish2',
				'description' => 'Easily curate the best of the web for your readers'
				);

		// Widget control settings
		$control_options = array( 
				'width' => 300, 
				'height' => 350,
				'id_base' => 'publish2-widget'
				);

		// Create the widget
		$this->WP_Widget('publish2-widget', 'Publish2', $widget_options, $control_options);
		
	}
	
	/**
	 * Settings form that lives in the admin's widget manager
	 */
	function form($instance) {
		
		// Widget setting defaults
		// @todo Might want to set these from the settings page if that exists/ is relevant
		$defaults = array(
				'feedlink' => 'http://www.publish2.com/newsgroups/publishing-2-0-reading',
				'shorturl' => false,
				'widget_title' => 'Publish2 Reading List',
				'link_count' => 5,
				'advanced_options' => false,
				'link_format' => null,
				'display_list' => true,
				'publication_name' => true,
				'publication_date' => true,
				'journalist_full_name' => true,
				'description' => true,
				'quote' => true,
				'tags' => true,
				'embed_video' => true,
				'video_width' => 180,
				'character_limit' => null,
				'share_buttons' => false,
				'read_more_text' => 'Read more',
				'read_more_url' => null,
				);
		
		$instance = array_merge($defaults, $instance);
		extract($instance);
		
		?>

		<p><label for="<?php echo $this->get_field_id('widget_title'); ?>">Title (optional):<br /><input type="text" id="<?php echo $this->get_field_id('widget_title'); ?>" size="25" name="<?php echo $this->get_field_name('widget_title'); ?>" value="<?php echo $widget_title; ?>" /></label></p>

		<p><label for="<?php echo $this->get_field_id('feedlink'); ?>">Source URL for links (required):<br /><input type="text" id="<?php echo $this->get_field_id('feedlink'); ?>" name="<?php echo $this->get_field_name('feedlink'); ?>" value="<?php echo $feedlink; ?>" size="25" /></label></p>
		  
		<p>Enable:</p>
		
		<p><label for="<?php echo $this->get_field_id('share_buttons'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('share_buttons'); ?>" name="<?php echo $this->get_field_name('share_buttons'); ?>" <?php if ($share_buttons) echo ' checked="checked"'; ?> />&nbsp;&nbsp;Share on Twitter, Facebook, LinkedIn</label></p>
		
		<?php /*
		<p><label for="<?php echo $this->get_field_id('pagination'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('pagination'); ?>" name="<?php echo $this->get_field_name('pagination'); ?>" <?php if ($pagination) echo ' checked="checked"'; ?> />&nbsp;&nbsp;AJAX pagination</label></p> */ ?>

		<p><label for="<?php echo $this->get_field_id('shorturl'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('shorturl'); ?>" name="<?php echo $this->get_field_name('shorturl'); ?>" <?php if ($shorturl) echo ' checked="checked"'; ?> />&nbsp;&nbsp;Short URLs for click tracking</label></p>

		<p><label for="<?php echo $this->get_field_id('link_count'); ?>">Number of links to display:&nbsp;&nbsp;&nbsp;
			<select id="<?php echo $this->get_field_id('link_count'); ?>" name="<?php echo $this->get_field_name('link_count'); ?>">
				<option <?php if ($link_count == 1) { echo 'selected="selected"'; }?>>1</option>
				<option <?php if ($link_count == 2) { echo 'selected="selected"'; }?>>2</option>
				<option <?php if ($link_count == 3) { echo 'selected="selected"'; }?>>3</option>
				<option <?php if ($link_count == 4) { echo 'selected="selected"'; }?>>4</option>
				<option <?php if ($link_count == 5) { echo 'selected="selected"'; }?>>5</option>
				<option <?php if ($link_count == 6) { echo 'selected="selected"'; }?>>6</option>
				<option <?php if ($link_count == 7) { echo 'selected="selected"'; }?>>7</option>
				<option <?php if ($link_count == 8) { echo 'selected="selected"'; }?>>8</option>
				<option <?php if ($link_count == 9) { echo 'selected="selected"'; }?>>9</option>
				<option <?php if ($link_count == 10) { echo 'selected="selected"'; }?>>10</option>
				<option <?php if ($link_count == 15) { echo 'selected="selected"'; }?>>15</option>
				<option <?php if ($link_count == 20) { echo 'selected="selected"'; }?>>20</option>
				<option <?php if ($link_count == 25) { echo 'selected="selected"'; }?>>25</option>
			</select>
		</label></p>

		<p>Basic display options:</p>

		<p><label for="<?php echo $this->get_field_id('publication_name'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('publication_name'); ?>" name="<?php echo $this->get_field_name('publication_name'); ?>" <?php if ($publication_name) echo ' checked="checked" '; ?> />&nbsp;&nbsp;Publication name</label></p>

		<p><label for="<?php echo $this->get_field_id('publication_date'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('publication_date'); ?>" name="<?php echo $this->get_field_name('publication_date'); ?>" <?php if ($publication_date) echo ' checked="checked" '; ?> />&nbsp;&nbsp;Publication date</label></p>

		<p><label for="<?php echo $this->get_field_id('journalist_full_name'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('journalist_full_name'); ?>" name="<?php echo $this->get_field_name('journalist_full_name'); ?>" <?php if ($journalist_full_name) echo ' checked="checked" '; ?> />&nbsp;&nbsp;User name</label></p>

		<p><label for="<?php echo $this->get_field_id('description'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>" <?php if ($description) echo ' checked="checked"'; ?> />&nbsp;&nbsp;Comment&nbsp;</label><label for="<?php echo $this->get_field_id('character_limit'); ?>">(limited to&nbsp;<input type="text" id="<?php echo $this->get_field_id('character_limit'); ?>" maxlength="3" size="3" name="<?php echo $this->get_field_name('character_limit'); ?>" value="<?php echo $character_limit; ?>" />&nbsp;characters)</label></p>

		<p><label for="<?php echo $this->get_field_id('quote'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('quote'); ?>" name="<?php echo $this->get_field_name('quote'); ?>" <?php if ($quote) echo ' checked="checked" '; ?> />&nbsp;&nbsp;Quote</label></p>

		<p><label for="<?php echo $this->get_field_id('tags'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('tags'); ?>" name="<?php echo $this->get_field_name('tags'); ?>" <?php if ($tags) echo ' checked="checked" '; ?> />&nbsp;&nbsp;Tags</label></p>

		<p><label for="<?php echo $this->get_field_id('embed_video'); ?>"><input type="checkbox" id="<?php echo $this->get_field_id('embed_video'); ?>" name="<?php echo $this->get_field_name('embed_video'); ?>" <?php if ($embed_video) echo ' checked="checked"'; ?> />&nbsp;&nbsp;Embed video (if applicable)</label></p>

		<p><label for="<?php echo $this->get_field_id('video_width'); ?>">Video width:&nbsp;&nbsp;<input type="text" id="<?php echo $this->get_field_id('video_width'); ?>" maxlength="3" size="5" name="<?php echo $this->get_field_name('video_width'); ?>" value="<?php echo $video_width; ?>" />&nbsp;pixels</label></p>

		<p>Advanced display options:</p>

		<p><textarea id="<?php echo $this->get_field_id('link_format'); ?>" name="<?php echo $this->get_field_name('link_format'); ?>" rows="6" cols="20"><?php echo $link_format; ?></textarea>
			<br />Accepts <a href="/wp-content/plugins/publish2/img/screenshots/example-tokens_sidebar.jpg" target="_blank">tokens</a> %headline% %source% %date% %journalist% %comment% %quote% %tags% and basic HTML. Optionally, if you save links to videos, %video% will determine the placement. Using tokens will override the checkboxes in "Basic display options."
		</p>

		<p><label for="<?php echo $this->get_field_id('read_more_text'); ?>">Widget footer text:<br /><input name="<?php echo $this->get_field_name('read_more_text'); ?>" type="text" id="<?php echo $this->get_field_id('read_more_text'); ?>" value="<?php echo $read_more_text; ?>" size="25" /></label></p>

		<p><label for="<?php echo $this->get_field_id('read_more_url'); ?>">Alternate URL for footer text (optional):<br />
			<input name="<?php echo $this->get_field_name('read_more_url'); ?>" type="text" id="<?php echo $this->get_field_id('read_more_url'); ?>" value="<?php echo $read_more_url; ?>" size="25" /></label></p>

		<?php
		
	}

  /**
   * Handles POST requests with new settings
   */
	function update($new_instance, $old_instance) {
		
		$instance = $old_instance;
		
		$instance['feedlink'] = $new_instance['feedlink']; // String
		$instance['shorturl'] = $new_instance['shorturl']; // True/false
		$instance['share_buttons'] = $new_instance['share_buttons']; // True/false
		$instance['pagination'] = $new_instance['pagination']; // True/false
		$instance['widget_title'] = strip_tags(stripslashes($new_instance['widget_title'])); // String
		$instance['link_count'] = $new_instance['link_count']; // Integer
		$instance['character_limit'] = $new_instance['character_limit']; // Integer		
		$instance['advanced_options'] = $new_instance['advanced_options']; // True/false
		$instance['link_format'] = stripslashes(strip_tags($new_instance['link_format'], '<h1><h2><h3><h4><h5><h6><b><i><strong><blockquote><em><span><a><p><ul><ol><li><br><div>')); // String
		$instance['display_list'] = $new_instance['display_list']; // True/false
		$instance['publication_name'] = $new_instance['publication_name']; // True/false
		$instance['publication_date'] = $new_instance['publication_date']; // True/false
		$instance['journalist_full_name'] = $new_instance['journalist_full_name']; // True/false
		$instance['description'] = $new_instance['description']; // True/false
		$instance['quote'] = $new_instance['quote']; // True/false
		$instance['tags'] = $new_instance['tags']; // True/false
		$instance['embed_video'] = $new_instance['embed_video']; // True/false
		$instance['video_width'] = $new_instance['video_width']; // Integer
		$instance['read_more_text'] = stripslashes($new_instance['read_more_text']); // String
		$instance['read_more_url'] = $new_instance['read_more_url']; // String
		
		return $instance;
		
	}
	
	/**
   * Handles public facing widget
   */
	function widget($args, $instance) {
		
		extract($args);
		extract($instance);
		
		if (!$link_format) {
			$display_list = true;
			$link_format = null;
			if (!$embed_video) { $video_width = 0; }
		} else if ($link_format) {
			$display_list = false;
			if ($video_width == '') { $video_width = 180; }		
		}
		
		// If the user has added text as a title, add it to the top of the widget
		echo $before_widget . $before_title;
		if ($widget_title != "") {
			echo '<span class="publish2_header">' . $widget_title . '</span>';
		}
		echo $after_title;

		publish2($feedlink, $link_count, $publication_name, $publication_date, $journalist_full_name, $description, $tags, $link_format, 'widget', $display_list, $video_width, $quote, $shorturl, $character_limit, $share_buttons, 'widget');
			
		if ($read_more_text) {
			echo '<p><a href="';
			if ($read_more_url) {
				echo $read_more_url;
			} else {
				echo $feedlink;
			}
			echo '" class="publish2_footer">' . $read_more_text . '</a></p>';
		}
		// Need to dump these settings for pagination
		if ($pagination) {
		  echo '<p class="publish2_pagination"><span id="publish2_back" class="inactive">Back</span><span id="publish2_next" class="active">Next</span></p>'; 
		  echo '<div class="p2data" style="display:none;">';
		  echo '<span class="p2data_feedlink">' . publish2_cleanurl($feedlink) . '</span>';
		  echo '<span class="p2data_shorturl">' . $shorturl . '</span>';
		  echo '<span class="p2data_character_limit">' . $character_limit . '</span>';
		  echo '<span class="p2data_pagination">' . $pagination . '</span>';
		  echo '<span class="p2data_share_buttons">' . $share_buttons . '</span>';
		  echo '<span class="p2data_link_count">' . $link_count . '</span>';
		  echo '<span class="p2data_publication_name">' . $publication_name . '</span>';
		  echo '<span class="p2data_publication_date">' . $publication_date . '</span>';
		  echo '<span class="p2data_journalist_full_name">' . $journalist_full_name . '</span>';
		  echo '<span class="p2data_description">' . $description . '</span>';
		  echo '<span class="p2data_tags">' . $tags . '</span>';
		  echo '<span class="p2data_link_format">' . $link_format . '</span>';
		  echo '<span class="p2data_embed_video">' . $embed_video . '</span>';
		  echo '<span class="p2data_video_width">' . $video_width . '</span>';
		  echo '<span class="p2data_quote">' . $quote . '</span>';
		  echo '</p>';
		}
		echo $after_widget;
		
	}

}

/**
 * Registers any and all widgets
 */
function publish2_loadwidgets() {
	register_widget('Publish2_Widget');
}

/**
 * Create a default Publish2 settings page in the WordPress admin
 */
function publish2_options() {
	
	$message = null;
	$message_updated = __("Your settings have been updated.", 'publish2');
	$options = $newoptions = get_option('publish2_settings');
	
	// Custom message logic for a test link roundup
	if ($_POST['publish2-test_roundup']) {
		$new_post_id = publish2_publishroundup($options, true);
		if ($new_post_id) {
			$blog_url = get_bloginfo('url');
			$new_post_edit = $blog_url . '/wp-admin/post.php?action=edit&post=' . $new_post_id;
			$message = "Test roundup created as draft. <a href='" . $new_post_edit . "'>Edit post</a>";
		} else {
			$message = 'An error occurred while creating test roundup.';
		}
	}
	
	// Custom message logic for a test single link import
	if ($_POST['publish2-test_import']) {
		$links_published = publish2_publishsingle($options, true);
		if ($links_published) {
			$blog_url = get_bloginfo('url');
			$message = "Test import complete. <a href='" . $blog_url . "/wp-admin/edit.php'>" . $links_published . " drafts created</a>";
		} else {
			$message = 'An error occurred while creating test link import.';
		}
	}
	
	// Save updated settings
	if ($_POST['publish2-submit']) {
		$message = $message_updated;
		
		if (!$_GET['options']) {
			$newoptions['enable_link_roundups'] = (int) $_POST['publish2-enable_link_roundups'];
			$newoptions['roundup_feedlink'] = $_POST['publish2-roundup_feedlink'];
			$newoptions['roundup_title'] = stripslashes(strip_tags($_POST['publish2-roundup_title']));
			$newoptions['roundup_shorturl'] = (bool) $_POST['publish2-roundup_shorturl'];
			$newoptions['roundup_introduction'] = stripslashes(strip_tags($_POST['publish2-roundup_introduction'], '<h1><h2><h3><h4><h5><h6><b><i><strong><blockquote><em><span><a><p><ul><ol><li><br><div>'));
			$newoptions['roundup_author'] = (int) $_POST['publish2-roundup_author'];
			$newoptions['roundup_category'] = (int) $_POST['publish2-roundup_category'];
			$newoptions['roundup_advanced_options'] = (int) $_POST['publish2-roundup_advanced_options'];
			$newoptions['roundup_publication_name'] = (bool) $_POST['publish2-roundup_publication_name'];
			$newoptions['roundup_publication_date'] = (bool) $_POST['publish2-roundup_publication_date'];
			$newoptions['roundup_journalist_full_name'] = (bool) $_POST['publish2-roundup_journalist_full_name'];
			$newoptions['roundup_description'] = (bool) $_POST['publish2-roundup_description'];
			$newoptions['roundup_quote'] = (bool) $_POST['publish2-roundup_quote'];
			$newoptions['roundup_link_format'] = stripslashes(strip_tags($_POST['publish2-roundup_link_format'], '<h1><h2><h3><h4><h5><h6><b><i><strong><blockquote><em><span><a><p><ul><ol><li><br><div>'));
			$newoptions['roundup_video'] = (bool) $_POST['publish2-roundup_video'];
			$newoptions['roundup_video_width'] = (int) $_POST['publish2-roundup_video_width'];	
		} else if ($_GET['options'] == 'php-function') {
			$newoptions['feedlink'] = $_POST['publish2-feedlink'];
			$newoptions['shorturl'] = (bool) $_POST['publish2-shorturl'];
			$newoptions['link_format'] = stripslashes(strip_tags($_POST['publish2-link_format'], '<h1><h2><h3><h4><h5><h6><b><i><strong><blockquote><em><span><a><p><ul><ol><li><br><div>'));
			$newoptions['widget_title'] = strip_tags(stripslashes($_POST['publish2-widget_title']));
			$newoptions['display_list'] = (bool) $_POST['publish2-display_list'];
			$newoptions['link_count'] = (int) $_POST['publish2-link_count'];
			$newoptions['publication_name'] = (bool) $_POST['publish2-publication_name'];
			$newoptions['publication_date'] = (bool) $_POST['publish2-publication_date'];
			$newoptions['journalist_full_name'] = (bool) $_POST['publish2-journalist_full_name'];
			$newoptions['description'] = (bool) $_POST['publish2-description'];
			$newoptions['quote'] = (bool) $_POST['publish2-quote'];
			$newoptions['tags'] = (bool) $_POST['publish2-tags'];
			$newoptions['advanced_options'] = (int) $_POST['publish2-advanced_options'];
			$newoptions['embed_video'] = (bool) $_POST['publish2-embed_video'];
			$newoptions['video_width'] = (int) $_POST['publish2-video_width'];
		} else if ($_GET['options'] == 'import-single') {
			$newoptions['enable_link_importing'] = (bool) $_POST['publish2-enable_link_importing'];
			$newoptions['import_feedlink'] = $_POST['publish2-import_feedlink'];
			$newoptions['import_trigger_method'] = (int) $_POST['publish2-import_trigger_method'];
			$newoptions['import_shorturl'] = (bool) $_POST['publish2-import_shorturl'];
			$newoptions['import_author'] = (int) $_POST['publish2-import_author'];
			$newoptions['import_match_authors'] = (bool) $_POST['publish2-import_match_authors'];
			$newoptions['import_category'] = (int) $_POST['publish2-import_category'];
			$newoptions['import_publication_name'] = (bool) $_POST['publish2-import_publication_name'];
			$newoptions['import_publication_date'] = (bool) $_POST['publish2-import_publication_date'];
			$newoptions['import_journalist'] = (bool) $_POST['publish2-import_journalist'];
			$newoptions['import_quote'] = (bool) $_POST['publish2-import_quote'];
			$newoptions['import_tags'] = (bool) $_POST['publish2-import_tags'];
			$newoptions['import_video'] = (bool) $_POST['publish2-import_video'];
			$newoptions['import_video_width'] = (int) $_POST['publish2-import_video_width'];
			$newoptions['import_read_more'] = stripslashes(strip_tags($_POST['publish2-import_read_more']));
		} else if ($_GET['options'] == 'advanced') {
			$newoptions['notification_address'] = $_POST['publish2-notification_address'];
			$newoptions['notification_message'] = stripslashes(strip_tags($_POST['publish2-notification_message'], '<h1><h2><h3><h4><h5><h6><b><i><strong><blockquote><em><span><a><p><ul><ol><li><br><div>'));
		}
		
	}
		
	if ($options != $newoptions) {
		$options = array_merge($options, $newoptions);
		if ($options['link_count'] < 1) { $options['link_count'] = 1; }	
		if ($options['link_count'] > 100) { $options['link_count'] = 100; }
		$options['feedlink'] = publish2_cleanurl($options['feedlink']);
		$options['import_feedlink'] = publish2_cleanurl($options['import_feedlink']);
		$options['roundup_feedlink'] = publish2_cleanurl($options['roundup_feedlink']);
		$options['newsettings'] = true;
		update_option('publish2_settings', $options);
	}
	$check_environment = publish2_checkenvironment();
	
	if ($options['video_width'] == '') { $options['video_width'] = 180; }
	
	?>
		
	<?php if ($message) : ?>
	<div id="message" class="updated fade"><p><?php echo $message; ?></p></div>
	<?php endif; ?>
	<div id="dropmessage" class="updated" style="display:none;"></div>
		
	<div class="wrap">

		<h2>Publish2 Settings</h2>

		<?php if (!$check_environment['json']) { ?>
		<div style="margin:10px auto; border:3px #f00 solid; background-color:#fdd; color:#000; padding:10px; text-align:center;">JSON is required for the Publish2 plugin to work. <a href="http://wordpress.org/extend/plugins/publish2/faq/" title="What does this mean?">You may need to upgrade to at least PHP 5.2</a></div>
		<?php } else if (!$check_environment['file_get_contents'] && !$check_environment['curl']) {?><div style="margin:10px auto; border:3px #f00 solid; background-color:#fdd; color:#000; padding:10px; text-align:center;"><a href="http://wordpress.org/extend/plugins/publish2/faq/" title="What does this mean?">Either file_get_contents() or cURL are required</a> for the Publish2 plugin to work.</div>
		<?php } else { ?>

		<form method="post">
			
		<img src="<?php echo PUBLISH2_URL; ?>/img/publish2_h350.jpg" width="350px" height="150px" style="float:right; border:1px solid #000; margin-left:10px; margin-right:10px; margin-bottom:20px;" id="p2-gallery" />

		<p onmouseover="jQuery('#p2-gallery').attr('src', '<?php echo PUBLISH2_URL; ?>/img/publish2_h350.jpg');"><strong>The Publish2 plugin brings collaborative curation to WordPress.</strong> Easily add links, tweets, and videos from Publish2 to your WordPress blog.</p>
		
		<p onmouseover="jQuery('#p2-gallery').attr('src', '<?php echo PUBLISH2_URL; ?>/img/linkassist_h350.jpg');"><a href="<?php bloginfo('url'); ?>/wp-admin/profile.php">Enable Link Assist</a> to access your links, tweets, and videos saved on Publish2 while you're writing a post. Add links, tweets, and videos to a blog post as inline text links or displayed as a list. Insert tweets into your posts, or embed videos from YouTube or Vimeo.</p>

		<p onmouseover="jQuery('#p2-gallery').attr('src', '<?php echo PUBLISH2_URL; ?>/img/whatwerereading_h350.jpg');">The Publish2 plugin also displays your links, tweets, and videos in a <a href="<?php bloginfo('url'); ?>/wp-admin/widgets.php" title="Install the Widget">sidebar widget</a>, a list of links on <a href="<?php bloginfo('url'); ?>/wp-admin/edit-pages.php">any page</a>, or by using <code>&#60;&#63;php publish2(); &#63;&#62;</code> in any of your WordPress templates.</p>
		
		<p><a href="<?php bloginfo('url'); ?>/wp-admin/profile.php#publish2" class="button">Configure Link Assist for your account</a>&nbsp;&nbsp;&nbsp;<a href="<?php bloginfo('url'); ?>/wp-admin/widgets.php" class="button">Install and enable sidebar widget</a></p>		
		
		<p>Import single links as posts to automatically create new content for your WordPress blog. Batch import links to build daily or weekly roundup posts.</p>	
		
		<ul id="publish2-settings_navigation">
			<li><a href="<?php bloginfo('url') ;?>/wp-admin/options-general.php?page=<?php echo $_GET['page']; ?>"<?php if ($options['enable_link_roundups'] && !$_GET['options']) { echo ' class="active enabled"'; } else if (!$options['enable_link_roundups'] && !$_GET['options']) { echo ' class="active disabled"'; } else if (!$_GET['options']) { echo ' class="active"'; } else if ($options['enable_link_roundups']) { echo ' class="enabled"'; } else { echo 'class="disabled"'; } ?>><strong>Automatic Link Roundups</strong><br /><span>Periodically publish link roundups</span></a></li>
			<li><a href="<?php bloginfo('url') ;?>/wp-admin/options-general.php?page=<?php echo $_GET['page']; ?>&amp;options=import-single"<?php if ($options['enable_link_importing'] && $_GET['options'] == 'import-single') { echo ' class="active enabled"'; } else if (!$options['enable_link_importing'] && $_GET['options'] == 'import-single') { echo ' class="active disabled"'; } else if ($_GET['options'] == 'import-single') { echo ' class="active"'; } else if ($options['enable_link_importing']) { echo ' class="enabled"'; } else { echo 'class="disabled"'; } ?>><strong>Automatically Import Links</strong><br /><span>Import links as posts</span></a></li>
			<li><a href="<?php bloginfo('url') ;?>/wp-admin/options-general.php?page=<?php echo $_GET['page']; ?>&amp;options=php-function"<?php if ($_GET['options'] == 'php-function') { echo ' class="active"'; } ?>><strong>PHP Function Configuration</strong><br /><span>Publish links with a PHP function</span></a></li>
			<li><a href="<?php bloginfo('url') ;?>/wp-admin/options-general.php?page=<?php echo $_GET['page']; ?>&amp;options=advanced"<?php if ($_GET['options'] == 'advanced') echo ' class="active"'; ?>><strong>Advanced Options</strong><br /><span>Set error reporting and more</span></a></li>
			<li><a href="<?php bloginfo('url') ;?>/wp-admin/options-general.php?page=<?php echo $_GET['page']; ?>&amp;options=additional"<?php if ($_GET['options'] == 'additional') echo ' class="active"'; ?>><strong>Additional Information</strong><br /><span>Styling and custom functionality</span></a></li>
		</ul>
		
		<?php if (!$_GET['options']) : ?>
		
			<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
			
				<tr valign="top">
					<th scope="row">Automatic Link Roundups:</th>
					<td><select name="publish2-enable_link_roundups" id="publish2-enable_link_roundups">
						<option value="0" <?php if (!$options['enable_link_roundups']) { echo 'selected="selected"'; }?>>Disabled</option>
						<option value="1" <?php if ($options['enable_link_roundups'] == 1) { echo 'selected="selected"'; }?>>Daily</option>
						<option value="2" <?php if ($options['enable_link_roundups'] == 2) { echo 'selected="selected"'; }?>>Weekly</option>
					</select></td>
				</tr>
				<?php
					
					if ($options['enable_link_roundups'] == 1) {
						$roundup_next_import = $options['roundup_last_imported'] + (3600 * 24);
					} else if ($options['enable_link_roundups'] == 2) {
						$roundup_next_import = $options['roundup_last_imported'] + (3600 * 24 * 7);
					} else {
						$roundup_next_import = 0;
					}
				
					if (wp_timezone_supported() && $options['roundup_last_imported']) {
						$timezone_string = get_option('timezone_string');								
						$gmt_offset = get_option('gmt_offset');
						if ($timezone_string) {
							$roundup_last_imported = date('l F j, Y \a\t h:i:s A', $options['roundup_last_imported']);
							$roundup_last_imported .= ' ' . $timezone_string;
							if ($roundup_next_import) {
								$roundup_next_import = date('l F j, Y \a\t h:i:s A', $roundup_next_import);
								$roundup_next_import .= ' ' . $timezone_string;
							}
						} else {
							$roundup_correct_time = $options['roundup_last_imported'] + ($gmt_offset * 3600);
							$roundup_last_imported = date('l F j, Y \a\t h:i:s A', $roundup_correct_time);
							$roundup_last_imported .= ' (GMT' . $gmt_offset . ')';
							if ($roundup_next_import) {
								$roundup_next_import = $roundup_next_import + ($gmt_offset * 3600);
								$roundup_next_import = date('l F j, Y \a\t h:i:s A', $roundup_next_import);
								$roundup_next_import .= ' (GMT' . $gmt_offset . ')';
							}
						}
					} else if ($options['roundup_last_imported']) {
						$roundup_last_imported = date('l F j, Y \a\t h:i:s A T', $options['roundup_last_imported']);
						if ($roundup_next_import) {
							$roundup_next_import = date('l F j, Y \a\t h:i:s A T', $roundup_next_import);
						}
					} else {
						$roundup_last_imported = 'Never imported';
					}
				?>
				<tr valign="top" id="nextImport" class="toggleImport">
					<th scope="row">Next Import</th>
					<td><?php if ($roundup_next_import) { echo $roundup_next_import; } else { echo 'Not scheduled'; } ?></td>
				</tr>
				<tr valign="top" id="lastImported" class="toggleImport" style="display:none;">
					<th scope="row">Last Imported</th>
					<td><?php echo $roundup_last_imported; ?></td>
				</tr>
				<tr valign="top">
					<th scope="row">Source URL for roundups (required):</th>
					<td>
						<input name="publish2-roundup_feedlink" type="text" id="publish2-roundup_feedlink" class="publish2URL" value="<?php echo $options['roundup_feedlink']; ?>" size="50" /><span id="publish2URLnotice"></span><br />
						<span class="setting-description">Add a URL from a Newsgroup or links search.<br />Supports "social" URLs to include tweets and videos.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Use short URLs for click tracking</th>
					<td><input type="checkbox" id="publish2-roundup_shorturl" name="publish2-roundup_shorturl" <?php if ($options['roundup_shorturl']) echo ' checked="checked"'; ?> /><br />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Post title for roundups:</th>
					<td>
						<input name="publish2-roundup_title" type="text" id="publish2-roundup_title" value="<?php echo $options['roundup_title']; ?>" size="50" /><br />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Introduction:</th>
					<td><textarea id="publish2-roundup_introduction" name="publish2-roundup_introduction" rows="4" cols="60"><?php echo $options['roundup_introduction']; ?></textarea>
						<p style="margin-top: 10px;" class="setting-description">Add optional introductory text for all roundup posts. Basic HTML is accepted.</p>	
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Default Post Author:</th>
					<td>
						<?php $authors = get_users_of_blog(); ?>
						<select name="publish2-roundup_author">
							<?php foreach ($authors as $user) {
								$usero = new WP_User($user->user_id);
								$author = $usero->data;
								// Only list users who are allowed to publish
								if (!$usero->has_cap('publish_posts')) {
									continue;
								}
								echo '<option value="' . $author->ID . '"';
								if ($options['roundup_author'] == $author->ID) {
									echo ' selected="selected"';
								}
								echo '>' . $author->user_nicename . '</option>';
							} ?>
						</select><br />
						<span class="setting-description">Choose a user to be the default author of every new roundup post.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Roundup Post Category:</th>
					<td>
						<?php $categories = get_categories(); ?>
						<select name="publish2-roundup_category" id="publish2-roundup_category">
							<?php foreach ($categories as $category) {
								echo '<option value="' . $category->cat_ID . '"';
								if ($options['roundup_category'] == $category->cat_ID) {
									echo ' selected="selected"';
								}
								echo '>' . $category->name . '</option>';
							} ?>
						</select><br />
						<span class="setting-description">Choose a category for all roundup posts.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Select elements to display:</th>
					<td><select name="publish2-roundup_advanced_options" class="publish2-advanced_options">
						<option value="0" <?php if (!$options['roundup_advanced_options']) { echo 'selected="selected"'; }?>>Basic Options</option>
						<option value="1" <?php if ($options['roundup_advanced_options']) { echo 'selected="selected"'; }?>>Advanced Options</option>
					</select></td>
				</tr>
			</table>

				<div class="normal_options" <?php if (!$options['roundup_advanced_options']) { echo 'style="display:block;"'; } else { echo 'style="display:none;"'; }?>>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">			
					<tr valign="top">
						<th scope="row">Publication name</th>
						<td><input type="checkbox" id="publish2-roundup_publication_name" name="publish2-roundup_publication_name" <?php if ($options['roundup_publication_name']) echo ' checked="checked" '; ?>/></td>
					</tr>	
					<tr valign="top">
						<th scope="row">Publication date</th>
						<td><input type="checkbox" id="publish2-roundup_publication_date" name="publish2-roundup_publication_date" <?php if ($options['roundup_publication_date']) echo ' checked="checked" '; ?>/></td>
					</tr>
					<tr valign="top">
						<th scope="row">User name</th>
						<td><input type="checkbox" id="publish2-roundup_journalist_full_name" name="publish2-roundup_journalist_full_name" <?php if ($options['roundup_journalist_full_name']) echo ' checked="checked" '; ?>/></td>
					</tr>
					<tr valign="top">
						<th scope="row">Comment</th>
						<td><input type="checkbox" id="publish2-roundup_description" name="publish2-roundup_description" <?php if ($options['roundup_description']) echo ' checked="checked" '; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Quote</th>
						<td><input type="checkbox" id="publish2-roundup_quote" name="publish2-roundup_quote" <?php if ($options['roundup_quote']) echo ' checked="checked" '; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Embed video (if applicable)</th>
						<td><input type="checkbox" id="publish2-roundup_video" name="publish2-roundup_video" <?php if ($options['roundup_video']) echo ' checked="checked" '; ?>/></td>
					</tr>
				</table>

				</div>

				<div class="advanced_options" <?php if ($options['roundup_advanced_options']) { echo 'style="display:block;"'; } else { echo 'style="display:none;"'; }?>>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
					<tr valign="top">
						<th scope="row">You can use a combination of tokens (e.g. %headline%) and HTML to customize how each link is formatted. The field will also accept classes and IDs if you want specific styles.</th>
						<td><textarea id="publish2-roundup_link_format" name="publish2-roundup_link_format" rows="6" cols="60"><?php echo $options['roundup_link_format']; ?></textarea>
							<p style="margin-top: 10px;" class="setting-description">Accepts <a href="<?php echo PUBLISH2_URL; ?>/img/screenshots/example-tokens_settings.jpg" target="_blank">tokens</a> %headline%, %source%, %date%, %journalist%, %comment%, %quote%, %tags% and basic HTML. Optionally, if you save links to videos, %video% will determine the placement.</p>	
						</td>
					</tr>
				</table>

				</div>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
					<tr valign="top">
						<th scope="row">Video width</th>
						<td><input type="text" maxlength="3" id="publish2-roundup_video_width" name="publish2-roundup_video_width" value="<?php echo $options['roundup_video_width']; ?>"/>&nbsp;<span class="setting-description">pixels</span></td>
					</tr>
				</table>
			
				<div class="submit">
					<input type="submit" name="publish2-submit" id="publish2-submit" class="button-primary" value="<?php _e('Save Changes'); ?>" /> <input type="submit" name="publish2-test_roundup" id="publish2-test_roundup" class="button" value="<?php _e('Create roundup test') ?>" />
				</div>
			
			<?php elseif ($_GET['options'] == 'import-single') : ?>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">

					<tr valign="top">
						<th scope="row">Automatically Import Links:</th>
						<td><select name="publish2-enable_link_importing" id="publish2-enable_link_importing">
							<option value="0" <?php if (!$options['enable_link_importing']) { echo 'selected="selected"'; }?>>Disabled</option>
							<option value="1" <?php if ($options['enable_link_importing']) { echo 'selected="selected"'; }?>>Activated</option>
						</select></td>
					</tr>
					<tr valign="top">
						<th scope="row">Link Importing Trigger Method:</th>
						<td><select name="publish2-import_trigger_method" id="publish2-import_trigger_method">
							<option value="0" <?php if (!$options['import_trigger_method']) { echo 'selected="selected"'; }?>>Visitor page loads</option>
							<option value="1" <?php if ($options['import_trigger_method'] == 1) { echo 'selected="selected"'; }?>>WP Cron</option>
						</select></td>
					</tr>
					<tr valign="top" id="lastImported" class="toggleImport">
						<th scope="row">Last Imported</th>
						<?php
							if (wp_timezone_supported()) {
								$timezone_string = get_option('timezone_string');								
								$gmt_offset = get_option('gmt_offset');
								if ($timezone_string) {
									$import_last_imported = date('l F j, Y \a\t h:i:s A', $options['import_last_imported']);
									$import_last_imported .= ' ' . $timezone_string;
									$import_next_imported = date('l F j, Y \a\t h:i:s A', wp_next_scheduled('publish2_hourly'));
									$import_next_imported .= ' ' . $timezone_string;
								} else {
									$correct_time = $options['import_last_imported'] + ($gmt_offset * 3600);
									$import_last_imported = date('l F j, Y \a\t h:i:s A', $correct_time);
									$import_last_imported .= ' (GMT' . $gmt_offset . ')';
									$correct_time = wp_next_scheduled('publish2_hourly') + ($gmt_offset * 3600);
									$import_next_imported = date('l F j, Y \a\t h:i:s A', $correct_time);
									$import_next_imported .= ' (GMT' . $gmt_offset . ')';
								}
							} else {
								$import_last_imported = date('l F j, Y \a\t h:i:s A T', $options['import_last_imported']);
								$import_next_imported = date('l F j, Y \a\t h:i:s A T', wp_next_scheduled('publish2_hourly'));
							}
						?>
						<td><?php if ($options['import_last_imported']) { echo $import_last_imported; } else { echo 'Never imported'; } ?></td>
					</tr>
					<tr valign="top" id="nextImport" class="toggleImport" style="display:none;">
						<th scope="row">Next Import</th>
					  <td><?php if (wp_next_scheduled('publish2_hourly')) { echo $import_next_imported; } else { echo 'Not scheduled/Not applicable'; } ?></td>
					</tr>
					<tr valign="top">
						<th scope="row">Source URL to import (required):</th>
						<td>
							<input name="publish2-import_feedlink" type="text" id="publish2-import_feedlink" class="publish2URL" value="<?php echo $options['import_feedlink']; ?>" size="50" /><span id="publish2URLnotice"></span><br />
							<span class="setting-description">Add a URL from a Newsgroup or links search.<br />Supports "social" URLs to include tweets and videos.</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Use short URLs for click tracking</th>
						<td><input type="checkbox" id="publish2-import_shorturl" name="publish2-import_shorturl" <?php if ($options['import_shorturl']) echo ' checked="checked"'; ?> /><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Default Post Author:</th>
						<td>
							<?php $authors = get_users_of_blog(); ?>
							<select name="publish2-import_author">
								<?php foreach ($authors as $user) {
									$usero = new WP_User($user->user_id);
									$author = $usero->data;
									// Only list users who are allowed to publish
									if (!$usero->has_cap('publish_posts')) {
										continue;
									}
									echo '<option value="' . $author->ID . '"';
									if ($options['import_author'] == $author->ID) {
										echo ' selected="selected"';
									}
									echo '>' . $author->user_nicename . '</option>';
								} ?>
							</select>&nbsp;&nbsp;&nbsp;<label for="publish2-import_match_authors">Set Publish2 user as post author</label>&nbsp;&nbsp;<input type="checkbox" id="publish2-import_match_authors" name="publish2-import_match_authors" <?php if ($options['import_match_authors']) echo ' checked="checked" '; ?> /><br />
							<span class="setting-description">Choose a default author for all link posts. <a href="/wp-admin/profile.php">Add your Publish2 profile URL to your WordPress profile</a> to automatically list the Publish2 user as the post author.</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Link Post Category:</th>
						<td>
							<?php $categories = get_categories(); ?>
							<select name="publish2-import_category" id="publish2-import_category">
								<?php foreach ($categories as $category) {
									echo '<option value="' . $category->cat_ID . '"';
									if ($options['import_category'] == $category->cat_ID) {
										echo ' selected="selected"';
									}
									echo '>' . $category->name . '</option>';
								} ?>
							</select><br />
							<span class="setting-description">Choose a category for your imported links.</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Include "[User] says:"</th>
						<td><input type="checkbox" id="publish2-import_journalist" name="publish2-import_journalist" <?php if ($options['import_journalist']) echo ' checked="checked" '; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Include Quote (if applicable)</th>
						<td><input type="checkbox" id="publish2-import_quote" name="publish2-import_quote" <?php if ($options['import_quote']) echo ' checked="checked" '; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Add Tags</th>
						<td><input type="checkbox" id="publish2-import_tags" name="publish2-import_tags" <?php if ($options['import_tags']) echo ' checked="checked" '; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Import video (if applicable)</th>
						<td><input type="checkbox" id="publish2-import_video" name="publish2-import_video" <?php if ($options['import_video']) echo ' checked="checked" '; ?>/></td>
					</tr>
					<tr valign="top">
						<th scope="row">Video width</th>
						<?php if (!$options['import_video_width']) { $options['import_video_width'] = 500; } ?>					
						<td><input type="text" maxlength="3" id="publish2-import_video_width" name="publish2-import_video_width" value="<?php echo $options['import_video_width']; ?>"/>&nbsp;<span class="setting-description">pixels</span></td>
					</tr>
					<tr valign="top">
						<th scope="row">Read more text</th>
						<td>
							<input name="publish2-import_read_more" type="text" id="publish2-import_read_more" value="<?php echo $options['import_read_more']; ?>" size="50" /><br />
							<span class="setting-description">Leave blank for default. Use %headline%, %source% or %date% to include content data.</span>
						</td>
					</tr>
				</table>
			
				<div class="submit">
					<input type="submit" name="publish2-submit" id="publish2-submit" class="button-primary" value="<?php _e('Save Changes') ?>" /> <input type="submit" name="publish2-test_import" id="publish2-test_import" class="button" value="<?php _e('Test link import') ?>" />
				</div>
		
			<?php elseif ($_GET['options'] == 'php-function') : ?>

			<p>If you want to add links, tweets, and videos from Publish2 to a WordPress template, you can use the plugin's <code>&#60;&#63;php publish2(); &#63;&#62;</code> PHP function. The options below establish the default settings for <code>&#60;&#63;php publish2(); &#63;&#62;</code>:</p>

			<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
				<tr valign="top">
					<th scope="row">Source URL for links (required):</th>
					<td><input name="publish2-feedlink" type="text" id="publish2-feedlink" class="publish2URL" value="<?php echo wp_specialchars($options['feedlink'], true); ?>" size="50" /><span id="publish2URLnotice"></span><br />
					<span class="setting-description">Add a URL from a Newsgroup or links search.<br />Supports "social" URLs to include tweets and videos.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Use short URLs for click tracking</th>
					<td><input type="checkbox" id="publish2-shorturl" name="publish2-shorturl" <?php if ($options['shorturl']) echo ' checked="checked"'; ?> /><br />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Number of items to display:</th>
					<td><input type="text" maxlength="3" id="publish2-link_count" name="publish2-link_count" value="<?php echo wp_specialchars($options['link_count'], true); ?>"/><br />
					<span class="setting-description">Anything between 1 and 100</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Select elements to display:</th>
					<td><select name="publish2-advanced_options" class="publish2-advanced_options">
						<option value="0" <?php if (!$options['advanced_options']) { echo 'selected="selected"'; }?>>Basic Options</option>
						<option value="1" <?php if ($options['advanced_options']) { echo 'selected="selected"'; }?>>Advanced Options</option>
					</select></td>
				</tr>
			</table>

				<div class="normal_options" <?php if (!$options['advanced_options']) { echo 'style="display:block;"'; } else { echo 'style="display:none;"'; }?>>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">			
					<tr valign="top">
						<th scope="row">Publication name</th>
						<td><input type="checkbox" id="publish2-publication_name" name="publish2-publication_name" <?php if ($options['publication_name']) echo ' checked="checked"'; ?>/></td>
					</tr>	
					<tr valign="top">
						<th scope="row">Publication date</th>
						<td><input type="checkbox" id="publish2-publication_date" name="publish2-publication_date" <?php if ($options['publication_date']) echo ' checked="checked"'; ?>/></td>
					</tr>
					<tr valign="top">
						<th scope="row">User name</th>
						<td><input type="checkbox" id="publish2-journalist_full_name" name="publish2-journalist_full_name" <?php if ($options['journalist_full_name']) echo ' checked="checked"'; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Comment</th>
						<td><input type="checkbox" id="publish2-description" name="publish2-description" <?php if ($options['description']) echo ' checked="checked"'; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Quote</th>
						<td><input type="checkbox" id="publish2-quote" name="publish2-quote" <?php if ($options['quote']) echo ' checked="checked"'; ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Tags</th>
						<td><input type="checkbox" id="publish2-tags" name="publish2-tags" <?php if ($options['tags']) echo ' checked="checked"'; ?>/></td>
					</tr>
					<tr valign="top">
						<th scope="row">Embed video (if applicable)</th>
						<td><input type="checkbox" id="publish2-embed_video" name="publish2-embed_video" <?php if ($options['embed_video']) echo ' checked="checked" '; ?>/></td>
					</tr>
				</table>

				</div>

				<div class="advanced_options" <?php if ($options['advanced_options']) { echo 'style="display:block;"'; } else { echo 'style="display:none;"'; }?>>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
					<tr valign="top">
						<th scope="row">You can use a combination of tokens (e.g. %headline%) and HTML to customize how each link is formatted. The field will also accept classes and IDs if you want specific styles.</th>
						<td><textarea id="publish2-link_format" name="publish2-link_format" rows="6" cols="60"><?php echo $options['link_format']; ?></textarea>
							<p style="margin-top: 10px;" class="setting-description">Accepts <a href="<?php echo PUBLISH2_URL; ?>/img/screenshots/example-tokens_settings.jpg" target="_blank">tokens</a> %headline%, %source%, %date%, %journalist%, %comment%, %quote%, %tags% and basic HTML. Optionally, if you save links to videos, %video% will determine the placement.</p>	
						</td>
					</tr>
				</table>

				</div>

				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
					<tr valign="top">
						<th scope="row">Video width</th>
						<td><input type="text" maxlength="3" id="publish2-video_width" name="publish2-video_width" value="<?php echo $options['video_width']; ?>"/>&nbsp;<span class="setting-description">pixels</span></td>
					</tr>

					<tr valign="top">
						<th scope="row">Add bullets (unordered list)&nbsp;&nbsp;</th>
						<td><input type="checkbox" id="publish2-display_list" name="publish2-display_list" <?php if ($options['display_list']) echo ' checked="checked" '; ?>/>
						</td>
					</tr>
				</table>
				
				<div class="submit">
					<input type="submit" name="publish2-submit" id="publish2-submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</div>
		
			<?php elseif ($_GET['options'] == 'advanced') : ?>
			
				<table class="form-table" cellspacing="2" cellpadding="5" width="100%">
					<tr valign="top">
						<th scope="row">Email addresses for error reports:</th>
						<td><input name="publish2-notification_address" type="text" id="publish2-notification_address" value="<?php echo $options['notification_address']; ?>" size="50" />
							<p class="setting-description">A comma-separated list of addresses to alert if WordPress can't access your Publish2 links.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Error message for readers</th>
						<td><textarea id="publish2-notification_message" name="publish2-notification_message" rows="6" cols="60"><?php echo $options['notification_message']; ?></textarea>
							<p style="margin-top: 10px;" class="setting-description">If WordPress can't access your Publish2 links, this is what your readers will see. Basic HTML is accepted.</p>
							<p><a href="<?php echo PUBLISH2_URL; ?>/php/log-download.php?type=error">Download last 100 error records</a></p>
						</td>
					</tr>
				</table>
			
				<div class="submit">
					<input type="submit" name="publish2-submit" id="publish2-submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</div>
			
			<?php elseif ($_GET['options'] == 'additional') : ?>
			
			<h3>More PHP Function Options</h3>
			
			<p>If you want to customize <code>&#60;&#63;php publish2(); &#63;&#62;</code> to display different links in different locations on your WordPress Theme, the function accepts these parameters:</p>
			
			<p><code>&#60;&#63;php publish2($feedlink[str], $link_count[int], $headline[bool], $date[bool], $journalist[bool], $comment[bool], $tags[bool], $link_format[str]); &#63;&#62;</code></p>
			
			<h3>CSS Styling for Basic Display</h3>
			<p>If you're using the basic checkbox options to select which link elements to display, the plugin renders HTML with unique CSS classes to define how your links are displayed. Here is sample HTML to use as a guide:</p>
			
			<p><code>&lt;h4&gt;&lt;span&gt;What I'm Reading&lt;/span&gt;&lt;/h4&gt;<br />
				&lt;h4&gt;&lt;a href="http://newssite.com/article"&gt;Sample Headline&lt;/a&gt;&lt;/h4&gt;<br />
				&lt;p&gt;&lt;span&gt;newssite.com&lt;/span&gt; | &lt;span&gt;July 13, 2009&lt;/span&gt;&lt;/p&gt;<br />
				&lt;p&gt;&lt;a href="http://www.publish2.com/journalists/daniel-bachhuber"&gt;Daniel Bachhuber&lt;/a&gt; says: &lt;span&gt;This is an insightful article, highly recommended.&lt;/span&gt;&lt;/p&gt;<br />
				&lt;p&gt;Tags: &lt;a href="http://www.publish2.com/journalists/daniel-bachhuber/tag1"&gt;Tag1&lt;/a&gt;, &lt;a href="http://www.publish2.com/journalists/daniel-bachhuber/tag2"&gt;Tag2&lt;/a&gt;<br />
				&lt;p&gt;&lt;a href="http://www.publish2.com/journalists/daniel-bachhuber/links"&gt;More of Daniel's Links&lt;/a&gt;&lt;/p&gt;<br />
			</code></p>
			
			<?php endif; ?>
			
		</form>
			
	</div>

	<?php }
		
}

/**
 * WordPress hook to register the settings page with WordPress
 */
function publish2_menu() {
	add_options_page('Publish2 Settings', 'Publish2', 8, __FILE__, 'publish2_options');
}

/**
 * WordPress hook to add a settings box at the end of a User's profile
 */
function publish2_profileoptions() {

	global $profileuser;
	// Only show profile settings if user is a contributor or above
	if (!$profileuser->has_cap('edit_posts')) {
	 return;
	}
	// Get the user's information so that we can pull their P2 settings from the database
	$user_id = (int) $profileuser->ID;
	$profile_url = get_usermeta($user_id, 'p2_profile_url');	
	$defaults = get_option('publish2_widget_settings');
	$feedlink = get_usermeta($user_id, 'publish2_url');
	$linkassist = get_usermeta($user_id, 'publish2_linkassist');
	$linkassist_shorturl = get_usermeta($user_id, 'p2_linkassist_shorturl');
	$linkassist_publication = get_usermeta($user_id, 'p2_linkassist_publication');
	$linkassist_date = get_usermeta($user_id, 'p2_linkassist_date');
	$linkassist_journalist = get_usermeta($user_id, 'p2_linkassist_journalist');
	$linkassist_comment = get_usermeta($user_id, 'p2_linkassist_comment');
	$linkassist_quote = get_usermeta($user_id, 'p2_linkassist_quote');	
	$linkassist_tags = get_usermeta($user_id, 'p2_linkassist_tags');
	$linkassist_link_format = get_usermeta($user_id, 'p2_linkassist_link_format');	
	$video_width = get_usermeta($user_id, 'p2_video_width');
	
	// Check to see whether the settings have been used before. If not, fill in with the defaults
	if (!$linkassist) { $linkassist = 'on'; }
	if (!$feedlink) { $feedlink = $defaults['feedlink']; }
	if (!$linkassist_publication) { $linkassist_publication = $defaults['publication_name']; }
	if (!$linkassist_date) { $linkassist_date = $defaults['publication_date']; }
	if (!$linkassist_journalist) { $linkassist_journalist = $defaults['journalist_full_name']; }
	if (!$linkassist_comment) { $linkassist_comment = $defaults['description']; }
	if (!$linkassist_quote) { $linkassist_quote = $defaults['quote']; }	
	if (!$linkassist_tags) { $linkassist_tags = $defaults['tags']; }
	if (!$video_width) { $video_width = 425; }
	
	?>
	<a name="publish2"></a>
	<h3>Publish2 Settings</h3>
	
	<table class="form-table">
		<tr>
			<th>Publish2 Profile URL</th>
			<td><input type="text" id="publish2-profile_url" class="regular-text" name="publish2-profile_url" value="<?php echo $profile_url; ?>"/>
				<p style="margin-top: 10px;" class="setting-description">e.g. <a href="http://www.publish2.com/journalists/user-name">http://www.publish2.com/journalists/user-name</a>)</p>
			</td>
		</tr>
		<tr>
			<th>Enable Publish2 Link Assist</th>
			<td><input type="checkbox" id="publish2-linkassist" name="publish2-linkassist" <?php if ($linkassist == 'on') echo 'checked="checked"'; ?> /></td>
		</tr>
		<tr>
			<th>Publish2 links URL to use for Link Assist</th>
			<td><input type="text" id="publish2-feedlink" class="regular-text publish2URL" name="publish2-feedlink" value="<?php echo $feedlink; ?>" /><span id="publish2URLnotice"></span></td>
		</tr>
		
		<tr>
			<th>Use short URLs for click tracking</th>
			<td>
				<input type="checkbox" id="p2-linkassist_shorturl" name="p2-linkassist_shorturl" <?php if ($linkassist_shorturl == 'on') echo 'checked="checked"'; ?> /><br />
			</td>
		</tr>
		
		<tr>
			<th>Details to add with Link Assist</th>
			<td>
				<input type="checkbox" id="p2-linkassist_publication" name="p2-linkassist_publication" <?php if ($linkassist_publication == 'on') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<label for="p2-linkassist_publication">Publication name</label><br />
				<input type="checkbox" id="p2-linkassist_date" name="p2-linkassist_date" <?php if ($linkassist_date == 'on') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<label for="p2-linkassist_date">Publication date</label><br />
				<input type="checkbox" id="p2-linkassist_journalist" name="p2-linkassist_journalist" <?php if ($linkassist_journalist == 'on') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<label for="p2-linkassist_journalist">User name</label><br />
				<input type="checkbox" id="p2-linkassist_comment" name="p2-linkassist_comment" <?php if ($linkassist_comment == 'on') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<label for="p2-linkassist_comment">Comment</label><br />
				<input type="checkbox" id="p2-linkassist_quote" name="p2-linkassist_quote" <?php if ($linkassist_quote == 'on') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<label for="p2-linkassist_quote">Quote</label><br />
				<input type="checkbox" id="p2-linkassist_tags" name="p2-linkassist_tags" <?php if ($linkassist_tags == 'on') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<label for="p2-linkassist_tags">Tags</label><br />
			</td>
		</tr>
		
		<tr>
			<th>You can use a combination of tokens (e.g. %headline%) and basic HTML to customize how each link is formatted. Using tokens will override the checkboxes in "Details to add with Link Assist."</th>
			<td><textarea id="p2-linkassist_link_format" name="p2-linkassist_link_format" rows="6" cols="40"><?php echo $linkassist_link_format; ?></textarea>
				<p style="margin-top: 10px;" class="setting-description">Accepts <a href="<?php echo PUBLISH2_URL; ?>/img/screenshots/example-tokens_settings.jpg" target="_blank">tokens</a> %headline%, %source%, %date%, %journalist%, %comment%, %quote% %tags% and basic HTML. Optionally, if you save links to videos, %video% will determine the placement.</p>	
			</td>
		</tr>
		
		<tr>
			<th>Video embed width</th>
			<td>
				<input type="text" maxlength="3" id="p2-video_width" name="p2-video_width" value="<?php echo $video_width; ?>"/>&nbsp;&nbsp;&nbsp;<span class="setting-description">pixels</span>
			</td>
		</tr>
	</table>
	
	<?php
	
}

/**
 * WordPress hook that runs when someone updates their profile options.
 * Grabs the information in the P2 fields and saves it to the metadata fields associated with the user
 */
function publish2_saveprofileoptions($user_id) {
	
	$profile_url = $_POST['publish2-profile_url'];
	$profile_url = publish2_cleanurl($profile_url);
	update_usermeta($user_id, 'p2_profile_url', $profile_url);
	
	$linkassist = $_POST['publish2-linkassist'];
	if ($linkassist != 'on') { $linkassist = 'off'; }
	update_usermeta($user_id, 'publish2_linkassist', $linkassist);
	
	$feedlink = $_POST['publish2-feedlink'];
	$feedlink = publish2_cleanurl($feedlink);	
	update_usermeta($user_id, 'publish2_url', $feedlink);
	
	$linkassist_shorturl = $_POST['p2-linkassist_shorturl'];
	if ($linkassist_shorturl != 'on') { $linkassist_shorturl = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_shorturl', $linkassist_shorturl);
	
	$linkassist_publication = $_POST['p2-linkassist_publication'];
	if ($linkassist_publication != 'on') { $linkassist_publication = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_publication', $linkassist_publication);
	
	$linkassist_date = $_POST['p2-linkassist_date'];
	if ($linkassist_date != 'on') { $linkassist_date = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_date', $linkassist_date);
	
	$linkassist_journalist = $_POST['p2-linkassist_journalist'];
	if ($linkassist_journalist != 'on') { $linkassist_journalist = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_journalist', $linkassist_journalist);
	
	$linkassist_comment = $_POST['p2-linkassist_comment'];
	if ($linkassist_comment != 'on') { $linkassist_comment = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_comment', $linkassist_comment);
	
	$linkassist_quote = $_POST['p2-linkassist_quote'];
	if ($linkassist_quote != 'on') { $linkassist_quote = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_quote', $linkassist_quote);
	
	$linkassist_tags = $_POST['p2-linkassist_tags'];
	if ($linkassist_tags != 'on') { $linkassist_tags = 'off'; }
	update_usermeta($user_id, 'p2_linkassist_tags', $linkassist_tags);
	
	$linkassist_link_format = stripslashes(strip_tags($_POST['p2-linkassist_link_format'], '<h1><h2><h3><h4><h5><h6><b><i><strong><em><blockquote><span><a><p><ul><ol><li><br><div>'));
	update_usermeta($user_id, 'p2_linkassist_link_format', $linkassist_link_format);
	
	$video_width = $_POST['p2-video_width'];
	update_usermeta($user_id, 'p2_video_width', $video_width);
	
}

/**
 * What to run on an hourly basis
 */
function publish2_hourlycron() {
	$options = get_option('publish2_settings');
	publish2_publishsingle($options);
}

function publish2_deactivate() {
  if (wp_next_scheduled('publish2_hourly')) {
		wp_clear_scheduled_hook('publish2_hourly');
	}
}

// Any sort of initialization scripts
add_action('init', 'publish2_init');
add_action('publish2_hourly', 'publish2_hourlycron');
add_action('widgets_init', 'publish2_loadwidgets');

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'publish2_deactivate');

// CSS files to include in the admin header
add_action('admin_print_scripts', 'publish2_cssadminheader');

// Link Assist actions to run when editing or saving a user's profile
add_action('edit_user_profile', 'publish2_profileoptions');
add_action('show_user_profile', 'publish2_profileoptions');
add_action('profile_update', 'publish2_saveprofileoptions');

// Add the Link Assist box, if applicable, to the edit post screen. Also, save link if changed
add_action('admin_menu', 'publish2_addpostlinkassistbox');
add_action('save_post', 'publish2_savepostmetabox');
add_action('edit_post', 'publish2_savepostmetabox');
add_action('publish_post', 'publish2_savepostmetabox');

// Used to append a P2 Links meta box at the end of a page
add_action('save_post', 'publish2_savepagemetabox');
add_action('edit_post', 'publish2_savepagemetabox');
add_action('publish_post', 'publish2_savepagemetabox');
add_action('edit_page_form', 'publish2_savepagemetabox');
add_action('admin_menu', 'publish2_addpagemetabox');

add_action('the_content', 'publish2_pagelinks');
add_filter('the_content', 'publish2_callthelinks', 10);

add_action('admin_menu', 'publish2_menu');

?>
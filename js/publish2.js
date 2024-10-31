var publish2_plugin_url = '/wp-content/plugins/publish2';
var publish2_version = '1.5.1';

/**
 * Preloads any images that we want cached for Link Assist to remove flicker
 */
function publish2_preloader() {
	
	p2Splash = new Image();
	p2Splash = "../wp-content/plugins/publish2/img/publish2_h250.gif";
	loadingRed = new Image(); 
	loadingRed.src = "../wp-content/plugins/publish2/img/loading-red.gif";
	loadingRedStatic = new Image();
	loadingRedStatic = "../wp-content/plugins/publish2/img/loading_static-red.gif";
	
}
publish2_preloader();

/**
 * Check the URL for various indicators of quality
 */
function publish2_cleanurl(feedlink) {
	
	// Feedlinks need 'http://' at the beginning
	var checkhttp = feedlink.indexOf('http://');
	if (checkhttp == -1) {
		feedlink = 'http://' + feedlink;
	}
	var feedlink_length = feedlink.length;
	var last_char = feedlink.charAt(feedlink_length - 1);
	if (last_char == '/') {
		feedlink = feedlink.substring(0, feedlink.length-1);
	}
	return feedlink;
	
}

/**
 * Generates a valid Publish2 JSON feed from given links URL
 */
function publish2_generatejson(url, number_of_items, page) {

  var json_feed = '';
  url = publish2_cleanurl(url);
	if (typeof(number_of_items) == 'undefined') {
		number_of_items = 10;
	}
	if (typeof(page) == 'undefined') {
		page = 1;
	}

  // Two types of Publish2 links URLs: Newsgroup and Search Links
  if (url.indexOf("/newsgroups/") != -1) {
    json_feed = url + '.js?number_of_items=' + number_of_items + '&page=' + page;
  } else if (url.indexOf("/search/links") != -1) {
    json_feed = url.replace("/search/links", "/search/links.js");
    if (url.indexOf("/search/links?") != -1) {
      json_feed += '&';
    } else {
      json_feed += '?';
    }
    json_feed += 'number_of_items=' + number_of_items + '&page=' + page;
  } else {
    json_feed = url + '?number_of_items=' + number_of_items + '&page=' + page;
  }
  json_feed += '&request_app=wordpress&request_func=linkassist&request_ver=' + publish2_version;
  return json_feed;

}

/**
 * Checks Publish2 URLs to determine whether they're valid or not
 */
function publish2_checkurl() {
	
	// Update document to indicate testing in progress
	jQuery('span#publish2URLnotice').removeClass('badURL testingURL goodURL');
	jQuery('span#publish2URLnotice').addClass('testingURL');
	
	var feedlink = jQuery('input.publish2URL').val();
	if (feedlink) {
	
		feedlink = publish2_cleanurl(feedlink);
		var json_url = publish2_generatejson(feedlink);
	
		jQuery.ajax({
		  dataType: 'jsonp',
			url: json_url,
			success: function(data, textStatus) {
			
				jQuery('span#publish2URLnotice').removeClass('testingURL');
				jQuery('input.publish2URL').removeClass('badURL goodURL needURL');
			
        jQuery('span#publish2URLnotice').addClass('goodURL');			
        jQuery('input.publish2URL').addClass('goodURL');

			},
			error: function(XMLHttpRequest, textStatus, errowThrown) {
				jQuery('span#publish2URLnotice').removeClass('badURL testingURL goodURL');
			},
		});
	
  } else {
    
    jQuery('span#publish2URLnotice').removeClass('testingURL');
		jQuery('input.publish2URL').removeClass('badURL goodURL');
		jQuery('input.publish2URL').addClass('needURL');
    
  }
	
}

jQuery(document).ready(function(){
	
	// Change between basic and advanced display options on settings page
	jQuery('.publish2-advanced_options').change(function() {
		if (jQuery(this).val() == 1) {
			jQuery(".normal_options").hide();
			jQuery(".advanced_options").show();
		} else {
			jQuery(".advanced_options").hide();
			jQuery(".normal_options").show();
		}
	});
	
	// Toggle last import and next scheduled import on settings page
	jQuery('tr.toggleImport').click(function() {
		if (jQuery(this).attr('id') == 'nextImport') {
			jQuery(this).hide();
			jQuery('tr#lastImported').show();
		} else if (jQuery(this).attr('id') == 'lastImported') {
			jQuery(this).hide();
			jQuery('tr#nextImport').show();
		}
	});
	
	// AJAX URL checker for all Publish2 URL input forms
	// Check on page load
	publish2_checkurl();
	// Check on input blur	
	jQuery('input.publish2URL').blur(function(){
		publish2_checkurl();
	});
	
});
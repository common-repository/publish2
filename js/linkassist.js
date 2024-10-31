// Detects the browser so we can make sure IE works. From: http://www.quirksmode.org/js/detect.html
var BrowserDetect = {
	init: function () {
		this.browser = this.searchString(this.dataBrowser) || "An unknown browser";
		this.version = this.searchVersion(navigator.userAgent)
			|| this.searchVersion(navigator.appVersion)
			|| "an unknown version";
		this.OS = this.searchString(this.dataOS) || "an unknown OS";
	},
	searchString: function (data) {
		for (var i=0;i<data.length;i++)	{
			var dataString = data[i].string;
			var dataProp = data[i].prop;
			this.versionSearchString = data[i].versionSearch || data[i].identity;
			if (dataString) {
				if (dataString.indexOf(data[i].subString) != -1)
					return data[i].identity;
			}
			else if (dataProp)
				return data[i].identity;
		}
	},
	searchVersion: function (dataString) {
		var index = dataString.indexOf(this.versionSearchString);
		if (index == -1) return;
		return parseFloat(dataString.substring(index+this.versionSearchString.length+1));
	},
	dataBrowser: [
		{
			string: navigator.userAgent,
			subString: "Chrome",
			identity: "Chrome"
		},
		{ 	string: navigator.userAgent,
			subString: "OmniWeb",
			versionSearch: "OmniWeb/",
			identity: "OmniWeb"
		},
		{
			string: navigator.vendor,
			subString: "Apple",
			identity: "Safari",
			versionSearch: "Version"
		},
		{
			prop: window.opera,
			identity: "Opera"
		},
		{
			string: navigator.vendor,
			subString: "iCab",
			identity: "iCab"
		},
		{
			string: navigator.vendor,
			subString: "KDE",
			identity: "Konqueror"
		},
		{
			string: navigator.userAgent,
			subString: "Firefox",
			identity: "Firefox"
		},
		{
			string: navigator.vendor,
			subString: "Camino",
			identity: "Camino"
		},
		{		// for newer Netscapes (6+)
			string: navigator.userAgent,
			subString: "Netscape",
			identity: "Netscape"
		},
		{
			string: navigator.userAgent,
			subString: "MSIE",
			identity: "Explorer",
			versionSearch: "MSIE"
		},
		{
			string: navigator.userAgent,
			subString: "Gecko",
			identity: "Mozilla",
			versionSearch: "rv"
		},
		{ 		// for older Netscapes (4-)
			string: navigator.userAgent,
			subString: "Mozilla",
			identity: "Netscape",
			versionSearch: "Mozilla"
		}
	],
	dataOS : [
		{
			string: navigator.platform,
			subString: "Win",
			identity: "Windows"
		},
		{
			string: navigator.platform,
			subString: "Mac",
			identity: "Mac"
		},
		{
			string: navigator.userAgent,
			subString: "iPhone",
			identity: "iPhone/iPod"
	    },
		{
			string: navigator.platform,
			subString: "Linux",
			identity: "Linux"
		}
	]

};
BrowserDetect.init();

jQuery(document).ready(function(){
	
	// Left and right arrow pagination for Link Assist!
	jQuery('#publish2-link_assist-links').hover(over,out);
	function over() {
		jQuery(document).bind('keydown', function(e){
			if (e.keyCode == 37) { 
				var page = jQuery('#p2-pagination_prev').attr('class');
				publish2_updatelinks(page);				
				return false;
			} else if (e.keyCode == 39) {
				var page = jQuery('#p2-pagination_next').attr('class');
				publish2_updatelinks(page);
				return false;
			}
		});
	}
	function out() {
		jQuery(document).unbind('keydown');
	}

});

/**
 * Adds the link passed to it to the text in the content area either using TinyMCE (if Visual enabled) or custom JS
 */
function publish2_additem(url, short_url, title, publication, date, journalist, journalist_url, comment, quote, tags, option) {
		
	// Reconvert the apostrophes in the title
	title = unescape(title);
	publication = unescape(publication);
	date = unescape(date);
	journalist = unescape(journalist);
	comment = unescape(comment);
	quote = unescape(quote);
	tags = unescape(tags);
	
	// Get the custom Link Assist metadata settings from the PHP dump on the page
	var linkassist_shorturl = jQuery('#p2-linkassist_shorturl').text();
	var linkassist_publication = jQuery('#p2-linkassist_publication').text();
	var linkassist_date = jQuery('#p2-linkassist_date').text();
	var linkassist_journalist = jQuery('#p2-linkassist_journalist').text();
	var linkassist_comment = jQuery('#p2-linkassist_comment').text();
	var linkassist_quote = jQuery('#p2-linkassist_quote').text();
	var linkassist_tags = jQuery('#p2-linkassist_tags').text();
	var linkassist_link_format = jQuery('#p2-linkassist_link_format').html();
	var video_width = jQuery('#p2-video_width').text();
	
	// Get the original URL so we can check it against the URL passing through
	var original_url = jQuery('#publish2-feedlink').val();
	if (original_url.indexOf(journalist_url) != -1) {
		var sameurl = true;
	}
	
	// Generate all of the different possibilities for content that we want to insert
	var insertcontent = '';
	var youtubeid = publish2_getvideoid(url);
	var vimeoid = publish2_getvideoid(url);
	
	// If short URLs are enabled, then make that the default URL
	if (linkassist_shorturl == 'on' && short_url) {
		url = short_url;
	}
	
	// If tokens are enabled, then parse the string for pointers. Else, use the settings
	if ((linkassist_link_format != '' && linkassist_link_format != ' ') && (option == 'linkmetadata' || option == 'wholetweet' || option == 'embedyoutube' || option == 'embedvimeo')) {
		
		if (linkassist_link_format.indexOf('%video%') != -1) {
		
			if (option == 'embedyoutube') {
				var video_height = video_width * .80208333333;
				var embed = '<object width="' + video_width + '" height="' + video_height + '"><param name="movie" value="http://www.youtube.com/v/' + youtubeid + '&hl=en&fs=1&"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/' + youtubeid + '&hl=en&fs=1&" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="' + video_width + '" height="' + video_height + '"></embed></object>';
				linkassist_link_format = linkassist_link_format.replace(/%video%/g, embed);
			} else if (option == 'embedvimeo') {
				var video_height = video_width * .675;
				var embed = '<object width="' + video_width + '" height="' + video_height + '"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=' + vimeoid + '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=' + vimeoid + '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="' + video_width + '" height="' + video_height + '"></embed></object>';
				linkassist_link_format = linkassist_link_format.replace(/%video%/g, embed);
			} else {
				linkassist_link_format = linkassist_link_format.replace(/%video%/g, '');
			}
		
		}	
		
		if (linkassist_link_format.indexOf('%headline%') != -1) {
			if (option == 'wholetweet') {
				title = '<a href="' + url + '">' + publication + '</a>: ' + title;
				publication = 'Twitter';
			} else {
				title = '<a href="' + url + '">' + title + '</a>';
			}
			linkassist_link_format = linkassist_link_format.replace(/%headline%/g, title);
		}
		
		if (linkassist_link_format.indexOf('%source%') != -1) {
			linkassist_link_format = linkassist_link_format.replace(/%source%/g, publication);
		}
		
		if (linkassist_link_format.indexOf('%date%') != -1) {
			linkassist_link_format = linkassist_link_format.replace(/%date%/g, date);
		}
		
		if (linkassist_link_format.indexOf('%journalist%') != -1) {
			journalist = '<a href="' + journalist_url + '">' + journalist + '</a>';
			linkassist_link_format = linkassist_link_format.replace(/%journalist%/g, journalist);
		}
		
		if (linkassist_link_format.indexOf('%comment%') != -1) {
			linkassist_link_format = linkassist_link_format.replace(/%comment%/g, comment);
		}
		
		if (linkassist_link_format.indexOf('%quote%') != -1) {
			linkassist_link_format = linkassist_link_format.replace(/%quote%/g, quote);
		}
		
		if (linkassist_link_format.indexOf('%tags%') != -1) {
			linkassist_link_format = linkassist_link_format.replace(/%tags%/g, tags);
		}
		
		// If it's Visual editor, replace all line breaks with HTML
		if (typeof(tinyMCE) != 'undefined' && (tinyMCE.activeEditor != null && tinyMCE.activeEditor.isHidden() == false)) {
			linkassist_link_format = linkassist_link_format.replace(/\n/g, '<br />');
		}
		
		insertcontent = linkassist_link_format;
		
	} else {
	
		if (option == 'wholetweet') {
			var insertcontent = '<a href="' + url + '">' + publication + '</a>: ' + title;
			publication = 'Twitter';
		} else {
		
			insertcontent += '<a href="' + url + '">' + title + '</a>';
			if (option == 'embedyoutube') {
				var video_height = video_width * .80208333333;
				insertcontent += publish2_addlinebreak();
				insertcontent += '<object width="' + video_width + '" height="' + video_height + '"><param name="movie" value="http://www.youtube.com/v/' + youtubeid + '&hl=en&fs=1&"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/' + youtubeid + '&hl=en&fs=1&" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="' + video_width + '" height="' + video_height + '"></embed></object>';
			} else if (option == 'embedvimeo') {
				var video_height = video_width * .675;
				insertcontent += publish2_addlinebreak();
				insertcontent += '<object width="' + video_width + '" height="' + video_height + '"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=' + vimeoid + '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=' + vimeoid + '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="' + video_width + '" height="' + video_height + '"></embed></object>';
			}
		
		}
		
		if ((linkassist_publication == 'on' && publication != '') && (linkassist_date == 'on' && date != '')) {
			insertcontent += publish2_addlinebreak();
			insertcontent += publication + ' | ' + date;
		} else if (linkassist_publication == 'off' && (linkassist_date == 'on' && date != '')) {
			insertcontent += publish2_addlinebreak();
			insertcontent += date;
		} else if (linkassist_publication == 'on' && publication != '') {
			insertcontent += publish2_addlinebreak();
			insertcontent += publication;
		}
	
		if (linkassist_journalist == 'on' && sameurl != true && (linkassist_comment == 'on' && comment != '')) {
			insertcontent += publish2_addlinebreak();
			insertcontent += '<a href="' + journalist_url + '">' + journalist + '</a> says: ' + comment;
		} else if (linkassist_comment == 'on' && comment != '') {
			insertcontent += publish2_addlinebreak();
			insertcontent += comment;
		}
	
		if (linkassist_quote == 'on' && quote != '') {
			insertcontent += publish2_addlinebreak();
			insertcontent += 'Quote: ' + quote;
		}
		
		if (linkassist_tags == 'on' && (tags != '' && tags != 'undefined')) {
			insertcontent += publish2_addlinebreak();
			insertcontent += tags;
		}

	}

	// If TinyMCE is active, then use its native functions. Else, use the custom js further down	
	if (typeof(tinyMCE) != 'undefined' && (tinyMCE.activeEditor != null && tinyMCE.activeEditor.isHidden() == false)) {
		
		var selectedtext = tinyMCE.activeEditor.selection.getContent({format : 'text'});
		var ed = tinyMCE.activeEditor;
		
		// Get and store the range so we can restore the caret later
		//var orgrange = ed.selection.getRng();
		
		//var startPos = ed.selection.getStart();
		//var endPos = ed.selection.getEnd();
		//var startPos = myField.selectionStart;
		//var endPos = myField.selectionEnd;
		
		// First option is to add just the link around selected text (or title if no text selected)
		if (option == 'link') {
			if (selectedtext == '') {
				ed.selection.collapse(false);
				if (publish2_urltest(url) == 'twitter') {
					var titlewurl = '<a href="' + url + '">'
									+ publication
									+ '</a>: '
									+ title;
				} else {
					var titlewurl = '<a href="' + url + '">'
									+ title
									+ '</a>';
				}
				
				// If the browser is IE, we have to use a function that doesn't place the link at the top of the page
				if (BrowserDetect.browser == 'Explorer') {
					ed.execCommand("mceInsertRawHTML", false, titlewurl);
				} else { 
					ed.selection.setContent(titlewurl);
				}
				
			}
			else {
			
				ed.execCommand("mceInsertLink", false, url);

			}
		}
		// Otherwise, use the functions we created above to insert the text
		else {
			
			if (BrowserDetect.browser == 'Explorer') {
				ed.execCommand("mceInsertRawHTML", false, insertcontent);
			} else {
				ed.selection.setContent(insertcontent);
			}
			
		}
		
		tinyMCE.activeEditor.focus();
		
	}
	
	else {
	
		var myField = document.getElementById('content');

		// Internet Explorer support
		if (document.selection) {
			myField.focus();
			sel = document.selection.createRange();
			if (option == 'link') {
				if (sel.text.length == 0) {
					if (publish2_urltest(url) == 'twitter') {
						sel.text = '<a href="' + url + '">'
									+ publication
									+ '</a>: '
									+ title;
					} else {
						sel.text = '<a href="' + url + '">'
									+ title
									+ '</a>';
					}
				} else {
					sel.text = '<a href="' + url + '">'
								+ sel.text
								+ '</a>';
				}
			} else {
				sel.text = insertcontent;
			}
			myField.focus();
		}
		// Mozilla and Netscape support
		else if (myField.selectionStart) {

			var startPos = myField.selectionStart;
			var endPos = myField.selectionEnd;
			var scrollTop = myField.scrollTop;
			if (option == 'link') {
				if (startPos != endPos) {
					myField.value = myField.value.substring(0, startPos)
									+ '<a href="' + url + '">'
									+ myField.value.substring(startPos, endPos)
									+ '</a>'
									+ myField.value.substring(endPos, myField.value.length);
					var stringlength = endPos - startPos;
					var urllength = url.length + 15 + stringlength;
					myField.selectionStart = startPos + urllength;
					myField.selectionEnd = startPos + urllength;
				} else if (startPos == endPos) {
					if (publish2_urltest(url) == 'twitter') {
						myField.value = myField.value.substring(0, startPos)
									+ '<a href="' + url + '">'
									+ publication
									+ '</a>: '
									+ title
									+ myField.value.substring(endPos, myField.value.length);
					} else {
						myField.value = myField.value.substring(0, startPos)
									+ '<a href="' + url + '">'
									+ title
									+ '</a>'
									+ myField.value.substring(endPos, myField.value.length);
					}
					var urllength = 15 + url.length + title.length;
					myField.selectionStart = startPos + urllength;
					myField.selectionEnd = startPos + urllength;
				}
			} else {
				myField.value = myField.value.substring(0, startPos)
								+ insertcontent
								+ myField.value.substring(endPos, myField.value.length);
				var contentlength = myField.value.length;	
				myField.selectionStart = startPos + contentlength;
				myField.selectionEnd = startPos + contentlength;
			}
			myField.focus();
			myField.scrollTop = scrollTop;

		}
		// If nothing exists in the textarea, then we'll just replace its value with 
		else {
			
			if (jQuery('#content').val() == '') {
				if (option == 'link') {
					if (publish2_urltest(url) == 'twitter') {
						insertcontent = '<a href="' + url + '">' + publication + '</a>: ' + title;
					} else {
						insertcontent = '<a href="' + url + '">' + title + '</a>';
					}
				}
				jQuery('#content').val(insertcontent);
			}
			
		}
	
	}
	
}

/**
 * Returns different types of line breaks whether TinyMCE is active or not
 */
function publish2_addlinebreak() {
	if (typeof(tinyMCE) != 'undefined' && (tinyMCE.activeEditor != null && tinyMCE.activeEditor.isHidden() == false)) {
		return '<br />';
	} else {
		return '\n';
	}
}

/**
 * Tests the link URL to see if it's a tweet, video, etc. or not
 */
function publish2_urltest(url) {
	
	if (url.match(/http(?:s*):\/\/twitter.com\/(\S+)\/(?:status|statuses)\/(\d+)/) != null) {
		return 'twitter';
	} else if (url.match(/http:\/\/www.youtube.com\/watch/) != null) {
		return 'youtube';
	} else if (url.match(/http:\/\/www.vimeo.com/) != null || url.match(/http:\/\/vimeo.com/) != null) {
		return 'vimeo';
	} else {
		return 'standard';
	}
	
}

// Get the video ID from the URL and return it
function publish2_getvideoid(url) {
	
	if (publish2_urltest(url) == 'youtube') {
		var youtubeidstart = url.indexOf('=');
		youtubeidstart += 1;
		var youtubeidend = url.indexOf('&');
		if (youtubeidend == -1) {
			var youtubeid = url.slice(youtubeidstart);
		} else {
			youtubeidend += 1;
			var youtubeid = url.slice(youtubeidstart, youtubeidend);
		}
		return youtubeid;
	} else if (publish2_urltest(url) == 'vimeo') {
		if (url.match(/http:\/\/www.vimeo.com/) != null) {
			vimeoid = url.replace(/http:\/\/www.vimeo.com/, '');
		} else if (url.match(/http:\/\/vimeo.com/) != null) {
			vimeoid = url.replace(/http:\/\/vimeo.com/, '');
		}
		vimeoid = vimeoid.replace(/\//, '');
		return vimeoid;
	}
	
}

/**
 * Get the type of feedlink based on the URL structure
 * @todo Outdated, needs updating
 */
function publish2_getfeedlinktype(feedlink) {
	
	// This set of conditionals is set as all 'if's such that the last ones will take priority
	if (feedlink.indexOf('/journalists/') != -1) {
		feedlink_type = 'journalist';
		if (feedlink.indexOf('/social/twitter') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social/youtube') != -1) {
			feedlink_type += '&social&youtube';
		} else if (feedlink.indexOf('/social') != -1) {
			feedlink_type += '&social';
		} else if (feedlink.indexOf('/basic') != -1) {
			feedlink_type += '&basic'
		}
	} else if (feedlink.indexOf('/users/') != -1) {
		feedlink_type = 'sponsor';
		if (feedlink.indexOf('/social/twitter') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social/youtube') != -1) {
			feedlink_type += '&social&youtube';
		} else if (feedlink.indexOf('/social') != -1) {
			feedlink_type += '&social';
		} else if (feedlink.indexOf('/basic') != -1) {
			feedlink_type += '&basic'
		}
	} else if (feedlink.indexOf('/newsgroups/') != -1) {
		feedlink_type = 'newsgroup';
		if (feedlink.indexOf('/social/twitter') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social/youtube') != -1) {
			feedlink_type += '&social&youtube';
		} else if (feedlink.indexOf('/social') != -1) {
			feedlink_type += '&social';
		} else if (feedlink.indexOf('/basic') != -1) {
			feedlink_type += '&basic'
		}
	} else if (feedlink.indexOf('/user-newsgroups/') != -1) {
			feedlink_type = 'spons_newsgroup';
			if (feedlink.indexOf('/social/twitter') != -1) {
				feedlink_type += '&social&twitter';
			} else if (feedlink.indexOf('/social/youtube') != -1) {
				feedlink_type += '&social&youtube';
			} else if (feedlink.indexOf('/social') != -1) {
				feedlink_type += '&social';
			} else if (feedlink.indexOf('/basic') != -1) {
				feedlink_type += '&basic'
			}
	} else if (feedlink.indexOf('/network/') != -1) {
		feedlink_type = 'network';
		if (feedlink.indexOf('/social/twitter') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social/youtube') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social') != -1) {
			feedlink_type += '&social';
		} else if (feedlink.indexOf('/basic') != -1) {
			feedlink_type += '&basic'
		}
	} else if (feedlink.indexOf('/user-network/') != -1) {
		feedlink_type = 'network';
		if (feedlink.indexOf('/social/twitter') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social/youtube') != -1) {
			feedlink_type += '&social&twitter';
		} else if (feedlink.indexOf('/social') != -1) {
			feedlink_type += '&social';
		} else if (feedlink.indexOf('/basic') != -1) {
			feedlink_type += '&basic'
		}
	} else if (feedlink.indexOf('/topics') != -1) {
		feedlink_type = 'topics';
	} else if (feedlink.indexOf('/social') != -1) {
		feedlink_type = 'social';
		if (feedlink.indexOf('/social/twitter') != -1) {
			feedlink_type += '&twitter';
		} else if (feedlink.indexOf('/social/youtube') != -1) {
			feedlink_type += '&youtube';
		}
	} else if (feedlink.indexOf('/recent') != -1) {
		feedlink_type = 'recent';
	} else {
		feedlink_type = 'unknown';
	}
	return feedlink_type;
	
}

/**
 * Based on the feedlink URL and a standard naming type, generate a new links url and return it
 * @todo Outdated, needs updating
 */
function publish2_generatefeedlink(feedlink, future_type) {
	
	current_type = publish2_getfeedlinktype(feedlink);
	feedlink = publish2_cleanurl(feedlink);
	var newfeedlink = '';
	if (current_type == 'journalist&social&twitter' || current_type == 'sponsor&social&twitter') {
		if (future_type == 'all') {
			newfeedlink = feedlink.replace(/\/social\/twitter/, '/links');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/social\/twitter/, 'social/youtube');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/\/social\/twitter/, '/social');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'journalist&social&youtube' || current_type == 'sponsor&social&youtube') {
		if (future_type == 'all') {
			newfeedlink = feedlink.replace(/\/social\/youtube/, '/links');
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/\/social\/youtube/, '/social/twitter');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/\/social\/youtube/, '/social');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'journalist&social' || current_type == 'sponsor&social') {
		if (future_type == 'all') {
			newfeedlink = feedlink.replace(/\/social/, '/links');
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/\/social/, '/social/twitter');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/\/social/, '/social/youtube');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'journalist' || current_type == 'sponsor') {
		if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/\/links/, '/social/twitter');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/\/links/, '/social/youtube');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/\/links/, '/social');
		} else {
			newfeedlink = feedlink;
		}
	}
	
	if (current_type == 'newsgroup&social&twitter' || current_type == 'spons_newsgroup&social&twitter') {
		if (future_type == 'all') {
			newfeedlink = feedlink.replace(/\/social\/twitter/, '');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/\/social\/twitter/, '/social/youtube');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/\/social\/twitter/, '/social');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'newsgroup&social&youtube' || current_type == 'spons_newsgroup&social&youtube') {
		if (future_type == 'all') {
			newfeedlink = feedlink.replace(/\/social\/youtube/, '');
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/\/social\/youtube/, '/social/twitter');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/\/social\/youtube/, '/social');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'newsgroup&social' || current_type == 'spons_newsgroup&social') {
		if (future_type == 'all') {
			newfeedlink = feedlink.replace(/\/social/, '');
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/\/social/, '/social/twitter');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/\/social/, '/social/youtube');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'newsgroup' || current_type == 'spons_newsgroup') {
		if (future_type == 'twitter') {
			newfeedlink = feedlink + '/social/twitter';
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink + '/social/youtube';
		} else if (future_type == 'social') {
			newfeedlink = feedlink + '/social';
		} else {
			newfeedlink = feedlink;
		}
	}
	
	if (current_type == 'social&twitter') {
		if (future_type == 'all') {
			newfeedlink = 'http://www.publish2.com/recent';
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/social\/twitter/, 'social/youtube');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/social\/twitter/, 'social');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'social&youtube') {
		if (future_type == 'all') {
			newfeedlink = 'http://www.publish2.com/recent';
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/social\/youtube/, 'social/twitter');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/social\/youtube/, 'social');
		} else {
			newfeedlink = feedlink;
		}
	} else if (current_type == 'social') {
		if (future_type == 'all') {
			newfeedlink = 'http://www.publish2.com/recent';
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/\/social/, '/social/twitter');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/\/social/, '/social/youtube');
		} else {
			newfeedlink = feedlink;
		}
	}
	
	if (current_type == 'topics') {
		if (future_type == 'all') {
			newfeedlink = 'http://www.publish2.com/recent';
		} else if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/topics/, 'social/twitter');
		} else if (future_type == 'youtube') {
			newfeedlink = feedlink.replace(/topics/, 'social/youtube');
		} else if (future_type == 'social') {
			newfeedlink = feedlink.replace(/topics/, 'social');
		} else {
			newfeedlink = feedlink;
		}
	}
	
	if (current_type == 'recent') {
		if (future_type == 'twitter') {
			newfeedlink = feedlink.replace(/recent/, 'social/twitter');
		} else if (future_type == 'all') {
			newfeedlink = feedlink.replace(/social\/twitter/, 'recent');
		}
	}
	
	return newfeedlink;
	
}

/**
 * Reveals or hides the source of links within the Link Assist widget
 */
function publish2_urlsource(action) {
	
	action = parseInt(action);
	if (action == 1) {
	  jQuery('#p2-link_assist-reset').hide();
		jQuery("#p2-links_header").hide();
		jQuery("#p2-source-option").fadeIn('normal');
	} else if (action == 2) {
		jQuery("#p2-source-option").hide();
	  jQuery('#p2-link_assist-reset').show();		
		jQuery("#p2-links_header").fadeIn('normal');
		var feedlink = jQuery('#publish2-feedlink').val();
		feedlink = publish2_cleanurl(feedlink);
		publish2_listlinks(feedlink);
	} else if (action == 3) {
		jQuery("#p2-source-option").hide();
		jQuery('#p2-link_assist-reset').show();
		jQuery("#p2-links_header").fadeIn('normal');
	}
	
}

// Take a Publish2 tag and present it in a format that's usable in a URL
function publish2_cleantag(thetag) {
	
	// Clean up the adhoc rating system that some use
	if (thetag == '*') {
		thetag = '-9';
	} else if (thetag == '**') {
		thetag = '-4';
	} else if (thetag == '***') {
		thetag = '-3';
	} else if (thetag == '****') {
		thetag = '-2';
	} else if (thetag == '*****') {
		thetag = '-5';
	}
	// All sorts of crazy characters that need to be filtered out
	thetag = thetag.replace(/\s+/g, "-");
	thetag = thetag.replace(/&/g, '-');
	thetag = thetag.replace(/---/g, '-');
	thetag = thetag.replace(/\./g, '');
	thetag = thetag.replace(/#/g, '-');
	thetag = thetag.replace(/\'/g, '-');
	return thetag;
		
}

function publish2_linksnav(option) {
	
	var feedlink = jQuery('#publish2-feedlink').val();
	var working_feedlink = jQuery('#p2-temp_working_feedlink').html();
	var thetag = jQuery('#p2-filter_tag').val();
	var links_low = jQuery('#p2-temp_links_low').html();
	var links_low = parseInt(links_low);
	
	if (option == true) {
		links_low = links_low + 3;
		publish2_listlinks(feedlink, thetag, links_low);		
	} else if (option == 'reset') {
		publish2_listlinks(feedlink);
	} else if (option == 'filter') {
		if (thetag == undefined || thetag == '') {
			if (working_feedlink.indexOf('/topics') != -1) {
				publish2_listlinks('http://www.publish2.com/recent', null, null);
			} else {
				publish2_listlinks(working_feedlink, null, null);
			}
		} else if (working_feedlink != feedlink) {
			publish2_listlinks(working_feedlink, thetag, null);
		} else {
			publish2_listlinks(feedlink, thetag, null);
		}
	} else if (option == 4) {
		feedlink = jQuery('#publish2-feedlink_new').val();
		publish2_listlinks(feedlink);
		jQuery('#publish2-feedlink').val(feedlink);
	} else {
		links_low = links_low - 3;
		publish2_listlinks(feedlink, thetag, links_low);
	}
	
}

function publish2_updatelinks(page_num) {
	
	var total_links = jQuery('#p2-temp_total_links').html();
	
	if (page_num < 1) {
		page_num = 1;
	}
	links_low = (page_num - 1) * 3;
	var links_high = links_low + 2;
	
	// Cycles through and shows or hides each link based on the lowest number that should be displayed
	for (i = 0; i < total_links; i++) {
		var link_num = i + 1;
		var link_div = '#p2-link_num-' + link_num;
		if (i >= links_low && i <= links_high) {
			jQuery(link_div).show();
		} else {
			jQuery(link_div).hide();
		}
	}
	
	// Create pagination based on the links you just displayed
	publish2_create_pagination(links_low);
	
}

/**
 * Builds the side tabs to go backwards and fowards through the user's links
 */
function publish2_create_pagination(links_low, total_links) {
	
	if (total_links == null) {
		total_links = jQuery('#p2-temp_total_links').html();
	}

	// Generate the "previous" button and action. Won't display if on the first page of links
	page_num = (links_low / 3);
	prev_page_action = "publish2_updatelinks(" + page_num + ")"
	p2_pagination_html = '<a id="p2-pagination_prev" class="' + page_num + '" onclick="' + prev_page_action + '"';
	if (links_low == 0) {
		p2_pagination_html += ' style="display:none;"';
	}	
	p2_pagination_html += ' title="Previous links">';
	p2_pagination_html += '<div id="p2-pagination_prev-button"></div';
	p2_pagination_html += '</a>';

	// Generate the "next" button and action. Won't display if there isn't another page of links
	page_num = (links_low / 3) + 2;
	next_page_action = "publish2_updatelinks(" + page_num + ")";
	p2_pagination_html += '<a id="p2-pagination_next" class="' + page_num + '" onclick="' + next_page_action + '"';
	var page_max = links_low + 3;
	if (total_links <= page_max) {
		p2_pagination_html += ' style="display:none;"';
	}
	p2_pagination_html += ' title="Next links">';
	p2_pagination_html += '<div id="p2-pagination_next-button"></div';
	p2_pagination_html += '</a>';
	
	// Add the pagination to the Link Assist div
	jQuery("#p2-pagination").empty().html(p2_pagination_html);
	
	// Display the number of links displayed at the bottom of the Link Assist window
	var low = links_low + 1;
	var links_high = links_low + 2;
	var high = links_high;
	if (total_links > 2) {
		high++;
	}
	p2_link_count = 'Displaying ' + low;
	difference = (total_links - low);
	if (total_links < 1 || total_links == low) {
		p2_link_count += ' of ' + total_links + ' links';
	} else if (difference <= 2){
		p2_link_count += '-' + (low + difference) + ' of ' + total_links + ' links';
	} else {
		p2_link_count += '-'
						+ high
						+ ' of '
						+ total_links;
		if (total_links == 100) {
			p2_link_count += '+';
		}
		p2_link_count += ' links';
	}
	jQuery("#p2-link_count").empty().html(p2_link_count);
	
}

/**
 * Generates and pulls the JSON feed, then passes to a new function
 */
function publish2_listlinks(feedlink, thetag, links_low) {

	if (links_low == null || links_low < 0) {
		links_low = 0;
	}
	
	// Make sure the feedlink has a "http://" and trailing forward slash
	feedlink = publish2_cleanurl(feedlink);
	
	// Do a catch if they've accidentally left a space in the box
	if (thetag == " ") {
		thetag = null;
	}
	
	var json_url = publish2_generatejson(feedlink, 30);

	// Set the total number of links to display
	var links_high = links_low + 2;
	
	// Start displaying the "loading" graphic
	var rotating_reset = '<img src="' + publish2_plugin_url + '/img/loading-red.gif" />';
	jQuery("#p2-link_assist-reset").html(rotating_reset);
	
	jQuery.ajax({
		url: json_url,
		dataType: "jsonp",
		success: function(data, textStatus) {
			
			// We're not doing error checking on data anymore because the application returns proper response codes
		  publish2_generatelinks(data, links_low, feedlink);
			
		},
		error: function(XMLHttpRequest, textStatus, errowThrown) {
			link_element = '<div id="p2-link_404">';
			link_element += "<p><em>Oopsy daisy</em>, Link Assist wasn't able to get any links from Publish2. Try refreshing?<br />";
			link_element += "Official error: " + textStatus + "</p>";
			link_element += '</div>';
			jQuery("#publish2-link_assist-links").html(link_element);
		}
	});

}

/**
 * Generate the links from the JSON object, then print to the screen
 */
function publish2_generatelinks(data, links_low, feedlink) {
	
	// Set the total number of links to display
	var links_high = links_low + 2;
	
	// If the URL hasn't generated a valid JSON feed, prompt for a new URL
	if (data == 'badfeed' || data == 'badtag') {

		// Clean out everything in the div
		jQuery("#p2-pagination").empty();
		jQuery("#p2-link_count").empty();
		jQuery("#p2_links_title").empty().html("Link journalism at your fingertips");
		
		link_element = '<div id="p2-link_404">';
		if (data == 'badfeed') {
			link_element += "<p><em>Whoops</em>, that URL doesn't seem to work.</p>";
			link_element += '<input type="text" id="publish2-feedlink_new" name="publish2-feedlink_new" size="18" />';
			link_element += ' <a href="javascript:void(0)" onclick="publish2_linksnav(4);return false;" class="button primary">Try again</a>';
		} else if (data == 'badtag') {
			link_element += "<p><em>Erp</em>, that tag doesn't seem to produce any links. Try a new one?</p>";
		}
		link_element += '</div>';
	
	} else {
	
		var feedlink_type = publish2_getfeedlinktype(feedlink);

		// Cleans up the title of the JSON feed so we can use it as the header of Link Assist
		var title = data.title;
		jQuery("#p2_links_title").empty().html(title);
		
		var original_feedlink = publish2_cleanurl(jQuery('#publish2-feedlink').val());
		var original_feedlink_type = publish2_getfeedlinktype(original_feedlink);
		var working_feedlink = publish2_cleanurl(feedlink);
		var working_feedlink_type = publish2_getfeedlinktype(working_feedlink);
		var thetag = jQuery('#p2-filter_tag').val();
		
		if (original_feedlink == working_feedlink) {
			var sameurl = true;
		}
		
		// If the original feedlink is from a journalist or sponsor, then build a nav bar with "My Links", "My Network", and "Newswire"
		if (original_feedlink_type.indexOf('journalist') != -1 || original_feedlink_type.indexOf('sponsor') != -1) {
			
			var networknav = '';
			if (working_feedlink != original_feedlink) {
				originalaction = "publish2_listlinks('" + original_feedlink;
				if (thetag != '') { originalaction += "', '" + thetag; }
				originalaction += "');return false;";
				networknav += '<a href="' + original_feedlink + '" onclick="' + originalaction + '">My Links</a> | ';
			} else {
				networknav += 'My Links | ';
			}
			if (working_feedlink_type.indexOf('network') != -1) {
				networknav += 'My Network Links | ';
			} else {
				if (original_feedlink_type.indexOf('journalist') != -1) {
					var networklinks = original_feedlink.replace(/journalists/, 'network');
				} else if (original_feedlink_type.indexOf('sponsor') != -1) {
					var networklinks = original_feedlink.replace(/users/, 'user-network');
				}
				var networklinksaction = "publish2_listlinks('" + networklinks;
				if (thetag != '') {
					networklinksaction += "', '" + thetag;
				}
				networklinksaction += "');return false;";
				networknav += '<a href="' + networklinks + '" onclick="'+ networklinksaction + '"' + '>My Network Links</a> | ';
			}
			if (working_feedlink_type.indexOf('recent') != -1 || working_feedlink_type.indexOf('topics') != -1) {
				networknav += 'Newswire';
			} else {
				if (thetag != '') {
					var newswiresaction = "publish2_listlinks('http://www.publish2.com/topics', '" + thetag + "');return false;";
					thetag = publish2_cleantag(thetag);
					var newswirelink = 'http://www.publish2.com/topics/' + thetag;
				} else {
					var newswiresaction = "publish2_listlinks('http://www.publish2.com/recent');return false;";
					var newswirelink = 'http://www.publish2.com/recent';
				}
				networknav += '<a href="' + newswirelink + '" onclick="'+ newswiresaction + '"' + '>Newswire</a>';
			}
			jQuery('#p2-network_nav').empty().html(networknav);
		}
		// If the original feedlink is from a Newsgroup, then just display "My Newsgroup" and "Newswire" as the options
		else if (original_feedlink.indexOf('newsgroups') != -1 || original_feedlink.indexOf('spons_newsgroup') != -1) {
			
			var networknav = '';
			if (working_feedlink != original_feedlink) {
				originalaction = "publish2_listlinks('" + original_feedlink;
				if (thetag != '') {
					originalaction += "', '" + thetag;
				}
				originalaction += "');return false;";
				networknav += '<a href="' + original_feedlink + '" onclick="' + originalaction + '">Newsgroup Links</a> | ';
			} else {
				networknav += 'Newsgroup Links | ';
			}
			if (working_feedlink_type.indexOf('recent') != -1 || working_feedlink_type.indexOf('topics') != -1) {
				networknav += 'Newswire';
			} else {
				if (thetag != '') {
					var newswiresaction = "publish2_listlinks('http://www.publish2.com/topics', '" + thetag + "');return false;";
					thetag = thetag.replace(/\'/g, '-');
					var newswirelink = 'http://www.publish2.com/topics/' + thetag;
				} else {
					var newswiresaction = "publish2_listlinks('http://www.publish2.com/recent');return false;";
					var newswirelink = 'http://www.publish2.com/recent';
				}
				networknav += '<a href="' + newswirelink + '" onclick="'+ newswiresaction + '"' + '>Newswire</a>';
			}
			/* jQuery('#p2-network_nav').empty().html(networknav); */
			jQuery('#p2-network_nav').empty().html('');
			
		}
		// For any other source of links, just empty the network nav bar
		else {
			jQuery('#p2-network_nav').empty();
		}
	
		var link_element = '';
		
		// If the source of links is either Twitter, YouTube, or social journalism, then allow the option to exit
		if (feedlink_type.indexOf('twitter') != -1) {
			var newfeedlink = publish2_generatefeedlink(feedlink, 'all');
			var resettoall = "publish2_listlinks('" + newfeedlink + "');return false;";
			link_element += '<div id="p2-sort_links">';
			link_element += 'Browsing tweets. <a href="' + newfeedlink + '" onclick="' + resettoall + '">See all links</a>?';
			link_element += '</div>';
		} else if (feedlink_type.indexOf('youtube') != -1) {
			var newfeedlink = publish2_generatefeedlink(feedlink, 'all');
			var resettoall = "publish2_listlinks('" + newfeedlink + "');return false;";
			link_element += '<div id="p2-sort_links">';
			link_element += 'Browsing YouTube videos. <a href="' + newfeedlink + '" onclick="' + resettoall + '">See all links</a>?';
			link_element += '</div>';
		} else if (feedlink_type.indexOf('social') != -1) {
			var newfeedlink = publish2_generatefeedlink(feedlink, 'all');
			var resettoall = "publish2_listlinks('" + newfeedlink + "');return false;";
			link_element += '<div id="p2-sort_links">';
			link_element += 'Browsing social links. <a href="' + newfeedlink + '" onclick="' + resettoall + '">See all</a>?';
			link_element += '</div>';
		}
	
		var total_links = 0;
		jQuery.each(data.items, function(i, item) {
			total_links++;
		});

		jQuery.each(data.items,	function(i, item) {
		
			// Wrap the entire link in a div to determine whether to display it or not
			link_element += '<div class="p2-link"';
			if (i < links_low || i > links_high) {
				link_element += ' style="display:none;"';	
			}
			link_num = i + 1;
			link_element += ' id="p2-link_num-' + link_num + '">';
			
			// If the link saved is a tweet, the show the tweet, the username, and the saved date
			if (publish2_urltest(item.link) == 'twitter' && item.link_meta != undefined) {
				title = item.title.replace(/\\"/, '"');
				if (item.publication_name == '') { item.publication_name == 'Twitter' }
				link_element += '<div class="p2-link_tweet-title">'
								+ '<a href="' + item.link + '" target="_blank" class="p2-link_twitter-name" title="Open tweet in new window">'
								+ item.link_meta.screen_name + '</a>: '
								+ item.link_meta.tweet_text
								+ '</div>';
				var newfeedlink = publish2_generatefeedlink(feedlink, 'twitter');
				var socialaction = "publish2_listlinks('" + newfeedlink + "');return false;";
				link_element += '<div class="p2-link_tweet-meta">';
								/* + '<a href="' + newfeedlink + '" onclick="' + socialaction + '" title="Filter links to just tweets">'
								+ '<img src="' + item.icon + '" class="p2-twitter_icon" />'
								+ '</a>'; */
				if (item.publication_date != '') {
					link_element += item.publication_date;
				}
				link_element += '</div>';			
	
				
			}
			// Else if it's a YouTube video, display the title and an embed
			else if (publish2_urltest(item.link) == 'youtube') {
				var youtubeid = publish2_getvideoid(item.link);
				var newfeedlink = publish2_generatefeedlink(feedlink, 'youtube');
				var socialaction = "publish2_listlinks('" + newfeedlink + "');return false;";
				link_element += '<div class="p2-link_title">'
								+ '<a href="' + item.link + '" target="_blank" title="Open video in new window">'
								+ item.title
								+ '</a>'
								+ '</div>'
								+ '<div class="p2-link_video"><object width="230" height="190"><param name="movie" value="http://www.youtube.com/v/' + youtubeid + '&hl=en&fs=1&"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/' + youtubeid + '&hl=en&fs=1&" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="230" height="190"></embed></object></div>';
				link_element += '<div class="p2-link_youtube-meta">';
				/*
				link_element += '<a href="' + newfeedlink + '" onclick="' + socialaction + '" title="Filter links to just YouTube videos">'
								+ '<img src="' + item.icon + '" class="p2-youtube_icon" />'
								+ '</a>'; */
				if (item.publication_date != '') {
					link_element += 'Uploaded on ' + item.publication_date;
				}
				link_element += '</div>';
			}
			// Else if it's a Vimeo video, display the title and an embed
			else if (publish2_urltest(item.link) == 'vimeo') {
				var vimeoid = publish2_getvideoid(item.link);
				link_element += '<div class="p2-link_title">'
								+ '<a href="' + item.link + '" target="_blank" title="Open video in new window">'
								+ item.title
								+ '</a>'
								+ '</div>'
				 				+ '<div class="p2-link_video"><object width="230" height="155"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=' + vimeoid + '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=' + vimeoid + '&amp;server=vimeo.com&amp;show_title=0&amp;show_byline=0&amp;show_portrait=0&amp;color=00adef&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="230" height="155"></embed></object></div>'
				 				+ '<div class="p2-link_pubdate">' + item.publication_date + '</div>';
			}
			// If it's any other type of link, then do the normal style display
			else {
				link_element += '<div class="p2-link_title">'
								+ '<a href="' + item.link + '" target="_blank" title="Open link in new window">'
								+ item.title
								+ '</a>'
								+ '</div>';
				if (item.publication_name != '' || item.publication_date != '') {
					if (item.publication_name != '' && item.publication_date != '') {
						link_element += '<div class="p2-link_pubdate">' + item.publication_name + ' | ' + item.publication_date + '</div>';
					} else if (item.publication_name == '' || item.publication_date == '') {
						link_element += '<div class="p2-link_pubdate">' + item.publication_name + item.publication_date + '</div>';
					}
				}
			}
			
			// Show either the journalist and the link description, or just the journalist's name
			// 'Journalist' can refer to either a journalist account or sponsor account
			if (sameurl == true && (feedlink_type.indexOf('journalist') != -1 || feedlink_type.indexOf('sponsor') != -1)) {
				if (item.description != '') {
					link_element += '<div class="p2-link_description">'
									+ item.description
									+ '</div>';
				}
			} else {
				link_element += '<div class="p2-link_description">';
				var journalistlinks = item.journalist_profile + '/links';
				var journalistaction = "publish2_listlinks('" + journalistlinks + "');return false;";
				/* link_element += '<a href="' + journalistlinks + '" onclick="' + journalistaction + ';">'
								+ item.journalist_full_name + '</a>'; */
        link_element += item.journalist_full_name;
				if (item.description != '' && item.description != null) {
					link_element += ' says: ' + item.description;
				} else {
					link_element += " saved this link.";
				}
				link_element += '</div>';
			} 
			
			if (item.quote != '' && item.quote != null && publish2_urltest(item.link) != 'twitter') {
				
				/* if (item.quote.charAt(0) != '"') {
					item.quote = '"' + item.quote;
				}
				quote_length = item.quote.length - 1;
				if (item.quote.charAt(quote_length) != '"') {
					item.quote = item.quote + '"';
				} */
				
				link_element += '<div class="p2-link_quote"><blockquote>'
								+ item.quote
								+ '</blockquote></div>';
			}
			
			// There are some feeds that jQuery won't parse unless you have both statements here. It's majik
			if (item.tags != undefined) {
				link_tags = '<div class="p2-link_tags">Tagged:';
				jQuery.each(item.tags, function(i, tag) {
					/* thetag = tag.name.replace(/\'/g, '-');
					if (feedlink_type.indexOf('topics') != -1 || feedlink_type.indexOf('recent') != -1) {
						feedlink = 'http://www.publish2.com/topics/';
						taglink = tag.link;
					} else if (feedlink_type.indexOf('network') != -1) {
						taglink = feedlink + '/' + publish2_cleantag(tag.name);
					} else {
						taglink = tag.link;
					}
					var tagaction = "publish2_listlinks('" + feedlink + "', '" + thetag + "');return false;";
					link_tags += ' <a href="' + taglink + '" onclick="' + tagaction + '">' + tag.name + '</a>,'; */
					link_tags += ' ' + tag.name + ',';
				});
				link_tags = link_tags.replace(/,$/,"");
				link_element += link_tags + '</div>';
			}

			// Generates a list of all the tags with links that we can pass to the add to post function
			if (item.tags != undefined && item.tags != undefined) {
				var alltags = 'Tags: '
				jQuery.each(item.tags, function(i, tag) {
					alltags += ' <a href="' + tag.link + '">' + tag.name + '</a>,';
				});
				alltags = alltags.replace(/,$/,"");
			}
			
			// Encode the link's metadata so it doesn't break the Javascript function
			item.title = escape(item.title);
			if (item.publication_name != '') {
				item.publication_name = escape(item.publication_name);
			}
			item.publication_date = escape(item.publication_date);
			item.journalist_full_name = escape(item.journalist_full_name);
			if (item.description != '' && item.description != null) {
				item.description = escape(item.description);
			} else if (item.description == null) {
        item.description = '';
      }
			if (item.quote != '' && item.quote != null) {
				item.quote = escape(item.quote);
			} else if (item.quote == null) {
			  item.quote = '';
			}
			if (alltags != undefined) {
				alltags = escape(alltags);
			}
			
			// This is the base set of data sent for every action
			var actiondefault = "publish2_additem('" + item.link + "', '" + item.short_url + "', '" + item.title + "', '" + item.publication_name + "', '" + item.publication_date + "', '" + item.journalist_full_name + "', '" + item.journalist_profile + "', '" + item.description + "', '" + item.quote + "', '" + alltags + "'";
			
			// If the link is a tweet, then generate the actions and create a dropdown
			if (publish2_urltest(item.link) == 'twitter') {
				
				var addlink = actiondefault + ", 'link');return false;";
				var addtweet = actiondefault + ", 'wholetweet');return false;";
				link_element += '<div class="p2-link_action">'
 								+ '<a title="Add tweet link to selected text" href="javascript:void(0);" class="button" onclick="' + addlink + '"><img src="../wp-content/plugins/publish2/img/link.gif" height="15" width="15" style="padding-right:5px;position:relative;top:4px;" />Link</a> &nbsp;&nbsp;'
								+ '<a title="Add full tweet to post" href="javascript:void(0);" class="button" onclick="' + addtweet + '">Add tweet and details</a>'
 								+ '</div>';
				
			}
			// Else if it's a YouTube video, generate a separate set of actions
			else if (publish2_urltest(item.link) == 'youtube') {
				
				var addlink = actiondefault + ", 'link');return false;";
				var embedyoutube = actiondefault + ", 'embedyoutube');return false;";
				link_element += '<div class="p2-link_action">'
 								+ '<a title="Add video link to selected text" href="javascript:void(0);" class="button" onclick="' + addlink + '"><img src="../wp-content/plugins/publish2/img/link.gif" height="15" width="15" style="padding-right:5px;position:relative;top:4px;" />Link</a> &nbsp;&nbsp;'
								+ '<a title="Embed video and saved details in post" href="javascript:void(0);" class="button" onclick="' + embedyoutube + '">Embed video</a>'
				 				+ '</div>';
				
			}
			// Else if it's a Vimeo video, generate the add link and embed video actions
			else if (publish2_urltest(item.link) == 'vimeo') {
				
				var addlink = actiondefault + ", 'link');return false;";
				var embedvimeo = actiondefault + ", 'embedvimeo');return false;";
				link_element += '<div class="p2-link_action">'
								+ '<a title="Add video link to selected text" href="javascript:void(0);" class="button" onclick="' + addlink + '"><img src="../wp-content/plugins/publish2/img/link.gif" height="15" width="15" style="padding-right:5px;position:relative;top:4px;" />Link</a> &nbsp;&nbsp;'
								+ '<a title="Embed video and saved details in post" href="javascript:void(0);" class="button" onclick="' + embedvimeo + '">Embed video</a>'
 								+ '</div>';
				
			}
			// Otherwise, use the normal link functions
			else {
			
				var addlink = actiondefault + ", 'link');return false;";
				var addlinkmetadata = actiondefault + ", 'linkmetadata');return false;";
				link_element += '<div class="p2-link_action">'
								+ '<a title="Add link to selected text" href="javascript:void(0);" class="button" onclick="' + addlink + '"><img src="../wp-content/plugins/publish2/img/link.gif" height="15" width="15" style="padding-right:5px;position:relative;top:4px;" />Link</a> &nbsp;&nbsp;'
 								+ '<a title="Add the full link, comments, and other saved details to post" href="javascript:void(0);" class="button" onclick="' + addlinkmetadata + '">Add link and details</a>'
								+ '</div>';
			
			}
			
			// End the div for the individual link
			link_element += '</div>';

		});

		// Create page buttons, reset the loading image, and display all of the links in the feed
		publish2_create_pagination(links_low, total_links);
	
	}
	
	// Stop the loading graphic and then display all of the links
	var static_reset = '<img src="../wp-content/plugins/publish2/img/loading_static-red.gif" />';
	jQuery('#p2-source-edit').show();
	jQuery("#p2-link_assist-reset").html(static_reset);
	jQuery("#publish2-link_assist-links").html(link_element);
	
	// Somewhat hackish method for storing the temporary variables hidden at the bottom of the box
	var p2_temp_settings = '<span id="p2-temp_links_low">' + links_low + '</span><span id="p2-temp_total_links">' + total_links + '</span><span id="p2-temp_working_feedlink">' + feedlink + '</span>';
	jQuery('#p2-temp_settings').empty().html(p2_temp_settings);

}
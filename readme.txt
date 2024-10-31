=== Publish2 ===
Tags: link journalism, JSON, widgets, journalism, links, related content, publish2
Contributors: danielbachhuber
Requires at least: 2.7
Tested up to: 3.0.1
Stable Tag: 1.5.1

Easily incorporate your Publish2 links into your WordPress site and blog posts.

== Description ==

The Publish2 Wordpress plugin allows you to easily publish links, tweets, and videos (YouTube and Vimeo are currently supported) saved with Publish2 in your blog posts, sidebar widgets, and dedicated pages.

Publish2 is free set of web tools for journalists and newsrooms to share the best of the web with their readers. Publish2's Social Journalism features make it easy to curate the real-time Web. Visit [www.publish2.com](http://www.publish2.com/ "Publish2") to learn more.

Features:

* With Link Assist in the blog post edit screen, you can access links, tweets, and videos that you saved to Publish2 and easily add them as inline links in the text of your post or displayed as a list. Insert tweets into your posts, or embed videos from YouTube or Vimeo.
* Display links, tweets, and embeddable videos in a sidebar widget, as a list on any page, or by using `<?php publish2(); ?>` anywhere in your WordPress Theme. Customize the format and display of content.

Send feedback, questions, comments, and ideas to wordpress at publish2 dot com.

== Installation ==

Here's how to get the Publish2 WordPress plugin up and running:

1. Download the plugin and unzip the Publish2 folder.  
2. Upload Publish2 plugin folder to the '/wp-content/plugins/' directory on the server where you have WordPress installed.
3. Activate the Publish2 plugin on the 'Plugins' menu in the WordPress administration panel

= Enable Link Assist =

* Go to your Profile Settings ('Your Profile' under the Users menu) and select 'Enable Link Assist while editing posts' near the bottom of the screen.
* Copy and paste the URL to your links on the Publish2 website (e.g. 'http://www.publish2.com/journalists/josh-korr/links/').
* Choose the elements to display -- comments, publication name, publication date, journalist name, and tags -- when you insert a full link, tweet, or video.
* If you want to embed videos, you can also set the default video width.

= Display Links with Sidebar Widget =

* To display Publish2 links in a sidebar widget, go to the 'Widgets' page under the "Appearance' menu.
* Drag the Publish2 widget to your Sidebar on the right.
* Copy and paste the URL to your links on the Publish2 website (e.g. 'http://www.publish2.com/journalists/josh-korr/links/') in the 'Source URL for links' field.
* Choose the number of links to display and the link elements to display.  You can display comments, publication name, publication date, journalist name, and tags. You can also set the width of embedded videos.
* Optional configuration includes custom title and footer.
* Advanced configuration options allow to use tokens form precise arrangement and display of Publish2 content.

= Display Links on Pages =

* To display Publish2 links on a page, go to 'Edit' or 'Add New' under the "Pages' menu.
* In the 'Publish2 Settings' section below the main text box, change 'Display links from Publish2' to On.
* Copy and paste the URL to your links on the Publish2 website (e.g. 'http://www.publish2.com/journalists/josh-korr/links/') in the 'Source URL for links' field.
* Choose the number of links to display and the link elements to display.  You can display comments, publication name, publication date, journalist name, and tags. You can also set the width of embedded videos.

= Display Links with PHP Function =

* To display Publish2 links using the `<?php publish2_links(); ?>` function in a template (e.g. sidebar.php), go to 'Publish2' under the "Settings' menu.
* Copy and paste the URL to your links on the Publish2 website (e.g. 'http://www.publish2.com/journalists/josh-korr/links/') in the 'Source URL for links' field.
* Choose the number of links to display and the link elements to display.  You can display comments, publication name, publication date, journalist name, and tags. You can also set the width of embedded videos.
* Place the `<?php publish2_links(); ?>` function in the WordPress template where you want the links to display.

Note: The Publish2 plugin requires at least PHP 5.2 with either `file_get_contents` or cURL enabled. The plugin will disable itself automatically if either of these requirements aren't met.

== Screenshots ==

1. Enable Link Assist to access your links, tweets, and videos saved on Publish2 while you're writing a post. Add links, tweets, and videos to a blog post as inline text links or displayed as a list. Filter links by tags, journalist, content type, newsgroup, or newswire to find what you need.
 
2. You can create a recommended list for your readers by adding links, tweets, and videos from Publish2 to your blog.

== Frequently Asked Questions ==

= What is Link Assist, and how do I use it? =

Link Assist allows you to quickly and easily add links you've saved with Publish2 to a blog post as inline links. Link Assist displays links from any Publish2 link feed as a sidebar on the WordPress post edit page. Enable Link Assist on your WordPress user profile ('Your Profile' under the Users menu) and by checking 'Enable Link Assist while editing posts' and adding the URL of your Publish2 links. Once enabled, Link Assist will appear on the post edit page, allowing you to quickly scan through links, tweets, and videos, filter with tags, and add them to your post with one click.

= How do I style my links with CSS? =

If you're using the basic checkbox options to select which link elements to display, the plugin renders HTML with unique CSS classes to define how your links are displayed. Here is sample HTML to use as a guide:

`<h4><span class="publish2_header">What I'm Reading</span></h4>
<h4><a href="http://newssite.com/article" class="publish2_story_headline">Sample Headline</a></h4>
<p><span class="publish2_story_publication_name">newssite.com</span> | <span class="publish2_story_publication_date">July 13, 2009</span></p>
<p><a href="http://www.publish2.com/journalists/daniel-bachhuber" class="publish2_journalist_profile">Daniel Bachhuber</a> says: <span class="publish2_story_description">This is an insightful article, highly recommended.</span></p>
<p>Tags: <a href="http://www.publish2.com/journalists/daniel-bachhuber/tag1" class="publish2_story_tags">Tag1</a>, <a href="http://www. publish2.com/journalists/daniel-bachhuber/tag2" class="publish2_story_tags">Tag2</a>
<p><a href="http://www.publish2.com/journalists/daniel-bachhuber/links">More of Daniel's Links</a></p>`

= How do I use tokens to style my links? =

Select 'Advanced Options' when configuring the display of the Publish2 links on Pages or on Settings page for PHP function (on widget configuration, Advanced Options appear below the basic options). Advanced Options lets you use tokens (i.e. `%headline%`) to change the order of your link elements, as well as wrap each link element in specific HTML tags. You can also add custom CSS classes for granular control over styling. For example:

`<p>%source%</p>
<h3 class="publish2_headline">%headline%</h3>
<p>%date%</p>
<p>%comment%</p>
<p>%tags%</p>`

The following tokens are accepted:

* `%headline%` - The title of the link
* `%source%` - The publication name of the link
* `%date%` - The publication date of the link
* `%journalist%` - The journalist who saved the link on Publish2
* `%comment%` - The comment that the journalist added to the the link
* `%tags%` - All of the tags that were added to the link when saved
* `%video%` - Embeds a YouTube video with customizable width.

= What variables does the `publish2()` function accept? =

If you're using the PHP function in your theme or template to display Publish2 links, it can be customized from the defaults by passing the following parameters:

`<?php publish2($feedlink[str], $link_count[int], $headline[bool], $date[bool], $journalist[bool], $comment[bool], $tags[bool], $link_format[str]); ?>`

= Why does the plugin tell me I've entered a 'Bad URL'? =

If the plugin gives you a 'Bad URL' error, this means that the URL you've entered isn't a valid source of data from the Publish2 website. The plugin accepts URLs from any Publish2 page that has a feed, which includes individual journalist link pages, social link pages, Newsgroup link pages, and Newswire pages. All tag pages also have a feed.

= Why does the plugin need at least PHP 5.2? =

The plugin uses Publish2's JSON feeds to import link data. The JSON module that PHP uses was not included as a default until PHP 5.2. If you're running a version earlier than PHP 5.2, then you need to install the JSON module or upgrade to the latest version of PHP.

= Why won't the plugin work if I don't have `file_get_contents()` or cURL enabled? =

cURL and `file_get_contents()` are two of the options that the Publish2 WordPress plugin uses to pull data from Publish2 link feeds. At least one of these functions is required for the plugin to work. If either the Publish2 Settings page or the sidebar widget give you an error message, you'll probably have to contact your server administrator.

== Changelog ==

= Version 1.5.1 =
* Fixed issue where Link Assist wouldn't load in certain server environments

= Version 1.5 =
* Maintenance improvements for new version of Publish2
* Import single links or link roundups on a timely basis
* Uses WP oEmbed class for media embeds
* Various code refinements. Thanks Andrew Nacin for the review

= Version 1.4.5 =
* Minor bug fix

= Version 1.4.4 =
* Now backwards compatible to 2.6

= Version 1.4.3 =

* Support for quote functionality in widget and Link Assist
* Configure Link Assist using tokens

= Version 1.4.2 =

* Minor bug fixes and speed improvements

= Version 1.4 =

* Link Assist offers native support for Social Journalism, including the ability to embed YouTube videos, add tweets, or add links with all of the saved details, and better navigation to switch between your Links, Links from your Network, or the Newswire
* Displaying links on your website, either through the widget, PHP function, or on a page, better formats tweets and offers the option to embed YouTube links as videos

= Version 1.1 =

* Added Link Assist, a quick and easy way to bring the links you save to you when you're writing a post
* Minor bug fixes

= Version 1.0 =
* Initial release with widget, `publish2()` function, and the ability to append links at the bottom of a page
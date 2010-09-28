=== Snow Report ===
Tags: weather, skiing, snow, mountain, ski mountain, ski report, snow report, weather, snow country, snow depth, ski
Requires at least: 2.8
Tested up to: 3.0.1
Stable tag: trunk
Contributors: katzwebdesign
Donate link:https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=zackkatz%40gmail%2ecom&item_name=Snow%20Report%20for%20WordPress&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8

Add ski mountain snow reports to your content or your sidebar.

== Description ==

<h3>Catch some powder with a snow report!</h3>

This plugin uses the <a href="http://www.onthesnow.com" rel="nofollow">onthesnow.com</a> API to gather its mountain snow data.

<h3>Get ski reports for mountains in the following areas:</h3>

<strong>In the USA</strong><br />
Alaska, Arizona, California, Colorado, Connecticut, Idaho, Illinois, Indiana, Iowa, Maine, Maryland, Massachusetts, Michigan, Minnesota, Missouri, Montana, Nevada, New Hampshire, New Jersey, New Mexico, New York, North Carolina, Ohio, Oregon, Pennsylvania, South Dakota, Tennessee, Utah, Vermont, Virginia, Washington, West Virginia, Wisconsin, Wyoming

<strong>Canada</strong><br />
Alberta, British Columbia, Ontario, Quebec

<strong>Europe</strong><br />
Andorra, Austria, France, Germany, Italy, Switzerland

<strong>Southern Hemisphere</strong><br />
Argentina, Australia, Chile, New Zealand

<h3>Specify a mountain if you have one in mind.</h3>
Are you wanting to flaunt how much snow Telluride, CO has this winter? Or is Mammoth Mountain, CA the only one for you? You can easily choose to display snow reports from the one mountain you love.

Check out the Screenshots section for pictures.

<h3>Using the Snow Report Plugin</h3>
The plugin can be configured in two ways: 

1. Configure the plugin in the admin's Snow Report settings page, then add `[snow_report]` to your content where you want the snow_report to appear.
2. Go crazy with the `[snow_report]` shortcode.

<h4>Using the `[snow_report]` Shortcode</h4>
If you're a maniac for shortcodes, and you want all control all the time, this is a good way to use it.

<strong>The shortcode supports the following settings:</strong>

* <code>location="Colorado"</code> - It must exactly match one of the "Report Location" drop-down options on the left
* <code>caption="Ski Reports for Colorado"</code> - Add a caption to your table (it's like a title) 
* <code>measurement='inches'</code> - Use either <code>inches</code> or <code>cm</code>
* <code>align='center'</code> - Align the table cells. Choose from <code>left</code>, <code>center</code>, or <code>right</code>
* <code>noresults="Snow reports aren&rsquo;t available right now."</code> - Message shown when no results are available
* <code>showclosed="yes"</code> - Show seasonally closed mountains (<code>yes</code> or <code>no</code>)
* <code>class="css_table_class"</code> - Change the CSS class of the generated snow_report table

<h4>Learn more on the <a href="http://www.seodenver.com/snow-report/">official plugin page</a></h4>

== Installation ==

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
1. Activate the plugin
1. Go to the plugin settings page (under Settings > Snow Report)
1. Configure the settings on the page. (Instructions for some setting configurations are on the box to the right)
1. Click Save Changes.
1. When editing posts, use the `[snow_report]` "shortcode" as described on this plugin's Description tab

== Screenshots ==

1. Plugin configuration page
1. Embedded in content in the twentyten theme
1. Embedded in content in the twentyten theme

== Frequently Asked Questions == 

= I want to modify the snow report output. How do I do that? =

<pre>
function replace_snow_data($content) {
	// This will make all fields with "N/A" blank instead
	$content = str_replace('N/A', '', $content);
	return $content;
}
add_filter('snow_report_output', 'replace_snow_data');
</pre>

= What is the plugin license? =

* This plugin is released under a GPL license.

= This plugin slows down my site. =
It is recommended to use a caching plugin (such as WP Super Cache) with this plugin; that way the snow_report isn't re-loaded every page load.

== Changelog ==

= 1.0 =
* Initial launch

== Upgrade Notice ==

= 1.0 = 
* Blastoff!
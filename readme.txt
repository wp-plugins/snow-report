=== Snow Report ===
Tags: weather, skiing, snow, mountain, ski mountain, ski report, snow report, weather, snow country, snow depth, ski,snow-report, ski-report, liftopia, liftopia.com, lift tickets, ski tickets, on the snow, onthesnow
Requires at least: 2.8
Tested up to: 3.5
Stable tag: trunk
Contributors: katzwebdesign, katzwebservices
Donate link:https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=zackkatz%40gmail%2ecom&item_name=Snow%20Report&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8

Get mountain snow reports (including base pack, recent snowfall, and more) in your content or your sidebar.

== Description ==

<h3>Catch some powder on your website with a snow report!</h3>

Display the snow conditions on your favorite mountain or in your area using the Snow Report plugin. Choose to display a resort's open status, snow base depth, 48 hour snowfall, surface conditions, and link to purchase lift tickets.

This plugin uses the <a href="http://www.onthesnow.com" rel="nofollow">onthesnow.com</a> API to gather its mountain snow data, and <a href="http://bit.ly/lift-tickets" rel="nofollow">liftopia.com</a> for lift ticket links.

<h3>Get ski conditions reports for mountains in the following areas:</h3>

<strong>In the USA</strong><br />
Alaska, Arizona, California, Colorado, Connecticut, Idaho, Illinois, Indiana, Iowa, Maine, Maryland, Massachusetts, Michigan, Minnesota, Missouri, Montana, Nevada, New Hampshire, New Jersey, New Mexico, New York, North Carolina, Ohio, Oregon, Pennsylvania, South Dakota, Tennessee, Utah, Vermont, Virginia, Washington, West Virginia, Wisconsin, Wyoming

<strong>Canada</strong><br />
Alberta, British Columbia, Ontario, Quebec

<strong>Europe</strong><br />
Andorra, Austria, France, Germany, Italy, Switzerland

<strong>Southern Hemisphere</strong><br />
Argentina, Australia, Chile, New Zealand

<h3>Specify some mountains if you have one in mind.</h3>
Are you wanting to flaunt how much snow Telluride, CO has this winter? Or is Mammoth Mountain, CA the only one for you? You can easily choose to display snow reports from the mountains you love.

Check out the Screenshots section for pictures.

<h3>Using the Snow Report Plugin</h3>
The plugin can be configured in two ways:

1. Configure the plugin in the admin's Snow Report settings page, then add `[snow_report]` to your content where you want the snow_report to appear.
2. Go crazy with the `[snow_report]` shortcode. See the "FAQ" tab above.

<h4>Learn more on the <a href="http://www.seodenver.com/snow-report/">official Snow Report plugin page</a></h4>

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

= Using the `[snow_report]` Shortcode =
If you're a maniac for shortcodes, and you want all control all the time, this is a good way to use it.

<strong>The shortcode supports the following settings:</strong>

* `location="Colorado"` - It must exactly match one of the "Report Location" drop-down options on the left
* `caption="Ski Reports for Colorado"` - Add a caption to your table (it's like a title)
* `measurement='inches'` - Use either `inches` or `cm`
* `align='center'` - Align the table cells. Choose from `left`, `center`, or `right`
* `noresults="Snow reports aren&rsquo;t available right now."` - Message shown when no results are available
* `show_tickets="yes"` - Show a link to purchase lift tickets for each displayed resort (`yes` or `no`)
* `cache_results="yes"` - Whether to cache results or not. Setting this to "no" is not encouraged. (`yes` or `no`)
* `ticket_text="%%resort%% lift tickets"` - Format the text for the buy tickets link. `%%resort%%` will be replaced by the resort name.
* `showclosed="yes"` - Show seasonally closed mountains (`yes` or `no`)
* `class="css_table_class"` - Change the CSS class of the generated report table
* `showtablelink="yes"` - Show a link under the snow report table that lets people know this plugin was used
* `id="1"` - If you are showing more than one snow report for the same location on a page, use `&lt;id&gt;` and give them unique ID numbers. Otherwise, they will be identical (if caching is turned on).
* `columns="status,base,48hr,surface,tickets"` - The columns that will be shown in the table. <br /><strong>How to define shown columns:</strong>
	* Show only if a resort is open: `columns="status"`.
	* Show base pack and lift tickets: `columns="base,tickets"`.
	* Show 48 hour snowfall and surface conditions: `columns="48hr,surface"`.

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
Version 1.1 added caching functionality. To enable, go to the options page and check the box next to "Cache Results." If that doesn't work as expected, it is recommended to use a caching plugin (such as WP Super Cache) so that the report isn't re-generated each page load.


= How do I have two snow reports of the same location on the same page? =
If you're going to have two reports for the same area on the same page, you should add the `id` attribute to your shortcode:
`[snow_report location="Colorado" id="1" caption="Test 1"] is now different from [snow_report location="Colorado" id="2" caption="Test 2"]`

This way, the caching functionality will display different results.

__Note:__ This is only an issue when caching is turned on.

== Changelog ==

= 1.3 =
* Fixed issue with Array showing up under "Snow Conditions"
* Reworked the mountain selection process
    - Added support for multiple mountains at once
    - You can now use checkboxes to select the mountains you want
    - You can now use newly added mountain IDs instead of names in the shortcode
* Improved caching to deal with plugin settings better
* Improved shortcode handling of data
* Added Austrian mountain ski ticket support
* Added more internationalization text support

= 1.2.2 =
* Added support for additional mountains

= 1.2.1 =
* Fixed shortcode `measurement` setting - now `measurement="cm"` works properly.

= 1.2 =
* Improved caching of tables; this also fixed issues with columns not showing up when checked.
* Added `cache_hours` option for shortcode and plugin. Defaults to 12 hours.
* Improved the look of the below-table link
* Added an HTML comment above the table to let users know when an individual mountain feed isn't working

= 1.1.2 =
* Fixed `Missing argument 1 for snow_report::av()` bug (thanks to <a href="http://www.summitsnowreport.com" rel="nofollow">Nick</a> for sharing)

= 1.1.1 =
* Updated the lift tickets feature so that if a resort has no link, to display nothing (instead of an empty link)

= 1.1 =
* Added results caching - speeds up the display of the table by storing the results in a cache (with shortcode support)
* Choose which columns to display - show or hide columns on a per-report basis (with shortcode support)
* Added option to show link to purchase lift tickets
* Added CSS classes to table headings (`<th>`) and table cells (`<td>`)
* Added plugin deactivation and uninstall procedures to keep your databases squeaky clean

= 1.0 =
* Initial launch

== Upgrade Notice ==

= 1.3 =
* Fixed issue with Array showing up under "Snow Conditions"
* Reworked the mountain selection process
    - Added support for multiple mountains at once
    - You can now use checkboxes to select the mountains you want
    - You can now use newly added mountain IDs instead of names in the shortcode
* Improved caching to deal with plugin settings better
* Improved shortcode handling of data
* Added Austrian mountain ski ticket support
* Added more internationalization text support

= 1.2.2 =
* Added support for additional mountains

= 1.2.1 =
* Fixed shortcode `measurement` setting - now `measurement="cm"` works properly.

= 1.2 =
* Improved caching of tables; this also fixed issues with columns not showing up when checked.
* Improved the look of the below-table link
* Added `cache_hours` option for shortcode and plugin. Defaults to 12 hours.
* Added an HTML comment above the table to let users know when an individual mountain feed isn't working

= 1.1.2 =
* Fixed `Missing argument 1 for snow_report::av()` bug (thanks to <a href="http://www.summitsnowreport.com" rel="nofollow">Nick</a> for sharing)

= 1.1.1 =
* Updated the lift tickets feature so that if a resort has no link, to display nothing (instead of an empty link)

= 1.1 =
* Fixes issue with plugin activation conflicting with the <a href="http://wordpress.org/extend/plugins/wunderground/">WP Wunderground</a> plugin
* May fix shortcode display <a href="http://wordpress.org/support/topic/plugin-snow-report-doesnt-work-on-my-site">issues reported on WordPress.org</a>

= 1.0 =
* Blastoff!
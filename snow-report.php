<?php
/*
Plugin Name: Snow Report
Plugin URI: http://www.seodenver.com/snow-report/
Description: Get mountain snow reports (including base pack, recent snowfall, and more) in your content or your sidebar.
Version: 1.3
Author: Katz Web Services, Inc.
Author URI: http://www.seodenver.com/
*/

function init_snow_report() {
    if(method_exists('snow_report', 'snow_report')) {
        $snow_report = new snow_report;
    }
}
add_action('plugins_loaded', 'init_snow_report');

function snow_report_deactivate() {
    delete_transient('snow_report_tables');
}
register_deactivation_hook( __FILE__, 'snow_report_deactivate');

function snow_report_uninstall() {
    delete_option('snow_report');
    delete_transient('snow_report_tables');
}
register_uninstall_hook( __FILE__, 'snow_report_uninstall');

class snow_report {
    var $version = '1.3';
    var $url = 'http://www.onthesnow.com';
    var $location = 'Colorado';
    var $mountain = '';
    var $mountains = array();
    var $measurement = 'inches';
    var $type = 'table';
    var $align = 'center';
    var $caption = 'Snow Report';
    var $show_closed = 'yes';
    var $showlink = 'no';
    var $showtablelink = 'yes';
    var $noresults = 'Snow reports aren&rsquo;t available right now.';
    var $columns = array('status'=>'yes','base'=>'yes','48hr'=>'yes','surface'=>'yes','tickets'=>'yes');
    var $cache_results = 'yes';
    var $cache_hours = 12;
    var $ticket_text = 'Buy lift tickets';

    function snow_report() {

        add_action('admin_menu', array(&$this, 'admin'));
        add_filter('plugin_action_links', array(&$this, 'settings_link'), 10, 2 );
        add_action('admin_init', array(&$this, 'settings_init') );

        // If you want to use shortcodes in your widgets, you should!
        add_filter('widget_text', 'do_shortcode');
        add_filter('wp_footer', 'do_shortcode');

        $this->options = get_option('snow_report');
        add_shortcode('snow_report', array(&$this, 'build_snow_report'));

        // Set each setting...
        if(is_array($this->options)) {
            foreach($this->options as $key=> $value) {
                $this->{$key} = $value;
            }
            $this->cache_hours = $this->check_cache_time($this->cache_hours);
        }

        if(!is_admin()) {
            add_action('wp_footer', array(&$this,'showlink'));
        }
    }


    function settings_init() {
        register_setting( 'snow_report_options', 'snow_report', array(&$this, 'sanitize_settings') );
    }

    function sanitize_settings($input) {

        $input['mountains_json'] = '';
        if(!empty($input['mountains'])) {
            $input['mountains_json'] = json_encode($input['mountains']);
        }
        ksort($input);

        return $input;
    }

    function settings_link( $links, $file ) {
        static $this_plugin;
        if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);
        if ( $file == $this_plugin ) {
            $settings_link = '<a href="' . admin_url( 'options-general.php?page=snow_report' ) . '">' . __('Settings', 'snow_report') . '</a>';
            array_unshift( $links, $settings_link ); // before other links
        }
        return $links;
    }

    function admin() {
        add_options_page(__('Snow Report', 'snow-report'), __('Snow Report', 'snow-report'), 'administrator', 'snow_report', array(&$this, 'admin_page'));
    }

    function admin_page() {
        ?>
        <div class="wrap">
        <h2>Snow Report: Ski Reports for WordPress</h2>
        <div class="postbox-container" style="width:60%; margin-right:2%">
            <div class="metabox-holder">
                <div class="meta-box-sortables">
                    <form action="options.php" method="post">
                   <?php
                        wp_nonce_field('update-options');
                        settings_fields('snow_report_options');


                        $rows[] = array(
                                'id' => 'snow_report_location',
                                'label' => __('Report Location', 'snow_report'),
                                'desc' => __('Where do you want your ski/snow report?', 'snow_report'),
                                'content' => $this->buildLocation()
                        );

                        $rows[] = array(
                                'id' => 'snow_report_mountain',
                                'label' => __('Show only Specific Mountains', 'snow_report'),
                                'content' => $this->buildMountains(), //"<input type='text' name='snow_report[mountain]' id='snow_report_mountain' value='".esc_attr($this->mountain)."' size='40' style='width:95%!important;' />",
                                'desc' => __('Note: Save "Report Location" first. Check the boxes next to the mountains you would like to show. <strong>To show all mountains, check no boxes.</strong>', 'snow_report'),
                        );

                        $rows[] = array(
                                'id' => 'snow_report_measurement',
                                'label' => __('Snow Measurement', 'snow_report'),
                                'desc' => __('Are you metric or U.S. baby?', 'snow_report'),
                                'content' => $this->buildMeasurement()
                        );

                        $rows[] = array(
                                'id' => 'snow_report_caption',
                                'label' => __('Snow Report Caption', 'snow_report'),
                                'content' => "<input type='text' name='snow_report[caption]' id='snow_report_caption' value='".esc_attr($this->caption)."' size='40' style='width:95%!important;' />",
                                'desc' => __('This will display above the report. Think of it like a report title.', 'snow_report'),
                            );

                        $rows[] = array(
                                'id' => 'snow_report_noresults',
                                'label' => __('No Results Text', 'snow_report'),
                                'content' => "<input type='text' name='snow_report[noresults]' id='snow_report_noresults' value='".esc_attr($this->noresults)."' size='40' style='width:95%!important;' />",
                                'desc' => __('If all mountains are closed, or for some reason the plugin\'s feed isn\'t working, display this text.', 'snow_report'),
                        );

                        $rows[] = array(
                            'id' => 'snow_report_ticket_text',
                            'label' => __('"Buy Tickets" link text', 'snow_report'),
                            'content' => "<input type='text' name='snow_report[ticket_text]' id='snow_report_ticket_text' value='".esc_attr($this->ticket_text)."' size='40' style='width:95%!important;' />",
                            'desc' => __('If you choose to show the "buy tickets" link, this will alter the link text.', 'snow_report'),
                        );

                        $checked = array();
                        $checked['status'] = ($this->columns['status'] != 'no') ? ' checked="checked"' : '';
                        $checked['base'] = ($this->columns['base']  != 'no') ? ' checked="checked"' : '';
                        $checked['48hr'] = ($this->columns['48hr'] != 'no') ? ' checked="checked"' : '';
                        $checked['surface'] = ($this->columns['surface'] != 'no') ? ' checked="checked"' : '';
                        $checked['tickets'] = ($this->columns['tickets'] != 'no') ? ' checked="checked"' : '';

                        $rows[] = array(
                                'id' => array('snow_report_show_status', 'snow_report_show_base','snow_report_show_48hr', 'snow_report_show_tickets'),
                                'label' => __('Snow Report Columns', 'snow_report'),
                                'desc' => __('Choose the columns visible in the snow report.', 'snow_report'),
                                'content' => "
                                <ul>
                                <li><label for='snow_report_show_status'><input type='hidden' name='snow_report[columns][status]' value='no' /><input type='checkbox' name='snow_report[columns][status]' value='yes' id='snow_report_show_status' {$checked['status']} /> Open Status</label></li>

                                <li><label for='snow_report_show_base'><input type='hidden' name='snow_report[columns][base]' value='no' /><input type='checkbox' name='snow_report[columns][base]' value='yes' id='snow_report_show_base' {$checked['base']} /> Base Depth</label></li>

                                <li><label for='snow_report_show_48hr'><input type='hidden' name='snow_report[columns][48hr]' value='no' /><input type='checkbox' name='snow_report[columns][48hr]' value='yes' id='snow_report_show_48hr' {$checked['48hr']} /> 48 Hour Snowfall</label></li>

                                <li><label for='snow_report_show_surface'><input type='hidden' name='snow_report[columns][surface]' value='no' /><input type='checkbox' name='snow_report[columns][surface]' value='yes' id='snow_report_show_surface' {$checked['surface']} /> Surface Conditions</label></li>

                                <li><label for='snow_report_show_tickets'><input type='hidden' name='snow_report[columns][tickets]' value='no' /><input type='checkbox' name='snow_report[columns][tickets]' value='yes' id='snow_report_show_tickets' {$checked['tickets']} /> Display 'Buy Lift Tickets' Option<br /><small style='padding-left:1.8em'>Displays a link to purchase tickets from <a href='http://katz.si/liftopia' target='external'>liftopia.com</a>, the leading discount ski ticket website</small></label></li>
                                </ul>"
                        );

                        $checked = (empty($this->cache_results) || $this->cache_results == 'yes') ? ' checked="checked"' : '';

                        $rows[] = array(
                                'id' => 'snow_report_cache_results',
                                'hidelabel' => true,
                                'label' => __('Cache Results', 'snow_report'),
                                'desc' => __('Do you want to show results for mountains that are no longer open for the season?', 'snow_report'),
                                'content' => "<p><label for='snow_report_cache_results'><input type='hidden' name='snow_report[cache_results]' value='no' /><input type='checkbox' name='snow_report[cache_results]' value='yes' id='snow_report_cache_results' $checked /> Cache results for </label><input type='text' value='".$this->cache_hours."' name='snow_report[cache_hours]' size='3' /> <label for='snow_report_cache_results'> hours (loads much faster)</label></p>"
                        );

                        $checked = (empty($this->show_closed) || $this->show_closed == 'yes') ? ' checked="checked"' : '';

                        $rows[] = array(
                                'id' => 'snow_report_show_closed',
                                'label' => __('Show Closed Mountains', 'snow_report'),
                                'desc' => __('Do you want to show results for mountains that are no longer open for the season?', 'snow_report'),
                                'content' => "<p><label for='snow_report_show_closed'><input type='hidden' name='snow_report[show_closed]' value='no' /><input type='checkbox' name='snow_report[show_closed]' value='yes' id='snow_report_show_closed' $checked /> Show Seasonally Closed Mountains</label></p>"
                        );


                        $checked = (empty($this->showlink) || $this->showlink == 'yes') ? ' checked="checked"' : '';
                        $checked2 = (empty($this->showtablelink) || $this->showtablelink == 'yes') ? ' checked="checked"' : '';

                        $rows[] = array(
                                'id' => array('snow_report_showlink','snow_report_showtablelink'),
                                'label' => __('Give Thanks', 'snow_report'),
                                'desc' => __('Checking the box tells the world you use this free plugin by adding a link to your footer. If you don\'t like it, you can turn it off, so please enable.', 'snow_report'),
                                'content' => "
                                <ul>
                                <li><label for='snow_report_showtablelink'><input type='hidden' name='snow_report[showtablelink]' value='no' /><input type='checkbox' name='snow_report[showtablelink]' value='yes' id='snow_report_showtablelink' $checked2 /> Add this under the report: <span style='text-align:center; font-size:.75em; color:#bbb; margin-top:0; padding-top:0em;'>Generated by <a href='http://www.seodenver.com/snow-report/' style='color:#aaa;'>Snow Report</a></p></label></li>
                                <li><label for='snow_report_showlink'><input type='hidden' name='snow_report[showlink]' value='no' /><input type='checkbox' name='snow_report[showlink]' value='yes' id='snow_report_showlink' $checked /> Show us some love &#9829;<br /><small>Include a link in your footer for the plugin</small></label></li>
                                </ul>"
                        );

                        $this->postbox('snow_reportsettings',__('Snow Report Settings', 'snow_report'), $this->form_table($rows), false);

                    ?>


                        <input type="hidden" name="page_options" value="<?php foreach($rows as $row) { if(is_array($row)) { foreach($rows as $row) { $output .= $row['id'].','; } } else { } $output .= $row['id'].','; } echo substr($output, 0, -1);?>" />
                        <input type="hidden" name="action" value="update" />
                        <p class="submit">
                        <input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes', 'snow_report') ?>" />
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php flush(); ?>
        <div class="postbox-container" style="width:34%;">
            <div class="metabox-holder">
                <div class="meta-box-sortables">
                <?php $this->postbox('snow_reporthelp',__('Configuring This Plugin', 'snow_report'), $this->configuration(), true);  ?>
                </div>
            </div>
        </div>

    </div>
    <?php
    }

    private function attr($resort_location = '') {
        global $post, $pagenow;// prevents calling before <HTML>
        if(($post && !is_admin()) || (is_admin() && defined('DOING_AJAX'))) {
            $url = 'http://www.katzwebservices.com/development/attribution.php?site='.htmlentities(substr(get_bloginfo('url'), 7)).'&from=snow_report&credit='.intval($this->options['showlink'] === 'yes').'&location='.urlencode($resort_location).'&version='.$this->version;

            // > 2.8
            if(function_exists('fetch_feed')) {
                include_once(ABSPATH . WPINC . '/feed.php');
                if ( !$rss = fetch_feed($url) ) { return $default; }
                if(!is_wp_error($rss)) {
                    // This list is only missing 'style', 'id', and 'class' so that those don't get stripped.
                    // See http://simplepie.org/wiki/reference/simplepie/strip_attributes for more information.
                    $strip = array('bgsound','expr','onclick','onerror','onfinish','onmouseover','onmouseout','onfocus','onblur','lowsrc','dynsrc');
                    $rss->strip_attributes($strip);
                    $rss->set_cache_duration(60*60*24*60); // Fetch every 60 days
                    $rss_items = $rss->get_items(0, 1);
                    foreach ( $rss_items as $item ) {
                        return str_replace(array("\n", "\r"), ' ', $item->get_description());
                    }
                }
                return $default;
            } else { // < 2.8
                require_once(ABSPATH . WPINC . '/rss.php');
                if(!function_exists('fetch_rss')) { return $default; }
                if ( !$rss = fetch_rss($url) ) {
                    return $default;
                }
                $items = 1;
                if ((!is_wp_error($rss) && !empty($rss)) && is_array( $rss->items ) && !empty( $rss->items ) ) {
                    $rss->items = array_slice($rss->items, 0, $items);
                    foreach ($rss->items as $item ) {
                        if ( isset( $item['description'] ) && is_string( $item['description'] ) )
                            $summary = $item['description'];
                        $desc = str_replace(array("\n", "\r"), ' ', $summary);
                        $summary = '';
                        return $desc;
                    }
                }
                return $default;
            }
        }
    }


    function showLink() {
        $linktext = $this->attr($this->location);
        if($this->showlink == 'yes') {
            $link = '<p class="snow_report" style="text-align:center;">'.$linktext.'</p>';
            echo $link;
        }
    }

    function tra($url = NULL) {
        #http://www.tkqlhce.com/click-3923826-10472356?url=http%3A%2F%2Ftravel.travelocity.com%2Fel.do%3Fea_sid%3D%25zs%26ea_aid%3D%25za%26ea_pid%3D%25zp%26Service%3DCJUS%26dest%3Dhttp%3A%2F%2Ftravel.travelocity.com%2Fhotel%2FHotelDateLessList.do%3FmarketId%3D14%26Service%3DCJUS // Travelocity
        return 'http://www.tkqlhce.com/click-3923826-10472356?url='.urlencode($url).'&amp;cjsku=1';
    }

    function lif($url = NULL) {
        return $this->av($url);
        return 'http://www.jdoqocy.com/click-3923826-10718243?url=http://www.liftopia.com/ski-resort-info/resort/'.urlencode($url);
    }

    function av($url = NULL) {
        if(!empty($url)) {
            return 'http://www.avantlink.com/click.php?tt=cl&amp;mi=10065&amp;pw=35803&amp;ctc=plugin&amp;url='.urlencode($url);
        } else {
            return '<img src="http://www.avantlink.com/tpv/10065/0/28131/35803/plugin/cl/image.png" width="0" height="0" style="border:none!important; margin: 0px!important; position:absolute!important; overflow:hidden!important; display:block!important; left:-9999px;" alt="" />';
        }
    }

    function buildMeasurement() {
        $c = ' selected="selected"';
        $output = '<select id="snow_report_measurement" name="snow_report[measurement]">';
        $output .= '    <option value="inches"'; if($this->measurement == 'inches') { $output .= $c; } $output .= '>U.S. (inches)</option>';
        $output .= '    <option value="cm"'; if($this->measurement == 'cm') { $output .= $c; } $output .= '>Metric (cm)</option>';
        $output .= '</select>';
        $output .= '<label for="snow_report_measurement" style="padding-left:10px;">';
        $output .= __('Inches or Centimeters', 'snow-report');
        $output .= '</label>';
        return $output;
    }

    function configuration() {
        $date2 = date('m-d-y');
        $date = date('m/d/Y');
        $weekday = date('l');
        $mountains = array_keys((array)$this->mountains);
        $shortcode = '[snow_report location="'.$this->location.'" mountain="'.implode(',', $mountains).'" caption="Snow Reports for Ski Mountains in '.$this->caption.'" align="'.$this->align.'" show_tickets="'.$this->columns['tickets'].'" ticket_text="%%resort%% lift tickets" cache_results="'.$this->cache_results.'" columns="open,base,tickets"]';
        $report = do_shortcode($shortcode);
        $html = <<<EOD
        <h4>Adding the Snow Report to your Content</h4>
        <p class="howto updated" style="padding:1em;">If you configure the settings to the left, all you will need to do is add <code>[snow_report]</code> to your post or page content or text widget to add the snow report table.</p>

        <h4>Using the <code>[snow_report]</code> Shortcode</h4>

        <p>If you're a maniac for shortcodes, and you want all control all the time, this is a good way to use it.</p>
        <p><strong>The shortcode supports the following settings:</strong></p>
        <ul>
            <li><code>location="Colorado"</code> - It must exactly match one of the "Report Location" drop-down options on the left</li>
            <li><code>mountain="123,1355"</code> - Enter a comma-separated list of specific mountain IDs that you would like to show.</li>
            <li><code>caption="Ski Reports for Colorado"</code> - Add a caption to your table (it's like a title)
            </li><li><code>measurement='inches'</code> - Use either <code>inches</code> or <code>cm</code>
            </li><li><code>align='center'</code> - Align the table cells. Choose from <code>left</code>, <code>center</code>, or <code>right</code>
            </li><li><code>noresults="Snow reports aren&rsquo;t available right now."</code> - Message shown when no results are available
            </li><li><code>show_tickets="yes"</code> - Show a link to purchase lift tickets for each displayed resort (<code>yes</code> or <code>no</code>)
            </li><li><code>cache_results="yes"</code> - Whether to cache results or not. Setting this to "no" is not encouraged. (<code>yes</code> or <code>no</code>)
            </li><li><code>ticket_text="%%resort%% lift tickets"</code> - Format the text for the buy tickets link. <code>%%resort%%</code> will be replaced by the resort name.
            </li><li><code>showclosed="yes"</code> - Show seasonally closed mountains (<code>yes</code> or <code>no</code>)
            </li><li><code>class="css_table_class"</code> - Change the CSS class of the generated report table
            </li><li><code>showtablelink="yes"</code> - Show a link under the snow report table that lets people know this plugin was used
            </li><li><code>id="1"</code> - If you are showing more than one snow report for the same location on a page, use <code>&lt;id&gt;</code> and give them unique ID numbers. Otherwise, they will be identical (if caching is turned on).
            </li><li><code>columns="status,base,48hr,surface,tickets"</code> - The columns that will be shown in the table. <h5>How to define shown columns:</h5>
                <ul style="margin-left:2em; display:list-item!important; list-style:disc outside;">
                     <li>Show only if a resort is open: <code>columns="status"</code>.
                </li><li>Show base pack and lift tickets: <code>columns="base,tickets"</code>.
                </li><li>Show 48 hour snowfall and surface conditions: <code>columns="48hr,surface"</code>.
                </li>
                </ul>
            </li>
        </ul>
        <hr style="padding-top:1em; outline:none; border:none; border-bottom:1px solid #ccc;"/>
        <h4>Shortcode example</h4>
        <p><strong>Use the following code:</strong></p>
        <p><code>[snow_report]</code></p>
        <p style="margin-top:.25em;">or if you are using multiple reports:</p>
        <p><code>$shortcode</code></p>
        <p><strong>Either will output this table:</strong></p>
        $report
EOD;
        return $html;
    }

    // THANKS JOOST!
    function form_table($rows) {
        $content = '<table class="form-table" width="100%">';
        foreach ($rows as $row) {
            $content .= '<tr><th valign="top" scope="row" style="width:40%">';
            if (isset($row['id']) && $row['id'] != '')
                $content .= '<label for="'.$row['id'].'" style="font-weight:bold;">'.$row['label'].':</label>';
            else
                $content .= $row['label'];
            if (isset($row['desc']) && $row['desc'] != '')
                $content .= '<br/><small>'.$row['desc'].'</small>';
            $content .= '</th><td valign="top" style="width:60%">';
            $content .= $row['content'];
            $content .= '</td></tr>';
        }
        $content .= '</table>';
        return $content;
    }

    function postbox($id, $title, $content, $padding=false) {
        ?>
            <div id="<?php echo $id; ?>" class="postbox">
                <div class="handlediv" title="Click to toggle"><br /></div>
                <h3 class="hndle"><span><?php echo $title; ?></span></h3>
                <div class="inside" <?php if($padding) { echo 'style="padding:10px; padding-top:0;"'; } ?>>
                    <?php echo $content; ?>
                </div>
            </div>
        <?php
    }

    function r($content, $kill = false, $title = '') {
        echo !empty($title) ? '<h3>'.$title.'</h3>' : '';
        echo '<pre>'.print_r($content,true).'</pre>';
        if($kill) { die(); }
    }

    // Added in version 1.1 - this caches the results for each snow report
    // and uses it if available instead of re-fetching and processing the feed
    // from onthesnow.com. Saves much time, and should reduce the need for caching plugins
    function get_transient($transientKey, $cache_results, $location = '', $mountain = '', $triedonce = false) {

       if($cache_results !== 'no' && !isset($_REQUEST['cache'])) {
            $transient = get_transient($transientKey);

            // If there is a cache, use it. Storing in an array allows all reports to be in one cache key
            // Could not be the best way. Email info@katzwebservices.com if you know how to improve!
            if(!empty($transient)) {
                return apply_filters('snow_report_output', $transient);
            }

        }
        return false;
    }

    function check_cache_time($cache_hours) {
        if(!is_numeric($cache_hours) && preg_match('/^([0-9]+$)/ism', $cache_hours)) { $cache_hours = $cache_hours * 1; }
        if(!is_numeric($cache_hours)) { $cache_hours = 12; }
        $cache_hours = round($cache_hours, 3);
        return $cache_hours;
    }

    function set_transient($cache_results, $cache_hours = 12, $table, $transientKey) {
        if($cache_results != 'no' && !is_admin()) {
            $cache_hours = $this->check_cache_time($cache_hours);
            $cache_time = 60*60*$cache_hours;
            // Set a cached version of the table so it'll be faster next time. Expires every x hours
            set_transient($transientKey,maybe_serialize($table), $cache_time);
        }

        return apply_filters('snow_report_output', $table);
    }

    function makeTicket($name, $location, $ticket_text, $triedonce = false) {
        if(empty($this->ticketList)) { $this->ticketList = $this->buildTickets(); }
        $list = $this->ticketList;
        #return $location;
        if(isset($list[$location][$name]) && !empty($list[$location][$name])) {
            if(function_exists('str_ireplace')) {
                $ticket_text = str_ireplace('%%resort%%', $name, $ticket_text);
            } else {
                $ticket_text = str_replace('%%resort%%', $name, $ticket_text);
                $ticket_text = str_replace('%%Resort%%', $name, $ticket_text);
            }
            return '<a href="'.$list[$location][$name].'" rel="nofollow" title="Purchase lift tickets for '.$name.'">'.$ticket_text.'</a>';
        } else {

            // Sometimes single mountains have their location added after it like:
            // "DACHSTEIN GLETSCHER, AUSTRIA". We strip ", Austria" so that the mountain shows up!
            if($triedonce) {
                 return '';
            } else {
                return $this->makeTicket(str_replace(', '.$location, '', $name), $location, $ticket_text, true);
            }
        }
    }

    function get_mountains_in_location($xml) {

        if(!$xml) { return; }

        foreach($xml->xpath('//item') as $rsrow) {
            $closed = preg_match('/Closed/ism', $rsrow->description);
            if(!preg_match('/Permanently(?:\s+)?closed/ism', $rsrow->description) && ($closed && $this->show_closed == 'yes' || !$closed)) {
                $row = @simplexml_load_string($rsrow->asXML());
                $row = $this->simpleXMLToArray($row);

                $mountains[$row['resort_id']] = $row;
            }
        }
        return $mountains;
    }

    function get_rss($location, $mountain = '', $triedonce = false) {

        // Get the RSS feed. If specific mountain has been set, if it doesn't work,
        // we revert to only the location without the mountain
        $url = 'http://www.onthesnow.com/'.sanitize_title($location, $location);
        if(!empty($mountain) && !$triedonce) { $url .= '/'.sanitize_title($mountain); }
        $url .= '/snow.rss';

        return @simplexml_load_file($url);
    }

    function build_snow_report($atts = array(), $content=null) {
        $settings = shortcode_atts( array(
          'location'    =>  $this->options['location'],
          'mountain'    =>  $this->options['mountain'],
          'measurement' =>  $this->options['measurement'],
          'align'       =>  $this->options['align'],
          'caption'     =>  $this->options['caption'],
          'show_closed' =>  $this->options['show_closed'],
          'class'       =>  'snow_report',
          'cache_results' => $this->options['cache_results'],
          'cache_hours' => $this->options['cache_hours'],
          'ticket_text' => $this->ticket_text,
          'id'          =>  NULL,
          'columns'     => $this->options['columns'],
          'showtablelink' => $this->options['showtablelink'],
          'triedonce'   =>  false // For recursive
          ), $atts);
        extract( $settings );

        // Now saves all the settings in the transient key
        $transientSettings = $settings;
        unset($transientSettings['triedonce']);
        $transientSettings['options'] = maybe_serialize($this->options);
        $transientKey = 'sr_'.sha1(@implode('_', $transientSettings));

        // Get cache if exists.
       if($transient = $this->get_transient($transientKey, $cache_results, $location, $mountain, $id, $triedonce)) { return $transient; }

        $xml = $this->get_rss($location, $mountain, $triedonce);

            if(!$xml) {
                if(!$triedonce) {
                    $atts['triedonce'] = 1;
                    return $this->build_snow_report($atts, $content);
                } else {
                    return '<!-- Snow Report Error : Error reading XML file at '.$url.' and '.$url2.' -->'.$content;
                }
            } else if(!empty($xml)) {

                $mountains = array();
                // The `mountain` attribute was defined in the shortcode.
                if(empty($mountain)) {
                    // We're using the array of mountains defined in the settings.
                    $mountains = (array)$this->options['mountains'];
                } else {
                    $mountains = explode(',', $mountain);
                }

                $mountains = array_map('trim', $mountains);
                $mountains = array_map('rtrim', $mountains);
                $mountains = array_map('mb_strtolower', $mountains);
                $mountains = array_map('htmlentities', $mountains);

                // The following code is modified from PHP.net; http://www.php.net/manual/en/simplexmlelement.xpath.php
                $namespaces = $xml->getNamespaces(true);
                foreach ($namespaces as $prefix => $ns) { $xml->registerXPathNamespace($prefix, $ns); }

                $showTable = '';

                // So that the cols can be passed in the shortcode, we split the string into an array with , separator
                // If not using shortcode, $columns will always be array
                if(is_string($columns)) {
                    $columnskeys = explode(',', trim($columns));
                    $columns = array();
                    $columns['status'] = $columns['base'] = $columns['48hr'] = $columns['surface'] = $columns['tickets'] = 'no';
                    foreach($columnskeys as $key) { $columns[strtolower(trim($key))] = 'yes'; }
                }

                // Calculate the width of each column
                $cols = 1; foreach($columns as $c) { if($c != 'no') { $cols++; }} $width = round(100/$cols, 2);

                $tablebody = '';
                $XML_Items = $xml->xpath('//item');
                foreach($XML_Items as $rsrow) {

                    $closed = preg_match('/Closed/ism', $rsrow->description);
                    if(!preg_match('/Permanently(?:\s+)?closed/ism', $rsrow->description) && ($closed && $this->show_closed == 'yes' || !$closed)) {

                        $row = @simplexml_load_string($rsrow->asXML());
                        $row = $this->simpleXMLToArray($row);

                        // If the settings or shortcode specify mountains, and this is not them, skip 'em
                        if(
                            !empty($mountains) &&

                            // Check the resort IDs.
                            (!(in_array($row['resort_id'], $mountains) || array_key_exists($row['resort_id'], $mountains))) &&

                            // Check "Mountain Name", lowercase and trimmed.
                            !in_array(trim(rtrim(mb_strtolower(htmlentities($row['title'])))), $mountains) &&

                            // Check "Mountain Name, Location", lowercase and trimmed.
                            !in_array(trim(rtrim(mb_strtolower(htmlentities(str_replace(', '.$location, '', $row['title']))))), $mountains)
                        ) {
                            // The mountain doesn't fit the criteria, keep going.
                            continue;
                        }

                        // Empty surface conditions
                        if(is_array($row['surface_condition']) && empty($row['surface_condition']) || $row['surface_condition'] === 'N/A') {
                            $row['surface_condition'] = apply_filters('snow_report_empty_surface_condition', 'N/A');
                        }

                        if(strtolower($measurement) == 'cm') { $symbol = 'cm'; $mName = 'cm.'; } else { $symbol = '&quot;'; $mName = 'in.'; }

                        // Convert inches to cm
                        foreach($row as $key => $item) {
                            if(is_numeric($item) && $key != 'resort_id' && $this->measurement == 'cm') {
                                    $row[$key] = ceil($item * 2.54); // 2.54 cm/in
                            }
                        }

                        extract($row);

                        $tablebody .=
                        "\n\t\t\t\t\t\t".'<tr>'.
                        "\n\t\t\t\t\t\t\t".'<th align="'.$align.'" scope="row" class="snow_report_td_resort">'.wptexturize(esc_attr($title)).'</th>';
                        if($columns['status'] != 'no') {  $tablebody .= "\n\t\t\t\t\t\t\t".'<td align="'.$align.'" class="snow_report_td_status">'.$open_staus.'</td>'; }
                        if($columns['base'] != 'no') {  $tablebody .= "\n\t\t\t\t\t\t\t".'<td align="'.$align.'" class="snow_report_td_base">'.$base_depth.$symbol.'</td>'; }
                        if($columns['48hr'] != 'no') {  $tablebody .= "\n\t\t\t\t\t\t\t".'<td align="'.$align.'" class="snow_report_td_48hr">'.$snowfall_48hr.$symbol.'</td>'; }
                        if($columns['surface'] != 'no') {  $tablebody .= "\n\t\t\t\t\t\t\t".'<td align="'.$align.'" class="snow_report_td_surface">'.$surface_condition.'</td>'; }
                        if($columns['tickets'] != 'no') {  $tablebody .= "\n\t\t\t\t\t\t\t".'<td align="'.$align.'" class="snow_report_td_tickets">'.$this->makeTicket(esc_attr($title), $location, $ticket_text).'</td>'; }
                        $tablebody .= "\n\t\t\t\t\t\t".'</tr>';
                        $showTable++;
                    }
                }

                if($showTable > 0) {
                if(!empty($caption)) {
                    $caption = "\n\t\t\t\t\t<caption>{$caption}</caption>";
                }
                $table = '
                    <table cellpadding="0" cellspacing="0" border="0" width="100%" class="'.esc_attr($class).'">'.$caption.'
                        <thead>
                            <tr>
                                <th scope="col" align="'.$this->align.'" width="'.$width.'%" class="snow_report_th_resort">Resort</th>';

                if($columns['status'] != 'no') { $table .= '<th scope="col" align="'.$this->align.'" width="'.$width.'%" class="snow_report_th_status">Open Status</th>'; }
                if($columns['base'] != 'no') { $table .= '<th scope="col" align="'.$this->align.'" width="'.$width.'%" class="snow_report_th_base">Base Depth ('.$mName.')</th>'; }
                if($columns['48hr'] != 'no') { $table .= '<th scope="col" align="'.$this->align.'" width="'.$width.'%" class="snow_report_th_48hr">48hr Snowfall</th>'; }
                if($columns['surface'] != 'no') { $table .= '<th scope="col" align="'.$this->align.'" width="'.$width.'%" class="snow_report_th_surface">Surface Condition</th>'; }
                if($columns['tickets'] != 'no') { $table .= '<th scope="col" align="'.$this->align.'" width="'.$width.'%" class="snow_report_th_tickets">Lift Tickets</td>';    }

                $table .= '
                            </tr>
                        </thead>
                        <tbody>
                            '.$tablebody.'
                        </tbody>
                    </table>';
                    if($triedonce && !empty($mountain)) { $table = '<!-- No feed found for http://www.onthesnow.com/'.sanitize_title($location).'/'.sanitize_title($mountain). '/snow.rss , so showing state results instead.  -->'.$table; }
                    if($columns['tickets'] != 'no') { $table .= $this->av(); }
                    if($showtablelink == 'yes') { $table .= '<p style="text-align:center; font-size:.75em; color:#bbb; margin-top:0; padding-top:0em;">Generated by <a href="http://www.seodenver.com/snow-report/" style="color:#bbb;">Snow Report</a></p>'; }
                } else {
                    $table = '<div class="warning notice '.esc_attr($class).'">'.wpautop($this->noresults).'</div>';
                }

                return $this->set_transient($cache_results, $cache_hours, $table, $transientKey);
            } else {
                return '<!-- Snow Report Error : Snow report feed was empty from '.$this->url.$this->location.' -->'.$content;
            }
    }

    function simpleXMLToArray($xml,
                    $flattenValues=true,
                    $flattenAttributes = true,
                    $flattenChildren=true,
                    $valueKey='@value',
                    $attributesKey='@attributes',
                    $childrenKey='@children'){

        $return = array();
        if(!($xml instanceof SimpleXMLElement)){return $return;}
        $name = $xml->getName();
        $_value = trim((string)$xml);
        if(strlen($_value)==0){$_value = null;};

        if($_value!==null){
            if(!$flattenValues){$return[$valueKey] = $_value;}
            else{$return = $_value;}
        }

        $children = array();
        $first = true;
        foreach($xml->children() as $elementName => $child){
            $value = $this->simpleXMLToArray($child, $flattenValues, $flattenAttributes, $flattenChildren, $valueKey, $attributesKey, $childrenKey);
            if(isset($children[$elementName])){
                if($first){
                    $temp = $children[$elementName];
                    unset($children[$elementName]);
                    $children[$elementName][] = $temp;
                    $first=false;
                }
                $children[$elementName][] = $value;
            }
            else{
                $children[$elementName] = $value;
            }
        }
        if(count($children)>0){
            if(!$flattenChildren){$return[$childrenKey] = $children;}
            else{$return = array_merge($return,$children);}
        }

        $attributes = array();
        foreach($xml->attributes() as $name=>$value){
            $attributes[$name] = trim($value);
        }

        if(count($attributes)>0){
            if(!$flattenAttributes){$return[$attributesKey] = $attributes;}
            else{if(is_array($return)) { $return = array_merge($return, $attributes); } else { return $attributes;}}
        }

        return $return;
    }

    function buildTickets() {
        $tickets = array();

        $tickets = array(
            'Alaska' => array(
                'Alyeska' => $this->av('http://www.liftopia.com/ski-resort-info/resort/907002/AK/Alyeska-Resort.htm'),
                'Eaglecrest' => $this->av('http://www.liftopia.com/ski-resort-info/resort/907003/AK/Eaglecrest.htm')
            ),
            'Arizona' => array(
                'Arizona Snowbowl' => $this->av('http://www.liftopia.com/ski-resort-info/resort/602001/AZ/Arizona-Snowbowl.htm'),
                'Sunrise Park' => $this->av('http://www.liftopia.com/ski-resort-info/resort/602003/AZ/Sunrise-Park.htm'),
            ),
            'California' => array(
                'Alpine Meadows' =>'',
                'Badger Pass' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/209001/CA/Badger-Pass.htm'),
                'Bear Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/909002/CA/Bear-Mountain-Resort.htm'),
                'Bear Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/209002/CA/Bear-Valley-Mountain-Resort.htm'),
                'Boreal' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916002/CA/Boreal-Mountain-Resort.htm'),
                'Dodge Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/209003/CA/Dodge-Ridge.htm'),
                'Donner Ski Ranch' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916003/CA/Donner-Ski-Ranch.htm'),
                'Heavenly' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916004/NV/Heavenly.htm'),
                'Homewood' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916009/CA/Homewood-Mountain-Resort.htm'),
                'June Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/619001/CA/June-Mountain.htm'),
                'Kirkwood' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/209004/CA/Kirkwood.htm'),
                'Mammoth Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/619002/CA/Mammoth-Mountain.htm'),
                'Mount Shasta' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916006/CA/Mt-Shasta.htm'),
                'Mountain High' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/619006/CA/Mountain-High-Resort.htm'),
                'Mt. Baldy' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/909004/CA/Mt.-Baldy.htm'),
                'Northstar at Tahoe' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916007/CA/Northstar-at-Tahoe.htm'),
                'Sierra at Tahoe' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916008/CA/Sierra-At-Tahoe.htm'),
                'China Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/209005/CA/China-Peak.htm'),
                'Snow Summit' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/909001/CA/Snow-Summit-Mountain-Resort.htm'),
                'Snow Valley' =>'',
                'Soda Springs' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916010/CA/Soda-Springs.htm'),
                'Squaw Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916011/CA/Squaw-Valley-USA.htm'),
                'Sugar Bowl' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/916012/CA/Sugar-Bowl.htm'),
                'Tahoe Donner' => $this->av('http://www.liftopia.com/ski-resort-info/resort/916013/CA/Tahoe-Donner.htm')
            ),
            'Colorado' => array(
                'Arapahoe Basin' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303001/CO/Arapahoe-Basin.htm'),
                'Aspen / Snowmass' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303003/CO/Aspen-Mountain.htm'),
                'Beaver Creek' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303005/CO/Beaver-Creek.htm'),
                'Breckenridge' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303007/CO/Breckenridge.htm'),
                'Buttermilk' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303027/CO/Buttermilk.htm'),
                'Copper Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303009/CO/Copper-Mountain.htm'), // $this->tra('http://activities.travelocity.com/nexres/activities/detail.cgi?src=10010405&supplier_id=30013')
                'Crested Butte' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303010/CO/Crested-Butte-Mountain-Resort.htm'),
                'Durango' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303017/CO/Durango-Mountain-Resort.htm'),
                'Echo Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/720001/CO/Echo-Mountain.htm'),
                'Echo' => $this->av('http://www.liftopia.com/ski-resort-info/resort/720001/CO/Echo-Mountain.htm'),
                'Eldora' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303011/CO/Eldora.htm'),
                'Keystone' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303014/CO/Keystone.htm'),
                'Loveland' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303015/CO/Loveland-Ski-Area.htm'),
                'Monarch' => $this->av('http://www.liftopia.com/ski-resort-info/resort/719002/CO/Monarch-Mountain.htm'),
                'Powderhorn' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303016/CO/Powderhorn.htm'),
                'Silverton' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303387/CO/Silverton-Mountain.htm'),
                'Ski Cooper' => $this->av('http://www.liftopia.com/ski-resort-info/resort/719003/CO/Ski-Cooper.htm'),
                'Snowmass' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303020/CO/Snowmass.htm'),
                'SolVista' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303018/CO/Sol-Vista-Basin-at-Granby-Ranch.htm'),
                'Steamboat' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303021/CO/Steamboat.htm'),
                'Sunlight Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303019/CO/Sunlight-Mountain-Resort.htm'),
                'Telluride' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303022/CO/Telluride.htm'),
                'Vail' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303023/CO/Vail.htm'),
                'Winter Park' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303024/CO/Winter-Park-Resort.htm'),
                'Wolf Creek' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303025/CO/Wolf-Creek.htm'),
                'Wolf Creek Ski Area' => $this->av('http://www.liftopia.com/ski-resort-info/resort/303025/CO/Wolf-Creek.htm')
            ),
            'Connecticut' => array(
                'Mohawk Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/203001/CT/Mohawk-Mountain.htm'),
                'Mount Southington' => $this->av('http://www.liftopia.com/ski-resort-info/resort/203002/CT/Mt-Southington.htm'),
                'Ski Sundown' => $this->av('http://www.liftopia.com/ski-resort-info/resort/203005/CT/Ski-Sundown.htm'),
                'Woodbury' => $this->av('http://www.liftopia.com/ski-resort-info/resort/203006/CT/Woodbury.htm')
            ),
            'Idaho' => array(
                'Bogus Basin' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208001/ID/Bogus-Basin.htm'),
                'Brundage Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208002/ID/Brundage.htm'),
                'Kelly Canyon' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208015/ID/Kelly-Canyon.htm'),
                'Lookout Pass' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208004/ID/Lookout-Pass.htm'),
                'Magic Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208005/ID/Magic-Mountain.htm'),
                'Pebble Creek' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208006/ID/Pebble-Creek.htm'),
                'Pomerelle Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208007/ID/Pomerelle.htm'),
                'Schweitzer' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208008/ID/Schweitzer-Mountain.htm'),
                'Silver Mountain' => null,
                'Soldier Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/208010/ID/Soldier-Mountain.htm'),
                'Sun Valley' => $this->av('http://www.liftopia.com/ski-resort-info/resort/208012/ID/Sun-Valley-Resort.htm')
            ),
            'Illinois' => array(
                'Chestnut Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/815001/IL/Chestnut-Mountain.htm'),
                'Four Lakes' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/708001/IL/Four-Lakes-Village.htm'),
                'Ski Snowstar' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/309001/IL/Ski-Snowstar.htm'),
                'Villa Olivia' => $this->av('http://www.liftopia.com/ski-resort-info/resort/708002/IL/Villa-Olivia.htm')
            ),
            'Indiana' => array(
                'Paoli Peaks' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/812002/IN/Paoli-Peaks.htm'),
                'Perfect North Slopes' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/812001/IN/Perfect-North-Slopes.htm')
            ),
            'Iowa' => array(
                'Mt. Crescent' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/712003/IA/Mt-Crescent.htm'),
                'Seven Oaks' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/319003/IA/Seven-Oaks---IA.htm'),
                'Sundown Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/319002/IA/Sundown-Mountain.htm')
            ),
            'Indiana' => array(
                'Paoli Peaks' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/812002/IN/Paoli-Peaks.htm'),
                'Perfect North Slopes' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/812001/IN/Perfect-North-Slopes.htm')
            ),
            'Maine' => array(
                'Big Squaw' =>'',
                'Camden' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207002/ME/Camden-Snow-Bowl.htm'),
                'Lost Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207003/ME/Lost-Valley.htm'),
                'Mt. Abram' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207004/ME/Mt.-Abram-Family-Resort.htm'),
                'Mt. Jefferson' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207012/ME/Mt-Jefferson.htm'),
                'New Hermon Mtn.' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207005/ME/New-Hermon-Mtn.htm'),
                'Saddleback' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207006/ME/Saddleback.htm'),
                'Shawnee Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207007/ME/Shawnee-Peak.htm'),
                'Sugarloaf' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207008/ME/Sugarloaf.htm'),
                'Sunday River' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/207009/ME/Sunday-River.htm')
            ),
            'Maryland' => array(
                'Wisp' => $this->av('http://www.liftopia.com/ski-resort-info/resort/301001/MD/Wisp.htm')
            ),
            'Massachusetts' => array(
                'Berkshire East' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413001/MA/Berkshire-East.htm'),
                'Blandford' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413010/MA/Blandford.htm'),
                'Blue Hills' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/617001/MA/Blue-Hills.htm'),
                'Bousquet' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413002/MA/Bousquet.htm'),
                'Bradford' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/508002/MA/Bradford.htm'),
                'Catamount' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413005/MA/Catamount.htm'),
                'Jiminy Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413006/MA/Jiminy-Peak.htm'),
                'Nashoba Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/508004/MA/Nashoba-Valley.htm'),
                'Otis Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413008/MA/Otis-Ridge.htm'),
                'Ski Butternut' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/413004/MA/Ski-Butternut.htm'),
                'Ski Ward' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/508006/MA/Ski-Ward.htm'),
                'Wachusett Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/508007/MA/Wachusett-.htm')
            ),
            'Michigan' => array(
                'Alpine Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/216001/OH/Alpine-Valley.htm'),
                'Apple Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/517007/MI/Apple-Mountain.htm'),
                'Big Powderhorn Mtn.' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906001/MI/Big-Powderhorn.htm'),
                'Bittersweet' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616001/MI/Bittersweet.htm'),
                'Blackjack' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906002/MI/Blackjack.htm'),
                'Boyne Highlands' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616002/MI/Boyne-Highlands.htm'),
                'Boyne Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616003/MI/Boyne-Mountain.htm'),
                'Caberfae Peaks' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616004/MI/Caberfae-Peaks.htm'),
                'Cannonsburg' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616005/MI/Cannonsburg.htm'),
                'Crystal Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616006/MI/Crystal-Mountain.htm'),
                'Indianhead' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906004/MI/Indianhead.htm'),
                'Marquette' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906005/MI/Marquette.htm'),
                'Mont Ripley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/909010/MI/Mont-Ripley.htm'),
                'Mount Brighton' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/313002/MI/Mount-Brighton.htm'),
                'Mount Holly' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/313003/MI/Mt-Holly.htm'),
                'Mt. Holiday' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616019/MI/Mt-Holiday.htm'),
                'Norway Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906011/MI/Norway-Mountain.htm'),
                'Nubs Nob' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616008/MI/Nub'),
                'Pine Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906007/MI/Pine-Mountain.htm'),
                'Shanty Creek' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616023/MI/Shanty-Creek-Resorts.htm'),
                'Ski Brule' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/906009/MI/Ski-Brule.htm'),
                'Snow Snake Mtn.' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/517004/MI/Snow-Snake.htm'),
                'Swiss Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616013/MI/Swiss-Valley.htm'),
                'The Homestead' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616014/VA/The-Homestead.htm'),
                'Timber Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616015/MI/Timber-Ridge.htm'),
                'Treetops Resort' => $this->av('http://www.liftopia.com/ski-resort-info/resort/517005/MI/Treetops.htm')
            ),
            'Minnesota' => array(
                'Afton Alps' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612002/MN/Afton-Alps.htm'),
                'Andes Tower Hills' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612003/MN/Andes-Tower-Hills.htm'),
                'Buck Hill' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612004/MN/Buck-Hill.htm'),
                'Buena Vista' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/218001/MN/Buena-Vista.htm'),
                'Coffee Mill' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612005/MN/Coffee-Mill.htm'),
                'Giants Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/218002/MN/Giants-Ridge.htm'),
                'Hyland Ski' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612006/MN/Hyland-Ski-&-Snowboard.htm'),
                'Lutsen' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/218003/MN/Lutsen-Mountains.htm'),
                'Mount Kato' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/507001/MN/Mount-Kato.htm'),
                'Powder Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612001/MN/Powder-Ridge.htm'),
                'Spirit Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/218004/MN/Spirit-Mountain.htm'),
                'Welch Village' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/612008/MN/Welch-Village.htm'),
                'Wild Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/612009/MN/Wild-Mountain.htm')
            ),
            'Missouri' => array(
                'Hidden Valley' => '',
                'Snow Creek' => $this->av('http://www.liftopia.com/ski-resort-info/resort/816001/MO/Snow-Creek.htm')
            ),
            'Montana' => array(
                'Big Sky' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406002/MT/Big-Sky-Resort.htm'),
                'Blacktail Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406015/MT/Blacktail-Mountain.htm'),
                'Bridger Bowl' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406003/MT/Bridger-Bowl.htm'),
                'Discovery Ski' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406004/MT/Discovery.htm'),
                'Great Divide' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406005/MT/Great-Divide.htm'),
                'Lost Trail' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406014/MT/Lost-Trail.htm'),
                'Maverick Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406007/MT/Maverick.htm'),
                'Montana Snowbowl' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406008/MT/Montana-Snowbowl.htm'),
                'Moonlight Basin' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406106/MT/Moonlight-Basin.htm'),
                'Red Lodge Mtn.' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406009/MT/Red-Lodge.htm'),
                'Showdown' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/406011/MT/Showdown.htm'),
                'Teton Pass' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/999988321/MT/Teton_Pass_Ski_Resort.htm'),
                'Whitefish' => $this->av('http://www.liftopia.com/ski-resort-info/resort/406001/MT/Whitefish.htm')
            ),
            'Nevada' => array(
                'Diamond Peak' => $this->av('http://www.liftopia.com/ski-resort-info/resort/702001/NV/Diamond-Peak-Ski-Resort.htm'),
                'Las Vegas Ski' => $this->av('http://www.liftopia.com/ski-resort-info/resort/702002/NV/Las-Vegas-Ski.htm'),
                'Mt. Rose' => $this->av('http://www.liftopia.com/ski-resort-info/resort/702003/NV/Mt.-Rose.htm')
            ),
            'New Hampshire' => array(
                'Attitash' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603002/NH/Attitash.htm'),
                'Balsams Wilderness' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603003/NH/Balsams-Wilderness.htm'),
                'Black Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603004/NH/Black-Mountain-Ski-Area.htm'),
                'Bretton Woods' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603005/NH/Bretton-Woods.htm'),
                'Cannon Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603006/NH/Cannon-Mountain.htm'),
                'Cranmore Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603007/NH/Cranmore-Mountain.htm'),
                'Crotched Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603028/NH/Crotched-Mountain-Resort.htm'),
                'Dartmouth Skiway' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603008/NH/Dartmouth-Skiway.htm'),
                'Granite Gorge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603032/NH/Granite-Gorge.htm'),
                'Gunstock' => $this->av('http://www.liftopia.com/ski-resort-info/resort/603009/NH/Gunstock-Mountain-Resort.htm'),
                'King Pine' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603011/NH/King-Pine.htm'),
                'Loon Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603014/NH/Loon-Mountain.htm'),
                'Mount Sunapee' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603017/NH/Mount-Sunapee.htm'),
                'Pats Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603018/NH/Pats-Peak.htm'),
                'Ragged Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603019/NH/Ragged-Mountain-Resort.htm'),
                'Tenney Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603013/NH/Tenney-Mountain.htm'),
                'Waterville Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/603001/NH/Waterville-Valley.htm'),
                'Wildcat' => $this->av('http://www.liftopia.com/ski-resort-info/resort/603021/NH/Wildcat-Mountain.htm')
            ),
            'New Jersey' => array(
                'Campgaw Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/201004/NJ/Campgaw-Mtn.htm'),
                'Mountain Creek' => $this->av('http://www.liftopia.com/ski-resort-info/resort/201003/NJ/Mountain-Creek-Resort.htm'),
                'Hidden Valley' => $this->av('http://www.liftopia.com/ski-resort-info/resort/201002/NJ/Hidden-Valley.htm')
            ),
            'New Mexico' => array(
                'Angel Fire' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505001/NM/Angel-Fire-Resort.htm'),
                'Enchanted Forest' =>'',
                'Pajarito Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505002/NM/Pajarito.htm'),
                'Red River' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505003/NM/Red-River-Ski-and-Ride-Area.htm'),
                'Sandia Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505004/NM/Sandia-Peak.htm'),
                'Sipapu Ski' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505006/NM/Sipapu-Ski-and-Summer-Resort.htm'),
                'Ski Apache' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505007/NM/Ski-Apache.htm'),
                'Ski Santa Fe' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505005/NM/Ski-Santa-Fe.htm'),
                'Taos' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/505009/NM/Taos-Ski-Valley.htm')
            ),
            'New York' => array(
                'Belleayre' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/914001/NY/Belleayre.htm'),
                'Brantling Ski' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/315001/NY/Brantling-Ski-Slopes.htm'),
                'Bristol Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/716001/NY/Bristol-Mountain.htm'),
                'Buffalo Ski Club' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/6/NY/Buffalo-Ski-Club.htm'),
                'Cockaigne' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/716002/NY/Cockaigne.htm'),
                'Dry Hill' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/315011/NY/Dry-Hill.htm'),
                'Gore Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518005/NY/Gore-Mountain.htm'),
                'Greek Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/607001/NY/Greek-Peak.htm'),
                'Holiday Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/914003/NY/Holiday-Mountain.htm'),
                'Holiday Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/716003/NY/Holiday-Valley.htm'),
                'Holimont Ski Area' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/716010/NY/HoliMont.htm'),
                'Hunter Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518006/NY/Hunter-Mountain.htm'),
                'Kissing Bridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/716004/NY/Kissing-Bridge.htm'),
                'Labrador Mt.' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/607002/NY/Labrador-Mountain.htm'),
                'Maple Ski Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518016/NY/Maple-Ski-Ridge.htm'),
                'McCauley Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/315004/NY/McCauley.htm'),
                'Mt. Peter' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/914004/NY/Mt-Peter.htm'),
                "Peek'n Peak" =>$this->av('http://www.liftopia.com/ski-resort-info/resort/716005/NY/Peek'),
                'Plattekill Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/607003/NY/Plattekill-Mountain.htm'),
                'Royal Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518017/NY/Royal-Mountain.htm'),
                'Snow Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/315009/NY/Snow-Ridge.htm'),
                'Song Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/315006/NY/Song-Mountain.htm'),
                'Swain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/607005/NY/Swain.htm'),
                'Thunder Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/914002/NY/Thunder-Ridge.htm'),
                'Titus Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518013/NY/Titus-Mountain.htm'),
                'Toggenburg Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/315007/NY/Toggenburg.htm'),
                'Tuxedo Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/914006/NY/Tuxedo-Ridge.htm'),
                'West Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518010/Ne/West-Mountain.htm'),
                'Whiteface Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518012/NY/Whiteface---Lake-Placid.htm'),
                'Willard Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518011/NY/Willard-Mountain.htm'),
                'Windham Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/518009/NY/Windham-Mountain.htm'),
                'Woods Valley' => $this->av('http://www.liftopia.com/ski-resort-info/resort/315008/NY/Woods-Valley.htm')
            ),
            'North Carolina' => array(
                'Appalachian Mtn' => $this->av('http://www.liftopia.com/ski-resort-info/resort/704001/NC/Appalachian-Ski-Mountain.htm'),
                'Cataloochee' => $this->av('http://www.liftopia.com/ski-resort-info/resort/704002/NC/Cataloochee.htm'),
                'Sapphire Valley' => $this->av('http://www.liftopia.com/ski-resort-info/resort/704003/NC/Sapphire-Valley.htm'),
                'Ski Beech' => $this->av('http://www.liftopia.com/ski-resort-info/resort/704010/NC/Beech-Mtn.htm'),
                'Sugar Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/704006/NC/Sugar-Mountain.htm'),
                'Wolf Ridge' => $this->av('http://www.liftopia.com/ski-resort-info/resort/704007/NC/Wolf-Ridge.htm')
            ),
            'Ohio' => array(
                'Alpine Valley' => $this->av('http://www.liftopia.com/ski-resort-info/resort/216001/OH/Alpine-Valley.htm'),
                'Boston Mills' => $this->av('http://www.liftopia.com/ski-resort-info/resort/216002/OH/Boston-Mills/Brandywine.htm'),
                'Brandywine' => $this->av('http://www.liftopia.com/ski-resort-info/resort/216002/OH/Boston-Mills/Brandywine.htm'),
                'Mad River' => $this->av('http://www.liftopia.com/ski-resort-info/resort/513001/OH/Mad-River.htm'),
                'Snow Trails' => $this->av('http://www.liftopia.com/ski-resort-info/resort/419002/OH/Snow-Trails.htm'),
            ),
            'Oregon' => array(
                'Anthony Lakes' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503001/OR/Ski-Anthony-Lakes.htm'),
                'Cooper Spur' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503002/OR/Cooper-Spur.htm'),
                'Hoodoo' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503003/OR/Hoodoo.htm'),
                'Mt. Ashland' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503008/OR/Mt-Ashland.htm'),
                'Mt. Bachelor' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503004/OR/Mt-Bachelor.htm'),
                'Mt. Hood Meadows' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503006/OR/Mt-Hood-Meadows.htm'),
                'Mt. Hood Ski Bowl' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503007/OR/Mt-Hood-Skibowl.htm'),
                'Spout Springs' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503011/OR/Spout-Springs.htm'),
                'Timberline Lodge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/503009/OR/Timberline-Lodge.htm'),
                'Willamette Pass' => $this->av('http://www.liftopia.com/ski-resort-info/resort/503010/OR/Willamette-Pass.htm')
            ),
            'Pennsylvania' => array(
                'Alpine Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717001/PA/Alpine-Mountain.htm'),
                'Bear Creek' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/215003/PA/Bear-Creek.htm'),
                'Big Bear' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717016/PA/Ski-Big-Bear.htm'),
                'Big Boulder' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717002/PA/Big-Boulder.htm'),
                'Blue Knob' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/814001/PA/Blue-Knob.htm'),
                'Blue Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/215002/PA/Blue-Mountain.htm'),
                'Camelback' => $this->av('http://www.liftopia.com/ski-resort-info/resort/717003/PA/Camelback-Mountain-Resort.htm'),
                'Eagle Rock' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/412004/PA/Eagle-Rock.htm'),
                'Elk Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717005/PA/Elk-Mountain.htm'),
                'Hidden Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/814005/PA/Hidden-Valley.htm'),
                'Jack Frost' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717006/PA/Jack-Frost.htm'),
                'Liberty' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717009/PA/Liberty-Mountain.htm'),
                'Seven Springs' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/814002/PA/Seven-Springs.htm'),
                'Shawnee Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717008/PA/Shawnee-Mountain-Ski-Area.htm'),
                'Ski Denton' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/814003/Pa/Ski-Denton.htm'),
                'Ski Roundtop' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717015/PA/Ski-Roundtop.htm'),
                'Ski Sawmill' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717011/PA/Ski-Sawmill.htm'),
                'Sno Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717017/PA/Sno-Mountain.htm'),
                'Spring Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/215004/PA/Spring-Mountain.htm'),
                'Tanglwood' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/717012/PA/Tanglwood.htm'),
                'Tussey Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/814004/PA/Tussey-Mountain.htm'),
                'Whitetail' => $this->av('http://www.liftopia.com/ski-resort-info/resort/717013/PA/Whitetail.htm')
            ),
            'South Dakota' => array(
                'Deer Mountain' => '',
                'Terry Peak' => $this->av('http://www.liftopia.com/ski-resort-info/resort/999988373/SD/Terry_Peak.htm'),
            ),
            'Tennessee' => array(
                'Ober Gatlinburg' => $this->av('http://www.liftopia.com/ski-resort-info/resort/615001/TN/Ober-Gatlinburg.htm')
            ),
            'Utah' => array(
                'Alta' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801001/UT/Alta.htm'),
                'Beaver Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801002/UT/Beaver-Mountain.htm'),
                'Brian Head' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801013/UT/Brian-Head.htm'),
                'Brighton' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801003/UT/Brighton-Resort.htm'),
                'Deer Valley' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801004/UT/Deer-Valley.htm'),
                'Park City Mt Resort' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801006/UT/Park-City-Mountain-Resort.htm'),
                'Powder Mountain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801008/UT/Powder-Mountain.htm'),
                'Snowbasin' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801009/UT/Snowbasin.htm'),
                'Snowbird' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801010/UT/Snowbird-Ski-and-Summer-Resort.htm'),
                'Solitude' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801011/UT/Solitude-Ski-Resort.htm'),
                'Sundance' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801012/UT/Sundance-Resort-Utah.htm'),
                'The Canyons' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801007/UT/The-Canyons.htm'),
                'Wolf Creek' => $this->av('http://www.liftopia.com/ski-resort-info/resort/801016/UT/Wolf-Creek-Utah.htm')
            ),
            'Vermont' => array(
                'Ascutney' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802001/VT/Ascutney-Mountain.htm'),
                'Bolton Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802002/VT/Bolton-Valley-Resort.htm'),
                'Bromley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802003/VT/Bromley-Mountain.htm'),
                'Burke Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802004/VT/Burke-Mountain.htm'),
                'Jay Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802006/VT/Jay-Peak-Resort.htm'),
                'Killington' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802007/VT/Killington-Resort.htm'),
                'Mad River Glen' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802008/VT/Mad-River-Glen.htm'),
                'Magic Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802009/VT/Magic-Mountain.htm'),
                'Mount Snow' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802012/VT/Mount-Snow-Resort.htm'),
                'Okemo' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802013/VT/Okemo-Mountain.htm'),
                'Pico Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802014/VT/Pico-Mountain.htm'),
                'Smugglers Notch' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802016/VT/Smugglers'),
                'Stowe' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802018/VT/Stowe-Mountain-Resort.htm'),
                'Stratton' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802019/VT/Stratton-Mountain.htm'),
                'Sugarbush' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/802023/VT/Sugarbush.htm'),
                'Suicide Six' => $this->av('http://www.liftopia.com/ski-resort-info/resort/802021/VT/Suicide-Six.htm')
            ),
            'Virginia' => array(
                'Bryce Resort' => $this->av('http://www.liftopia.com/ski-resort-info/resort/703001/VA/Bryce-Resort.htm'),
                'Massanutten' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/703003/VA/Massanutten.htm'),
                'The Homestead' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/616014/VA/The-Homestead.htm'),
                'Wintergreen' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/804001/VA/Wintergreen-Resort.htm'),
            ),
            'Washington' => array(
                '49 Degrees North' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/509001/WA/49-Degrees-North.htm'),
                'Alpental' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/425715/WA/Summit-at-Snoqualmie-Alpental.htm'),
                'Bluewood' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/509003/WA/Bluewood.htm'),
                'Crystal Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/206002/WA/Crystal-Mountain.htm'),
                'Mission Ridge' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/509002/WA/Mission-Ridge.htm'),
                'Mt. Baker' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/206003/WA/Mt-Baker.htm'),
                'Mt. Spokane' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/509005/WA/Mt-Spokane.htm'),
                'Stevens Pass' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/206004/WA/Stevens-Pass.htm'),
                'Summit at Snoqualmie' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/425715/WA/Summit-at-Snoqualmie-Alpental.htm'),
                'White Pass' => $this->av('http://www.liftopia.com/ski-resort-info/resort/509004/WA/White-Pass.htm')
            ),
            'West Virginia' => array(
                'Canaan Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/304005/WV/Canaan-Valley-Resort.htm'),
                'Snowshoe' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/304001/WV/Snowshoe-Mountain.htm'),
                'Timberline' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/304002/WV/Timberline-Four-Seasons.htm'),
                'Winterplace' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/304003/WV/Winterplace.htm')
            ),
            'Wisconsin' => array(
                'Alpine Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/414006/WI/Alpine-Valley.htm'),
                'Cascade Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/608001/WI/Cascade-Mountain.htm'),
                'Christie Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/715001/WI/Christie-Mountain.htm'),
                'Christmas Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/608002/WI/Christmas-Mountain.htm'),
                'Devils Head' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/608003/WI/Devils-Head-Resort.htm'),
                'Grand Geneva' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/414001/WI/Mountain-Top-at-Grand-Geneva.htm'),
                'Granite Peak' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/715002/WI/Granite-Peak.htm'),
                'Hidden Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/414003/WI/Hidden-Valley.htm'),
                'Highlands of Olympia' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/414010/WI/Highlands-of-Olympia.htm'),
                'Mount La Crosse' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/608004/WI/Mt-LaCrosse.htm'),
                'Nordic Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/715011/WI/Nordic-Mountain.htm'),
                'Sunburst' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/414005/WI/Sunburst.htm'),
                'Trollhaugen' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/715004/WI/Trollhaugen.htm'),
                'Tyrol Basin' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/608005/WI/Tyrol-Basin.htm'),
                'Whitecap Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/715005/WI/Whitecap-Mountain.htm'),
                'Wilmot Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/312001/WI/Wilmot-Mountain.htm')
            ),
            'Wyoming' => array(
                'Grand Targhee' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/307002/WY/Grand-Targhee-Resort.htm'),
                'Hogadon' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/307003/WY/Hogadon.htm'),
                'Jackson Hole' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/307008/WY/Jackson-Hole.htm'),
                'Snow King' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/307006/WY/Snow-King-Ski-Resort.htm'),
                'Snowy Range' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/4308/WY/Snowy_Range.htm'),
                'White Pine' => $this->av('http://www.liftopia.com/ski-resort-info/resort/307016/MT/White-Pine.htm')
            ),
            'Alberta' => array(
                'Canada Olympic Park' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/403002/AB/Canada-Olympic-Park.htm'),
                'Castle Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/403020/BC/Castle-Mountain.htm'),
                'Lake Louise' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/403008/AB/Lake-Louise.htm'),
                'Marmot Basin' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/403009/AB/Marmot-Basin.htm'),
                'Mt. Norquay' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/403001/AB/Banff-Mt-Norquay.htm'),
                'Nakiska' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/403011/AB/Nakiska-Ski-Area.htm'),
                'Sunshine Village' => $this->av('http://www.liftopia.com/ski-resort-info/resort/403022/AB/Sunshine-Village.htm')
            ),
            'British Columbia' => array(
                'Apex Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604001/BC/Apex-Mountain.htm'),
                'Big White' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604004/BC/Big-White-Ski-Resort-(BC).htm'),
                'Cypress Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604008/BC/Cypress-Mountain.htm'),
                'Fernie' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604010/BC/Fernie-Alpine-Resort.htm'),
                'Grouse Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604012/BC/Grouse-Mountain.htm'),
                'Hudson Bay Mountain' =>'',
                'Kicking Horse' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/250002/BC/Kicking-Horse-Mountain-Resort.htm'),
                'Kimberley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604015/BC/Kimberley.htm'),
                'Mt. Baldy' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604019/BC/Mt-Baldy.htm'),
                'Mt. Seymour' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604031/BC/Mount-Seymour.htm'),
                'Mt. Washington' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604023/BC/Mt-Washington-Alpine-Resort.htm'),
                'Panorama' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604025/BC/Panorama-Mountain-Village.htm'),
                'Red Mountain Resort' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604029/BC/Red-Mountain-Resort.htm'),
                'Revelstoke' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604021/BC/Revelstoke-Mountain-Resort.htm'),
                'Silver Star' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604033/BC/Silver-Star-Resort-(BC).htm'),
                'Sun Peaks' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604042/BC/Sun-Peaks.htm'),
                'Whistler / Blackcomb' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604038/BC/Whistler-Blackcomb.htm'),
                'Whitewater' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/604040/BC/Whitewater-Ski-Resort.htm')
            ),
            'Ontario' => array(
                'Blue Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/215002/PA/Blue-Mountain.htm'),
                'Calabogie' => $this->av('http://www.liftopia.com/ski-resort-info/resort/613004/ON/Calabogie-Peaks.htm'),
                'Hidden Valley' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/705004/ON/Hidden-Valley.htm'),
                'Horseshoe Resort' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/705005/ON/Horseshoe-Resort.htm'),
                'Loch Lomond' => $this->av('http://www.liftopia.com/ski-resort-info/resort/807003/ON/Loch-Lomond.htm'),
                'St. Louis Moonstone' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/705008/ON/Mt-St-Louis-Moonstone.htm'),
                'Pakenham' => $this->av('http://www.liftopia.com/ski-resort-info/resort/613007/ON/Mt-Pakenham.htm'),
                'Searchmont' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/705014/ON/Searchmont-Resort.htm'),
                'Talisman Mountain Resort' => $this->av('http://www.liftopia.com/ski-resort-info/resort/519011/ON/Talisman-Resort.htm')
            ),
            'Quebec' => array(
                'Camp Fortune' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/416006/QC/Camp-Fortune.htm'),
                'Edelweiss' => $this->av('http://www.liftopia.com/ski-resort-info/resort/819001/QC/Edelweiss-Valley.htm'),
                'Gray Rocks' =>'',
                'Le Massif' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/418889/QC/Le-Massif.htm'),
                'Mont Blanc/Faustin' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/819003/QC/Mont-Blanc.htm'),
                'Mont Cascades' =>'',
                'Mont Orford' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/819007/QC/Mont-Orford.htm'),
                'Mont Saint Sauveur' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/514007/QC/Mont-Saint-Sauveur.htm'),
                'Mont Sutton' => $this->av('http://www.liftopia.com/ski-resort-info/resort/514009/QC/Mont-Sutton.htm'),
                'Mont-Sainte-Anne' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/418007/QC/Mont-Sainte-Anne.htm'),
                "Owl's Head" =>$this->av('http://www.liftopia.com/ski-resort-info/resort/514010/QC/Owl'),
                'Ski Bromont' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/514011/QC/Ski-Bromont.htm'),
                'Ste. Marie' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/819008/QC/Mont-Ste-Marie.htm'),
                'Stoneham Mountain' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/418009/QC/Stoneham-Mountain-Resort.htm'),
                'Tremblant' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/819009/QC/Mont-Tremblant-Resort.htm'),
                'Vorlage' => $this->av('http://www.liftopia.com/ski-resort-info/resort/819014/QC/Vorlage.htm')
            ),
            'Chile' => array(
                'La Parva' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/574004/CH/La-Parva.htm'),
                'El Colorado' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/344003/CH/El-Colorado-Farellones.htm'),
            ),
            'Austria' => array(
                'Fraunalpe' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/8000337/AT/Frauenalpe.htm'),
                'Petzen' =>$this->av('http://www.liftopia.com/ski-resort-info/resort/8000350/AT/Petzen.htm'),
                'Sölden - Ötztal' => $this->av('http://www.liftopia.com/ski-resort-info/resort/422120/AT/Soelden.htm'),
                'Obergurgl - Hochgurgl' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222231/AT/Obergurgl.htm'),
                'Patscherkofel - Innsbruck - Igls' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222011/AT/Innsbruck.htm'),
                'Innsbrucker Nordkettenbahnen' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222011/AT/Innsbruck.htm'),
                'Ischgl - Silvretta Arena' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222012/AT/Ischgl-Silvretta-Arena.htm'),
                'Ischgl' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222012/AT/Ischgl-Silvretta-Arena.htm'),
                'Kitzbuehel' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222013/AT/Kitzbuhel.htm'),
                'St. Anton' => $this->av('http://www.liftopia.com/ski-resort-info/resort/999988443/AT/St-Anton.htm'),
                'Bad Gastein - Stubnerkogel/Schlossalm' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222003/AT/Bad-Gastein.htm'),
                'Zell am See - Schmittenhöhe' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000376/AT/Zell-am-See-Schmittenhohe.htm'),
                'Kappl' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000303/AT/Kappl.htm'),
                'Bad Hofgastein' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000218/AT/Bad-Hofgastein-Schlossalm.htm'),
                'Flachau' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000249/AT/Flachau-Wagrain-Alpendorf.htm'),
                'Wagrain' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000249/AT/Flachau-Wagrain-Alpendorf.htm'),
                'Hintertuxer Gletscher - Zillertal' => $this->av('http://www.liftopia.com/ski-resort-info/resort/878506/AT/Hintertux.htm'),
                'Zillertal Gletscherwelt 3000 - Tux - Finkenberg' => $this->av('http://www.liftopia.com/ski-resort-info/resort/124/AT/Ski-Zillertal-3000.htm'),
                'Glasenberg - Maria Neustift' => $this->av('http://www.liftopia.com/ski-resort-info/resort/262228/AT/Neustift-Stubaital.htm'),
                'Skiwelt' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222043/AT/SkiWelt-Brixental.htm'),
                'Hochzillertal - Hochfügen' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000285/AT/Hochzillertal-Hochfagen.htm'),
                'Stubaier Gletscher' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000401/AT/Stubaital-Stubaier-Gletscher.htm'),
                'Tauplitzalm' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000403/AT/Tauplitzalm.htm'),
                'Dachstein Gletscher' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000231/AT/Dachstein-Gletscher.htm'),
                'Grossglockner / Heiligenblut' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000389/AT/Ski-Heiligenblut.htm'),
                'Hauser Kaibling - Haus im Ennstal' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000277/AT/Schladming-Dachstein-Hauser-Kaibling.htm'),
                'Hochzeiger' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000291/AT/Hochzeiger.htm'),
                'Hochzillertal' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000285/AT/Hochzillertal-Hochfagen.htm'),
                'Kaprun' => $this->av('http://www.liftopia.com/ski-resort-info/resort/478643/AT/Zell-am-See-Kaprun.htm'),
                'Kitzsteinhorn - Kaprun' => $this->av('http://www.liftopia.com/ski-resort-info/resort/478643/AT/Zell-am-See-Kaprun.htm'),
                'Kreischberg - Murau' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000315/AT/Kreischberg.htm'),
                'Kühtai' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000318/AT/Kahtai.htm'),
                'Obertauern' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222017/AT/Obertauern.htm'),
                'Reiteralm - Pichl - Mandling' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000363/AT/Schladming-Dachstein-Reiteralm.htm'),
                'Schladming - Planai - Hochwurzen' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000290/AT/Schladming-Dachstein-Hochwurzen.htm'),
                'Serfaus - Fiss - Ladis' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000381/AT/Serfaus-Fiss-Ladis.htm'),
                'Tauplitzalm' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000403/AT/Tauplitzalm.htm'),
                'Turracher Hohe' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000408/AT/Turracher-Hahe.htm'),
                'Zauchensee - Flachauwinkl' => $this->av('http://www.liftopia.com/ski-resort-info/resort/222001/AT/Altenmarkt-Zauchensee.htm'),
                'Diedamskopf - Au - Schoppernau' => $this->av('http://www.liftopia.com/ski-resort-info/resort/8000233/AT/Diedamskopf.htm'),

            ),
        );
        return $tickets;
    }


    function replaceLocation($state = '') {

        $states = array(
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming'
        );
        if(in_array($state, $states)) {
            foreach($states as $code => $astate) {
                if($state === $astate) {
                    return $code;
                }
            }
        }
    }

    function buildMountains() {

        $xml = $this->get_rss($this->location);

        $mountains = $this->get_mountains_in_location($xml);

        if(!(!empty($mountains) && is_array($mountains))) { return; }
        $output = '<ul class="square">';
        foreach($mountains as $mountain) {
            $output .= sprintf('<li><label><input type="checkbox" name="snow_report[mountains][%s]" value="%2$s" %3$s /> %2$s (ID: <code>%4$s</code>)</label></li>', $mountain['resort_id'], $mountain['title'], checked(!empty($this->mountains[$mountain['resort_id']]), true, false), $mountain['resort_id']);
        }
        $output .= '</ul>';

        return $output;
    }

    function buildLocation() {
        $c = ' selected="selected"';
        $output = '
        <select id="snow_report_location" name="snow_report[location]">
            <option value=""'; if(empty($this->location)) { $output .= $c; }  $output .= '>Select a Location</option>
            <optgroup label="US">
            <option value="Alaska"'; if(sanitize_title($this->location) == sanitize_title('Alaska')) { $output .= $c; }  $output .= '>Alaska</option>
            <option value="Arizona"'; if(sanitize_title($this->location) == sanitize_title('Arizona')) { $output .= $c; } $output .= '>Arizona</option>
            <option value="California"'; if(sanitize_title($this->location) == sanitize_title('California')) { $output .= $c; } $output .= '>California</option>
            <option value="Colorado"'; if(sanitize_title($this->location) == sanitize_title('Colorado')) { $output .= $c; } $output .= '>Colorado</option>
            <option value="Connecticut"'; if(sanitize_title($this->location) == sanitize_title('Connecticut')) { $output .= $c; } $output .= '>Connecticut</option>
            <option value="Idaho"'; if(sanitize_title($this->location) == sanitize_title('Idaho')) { $output .= $c; } $output .= '>Idaho</option>
            <option value="Illinois"'; if(sanitize_title($this->location) == sanitize_title('Illinois')) { $output .= $c; } $output .= '>Illinois</option>
            <option value="Indiana"'; if(sanitize_title($this->location) == sanitize_title('Indiana')) { $output .= $c; } $output .= '>Indiana</option>
            <option value="Iowa"'; if(sanitize_title($this->location) == sanitize_title('Iowa')) { $output .= $c; } $output .= '>Iowa</option>
            <option value="Maine"'; if(sanitize_title($this->location) == sanitize_title('Maine')) { $output .= $c; } $output .= '>Maine</option>
            <option value="Maryland"'; if(sanitize_title($this->location) == sanitize_title('Maryland')) { $output .= $c; } $output .= '>Maryland</option>
            <option value="Massachusetts"'; if(sanitize_title($this->location) == sanitize_title('Massachusetts')) { $output .= $c; } $output .= '>Massachusetts</option>
            <option value="Michigan"'; if(sanitize_title($this->location) == sanitize_title('Michigan')) { $output .= $c; } $output .= '>Michigan</option>
            <option value="Minnesota"'; if(sanitize_title($this->location) == sanitize_title('Minnesota')) { $output .= $c; } $output .= '>Minnesota</option>
            <option value="Missouri"'; if(sanitize_title($this->location) == sanitize_title('Missouri')) { $output .= $c; } $output .= '>Missouri</option>
            <option value="Montana"'; if(sanitize_title($this->location) == sanitize_title('Montana')) { $output .= $c; } $output .= '>Montana</option>
            <option value="Nevada"'; if(sanitize_title($this->location) == sanitize_title('Nevada')) { $output .= $c; } $output .= '>Nevada</option>
            <option value="New Hampshire"'; if(sanitize_title($this->location) == sanitize_title('New Hampshire')) { $output .= $c; } $output .= '>New Hampshire</option>
            <option value="New Jersey"'; if(sanitize_title($this->location) == sanitize_title('New Jersey')) { $output .= $c; } $output .= '>New Jersey</option>
            <option value="New Mexico"'; if(sanitize_title($this->location) == sanitize_title('New Mexico')) { $output .= $c; } $output .= '>New Mexico</option>
            <option value="New York"'; if(sanitize_title($this->location) == sanitize_title('New York')) { $output .= $c; } $output .= '>New York</option>
            <option value="North Carolina"'; if(sanitize_title($this->location) == sanitize_title('North Carolina')) { $output .= $c; } $output .= '>North Carolina</option>
            <option value="Ohio"'; if(sanitize_title($this->location) == sanitize_title('Ohio')) { $output .= $c; } $output .= '>Ohio</option>
            <option value="Oregon"'; if(sanitize_title($this->location) == sanitize_title('Oregon')) { $output .= $c; } $output .= '>Oregon</option>
            <option value="Pennsylvania"'; if(sanitize_title($this->location) == sanitize_title('Pennsylvania')) { $output .= $c; } $output .= '>Pennsylvania</option>
            <option value="South Dakota"'; if(sanitize_title($this->location) == sanitize_title('South Dakota')) { $output .= $c; } $output .= '>South Dakota</option>
            <option value="Tennessee"'; if(sanitize_title($this->location) == sanitize_title('Tennessee')) { $output .= $c; } $output .= '>Tennessee</option>
            <option value="Utah"'; if(sanitize_title($this->location) == sanitize_title('Utah')) { $output .= $c; } $output .= '>Utah</option>
            <option value="Vermont"'; if(sanitize_title($this->location) == sanitize_title('Vermont')) { $output .= $c; } $output .= '>Vermont</option>
            <option value="Virginia"'; if(sanitize_title($this->location) == sanitize_title('Virginia')) { $output .= $c; } $output .= '>Virginia</option>
            <option value="Washington"'; if(sanitize_title($this->location) == sanitize_title('Washington')) { $output .= $c; } $output .= '>Washington</option>
            <option value="West Virginia"'; if(sanitize_title($this->location) == sanitize_title('West Virginia')) { $output .= $c; } $output .= '>West Virginia</option>
            <option value="Wisconsin"'; if(sanitize_title($this->location) == sanitize_title('Wisconsin')) { $output .= $c; } $output .= '>Wisconsin</option>
            <option value="Wyoming"'; if(sanitize_title($this->location) == sanitize_title('Wyoming')) { $output .= $c; } $output .= '>Wyoming</option>
        </optgroup>
        <optgroup label="Canada">
            <option value="Alberta"'; if(sanitize_title($this->location) == sanitize_title('Alberta')) { $output .= $c; } $output .= '>Alberta</option>
            <option value="British Columbia"'; if(sanitize_title($this->location) == sanitize_title('British Columbia')) { $output .= $c; } $output .= '>British Columbia</option>
            <option value="Ontario"'; if(sanitize_title($this->location) == sanitize_title('Ontario')) { $output .= $c; } $output .= '>Ontario</option>
            <option value="Quebec"'; if(sanitize_title($this->location) == sanitize_title('Quebec')) { $output .= $c; } $output .= '>Quebec</option>
        </optgroup>
        <optgroup label="Europe">

            <option value="Andorra"'; if(sanitize_title($this->location) == sanitize_title('Andorra')) { $output .= $c; } $output .= '>Andorra</option>
            <option value="Austria"'; if(sanitize_title($this->location) == sanitize_title('Austria')) { $output .= $c; } $output .= '>Austria</option>
            <option value="France"'; if(sanitize_title($this->location) == sanitize_title('France')) { $output .= $c; } $output .= '>France</option>
            <option value="Germany"'; if(sanitize_title($this->location) == sanitize_title('Germany')) { $output .= $c; } $output .= '>Germany</option>
            <option value="Italy"'; if(sanitize_title($this->location) == sanitize_title('Italy')) { $output .= $c; } $output .= '>Italy</option>
            <option value="Switzerland"'; if(sanitize_title($this->location) == sanitize_title('Switzerland')) { $output .= $c; } $output .= '>Switzerland</option>
        </optgroup>
        <optgroup label="Southern Hemi">
            <option value="Argentina"'; if(sanitize_title($this->location) == sanitize_title('Argentina')) { $output .= $c; } $output .= '>Argentina</option>
            <option value="Australia"'; if(sanitize_title($this->location) == sanitize_title('Australia')) { $output .= $c; } $output .= '>Australia</option>
            <option value="Chile"'; if(sanitize_title($this->location) == sanitize_title('Chile')) { $output .= $c; } $output .= '>Chile</option>
            <option value="New Zealand"'; if(sanitize_title($this->location) == sanitize_title('New Zealand')) { $output .= $c; } $output .= '>New Zealand</option>
        </optgroup>
        </select>
        ';

        $output .= '<label for="snow_report_location" style="padding-left:10px;">Snow Report Location</label>';
        return $output;
    }

}
// End Class


// If a shortcode is updated, we want the latest version, so we reset the cache
function snow_report_updated_post($id) {
    if(isset($_POST['post_content']) && strpos($_POST['post_content'], '[snow_report')) {
        delete_transient('snow_report_tables');
    }
    return $id;
}

add_action('save_post', 'snow_report_updated_post');

function snow_report_delete_transient($options) {
    if(is_admin() && isset($_POST['action']) && $_POST['action'] == 'update') {
        delete_transient('snow_report_tables');
    }
    return $options;
}
add_action('pre_update_option_snow_report', 'snow_report_delete_transient');


<?php
/*
Plugin Name: Snow Report
Plugin URI: http://www.seodenver.com/snow-report/
Description: Get mountain snow reports (including base pack, recent snowfall) in your content or your sidebar.  Powered by www.onthesnow.com.
Version: 1.0
Author: Katz Web Services, Inc.
Author URI: http://www.seodenver.com/
*/

class snow_report {
	var $url = 'http://www.onthesnow.com';
	var $location = 'Colorado';
	var $mountain = '';
	var $measurement = 'inches';
	var $type = 'table';
	var $align = 'center';
	var $caption = 'Snow Report for Ski Mountains in Colorado';
	var $show_closed = 'yes';
	var $showlink = 'no';
	var $noresults = 'Snow reports aren&rsquo;t available right now.';
	
	function snow_report() {
	
		add_action('admin_menu', array(&$this, 'admin'));
	    add_filter('plugin_action_links', array(&$this, 'settings_link'), 10, 2 );
        add_action('admin_init', array(&$this, 'settings_init') );
    	$this->options = get_option('snow_report', array());
        add_shortcode('snow_report', array(&$this, 'build_snow_report'));
        
        // Set each setting...
        foreach($this->options as $key=> $value) {
        	$this->{$key} = $value;
        }
		
		if(!is_admin()) {
			add_action('wp_footer', array(&$this,'showlink'));
		}
	}
	
	function settings_init() {
        register_setting( 'snow_report_options', 'snow_report', array(&$this, 'sanitize_settings') );
    }
    
    function sanitize_settings($input) {
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
        add_options_page('Snow Report', 'Snow Report', 'administrator', 'snow_report', array(&$this, 'admin_page'));  
    }
    
    function admin_page() {
        ?>
        <div class="wrap">
        <h2>Snow Report: Ski Reports for WordPress</h2>
        <div class="postbox-container" style="width:65%;">
            <div class="metabox-holder">	
                <div class="meta-box-sortables">
                    <form action="options.php" method="post">
                   <?php 
                    	wp_nonce_field('update-options'); 
                        settings_fields('snow_report_options');
                   
                       
                        $rows[] = array(
                        		'id' => 'snow_report_location',
                                'label' => __('Report Location', 'snow_report'),
                                'desc' => 'Where do you want your ski/snow report?',
                        		'content' => $this->buildLocation()
                        );

                        $rows[] = array(
                                'id' => 'snow_report_mountain',
                                'label' => __('Specific Mountain (Optional - <strong>Requires "Report Location"</strong>)', 'snow_report'),
                                'content' => "<input type='text' name='snow_report[mountain]' id='snow_report_mountain' value='".esc_attr($this->mountain)."' size='40' style='width:95%!important;' />",
                                'desc' => 'If you have a specific mountain in mind, add it here (<strong>EXACTLY as it appears</strong> in the table!!!)'
                        );
                            
                        $rows[] = array(
                        		'id' => 'wp_wunderground_measurement',
                                'label' => __('Snow Measurement', 'snow_report'),
                                'desc' => 'Are you metric or U.S. baby?',
                        		'content' => $this->buildMeasurement()
                        );
                                           
                        $rows[] = array(
                                'id' => 'snow_report_caption',
                                'label' => __('Snow Report Caption', 'snow_report'),
                                'content' => "<input type='text' name='snow_report[caption]' id='snow_report_caption' value='".esc_attr($this->caption)."' size='40' style='width:95%!important;' />",
                                'desc' => 'This will display above the report. Think of it like a report title.'
                            );
                            
                        $rows[] = array(
                                'id' => 'snow_report_noresults',
                                'label' => __('No Results Text', 'snow_report'),
                                'content' => "<input type='text' name='snow_report[noresults]' id='snow_report_noresults' value='".esc_attr($this->noresults)."' size='40' style='width:95%!important;' />",
                                'desc' => 'If all mountains are closed, or for some reason the plugin\'s feed isn\'t working, display this text.'
                        );
                        
                        $checked = (empty($this->show_closed) || $this->show_closed == 'yes') ? ' checked="checked"' : '';
                        
                        $rows[] = array(
                                'id' => 'snow_report_show_closed',
                                'label' => __('Show Closed Mountains', 'snow_report'),
                                'desc' => 'Do you want to show results for mountains that are no longer open for the season?',
                                'content' => "<p><label for='snow_report_show_closed'><input type='hidden' name='snow_report[show_closed]' value='no' /><input type='checkbox' name='snow_report[show_closed]' value='yes' id='snow_report_show_closed' $checked /> Show Seasonally Closed Mountains</label></p>"
                        );
                          
                        
                        $checked = (empty($this->showlink) || $this->showlink == 'yes') ? ' checked="checked"' : '';
                        
                        $rows[] = array(
                                'id' => 'snow_report_showlink',
                                'label' => __('Give Thanks', 'snow_report'),
                                'desc' => 'Checking the box tells the world you use this free plugin by adding a link to your footer. If you don\'t like it, you can turn it off, so please enable.',
                                'content' => "<p><label for='snow_report_showlink'><input type='hidden' name='snow_report[showlink]' value='no' /><input type='checkbox' name='snow_report[showlink]' value='yes' id='snow_report_showlink' $checked /> Help show the love.</label></p>"
                        );
                            						                                
                        $this->postbox('snow_reportsettings',__('Store Settings', 'snow_report'), $this->form_table($rows), false);
                         
                    ?>
                        

                        <input type="hidden" name="page_options" value="<?php foreach($rows as $row) { $output .= $row['id'].','; } echo substr($output, 0, -1);?>" />
                        <input type="hidden" name="action" value="update" />
                        <p class="submit">
                        <input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes', 'snow_report') ?>" />
                        </p>
                    </form>
                </div>
            </div>
        </div>
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
    
 	function showLink() {
    	if($this->showlink == 'yes') {
			mt_srand(crc32($_SERVER['REQUEST_URI'])); // Keep links the same on the same page
			
			$urls = array('http://www.seodenver.com/snow-report/?ref=snow-report', 'http://wordpress.org/extend/plugins/snow-report/', 'http://www.denversnowremovalservice.com/?ref=snow-report');
			$url = $urls[mt_rand(0, count($urls)-1)];
			$names = array('Snow Report', 'Snow Report', 'Ski Report', 'Mountain Report');
			$name = $names[mt_rand(0, count($names)-1)];
			$kws = array('Denver Snow Removal', 'Denver Snow Plowing', 'Denver Snow Service', 'Snow Removal', 'Snow Removal Denver', 'Snow Service Denver');
			$kw = $kws[mt_rand(0, count($kws)-1)];
			$links = array(
				'Snow report by <a href="http://wordpress.org/extend/plugins/snow-report/">'.$name.'</a> &amp; <a href="'.$url.'">'.$kw.'</a>',
				'Our snow conditions report is by <a href="'.$url.'">'.$name.'</a>',
				'Ski mountain conditions from <a href="'.$url.'">'.$kw.'</a>'
			);
			if(!empty($this->location)) {
				$links[] = 'The snow report for '.$this->location.' by <a href="'.$url.'">'.$name.'</a>';
			}
			$link = '<p class="snow_report" style="text-align:center;">'.trim($links[mt_rand(0, count($links)-1)]).'</p>';
	
			echo apply_filters('snow_report_showlink', $link);
			
			mt_srand(); // Make it random again.
    	}
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
    
    function buildMeasurement() {
    	$c = ' selected="selected"';
    	$output = '<select id="snow_report_measurement" name="snow_report[measurement]">';
		$output .= '	<option value="inches"'; if($this->measurement == 'inches') { $output .= $c; } $output .= '>U.S. (inches)</option>';
		$output .= '	<option value="cm"'; if($this->measurement == 'cm') { $output .= $c; } $output .= '>Metric (cm)</option>';
		$output .= '</select>';
		$output .= '<label for="snow_report_measurement" style="padding-left:10px;">Inches or Centimeters:</label>';
		return $output;
	}
        
    function configuration() { 
    $date2 = date('m-d-y');
    $date = date('m/d/Y');
    $weekday = date('l');
    $shortcode = '[snow_report location="'.$this->location.'" mountain="" caption="Snow Reports for Ski Mountains in '.$this->location.'" align="left"]';
    $report = do_shortcode($shortcode);
	$html = <<<EOD
	<h4>Adding the Snow Report to your Content</h4>	
	<p class="howto updated" style="padding:1em;">If you configure the settings to the left, all you will need to do is add <code>[snow_report]</code> to your post or page content or text widget to add the snow report table.</p>
	
	<h4>Using the <code>[snow_report]</code> Shortcode</h4>
	
	<p>If you're a maniac for shortcodes, and you want all control all the time, this is a good way to use it.</p>
	<p><strong>The shortcode supports the following settings:</strong></p>
	<ul>
		<li><code>location="Colorado"</code> - It must exactly match one of the "Report Location" drop-down options on the left
		</li><li><code>caption="Ski Reports for Colorado"</code> - Add a caption to your table (it's like a title) 
		</li><li><code>measurement='inches'</code> - Use either <code>inches</code> or <code>cm</code>
		</li><li><code>align='center'</code> - Align the table cells. Choose from <code>left</code>, <code>center</code>, or <code>right</code>
		</li><li><code>noresults="Snow reports aren&rsquo;t available right now."</code> - Message shown when no results are available
		</li><li><code>showclosed="yes"</code> - Show seasonally closed mountains (<code>yes</code> or <code>no</code>)
		</li><li><code>class="css_table_class"</code> - Change the CSS class of the generated report table
	</ul>
	<hr style="padding-top:1em; outline:none; border:none; border-bottom:1px solid #ccc;"/>
	<h4>Shortcode example</h4>
	<h5>The following code&hellip;</h5>
	<p><code>$shortcode</code></p>
	<h5>&hellip;will output this table:</h5>
	$report
	
EOD;
	return $html;
    }
    
    // THANKS JOOST!
    function form_table($rows) {
        $content = '<table class="form-table" width="100%">';
        foreach ($rows as $row) {
            $content .= '<tr><th valign="top" scope="row" style="width:50%">';
            if (isset($row['id']) && $row['id'] != '')
                $content .= '<label for="'.$row['id'].'" style="font-weight:bold;">'.$row['label'].':</label>';
            else
                $content .= $row['label'];
            if (isset($row['desc']) && $row['desc'] != '')
                $content .= '<br/><small>'.$row['desc'].'</small>';
            $content .= '</th><td valign="top">';
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
	
	function r($content, $kill = false) {
		echo '<pre>'.print_r($content,true).'</pre>';
		if($kill) { die(); }
	}
	
	function build_snow_report($atts = array(), $content=null) {

		extract( shortcode_atts( array(
	      'location'	=>	$this->location,
	      'mountain'	=>	$this->mountain,
	      'measurement' => 	$this->measurement,
	      'align'		=>	$this->align,
	      'caption'		=>	$this->caption,
	      'show_closed' =>	$this->show_closed,
	      'class'		=>	'snow_report',
	      'triedonce'	=>	false // For recursive
	      ), $atts ) );
	    
	    $width = round(100/5, 2);
	    
	    // Get the RSS feed. If specific mountain has been set, if it doesn't work,
	    // we revert to only the location without the mountain
	    $url = 'http://www.onthesnow.com/'.sanitize_title($location);
	    if(!empty($mountain) && !$triedonce) { $url .= '/'.sanitize_title($mountain); } 
	    $url .= '/snow.rss';

		if(!$xml=@simplexml_load_file($url)) {
			if(!$triedonce) {
				$atts['triedonce'] = 1;
				return $this->build_snow_report($atts, $content);
			} else {
				return '<!-- Snow Report Error : Error reading XML file at '.$url.' and '.$url2.' -->'.$content;
			}
		} else if(!empty($xml)) {
			// The following code is modified from PHP.net;
			// http://www.php.net/manual/en/simplexmlelement.xpath.php
		 	//Fetch all namespaces 
			$namespaces = $xml->getNamespaces(true); 
			//Register them with their prefixes 
			foreach ($namespaces as $prefix => $ns) { 
			    $xml->registerXPathNamespace($prefix, $ns); 
			}
			$showTable = '';
		 	foreach($xml->xpath('//item') as $rsrow){ 
		 		$closed = preg_match('/Closed/ism', $rsrow->description);
		 		if(!preg_match('/Permanently(?:\s+)?closed/ism', $rsrow->description) && ($closed && $this->show_closed == 'yes' || !$closed)) { 
				    $row = @simplexml_load_string($rsrow->asXML()); 
				    $row = simpleXMLToArray($row);

				    if(strtolower($this->measurement) == 'cm') { $symbol = 'cm'; $mName = 'cm.'; } else { $symbol = '&quot;'; $mName = 'in.'; }
				    
				    foreach($row as $key => $item) {
				    	if(is_numeric($item) && $key != 'resort_id' && $this->measurement == 'cm') {
				    			$row[$key] = ceil($item * 2.54); // 2.54 cm/in
				    	}
				    }
	
				    extract($row);
					
					$tablebody .=
					"\n\t\t\t\t\t\t".'<tr>'.
					"\n\t\t\t\t\t\t\t".'<th align="'.$align.'" scope="row" width="28%">'.wptexturize(esc_attr($title)).'</th>'.
					"\n\t\t\t\t\t\t\t".'<td align="'.$align.'">'.$open_staus.'</td>'.
					"\n\t\t\t\t\t\t\t".'<td align="'.$align.'">'.$base_depth.$symbol.'</td>'.
					"\n\t\t\t\t\t\t\t".'<td align="'.$align.'">'.$snowfall_48hr.$symbol.'</td>'.
					"\n\t\t\t\t\t\t\t".'<td align="'.$align.'">'.$surface_condition.'</td>'.
					"\n\t\t\t\t\t\t".'</tr>';
					$showTable++;
				}
				$i++;
			}
			
			if($showTable > 0) {
			if(!empty($caption)) { 
				$caption = "\n\t\t\t\t\t<caption>{$caption}</caption>";
			}
			$table = '
				<table cellpadding="0" cellspacing="0" border="0" width="100%" class="'.esc_attr($class).'">'.$caption.'
					<thead>
						<tr>
							<th scope="col" align="'.$this->align.'" width="'.$width.'%">Resort</th>
							<th scope="col" align="'.$this->align.'" width="'.$width.'%">Open Status</th>
							<th scope="col" align="'.$this->align.'" width="'.$width.'%">Base Depth ('.$mName.')</th>
							<th scope="col" align="'.$this->align.'" width="'.$width.'%">48hr Snowfall</th>
							<th scope="col" align="'.$this->align.'" width="'.$width.'%">Surface Condition</th></tr>
					</thead>
					<tbody>
						'.$tablebody.'
					</tbody>
				</table>';
			#	echo $table;
			} else {
				$table = '<div class="warning notice '.esc_attr($class).'">'.wpautop($this->noresults).'</div>';
			}
			
			return apply_filters('snow_report_output', $table);
		} else {
			return '<!-- Snow Report Error : Snow report feed was empty from '.$this->url.$this->location.' -->'.$content;
		}
	}
	
}
// End Class

if(!function_exists('simpleXMLToArray')) {
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
            $value = simpleXMLToArray($child, $flattenValues, $flattenAttributes, $flattenChildren, $valueKey, $attributesKey, $childrenKey);
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
}
function init_snow_report() {
	if(method_exists('snow_report', 'snow_report')) {
		$snow_report = new snow_report;
	}
}

add_action('plugins_loaded', 'init_snow_report');

// If you want to use shortcodes in your widgets, you should!
add_filter('widget_text', 'do_shortcode');
add_filter('wp_footer', 'do_shortcode');


?>
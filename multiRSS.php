<?php
/*
Plugin Name: Multiple RSS
Plugin URI: http://www.hartinc.com
Description: This plugin combins and displays mulitple RSS Feeds
Version: 1.0
Author: Robert C. Green II
Author URI: http://www.robertcgreenii.com 
Author URI: http://www.parallelcoding.com

 Copyright 2009  (email : Robert.C.Green@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include("Mobile_Detect.php");

//Version
$multi_rss_version = "1.0";

//Admin Action
add_action('admin_menu', 'multi_rss_menu');

//Activation Hook
register_activation_hook(__FILE__,'multi_rss_install');

//Options
add_option('num_feeds_to_show',3);
add_option('direction','ASC');
add_option('multiRss_Header','<h1>Recent Stuff</h1>');
add_option('multiRss_ShowOnMobile',1);

//ShortCode
add_shortcode('multiRSSDisplay', 'multi_rss_display_feeds');


/**
 * Installs MultiRSS Plugin
 *
 * @since 1.0
 */
function multi_rss_install(){

	global $wpdb;
	$table_name = $wpdb->prefix . 'multirss';
	
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

		$sql = 'CREATE TABLE ' . $table_name . ' (
			feedId mediumint(9) NOT NULL AUTO_INCREMENT,
			URL text NOT NULL,
			Favicon text NOT NULL,
			UNIQUE KEY id (id)
			);';
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		add_option("multi_rss_version", $multi_rss_version);
	}
}


function multi_rss_enqueue_scripts() {
	echo '<link rel="stylesheet" type="text/css" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/MultiRSS/style.css"></link>';
	echo '<script language="JavaScript" type="text/javascript" src="' . get_bloginfo('wpurl') . '/wp-includes/js/jquery/jquery.js"></script>';
	echo '<script language="JavaScript" type="text/javascript" src="' . get_bloginfo('wpurl') . '/wp-content/plugins/MultiRSS/jquery.tablesorter.js"></script>';
}

/**
 * Displays Multi Rss Feeds from Shortcode
 * 
 * @since 1.0
 *
 * @param    array    $atts    Array of Attributes
 * @param    string    $atts    Content
  *
  * @return   string 	Feeds to Display
 */

function multi_rss_display_feeds($atts, $content=null){
	
	$detect = new Mobile_Detect();
	if(get_option('multiRss_ShowOnMobile') == 0 && $detect->isMobile()){
		return;
	}
	
	global $wpdb;
	$table_name = $wpdb->prefix . 'multirss';
	$results = $wpdb->get_results("SELECT * FROM $table_name");
	$retValue = get_option('multiRss_Header');
	
	if(count($results) > 0){
		
		//Get All Feeds
		foreach ($results as $result) {
			$cur_feed = new SimplePie();
			$cur_feed->set_feed_url($result->URL);
			$cur_feed->enable_cache(false);
			$cur_feed->set_favicon_handler($result->Favicon);
			$cur_feed->init();

			$my_feeds[] = $cur_feed;
		
		}
		
		//Merge feeds 1 by 1
		$feeds = SimplePie::merge_items($my_feeds);
		
		//Iterate and display
		$count = 0;
		foreach($feeds as $item){
	
			if($count >= intval(get_option('num_feeds_to_show'))) break;
			
			//Current Image
			$curImage = '';
			
			//Get Parent Feed
			$feed = $item->get_feed();
			
			//Output
			$retValue .= '<a href="' . $item->get_link() . '"><span>' . $item->get_title() . '</span><img src="' . $feed->get_favicon() . '"/></a>';
		
			//Iterate Counter
			$count++;
		}
	}
	
	return $retValue;
}

/**
 * Sets up Multi RSS Options Menu Item
 *
 * @since 1.0
 */

function multi_rss_menu() {
	add_options_page('Multi RSS Options', 'Multi RSS Options', 8, __FILE__, 'multi_rss_options');
}

/**
 * Validates Image file before upload
 * 
 *
 * @since 1.0
 *
 * @param    string    $filename    Name of Image File
 * @return   bool                Valid or Not?
 */
function validate_image($filename){

	// These will be the types of file that will pass the validation.
	$allowed_filetypes = array('.jpg','.gif','.bmp','.png'); 
	
	// Maximum filesize in BYTES (currently 0.5MB).
	$max_filesize = 524288; 
	
	// Get the extension from the filename.
	$ext = substr($filename, strpos($filename,'.'), strlen($filename)-1); 

	//If it is an allowed fileType
	if(!in_array($ext,$allowed_filetypes)){
		echo '<p>' . $ext . ' is not an allowed file type!</p>';
		return false;
	}
	
	//If it's a valid size
	if($_FILES['newFeedFavicon']['size'] > $max_filesize){
		echo '<p>Image file is too large!</p>';
		return false;
	}

	return true;
}
/**
 * Handle Image Upload
 * 
 * Taken from http://www.packtpub.com/article/developing-post-types-plugin-with-wordpress
 *
 * @since 1.0
 *
 * @param    Array    $upload    File information to upload
 * @return   Array                File upload information
 */
function handle_image_upload($upload)
{
	// check if image
	if (file_is_displayable_image( $upload['tmp_name'] )){
		// handle the uploaded file
		$overrides = array('test_form' => false);
		$file = wp_handle_upload($upload, $overrides);
	}
	return $file;
}

/**
 * Display and save all settings
 *
 * @since 1.0
 */
function multi_rss_options() {
	multi_rss_enqueue_scripts();
	global $wpdb;
	$errorMsg = '';
	
	//Save Options
	if($_POST['multi_rss_save']){
	
		//Begin output formatting
		echo '<div class="updated">';
		
		//If the number of feeds to show is numeric
		if(is_numeric($_POST['num_feeds_to_show'])){
		
			//Update the optoin
			update_option('num_feeds_to_show',$_POST['num_feeds_to_show']);
			echo '<p>Number of feeds to show updated succesfully.</p>';
		}else{
			//Not a valid # of feeds to show
			echo '<p>Number of feeds is Non-Numeric!</p>';
		}
		
		//If the header is there
		if(isset($_POST['header'])){
		
			//Update the optoin
			update_option('multiRss_Header',$_POST['header']);
			echo '<p>Header updated succesfully.</p>';
		}else{
			//Not a valid # of feeds to show
			echo '<p>Bad Header Value!</p>';
		}
		//End Div
		
		
		//If Show on mobile
		if(isset($_POST['show_on_mobile'])){
			//Update the optoin
			update_option('multiRss_ShowOnMobile',1);
			echo '<p>True - Show on mobile updated succesfully.</p>';
		}else{
			//Update the optoin
			update_option('multiRss_ShowOnMobile',0);
			echo '<p>False - Show on mobile updated succesfully.</p>';
		}
		//End Div
		
		echo '</div>';
	}
	
	//Delete if Necessary
	if($_POST['multi_rss_delete_feeds']){
		
		//Begin output formatting
		echo '<div class="updated">';
		
		//Get all the items to delete
		$items_to_delete = $_POST['items_to_delete'];
		
		//Var to hold ids to delete
		$ids = '';
		
		//Build statement of ids
		foreach($items_to_delete as $item){
			$ids .= '"' . $item . '",';
		}
		
		//IF we actually have ids
		if(strlen($ids) > 0){
		
			//Remove the last comma
			$ids = substr($ids, 0, strlen($ids)-1);
			
			//Delete the little buggers
			$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'multirss WHERE feedId IN (' . $ids . ')');
			echo '<p>Item(s) deleted succesfully.</p>';
		}else{
			echo '<p>Error during item deletion. Please try again.</p>';
		}
		
		//End formatting
		echo '</div>';
	}
	
	//Save new feed
	if($_POST['multi_rss_newfeed_save']){
	
		//Validation Var
		$isValid = false;
		
		//Begin Output
		echo '<div class="updated">';
		
		//Get current Feed URL
		$url = trim($_POST['newFeed']);
		
		//Make sure the file is actually there
		if((!empty($_FILES['newFeedFavicon'])) && ($_FILES['newFeedFavicon']['error'] == 0)) {
					
			//Get Upload Directory
			$uploads = wp_upload_dir();
			//The current file to upload
			$upload = $_FILES['newFeedFavicon'];
				
			//Perform Validations
			if(is_writable($uploads['path']) && validate_image($upload['name'])){
			
				// if file uploaded
				if ($upload['tmp_name']){
					
					// handle uploaded image
					$file = handle_image_upload($upload);
				
					if ($file){
						$image_url = $file['url'];
						
						//Insert into table
						$wpdb->query('INSERT INTO ' . $wpdb->prefix . 'multirss(URL, Favicon) VALUES("' . $wpdb->escape($url) . '","' .$image_url . '")');
						
						echo '<p>Image succesfully uploaded!</p>';
						
					}
				}
			}
		}else{
			echo '<p>Poorly chosen file. Please try again.</p>';
		}
		//End output
		echo '</div>';
	}
	?>
	<div class="wrap">
		<h2>Multi RSS Settings</h2>
		<form method="post" id="multi_rss_options">
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Number of Feed Items to Show:</th>
					<td><input type="text" name="num_feeds_to_show" value="<?php echo get_option('num_feeds_to_show'); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Header:</th>
					<td><input type="text" name="header" value="<?php echo get_option('multiRss_Header'); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Show on Mobile Devices:</th>
					<td><input type="checkbox" name="show_on_mobile" <?php if(get_option('multiRss_ShowOnMobile') == 1){ echo 'checked';}?>/></td>
				</tr>
			</table>	
			
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="num_feeds_to_show,multiRss_Image_Upload_Path" />

			<p class="submit">
				<input type="submit" class="button-primary" name="multi_rss_save" value="Save" />
			</p>
		</form>
		
		<form method="post" id="multi_rss_feeds">
			<h4>Current Feeds:</h4>
			
				<?php
				
					//Get Table Name
					$table_name = $wpdb->prefix . 'multirss';
					
				
					//Get all the Current Feeds
					$results = $wpdb->get_results("SELECT * FROM $table_name");
					
					//If we have resutls
					if(count($results) > 0){
					
						//Begin building our table
						echo '<table id="multiRssTable" class="tablesorter">';
						echo '<thead>';
						echo '<tr>';
						echo '<th>Delete?</th>';
						echo '<th>URL</th>';
						echo '<th>Favicon</th>';
						echo '</tr>';
						echo '<tr>';
						echo '</thead>';
						
						echo '<tbody>';
						//For each Result build the output row
						foreach ($results as $result) {
							echo '<tr>';
							echo '<td><input type="checkbox" name="items_to_delete[]" value="' . $result->feedId . '"/></td>';
							echo '<td>' . str_replace('&','&amp;',$result->URL) . '</td>';
							echo '<td><img align="right" src="' .  $result->Favicon . '"/></td>';
							echo '</tr>';
						}
						
						//End the table
						echo '</tr>';
						echo '</tbody>';
						echo '</table>';
						echo '<p class="submit">';
						echo '<input type="submit" class="button-primary" name="multi_rss_delete_feeds" value="Delete Selected" />';
						echo '</p>';
						
						echo '<script type="text/javascript">';
						//echo 'jQuery(document).ready(function() {'; 
						echo '	jQuery("#multiRssTable").tablesorter(); ';
						//echo '} );'; 
						echo '</script>';
					}else{
						echo '<div><p> There are no feeds currently added.</p></div>';
					}
				?>
				
		</form>
		
		<form method="post" enctype="multipart/form-data" id="multi_rss_newFeed">
			<input type="hidden" name="MAX_FILE_SIZE" value="100000" />

			<table class="form-table">
				<tr valign="top">
					<th scope="row">New Feed:</th>
					<td><input type="text" name="newFeed" value=""/></td>
				</tr>
				<tr valign="top">
					<th scope="row">Feed Favicon:</th>
					<td><input type="file" name="newFeedFavicon"/></td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" name="multi_rss_newfeed_save" value="Add Feed" />
			</p>
		</form>
	</div>
	<?php
}?>
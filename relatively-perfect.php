<?php
/*
Plugin Name: Relatively Perfect
Plugin URI: 
Description:  
Version: 0.0.1
Author: Blind Tigers
Author URI:
*/
/*  
	Copyright 2011 Blind Tigers

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
//Do a PHP version check, require 5.2 or newer
if(version_compare(PHP_VERSION, '5.2.0', '<'))
{
	//Silently deactivate plugin, keeps admin usable
	deactivate_plugins(plugin_basename(__FILE__), true);
	//Spit out die messages
	wp_die(sprintf(__('Your PHP version is too old, please upgrade to a newer version. Your version is %s, this plugin requires %s', 'rel_perf'), phpversion(), '5.2.0'));
}
//Include admin base class
if(!class_exists('mtekk_admin'))
{
	require_once(dirname(__FILE__) . '/includes/mtekk_admin_class.php');
}
/**
 * The administrative interface class 
 */
class RelativelyPerfect extends mtekk_admin
{
	protected $version = '0.0.1';
	protected $full_name = 'Relatively Perfect Settings';
	protected $short_name = 'Relatively Perfect';
	protected $access_level = 'manage_options';
	protected $identifier = 'rel_perf';
	protected $unique_prefix = 'btrp';
	protected $plugin_basename = '';
	protected $llynx_scrape;
	protected $opt = array(
					'global_style' => true,
					'p_max_count' => 5,
					'p_min_length' => 120,
					'p_max_length' => 180,
					'img_max_count' => 20,
					'img_min_x' => 50, 
					'img_min_y' => 50,
					'img_max_range' => 256,
					'curl_agent' => 'WP Links Bot',
					'curl_embrowser' => false,
					'curl_timeout' => 3,
					'cache_type' => 'original',
					'cache_quality' => 80,
					'cache_max_x' => 100,
					'cache_max_y' => 100,
					'cache_crop' => false,
					'short_url' => false,
					'template' => '<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>',
					'image_template' => '');
	protected $template_tags = array(
					'%url%',
					'%short_url%',
					'%image%',
					'%title%',
					'%description%'
					);
	/**
	 * __construct()
	 * 
	 * Class default constructor
	 */
	function __construct()
	{
		//We set the plugin basename here, could manually set it, but this is for demonstration purposes
		$this->plugin_basename = plugin_basename(__FILE__);
		//We're going to make sure we load the parent's constructor
		parent::__construct();
	}
	/**
	 * admin initialisation callback function
	 * 
	 * is bound to wpordpress action 'admin_init' on instantiation
	 * 
	 * @return void
	 */
	function init()
	{
		//We're going to make sure we run the parent's version of this function as well
		parent::init();
		//We can not synchronize our database options untill after the parent init runs (the reset routine must run first if it needs to)
		$this->opt = get_option($this->unique_prefix . '_options');
		//Add javascript enqeueing callback
		add_action('wp_print_scripts', array($this, 'javascript'));
	}
	/**
	 * security
	 * 
	 * Makes sure the current user can manage options to proceed
	 */
	function security()
	{
		//If the user can not manage options we will die on them
		if(!current_user_can($this->access_level))
		{
			wp_die(__('Insufficient privileges to proceed.', 'rel_perf'));
		}
	}
	/** 
	 * This sets up and upgrades the database settings, runs on every activation
	 */
	function install()
	{
		//Call our little security function
		$this->security();
		//Try retrieving the options from the database
		$opts = get_option($this->unique_prefix . '_version');
		//If there are no settings, copy over the default settings
		if(!is_array($opts))
		{
			//Add the options
			add_option($this->unique_prefix . '_options', $opts);
			add_option($this->unique_prefix . '_options_bk', $opts, false);
			//Add the version, no need to autoload the db version
			add_option($this->unique_prefix . '_version', $this->version, false);
		}
		else
		{
			//Retrieve the database version
			$db_version = get_option($this->unique_prefix . '_version');
			if($this->version !== $db_version)
			{
				//Run the settings update script
				$this->opts_upgrade($opts, $db_version);
				//Always have to update the version
				update_option($this->unique_prefix . '_version', $this->version);
				//Store the options
				update_option($this->unique_prefix . '_options', $this->opt);
			}
		}
	}
	/**
	 * Upgrades input options array, sets to $this->opt
	 * 
	 * @param array $opts
	 * @param string $version the version of the passed in options
	 */
	function opts_upgrade($opts, $version)
	{
		//If our version is not the same as in the db, time to update
		if($version !== $this->version)
		{
			//Upgrading from 0.2.x
			if(version_compare($version, '0.3.0', '<'))
			{
				$opts['short_url'] = false;
				$opts['template'] = '<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>';
			}
			//Save the passed in opts to the object's option array
			$this->opt = $opts;
		}
	}
	/**
	 * ops_update
	 * 
	 * Updates the database settings from the webform
	 */
	function opts_update()
	{
		global $wp_taxonomies;
		//Do some security related thigns as we are not using the normal WP settings API
		$this->security();
		//Do a nonce check, prevent malicious link/form problems
		check_admin_referer($this->unique_prefix . '_options-options');
		//Update local options from database
		$this->opt = get_option($this->unique_prefix . '_options');
		//Update our backup options
		update_option($this->unique_prefix . '_options_bk', $this->opt);
		//Grab our incomming array (the data is dirty)
		$input = $_POST[$this->unique_prefix . '_options'];
		//Loop through all of the existing options (avoids random setting injection)
		foreach($this->opt as $option => $value)
		{
			//Handle all of our boolean options first
			if($option == 'trends' || $option == 'backlink' || $option == 'cache_crop' || $option == 'global_style' || $option == 'curl_embrowser' || $option == 'short_url')
			{
				$this->opt[$option] = isset($input[$option]);
			}
			//Now handle all of the integers
			else if(strpos($option, 'img_m') === 0 || strpos($option, 'p_m') === 0 || strpos($option, 'cache_m') === 0 || $option == 'cache_quality')
			{
				$this->opt[$option] = (int) stripslashes($input[$option]);
			}
			//Now handle anything that can't be blank
			else if(strpos($option, 'curl_') === 0)
			{
				//Only save a new anchor if not blank
				if(isset($input[$option]))
				{
					//Do excess slash removal sanitation
					$this->opt[$option] = stripslashes($input[$option]);
				}
			}
			//Now everything else
			else
			{
				$this->opt[$option] = stripslashes($input[$option]);
			}
		}
		//Commit the option changes
		update_option($this->unique_prefix . '_options', $this->opt);
		//Check if known settings match attempted save
		if(count(array_diff_key($input, $this->opt)) == 0)
		{
			//Let the user know everything went ok
			$this->message['updated fade'][] = __('Settings successfully saved.', $this->identifier) . $this->undo_anchor(__('Undo the options save.', $this->identifier));
		}
		else
		{
			//Let the user know the following were not saved
			$this->message['updated fade'][] = __('Some settings were not saved.', $this->identifier) . $this->undo_anchor(__('Undo the options save.', $this->identifier));
			$temp = __('The following settings were not saved:', $this->identifier);
			foreach(array_diff_key($input, $this->opt) as $setting => $value)
			{
				$temp .= '<br />' . $setting;
			}
			$this->message['updated fade'][] = $temp . '<br />' . sprintf(__('Please include this message in your %sbug report%s.', $this->identifier),'<a title="' . __('Go to the WP Lynx support post for your version.', $this->identifier) . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . $this->version . '/#respond">', '</a>');
		}
		add_action('admin_notices', array($this, 'message'));
	}
	function admin_head_style()
	{
		?>
<style type="text/css">
/*WP Lynx Admin Styles*/
.describe td{vertical-align:top;}
.describe textarea{height:5em;}
.A1B1{width:128px;float:left;}
.llynx_thumb{height:138px;overflow:hidden;border-bottom:1px solid #dfdfdf;margin-bottom:5px;}
</style>
		<?php
	}
	/**
	 * javascript
	 *
	 * Enqueues JS dependencies (jquery) for the tabs
	 * 
	 * @see admin_init()
	 * @return void
	 */
	function javascript()
	{
		//Enqueue ui-tabs
		wp_enqueue_script('jquery-ui-tabs');
	}
	/**
	 * get help text
	 * 
	 * @return string
	 */
	protected function _get_help_text()
	{
		return sprintf(__('Tips for the settings are located below select options. Please refer to the %sdocumentation%s for more information.', 'rel_perf'), 
			'<a title="' . __('Go to the Links Lynx online documentation', 'rel_perf') . '" href="http://mtekk.us/code/wp-lynx/wp-lynx-doc/">', '</a>') .
			sprintf(__('If you think you have found a bug, please include your WordPress version and details on how to reporduce the bug when you %sreport the issue%s.', $this->identifier),'<a title="' . __('Go to the WP Lynx support post for your version.', $this->identifier) . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . $this->version . '/#respond">', '</a>') . '</p>';
	}
	/**
	 * admin_head
	 *
	 * Adds in the JavaScript and CSS for the tabs in the adminsitrative 
	 * interface
	 * 
	 */
	function admin_head()
	{	
		// print style and script element (should go into head element) 
		?>
<style type="text/css">
	/**
	 * Tabbed Admin Page (CSS)
	 * 
	 * @see Breadcrumb NavXT (Wordpress Plugin)
	 * @author Tom Klingenberg 
	 * @colordef #c6d9e9 light-blue (older tabs border color, obsolete)
	 * @colordef #dfdfdf light-grey (tabs border color)
	 * @colordef #f9f9f9 very-light-grey (admin standard background color)
	 * @colordef #fff    white (active tab background color)
	 */
#hasadmintabs ul.ui-tabs-nav {border-bottom:1px solid #dfdfdf; font-size:12px; height:29px; list-style-image:none; list-style-position:outside; list-style-type:none; margin:13px 0 0; overflow:visible; padding:0 0 0 8px;}
#hasadmintabs ul.ui-tabs-nav li {display:block; float:left; line-height:200%; list-style-image:none; list-style-position:outside; list-style-type:none; margin:0; padding:0; position:relative; text-align:center; white-space:nowrap; width:auto;}
#hasadmintabs ul.ui-tabs-nav li a {background:transparent none no-repeat scroll 0 50%; border-bottom:1px solid #dfdfdf; display:block; float:left; line-height:28px; padding:1px 13px 0; position:relative; text-decoration:none;}
#hasadmintabs ul.ui-tabs-nav li.ui-tabs-selected a{-moz-border-radius-topleft:4px; -moz-border-radius-topright:4px;border:1px solid #dfdfdf; border-bottom-color:#f9f9f9; color:#333333; font-weight:normal; padding:0 12px;}
#hasadmintabs ul.ui-tabs-nav a:focus, a:active {outline-color:-moz-use-text-color; outline-style:none; outline-width:medium;}
#screen-options-wrap p.submit {margin:0; padding:0;}
</style>
<script type="text/javascript">
/* <![CDATA[ */
	jQuery(function()
	{
		llynx_context_init();
		llynx_tabulator_init();		
	 });
	/**
	 * Tabulator Bootup
	 */
	function llynx_tabulator_init(){
		if (!jQuery("#hasadmintabs").length) return;		
		/* init markup for tabs */
		jQuery('#hasadmintabs').prepend("<ul><\/ul>");
		jQuery('#hasadmintabs > fieldset').each(function(i){
		    id      = jQuery(this).attr('id');
		    caption = jQuery(this).find('h3').text();
		    jQuery('#hasadmintabs > ul').append('<li><a href="#'+id+'"><span>'+caption+"<\/span><\/a><\/li>");
		    jQuery(this).find('h3').hide();					    
	    });	
		/* init the tabs plugin */
		jQuery("#hasadmintabs").tabs();
		/* handler for opening the last tab after submit (compability version) */
		jQuery('#hasadmintabs ul a').click(function(i){
			var form   = jQuery('#llynx-options');
			var action = form.attr("action").split('#', 1) + jQuery(this).attr('href');				
			form.get(0).setAttribute("action", action);
		});
	}
	/**
	 * context screen options for import/export
	 */
	 function llynx_context_init(){
		if (!jQuery("#llynx_import_export_relocate").length) return;
		jQuery('#screen-meta').prepend(
				'<div id="screen-options-wrap" class="hidden"></div>'
		);
		jQuery('#screen-meta-links').append(
				'<div id="screen-options-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
				'<a class="show-settings" id="show-settings-link" href="#screen-options"><?php printf('%s/%s/%s', __('Import', 'rel_perf'), __('Export', 'rel_perf'), __('Reset', 'rel_perf')); ?></a>' + 
				'</div>'
		);
		var code = jQuery('#llynx_import_export_relocate').html();
		jQuery('#llynx_import_export_relocate').html('');
		code = code.replace(/h3>/gi, 'h5>');		
		jQuery('#screen-options-wrap').prepend(code);		
	 }
/* ]]> */
</script>
<?php
	} //function admin_head()
	/**
	 * admin_page
	 * 
	 * The administrative page for Links Lynx
	 * 
	 */
	function admin_page()
	{
		global $wp_taxonomies;
		$this->security();
		$uploadDir = wp_upload_dir();
		if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		{
			//Let the user know their directory is not writable
			$this->message['error'][] = __('WordPress uploads directory is not writable, thumbnails will be dissabled.', $this->identifier);
			//Too late to use normal hook, directly display the message
			$this->message();
		}
		$this->version_check(get_option($this->unique_prefix . '_version'));
		?>
		<div class="wrap"><h2><?php _e('Relatively Perfect Settings', 'rel_perf'); ?></h2>		
		<p<?php if($this->_has_contextual_help): ?> class="hide-if-js"<?php endif; ?>><?php 
			print $this->_get_help_text();			 
		?></p>
		<form action="options-general.php?page=rel_perf" method="post" id="<?php echo $this->unique_prefix;?>-options">
			<?php settings_fields($this->unique_prefix . '_options');?>
			<div id="hasadmintabs">
			<fieldset id="general" class="<?php echo $this->unique_prefix;?>_options">
				<h3><?php _e('General', 'rel_perf'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_check(__('Shorten URL', 'rel_perf'), 'short_url', __('Shorten URL using a URL shortening service such as tinyurl.com.', 'rel_perf'));
						$this->input_check(__('Default Style', 'rel_perf'), 'global_style', __('Enable the default Lynx Prints styling on your blog.', 'rel_perf'));
						$this->input_text(__('Maximum Image Width', 'rel_perf'), 'cache_max_x', '10', false, __('Maximum cached image width in pixels.', 'rel_perf'));
						$this->input_text(__('Maximum Image Height', 'rel_perf'), 'cache_max_y', '10', false, __('Maximum cached image height in pixels.', 'rel_perf'));
						$this->input_check(__('Crop Image', 'rel_perf'), 'cache_crop', __('Crop images in the cache to the above dimensions.', 'rel_perf'));
					?>
					<tr valign="top">
						<th scope="row">
							<?php _e('Cached Image Format', 'rel_perf'); ?>
						</th>
						<td>
							<?php
								$this->input_radio('cache_type', 'original', __('Same as source format', 'rel_perf'));
								$this->input_radio('cache_type', 'png', __('PNG'));
								$this->input_radio('cache_type', 'jpeg', __('JPEG'));
								$this->input_radio('cache_type', 'gif', __('GIF'));
							?>
							<span class="setting-description"><?php _e('The image format to use in the local image cache.', 'rel_perf'); ?></span>
						</td>
					</tr>
					<?php
						$this->input_text(__('Cache Image Quality', 'rel_perf'), 'cache_quality', '10', false, __('Image quality when cached images are saved as JPEG.', 'rel_perf'));
					?>
				</table>
			</fieldset>
			<fieldset id="images" class="<?php echo $this->unique_prefix;?>_options">
				<h3><?php _e('Images', 'rel_perf'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Minimum Image Width', 'rel_perf'), 'img_min_x', '10', false, __('Minimum width of images to scrape in pixels.', 'rel_perf'));
						$this->input_text(__('Minimum Image Height', 'rel_perf'), 'img_min_y', '10', false, __('Minimum hieght of images to scrape in pixels.', 'rel_perf'));
						$this->input_text(__('Maximum Image Count', 'rel_perf'), 'img_max_count', '10', false, __('Maximum number of images to scrape.', 'rel_perf'));
						$this->input_text(__('Maximum Image Scrape Size', 'rel_perf'), 'img_max_range', '10', false, __('Maximum number of bytes to download when determining the dimensions of JPEG images.', 'rel_perf'));
					?>
				</table>
			</fieldset>
			<fieldset id="text" class="<?php echo $this->unique_prefix;?>_options">
				<h3><?php _e('Text', 'rel_perf'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Minimum Paragraph Length', 'rel_perf'), 'p_min_length', '10', false, __('Minimum paragraph length to be scraped (in characters).', 'rel_perf'));
						$this->input_text(__('Maximum Paragraph Length', 'rel_perf'), 'p_max_length', '10', false, __('Maximum paragraph length before it is cutt off (in characters).', 'rel_perf'));
						$this->input_text(__('Minimum Paragraph Count', 'rel_perf'), 'p_max_count', '10', false, __('Maximum number of paragraphs to scrape.', 'rel_perf'));
					?>
				</table>
			</fieldset>
			<fieldset id="advanced" class="<?php echo $this->unique_prefix;?>_options">
				<h3><?php _e('Advanced', 'rel_perf'); ?></h3>
				<table class="form-table">
					<?php
						$this->textbox(__('Lynx Print Template', 'rel_perf'), 'template', 3, false, __('Available tags: ', 'rel_perf') . implode(', ', $this->template_tags));
						$this->input_text(__('Timeout', 'rel_perf'), 'curl_timeout', '10', false, __('Maximum time for scrape execution in seconds.', 'rel_perf'));
						$this->input_text(__('Useragent', 'rel_perf'), 'curl_agent', '32', $this->opt['curl_embrowser'], __('Useragent to use during scrape execution.', 'rel_perf'));
						$this->input_check(__('Emulate Browser', 'rel_perf'), 'curl_embrowser', __("Useragent will be exactly as the users's browser.", 'rel_perf'));
					?>
				</table>
			</fieldset>
			</div>
			<p class="submit"><input type="submit" class="button-primary" name="<?php echo $this->unique_prefix;?>_admin_options" value="<?php esc_attr_e('Save Changes') ?>" /></p>
		</form>
		<?php $this->import_form(); ?>
		</div>
		<?php
	}
}
//Let's make an instance of our object takes care of everything
$RelativelyPerfect = new RelativelyPerfect;
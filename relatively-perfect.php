<?php
/*
Plugin Name: Relatively Perfect
Plugin URI: 
Description: A relatively perfect events and calendar management plugin.
Version: 0.0.1
Author: Blind Tigers
Author URI: blindtigers.com
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
if(version_compare(phpversion(), '5.2.0', '<'))
{
	//Only purpose of this function is to echo out the PHP version error
	function bcn_phpold()
	{
		printf('<div class="error"><p>' . __('Your PHP version is too old, please upgrade to a newer version. Your version is %s, Relatively Perfect requires %s', 'rel_perf') . '</p></div>', phpversion(), '5.2.0');
	}
	//If we are in the admin, let's print a warning then return
	if(is_admin())
	{
		add_action('admin_notices', 'btrp_phpold');
	}
	return;
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
	protected $opt = array();
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
			wp_die(__('Insufficient privileges to proceed.', $this->identifier));
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
			}
			//Save the passed in opts to the object's option array
			$this->opt = $opts;
		}
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
			'<a title="' . __('Go to the Relatively Perfect online documentation', 'rel_perf') . '" href="http://urlhere">', '</a>');
	}
	/**
	 * admin_head
	 *
	 * Adds in the JavaScript and CSS for the tabs in the adminsitrative 
	 * interface
	 * 
	 */
	function admin_styles()
	{
		wp_enqueue_style('mtekk_admin_tabs');
	}
	function admin_scripts()
	{
		//Enqueue the admin tabs javascript
		wp_enqueue_script('mtekk_admin_tabs');
		//Load the translations for the tabs
		wp_localize_script('mtekk_admin_tabs', 'objectL10n', array(
			'mtad_import' => __('Import', $this->identifier),
			'mtad_export' => __('Export', $this->identifier),
			'mtad_reset' => __('Reset', $this->identifier),
		));
	}
	function admin_head()
	{	
	
	}
	/**
	 * admin_page
	 * 
	 * The administrative page for Relatively Perfect
	 * 
	 */
	function admin_page()
	{
		global $wp_taxonomies;
		$this->security();
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
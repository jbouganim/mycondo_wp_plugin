<?php
/**
 * MyCondoApp
 *
 * @wordpress-plugin
 * Plugin Name:       MyCondo Wordpress
 * Description:       Manage your condo from Wordpress.
 * Version:           0.1
 * Author:            Jonathan Bouganim 
 * License:           GPL-2.0+
 * Text Domain:       MYCONDO_LOCALE
 * @since             0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once(dirname(__FILE__) . "/config.php");

require_once(MYCONDO_LIB . '/post_expiration/post_expiration.php');
//require_once(MYCONDO_LIB . '/remove_branding.php');

//Load everything admin-related, show metabox...
include(MYCONDO_INC."/synonym-taxonomy.php");
include(MYCONDO_INC."/routine_post_type.php");
include(MYCONDO_INC."/mycondo.php");
include(MYCONDO_INC."/mycondo-admin.php");
include(MYCONDO_INC."/mycondo-api.php");
include(MYCONDO_INC."/mycondo-api-methods.php");
include(MYCONDO_INC."/mycondo-moods.php");

//Components
include(MYCONDO_COMPONENTS."/component.php");
include(MYCONDO_COMPONENTS."/buzzer.php");
include(MYCONDO_COMPONENTS."/music.php");
include(MYCONDO_COMPONENTS."/lights.php");
include(MYCONDO_COMPONENTS."/thermostat.php");


//include(MYCONDO_INC."/condo-wp_cli.php");
//include(MYCONDO_INC."/mycondo-translations.php");

// init activation hook
register_activation_hook(__FILE__, 'mycondo_plugin_install');
register_deactivation_hook(__FILE__, 'mycondo_plugin_uninstall');

// Run default settings on install to ensure we don't throw any errors
function mycondo_plugin_install(){
	// Set the defaults if we have nothing set
	$settings = get_option( MyCondo_Admin::SLUG );
	if ($settings != false || ! isset($_GET['mycondo-reset-settings']) )
		return;

	$new_settings = '';
	$new_settings = json_decode($new_settings, true);

	$admin = new MyCondo_Admin();
	$admin->set_options($new_settings);

	flush_rewrite_rules( true );
}

// Run default settings on uninstall to remove any default settings
function mycondo_plugin_uninstall(){
	flush_rewrite_rules( true );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 */
function run_mycondo() {

	$plugin = new MyCondo();
	$plugin->run();

}
run_mycondo();

?>

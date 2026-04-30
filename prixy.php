<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://prixy.io
 * @since             1.0.0
 * @package           Prixy
 *
 * @wordpress-plugin
 * Plugin Name:       Prixy - Real-Time Exchange Rate Updater for WooCommerce
 * Plugin URI:       https://prixy.io
 * Description:       Real-Time Exchange Rate Updater for WooCommerce. Automatically adjust your WooCommerce product prices using the latest dollar exchange values.
 * Version:           1.0.0
 * Author:            Prixy Team
 * Author URI:       https://prixy.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prixy
 * Domain Path:       /languages
 */

require_once plugin_dir_path( __FILE__ ) . 'includes/class-prixy-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-prixy-deactivator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-prixy.php';

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-prixy-activator.php
 */
function activate_prixy() {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-prixy-deactivator.php
 */
function deactivate_prixy() {
	Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_prixy' );
register_deactivation_hook( __FILE__, 'deactivate_prixy' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_prixy() {

	define( 'PRIXY_VERSION', '1.0.0' );
	define( 'PRIXY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'PRIXY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

	$plugin = new Prixy();
	$plugin->run();

	// Exponer el container globalmente para que Cron::run_cron() pueda acceder
	// sin reconstruirlo si ya está inicializado en el request actual.
	global $prixy_container;
	$prixy_container = $plugin->get_container();
}
run_prixy();
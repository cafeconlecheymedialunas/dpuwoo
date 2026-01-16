<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://github.com/cafeconlecheymedialunas
 * @since             1.0.0
 * @package           Dpuwoo
 *
 * @wordpress-plugin
 * Plugin Name:       Dolar Price Updater for WooCommerce
 * Plugin URI:        https://https://github.com/cafeconlecheymedialunas/Dolar-Price-Updater-for-WooCommerce
 * Description:       Automatically adjust your WooCommerce product prices using the latest dollar exchange values. Apply updates globally, by category, or by product, with smart controls to prevent pricing errors.
 * Version:           1.0.0
 * Author:            Mauro Gaitan
 * Author URI:        https://https://github.com/cafeconlecheymedialunas/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dpuwoo
 * Domain Path:       /languages
 */

require_once plugin_dir_path( __FILE__ ) . 'includes/class-dpuwoo-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dpuwoo-deactivator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dpuwoo.php';

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-dpuwoo-activator.php
 */
function activate_dpuwoo() {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-dpuwoo-deactivator.php
 */
function deactivate_dpuwoo() {
	Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_dpuwoo' );
register_deactivation_hook( __FILE__, 'deactivate_dpuwoo' );

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
function run_dpuwoo() {

	define( 'DPUWOO_VERSION', '1.0.0' );
	define( 'DPUWOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'DPUWOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

	$plugin = new Dpuwoo();
	$plugin->run();

}
run_dpuwoo();
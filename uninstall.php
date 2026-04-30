<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://https://github.com/cafeconlecheymedialunas
 * @since      1.0.0
 *
 * @package    Dpuwoo
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// COMPLETE RESET ON UNINSTALL
function dpuwoo_uninstall_cleanup()
{
	global $wpdb;
	
	// 1. DELETE ALL PLUGIN OPTIONS
	delete_option('dpuwoo_settings');
	delete_option('dpuwoo_reference_currency');
	delete_option('dpuwoo_last_dollar_value');
	delete_option('dpuwoo_initial_setup_done');
	delete_option('dpuwoo_admin_notice');
	// Note: baseline_dollar_value is deprecated and no longer used
	delete_option('dpuwoo_auto_detection_date');
	
	// 2. REMOVE ALL PRODUCT USD PRICE METADATA
	$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_prixy_original_price_usd'");
	
	// 3. REMOVE BASELINE TABLE
	$baseline_table = $wpdb->prefix . 'dpuwoo_baselines';
	$wpdb->query("DROP TABLE IF EXISTS {$baseline_table}");
	
	// 3. CLEAR SCHEDULED HOOKS
	wp_clear_scheduled_hook('dpuwoo_do_update');
	
	// 4. DROP PLUGIN TABLES (optional - uncomment if you want to remove all data)
	/*
	$tables = [
		$wpdb->prefix . 'dpuwoo_runs',
		$wpdb->prefix . 'dpuwoo_run_items'
	];
	
	foreach ($tables as $table) {
		$wpdb->query("DROP TABLE IF EXISTS {$table}");
	}
	*/
	
	error_log('DPUWoo: Complete uninstall cleanup performed');
}

dpuwoo_uninstall_cleanup();
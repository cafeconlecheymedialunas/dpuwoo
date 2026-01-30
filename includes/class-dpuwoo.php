<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Dpuwoo
 * @subpackage Dpuwoo/includes
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class Dpuwoo {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Dpuwoo_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'DPUWOO_VERSION' ) ) {
			$this->version = DPUWOO_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'dpuwoo';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Dpuwoo_Loader. Orchestrates the hooks of the plugin.
	 * - Dpuwoo_i18n. Defines internationalization functionality.
	 * - Dpuwoo_Admin. Defines all hooks for the admin area.
	 * - Dpuwoo_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dpuwoo-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-dpuwoo-public.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-log-repository.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-product-repository.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-trait-request.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-currencyapi-provider.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-exhangerateapi-provider.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-dolarapi-provider.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-api.php';
	
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-price-calculator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-price-updater.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-fallback.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-logger.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-cron.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-admin-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-ajax-manager.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/functions.php';
		$this->loader = new Loader();


	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Dpuwoo_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		$this->loader->add_action('admin_menu', $plugin_admin, 'register_menu');
		
		$plugin_setting = new Admin_Settings();
		$this->loader->add_action('admin_init', $plugin_setting, 'register_settings');
		
		// === INSTANCIAR Ajax_Manager COMO LAS DEMÁS CLASES ===
		$ajax_manager = new Ajax_Manager();
		
		// Registrar métodos de AJAX
		$this->loader->add_action('wp_ajax_dpuwoo_simulate_batch', $ajax_manager, 'ajax_simulate_batch');
		$this->loader->add_action('wp_ajax_dpuwoo_update_batch', $ajax_manager, 'ajax_update_batch');
		$this->loader->add_action('wp_ajax_dpuwoo_update_now', $ajax_manager, 'ajax_update_now');
		$this->loader->add_action('wp_ajax_dpuwoo_get_runs', $ajax_manager, 'ajax_get_runs');
		$this->loader->add_action('wp_ajax_dpuwoo_get_run_items', $ajax_manager, 'ajax_get_run_items');
		$this->loader->add_action('wp_ajax_dpuwoo_revert_item', $ajax_manager, 'ajax_revert_item');
		$this->loader->add_action('wp_ajax_dpuwoo_revert_run', $ajax_manager, 'ajax_revert_run');
		$this->loader->add_action('wp_ajax_dpuwoo_get_currencies', $ajax_manager, 'ajax_get_currencies');
		// Removed wp_ajax_dpuwoo_save_settings to use traditional form submission instead
	}
	

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Dpuwoo_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );


	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Dpuwoo_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

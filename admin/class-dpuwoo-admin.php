<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Dpuwoo
 * @subpackage Dpuwoo/admin
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dpuwoo_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dpuwoo_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/dpuwoo-main.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	
	public function enqueue_scripts($hook)
	{
		if (strpos($hook, 'dpuwoo_') === false) return;

		// Tailwind CSS desde CDN
		wp_enqueue_script(
			'tailwind', 
			'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', 
			null, 
			true
		);

		// Registrar SweetAlert2
		wp_register_script(
			'sweetalert2',
			'https://cdn.jsdelivr.net/npm/sweetalert2@11',
			array(), 
			null,
			true
		);

		wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true);
    	wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css', array(), '4.1.0-rc.0');

		// Script de tabs PRIMERO (sin dependencias complejas)
		wp_enqueue_script(
			$this->plugin_name . '-tabs',
			plugin_dir_url(__FILE__) . 'js/dpuwoo-tabs.js',
			array('jquery'), // Solo jQuery
			$this->version,
			false
		);

		// Script principal SEGUNDO (depende de tabs)
		wp_enqueue_script(
			$this->plugin_name . '-main',
			plugin_dir_url(__FILE__) . 'js/dpuwoo-main.js',
			array('jquery', 'sweetalert2', $this->plugin_name . '-tabs'),
			$this->version,
			false
		);

		// Localizar las variables AJAX en el script principal
		$opts      = get_option('dpuwoo_settings', []);
		$ajax_data = array(
			'ajax_url'      => admin_url('admin-ajax.php'),
			'nonce'         => wp_create_nonce('dpuwoo_ajax_nonce'),
			'threshold_min' => floatval($opts['threshold']     ?? 0),
			'threshold_max' => floatval($opts['threshold_max'] ?? 0),
		);

		wp_localize_script($this->plugin_name . '-main', 'dpuwoo_ajax', $ajax_data);

		// Los otros scripts que dependen del principal
		wp_enqueue_script(
			$this->plugin_name . '-logs',
			plugin_dir_url(__FILE__) . 'js/dpuwoo-logs.js',
			array('jquery', 'sweetalert2', $this->plugin_name . '-main'),
			$this->version,
			false
		);

		wp_enqueue_script(
			$this->plugin_name . '-simulation',
			plugin_dir_url(__FILE__) . 'js/dpuwoo-simulation.js',
			array('jquery', 'sweetalert2', $this->plugin_name . '-main'),
			$this->version,
			false
		);

		wp_enqueue_script(
			$this->plugin_name . '-update',
			plugin_dir_url(__FILE__) . 'js/dpuwoo-update.js',
			array('jquery', 'sweetalert2', $this->plugin_name . '-main'),
			$this->version,
			false
		);

		
		wp_enqueue_script(
			$this->plugin_name . '-currencies',
			plugin_dir_url(__FILE__) . 'js/currencies.js',
			array('jquery', 'select2', $this->plugin_name . '-main'),
			$this->version,
			false
		);

		wp_enqueue_script(
			$this->plugin_name . '-api-keys',
			plugin_dir_url(__FILE__) . 'js/dpuwoo-api-keys.js',
			array('jquery', $this->plugin_name . '-main'),
			$this->version . '-' . time(), // Cache busting
			false
		);

		wp_localize_script($this->plugin_name . '-currencies', 'dpuwoo_ajax', [
			'ajax_url'      => admin_url('admin-ajax.php'),
			'nonce'         => wp_create_nonce('dpuwoo_ajax_nonce'),
			'base_currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
		]);

		// CSS
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/dpuwoo-main.css');

		// ── Dashboard Overview: Chart.js + dedicated script ────────────────────────
		if ($hook === 'toplevel_page_dpuwoo_settings') {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
				[],
				'4',
				true
			);
			wp_enqueue_script(
				$this->plugin_name . '-dashboard-overview',
				plugin_dir_url(__FILE__) . 'js/dpuwoo-dashboard-overview.js',
				['jquery', 'chartjs', $this->plugin_name . '-main'],
				$this->version,
				true
			);
			wp_localize_script($this->plugin_name . '-dashboard-overview', 'dpuwoo_dashboard', [
				'ajax_url'      => admin_url('admin-ajax.php'),
				'nonce'         => wp_create_nonce('dpuwoo_ajax_nonce'),
				'product_count' => Log_Repository::get_instance()->count_all_products(),
				'logs_url'      => admin_url('admin.php?page=dpuwoo_logs'),
				'settings_url'  => admin_url('admin.php?page=dpuwoo_configuration'),
				'auto_url'      => admin_url('admin.php?page=dpuwoo_automation'),
				'manual_url'    => admin_url('admin.php?page=dpuwoo_dashboard'),
			]);
		}
	}



	public static function register_menu()
	{
		add_menu_page(
			'Dollar Sync - Dashboard',
			'Dollar Sync',
			'manage_options',
			'dpuwoo_settings',
			[__CLASS__, 'render_overview'],
			'dashicons-admin-site',
			60
		);

		// Primer submenú (mismo slug que el padre) = Dashboard Overview
		add_submenu_page(
			'dpuwoo_settings',
			'Dollar Sync - Dashboard',
			'Dashboard',
			'manage_options',
			'dpuwoo_settings',
			[__CLASS__, 'render_overview']
		);

		add_submenu_page(
			'dpuwoo_settings',
			'Dollar Sync - Configuración',
			'Configuración',
			'manage_options',
			'dpuwoo_configuration',
			[__CLASS__, 'render_settings']
		);

		add_submenu_page(
			'dpuwoo_settings',
			'Dollar Sync - Automatización',
			'Automatización',
			'manage_options',
			'dpuwoo_automation',
			[__CLASS__, 'render_automation']
		);

		add_submenu_page(
			'dpuwoo_settings',
			'Dollar Sync - Actualización Manual',
			'Actualización Manual',
			'manage_options',
			'dpuwoo_dashboard',
			[__CLASS__, 'render_dashboard']
		);

		add_submenu_page(
			'dpuwoo_settings',
			'Dollar Sync - Historial',
			'Historial',
			'manage_options',
			'dpuwoo_logs',
			[__CLASS__, 'render_logs']
		);
	}


	public static function render_overview()
	{
		include DPUWOO_PLUGIN_DIR . 'admin/partials/dpuwoo-main-dashboard.php';
	}

	public static function render_dashboard()
	{
		include DPUWOO_PLUGIN_DIR . 'admin/partials/dpuwoo-dashboard.php';
	}

	public static function render_settings()
	{
		include DPUWOO_PLUGIN_DIR . 'admin/partials/dpuwoo-settings.php';
	}

	public static function render_automation()
	{
		include DPUWOO_PLUGIN_DIR . 'admin/partials/dpuwoo-automation.php';
	}

	public static function render_logs()
	{
		include DPUWOO_PLUGIN_DIR . 'admin/partials/dpuwoo-logs.php';
	}
}

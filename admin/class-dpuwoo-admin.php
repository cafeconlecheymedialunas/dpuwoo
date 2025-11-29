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
		$ajax_data = array(
			'ajax_url' => admin_url('admin-ajax.php'), 
			'nonce' => wp_create_nonce('dpuwoo_nonce')
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

		// CSS
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/dpuwoo-main.css');
	}



	public static function register_menu()
	{
		add_menu_page(
			'Dollar Sync',
			'Dollar Sync',
			'manage_options',
			'dpuwoo_dashboard',
			[__CLASS__, 'render_dashboard'],
			'dashicons-admin-site',
			60
		);
	}


	public static function render_dashboard()
	{
		include DPUWOO_PLUGIN_DIR . 'admin/partials/dpuwoo-dashboard.php';
	}
}

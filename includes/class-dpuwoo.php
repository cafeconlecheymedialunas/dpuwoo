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
	 * Contenedor de Inyección de Dependencias.
	 * Construido una sola vez durante el bootstrap del plugin.
	 *
	 * @var Dpuwoo_Container
	 */
	private Dpuwoo_Container $container;

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
		$this->container = Dpuwoo_Container::build();
		add_filter('cron_schedules', [Cron::class, 'register_schedule']);
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
		$base = plugin_dir_path( dirname( __FILE__ ) );

		// ── Core WordPress ─────────────────────────────────────────────────────────
		require_once $base . 'includes/class-dpuwoo-loader.php';
		require_once $base . 'includes/class-dpuwoo-i18n.php';

		// ── Admin & Public ─────────────────────────────────────────────────────────
		require_once $base . 'admin/class-dpuwoo-admin.php';
		require_once $base . 'public/class-dpuwoo-public.php';

		// ── Domain — Interfaces ────────────────────────────────────────────────────
		require_once $base . 'includes/domain/interfaces/interface-dpuwoo-price-rule.php';
		require_once $base . 'includes/domain/interfaces/interface-dpuwoo-api-provider.php';
		require_once $base . 'includes/domain/interfaces/interface-dpuwoo-product-repository.php';
		require_once $base . 'includes/domain/interfaces/interface-dpuwoo-log-repository.php';

		// ── Domain — Value Objects ─────────────────────────────────────────────────
		require_once $base . 'includes/domain/value-objects/class-dpuwoo-exchange-rate.php';
		require_once $base . 'includes/domain/value-objects/class-dpuwoo-price-context.php';
		require_once $base . 'includes/domain/value-objects/class-dpuwoo-calculation-result.php';
		require_once $base . 'includes/domain/value-objects/class-dpuwoo-batch-result.php';

		// ── Domain — Policies ──────────────────────────────────────────────────────
		require_once $base . 'includes/domain/policies/class-dpuwoo-threshold-policy.php';

		// ── Domain — Rules (Strategy Pattern) ─────────────────────────────────────
		require_once $base . 'includes/domain/rules/class-dpuwoo-ratio-rule.php';
		require_once $base . 'includes/domain/rules/class-dpuwoo-margin-rule.php';
		require_once $base . 'includes/domain/rules/class-dpuwoo-direction-rule.php';
		require_once $base . 'includes/domain/rules/class-dpuwoo-rounding-rule.php';
		require_once $base . 'includes/domain/rules/class-dpuwoo-category-exclusion-rule.php';

		// ── Infrastructure — Repositorios y API ───────────────────────────────────
		require_once $base . 'includes/class-dpuwoo-log-repository.php';
		require_once $base . 'includes/class-dpuwoo-product-repository.php';
		require_once $base . 'includes/class-dpuwoo-trait-request.php';
		require_once $base . 'includes/class-dpuwoo-currencyapi-provider.php';
		require_once $base . 'includes/class-dpuwoo-exhangerateapi-provider.php';
		require_once $base . 'includes/class-dpuwoo-dolarapi-provider.php';
		require_once $base . 'includes/infrastructure/class-dpuwoo-api-provider-factory.php';
		require_once $base . 'includes/class-dpuwoo-api.php';
		require_once $base . 'includes/infrastructure/class-dpuwoo-settings-repository.php';
		require_once $base . 'includes/class-dpuwoo-logger.php';

		// ── Application — Servicios ────────────────────────────────────────────────
		require_once $base . 'includes/application/services/class-dpuwoo-price-calculation-engine.php';
		require_once $base . 'includes/application/services/class-dpuwoo-batch-processor.php';

		// ── Application — Commands (DTOs) ──────────────────────────────────────────
		require_once $base . 'includes/application/commands/class-dpuwoo-update-prices-command.php';
		require_once $base . 'includes/application/commands/class-dpuwoo-rollback-item-command.php';
		require_once $base . 'includes/application/commands/class-dpuwoo-rollback-run-command.php';

		// ── Application — Handlers ─────────────────────────────────────────────────
		require_once $base . 'includes/application/handlers/class-dpuwoo-update-prices-handler.php';
		require_once $base . 'includes/application/handlers/class-dpuwoo-rollback-handler.php';

		// ── Application — Command Bus ──────────────────────────────────────────────
		require_once $base . 'includes/application/class-dpuwoo-command-bus.php';

		// ── Presentation ───────────────────────────────────────────────────────────
		require_once $base . 'includes/presentation/class-dpuwoo-ajax-controller.php';

		// ── DI Container ───────────────────────────────────────────────────────────
		require_once $base . 'includes/class-dpuwoo-container.php';

		// ── Miscelánea ─────────────────────────────────────────────────────────────
		require_once $base . 'includes/class-dpuwoo-fallback.php';
		require_once $base . 'includes/class-dpuwoo-cron.php';
		require_once $base . 'includes/class-dpuwoo-admin-settings.php';
		require_once $base . 'includes/helpers/functions.php';

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
		$this->loader->add_action('admin_init', Activator::class, 'maybe_upgrade');

		// Re-agendar (o desagendar) el cron al guardar settings
		$this->loader->add_action('update_option_dpuwoo_settings', Cron::class, 'schedule');
		$this->loader->add_action(Cron::HOOK, Cron::class, 'run_cron');

		// ── AJAX handlers — Capa de Presentación (via DI Container) ───────────────
		// Ajax_Controller recibe sus dependencias inyectadas por el Container;
		// no instancia nada directamente. Los action names se mantienen idénticos
		// para compatibilidad con el frontend JavaScript existente.
		/** @var Ajax_Controller $ajax */
		$ajax = $this->container->get('ajax_controller');

		$this->loader->add_action('wp_ajax_dpuwoo_simulate_batch',    $ajax, 'handle_simulate_batch');
		$this->loader->add_action('wp_ajax_dpuwoo_update_batch',      $ajax, 'handle_update_batch');
		$this->loader->add_action('wp_ajax_dpuwoo_get_runs',          $ajax, 'handle_get_runs');
		$this->loader->add_action('wp_ajax_dpuwoo_get_run_items',     $ajax, 'handle_get_run_items');
		$this->loader->add_action('wp_ajax_dpuwoo_revert_item',       $ajax, 'handle_revert_item');
		$this->loader->add_action('wp_ajax_dpuwoo_revert_run',        $ajax, 'handle_revert_run');
		$this->loader->add_action('wp_ajax_dpuwoo_get_currencies',    $ajax, 'handle_get_currencies');
		$this->loader->add_action('wp_ajax_dpuwoo_get_current_rate',  $ajax, 'handle_get_current_rate');
		$this->loader->add_action('wp_ajax_dpuwoo_get_providers_info',$ajax, 'handle_get_providers_info');
		$this->loader->add_action('wp_ajax_dpuwoo_test_api_connection',$ajax, 'handle_test_api_connection');
		$this->loader->add_action('wp_ajax_dpuwoo_initialize_baseline', $ajax, 'handle_initialize_baseline');
		$this->loader->add_action('wp_ajax_dpuwoo_test_api',            $ajax, 'handle_test_api');
		$this->loader->add_action('wp_ajax_dpuwoo_get_rates',         $ajax, 'handle_get_rates');
		$this->loader->add_action('wp_ajax_dpuwoo_get_dashboard_stats', $ajax, 'handle_get_dashboard_stats');
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

	/**
	 * Retorna el DI Container bootstrapped del plugin.
	 * Usado para exponer el container al Cron y a tests de integración.
	 *
	 * @return Dpuwoo_Container
	 */
	public function get_container(): Dpuwoo_Container {
		return $this->container;
	}

}

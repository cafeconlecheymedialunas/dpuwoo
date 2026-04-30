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
 * @package    Prixy
 * @subpackage Prixy/includes
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class Prixy {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Prixy_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Contenedor de Inyección de Dependencias.
	 * Construido una sola vez durante el bootstrap del plugin.
	 *
	 * @var Prixy_Container
	 */
	private Prixy_Container $container;

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
		if ( defined( 'PRIXY_VERSION' ) ) {
			$this->version = PRIXY_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'prixy';

		$this->load_dependencies();
		$this->container = Prixy_Container::build();
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
	 * - Prixy_Loader. Orchestrates the hooks of the plugin.
	 * - Prixy_i18n. Defines internationalization functionality.
	 * - Prixy_Admin. Defines all hooks for the admin area.
	 * - Prixy_Public. Defines all hooks for the public side of the site.
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
		require_once $base . 'includes/class-prixy-loader.php';
		require_once $base . 'includes/class-prixy-i18n.php';

		// ── Admin & Public ─────────────────────────────────────────────────────────
		require_once $base . 'admin/class-prixy-admin.php';
		require_once $base . 'public/class-prixy-public.php';

		// ── Domain — Interfaces ────────────────────────────────────────────────────
		require_once $base . 'includes/domain/interfaces/interface-prixy-price-rule.php';
		require_once $base . 'includes/domain/interfaces/interface-prixy-api-provider.php';
		require_once $base . 'includes/domain/interfaces/interface-prixy-product-repository.php';
		require_once $base . 'includes/domain/interfaces/interface-prixy-log-repository.php';

		// ── Domain — Value Objects ─────────────────────────────────────────────────
		require_once $base . 'includes/domain/value-objects/class-prixy-exchange-rate.php';
		require_once $base . 'includes/domain/value-objects/class-prixy-price-context.php';
		require_once $base . 'includes/domain/value-objects/class-prixy-calculation-result.php';
		require_once $base . 'includes/domain/value-objects/class-prixy-batch-result.php';

		// ── Domain — Policies ──────────────────────────────────────────────────────
		require_once $base . 'includes/domain/policies/class-prixy-threshold-policy.php';

		// ── Domain — Rules (Strategy Pattern) ─────────────────────────────────────
		require_once $base . 'includes/domain/rules/class-prixy-ratio-rule.php';
		require_once $base . 'includes/domain/rules/class-prixy-margin-rule.php';
		require_once $base . 'includes/domain/rules/class-prixy-direction-rule.php';
		require_once $base . 'includes/domain/rules/class-prixy-rounding-rule.php';
		require_once $base . 'includes/domain/rules/class-prixy-category-exclusion-rule.php';

		// ── Infrastructure — Base classes para API ───────────────────────────────
		require_once $base . 'includes/class-prixy-trait-request.php';

		// ── Infrastructure — Repositorios y API ───────────────────────────────────
		require_once $base . 'includes/infrastructure/repositories/class-prixy-log-repository.php';
		require_once $base . 'includes/infrastructure/repositories/class-prixy-product-repository.php';
		require_once $base . 'includes/infrastructure/api/class-prixy-api-response-formatter.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-dolarapi-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-jsdelivr-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-cryptoprice-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-currencyapi-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-exhangerateapi-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-moneyconvert-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-hexarate-provider.php';
		require_once $base . 'includes/infrastructure/api/providers/class-prixy-foreignrate-provider.php';
		require_once $base . 'includes/infrastructure/class-prixy-api-provider-factory.php';
		require_once $base . 'includes/infrastructure/api/class-prixy-api-client.php';
		require_once $base . 'includes/infrastructure/class-prixy-settings-repository.php';
		require_once $base . 'includes/infrastructure/services/class-prixy-email-notifier.php';
		require_once $base . 'includes/infrastructure/services/class-prixy-multi-currency-manager.php';
		require_once $base . 'includes/class-prixy-logger.php';

		// ── Application — Servicios ────────────────────────────────────────────────
		require_once $base . 'includes/application/services/class-prixy-price-calculation-engine.php';
		require_once $base . 'includes/application/services/class-prixy-batch-processor.php';

		// ── Application — Commands (DTOs) ──────────────────────────────────────────
		require_once $base . 'includes/application/commands/class-prixy-update-prices-command.php';
		require_once $base . 'includes/application/commands/class-prixy-rollback-item-command.php';
		require_once $base . 'includes/application/commands/class-prixy-rollback-run-command.php';

		// ── Application — Handlers ─────────────────────────────────────────────────
		require_once $base . 'includes/application/handlers/class-prixy-update-prices-handler.php';
		require_once $base . 'includes/application/handlers/class-prixy-rollback-handler.php';

		// ── Application — Command Bus ──────────────────────────────────────────────
		require_once $base . 'includes/application/class-prixy-command-bus.php';

		// ── Presentation ───────────────────────────────────────────────────────────
		require_once $base . 'includes/presentation/class-prixy-ajax-controller.php';

		// ── DI Container ───────────────────────────────────────────────────────────
		require_once $base . 'includes/class-prixy-container.php';

		// ── Miscelánea ─────────────────────────────────────────────────────────────
		require_once $base . 'includes/class-prixy-fallback.php';
		require_once $base . 'includes/class-prixy-cron.php';
		require_once $base . 'includes/class-prixy-admin-settings.php';
		require_once $base . 'includes/helpers/functions.php';

		$this->loader = new Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Prixy_i18n class in order to set the domain and to register the hook
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
		$this->loader->add_action('admin_menu',     $plugin_admin, 'register_menu');
		$this->loader->add_action('admin_init',     $plugin_admin, 'handle_activation_redirect');
		$this->loader->add_action('admin_notices',  $plugin_admin, 'display_admin_notices');

		$plugin_setting = new Admin_Settings();
		$this->loader->add_action('admin_init', $plugin_setting, 'register_settings');
		$this->loader->add_action('admin_init', Activator::class, 'maybe_upgrade');

		// Re-agendar (o desagendar) el cron al guardar settings
		$this->loader->add_action('update_option_prixy_settings', Cron::class, 'schedule');
		$this->loader->add_action(Cron::HOOK, Cron::class, 'run_cron');

		// ── AJAX handlers — Capa de Presentación (via DI Container) ───────────────
		// Ajax_Controller recibe sus dependencias inyectadas por el Container;
		// no instancia nada directamente. Los action names se mantienen idénticos
		// para compatibilidad con el frontend JavaScript existente.
		/** @var Ajax_Controller $ajax */
		$ajax = $this->container->get('ajax_controller');

		$this->loader->add_action('wp_ajax_prixy_simulate_batch',    $ajax, 'handle_simulate_batch');
		$this->loader->add_action('wp_ajax_prixy_update_batch',      $ajax, 'handle_update_batch');
		$this->loader->add_action('wp_ajax_prixy_get_runs',          $ajax, 'handle_get_runs');
		$this->loader->add_action('wp_ajax_prixy_get_run_items',     $ajax, 'handle_get_run_items');
		$this->loader->add_action('wp_ajax_prixy_revert_item',       $ajax, 'handle_revert_item');
		$this->loader->add_action('wp_ajax_prixy_revert_run',        $ajax, 'handle_revert_run');
		$this->loader->add_action('wp_ajax_prixy_get_currencies',    $ajax, 'handle_get_currencies');
		$this->loader->add_action('wp_ajax_prixy_get_current_rate',  $ajax, 'handle_get_current_rate');
		$this->loader->add_action('wp_ajax_prixy_get_providers_info',$ajax, 'handle_get_providers_info');
		$this->loader->add_action('wp_ajax_prixy_test_api_connection',$ajax, 'handle_test_api_connection');
		$this->loader->add_action('wp_ajax_prixy_initialize_baseline', $ajax, 'handle_initialize_baseline');
		$this->loader->add_action('wp_ajax_prixy_test_api',            $ajax, 'handle_test_api');
		$this->loader->add_action('wp_ajax_prixy_get_rates',         $ajax, 'handle_get_rates');
		$this->loader->add_action('wp_ajax_prixy_get_dashboard_stats',  $ajax, 'handle_get_dashboard_stats');
		$this->loader->add_action('wp_ajax_prixy_get_setup_progress',   $ajax, 'handle_get_setup_progress');
		$this->loader->add_action('wp_ajax_prixy_save_origin_rate',      $ajax, 'handle_save_origin_rate');
		$this->loader->add_action('wp_ajax_prixy_first_setup_batch',    $ajax, 'handle_first_setup_batch');
		$this->loader->add_action('wp_ajax_prixy_preview_products',     $ajax, 'handle_preview_products');
	}
	

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Prixy_Public( $this->get_plugin_name(), $this->get_version() );

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
	 * @return    Prixy_Loader    Orchestrates the hooks of the plugin.
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
	 * @return Prixy_Container
	 */
	public function get_container(): Prixy_Container {
		return $this->container;
	}

}

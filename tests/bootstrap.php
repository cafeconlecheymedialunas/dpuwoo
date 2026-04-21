<?php
/**
 * PHPUnit Bootstrap file for DPUWoo tests.
 * Loads all plugin classes without WordPress dependencies.
 */

define('ABSPATH', '/tmp/wordpress/');
define('WPINC', 'wp-includes');
define('DB_NAME', 'test_db');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('DPUWOO_VERSION', '1.0.0');

$plugin_dir = __DIR__ . '/../';
$includes_dir = $plugin_dir . 'includes/';

require_once $includes_dir . 'class-dpuwoo-loader.php';
require_once $includes_dir . 'class-dpuwoo-i18n.php';
require_once $includes_dir . 'domain/interfaces/interface-dpuwoo-price-rule.php';
require_once $includes_dir . 'domain/interfaces/interface-dpuwoo-api-provider.php';
require_once $includes_dir . 'domain/interfaces/interface-dpuwoo-product-repository.php';
require_once $includes_dir . 'domain/interfaces/interface-dpuwoo-log-repository.php';
require_once $includes_dir . 'domain/value-objects/class-dpuwoo-exchange-rate.php';
require_once $includes_dir . 'domain/value-objects/class-dpuwoo-price-context.php';
require_once $includes_dir . 'domain/value-objects/class-dpuwoo-calculation-result.php';
require_once $includes_dir . 'domain/value-objects/class-dpuwoo-batch-result.php';
require_once $includes_dir . 'domain/policies/class-dpuwoo-threshold-policy.php';
require_once $includes_dir . 'domain/rules/class-dpuwoo-ratio-rule.php';
require_once $includes_dir . 'domain/rules/class-dpuwoo-margin-rule.php';
require_once $includes_dir . 'domain/rules/class-dpuwoo-direction-rule.php';
require_once $includes_dir . 'domain/rules/class-dpuwoo-rounding-rule.php';
require_once $includes_dir . 'domain/rules/class-dpuwoo-category-exclusion-rule.php';
require_once $includes_dir . 'infrastructure/repositories/class-dpuwoo-log-repository.php';
require_once $includes_dir . 'infrastructure/repositories/class-dpuwoo-product-repository.php';
require_once $includes_dir . 'class-dpuwoo-trait-request.php';
require_once $includes_dir . 'infrastructure/api/class-dpuwoo-api-response-formatter.php';
require_once $includes_dir . 'infrastructure/api/providers/class-dpuwoo-currencyapi-provider.php';
require_once $includes_dir . 'infrastructure/api/providers/class-dpuwoo-exhangerateapi-provider.php';
require_once $includes_dir . 'infrastructure/api/providers/class-dpuwoo-dolarapi-provider.php';
require_once $includes_dir . 'infrastructure/class-dpuwoo-api-provider-factory.php';
require_once $includes_dir . 'infrastructure/api/class-dpuwoo-api-client.php';
require_once $includes_dir . 'infrastructure/class-dpuwoo-settings-repository.php';
require_once $includes_dir . 'class-dpuwoo-logger.php';
require_once $includes_dir . 'application/services/class-dpuwoo-price-calculation-engine.php';
require_once $includes_dir . 'application/services/class-dpuwoo-batch-processor.php';
require_once $includes_dir . 'application/commands/class-dpuwoo-update-prices-command.php';
require_once $includes_dir . 'application/commands/class-dpuwoo-rollback-item-command.php';
require_once $includes_dir . 'application/commands/class-dpuwoo-rollback-run-command.php';
require_once $includes_dir . 'application/handlers/class-dpuwoo-update-prices-handler.php';
require_once $includes_dir . 'application/handlers/class-dpuwoo-rollback-handler.php';
require_once $includes_dir . 'application/class-dpuwoo-command-bus.php';
require_once $includes_dir . 'class-dpuwoo-cron.php';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

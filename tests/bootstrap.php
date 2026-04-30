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

require_once $includes_dir . 'class-prixy-loader.php';
require_once $includes_dir . 'class-prixy-i18n.php';
require_once $includes_dir . 'domain/interfaces/interface-prixy-price-rule.php';
require_once $includes_dir . 'domain/interfaces/interface-prixy-api-provider.php';
require_once $includes_dir . 'domain/interfaces/interface-prixy-product-repository.php';
require_once $includes_dir . 'domain/interfaces/interface-prixy-log-repository.php';
require_once $includes_dir . 'domain/value-objects/class-prixy-exchange-rate.php';
require_once $includes_dir . 'domain/value-objects/class-prixy-price-context.php';
require_once $includes_dir . 'domain/value-objects/class-prixy-calculation-result.php';
require_once $includes_dir . 'domain/value-objects/class-prixy-batch-result.php';
require_once $includes_dir . 'domain/policies/class-prixy-threshold-policy.php';
require_once $includes_dir . 'domain/rules/class-prixy-ratio-rule.php';
require_once $includes_dir . 'domain/rules/class-prixy-margin-rule.php';
require_once $includes_dir . 'domain/rules/class-prixy-direction-rule.php';
require_once $includes_dir . 'domain/rules/class-prixy-rounding-rule.php';
require_once $includes_dir . 'domain/rules/class-prixy-category-exclusion-rule.php';
require_once $includes_dir . 'infrastructure/repositories/class-prixy-log-repository.php';
require_once $includes_dir . 'infrastructure/repositories/class-prixy-product-repository.php';
require_once $includes_dir . 'class-prixy-trait-request.php';
require_once $includes_dir . 'infrastructure/api/class-prixy-api-response-formatter.php';
require_once $includes_dir . 'infrastructure/api/providers/class-prixy-currencyapi-provider.php';
require_once $includes_dir . 'infrastructure/api/providers/class-prixy-exhangerateapi-provider.php';
require_once $includes_dir . 'infrastructure/api/providers/class-prixy-dolarapi-provider.php';
require_once $includes_dir . 'infrastructure/class-prixy-api-provider-factory.php';
require_once $includes_dir . 'infrastructure/api/class-prixy-api-client.php';
require_once $includes_dir . 'infrastructure/class-prixy-settings-repository.php';
require_once $includes_dir . 'class-prixy-logger.php';
require_once $includes_dir . 'application/services/class-prixy-price-calculation-engine.php';
require_once $includes_dir . 'application/services/class-prixy-batch-processor.php';
require_once $includes_dir . 'application/commands/class-prixy-update-prices-command.php';
require_once $includes_dir . 'application/commands/class-prixy-rollback-item-command.php';
require_once $includes_dir . 'application/commands/class-prixy-rollback-run-command.php';
require_once $includes_dir . 'application/handlers/class-prixy-update-prices-handler.php';
require_once $includes_dir . 'application/handlers/class-prixy-rollback-handler.php';
require_once $includes_dir . 'application/class-prixy-command-bus.php';
require_once $includes_dir . 'class-prixy-cron.php';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

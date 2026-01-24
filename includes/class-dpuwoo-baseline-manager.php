<?php
if (!defined('ABSPATH')) exit;

/**
 * DPUWOO_Baseline_Manager
 * Manages the baseline dollar rates with a dedicated database table
 * This ensures reliable baseline storage and retrieval
 */
class DPUWOO_Baseline_Manager
{
    protected static $instance;
    private $table_name;
    
    public static function init()
    {
        error_log('DPUWoo Baseline Manager: Initializing');
        if (null === self::$instance) {
            error_log('DPUWoo Baseline Manager: Creating new instance');
            self::$instance = new self();
        }
        error_log('DPUWoo Baseline Manager: Returning instance');
        return self::$instance;
    }
    
    public static function get_instance()
    {
        error_log('DPUWoo Baseline Manager: Getting instance');
        return self::init();
    }
    
    private function __construct()
    {
        error_log('DPUWoo Baseline Manager: Constructor called');
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dpuwoo_baselines';
        error_log('DPUWoo Baseline Manager: Table name set to ' . $this->table_name);
    }
    
    /**
     * Create the baselines table if it doesn't exist
     */
    public function create_table()
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            baseline_type varchar(50) NOT NULL DEFAULT 'dollar',
            baseline_value decimal(10,4) NOT NULL,
            currency_code varchar(10) NOT NULL DEFAULT 'USD',
            source varchar(100) NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            notes text,
            PRIMARY KEY (id),
            KEY baseline_type (baseline_type),
            KEY date_created (date_created),
            KEY is_active (is_active)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert initial baseline if table is empty
        $this->ensure_initial_baseline();
    }
    
    /**
     * Ensure there's at least one active baseline in the system
     */
    private function ensure_initial_baseline()
    {
        global $wpdb;
        
        $existing_baseline = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE is_active = 1 AND baseline_type = 'dollar'"
        );
        
        if ($existing_baseline == 0) {
            $this->set_baseline('dollar', $this->fetch_current_dollar_rate(), 'USD', 'initial_setup');
        }
    }
    
    /**
     * Set or update a baseline value
     * @param string $type Type of baseline (dollar, euro, etc.)
     * @param float $value The baseline value
     * @param string $currency Currency code
     * @param string $source Source of the baseline
     * @param string $notes Optional notes
     * @return int|false Insert ID or false on failure
     */
    public function set_baseline($type, $value, $currency = 'USD', $source = 'manual', $notes = '')
    {
        global $wpdb;
        
        // Deactivate existing baselines of the same type
        $wpdb->update(
            $this->table_name,
            ['is_active' => 0],
            ['baseline_type' => $type, 'is_active' => 1],
            ['%d'],
            ['%s', '%d']
        );
        
        // Insert new baseline
        $result = $wpdb->insert(
            $this->table_name,
            [
                'baseline_type' => $type,
                'baseline_value' => $value,
                'currency_code' => $currency,
                'source' => $source,
                'notes' => $notes,
                'is_active' => 1
            ],
            ['%s', '%f', '%s', '%s', '%s', '%d']
        );
        
        if ($result !== false) {
            error_log("DPUWoo: Set new baseline - Type: {$type}, Value: {$value}, Source: {$source}");
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get the current active baseline value
     * @param string $type Type of baseline (default: 'dollar')
     * @return float|null Baseline value or null if not found
     */
    public function get_current_baseline($type = 'dollar')
    {
        error_log('DPUWoo Baseline Manager: Getting current baseline for type ' . $type);
        global $wpdb;
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT baseline_value FROM {$this->table_name} 
             WHERE baseline_type = %s AND is_active = 1 
             ORDER BY date_created DESC LIMIT 1",
            $type
        ));
        
        error_log('DPUWoo Baseline Manager: Raw value from DB: ' . var_export($value, true));
        
        $result = $value ? floatval($value) : null;
        error_log('DPUWoo Baseline Manager: Final result: ' . var_export($result, true));
        
        return $result;
    }
    
    /**
     * Get all baseline history for a type
     * @param string $type Type of baseline
     * @param int $limit Number of records to return
     * @return array Baseline history
     */
    public function get_baseline_history($type = 'dollar', $limit = 50)
    {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE baseline_type = %s 
             ORDER BY date_created DESC 
             LIMIT %d",
            $type,
            $limit
        ));
    }
    
    /**
     * Fetch current dollar rate from API
     * @return float Current dollar rate or 1.0 on failure
     */
    private function fetch_current_dollar_rate()
    {
        if (!function_exists('wp_remote_get')) {
            return 1.0;
        }
        
        $url = "https://dolarapi.com/v1/dolares/oficial";
        $args = ['timeout' => 15, 'sslverify' => false];
        
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            error_log('DPUWoo Baseline Manager - Error fetching dollar: ' . $res->get_error_message());
            return 1.0;
        }
        
        $body = json_decode(wp_remote_retrieve_body($res), true);
        
        if (!isset($body['venta'])) {
            error_log('DPUWoo Baseline Manager - Invalid API response');
            return 1.0;
        }
        
        $rate = floatval($body['venta']);
        return ($rate > 0) ? $rate : 1.0;
    }
    
    /**
     * Auto-setup baseline during plugin activation/update
     * This replaces the old option-based approach
     */
    public function auto_setup_baseline()
    {
        // Check if we already have an active dollar baseline
        $current_baseline = $this->get_current_baseline('dollar');
        
        if ($current_baseline === null || $current_baseline <= 0) {
            // Fetch current rate and set as baseline
            $current_rate = $this->fetch_current_dollar_rate();
            $this->set_baseline('dollar', $current_rate, 'USD', 'auto_setup', 'Auto-configured during plugin setup');
            
            error_log("DPUWoo: Auto-configured baseline dollar rate: {$current_rate}");
        } else {
            error_log("DPUWoo: Baseline already exists: {$current_baseline}");
        }
    }
    
    /**
     * Force initialization - can be called manually if needed
     * This is useful for cases where activation didn't run properly
     * Sets up fresh baseline without migrating old data
     */
    public function force_initialize()
    {
        $this->create_table();
        $this->auto_setup_baseline();
        
        error_log('DPUWoo Baseline Manager: Force initialization completed (fresh setup only)');
        return true;
    }
    
    /**
     * Get baseline info for display/admin purposes
     * @return array Baseline information
     */
    public function get_baseline_info()
    {
        global $wpdb;
        
        $current = $this->get_current_baseline('dollar');
        $history_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE baseline_type = 'dollar'"
        );
        
        $latest_record = $wpdb->get_row(
            "SELECT * FROM {$this->table_name} 
             WHERE baseline_type = 'dollar' 
             ORDER BY date_created DESC 
             LIMIT 1"
        );
        
        return [
            'current_value' => $current,
            'history_count' => (int) $history_count,
            'last_updated' => $latest_record ? $latest_record->date_updated : null,
            'source' => $latest_record ? $latest_record->source : null,
            'currency' => $latest_record ? $latest_record->currency_code : 'USD'
        ];
    }
}
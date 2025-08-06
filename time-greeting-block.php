<?php
/**
 * Plugin Name: Time Greeting Block
 * Plugin URI: https://yourwebsite.com/time-greeting-block
 * Description: A WordPress plugin that provides time-based greetings and date display through Gutenberg blocks, shortcodes, and echo functions.
 * Version: 1.3
 * Author: Stephen Walker
 * Author URI: https://flyingw.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: time-greeting-block
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TGB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TGB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TGB_PLUGIN_VERSION', '1.0.3');
define('TGB_OPTION_NAME', 'tgb_settings');

/**
 * Main plugin class
 */
class TimeGreetingBlock {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
        
        // Register shortcode
        add_shortcode('time_greeting', array($this, 'shortcode_handler'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('TimeGreetingBlock', 'uninstall'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register the block using block.json
        if (function_exists('register_block_type')) {
            register_block_type(__DIR__, array(
                'render_callback' => array($this, 'render_block'),
            ));
        }
    }
    
    /**
     * Load plugin text domain for internationalization
     */
    public function load_textdomain() {
        load_plugin_textdomain('time-greeting-block', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if the block is being used or shortcode might be present
        if (has_block('time-greeting-block/time-greeting') || $this->page_has_shortcode()) {
            wp_enqueue_style(
                'time-greeting-block-style',
                TGB_PLUGIN_URL . 'assets/block-style.css',
                array(),
                TGB_PLUGIN_VERSION
            );
        }
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_assets() {
        if (is_admin()) {
            // Editor JavaScript
            wp_enqueue_script(
                'time-greeting-block-editor',
                TGB_PLUGIN_URL . 'assets/block-editor.js',
                array(
                    'wp-blocks',
                    'wp-element', 
                    'wp-editor',
                    'wp-block-editor',
                    'wp-components',
                    'wp-i18n',
                    'wp-server-side-render'
                ),
                TGB_PLUGIN_VERSION,
                true
            );
            
            // Editor CSS
            wp_enqueue_style(
                'time-greeting-block-editor-style',
                TGB_PLUGIN_URL . 'assets/block-editor.css',
                array('wp-edit-blocks'),
                TGB_PLUGIN_VERSION
            );
            
            // Localize script for translations
            wp_set_script_translations('time-greeting-block-editor', 'time-greeting-block');
        }
    }
    
    /**
     * Check if current page/post has the shortcode
     */
    private function page_has_shortcode() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'time_greeting')) {
            return true;
        }
        return false;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'morning_start' => 5,
            'afternoon_start' => 12,
            'evening_start' => 17,
            'night_start' => 22,
            'night_message' => __("It's {time} {tz} and we're asleep.", 'time-greeting-block'),
            'default_timezone' => get_option('timezone_string') ?: 'America/New_York',
            'default_tz_abbr' => 'ET',
            'plugin_version' => TGB_PLUGIN_VERSION,
            'activation_date' => current_time('mysql')
        );
        
        // Only add options if they don't exist (prevents overwriting on reactivation)
        if (!get_option(TGB_OPTION_NAME)) {
            add_option(TGB_OPTION_NAME, $default_options);
        }
        
        // Update version if different
        $current_options = get_option(TGB_OPTION_NAME, array());
        if (empty($current_options['plugin_version']) || $current_options['plugin_version'] !== TGB_PLUGIN_VERSION) {
            $current_options['plugin_version'] = TGB_PLUGIN_VERSION;
            $current_options['last_updated'] = current_time('mysql');
            update_option(TGB_OPTION_NAME, $current_options);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation - Clean up all data
     */
    public function deactivate() {
        // Clean up all plugin data
        $this->cleanup_plugin_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall - Also clean up (in case deactivate didn't run)
     */
    public static function uninstall() {
        // Ensure cleanup runs even if deactivate didn't
        $instance = new self();
        $instance->cleanup_plugin_data();
    }
    
    /**
     * Clean up all plugin data
     */
    private function cleanup_plugin_data() {
        // Remove plugin options
        delete_option(TGB_OPTION_NAME);
        
        // Remove any transients the plugin might have created
        delete_transient('tgb_timezone_cache');
        
        // Remove any user meta related to this plugin
        delete_metadata('user', 0, 'tgb_user_settings', '', true);
        
        // Remove any post meta related to this plugin (if any were added)
        delete_metadata('post', 0, 'tgb_custom_settings', '', true);
        
        // Clean up any scheduled events (if any)
        wp_clear_scheduled_hook('tgb_daily_cleanup');
        
        // Remove any custom database tables (none in this plugin, but good practice)
        global $wpdb;
        // Example: $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tgb_custom_table");
        
        // Clear any cached data
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('time-greeting-block');
        }
        
        // Log cleanup for debugging (remove in production)
        if (WP_DEBUG) {
            error_log('Time Greeting Block: Plugin data cleanup completed');
        }
    }
    
    /**
     * Get plugin settings with defaults
     */
    private function get_settings() {
        $defaults = array(
            'morning_start' => 5,
            'afternoon_start' => 12,
            'evening_start' => 17,
            'night_start' => 22,
            'night_message' => __("It's {time} {tz} and we're asleep.", 'time-greeting-block'),
            'default_timezone' => get_option('timezone_string') ?: 'America/New_York',
            'default_tz_abbr' => 'ET'
        );
        
        $settings = get_option(TGB_OPTION_NAME, $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Render block callback for server-side rendering
     */
    public function render_block($attributes, $content, $block) {
        // Sanitize and set defaults
        $attributes = wp_parse_args($attributes, array(
            'display' => 'greeting',
            'dateFormat' => 'F j, Y',
            'timezone' => '',
            'tzAbbr' => '',
            'align' => ''
        ));
        
        // Generate the greeting content
        $greeting_content = $this->generate_greeting($attributes);
        
        if (empty($greeting_content)) {
            return '';
        }
        
        // Prepare wrapper attributes
        $wrapper_attributes = get_block_wrapper_attributes(array(
            'class' => $attributes['align'] ? 'has-text-align-' . esc_attr($attributes['align']) : ''
        ));
        
        return sprintf(
            '<div %s>%s</div>',
            $wrapper_attributes,
            $greeting_content
        );
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode_handler($atts) {
        $attributes = shortcode_atts(array(
            'display' => 'greeting',
            'date_format' => 'F j, Y',
            'timezone' => '',
            'tz_abbr' => ''
        ), $atts, 'time_greeting');
        
        // Convert to camelCase for consistency with block attributes
        $normalized_attrs = array(
            'display' => sanitize_text_field($attributes['display']),
            'dateFormat' => sanitize_text_field($attributes['date_format']),
            'timezone' => sanitize_text_field($attributes['timezone']),
            'tzAbbr' => sanitize_text_field($attributes['tz_abbr'])
        );
        
        return $this->generate_greeting($normalized_attrs);
    }
    
    /**
     * Echo function for page builders like Bricks
     */
    public function echo_greeting($atts = array()) {
        echo $this->shortcode_handler($atts);
    }
    
    /**
     * Generate greeting content with proper accessibility
     */
    private function generate_greeting($attributes) {
        $settings = $this->get_settings();
        
        // Set defaults from settings
        $display = $attributes['display'] ?? 'greeting';
        $date_format = $attributes['dateFormat'] ?? 'F j, Y';
        $timezone = $attributes['timezone'] ?: $settings['default_timezone'];
        $tz_abbr = $attributes['tzAbbr'] ?: $settings['default_tz_abbr'];
        
        // Validate display option
        if (!in_array($display, array('greeting', 'date', 'both'))) {
            $display = 'greeting';
        }
        
        // Validate and sanitize date format
        $date_format = $this->sanitize_date_format($date_format);
        
        // Validate timezone
        if (!in_array($timezone, timezone_identifiers_list())) {
            $timezone = $settings['default_timezone'];
        }
        
        // Create DateTime object with specified timezone
        try {
            $datetime = new DateTime('now', new DateTimeZone($timezone));
        } catch (Exception $e) {
            // Fallback to default timezone if there's an error
            try {
                $datetime = new DateTime('now', new DateTimeZone($settings['default_timezone']));
                $timezone = $settings['default_timezone'];
            } catch (Exception $e2) {
                // Final fallback to server timezone
                $datetime = new DateTime();
                $timezone = date_default_timezone_get();
            }
        }
        
        $output = '';
        
        // Generate greeting if needed
        if ($display === 'greeting' || $display === 'both') {
            $greeting = $this->get_time_greeting($datetime, $tz_abbr, $settings);
            $current_time = $datetime->format('c'); // ISO 8601 format for datetime attribute
            
            // Wrap in semantic HTML with accessibility
            $output .= sprintf(
                '<span class="time-greeting" data-timezone="%s"><time datetime="%s">%s</time></span>',
                esc_attr($timezone),
                esc_attr($current_time),
                esc_html($greeting)
            );
        }
        
        // Add separator if showing both
        if ($display === 'both') {
            $output .= ' ';
        }
        
        // Generate date if needed
        if ($display === 'date' || $display === 'both') {
            try {
                $current_date = $datetime->format($date_format);
                $iso_date = $datetime->format('Y-m-d'); // ISO format for datetime attribute
                
                if ($display === 'date') {
                    $output = sprintf(
                        '<span class="time-greeting-date"><time datetime="%s">%s</time></span>',
                        esc_attr($iso_date),
                        esc_html($current_date)
                    );
                } else {
                    $output .= sprintf(
                        '<span class="time-greeting-date">%s <time datetime="%s">%s</time>.</span>',
                        esc_html__('Today is', 'time-greeting-block'),
                        esc_attr($iso_date),
                        esc_html($current_date)
                    );
                }
            } catch (Exception $e) {
                // If date formatting fails, skip the date part
                if ($display === 'date') {
                    $output = '<span class="time-greeting-date">' . esc_html__('Date unavailable', 'time-greeting-block') . '</span>';
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Sanitize date format to prevent code execution
     */
    private function sanitize_date_format($format) {
        // Allow only safe date format characters
        $safe_format = preg_replace('/[^a-zA-Z0-9\s\-\/\\\:,.\s]/', '', $format);
        
        // Ensure we have a valid format
        if (empty($safe_format)) {
            return 'F j, Y'; // Default format
        }
        
        return $safe_format;
    }
    
    /**
     * Get time-based greeting using DateTime object
     */
    private function get_time_greeting($datetime, $tz_abbr, $settings) {
        $hour = (int)$datetime->format('H');
        $current_time = $datetime->format('g:i A');
        
        if ($hour >= $settings['morning_start'] && $hour < $settings['afternoon_start']) {
            return __('Good morning!', 'time-greeting-block');
        } elseif ($hour >= $settings['afternoon_start'] && $hour < $settings['evening_start']) {
            return __('Good afternoon!', 'time-greeting-block');
        } elseif ($hour >= $settings['evening_start'] && $hour < $settings['night_start']) {
            return __('Good evening!', 'time-greeting-block');
        } else {
            // Replace placeholders in night message
            $night_message = $settings['night_message'];
            $night_message = str_replace('{time}', $current_time, $night_message);
            $night_message = str_replace('{tz}', esc_html($tz_abbr), $night_message);
            return $night_message;
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Time Greeting Settings', 'time-greeting-block'),
            __('Time Greeting', 'time-greeting-block'),
            'manage_options',
            'time-greeting-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting(
            'tgb_settings_group',
            TGB_OPTION_NAME,
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'tgb_main_section',
            __('Time Periods', 'time-greeting-block'),
            array($this, 'main_section_callback'),
            'time-greeting-settings'
        );
        
        $fields = array(
            'morning_start' => __('Morning starts at (24-hour)', 'time-greeting-block'),
            'afternoon_start' => __('Afternoon starts at (24-hour)', 'time-greeting-block'),
            'evening_start' => __('Evening starts at (24-hour)', 'time-greeting-block'),
            'night_start' => __('Night/Late hours start at (24-hour)', 'time-greeting-block')
        );
        
        foreach ($fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                array($this, 'number_field_callback'),
                'time-greeting-settings',
                'tgb_main_section',
                array('field' => $field, 'min' => 0, 'max' => 23)
            );
        }
        
        add_settings_field(
            'night_message',
            __('Night/After Hours Message', 'time-greeting-block'),
            array($this, 'textarea_field_callback'),
            'time-greeting-settings',
            'tgb_main_section',
            array('field' => 'night_message')
        );
        
        add_settings_section(
            'tgb_defaults_section',
            __('Default Settings', 'time-greeting-block'),
            array($this, 'defaults_section_callback'),
            'time-greeting-settings'
        );
        
        add_settings_field(
            'default_timezone',
            __('Default Timezone', 'time-greeting-block'),
            array($this, 'timezone_field_callback'),
            'time-greeting-settings',
            'tgb_defaults_section',
            array('field' => 'default_timezone')
        );
        
        add_settings_field(
            'default_tz_abbr',
            __('Default Timezone Abbreviation', 'time-greeting-block'),
            array($this, 'text_field_callback'),
            'time-greeting-settings',
            'tgb_defaults_section',
            array('field' => 'default_tz_abbr')
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        $current_settings = get_option(TGB_OPTION_NAME, array());
        
        // Preserve existing data that shouldn't be overwritten
        $sanitized['plugin_version'] = $current_settings['plugin_version'] ?? TGB_PLUGIN_VERSION;
        $sanitized['activation_date'] = $current_settings['activation_date'] ?? current_time('mysql');
        $sanitized['last_updated'] = current_time('mysql');
        
        // Sanitize user inputs
        $sanitized['morning_start'] = intval($input['morning_start'] ?? 5);
        $sanitized['afternoon_start'] = intval($input['afternoon_start'] ?? 12);
        $sanitized['evening_start'] = intval($input['evening_start'] ?? 17);
        $sanitized['night_start'] = intval($input['night_start'] ?? 22);
        $sanitized['night_message'] = sanitize_textarea_field($input['night_message'] ?? '');
        $sanitized['default_timezone'] = sanitize_text_field($input['default_timezone'] ?? '');
        $sanitized['default_tz_abbr'] = sanitize_text_field($input['default_tz_abbr'] ?? '');
        
        // Validate hour ranges
        foreach (array('morning_start', 'afternoon_start', 'evening_start', 'night_start') as $field) {
            if ($sanitized[$field] < 0 || $sanitized[$field] > 23) {
                add_settings_error(TGB_OPTION_NAME, $field, 
                    sprintf(__('%s must be between 0 and 23.', 'time-greeting-block'), 
                    ucfirst(str_replace('_', ' ', $field))));
                // Reset to default
                $defaults = array('morning_start' => 5, 'afternoon_start' => 12, 'evening_start' => 17, 'night_start' => 22);
                $sanitized[$field] = $defaults[$field];
            }
        }
        
        // Validate timezone
        if (!empty($sanitized['default_timezone']) && !in_array($sanitized['default_timezone'], timezone_identifiers_list())) {
            add_settings_error(TGB_OPTION_NAME, 'default_timezone', 
                __('Invalid timezone identifier.', 'time-greeting-block'));
            $sanitized['default_timezone'] = get_option('timezone_string') ?: 'America/New_York';
        }
        
        return $sanitized;
    }
    
    /**
     * Section callbacks
     */
    public function main_section_callback() {
        echo '<p>' . esc_html__('Configure when different greeting periods begin. Use 24-hour format (0-23).', 'time-greeting-block') . '</p>';
    }
    
    public function defaults_section_callback() {
        echo '<p>' . esc_html__('Set default values that will be used when no specific values are provided.', 'time-greeting-block') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function number_field_callback($args) {
        $settings = $this->get_settings();
        $value = $settings[$args['field']];
        $min = $args['min'] ?? 0;
        $max = $args['max'] ?? 100;
        
        printf(
            '<input type="number" id="%1$s" name="%5$s[%1$s]" value="%2$s" min="%3$s" max="%4$s" class="small-text" />',
            esc_attr($args['field']),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max),
            TGB_OPTION_NAME
        );
    }
    
    public function text_field_callback($args) {
        $settings = $this->get_settings();
        $value = $settings[$args['field']];
        
        printf(
            '<input type="text" id="%1$s" name="%3$s[%1$s]" value="%2$s" class="regular-text" />',
            esc_attr($args['field']),
            esc_attr($value),
            TGB_OPTION_NAME
        );
    }
    
    public function textarea_field_callback($args) {
        $settings = $this->get_settings();
        $value = $settings[$args['field']];
        
        printf(
            '<textarea id="%1$s" name="%3$s[%1$s]" rows="3" cols="50" class="large-text">%2$s</textarea>',
            esc_attr($args['field']),
            esc_textarea($value),
            TGB_OPTION_NAME
        );
        
        if ($args['field'] === 'night_message') {
            echo '<p class="description">' . 
                esc_html__('Use {time} for current time and {tz} for timezone abbreviation.', 'time-greeting-block') . 
                '</p>';
        }
    }
    
    public function timezone_field_callback($args) {
        $settings = $this->get_settings();
        $current_value = $settings[$args['field']];
        $timezones = timezone_identifiers_list();
        
        printf('<select id="%1$s" name="%2$s[%1$s]">', esc_attr($args['field']), TGB_OPTION_NAME);
        
        foreach ($timezones as $timezone) {
            printf(
                '<option value="%1$s"%2$s>%1$s</option>',
                esc_attr($timezone),
                selected($current_value, $timezone, false)
            );
        }
        
        echo '</select>';
    }
    
    /**
     * Admin page with tabbed interface
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'time-greeting-block'));
        }
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'settings', admin_url('options-general.php?page=time-greeting-settings'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'time-greeting-block'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'styling', admin_url('options-general.php?page=time-greeting-settings'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'styling' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Styling & CSS', 'time-greeting-block'); ?>
                </a>
            </nav>
            
            <div class="tgb-tab-content">
                <?php if ($current_tab === 'settings'): ?>
                    <?php $this->render_settings_tab(); ?>
                <?php elseif ($current_tab === 'styling'): ?>
                    <?php $this->render_styling_tab(); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .tgb-tab-content {
            margin-top: 20px;
        }
        
        .tgb-css-example {
            background: #f8f9fa;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 10px 0;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        
        .tgb-css-example code {
            background: none;
            padding: 0;
        }
        
        .tgb-variables-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .tgb-variables-table th,
        .tgb-variables-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .tgb-variables-table th {
            background-color: #f1f1f1;
            font-weight: 600;
        }
        
        .tgb-variables-table code {
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .tgb-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .tgb-section h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .tgb-highlight {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Render settings tab content
     */
    private function render_settings_tab() {
        ?>
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('tgb_settings_group');
                        do_settings_sections('time-greeting-settings');
                        submit_button();
                        ?>
                    </form>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Plugin Cleanup', 'time-greeting-block'); ?></span></h2>
                        <div class="inside">
                            <p><?php esc_html_e('This plugin automatically cleans up all its data when deactivated or uninstalled. No manual cleanup is required.', 'time-greeting-block'); ?></p>
                            <p><strong><?php esc_html_e('Data removed on deactivation:', 'time-greeting-block'); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e('Plugin settings and options', 'time-greeting-block'); ?></li>
                                <li><?php esc_html_e('Cached timezone data', 'time-greeting-block'); ?></li>
                                <li><?php esc_html_e('Any transient data', 'time-greeting-block'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div id="postbox-container-1" class="postbox-container">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Usage Documentation', 'time-greeting-block'); ?></span></h2>
                        <div class="inside">
                            <h3><?php esc_html_e('Block Editor', 'time-greeting-block'); ?></h3>
                            <p><?php esc_html_e('Add the Time Greeting block in the Gutenberg editor under Widgets category.', 'time-greeting-block'); ?></p>
                            
                            <h3><?php esc_html_e('Shortcode Usage', 'time-greeting-block'); ?></h3>
                            <p><strong><?php esc_html_e('Basic greeting:', 'time-greeting-block'); ?></strong></p>
                            <code>[time_greeting]</code>
                            
                            <p><strong><?php esc_html_e('Show date only:', 'time-greeting-block'); ?></strong></p>
                            <code>[time_greeting display="date"]</code>
                            
                            <p><strong><?php esc_html_e('Show both:', 'time-greeting-block'); ?></strong></p>
                            <code>[time_greeting display="both"]</code>
                            
                            <p><strong><?php esc_html_e('Custom timezone:', 'time-greeting-block'); ?></strong></p>
                            <code>[time_greeting timezone="America/Chicago" tz_abbr="CT"]</code>
                            
                            <h3><?php esc_html_e('Echo Function', 'time-greeting-block'); ?></h3>
                            <p><?php esc_html_e('For page builders like Bricks:', 'time-greeting-block'); ?></p>
                            <code>&lt;?php time_greeting_echo(); ?&gt;</code>
                            
                            <h3><?php esc_html_e('Parameters', 'time-greeting-block'); ?></h3>
                            <ul>
                                <li><strong>display:</strong> "greeting", "date", "both"</li>
                                <li><strong>date_format:</strong> <?php esc_html_e('PHP date format', 'time-greeting-block'); ?></li>
                                <li><strong>timezone:</strong> <?php esc_html_e('PHP timezone identifier', 'time-greeting-block'); ?></li>
                                <li><strong>tz_abbr:</strong> <?php esc_html_e('Timezone abbreviation', 'time-greeting-block'); ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Current Preview', 'time-greeting-block'); ?></span></h2>
                        <div class="inside">
                            <p><strong><?php esc_html_e('Current greeting:', 'time-greeting-block'); ?></strong></p>
                            <p><?php echo $this->generate_greeting(array('display' => 'greeting')); ?></p>
                            
                            <p><strong><?php esc_html_e('Current date:', 'time-greeting-block'); ?></strong></p>
                            <p><?php echo $this->generate_greeting(array('display' => 'date')); ?></p>
                            
                            <p><strong><?php esc_html_e('Both together:', 'time-greeting-block'); ?></strong></p>
                            <p><?php echo $this->generate_greeting(array('display' => 'both')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render styling tab content
     */
    private function render_styling_tab() {
        ?>
        <div class="tgb-section">
            <h3><?php esc_html_e('CSS Variables Overview', 'time-greeting-block'); ?></h3>
            <p><?php esc_html_e('The Time Greeting Block uses CSS Custom Properties (CSS Variables) for easy customization. Override these variables in your theme to customize the appearance.', 'time-greeting-block'); ?></p>
            
            <div class="tgb-highlight">
                <strong><?php esc_html_e('✨ New in v1.0.3:', 'time-greeting-block'); ?></strong> 
                <?php esc_html_e('Date text is no longer italic by default and has full opacity for better theme integration.', 'time-greeting-block'); ?>
            </div>
        </div>

        <div class="tgb-section">
            <h3><?php esc_html_e('Available CSS Variables', 'time-greeting-block'); ?></h3>
            <table class="tgb-variables-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('CSS Variable', 'time-greeting-block'); ?></th>
                        <th><?php esc_html_e('Default Value', 'time-greeting-block'); ?></th>
                        <th><?php esc_html_e('Description', 'time-greeting-block'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>--tgb-font-family</code></td>
                        <td><code>inherit</code></td>
                        <td><?php esc_html_e('Font family for the entire block', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-line-height</code></td>
                        <td><code>1.4</code></td>
                        <td><?php esc_html_e('Line height for the block', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-greeting-font-weight</code></td>
                        <td><code>500</code></td>
                        <td><?php esc_html_e('Font weight for greeting text', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-greeting-margin-right</code></td>
                        <td><code>0.5em</code></td>
                        <td><?php esc_html_e('Space after greeting when showing both', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-date-font-style</code></td>
                        <td><code>normal</code></td>
                        <td><?php esc_html_e('Font style for date (normal, italic, etc.)', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-date-opacity</code></td>
                        <td><code>1</code></td>
                        <td><?php esc_html_e('Opacity for date text (0-1)', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-separator-content</code></td>
                        <td><code>" "</code></td>
                        <td><?php esc_html_e('Content between greeting and date', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-background-padding</code></td>
                        <td><code>1em</code></td>
                        <td><?php esc_html_e('Padding when has background', 'time-greeting-block'); ?></td>
                    </tr>
                    <tr>
                        <td><code>--tgb-background-border-radius</code></td>
                        <td><code>4px</code></td>
                        <td><?php esc_html_e('Border radius when has background', 'time-greeting-block'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="tgb-section">
            <h3><?php esc_html_e('Common Customization Examples', 'time-greeting-block'); ?></h3>
            
            <h4><?php esc_html_e('Make Date Text Italic', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>.wp-block-time-greeting-block-time-greeting {
    --tgb-date-font-style: italic;
}</code>
            </div>
            
            <h4><?php esc_html_e('Bold Greeting Text', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>.wp-block-time-greeting-block-time-greeting {
    --tgb-greeting-font-weight: 700;
}</code>
            </div>
            
            <h4><?php esc_html_e('Custom Separator', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>.wp-block-time-greeting-block-time-greeting {
    --tgb-separator-content: " • ";
}</code>
            </div>
            
            <h4><?php esc_html_e('Reduce Date Opacity', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>.wp-block-time-greeting-block-time-greeting {
    --tgb-date-opacity: 0.7;
}</code>
            </div>
            
            <h4><?php esc_html_e('Custom Font Family', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>.wp-block-time-greeting-block-time-greeting {
    --tgb-font-family: "Helvetica Neue", Arial, sans-serif;
}</code>
            </div>
        </div>

        <div class="tgb-section">
            <h3><?php esc_html_e('Where to Add Custom CSS', 'time-greeting-block'); ?></h3>
            
            <h4><?php esc_html_e('Method 1: Theme Customizer (Recommended)', 'time-greeting-block'); ?></h4>
            <ol>
                <li><?php esc_html_e('Go to', 'time-greeting-block'); ?> <strong><?php esc_html_e('Appearance > Customize', 'time-greeting-block'); ?></strong></li>
                <li><?php esc_html_e('Click', 'time-greeting-block'); ?> <strong><?php esc_html_e('Additional CSS', 'time-greeting-block'); ?></strong></li>
                <li><?php esc_html_e('Add your custom CSS variables', 'time-greeting-block'); ?></li>
            </ol>
            
            <h4><?php esc_html_e('Method 2: Child Theme', 'time-greeting-block'); ?></h4>
            <p><?php esc_html_e('Add to your child theme\'s style.css:', 'time-greeting-block'); ?></p>
            <div class="tgb-css-example">
<code>/* Time Greeting Block Customizations */
.wp-block-time-greeting-block-time-greeting {
    --tgb-date-font-style: italic;
    --tgb-date-opacity: 0.8;
}</code>
            </div>
            
            <h4><?php esc_html_e('Method 3: Custom Plugin', 'time-greeting-block'); ?></h4>
            <p><?php esc_html_e('Create a simple plugin to add custom styles:', 'time-greeting-block'); ?></p>
            <div class="tgb-css-example">
<code>&lt;?php
// Custom Time Greeting Styles
function my_time_greeting_styles() {
    wp_add_inline_style('wp-block-library', '
        .wp-block-time-greeting-block-time-greeting {
            --tgb-greeting-font-weight: 600;
            --tgb-date-font-style: italic;
        }
    ');
}
add_action('wp_enqueue_scripts', 'my_time_greeting_styles');</code>
            </div>
        </div>

        <div class="tgb-section">
            <h3><?php esc_html_e('Advanced Styling Examples', 'time-greeting-block'); ?></h3>
            
            <h4><?php esc_html_e('Theme-Specific Variations', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>/* Corporate theme */
.corporate-theme .wp-block-time-greeting-block-time-greeting {
    --tgb-font-family: "Open Sans", sans-serif;
    --tgb-greeting-font-weight: 600;
    --tgb-date-font-style: normal;
}

/* Creative theme */
.creative-theme .wp-block-time-greeting-block-time-greeting {
    --tgb-font-family: "Georgia", serif;
    --tgb-greeting-font-weight: 400;
    --tgb-date-font-style: italic;
    --tgb-separator-content: " ~ ";
}</code>
            </div>
            
            <h4><?php esc_html_e('Dark Mode Support', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>@media (prefers-color-scheme: dark) {
    .wp-block-time-greeting-block-time-greeting {
        --tgb-date-opacity: 0.8;
    }
}</code>
            </div>
            
            <h4><?php esc_html_e('Mobile Responsive', 'time-greeting-block'); ?></h4>
            <div class="tgb-css-example">
<code>@media (max-width: 768px) {
    .wp-block-time-greeting-block-time-greeting {
        --tgb-greeting-margin-right: 0;
        --tgb-mobile-stack-margin-bottom: 0.5em;
    }
}</code>
            </div>
        </div>
        
        <div class="tgb-section">
            <h3><?php esc_html_e('Live Preview', 'time-greeting-block'); ?></h3>
            <p><?php esc_html_e('Here\'s how your current settings look:', 'time-greeting-block'); ?></p>
            
            <div style="background: #f9f9f9; padding: 20px; border-radius: 4px; margin: 15px 0;">
                <p><strong><?php esc_html_e('Current greeting:', 'time-greeting-block'); ?></strong></p>
                <div style="font-size: 18px; margin: 10px 0;"><?php echo $this->generate_greeting(array('display' => 'greeting')); ?></div>
                
                <p><strong><?php esc_html_e('Current date:', 'time-greeting-block'); ?></strong></p>
                <div style="font-size: 18px; margin: 10px 0;"><?php echo $this->generate_greeting(array('display' => 'date')); ?></div>
                
                <p><strong><?php esc_html_e('Both together:', 'time-greeting-block'); ?></strong></p>
                <div style="font-size: 18px; margin: 10px 0;"><?php echo $this->generate_greeting(array('display' => 'both')); ?></div>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new TimeGreetingBlock();

/**
 * Echo function for external use (Bricks Builder, etc.)
 */
function time_greeting_echo($atts = array()) {
    if (class_exists('TimeGreetingBlock')) {
        $plugin = new TimeGreetingBlock();
        $plugin->echo_greeting($atts);
    }
}
?>
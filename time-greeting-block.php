<?php
/**
 * Plugin Name: Time Greeting Block
 * Plugin URI: https://yourwebsite.com/time-greeting-block
 * Description: A WordPress plugin that provides time-based greetings and date display through Gutenberg blocks, shortcodes, and echo functions.
 * Version: 1.0.0
 * Author: Stephen Walker
 * Author URI: https://flyingw.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: time-greeting-block
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8.2
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TGB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TGB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TGB_PLUGIN_VERSION', '1.0.0');

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
        
        // Register block
        add_action('init', array($this, 'register_block'));
        
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Additional initialization if needed
    }
    
    /**
     * Load plugin text domain for internationalization
     */
    public function load_textdomain() {
        load_plugin_textdomain('time-greeting-block', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            'default_tz_abbr' => 'ET'
        );
        
        add_option('tgb_settings', $default_options);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
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
        
        $settings = get_option('tgb_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'time-greeting-block-style',
            TGB_PLUGIN_URL . 'assets/style.css',
            array(),
            TGB_PLUGIN_VERSION
        );
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_assets() {
        // Enqueue frontend CSS for both frontend and editor
        wp_enqueue_style(
            'time-greeting-block-style',
            TGB_PLUGIN_URL . 'assets/style.css',
            array(),
            TGB_PLUGIN_VERSION
        );
        
        if (is_admin()) {
            wp_enqueue_script(
                'time-greeting-block-editor',
                TGB_PLUGIN_URL . 'assets/block-editor.js',
                array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
                TGB_PLUGIN_VERSION,
                true
            );
            
            // Localize script for translations
            wp_set_script_translations('time-greeting-block-editor', 'time-greeting-block');
        }
    }
    
    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (function_exists('register_block_type')) {
            register_block_type('time-greeting-block/time-greeting', array(
                'render_callback' => array($this, 'render_block'),
                'attributes' => array(
                    'display' => array(
                        'type' => 'string',
                        'default' => 'greeting'
                    ),
                    'dateFormat' => array(
                        'type' => 'string',
                        'default' => 'F j, Y'
                    ),
                    'timezone' => array(
                        'type' => 'string',
                        'default' => ''
                    ),
                    'tzAbbr' => array(
                        'type' => 'string',
                        'default' => ''
                    )
                )
            ));
        }
    }
    
    /**
     * Render block callback
     */
    public function render_block($attributes) {
        return $this->generate_greeting($attributes);
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
        
        // Set timezone
        $original_timezone = date_default_timezone_get();
        date_default_timezone_set($timezone);
        
        $output = '';
        
        try {
            // Generate greeting if needed
            if ($display === 'greeting' || $display === 'both') {
                $greeting = $this->get_time_greeting($tz_abbr, $settings);
                $current_time = date('c'); // ISO 8601 format for datetime attribute
                
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
                $current_date = date($date_format);
                $iso_date = date('Y-m-d'); // ISO format for datetime attribute
                
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
            }
        } finally {
            // Restore original timezone
            date_default_timezone_set($original_timezone);
        }
        
        return $output;
    }
    
    /**
     * Get time-based greeting
     */
    private function get_time_greeting($tz_abbr, $settings) {
        $hour = (int)date('H');
        $current_time = date('g:i A');
        
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
            $night_message = str_replace('{tz}', $tz_abbr, $night_message);
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
            'tgb_settings',
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'tgb_main_section',
            __('Time Periods', 'time-greeting-block'),
            array($this, 'main_section_callback'),
            'time-greeting-settings'
        );
        
        add_settings_field(
            'morning_start',
            __('Morning starts at (24-hour)', 'time-greeting-block'),
            array($this, 'number_field_callback'),
            'time-greeting-settings',
            'tgb_main_section',
            array('field' => 'morning_start', 'min' => 0, 'max' => 23)
        );
        
        add_settings_field(
            'afternoon_start',
            __('Afternoon starts at (24-hour)', 'time-greeting-block'),
            array($this, 'number_field_callback'),
            'time-greeting-settings',
            'tgb_main_section',
            array('field' => 'afternoon_start', 'min' => 0, 'max' => 23)
        );
        
        add_settings_field(
            'evening_start',
            __('Evening starts at (24-hour)', 'time-greeting-block'),
            array($this, 'number_field_callback'),
            'time-greeting-settings',
            'tgb_main_section',
            array('field' => 'evening_start', 'min' => 0, 'max' => 23)
        );
        
        add_settings_field(
            'night_start',
            __('Night/Late hours start at (24-hour)', 'time-greeting-block'),
            array($this, 'number_field_callback'),
            'time-greeting-settings',
            'tgb_main_section',
            array('field' => 'night_start', 'min' => 0, 'max' => 23)
        );
        
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
                add_settings_error('tgb_settings', $field, 
                    sprintf(__('%s must be between 0 and 23.', 'time-greeting-block'), 
                    ucfirst(str_replace('_', ' ', $field))));
            }
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
            '<input type="number" id="%1$s" name="tgb_settings[%1$s]" value="%2$s" min="%3$s" max="%4$s" class="small-text" />',
            esc_attr($args['field']),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max)
        );
    }
    
    public function text_field_callback($args) {
        $settings = $this->get_settings();
        $value = $settings[$args['field']];
        
        printf(
            '<input type="text" id="%1$s" name="tgb_settings[%1$s]" value="%2$s" class="regular-text" />',
            esc_attr($args['field']),
            esc_attr($value)
        );
    }
    
    public function textarea_field_callback($args) {
        $settings = $this->get_settings();
        $value = $settings[$args['field']];
        
        printf(
            '<textarea id="%1$s" name="tgb_settings[%1$s]" rows="3" cols="50" class="large-text">%2$s</textarea>',
            esc_attr($args['field']),
            esc_textarea($value)
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
        
        printf('<select id="%1$s" name="tgb_settings[%1$s]">', esc_attr($args['field']));
        
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
     * Admin page
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'time-greeting-block'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
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
                    </div>
                    
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Documentation', 'time-greeting-block'); ?></span></h2>
                            <div class="inside">
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
                                <code>&lt;?php echo time_greeting_echo(); ?&gt;</code>
                                
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
                            </div>
                        </div>
                    </div>
                </div>
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
    global $time_greeting_plugin;
    if (class_exists('TimeGreetingBlock')) {
        $plugin = new TimeGreetingBlock();
        echo $plugin->shortcode_handler($atts);
    }
}
?>
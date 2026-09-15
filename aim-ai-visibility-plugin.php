<?php
/**
 * Plugin Name: AIM AI Visibility Technical Suite
 * Plugin URI: https://retailfixturesolutions.com
 * Description: Implements AI visibility technical fixes including schema markup, metadata management, and heading structure corrections with full undo capability.
 * Version: 1.0.0
 * Author: Advanced Integrated Marketing Inc.
 * Author URI: https://aim-tex.com
 * License: GPL v2 or later
 * Domain Path: /languages
 * Text Domain: aim-ai-visibility
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('AIM_AI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AIM_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIM_AI_PLUGIN_VERSION', '1.0.0');

class AIM_AI_Visibility_Suite {

    private $option_prefix = 'aim_ai_';
    private $db_version = 1;

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_head', array($this, 'inject_schema_markup'), 1);
        add_action('wp_head', array($this, 'inject_meta_tags'), 1);
        add_filter('wp_head', array($this, 'fix_heading_hierarchy'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_aim_ai_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_aim_ai_undo_changes', array($this, 'ajax_undo_changes'));
        add_action('wp_ajax_aim_ai_get_status', array($this, 'ajax_get_status'));
    }

    public function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create versioning table
        $table_name = $wpdb->prefix . 'aim_ai_versions';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            version_number int NOT NULL,
            settings longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Initialize default settings
        $defaults = $this->get_default_settings();
        foreach ($defaults as $key => $value) {
            if (!get_option($this->option_prefix . $key)) {
                update_option($this->option_prefix . $key, $value);
            }
        }

        // Save initial version
        $this->save_version();
    }

    public function deactivate() {
        // Cleanup if needed
    }

    public function get_default_settings() {
        return array(
            'org_name' => 'Retail Fixture Solutions',
            'org_url' => 'https://retailfixturesolutions.com',
            'org_phone' => '+1-972-923-0001',
            'org_address' => '',
            'org_city' => 'Dallas',
            'org_state' => 'TX',
            'org_zip' => '',
            'org_country' => 'US',
            'org_founding_date' => '',
            'org_logo_url' => '',
            'org_description' => '',
            'linkedin_url' => '',
            'gcp_url' => '',
            'linkedin_ceo_name' => '',
            'linkedin_ceo_url' => '',
            'default_meta_description' => '',
            'service_categories' => array(),
            'enable_heading_fix' => true,
            'enable_schema' => true,
            'enable_metadata' => true,
            'tracking_enabled' => true,
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Visibility Suite',
            'AI Visibility',
            'manage_options',
            'aim-ai-visibility',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            75
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_aim-ai-visibility') return;

        wp_enqueue_style('aim-ai-admin-style', AIM_AI_PLUGIN_URL . 'assets/admin-style.css', array(), AIM_AI_PLUGIN_VERSION);
        wp_enqueue_script('aim-ai-admin-script', AIM_AI_PLUGIN_URL . 'assets/admin-script.js', array('jquery'), AIM_AI_PLUGIN_VERSION, true);

        wp_localize_script('aim-ai-admin-script', 'aimAiAdmin', array(
            'nonce' => wp_create_nonce('aim_ai_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $settings = $this->get_all_settings();
        $status = $this->get_plugin_status();
        include AIM_AI_PLUGIN_PATH . 'templates/admin-page.php';
    }

    public function get_all_settings() {
        $defaults = $this->get_default_settings();
        $settings = array();

        foreach ($defaults as $key => $default_value) {
            $settings[$key] = get_option($this->option_prefix . $key, $default_value);
        }

        return $settings;
    }

    public function get_plugin_status() {
        global $wpdb;

        $table = $wpdb->prefix . 'aim_ai_versions';
        $total_versions = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $last_change = $wpdb->get_var("SELECT created_at FROM $table ORDER BY id DESC LIMIT 1");

        return array(
            'total_changes' => $total_versions - 1,
            'last_change' => $last_change,
            'settings_active' => get_option($this->option_prefix . 'enable_schema') ||
                                get_option($this->option_prefix . 'enable_metadata') ||
                                get_option($this->option_prefix . 'enable_heading_fix'),
        );
    }

    public function save_version() {
        global $wpdb;

        $table = $wpdb->prefix . 'aim_ai_versions';
        $settings = wp_json_encode($this->get_all_settings());

        $wpdb->insert(
            $table,
            array(
                'version_number' => $wpdb->get_var("SELECT COUNT(*) FROM $table") + 1,
                'settings' => $settings,
            ),
            array('%d', '%s')
        );
    }

    public function inject_schema_markup() {
        if (!get_option($this->option_prefix . 'enable_schema')) return;

        $settings = $this->get_all_settings();

        // Organization Schema
        $org_schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => sanitize_text_field($settings['org_name']),
            'url' => esc_url($settings['org_url']),
            'telephone' => sanitize_text_field($settings['org_phone']),
        );

        if (!empty($settings['org_address'])) {
            $org_schema['address'] = array(
                '@type' => 'PostalAddress',
                'streetAddress' => sanitize_text_field($settings['org_address']),
                'addressLocality' => sanitize_text_field($settings['org_city']),
                'addressRegion' => sanitize_text_field($settings['org_state']),
                'postalCode' => sanitize_text_field($settings['org_zip']),
                'addressCountry' => sanitize_text_field($settings['org_country']),
            );
        }

        if (!empty($settings['org_logo_url'])) {
            $org_schema['logo'] = esc_url($settings['org_logo_url']);
        }

        if (!empty($settings['org_founding_date'])) {
            $org_schema['foundingDate'] = sanitize_text_field($settings['org_founding_date']);
        }

        if (!empty($settings['org_description'])) {
            $org_schema['description'] = sanitize_textarea_field($settings['org_description']);
        }

        // Add sameAs links
        $same_as = array();
        if (!empty($settings['linkedin_url'])) {
            $same_as[] = esc_url($settings['linkedin_url']);
        }
        if (!empty($settings['gcp_url'])) {
            $same_as[] = esc_url($settings['gcp_url']);
        }
        if (!empty($same_as)) {
            $org_schema['sameAs'] = $same_as;
        }

        // Add Person schema if leadership exists
        if (!empty($settings['linkedin_ceo_name'])) {
            $org_schema['founder'] = array(
                '@type' => 'Person',
                'name' => sanitize_text_field($settings['linkedin_ceo_name']),
                'sameAs' => esc_url($settings['linkedin_ceo_url']),
            );
        }

        echo '<script type="application/ld+json">' . wp_json_encode($org_schema) . '</script>' . "\n";

        // Service Schema for product categories
        if (!empty($settings['service_categories']) && is_array($settings['service_categories'])) {
            foreach ($settings['service_categories'] as $service) {
                $service_schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'Service',
                    'name' => sanitize_text_field($service['name']),
                    'description' => sanitize_textarea_field($service['description']),
                    'provider' => array(
                        '@type' => 'ProfessionalService',
                        'name' => sanitize_text_field($settings['org_name']),
                    ),
                );

                echo '<script type="application/ld+json">' . wp_json_encode($service_schema) . '</script>' . "\n";
            }
        }
    }

    public function inject_meta_tags() {
        if (!get_option($this->option_prefix . 'enable_metadata')) return;

        $settings = $this->get_all_settings();

        if (is_home() || is_front_page()) {
            if (!empty($settings['default_meta_description'])) {
                echo '<meta name="description" content="' . esc_attr($settings['default_meta_description']) . '">' . "\n";
            }
        }
    }

    public function fix_heading_hierarchy() {
        if (!get_option($this->option_prefix . 'enable_heading_fix')) return;
        // Heading fixes are applied via CSS or custom template modifications
        // This is handled per-page basis in post meta or custom fields
    }

    public function register_settings() {
        $defaults = $this->get_default_settings();

        foreach ($defaults as $key => $value) {
            register_setting('aim_ai_group', $this->option_prefix . $key);
        }
    }

    public function ajax_save_settings() {
        check_ajax_referer('aim_ai_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

        foreach ($settings as $key => $value) {
            $clean_key = str_replace($this->option_prefix, '', $key);

            if (is_array($value)) {
                update_option($key, array_map('sanitize_text_field', $value));
            } else {
                update_option($key, sanitize_text_field($value));
            }
        }

        $this->save_version();

        wp_send_json_success(array(
            'message' => 'Settings saved successfully',
            'status' => $this->get_plugin_status(),
        ));
    }

    public function ajax_undo_changes() {
        check_ajax_referer('aim_ai_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aim_ai_versions';

        $versions = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 2");

        if (count($versions) < 2) {
            wp_send_json_error('No previous version available');
        }

        $previous_settings = json_decode($versions[1]->settings, true);

        foreach ($previous_settings as $key => $value) {
            update_option($this->option_prefix . $key, $value);
        }

        wp_send_json_success(array(
            'message' => 'Changes undone successfully',
            'status' => $this->get_plugin_status(),
        ));
    }

    public function ajax_get_status() {
        check_ajax_referer('aim_ai_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success($this->get_plugin_status());
    }
}

// Initialize plugin
new AIM_AI_Visibility_Suite();

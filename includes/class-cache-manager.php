<?php

if (!defined('ABSPATH')) {
    exit;
}

class DEGB_Cache_Manager {
    private static $instance = null;
    private $cache_group = 'degb_cache';
    private $cache_expiration = 3600; // 1 heure

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!wp_using_ext_object_cache()) {
            $this->setup_transients_cleanup();
        }
    }

    public function get_cached_member_status($email) {
        $cache_key = 'member_status_' . md5($email);
        $cached_value = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $cached_value) {
            return false;
        }
        
        return $cached_value;
    }

    public function set_cached_member_status($email, $is_member) {
        $cache_key = 'member_status_' . md5($email);
        wp_cache_set($cache_key, $is_member, $this->cache_group, $this->cache_expiration);
    }

    public function clear_member_cache($email) {
        $cache_key = 'member_status_' . md5($email);
        wp_cache_delete($cache_key, $this->cache_group);
    }

    private function setup_transients_cleanup() {
        if (!wp_next_scheduled('degb_cleanup_transients')) {
            wp_schedule_event(time(), 'daily', 'degb_cleanup_transients');
        }
        add_action('degb_cleanup_transients', array($this, 'cleanup_expired_transients'));
    }

    public function cleanup_expired_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_degb_%' 
            AND option_value < " . time()
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_degb_%' 
            AND option_name NOT IN (
                SELECT CONCAT('_transient_', SUBSTRING(option_name, 20)) 
                FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_degb_%'
            )"
        );
    }
}

DEGB_Cache_Manager::get_instance();

<?php
/**
 * Plugin Name: Dual Email Group Booking
 * Plugin URI: 
 * Description: Gestion des réservations de groupe avec double email et tarification différenciée membres/non-membres
 * Version: 1.0.0
 * Author: Tatchou Marc
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dual-email-group-booking
 * Domain Path: /languages
 * WC requires at least: 8.0.0
 * WC tested up to: 8.5.2
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Dual_Email_Group_Booking')) {
    class Dual_Email_Group_Booking {
        private static $instance = null;
        private $plugin_path;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            add_action('plugins_loaded', array($this, 'init'));
        }

        public function init() {
            // Vérifier si WooCommerce est activé
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }

            // Chargement des traductions
            load_plugin_textdomain('dual-email-group-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');

            // Initialisation des hooks
            $this->init_hooks();
            
            // Chargement des fichiers nécessaires
            $this->load_dependencies();
        }

        public function woocommerce_missing_notice() {
            ?>
            <div class="error">
                <p><?php _e('Dual Email Group Booking nécessite WooCommerce pour fonctionner. Veuillez installer et activer WooCommerce.', 'dual-email-group-booking'); ?></p>
            </div>
            <?php
        }

        private function init_hooks() {
            // Hooks pour l'ajout des champs emails
            add_action('woocommerce_edit_account_form', array($this, 'add_professional_email_field'));
            add_action('woocommerce_save_account_details', array($this, 'save_professional_email_field'));
            
            // Hooks pour la vérification des emails et calcul des prix
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_check_participant_status', array($this, 'check_participant_status'));
            add_action('wp_ajax_nopriv_check_participant_status', array($this, 'check_participant_status'));
            
            // Hook pour l'export CSV
            add_action('woocommerce_order_details_after_order_table', array($this, 'add_csv_download_button'));
        }

        private function load_dependencies() {
            require_once $this->plugin_path . 'includes/class-email-handler.php';
            require_once $this->plugin_path . 'includes/class-price-calculator.php';
            require_once $this->plugin_path . 'includes/class-csv-exporter.php';
            require_once $this->plugin_path . 'includes/class-cache-manager.php';
            require_once $this->plugin_path . 'includes/class-admin-interface.php';
        }

        public function enqueue_scripts() {
            wp_enqueue_style(
                'degb-styles',
                plugins_url('/assets/css/group-booking.css', __FILE__),
                array(),
                '1.0.0'
            );

            wp_enqueue_script(
                'degb-script',
                plugins_url('/assets/js/group-booking.js', __FILE__),
                array('jquery'),
                '1.0.0',
                true
            );

            wp_localize_script('degb-script', 'degbAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('degb_nonce')
            ));
        }
    }

    // Initialisation du plugin
    function Dual_Email_Group_Booking() {
        return Dual_Email_Group_Booking::get_instance();
    }

    add_action('plugins_loaded', 'Dual_Email_Group_Booking');
}

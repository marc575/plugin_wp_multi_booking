<?php

if (!defined('ABSPATH')) {
    exit;
}

class DEGB_Admin_Interface {
    private static $instance = null;
    private $plugin_slug = 'dual-email-group-booking';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Gestion des Réservations de Groupe', 'dual-email-group-booking'),
            __('Réservations Groupe', 'dual-email-group-booking'),
            'manage_options',
            $this->plugin_slug,
            array($this, 'display_admin_page'),
            'dashicons-groups',
            56
        );

        add_submenu_page(
            $this->plugin_slug,
            __('Statistiques', 'dual-email-group-booking'),
            __('Statistiques', 'dual-email-group-booking'),
            'manage_options',
            $this->plugin_slug . '-stats',
            array($this, 'display_stats_page')
        );
    }

    public function register_settings() {
        register_setting($this->plugin_slug . '-settings-group', 'degb_mailchimp_tags');
        register_setting($this->plugin_slug . '-settings-group', 'degb_csv_export_format');
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, $this->plugin_slug) === false) {
            return;
        }

        wp_enqueue_style(
            'degb-admin-styles',
            plugins_url('/assets/css/admin-style.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );
    }

    public function display_admin_page() {
        ?>
        <div class="wrap degb-admin-wrap">
            <h1><?php _e('Gestion des Réservations de Groupe', 'dual-email-group-booking'); ?></h1>
            
            <div class="degb-admin-content">
                <div class="degb-card">
                    <h2><?php _e('Aperçu', 'dual-email-group-booking'); ?></h2>
                    <div class="degb-stats-overview">
                        <?php $this->display_overview_stats(); ?>
                    </div>
                </div>

                <div class="degb-card">
                    <h2><?php _e('Réservations Récentes', 'dual-email-group-booking'); ?></h2>
                    <?php $this->display_recent_bookings(); ?>
                </div>

                <div class="degb-card">
                    <h2><?php _e('Configuration', 'dual-email-group-booking'); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($this->plugin_slug . '-settings-group');
                        do_settings_sections($this->plugin_slug . '-settings-group');
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php _e('Format d\'export CSV', 'dual-email-group-booking'); ?>
                                </th>
                                <td>
                                    <select name="degb_csv_export_format">
                                        <option value="standard" <?php selected(get_option('degb_csv_export_format'), 'standard'); ?>>
                                            <?php _e('Standard', 'dual-email-group-booking'); ?>
                                        </option>
                                        <option value="detailed" <?php selected(get_option('degb_csv_export_format'), 'detailed'); ?>>
                                            <?php _e('Détaillé', 'dual-email-group-booking'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function display_overview_stats() {
        global $wpdb;

        // Total des réservations de groupe
        $total_bookings = $wpdb->get_var("
            SELECT COUNT(DISTINCT order_id) 
            FROM {$wpdb->prefix}woocommerce_order_items 
            WHERE order_item_type = 'line_item'
            AND order_id IN (
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_fooevents_attendee_data'
            )
        ");

        // Total des participants
        $total_participants = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_fooevents_attendee_data'
        ");

        // Ratio membres/non-membres
        $member_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}wcwcustomer_lookup
        ");

        ?>
        <div class="degb-stat-item">
            <span class="degb-stat-number"><?php echo esc_html($total_bookings); ?></span>
            <span class="degb-stat-label"><?php _e('Réservations', 'dual-email-group-booking'); ?></span>
        </div>
        <div class="degb-stat-item">
            <span class="degb-stat-number"><?php echo esc_html($total_participants); ?></span>
            <span class="degb-stat-label"><?php _e('Participants', 'dual-email-group-booking'); ?></span>
        </div>
        <div class="degb-stat-item">
            <span class="degb-stat-number"><?php echo esc_html($member_count); ?></span>
            <span class="degb-stat-label"><?php _e('Membres', 'dual-email-group-booking'); ?></span>
        </div>
        <?php
    }

    private function display_recent_bookings() {
        $recent_orders = wc_get_orders(array(
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_fooevents_attendee_data',
            'meta_compare' => 'EXISTS',
        ));

        if (empty($recent_orders)) {
            echo '<p>' . __('Aucune réservation récente', 'dual-email-group-booking') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Commande', 'dual-email-group-booking'); ?></th>
                    <th><?php _e('Date', 'dual-email-group-booking'); ?></th>
                    <th><?php _e('Participants', 'dual-email-group-booking'); ?></th>
                    <th><?php _e('Total', 'dual-email-group-booking'); ?></th>
                    <th><?php _e('Actions', 'dual-email-group-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($order->get_edit_order_url()); ?>">
                                #<?php echo esc_html($order->get_order_number()); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($order->get_date_created()->format('Y-m-d H:i')); ?></td>
                        <td><?php echo count($order->get_meta('_fooevents_attendee_data')); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=export_participants_csv&order_id=' . $order->get_id()), 'export_csv_nonce')); ?>" class="button">
                                <?php _e('Exporter CSV', 'dual-email-group-booking'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function display_stats_page() {
        ?>
        <div class="wrap degb-admin-wrap">
            <h1><?php _e('Statistiques Détaillées', 'dual-email-group-booking'); ?></h1>
            
            <div class="degb-admin-content">
                <div class="degb-card">
                    <h2><?php _e('Analyse des Réservations', 'dual-email-group-booking'); ?></h2>
                    <?php $this->display_booking_analysis(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function display_booking_analysis() {
        global $wpdb;

        // Statistiques par mois
        $monthly_stats = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(p.post_date, '%Y-%m') as month,
                COUNT(DISTINCT p.ID) as total_orders,
                COUNT(pm.meta_value) as total_participants
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key = '_fooevents_attendee_data'
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ");

        if ($monthly_stats) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Mois', 'dual-email-group-booking'); ?></th>
                        <th><?php _e('Réservations', 'dual-email-group-booking'); ?></th>
                        <th><?php _e('Participants', 'dual-email-group-booking'); ?></th>
                        <th><?php _e('Moyenne par groupe', 'dual-email-group-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_stats as $stat) : ?>
                        <tr>
                            <td><?php echo date_i18n('F Y', strtotime($stat->month . '-01')); ?></td>
                            <td><?php echo esc_html($stat->total_orders); ?></td>
                            <td><?php echo esc_html($stat->total_participants); ?></td>
                            <td><?php echo number_format($stat->total_participants / $stat->total_orders, 1); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }
    }
}

DEGB_Admin_Interface::get_instance();

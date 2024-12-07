<?php

if (!defined('ABSPATH')) {
    exit;
}

class DEGB_CSV_Exporter {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_export_participants_csv', array($this, 'export_participants_csv'));
        add_action('init', array($this, 'add_csv_endpoint'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_export_scripts'));
    }

    public function add_csv_endpoint() {
        add_rewrite_endpoint('export-participants', EP_ALL);
    }

    public function enqueue_export_scripts() {
        if (is_account_page() || is_order_received_page()) {
            wp_enqueue_script(
                'degb-export',
                plugins_url('/assets/js/export.js', dirname(__FILE__)),
                array('jquery'),
                '1.0.0',
                true
            );

            wp_localize_script('degb-export', 'degbExport', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('export_csv_nonce')
            ));
        }
    }

    public function export_participants_csv() {
        check_ajax_referer('export_csv_nonce', 'nonce');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID manquant');
            return;
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order || !current_user_can('view_order', $order_id)) {
            wp_send_json_error('Accès non autorisé');
            return;
        }

        $participants = $this->get_order_participants($order);
        $filename = 'participants-order-' . $order_id . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM pour UTF-8

        // En-têtes CSV
        fputcsv($output, array(
            'Nom',
            'Email Personnel',
            'Email Professionnel',
            'Statut Membre',
            'Prix Payé',
            'TPS',
            'TVQ',
            'Total'
        ));

        // Données des participants
        foreach ($participants as $participant) {
            fputcsv($output, array(
                $participant['name'],
                $participant['personal_email'],
                $participant['professional_email'],
                $participant['is_member'] ? 'Membre' : 'Non-membre',
                $participant['base_price'],
                $participant['tps'],
                $participant['tvq'],
                $participant['total']
            ));
        }

        fclose($output);
        wp_die();
    }

    private function get_order_participants($order) {
        $participants = array();
        
        foreach ($order->get_items() as $item) {
            $participant_emails = $item->get_meta('participant_emails');
            
            if (!empty($participant_emails)) {
                foreach ($participant_emails as $email_data) {
                    $is_member = DEGB_Email_Handler::get_instance()->check_member_status($email_data['email']);
                    $base_price = $is_member ? DEGB_Price_Calculator::MEMBER_PRICE : DEGB_Price_Calculator::NON_MEMBER_PRICE;
                    $tps = $base_price * DEGB_Price_Calculator::TPS_RATE;
                    $tvq = $base_price * DEGB_Price_Calculator::TVQ_RATE;
                    $total = $base_price + $tps + $tvq;

                    $participants[] = array(
                        'name' => $email_data['name'],
                        'personal_email' => $email_data['email'],
                        'professional_email' => get_user_meta($email_data['user_id'], 'professional_email', true),
                        'is_member' => $is_member,
                        'base_price' => number_format($base_price, 2),
                        'tps' => number_format($tps, 2),
                        'tvq' => number_format($tvq, 2),
                        'total' => number_format($total, 2)
                    );
                }
            }
        }

        return $participants;
    }
}

DEGB_CSV_Exporter::get_instance();

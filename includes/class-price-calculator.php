<?php

if (!defined('ABSPATH')) {
    exit;
}

class DEGB_Price_Calculator {
    private static $instance = null;
    
    const MEMBER_PRICE = 20.00;
    const NON_MEMBER_PRICE = 30.00;
    const TPS_RATE = 0.05;
    const TVQ_RATE = 0.09975;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_calculate_participant_price', array($this, 'ajax_calculate_participant_price'));
        add_action('wp_ajax_nopriv_calculate_participant_price', array($this, 'ajax_calculate_participant_price'));
        add_filter('woocommerce_calculated_total', array($this, 'modify_cart_total'), 10, 2);
    }

    public function ajax_calculate_participant_price() {
        check_ajax_referer('calculate_price_nonce', 'nonce');

        $email = sanitize_email($_POST['email']);
        $is_member = DEGB_Email_Handler::get_instance()->check_member_status($email);
        
        $base_price = $is_member ? self::MEMBER_PRICE : self::NON_MEMBER_PRICE;
        $tps = $base_price * self::TPS_RATE;
        $tvq = $base_price * self::TVQ_RATE;
        $total = $base_price + $tps + $tvq;

        wp_send_json_success(array(
            'is_member' => $is_member,
            'base_price' => number_format($base_price, 2),
            'tps' => number_format($tps, 2),
            'tvq' => number_format($tvq, 2),
            'total' => number_format($total, 2)
        ));
    }

    public function calculate_participant_price($email) {
        // Vérification du cache d'abord
        $cached_status = DEGB_Cache_Manager::get_instance()->get_cached_member_status($email);
        
        if (false !== $cached_status) {
            return $cached_status ? self::MEMBER_PRICE : self::NON_MEMBER_PRICE;
        }

        $is_member = DEGB_Email_Handler::get_instance()->check_member_status($email);
        
        // Mise en cache du résultat
        DEGB_Cache_Manager::get_instance()->set_cached_member_status($email, $is_member);
        
        return $is_member ? self::MEMBER_PRICE : self::NON_MEMBER_PRICE;
    }

    public function integrate_with_fooevents($order_item) {
        if (!class_exists('FooEvents_Bookings')) {
            return;
        }

        $attendee_data = $order_item->get_meta('_fooevents_attendee_data');
        if (empty($attendee_data)) {
            return;
        }

        foreach ($attendee_data as $attendee) {
            $email = sanitize_email($attendee['email']);
            $is_member = DEGB_Email_Handler::get_instance()->check_member_status($email);
            
            // Mise en cache du statut membre
            DEGB_Cache_Manager::get_instance()->set_cached_member_status($email, $is_member);
            
            // Ajout du prix selon le statut
            $base_price = $is_member ? self::MEMBER_PRICE : self::NON_MEMBER_PRICE;
            
            // Mise à jour des métadonnées FooEvents
            update_post_meta(
                $order_item->get_id(),
                '_fooevents_attendee_price_' . $attendee['attendee_id'],
                $base_price
            );
        }
    }

    public function modify_cart_total($total, $cart) {
        if (!is_admin() && is_cart()) {
            foreach ($cart->get_cart() as $cart_item) {
                if (isset($cart_item['participant_emails'])) {
                    foreach ($cart_item['participant_emails'] as $email) {
                        $participant_price = $this->calculate_participant_price($email);
                        $total += $participant_price;
                    }
                }
            }
        }
        return $total;
    }

    public function get_tax_rates() {
        return array(
            'tps' => self::TPS_RATE,
            'tvq' => self::TVQ_RATE
        );
    }
}

DEGB_Price_Calculator::get_instance();

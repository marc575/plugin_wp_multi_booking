<?php

if (!defined('ABSPATH')) {
    exit;
}

class DEGB_Email_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_created_customer', array($this, 'save_professional_email'), 10, 1);
        add_action('woocommerce_save_account_details', array($this, 'update_professional_email'), 10, 1);
        add_action('show_user_profile', array($this, 'add_email_preference_field'));
        add_action('edit_user_profile', array($this, 'add_email_preference_field'));
        add_action('personal_options_update', array($this, 'save_email_preference'));
        add_action('edit_user_profile_update', array($this, 'save_email_preference'));
    }

    public function save_professional_email($customer_id) {
        if (isset($_POST['professional_email'])) {
            update_user_meta($customer_id, 'professional_email', sanitize_email($_POST['professional_email']));
        }
    }

    public function update_professional_email($customer_id) {
        if (isset($_POST['professional_email'])) {
            update_user_meta($customer_id, 'professional_email', sanitize_email($_POST['professional_email']));
        }
    }

    public function add_email_preference_field($user) {
        $email_preference = get_user_meta($user->ID, 'email_preference', true);
        ?>
        <h3><?php _e('Préférences de communication', 'dual-email-group-booking'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="email_preference"><?php _e('Recevoir les communications sur', 'dual-email-group-booking'); ?></label></th>
                <td>
                    <select name="email_preference" id="email_preference">
                        <option value="both" <?php selected($email_preference, 'both'); ?>><?php _e('Les deux emails', 'dual-email-group-booking'); ?></option>
                        <option value="personal" <?php selected($email_preference, 'personal'); ?>><?php _e('Email personnel uniquement', 'dual-email-group-booking'); ?></option>
                        <option value="professional" <?php selected($email_preference, 'professional'); ?>><?php _e('Email professionnel uniquement', 'dual-email-group-booking'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_email_preference($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'email_preference', $_POST['email_preference']);
        }
    }

    public function check_member_status($email) {
        global $wpdb;
        
        // Vérification dans la table existante des clients
        $member_check = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM {$wpdb->prefix}wcwcustomer_lookup 
            WHERE email = %s",
            $email
        ));

        if ($member_check) {
            return true;
        }

        // Vérification de l'email professionnel
        $professional_email_check = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'professional_email' 
            AND meta_value = %s",
            $email
        ));

        return !empty($professional_email_check);
    }

    public function sync_with_mailchimp($user_id) {
        if (!class_exists('MailChimp_WooCommerce_MailChimpApi')) {
            return;
        }

        $user = get_userdata($user_id);
        $personal_email = $user->user_email;
        $professional_email = get_user_meta($user_id, 'professional_email', true);
        $email_preference = get_user_meta($user_id, 'email_preference', true);

        // Récupération de l'instance MailChimp
        $mailchimp = MailChimp_WooCommerce_MailChimpApi::getInstance();
        $list_id = get_option('mailchimp-woocommerce-list_id');

        // Tags pour différencier les types d'emails
        $personal_tags = ['type' => 'personal_email'];
        $professional_tags = ['type' => 'professional_email'];

        // Synchronisation selon les préférences
        switch($email_preference) {
            case 'both':
                $this->update_mailchimp_member($mailchimp, $list_id, $personal_email, $personal_tags);
                $this->update_mailchimp_member($mailchimp, $list_id, $professional_email, $professional_tags);
                break;
            case 'personal':
                $this->update_mailchimp_member($mailchimp, $list_id, $personal_email, $personal_tags);
                break;
            case 'professional':
                $this->update_mailchimp_member($mailchimp, $list_id, $professional_email, $professional_tags);
                break;
        }
    }

    private function update_mailchimp_member($mailchimp, $list_id, $email, $tags) {
        try {
            $mailchimp->update($list_id . '/members/' . md5(strtolower($email)), [
                'email_address' => $email,
                'status' => 'subscribed',
                'merge_fields' => [
                    'TAGS' => json_encode($tags)
                ]
            ]);
        } catch (Exception $e) {
            error_log('Erreur Mailchimp: ' . $e->getMessage());
        }
    }
}

DEGB_Email_Handler::get_instance();

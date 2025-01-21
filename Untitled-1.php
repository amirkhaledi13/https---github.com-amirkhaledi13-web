<?php
/**
 * Plugin Name: Installment Payment Gateway
 * Plugin URI: https://example.com
 * Description: سیستم پرداخت اقساطی با احراز هویت شاهکار و اتصال به زرین‌پال.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// بارگذاری فایل‌های افزونه
add_action('plugins_loaded', 'initialize_installment_gateway');

function initialize_installment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Installment_Payment_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'installment_gateway';
            $this->method_title = __('Installment Payment', 'woocommerce');
            $this->method_description = __('پرداخت اقساطی با احراز هویت شاهکار.', 'woocommerce');
            $this->has_fields = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // فرم تنظیمات در پنل مدیریت
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Installment Payment Gateway', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('عنوانی که مشتری در صفحه پرداخت می‌بیند.', 'woocommerce'),
                    'default' => __('Installment Payment', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('توضیحات روش پرداخت اقساطی.', 'woocommerce'),
                    'default' => __('پرداخت اقساطی با احراز هویت و درگاه زرین‌پال.', 'woocommerce'),
                ),
            );
        }

        // پردازش پرداخت
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // ارسال اطلاعات به API شاهکار برای احراز هویت
            $verify_response = $this->verify_user_with_shahkar($order->get_billing_phone(), $order->get_billing_first_name());

            if (!$verify_response['success']) {
                wc_add_notice(__('احراز هویت شما انجام نشد. لطفاً اطلاعات خود را بررسی کنید.', 'woocommerce'), 'error');
                return;
            }

            // هدایت به صفحه پرداخت زرین‌پال
            return array(
                'result' => 'success',
                'redirect' => 'https://www.zarinpal.com/pg/StartPay/' . $this->generate_zarinpal_payment_link($order)
            );
        }

        // توابع کمکی: احراز هویت شاهکار
        private function verify_user_with_shahkar($phone, $name) {
            $api_url = 'https://shahkar-api.ir/verify';
            $api_key = 'YOUR_SHAHKAR_API_KEY';

            $response = wp_remote_post($api_url, array(
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'phone' => $phone,
                    'name' => $name,
                )),
            ));

            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body;
        }
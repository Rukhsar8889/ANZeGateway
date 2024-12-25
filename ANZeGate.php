<?php
/**
 * Plugin Name: ANZ eGate Payment Gateway for WooCommerce
 * Plugin URI: https://example.com
 * Description: A WooCommerce payment gateway plugin for ANZ eGate Developed by Rukhsar Nasir.
 * Version: 1.0.0
 * Author: Rukhsar Nasir
 * Author URI: https://www.linkedin.com/in/rukhsar-nasir/
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into WooCommerce payment gateway filter
add_filter('woocommerce_payment_gateways', 'add_anzegate_gateway');

function add_anzegate_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_ANZeGate';
    return $gateways;
}

// Include the main gateway class
add_action('plugins_loaded', 'initialize_anzegate_gateway');

function initialize_anzegate_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_ANZeGate extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'anzegate';
            $this->method_title = 'ANZ eGate';
            $this->method_description = 'Accept payments using ANZ eGate payment gateway.';

            // Define user-settable settings fields
            $this->init_form_fields();

            // Load settings
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->test_mode = $this->get_option('test_mode');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->access_code = $this->get_option('access_code');
            $this->secure_hash = $this->get_option('secure_hash');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_wc_gateway_anzegate', [$this, 'handle_response']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable ANZ eGate Payment Gateway',
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'Title that the user sees during checkout.',
                    'default' => 'Credit Card (ANZ eGate)',
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'Description that the user sees during checkout.',
                    'default' => 'Pay securely using your credit card through ANZ eGate.',
                ],
                'merchant_id' => [
                    'title' => 'Merchant ID',
                    'type' => 'text',
                    'description' => 'Your ANZ eGate Merchant ID.',
                    'default' => '',
                ],
                'access_code' => [
                    'title' => 'Access Code',
                    'type' => 'text',
                    'description' => 'Your ANZ eGate Access Code.',
                    'default' => '',
                ],
                'secure_hash' => [
                    'title' => 'Secure Hash',
                    'type' => 'text',
                    'description' => 'Your ANZ eGate Secure Hash.',
                    'default' => '',
                ],
                'test_mode' => [
                    'title' => 'Test Mode',
                    'type' => 'checkbox',
                    'label' => 'Enable Test Mode',
                    'default' => 'yes',
                ],
            ];
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $return_url = $this->get_return_url($order);
            $amount = number_format($order->get_total(), 2, '.', '') * 100; // Convert to cents
            $data = [
                'vpc_Version' => '1',
                'vpc_Command' => 'pay',
                'vpc_Merchant' => $this->merchant_id,
                'vpc_AccessCode' => $this->access_code,
                'vpc_MerchTxnRef' => $order_id,
                'vpc_OrderInfo' => $order->get_order_number(),
                'vpc_Amount' => $amount,
                'vpc_ReturnURL' => $return_url,
            ];

            $data['vpc_SecureHash'] = $this->generate_secure_hash($data);

            $redirect_url = 'https://migs.mastercard.com.au/vpcpay?' . http_build_query($data);

            return [
                'result' => 'success',
                'redirect' => $redirect_url,
            ];
        }

        private function generate_secure_hash($data)
        {
            ksort($data);
            $hash_string = '';

            foreach ($data as $key => $value) {
                if (substr($key, 0, 4) === 'vpc_' && $value !== '') {
                    $hash_string .= $key . '=' . $value . '&';
                }
            }

            $hash_string = rtrim($hash_string, '&');

            return strtoupper(hash_hmac('SHA256', $hash_string, pack('H*', $this->secure_hash)));
        }

        public function handle_response()
        {
            // Handle payment gateway response here
        }
    }
}

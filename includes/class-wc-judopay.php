<?php

use JudoPay\Init;

class WC_Judopay_Gateway extends WC_Payment_Gateway_CC
{
    public function __construct()
    {

        $this->id = 'judopay';
        $this->version = time();
        $this->plugin_url = plugins_url('', dirname(__FILE__));
        $this->icon = $this->plugin_url . '/assets/images/judopay.svg';
        $this->method_title = 'JudoPay';
        $this->method_description = 'Judopay is the industry leader in mobile-first payments. Born out of a frustration with friction-filled checkouts we built a flexible solution designed to drive sales and improve the customer experience.';

        $this->supports = [
            'products',
            'tokenization'
        ];

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->debug_mode = 'yes' === $this->get_option('debug_mode');
        $this->apiToken = $this->get_option('apiToken');
        $this->apiSecret = $this->get_option('apiSecret');
        $this->judoId = $this->get_option('judoId');
        $this->webhook_root = get_home_url(null, "wc-api/", is_ssl() ? 'https' : 'http');
        $this->threeDS_fields = ['receiptId', 'acsUrl', 'md', 'paReq'];
        $this->provider_url = $this->testmode ? 'https://gw1.judopay-sandbox.com/' : 'https://gw1.judopay.com/';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_3ds-start', [$this, 'threeds_start']);
        add_action('woocommerce_api_3ds-complete', [$this, 'threeds_complete']);
        add_action('woocommerce_before_checkout_form', [$this, 'error_notices'], 11);
        add_action('wp_enqueue_scripts', [$this, 'judopay_scripts']);

        require_once 'Judo-PHP/vendor/autoload.php';
        require_once 'Judo-PHP/src/Judopay.php';
    }

    public function judopay_scripts()
    {
        wp_enqueue_script('judopay', $this->plugin_url . '/assets/js/judopay.js', ['jquery'], $this->version, true);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'label' => 'Enable Judopay Gateway',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'JudoPay',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Reliable, secure & seamless payment gateway.',
            ],
            'testmode' => [
                'title' => 'Test mode',
                'label' => 'Enable Test Mode',
                'type' => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test API keys.',
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'debug_mode' => [
                'title' => 'Debug mode',
                'label' => 'Enable Debug Mode',
                'type' => 'checkbox',
                'description' => 'If enabled, all transaction data will be saved in WooCommerce Log.',
                'desc_tip' => true,
            ],
            'apiToken' => [
                'title' => 'apiToken',
                'type' => 'text'
            ],
            'apiSecret' => [
                'title' => 'apiSecret',
                'type' => 'text'
            ],
            'judoId' => [
                'title' => 'judoId',
                'type' => 'text'
            ]
        ];
    }

    public function validate_fields()
    {
        $return = true;
        if (!empty($_POST['wc-judopay-payment-token'])) {
            return $return;
        }
        if (isset($_POST['judopay-card-number']) && empty($_POST['judopay-card-number'])) {
            wc_add_notice('Card Number is required.', 'error');
            $return = false;
        }
        if (isset($_POST['judopay-card-expiry']) && empty($_POST['judopay-card-expiry'])) {
            wc_add_notice('Expiry Date is required.', 'error');
            $return = false;
        }
        if (isset($_POST['judopay-card-cvc']) && empty($_POST['judopay-card-cvc'])) {
            wc_add_notice('Card Code is required.', 'error');
            $return = false;
        }
        if (isset($_POST['judopay-card-startDate']) && empty($_POST['judopay-card-startDate'])) {
            wc_add_notice('Start Date is required.', 'error');
            $return = false;
        }
        if (isset($_POST['judopay-card-issueNumber']) && empty($_POST['judopay-card-issueNumber'])) {
            wc_add_notice('Issue Number is required.', 'error');
            $return = false;
        }
        return $return;

    }

    public function field_name($name)
    {
        return ' name="' . esc_attr($this->id . '-' . $name) . '" ';
    }

    public function add_payment_method()
    {
        $url = $this->provider_url . 'transactions/savecard';
        $judopay = new \Judopay(
            [
                'apiToken' => $this->apiToken,
                'apiSecret' => $this->apiSecret,
                'judoId' => $this->judoId
            ]
        );
        $yourConsumerReference = get_user_meta(get_current_user_id(), 'judopay_consumer_reference', true) ? get_user_meta(get_current_user_id(), 'judopay_consumer_reference', true) : $this->get_guid();
        $body_args = [
            'yourConsumerReference' => $yourConsumerReference,
            'cv2' => $_POST['judopay-card-cvc'],
            'cardNumber' => $_POST['judopay-card-number'],
            'expiryDate' => $_POST['judopay-card-expiry']
        ];
        if (!empty($_POST['judopay-card-startDate']))
            $body_args['startDate'] = $_POST['judopay-card-startDate'];
        if (!empty($_POST['judopay-card-issueNumber']))
            $body_args['issueNumber'] = $_POST['judopay-card-issueNumber'];

        $args = [
            'headers' => $judopay->get('request')->getHeaders(),
            'body' => json_encode($body_args)
        ];
        $response = wp_remote_post($url, $args);
        $this->log($response);
        if (is_wp_error($response))
            wc_add_notice($response->get_error_message(), 'error');

        $body = json_decode($response['body'], true);
        if ($body['result'] === 'Success') {
            $this->tokenize($body);
        } else {
            wc_add_notice($body['message'], 'error');
        }
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $judopay = new \Judopay(
            [
                'apiToken' => $this->apiToken,
                'apiSecret' => $this->apiSecret,
                'judoId' => $this->judoId
            ]
        );
        if (get_current_user_id() !== 0) {
            $yourConsumerReference = get_user_meta(get_current_user_id(), 'judopay_consumer_reference', true) ? get_user_meta(get_current_user_id(), 'judopay_consumer_reference', true) : $this->get_guid();
        } else {
            $yourConsumerReference = $this->get_guid();
        }
        $payment_args = [
            'judoId' => $this->judoId,
            'yourConsumerReference' => $yourConsumerReference,
            'yourPaymentReference' => $order_id . ':' . time(),
            'amount' => $order->get_total(),
            'currency' => $order->get_currency()
        ];

        if (isset($_POST['wc-judopay-payment-token']) && 'new' !== $_POST['wc-judopay-payment-token']) {
            $token = WC_Payment_Tokens::get($_POST['wc-judopay-payment-token']);
            $payment = $judopay->getModel('TokenPayment');
            $payment_args['consumerToken'] = $token->get_meta('consumerToken');
            $payment_args['yourConsumerReference'] = $token->get_meta('yourConsumerReference');
            $payment_args['cardToken'] = $token->get_token();
            $payment_args['cv2'] = $token->get_meta('cv2');
        } else {
            $payment = $judopay->getModel('Payment');
            $payment_args['cardNumber'] = $_POST['judopay-card-number'];
            $payment_args['expiryDate'] = $_POST['judopay-card-expiry'];
            $payment_args['cv2'] = $_POST['judopay-card-cvc'];
            if (isset($_POST['judopay-card-startDate']) && isset($_POST['judopay-card-issueNumber'])) {
                $payment_args['startDate'] = $_POST['judopay-card-startDate'];
                $payment_args['issueNumber'] = $_POST['judopay-card-issueNumber'];
            }
        }
        $payment->setAttributeValues($payment_args);
        try {
            $response = $payment->create();
            $this->log($response);
            if ($response['result'] === 'Success') {
                $order->payment_complete();
                $order->add_order_note('Order was successfully paid by JudoPay.');
                WC()->cart->empty_cart();
                update_post_meta($order_id, 'judopay_transaction_data', $response);
                if ($_POST['wc-judopay-new-payment-method'] === 'true' && isset($response['cardDetails']['cardToken'])) {
                    $this->tokenize($response, get_current_user_id());
                }
                return [
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                ];
            } elseif ($response['result'] === 'Requires 3D Secure') {
                if (WC()->session->get('3Dsecure'))
                    WC()->session->__unset('3Dsecure');

                foreach ($this->threeDS_fields as $field) {
                    if (!empty($response[$field])) {
                        $session[$field] = $response[$field];
                    }
                }
                if ($_POST['wc-judopay-new-payment-method'] === 'true')
                    $session['tokenize'] = true;

                $session['user_id'] = get_current_user_id();
                WC()->session->set('3Dsecure', $session);

                return [
                    'result' => 'success',
                    'redirect' => $this->webhook_root . '3ds-start/'
                ];
            } else {
                update_post_meta($order_id, 'judopay_transaction_data', $response);
                if (!empty($response['message'])) {
                    wc_add_notice($response['message'], 'error');
                } else {
                    wc_add_notice('There were some problems while processing your payment.', 'error');
                }
                return;
            }
        } catch (\Judopay\Exception\ValidationError $e) {
            $this->debug_mode ? wc_add_notice($e->getSummary(), 'error') : wc_add_notice($e->getMessage(), 'error');
        } catch (\Judopay\Exception\ApiException $e) {
            $this->debug_mode ? wc_add_notice($e->getSummary(), 'error') : wc_add_notice($e->getMessage(), 'error');
        } catch (\Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
        }

        $this->log($e);
        return;
    }

    public function get_guid()
    {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid = chr(123)
                . substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12)
                . chr(125);
            return $uuid;
        }
    }

    public function log($data)
    {
        if ($this->debug_mode) {
            $logger = wc_get_logger();
            if (is_string($data)) {
                $logger->debug($data, ['source' => $this->id]);
            } elseif (is_object($data) || is_array($data)) {
                $logger->debug(print_r($data, true), ['source' => $this->id]);
            }
        }
    }

    public function threeds_start()
    {
        session_start();
        require Init::$plugin_path . 'template-parts/html-3ds-post-request.php';
        die;
    }

    public function threeds_complete()
    {
        $data = explode(';', base64_decode($_GET['data']));
        $body_args = [
            'PaRes' => $_POST['PaRes']
        ];
        if (!empty($_POST['MD']))
            $body_args['MD'] = $_POST['MD'];

        $receiptId = $data[0];
        $user_id = $data[1];

        $url = $this->provider_url . 'transactions/' . $receiptId;
        $judopay = new \Judopay(
            [
                'apiToken' => $this->apiToken,
                'apiSecret' => $this->apiSecret,
                'judoId' => $this->judoId
            ]
        );
        $args = [
            'headers' => $judopay->get('request')->getHeaders(),
            'body' => json_encode($body_args),
            'method' => 'PUT',
            'timeout' => 20
        ];
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();
            require Init::$plugin_path . 'template-parts/html-3ds-response.php';
            die;
        }

        $body = json_decode($response['body'], true);

        if ($response['response']['code'] !== 200) {
            if (isset($body['message']) && isset($body['code'])) {
                $error_code = $body['code'];
                $error_message = $body['message'];
            } else {
                $error_code = $response['code'];
                $error_message = $response['message'];
            }
            require Init::$plugin_path . 'template-parts/html-3ds-response.php';
        } else {
            $order_id = explode(':', $body['yourPaymentReference'])[0];
            $order = wc_get_order($order_id);
            if ($body['result'] === 'Success') {
                if ($body['threeDSecure']['attempted'] === true && $body['threeDSecure']['result'] === 'PASSED') {
                    if (isset($data[2]) && $data[2] === 'tokenize') {
                        $this->tokenize($body, $data[1]);
                    }
                    $order->payment_complete($body['receiptId']);
                    $order->update_meta_data('judopay_transaction_data', $body);
                    $order->save();
                    WC()->cart->empty_cart();
                    wp_safe_redirect($order->get_checkout_order_received_url());
                } else {
                    $order->add_order_note('3Dsecure returned UNKNOWN status. Please contact us.', true);
                    $error_message = '3Dsecure returned UNKNOWN status. Please contact us.';
                    require Init::$plugin_path . 'template-parts/html-3ds-response.php';
                }
            } else {
                $order->update_meta_data('judopay_transaction_data', $body);
                $order->save();
                $error_message = $body['message'];
                require Init::$plugin_path . 'template-parts/html-3ds-response.php';
            }
        }
        die;
    }

    public function error_notices()
    {
        if (isset($_POST['judopay_error_code']) || isset($_POST['judopay_error_message'])) {
            $message = '';
            if (isset($_POST['judopay_error_code'])) {
                $message .= $_POST['judopay_error_code'] . ' - ';
            }
            $message .= $_POST['judopay_error_message'];
            wc_print_notice($message, 'error');
        }

    }

    public function tokenize($response, $user_id = false)
    {
        if (!($response))
            return;

        if ($user_id === false && get_current_user_id() === 0) {
            return;
        } else {
            $user_id = get_current_user_id();
        }

        $last4 = $response['cardDetails']['cardLastfour'];
        $exp_year = '20' . substr($response['cardDetails']['endDate'], -2);
        $exp_month = substr($response['cardDetails']['endDate'], 0, -2);
        $saved_tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
        foreach ($saved_tokens as $saved_token) {
            if ($saved_token->get_last4() === $last4) {
                $token = new WC_Payment_Token_CC($saved_token->get_id());
                break;
            }
        }
        if (!isset($token))
            $token = new WC_Payment_Token_CC();

        $token->set_token($response['cardDetails']['cardToken']);
        $token->set_gateway_id($this->id);
        $token->set_last4($last4);
        $token->set_expiry_year($exp_year);
        $token->set_expiry_month($exp_month);
        $token->set_card_type(strtolower($response['cardDetails']['cardScheme']));
        $token->add_meta_data('cv2', $_POST['judopay-card-cvc'], true);
        $token->add_meta_data('consumerToken', $response['consumer']['consumerToken'], true);
        $token->add_meta_data('yourConsumerReference', $response['consumer']['yourConsumerReference'], true);
        $token->set_user_id($user_id);
        $token->save();
    }
}
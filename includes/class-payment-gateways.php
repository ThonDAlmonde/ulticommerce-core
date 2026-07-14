<?php

defined( 'ABSPATH' ) || exit;

abstract class Ulti_Payment_Gateway {

    protected $id;
    protected $title;
    protected $description;
    protected $supports_redirect = false;
    protected $supports_webhook  = false;

    abstract public function process_payment( $order_id );

    public function __construct( $id, $title ) {
        $this->id    = $id;
        $this->title = $title;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_description() {
        return $this->description ?: '';
    }

    public function can_redirect() {
        return $this->supports_redirect;
    }

    public function has_webhook() {
        return $this->supports_webhook;
    }

    public function get_redirect_url( $order_id ) {
        return '';
    }

    public function handle_webhook( $payload = null ) {
        return false;
    }

    protected function mark_as_paid( $order_id, $transaction_id = '' ) {
        $old = get_post_meta( $order_id, '_order_status', true ) ?: 'new';
        update_post_meta( $order_id, '_order_status', 'paid' );
        if ( $transaction_id ) {
            update_post_meta( $order_id, '_order_transaction_id', $transaction_id );
        }
        do_action( 'ulti_order_status_changed', $order_id, $old, 'paid' );
        do_action( 'ulti_order_paid', $order_id );
    }
}

/* ====== BANK WIRE TRANSFER ====== */

class Ulti_Gateway_BankWire extends Ulti_Payment_Gateway {

    public function __construct() {
        parent::__construct( 'bank_wire', 'Bank Wire Transfer' );
        $this->description = esc_html__( 'Pay via bank wire transfer. After placing your order, transfer the total amount to our bank account. Your order will be processed once payment is confirmed.', 'ulticommerce-core' );
    }

    public function process_payment( $order_id ) {
        update_post_meta( $order_id, '_order_payment_method', $this->id );
        update_post_meta( $order_id, '_order_status', 'pending_payment' );
        update_post_meta( $order_id, '_order_payment_title', $this->title );
        return [
            'result'   => 'success',
            'redirect' => add_query_arg( 'order', get_the_title( $order_id ), get_permalink( get_page_by_template( 'page-confirmation.php' ) ) ?: home_url() ),
        ];
    }

    public static function get_bank_details() {
        $saved = get_option( 'ulti_bank_wire_details', [] );
        return apply_filters( 'ulti_bank_wire_details', [
            'bank_name'      => $saved['bank_name'] ?? '',
            'account_name'   => $saved['account_holder'] ?? '',
            'account_no'     => $saved['account_number'] ?? '',
            'branch'         => $saved['branch'] ?? '',
            'country'        => $saved['country'] ?? '',
            'swift'          => $saved['swift'] ?? '',
            'ifsc'           => $saved['ifsc'] ?? '',
            'iban'           => $saved['iban'] ?? '',
            'currency'       => get_option( 'ulti_default_currency', 'USD' ),
        ] );
    }
}

/* ====== GATEWAY REGISTRY ====== */

class Ulti_Payment_Gateways {

    private static $gateways = [];

    public static function init() {
        self::register( 'bank_wire', new Ulti_Gateway_BankWire() );
        do_action( 'ulti_register_payment_gateways' );
    }

    public static function register( $id, $gateway ) {
        self::$gateways[ $id ] = $gateway;
    }

    public static function get_all() {
        return self::$gateways;
    }

    public static function get( $id ) {
        return self::$gateways[ $id ] ?? null;
    }

    public static function get_enabled() {
        return self::$gateways;
    }

    public static function process( $gateway_id, $order_id ) {
        $gateway = self::get( $gateway_id );
        if ( ! $gateway ) {
            return [ 'result' => 'error', 'message' => 'Invalid gateway.' ];
        }
        return $gateway->process_payment( $order_id );
    }
}

add_action( 'init', [ 'Ulti_Payment_Gateways', 'init' ], 20 );

add_action( 'rest_api_init', function () {
    register_rest_route( 'wpc/v1', '/payment/webhook', [
        'methods'             => 'POST',
        'callback'            => 'ulti_payment_webhook_handler',
        'permission_callback' => '__return_true',
    ] );
} );

function ulti_payment_webhook_handler( $request ) {
    $payload = $request->get_body();

    foreach ( Ulti_Payment_Gateways::get_all() as $gateway ) {
        if ( $gateway->has_webhook() && $gateway->handle_webhook( $payload ) ) {
            return [ 'status' => 'ok' ];
        }
    }
    return new WP_Error( 'webhook_error', 'Webhook processing failed: no handler matched', [ 'status' => 400 ] );
}

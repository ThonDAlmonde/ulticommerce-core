<?php

defined( 'ABSPATH' ) || exit;

class Ultico_Webhook_Handler {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_webhook_route' ] );
        add_filter( 'ultico_register_payment_gateways', [ $this, 'register_custom_webhook_gateway' ] );
    }

    public function register_webhook_route() {
        register_rest_route( 'ultico/v1', '/payment/confirm', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_confirm' ],
            'permission_callback' => [ $this, 'verify_webhook_secret' ],
        ] );
    }

    public function verify_webhook_secret( $request ) {
        $secret = get_option( 'ultico_webhook_secret', '' );
        if ( ! $secret ) {
            return true;
        }
        $provided = $request->get_header( 'X-Webhook-Secret' );
        return $provided && hash_equals( $secret, $provided );
    }

    public function handle_confirm( $request ) {
        $body = $request->get_json_params();
        $order_id       = intval( $body['order_id'] ?? 0 );
        $transaction_id = sanitize_text_field( $body['transaction_id'] ?? '' );
        $status         = sanitize_text_field( $body['status'] ?? 'paid' );

        if ( ! $order_id || ! get_post( $order_id ) || get_post_type( $order_id ) !== 'ultico_order' ) {
            return new WP_Error( 'invalid_order', 'Invalid order ID', [ 'status' => 400 ] );
        }

        update_post_meta( $order_id, '_order_transaction_id', $transaction_id );
        update_post_meta( $order_id, '_order_paid_date', current_time( 'mysql' ) );

        $old_status = get_post_meta( $order_id, '_order_status', true ) ?: 'new';
        $new_status = $status === 'paid' ? 'paid' : $status;
        update_post_meta( $order_id, '_order_status', $new_status );
        do_action( 'ultico_order_status_changed', $order_id, $old_status, $new_status );
        if ( $new_status === 'paid' && $old_status !== 'paid' ) {
            do_action( 'ultico_order_paid', $order_id );
        }

        return [ 'status' => 'ok', 'order_id' => $order_id, 'new_status' => $new_status ];
    }
}

new Ultico_Webhook_Handler();

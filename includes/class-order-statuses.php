<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Order_Statuses {

    private static $final_statuses = [ 'canceled', 'refunded', 'delivered' ];

    private static $statuses = [
        'new'               => 'New',
        'pending_payment'   => 'Pending payment',
        'paid'              => 'Paid',
        'refunded'          => 'Refunded',
        'refund_requested'  => 'Refund requested',
        'canceled'          => 'Canceled',
        'on_hold'           => 'On hold',
        'deducted'          => 'Deducted',
        'picking'           => 'Picking',
        'packing'           => 'Packing',
        'dispatched'        => 'Dispatched',
        'shipping'          => 'Shipping',
        'delivered'         => 'Delivered',
    ];

    private static $allowed_transitions = [
        'new'              => [ 'pending_payment', 'canceled', 'on_hold' ],
        'pending_payment'  => [ 'paid', 'canceled', 'on_hold' ],
        'paid'             => [ 'deducted', 'refunded', 'on_hold' ],
        'on_hold'          => [ 'pending_payment', 'canceled' ],
        'deducted'         => [ 'picking', 'on_hold' ],
        'picking'          => [ 'packing', 'on_hold' ],
        'packing'          => [ 'dispatched', 'on_hold' ],
        'dispatched'       => [ 'shipping' ],
        'shipping'         => [ 'delivered' ],
        'delivered'        => [ 'refund_requested' ],
        'refund_requested' => [ 'refunded' ],
        'canceled'         => [],
        'refunded'         => [],
    ];

    public function __construct() {
        add_action( 'init', [ $this, 'register_statuses' ] );
        add_action( 'wp_ajax_ulti_update_order_status', [ $this, 'ajax_update_status' ] );
        add_action( 'ulti_order_status_changed', [ $this, 'handle_deduct_stock' ], 10, 3 );
        add_action( 'ulti_order_status_changed', [ $this, 'handle_stock_on_status_change' ], 20, 3 );
        add_action( 'ulti_order_paid', [ $this, 'notify_paid_order' ], 20, 1 );
        add_action( 'ulti_order_delivery_status_changed', [ $this, 'notify_delivery_update' ], 10, 2 );
    }

    public static function get_statuses() {
        return apply_filters( 'ulti_custom_order_statuses', self::$statuses );
    }

    public static function get_final_statuses() {
        return apply_filters( 'ulti_final_order_statuses', self::$final_statuses );
    }

    public static function is_final_status( $status ) {
        return in_array( $status, self::get_final_statuses(), true );
    }

    public static function get_label( $status ) {
        $all = self::get_statuses();
        return isset( $all[ $status ] ) ? $all[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );
    }

    public static function get_allowed_transitions( $from ) {
        $map = apply_filters( 'ulti_allowed_status_transitions', self::$allowed_transitions );
        return $map[ $from ] ?? [];
    }

    public static function can_transition( $from, $to ) {
        if ( self::is_final_status( $from ) && $from !== $to ) {
            return false;
        }
        $allowed = self::get_allowed_transitions( $from );
        return in_array( $to, $allowed, true );
    }

    public static function get_badge_class( $status ) {
        $map = [
            'new'               => 'badge-primary',
            'pending_payment'   => 'badge-warning',
            'paid'              => 'badge-success',
            'refunded'          => 'badge-error',
            'refund_requested'  => 'badge-warning',
            'canceled'          => '',
            'on_hold'           => 'badge-warning',
            'deducted'          => 'badge-primary',
            'picking'           => 'badge-primary',
            'packing'           => 'badge-primary',
            'dispatched'        => 'badge-primary',
            'shipping'          => 'badge-primary',
            'delivered'         => 'badge-success',
        ];
        $map = apply_filters( 'ulti_order_status_badge_classes', $map );
        return $map[ $status ] ?? 'badge-primary';
    }

    public function register_statuses() {
        foreach ( self::get_statuses() as $slug => $label ) {
            register_post_status( $slug, [
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
            ] );
        }
    }

    public function render_status_dropdown( $post ) {
        $current = get_post_meta( $post->ID, '_order_status', true ) ?: 'new';
        $is_final = self::is_final_status( $current );
        $allowed = self::get_allowed_transitions( $current );
        ?>
        <?php if ( $is_final ) : ?>
        <select id="ulti-order-status-select" name="_order_status" class="widefat" disabled>
            <option value="<?php echo esc_attr( $current ); ?>" selected><?php echo esc_html( self::get_label( $current ) ); ?></option>
        </select>
        <p style="color:#999;font-size:12px;margin:4px 0 0;"><?php esc_html_e( 'Final status — cannot be changed.', 'ulticommerce-core' ); ?></p>
        <?php else : ?>
        <select id="ulti-order-status-select" name="_order_status" class="widefat">
            <?php foreach ( self::get_statuses() as $slug => $label ) :
                $disabled = ! in_array( $slug, $allowed, true ) && $slug !== $current ? 'disabled' : '';
            ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?> <?php echo esc_attr( $disabled ); ?>>
                    <?php echo esc_html( $label ); ?><?php echo $disabled ? ' — ' . esc_html__( 'not allowed', 'ulticommerce-core' ) : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="button" id="ulti-order-status-update" style="margin-top:8px;"
                data-post-id="<?php echo esc_attr( $post->ID ); ?>">
            <?php esc_html_e( 'Update Status', 'ulticommerce-core' ); ?>
        </button>
        <span class="spinner" style="float:none;margin-top:8px;"></span>
        <p style="color:#999;font-size:11px;margin:4px 0 0;"><?php esc_html_e( 'Only allowed transitions are shown as active.', 'ulticommerce-core' ); ?></p>
        <?php wp_enqueue_script( 'ulticommerce-admin' ); ?>
        <?php wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    $("#ulti-order-status-update").on("click", function() {
        var btn = $(this);
        var status = $("#ulti-order-status-select").val();
        var postId = btn.data("post-id");
        var spinner = btn.siblings(".spinner");
        spinner.addClass("is-active");
        $.post(ajaxurl, {
            action: "ulti_update_order_status",
            post_id: postId,
            status: status,
            _ajax_nonce: "' . esc_js( wp_create_nonce( 'ulti_update_status_' . $post->ID ) ) . '"
        }, function(resp) {
            spinner.removeClass("is-active");
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : "' . esc_js( __( 'Error updating status.', 'ulticommerce-core' ) ) . '");
            }
        });
    });
});
' ); ?>
        <?php endif; ?>
        <?php
    }

    public function ajax_update_status() {
        $post_id = intval( $_POST['post_id'] );
        $status  = sanitize_text_field( $_POST['status'] );

        check_ajax_referer( 'ulti_update_status_' . $post_id, '_ajax_nonce' );

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $old_status = get_post_meta( $post_id, '_order_status', true ) ?: 'new';

        if ( ! self::can_transition( $old_status, $status ) ) {
            wp_send_json_error( [ 'message' => sprintf(
                esc_html__( 'Cannot change from "%s" to "%s".', 'ulticommerce-core' ),
                esc_html( self::get_label( $old_status ) ),
                esc_html( self::get_label( $status ) )
            ) ] );
        }

        update_post_meta( $post_id, '_order_status', $status );
        UltiCommerce_Order_CPT::log_status_change( $post_id, $status );
        do_action( 'ulti_order_status_changed', $post_id, $old_status, $status );

        if ( $status === 'paid' && $old_status !== 'paid' ) {
            do_action( 'ulti_order_paid', $post_id );
        }

        $delivery_statuses = [ 'picking', 'packing', 'dispatched', 'shipping', 'delivered' ];
        if ( in_array( $status, $delivery_statuses, true ) ) {
            do_action( 'ulti_order_delivery_status_changed', $post_id, $status );
        }

        wp_send_json_success();
    }

    public function handle_deduct_stock( $order_id, $old_status, $new_status ) {
        $target = get_option( 'ulti_deduct_stock_on_status', 'paid' );
        if ( $new_status !== $target ) return;
        $already = get_post_meta( $order_id, '_order_stock_deducted', true );
        if ( $already ) return;
        $this->deduct_stock_for_order( $order_id );
        update_post_meta( $order_id, '_order_stock_deducted', 1 );
    }

    public function deduct_stock_for_order( $order_id ) {
        $items = get_post_meta( $order_id, '_order_items', true ) ?: [];
        foreach ( $items as $item ) {
            $pid = intval( $item['product_id'] ?? 0 );
            $qty = intval( $item['quantity'] ?? 0 );
            if ( $pid && $qty ) {
                $stock = (int) get_post_meta( $pid, '_product_quantity', true );
                update_post_meta( $pid, '_product_quantity', max( 0, $stock - $qty ) );
            }
        }
    }

    public function notify_paid_order( $order_id ) {
        if ( function_exists( 'ulti_send_order_email' ) ) {
            ulti_send_order_email( $order_id, 'payment_received' );
        }
        if ( function_exists( 'ulti_generate_invoice_pdf' ) ) {
            ulti_generate_invoice_pdf( $order_id );
        }
    }

    public function notify_delivery_update( $order_id, $new_status ) {
        if ( function_exists( 'ulti_send_order_email' ) ) {
            ulti_send_order_email( $order_id, 'delivery_update' );
        }
    }

    public function handle_stock_on_status_change( $order_id, $old_status, $new_status ) {
        $restore_statuses = apply_filters( 'ulti_restore_stock_statuses', [ 'refunded', 'canceled' ] );
        $items = get_post_meta( $order_id, '_order_items', true ) ?: [];
        $deducted = get_post_meta( $order_id, '_order_stock_deducted', true );

        if ( in_array( $new_status, $restore_statuses, true ) && ! in_array( $old_status, $restore_statuses, true ) && $deducted ) {
            foreach ( $items as $item ) {
                $pid = intval( $item['product_id'] ?? 0 );
                $qty = intval( $item['quantity'] ?? 0 );
                if ( $pid && $qty ) {
                    $stock = (int) get_post_meta( $pid, '_product_quantity', true );
                    update_post_meta( $pid, '_product_quantity', $stock + $qty );
                }
            }
            update_post_meta( $order_id, '_order_stock_deducted', 0 );
        }
    }
}

new UltiCommerce_Order_Statuses();

<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Order_CPT {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_filter( 'manage_order_posts_columns', [ $this, 'custom_columns' ] );
        add_action( 'manage_order_posts_custom_column', [ $this, 'custom_column_data' ], 10, 2 );
        add_filter( 'manage_edit-order_sortable_columns', [ $this, 'sortable_columns' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_order', [ $this, 'save_order_meta' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
        add_filter( 'bulk_actions-edit-order', [ $this, 'bulk_status_actions' ] );
        add_action( 'wp_ajax_ulti_bulk_update_order_status', [ $this, 'ajax_bulk_update_status' ] );
        add_action( 'restrict_manage_posts', [ $this, 'admin_filters' ] );
        add_filter( 'parse_query', [ $this, 'apply_admin_filters' ] );
        add_action( 'admin_action_ulti_print_invoice', [ $this, 'print_invoice' ] );
        add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
        add_action( 'wp_ajax_ulti_add_order_note', [ $this, 'ajax_add_note' ] );
        add_action( 'wp_trash_post', [ $this, 'prevent_trash_final_status' ] );
        add_action( 'init', [ $this, 'schedule_cron_events' ] );
        add_action( 'ulti_auto_cancel_expired_orders', [ $this, 'auto_cancel_expired_orders' ] );
        add_action( 'handle_bulk_actions-edit-order', [ $this, 'handle_bulk_status_action' ], 10, 3 );
        add_action( 'admin_notices', [ $this, 'bulk_action_admin_notice' ] );
        add_action( 'template_redirect', [ $this, 'handle_print_invoice' ] );
    }

    public function schedule_cron_events() {
        if ( ! wp_next_scheduled( 'ulti_auto_cancel_expired_orders' ) ) {
            wp_schedule_event( time(), 'hourly', 'ulti_auto_cancel_expired_orders' );
        }
    }

    public function auto_cancel_expired_orders() {
        $orders = get_posts( [
            'post_type'      => 'order',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [
                [
                    'key'   => '_order_status',
                    'value' => 'pending_payment',
                ],
            ],
            'date_query' => [
                [
                    'column'    => 'post_date',
                    'before'    => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
                    'inclusive' => true,
                ],
            ],
        ] );
        foreach ( $orders as $order ) {
            $old = get_post_meta( $order->ID, '_order_status', true ) ?: 'new';
            update_post_meta( $order->ID, '_order_status', 'canceled' );
            self::log_status_change( $order->ID, 'canceled' );
            do_action( 'ulti_order_status_changed', $order->ID, $old, 'canceled' );
            if ( function_exists( 'ulti_send_order_email' ) ) {
                ulti_send_order_email( $order->ID, 'canceled' );
            }
        }
    }

    public function register_post_type() {
        register_post_type( 'order', [
            'labels' => [
                'name'               => __( 'Orders', 'ulticommerce' ),
                'singular_name'      => __( 'Order', 'ulticommerce' ),
                'add_new'            => __( 'Add Order', 'ulticommerce' ),
                'add_new_item'       => __( 'Add New Order', 'ulticommerce' ),
                'edit_item'          => __( 'Edit Order', 'ulticommerce' ),
                'view_item'          => __( 'View Order', 'ulticommerce' ),
                'search_items'       => __( 'Search Orders', 'ulticommerce' ),
                'not_found'          => __( 'No orders found', 'ulticommerce' ),
                'not_found_in_trash' => __( 'No orders in Trash', 'ulticommerce' ),
                'all_items'          => __( 'All Orders', 'ulticommerce' ),
                'menu_name'          => __( 'Orders', 'ulticommerce' ),
            ],
            'public'              => false,
            'publicly_queryable'  => true,
            'query_var'           => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-cart',
            'menu_position'       => 6,
            'supports'            => [ 'title' ],
            'show_in_rest'        => true,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
        ] );
    }

    public function custom_columns( $columns ) {
        return [
            'cb'          => '<input type="checkbox">',
            'order_num'   => esc_html__( 'Order', 'ulticommerce' ),
            'customer'    => esc_html__( 'Customer', 'ulticommerce' ),
            'payment'     => esc_html__( 'Payment', 'ulticommerce' ),
            'shipping'    => esc_html__( 'Shipping', 'ulticommerce' ),
            'total'       => esc_html__( 'Total', 'ulticommerce' ),
            'status'      => esc_html__( 'Status', 'ulticommerce' ),
            'date'        => esc_html__( 'Date', 'ulticommerce' ),
        ];
    }

    public function sortable_columns( $columns ) {
        $columns['total'] = 'total';
        return $columns;
    }

    public function custom_column_data( $column, $post_id ) {
        switch ( $column ) {
            case 'order_num':
                echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '"><strong>#' . esc_html( get_the_title( $post_id ) ) . '</strong></a>';
                break;
            case 'customer':
                echo esc_html( get_post_meta( $post_id, '_order_first_name', true ) . ' ' . get_post_meta( $post_id, '_order_last_name', true ) );
                echo '<br><small style="color:#999;">' . esc_html( get_post_meta( $post_id, '_order_email', true ) ) . '</small>';
                break;
            case 'payment':
                $pm = get_post_meta( $post_id, '_order_payment_title', true ) ?: get_post_meta( $post_id, '_order_payment_method', true );
                echo esc_html( $pm ?: '—' );
                $txn = get_post_meta( $post_id, '_order_transaction_id', true );
                if ( $txn ) echo '<br><small>' . esc_html( $txn ) . '</small>';
                break;
            case 'shipping':
                $method = get_post_meta( $post_id, '_order_shipping_method', true );
                $cost   = get_post_meta( $post_id, '_order_shipping_cost', true );
                echo esc_html( $method ?: '—' );
                if ( $cost ) echo '<br><small>' . esc_html( ulti_format_price( $cost ) ) . '</small>';
                break;
            case 'total':
                $total = get_post_meta( $post_id, '_order_total', true );
                echo esc_html( ulti_format_price( $total ?: 0 ) );
                break;
            case 'status':
                $status  = get_post_meta( $post_id, '_order_status', true ) ?: 'new';
                $class   = UltiCommerce_Order_Statuses::get_badge_class( $status );
                $is_final = UltiCommerce_Order_Statuses::is_final_status( $status );
                ?>
                <span class="uti-badge order-status-badge <?php echo esc_attr( $class ); ?> <?php echo $is_final ? 'status-final' : 'status-editable'; ?>"
                      data-post-id="<?php echo esc_attr( $post_id ); ?>"
                      data-nonce="<?php echo esc_attr( wp_create_nonce( 'ulti_update_status_' . $post_id ) ); ?>"
                      title="<?php echo $is_final ? esc_attr__( 'Final status', 'ulticommerce' ) : esc_attr__( 'Click to change', 'ulticommerce' ); ?>"
                      style="cursor:<?php echo $is_final ? 'default' : 'pointer'; ?>;">
                    <?php echo esc_html( UltiCommerce_Order_Statuses::get_label( $status ) ); ?>
                </span>
                <?php if ( ! $is_final ) :
                    $allowed = UltiCommerce_Order_Statuses::get_allowed_transitions( $status );
                ?>
                <select class="order-status-dropdown" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ulti_update_status_' . $post_id ) ); ?>" style="display:none;width:100%;">
                    <?php foreach ( UltiCommerce_Order_Statuses::get_statuses() as $slug => $lbl ) :
                        $option_disabled = $slug !== $status && ! in_array( $slug, $allowed, true ) ? 'disabled' : '';
                    ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status, $slug ); ?> <?php echo esc_attr( $option_disabled ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php
                break;
        }
    }

    public function row_actions( $actions, $post ) {
        if ( $post->post_type !== 'order' ) return $actions;
        $actions['invoice'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=ulti_print_invoice&post=' . $post->ID ), 'print_invoice_' . $post->ID ) ) . '" target="_blank">' . esc_html__( 'Invoice', 'ulticommerce' ) . '</a>';
        $order_num = get_post_meta( $post->ID, '_order_number', true );
        if ( $order_num ) {
            $pdf_dir = wp_upload_dir()['basedir'] . '/invoices/INV-' . $order_num . '.pdf';
            if ( file_exists( $pdf_dir ) ) {
                $pdf_url = wp_upload_dir()['baseurl'] . '/invoices/INV-' . $order_num . '.pdf';
                $actions['invoice_pdf'] = '<a href="' . esc_url( $pdf_url ) . '" target="_blank" download>' . esc_html__( 'PDF Invoice', 'ulticommerce' ) . '</a>';
            }
        }

        $status = get_post_meta( $post->ID, '_order_status', true ) ?: 'new';
        if ( UltiCommerce_Order_Statuses::is_final_status( $status ) ) {
            unset( $actions['trash'] );
        }

        return $actions;
    }

    public function admin_filters( $post_type ) {
        if ( $post_type !== 'order' ) return;
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $status = isset( $_GET['order_status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['order_status_filter'] ) ) : '';
        $date   = isset( $_GET['order_date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['order_date_filter'] ) ) : '';
        $search = sanitize_text_field( wp_unslash( $_GET['order_number_search'] ?? '' ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>
        <select name="order_status_filter">
            <option value=""><?php esc_html_e( 'All statuses', 'ulticommerce' ); ?></option>
            <?php foreach ( UltiCommerce_Order_Statuses::get_statuses() as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="order_date_filter">
            <option value=""><?php esc_html_e( 'All dates', 'ulticommerce' ); ?></option>
            <option value="today" <?php selected( $date, 'today' ); ?>><?php esc_html_e( 'Today', 'ulticommerce' ); ?></option>
            <option value="this_week" <?php selected( $date, 'this_week' ); ?>><?php esc_html_e( 'This week', 'ulticommerce' ); ?></option>
            <option value="this_month" <?php selected( $date, 'this_month' ); ?>><?php esc_html_e( 'This month', 'ulticommerce' ); ?></option>
            <option value="last_month" <?php selected( $date, 'last_month' ); ?>><?php esc_html_e( 'Last month', 'ulticommerce' ); ?></option>
            <option value="this_year" <?php selected( $date, 'this_year' ); ?>><?php esc_html_e( 'This year', 'ulticommerce' ); ?></option>
        </select>
        <input type="text" name="order_number_search" placeholder="<?php esc_attr_e( 'Search order #', 'ulticommerce' ); ?>" value="<?php echo esc_attr( $search ); ?>" style="width:140px;">
        <?php
    }

    public function apply_admin_filters( $query ) {
        global $pagenow;
        if ( ! is_admin() || $pagenow !== 'edit.php' || $query->get( 'post_type' ) !== 'order' ) return;
        if ( ! $query->is_main_query() ) return;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $meta_query = [];

        $status = sanitize_text_field( wp_unslash( $_GET['order_status_filter'] ?? '' ) );
        if ( ! empty( $status ) ) {
            $meta_query[] = [ 'key' => '_order_status', 'value' => $status ];
        }

        $order_num = sanitize_text_field( wp_unslash( $_GET['order_number_search'] ?? '' ) );
        if ( ! empty( $order_num ) ) {
            $query->set( 'title', $order_num );
        }

        $date_filter = sanitize_text_field( wp_unslash( $_GET['order_date_filter'] ?? '' ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $date_filter ) ) {
            $date_query = [];
            switch ( $date_filter ) {
                case 'today':
                    $date_query = [ 'after' => gmdate( 'Y-m-d 00:00:00' ), 'inclusive' => true ];
                    break;
                case 'this_week':
                    $date_query = [ 'after' => gmdate( 'Y-m-d', strtotime( 'last monday' ) ), 'inclusive' => true ];
                    break;
                case 'this_month':
                    $date_query = [ 'after' => gmdate( 'Y-m-01' ), 'inclusive' => true ];
                    break;
                case 'last_month':
                    $date_query = [ 'after' => gmdate( 'Y-m-01', strtotime( 'first day of last month' ) ), 'before' => gmdate( 'Y-m-t', strtotime( 'last day of last month' ) ), 'inclusive' => true ];
                    break;
                case 'this_year':
                    $date_query = [ 'after' => gmdate( 'Y-01-01' ), 'inclusive' => true ];
                    break;
            }
            if ( ! empty( $date_query ) ) {
                $query->set( 'date_query', [ $date_query ] );
            }
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }

    public function add_meta_boxes() {
        add_meta_box( 'order_status_box', esc_html__( 'Order Status', 'ulticommerce' ), [ $this, 'render_status_box' ], 'order', 'side', 'high' );
        add_meta_box( 'order_details', esc_html__( 'Order Details', 'ulticommerce' ), [ $this, 'render_meta_box' ], 'order', 'normal', 'high' );
        add_meta_box( 'order_delivery', esc_html__( 'Delivery & Tracking', 'ulticommerce' ), [ $this, 'render_delivery_box' ], 'order', 'side', 'high' );
        add_meta_box( 'order_notes', esc_html__( 'Order Notes', 'ulticommerce' ), [ $this, 'render_notes_box' ], 'order', 'normal' );
    }

    public function render_status_box( $post ) {
        $order_status = new UltiCommerce_Order_Statuses();
        echo '<div class="uti-status-box">';
        $order_status->render_status_dropdown( $post );
        echo '</div>';
        $this->render_status_timeline( $post->ID );
    }

    private function render_status_timeline( $order_id ) {
        $log = get_post_meta( $order_id, '_order_status_log', true ) ?: [];
        if ( empty( $log ) ) return;
        echo '<h4 style="margin:16px 0 8px;font-size:12px;">' . esc_html__( 'Timeline', 'ulticommerce' ) . '</h4>';
        echo '<ul style="margin:0;padding:0;list-style:none;font-size:12px;">';
        $recent = array_slice( array_reverse( $log ), 0, 5 );
        foreach ( $recent as $entry ) {
            echo '<li style="padding:4px 0;border-bottom:1px solid #f0f0f0;">';
            echo '<strong>' . esc_html( UltiCommerce_Order_Statuses::get_label( $entry['status'] ) ) . '</strong>';
            echo ' <span style="color:#999;">' . esc_html( $entry['time'] ) . '</span>';
            if ( ! empty( $entry['by'] ) ) echo ' <span style="color:#999;">— ' . esc_html( $entry['by'] ) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    public function render_meta_box( $post ) {
        $shipping_cost = get_post_meta( $post->ID, '_order_shipping_cost', true );
        ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Order Number', 'ulticommerce' ); ?></th><td><strong>#<?php echo esc_html( get_post_meta( $post->ID, '_order_number', true ) ); ?></strong></td></tr>
            <tr><th><?php esc_html_e( 'Status', 'ulticommerce' ); ?></th><td><?php
                $s = get_post_meta( $post->ID, '_order_status', true ) ?: 'new';
                $c = UltiCommerce_Order_Statuses::get_badge_class( $s );
                echo '<span class="uti-badge ' . esc_attr( $c ) . '">' . esc_html( UltiCommerce_Order_Statuses::get_label( $s ) ) . '</span>';
            ?></td></tr>
            <tr><th><?php esc_html_e( 'Date', 'ulticommerce' ); ?></th><td><?php echo esc_html( get_post_meta( $post->ID, '_order_date', true ) ?: '—' ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Customer', 'ulticommerce' ); ?></th><td><?php echo esc_html( get_post_meta( $post->ID, '_order_first_name', true ) . ' ' . get_post_meta( $post->ID, '_order_last_name', true ) ); ?><br><?php echo esc_html( get_post_meta( $post->ID, '_order_email', true ) ); ?><?php echo get_post_meta( $post->ID, '_order_phone', true ) ? ' — ' . esc_html( get_post_meta( $post->ID, '_order_phone', true ) ) : ''; ?></td></tr>
            <tr><th><?php esc_html_e( 'Shipping Address', 'ulticommerce' ); ?></th><td><?php
                $addr = get_post_meta( $post->ID, '_order_address', true );
                $city = get_post_meta( $post->ID, '_order_city', true );
                $state = get_post_meta( $post->ID, '_order_state', true );
                $zip = get_post_meta( $post->ID, '_order_zip', true );
                $country = get_post_meta( $post->ID, '_order_country', true );
                echo esc_html( implode( ', ', array_filter( [ $addr, $city, $state, $zip, $country ] ) ) ?: '—' );
            ?></td></tr>
            <tr><th><?php esc_html_e( 'Shipping Method', 'ulticommerce' ); ?></th><td><?php echo esc_html( get_post_meta( $post->ID, '_order_shipping_method', true ) ?: '—' ); ?><?php echo $shipping_cost ? ' (' . esc_html( ulti_format_price( $shipping_cost ) ) . ')' : ''; ?></td></tr>
            <tr><th><?php esc_html_e( 'Payment Method', 'ulticommerce' ); ?></th><td><?php
                echo esc_html( get_post_meta( $post->ID, '_order_payment_title', true ) ?: get_post_meta( $post->ID, '_order_payment_method', true ) ?: '—' );
                $txn = get_post_meta( $post->ID, '_order_transaction_id', true );
                echo $txn ? '<br>ID: ' . esc_html( $txn ) : '';
            ?></td></tr>
            <tr><th><?php esc_html_e( 'Payment', 'ulticommerce' ); ?></th><td><?php
                $sub = get_post_meta( $post->ID, '_order_subtotal', true );
                $discount = get_post_meta( $post->ID, '_order_discount', true );
                $coupon = get_post_meta( $post->ID, '_order_coupon', true );
                $total = get_post_meta( $post->ID, '_order_total', true );
                echo esc_html__( 'Subtotal:', 'ulticommerce' ) . ' ' . esc_html( ulti_format_price( $sub ?: 0 ) ) . '<br>';
                if ( $discount > 0 ) {
                    echo esc_html__( 'Discount:', 'ulticommerce' ) . ' -' . esc_html( ulti_format_price( $discount ) );
                    if ( $coupon ) echo ' (' . esc_html( $coupon ) . ')';
                    echo '<br>';
                }
                echo esc_html__( 'Shipping:', 'ulticommerce' ) . ' ' . ( $shipping_cost > 0 ? esc_html( ulti_format_price( $shipping_cost ) ) : esc_html__( 'Free', 'ulticommerce' ) ) . '<br>';
                echo '<strong>' . esc_html__( 'Total:', 'ulticommerce' ) . ' ' . esc_html( ulti_format_price( $total ?: 0 ) ) . '</strong>';
            ?></td></tr>
        </table>

        <?php
        $items = get_post_meta( $post->ID, '_order_items', true ) ?: [];
        if ( ! empty( $items ) ) {
            echo '<h3 style="margin-top:20px;">' . esc_html__( 'Order Items', 'ulticommerce' ) . '</h3>';
            echo '<table class="widefat fixed"><thead><tr><th>' . esc_html__( 'Product', 'ulticommerce' ) . '</th><th>' . esc_html__( 'Price', 'ulticommerce' ) . '</th><th>' . esc_html__( 'Qty', 'ulticommerce' ) . '</th><th>' . esc_html__( 'Total', 'ulticommerce' ) . '</th></tr></thead><tbody>';
            foreach ( $items as $item ) {
                echo '<tr><td>' . esc_html( $item['name'] ?? '' ) . '</td><td>' . esc_html( ulti_format_price( $item['price'] ?? 0 ) ) . '</td><td>' . esc_html( $item['quantity'] ?? 0 ) . '</td><td>' . esc_html( ulti_format_price( ( $item['price'] ?? 0 ) * ( $item['quantity'] ?? 0 ) ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        $paid_date = get_post_meta( $post->ID, '_order_paid_date', true );
        if ( $paid_date ) {
            echo '<p style="margin-top:12px;color:#999;font-size:12px;">' . esc_html__( 'Paid:', 'ulticommerce' ) . ' ' . esc_html( $paid_date ) . '</p>';
        }
    }

    public function render_notes_box( $post ) {
        $notes = get_post_meta( $post->ID, '_order_notes', true ) ?: [];
        if ( ! is_array( $notes ) ) $notes = [];
        ?>
        <div id="order-notes-wrap">
            <ul id="order-notes-list" style="max-height:200px;overflow-y:auto;margin:0 0 12px;padding:0;list-style:none;">
                <?php foreach ( array_reverse( $notes ) as $note ) : ?>
                    <li style="padding:8px;background:#f8f9fb;margin-bottom:4px;border-radius:4px;font-size:13px;">
                        <strong><?php echo esc_html( $note['author'] ?? '' ); ?></strong>
                        <span style="color:#999;font-size:11px;"><?php echo esc_html( $note['time'] ?? '' ); ?></span>
                        <p style="margin:4px 0 0;"><?php echo esc_html( $note['text'] ?? '' ); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <textarea id="order-note-input" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" rows="2" placeholder="<?php esc_attr_e( 'Add a note...', 'ulticommerce' ); ?>"></textarea>
            <button type="button" class="button" id="add-order-note" style="margin-top:4px;" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Add Note', 'ulticommerce' ); ?></button>
            <span class="spinner" style="float:none;margin-top:4px;"></span>
        </div>
        <?php
        wp_enqueue_script( 'ulticommerce-admin' );
        $ajax_nonce = wp_create_nonce( 'add_order_note_' . $post->ID );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    $("#add-order-note").on("click", function() {
        var btn = $(this);
        var text = $("#order-note-input").val();
        if (!text.trim()) return;
        var spinner = btn.siblings(".spinner");
        spinner.addClass("is-active");
        $.post(ajaxurl, { action: "ulti_add_order_note", post_id: btn.data("post-id"), text: text, _ajax_nonce: "' . esc_js( $ajax_nonce ) . '" }, function(resp) {
            spinner.removeClass("is-active");
            if (resp.success) { location.reload(); }
        });
    });
});
' );
    }

    public function render_delivery_box( $post ) {
        wp_nonce_field( 'ulti_save_order_meta', '_ulti_order_nonce' );
        $tracking = get_post_meta( $post->ID, '_order_tracking_number', true );
        $courier  = get_post_meta( $post->ID, '_order_courier_name', true );
        $weight   = get_post_meta( $post->ID, '_order_packing_weight', true );
        $note     = get_post_meta( $post->ID, '_order_packing_note', true );
        $status   = get_post_meta( $post->ID, '_order_status', true ) ?: 'new';
        $is_final = UltiCommerce_Order_Statuses::is_final_status( $status );
        ?>
        <table class="form-table" style="margin-top:0;">
            <tr>
                <th style="padding:4px 0;font-size:12px;"><?php esc_html_e( 'Tracking #', 'ulticommerce' ); ?></th>
                <td style="padding:4px 0;">
                    <input type="text" name="_order_tracking_number" value="<?php echo esc_attr( $tracking ); ?>" class="widefat" <?php disabled( $is_final ); ?>>
                </td>
            </tr>
            <tr>
                <th style="padding:4px 0;font-size:12px;"><?php esc_html_e( 'Courier', 'ulticommerce' ); ?></th>
                <td style="padding:4px 0;">
                    <input type="text" name="_order_courier_name" value="<?php echo esc_attr( $courier ); ?>" class="widefat" <?php disabled( $is_final ); ?>>
                </td>
            </tr>
            <tr>
                <th style="padding:4px 0;font-size:12px;"><?php esc_html_e( 'Weight (kg)', 'ulticommerce' ); ?></th>
                <td style="padding:4px 0;">
                    <input type="text" name="_order_packing_weight" value="<?php echo esc_attr( $weight ); ?>" class="widefat" <?php disabled( $is_final ); ?>>
                </td>
            </tr>
            <tr>
                <th style="padding:4px 0;font-size:12px;"><?php esc_html_e( 'Packing Note', 'ulticommerce' ); ?></th>
                <td style="padding:4px 0;">
                    <textarea name="_order_packing_note" class="widefat" rows="2" <?php disabled( $is_final ); ?>><?php echo esc_textarea( $note ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php if ( $tracking || $courier ) : ?>
        <p style="margin:8px 0 0;font-size:11px;color:#999;">
            <?php esc_html_e( 'Tracking info will be visible to the customer on the order page.', 'ulticommerce' ); ?>
        </p>
        <?php endif; ?>
        <?php
    }

    public function save_order_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! isset( $_POST['_ulti_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ulti_order_nonce'] ) ), 'ulti_save_order_meta' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['_order_status'] ) ) {
            $old = get_post_meta( $post_id, '_order_status', true ) ?: 'new';
            $new = sanitize_text_field( wp_unslash( $_POST['_order_status'] ) );

            if ( $old !== $new ) {
                if ( ! UltiCommerce_Order_Statuses::can_transition( $old, $new ) ) {
                    return;
                }
                update_post_meta( $post_id, '_order_status', $new );
                self::log_status_change( $post_id, $new );
                do_action( 'ulti_order_status_changed', $post_id, $old, $new );

                if ( $new === 'paid' && $old !== 'paid' ) {
                    do_action( 'ulti_order_paid', $post_id );
                }
            }
        }

        $tracking = [
            '_order_tracking_number' => '_order_tracking_number',
            '_order_courier_name'    => '_order_courier_name',
            '_order_packing_weight'  => '_order_packing_weight',
            '_order_packing_note'    => '_order_packing_note',
        ];
        foreach ( $tracking as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        $old_tracking = get_post_meta( $post_id, '_order_tracking_number', true );
        $new_tracking = sanitize_text_field( wp_unslash( $_POST['_order_tracking_number'] ?? '' ) );
        $old_courier  = get_post_meta( $post_id, '_order_courier_name', true );
        $new_courier  = sanitize_text_field( wp_unslash( $_POST['_order_courier_name'] ?? '' ) );
        if ( $new_tracking && $new_tracking !== $old_tracking ) {
            do_action( 'ulti_order_tracking_updated', $post_id, $new_tracking, $new_courier );
        }
    }

    public static function log_status_change( $order_id, $status ) {
        $log = get_post_meta( $order_id, '_order_status_log', true ) ?: [];
        if ( ! is_array( $log ) ) $log = [];
        $log[] = [
            'status' => $status,
            'time'   => current_time( 'mysql' ),
            'by'     => wp_get_current_user()->display_name ?: 'system',
        ];
        update_post_meta( $order_id, '_order_status_log', $log );
    }

    public function bulk_status_actions( $actions ) {
        foreach ( UltiCommerce_Order_Statuses::get_statuses() as $slug => $label ) {
            /* translators: %s: order status label */
            $actions[ 'set_status_' . $slug ] = sprintf( esc_html__( 'Set status to %s', 'ulticommerce' ), esc_html( $label ) );
        }
        return $actions;
    }

    public function handle_bulk_status_action( $redirect_to, $doaction, $post_ids ) {
        if ( strpos( $doaction, 'set_status_' ) !== 0 ) return $redirect_to;
        $status = substr( $doaction, 11 );
        if ( ! isset( UltiCommerce_Order_Statuses::get_statuses()[ $status ] ) ) return $redirect_to;

        $processed = 0;
        $skipped   = 0;
        foreach ( $post_ids as $pid ) {
            $old = get_post_meta( $pid, '_order_status', true ) ?: 'new';
            if ( ! UltiCommerce_Order_Statuses::can_transition( $old, $status ) ) {
                $skipped++;
                continue;
            }
            update_post_meta( $pid, '_order_status', $status );
            self::log_status_change( $pid, $status );
            do_action( 'ulti_order_status_changed', $pid, $old, $status );
            $processed++;
        }
        $redirect_to = add_query_arg( [
            'bulk_processed' => $processed,
            'bulk_skipped'   => $skipped,
        ], $redirect_to );
        return $redirect_to;
    }

    public function bulk_action_admin_notice() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-order' ) return;
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['bulk_processed'] ) ) {
            $processed = intval( wp_unslash( $_GET['bulk_processed'] ) );
            $skipped   = intval( wp_unslash( $_GET['bulk_skipped'] ?? 0 ) );
            echo '<div class="notice notice-success is-dismissible"><p>' .
                /* translators: %d: number of orders updated */
                esc_html( sprintf( __( 'Updated %d order(s).', 'ulticommerce' ), $processed ) ) .
                ( $skipped ? ' ' . /* translators: %d: number of orders skipped */ esc_html( sprintf( __( '%d skipped (invalid transition).', 'ulticommerce' ), $skipped ) ) : '' ) .
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                '</p></div>';
        }
    }

    public function ajax_bulk_update_status() {
        check_ajax_referer( 'ulti_bulk_status' );
        if ( ! current_user_can( 'edit_others_posts' ) ) wp_send_json_error();
        $post_ids = array_map( 'intval', (array) wp_unslash( $_POST['post_ids'] ?? [] ) );
        $status   = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
        $skipped  = 0;
        $invalid  = 0;
        foreach ( $post_ids as $pid ) {
            $old = get_post_meta( $pid, '_order_status', true ) ?: 'new';
            if ( UltiCommerce_Order_Statuses::is_final_status( $old ) ) {
                $skipped++;
                continue;
            }
            if ( ! UltiCommerce_Order_Statuses::can_transition( $old, $status ) ) {
                $invalid++;
                continue;
            }
            update_post_meta( $pid, '_order_status', $status );
            self::log_status_change( $pid, $status );
            do_action( 'ulti_order_status_changed', $pid, $old, $status );
        }
        wp_send_json_success( [ 'skipped' => $skipped, 'invalid' => $invalid ] );
    }

    public function ajax_add_note() {
        $post_id = intval( wp_unslash( $_POST['post_id'] ?? 0 ) );
        $text    = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
        if ( ! $post_id || ! $text ) wp_send_json_error();
        check_ajax_referer( 'add_order_note_' . $post_id, '_ajax_nonce' );
        if ( ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();

        $notes = get_post_meta( $post_id, '_order_notes', true ) ?: [];
        if ( ! is_array( $notes ) ) $notes = [];
        $notes[] = [
            'author' => wp_get_current_user()->display_name ?: 'Admin',
            'time'   => current_time( 'mysql' ),
            'text'   => $text,
        ];
        update_post_meta( $post_id, '_order_notes', $notes );
        wp_send_json_success();
    }

    public function handle_print_invoice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['print-invoice'] ) ) return;
        $post_id = intval( $_GET['post'] ?? ( get_query_var( 'view-order' ) ?: 0 ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( ! $post_id || get_post_type( $post_id ) !== 'order' ) return;
        $order_customer_id = (int) get_post_meta( $post_id, '_order_customer_id', true );
        if ( $order_customer_id && $order_customer_id !== get_current_user_id() && ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'ulticommerce' ) );
        }
        $this->stream_invoice_pdf( $post_id );
    }

    public function print_invoice() {
        $post_id = intval( $_GET['post'] ?? 0 );
        if ( ! $post_id || get_post_type( $post_id ) !== 'order' ) {
            wp_die( esc_html__( 'Invalid order.', 'ulticommerce' ) );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'ulticommerce' ) );
        }
        check_admin_referer( 'print_invoice_' . $post_id );
        $this->stream_invoice_pdf( $post_id );
    }

    private function stream_invoice_pdf( $post_id ) {
        $file = $this->generate_invoice_pdf( $post_id );
        if ( ! $file || ! file_exists( $file ) ) {
            wp_die( esc_html__( 'Could not generate invoice PDF.', 'ulticommerce' ) );
        }
        $order_num = get_post_meta( $post_id, '_order_number', true ) ?: $post_id;
        $filename  = 'INV-' . sanitize_file_name( $order_num ) . '.pdf';
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $file ) );
        WP_Filesystem();
        global $wp_filesystem;
        if ( $wp_filesystem ) {
            echo $wp_filesystem->get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        }
        exit;
    }

    public static function generate_invoice_pdf( $order_id ) {
        $order_num = get_post_meta( $order_id, '_order_number', true );
        if ( ! $order_num ) return;

        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/invoices';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $file = $dir . '/INV-' . $order_num . '.pdf';
        if ( file_exists( $file ) ) return $file;

        $html = self::get_invoice_html( $order_id );
        if ( ! $html ) return null;

        if ( class_exists( 'Dompdf\Dompdf' ) ) {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();
            file_put_contents( $file, $dompdf->output() );
            return $file;
        }
        return null;
    }

    private static function get_invoice_html( $order_id ) {
        $order_num = get_post_meta( $order_id, '_order_number', true );
        if ( ! $order_num ) return '';

        $first     = get_post_meta( $order_id, '_order_first_name', true );
        $last      = get_post_meta( $order_id, '_order_last_name', true );
        $customer  = get_post_meta( $order_id, '_order_customer_id', true );
        $user      = $customer ? get_userdata( $customer ) : null;
        $email     = get_post_meta( $order_id, '_order_email', true );
        $address   = get_post_meta( $order_id, '_order_address', true );
        $city      = get_post_meta( $order_id, '_order_city', true );
        $state     = get_post_meta( $order_id, '_order_state', true );
        $zip       = get_post_meta( $order_id, '_order_zip', true );
        $country   = get_post_meta( $order_id, '_order_country', true );
        $subtotal  = get_post_meta( $order_id, '_order_subtotal', true );
        $discount  = get_post_meta( $order_id, '_order_discount', true );
        $shipping_cost = get_post_meta( $order_id, '_order_shipping_cost', true );
        $total     = get_post_meta( $order_id, '_order_total', true );
        $status    = get_post_meta( $order_id, '_order_status', true ) ?: 'new';
        $items     = get_post_meta( $order_id, '_order_items', true ) ?: [];
        $date      = get_post_meta( $order_id, '_order_date', true );

        ob_start();
        ?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?php /* translators: %s: order number */ printf( esc_html__( 'Invoice #%s', 'ulticommerce' ), esc_html( $order_num ) ); ?></title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #333; margin: 40px; }
.invoice { max-width: 700px; margin: 0 auto; }
.header { border-bottom: 2px solid #0052cc; padding-bottom: 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
.header h1 { margin: 0; font-size: 28px; color: #0052cc; }
.header .meta { text-align: right; font-size: 13px; color: #666; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th { background: #f5f7fa; text-align: left; padding: 10px 12px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
.summary { margin-top: 16px; text-align: right; }
.summary div { margin: 4px 0; }
.total { font-size: 20px; font-weight: 700; color: #0052cc; }
.footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #999; }
</style>
</head><body>
<div class="invoice">
<div class="header">
<div><h1><?php esc_html_e( 'INVOICE', 'ulticommerce' ); ?></h1></div>
<div class="meta">
<div><strong><?php /* translators: %s: order number */ printf( esc_html__( 'Order #%s', 'ulticommerce' ), esc_html( $order_num ) ); ?></strong></div>
<div><?php echo esc_html( $date ? gmdate( 'M j, Y', strtotime( $date ) ) : '' ); ?></div>
</div></div>

<div class="address">
<h3><?php esc_html_e( 'Bill To', 'ulticommerce' ); ?></h3>
<p><strong><?php echo esc_html( "$first $last" ); ?></strong></p>
<p><?php echo esc_html( $email ); ?></p>
<p><?php echo esc_html( implode( ', ', array_filter( [ $address, $city, $state, $zip, $country ] ) ) ); ?></p>
</div>

<table><thead><tr><th><?php esc_html_e( 'Product', 'ulticommerce' ); ?></th><th><?php esc_html_e( 'Price', 'ulticommerce' ); ?></th><th><?php esc_html_e( 'Qty', 'ulticommerce' ); ?></th><th><?php esc_html_e( 'Total', 'ulticommerce' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $items as $item ) : ?>
<tr><td><?php echo esc_html( $item['name'] ?? '' ); ?></td><td><?php echo esc_html( ulti_format_price( $item['price'] ?? 0 ) ); ?></td><td><?php echo esc_html( $item['quantity'] ?? 0 ); ?></td><td><?php echo esc_html( ulti_format_price( ( $item['price'] ?? 0 ) * ( $item['quantity'] ?? 0 ) ) ); ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<div class="summary">
<div><?php esc_html_e( 'Subtotal:', 'ulticommerce' ); ?> <?php echo esc_html( ulti_format_price( $subtotal ?: 0 ) ); ?></div>
<?php if ( $discount > 0 ) : ?><div><?php esc_html_e( 'Discount:', 'ulticommerce' ); ?> -<?php echo esc_html( ulti_format_price( $discount ) ); ?></div><?php endif; ?>
<div><?php esc_html_e( 'Shipping:', 'ulticommerce' ); ?> <?php echo $shipping_cost > 0 ? esc_html( ulti_format_price( $shipping_cost ) ) : esc_html__( 'Free', 'ulticommerce' ); ?></div>
<div class="total"><?php esc_html_e( 'Total:', 'ulticommerce' ); ?> <?php echo esc_html( ulti_format_price( $total ?: 0 ) ); ?></div>
</div>

<div class="footer">
<p><?php esc_html_e( 'Thank you for your business!', 'ulticommerce' ); ?></p>
</div>
</div>
</body></html>
        <?php
        return ob_get_clean();
    }

    public function prevent_trash_final_status( $post_id ) {
        if ( get_post_type( $post_id ) !== 'order' ) return;
        $status = get_post_meta( $post_id, '_order_status', true ) ?: 'new';
        if ( UltiCommerce_Order_Statuses::is_final_status( $status ) ) {
            wp_die( esc_html__( 'This order has a final status (Canceled, Refunded, or Delivered) and cannot be moved to Trash.', 'ulticommerce' ) );
        }
    }

    public function admin_scripts( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'order' ) return;
        wp_enqueue_style( 'ulticommerce-admin' );
        wp_enqueue_script( 'ulticommerce-admin' );

        if ( $screen->base === 'edit' ) {
            wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    $(".order-status-badge.status-editable").on("click", function() {
        var badge = $(this);
        var postId = badge.data("post-id");
        var dropdown = $(".order-status-dropdown[data-post-id=\"" + postId + "\"]");
        badge.hide();
        dropdown.show().focus();
    });

    $(".order-status-dropdown").on("change", function() {
        var dropdown = $(this);
        var postId = dropdown.data("post-id");
        var status = dropdown.val();
        var nonce = dropdown.data("nonce");
        var badge = $(".order-status-badge[data-post-id=\"" + postId + "\"]");

        $.post(ajaxurl, {
            action: "ulti_update_order_status",
            post_id: postId,
            status: status,
            _ajax_nonce: nonce
        }, function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : "Error updating status.");
                location.reload();
            }
        });
    });

    $(".order-status-dropdown").on("blur", function() {
        var dropdown = $(this);
        if (!dropdown.data("changed")) {
            var postId = dropdown.data("post-id");
            var badge = $(".order-status-badge[data-post-id=\"" + postId + "\"]");
            dropdown.hide();
            badge.show();
        }
    }).on("focus", function() {
        $(this).removeData("changed");
    });
});
' );
        }
    }
}

new UltiCommerce_Order_CPT();

<?php

defined( 'ABSPATH' ) || exit;

class Ultico_Subscriber_CPT {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_filter( 'manage_ultico_subscriber_posts_columns', [ $this, 'custom_columns' ] );
        add_action( 'manage_ultico_subscriber_posts_custom_column', [ $this, 'custom_column_data' ], 10, 2 );
        add_filter( 'manage_edit-ultico_subscriber_sortable_columns', [ $this, 'sortable_columns' ] );
        add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
        add_action( 'wp_ajax_ultico_toggle_subscriber', [ $this, 'ajax_toggle_subscriber' ] );
        add_action( 'restrict_manage_posts', [ $this, 'export_button' ] );
        add_action( 'admin_init', [ $this, 'handle_export_csv' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
    }

    public function register_post_type() {
        register_post_type( 'ultico_subscriber', [
            'labels'              => [
                'name'               => esc_html__( 'Subscribers', 'ulticommerce' ),
                'singular_name'      => esc_html__( 'Subscriber', 'ulticommerce' ),
                'add_new'            => esc_html__( 'Add New', 'ulticommerce' ),
                'add_new_item'       => esc_html__( 'Add New Subscriber', 'ulticommerce' ),
                'edit_item'          => esc_html__( 'Edit Subscriber', 'ulticommerce' ),
                'view_item'          => esc_html__( 'View Subscriber', 'ulticommerce' ),
                'search_items'       => esc_html__( 'Search Subscribers', 'ulticommerce' ),
                'not_found'          => esc_html__( 'No subscribers found.', 'ulticommerce' ),
                'not_found_in_trash' => esc_html__( 'No subscribers in Trash.', 'ulticommerce' ),
                'all_items'          => esc_html__( 'All Subscribers', 'ulticommerce' ),
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'ulticommerce',
            'show_in_nav_menus'   => false,
            'show_in_rest'        => true,
            'hierarchical'        => false,
            'supports'            => [ 'title', 'custom-fields' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ] );
    }

    public function custom_columns( $columns ) {
        $date = $columns['date'];
        unset( $columns['date'] );
        $columns['email']      = esc_html__( 'Email', 'ulticommerce' );
        $columns['status']     = esc_html__( 'Status', 'ulticommerce' );
        $columns['source']     = esc_html__( 'Source', 'ulticommerce' );
        $columns['date']       = $date;
        return $columns;
    }

    public function custom_column_data( $column, $post_id ) {
        switch ( $column ) {
            case 'email':
                $email = get_the_title( $post_id );
                echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                break;
            case 'status':
                $status = get_post_meta( $post_id, '_subscriber_status', true ) ?: 'active';
                $badge_class = $status === 'active' ? 'badge-success' : 'badge-error';
                $label = $status === 'active' ? __( 'Active', 'ulticommerce' ) : __( 'Unsubscribed', 'ulticommerce' );
                echo '<span class="uti-badge ' . esc_attr( $badge_class ) . ' subscriber-status-badge" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html( $label ) . '</span>';
                break;
            case 'source':
                $source = get_post_meta( $post_id, '_subscriber_source', true );
                echo $source ? '<a href="' . esc_url( $source ) . '" target="_blank">' . esc_html( $source ) . '</a>' : '&mdash;';
                break;
        }
    }

    public function sortable_columns( $columns ) {
        $columns['email'] = 'title';
        return $columns;
    }

    public function row_actions( $actions, $post ) {
        if ( $post->post_type !== 'ultico_subscriber' ) return $actions;

        $status = get_post_meta( $post->ID, '_subscriber_status', true ) ?: 'active';
        $nonce = wp_create_nonce( 'ultico_toggle_subscriber_' . $post->ID );

        if ( $status === 'active' ) {
            $actions['unsubscribe'] = '<a href="#" class="subscriber-toggle" data-post-id="' . esc_attr( $post->ID ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-action="unsubscribe">' . esc_html__( 'Unsubscribe', 'ulticommerce' ) . '</a>';
        } else {
            $actions['resubscribe'] = '<a href="#" class="subscriber-toggle" data-post-id="' . esc_attr( $post->ID ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-action="resubscribe">' . esc_html__( 'Resubscribe', 'ulticommerce' ) . '</a>';
        }

        unset( $actions['edit'], $actions['inline hide-if-no-js'], $actions['view'] );
        return $actions;
    }

    public function ajax_toggle_subscriber() {
        $post_id = intval( wp_unslash( $_POST['post_id'] ?? 0 ) );
        $new_status = sanitize_text_field( wp_unslash( $_POST['new_status'] ?? '' ) );

        if ( ! $post_id || ! in_array( $new_status, [ 'active', 'unsubscribed' ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'ulticommerce' ) ] );
        }

        check_ajax_referer( 'ultico_toggle_subscriber_' . $post_id, '_ajax_nonce' );

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'ulticommerce' ) ] );
        }

        update_post_meta( $post_id, '_subscriber_status', $new_status );

        $label = $new_status === 'active' ? __( 'Active', 'ulticommerce' ) : __( 'Unsubscribed', 'ulticommerce' );
        $badge_class = $new_status === 'active' ? 'badge-success' : 'badge-error';

        wp_send_json_success( [
            'label'       => $label,
            'badge_class' => $badge_class,
            'new_status'  => $new_status,
        ] );
    }

    public function export_button( $post_type ) {
        if ( $post_type !== 'ultico_subscriber' ) return;
        wp_nonce_field( 'ultico_export_subscribers_csv', '_wpnonce_export_csv' );
        ?>
        <button type="submit" name="ultico_export_subscribers_csv" value="1" class="button" style="margin-left:6px;">
            <?php esc_html_e( 'Export CSV', 'ulticommerce' ); ?>
        </button>
        <?php
    }

    public function handle_export_csv() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['ultico_export_subscribers_csv'] ) || empty( $_GET['post_type'] ) || $_GET['post_type'] !== 'ultico_subscriber' ) {
            return;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }
        check_admin_referer( 'ultico_export_subscribers_csv', '_wpnonce_export_csv' );

        $subscribers = get_posts( [
            'post_type'      => 'ultico_subscriber',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // BOM for UTF-8
        fputcsv( $output, [ __( 'Email', 'ulticommerce' ), __( 'Status', 'ulticommerce' ), __( 'Source', 'ulticommerce' ), __( 'Date Subscribed', 'ulticommerce' ) ] );

        foreach ( $subscribers as $s ) {
            fputcsv( $output, [
                $s->post_title,
                get_post_meta( $s->ID, '_subscriber_status', true ) ?: 'active',
                get_post_meta( $s->ID, '_subscriber_source', true ),
                $s->post_date,
            ] );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $output );
        exit;
    }

    public function admin_scripts( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'ultico_subscriber' ) return;
        wp_enqueue_style( 'ulticommerce-admin' );
        wp_enqueue_script( 'ulticommerce-admin' );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    $(".subscriber-toggle").on("click", function(e) {
        e.preventDefault();
        var link = $(this);
        var postId = link.data("post-id");
        var nonce = link.data("nonce");
        var action = link.data("action");
        var newStatus = action === "unsubscribe" ? "unsubscribed" : "active";

        $.post(ajaxurl, {
            action: "ultico_toggle_subscriber",
            post_id: postId,
            new_status: newStatus,
            _ajax_nonce: nonce
        }, function(resp) {
            if (resp.success) {
                var badge = $(".subscriber-status-badge[data-post-id=\"" + postId + "\"]");
                badge.text(resp.data.label);
                badge.removeClass("badge-success badge-error").addClass(resp.data.badge_class);

                var newAction = resp.data.new_status === "active" ? "unsubscribe" : "resubscribe";
                var newLabel = resp.data.new_status === "active" ? "' . esc_js( __( 'Unsubscribe', 'ulticommerce' ) ) . '" : "' . esc_js( __( 'Resubscribe', 'ulticommerce' ) ) . '";

                link.text(newLabel);
                link.data("action", newAction);
            }
        });
    });
});
' );
    }
}

new Ultico_Subscriber_CPT();

<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Product_CPT {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_product', [ $this, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
    }

    public function register_post_type() {
        $labels = [
            'name'                  => __( 'Products', 'ulticommerce-core' ),
            'singular_name'         => __( 'Product', 'ulticommerce-core' ),
            'add_new'               => __( 'Add New', 'ulticommerce-core' ),
            'add_new_item'          => __( 'Add New Product', 'ulticommerce-core' ),
            'edit_item'             => __( 'Edit Product', 'ulticommerce-core' ),
            'new_item'              => __( 'New Product', 'ulticommerce-core' ),
            'view_item'             => __( 'View Product', 'ulticommerce-core' ),
            'search_items'          => __( 'Search Products', 'ulticommerce-core' ),
            'not_found'             => __( 'No products found', 'ulticommerce-core' ),
            'not_found_in_trash'    => __( 'No products found in Trash', 'ulticommerce-core' ),
            'all_items'             => __( 'All Products', 'ulticommerce-core' ),
            'menu_name'             => __( 'Products', 'ulticommerce-core' ),
        ];

        register_post_type( 'product', [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => false,
            'menu_icon'    => 'dashicons-cart',
            'supports'     => [ 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes' ],
            'rewrite'      => [ 'slug' => 'products', 'with_front' => false ],
            'show_in_menu' => true,
            'menu_position' => 5,
        ] );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'product_details',
            esc_html__( 'Product Details', 'ulticommerce-core' ),
            [ $this, 'render_meta_box' ],
            'product',
            'normal',
            'high'
        );

        add_meta_box(
            'product_gallery',
            esc_html__( 'Product Gallery', 'ulticommerce-core' ),
            [ $this, 'render_gallery_meta_box' ],
            'product',
            'side'
        );

        add_meta_box(
            'product_enable',
            esc_html__( 'Product Status', 'ulticommerce-core' ),
            [ $this, 'render_enable_meta_box' ],
            'product',
            'side'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'product_details_save', 'product_details_nonce' );

        $fields = [
            '_product_sku'            => [ 'SKU', 'text' ],
            '_product_unit_price'     => [ 'Unit Price', 'number', 'step="0.01"' ],
            '_product_discount_price' => [ 'Discount Price', 'number', 'step="0.01"' ],
            '_product_quantity'       => [ 'Stock Quantity', 'number' ],
            '_product_weight'         => [ 'Weight (g)', 'number', 'step="1"' ],
            '_product_width'          => [ 'Width (cm)', 'number', 'step="0.1"' ],
            '_product_height'         => [ 'Height (cm)', 'number', 'step="0.1"' ],
            '_product_length'         => [ 'Length (cm)', 'number', 'step="0.1"' ],
        ];

        echo '<table class="form-table">';
        foreach ( $fields as $key => $field ) {
            $value = get_post_meta( $post->ID, $key, true );
            $attrs = $field[2] ?? '';
            echo '<tr>';
            echo '<th><label for="' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th>';
            echo '<td><input type="' . esc_attr( $field[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" ' . $attrs . '></td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public function render_gallery_meta_box( $post ) {
        $gallery = get_post_meta( $post->ID, '_product_gallery', true ) ?: [];
        if ( ! is_array( $gallery ) ) {
            $gallery = [];
        }
        ?>
        <div id="product-gallery-wrap">
            <ul id="product-gallery-list">
                <?php foreach ( $gallery as $image_id ) : ?>
                    <li data-id="<?php echo esc_attr( $image_id ); ?>">
                        <?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
                        <input type="hidden" name="_product_gallery[]" value="<?php echo esc_attr( $image_id ); ?>">
                        <a href="#" class="remove-gallery-item">&times;</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="button" id="add-gallery-images"><?php esc_html_e( 'Add Gallery Images', 'ulticommerce-core' ); ?></button>
        </div>
        <?php
        $thumb_nonce = wp_create_nonce( 'ulti_get_attachment_thumb' );
        wp_enqueue_script( 'ulticommerce-admin' );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    var frame;
    var thumbNonce = "' . esc_js( $thumb_nonce ) . '";
    $("#add-gallery-images").on("click", function(e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({ title: "' . esc_js( __( 'Select Gallery Images', 'ulticommerce-core' ) ) . '", multiple: true, library: { type: "image" } });
        frame.on("select", function() {
            var ids = frame.state().get("selection").map(function(a) { return a.id; });
            ids.forEach(function(id) {
                $.post(ajaxurl, { action: "ulti_get_attachment_thumb", attachment_id: id, _ajax_nonce: thumbNonce }, function(html) {
                    $("#product-gallery-list").append(
                        "<li data-id=\"" + id + "\">" + html + "<input type=\"hidden\" name=\"_product_gallery[]\" value=\"" + id + "\"><a href=\"#\" class=\"remove-gallery-item\">&times;</a></li>"
                    );
                });
            });
        });
        frame.open();
    });
    $("#product-gallery-list").on("click", ".remove-gallery-item", function(e) {
        e.preventDefault();
        $(this).closest("li").remove();
    });
});
' );
    }

    public function render_enable_meta_box( $post ) {
        $enabled = get_post_meta( $post->ID, '_product_enabled', true );
        if ( $enabled === '' ) {
            $enabled = '1';
        }
        ?>
        <label>
            <input type="checkbox" name="_product_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
            <?php esc_html_e( 'Enable Product', 'ulticommerce-core' ); ?>
        </label>
        <?php
    }

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['product_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['product_details_nonce'] ) ), 'product_details_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            '_product_sku', '_product_unit_price', '_product_discount_price',
            '_product_quantity', '_product_weight', '_product_width', '_product_height', '_product_length',
        ];

        foreach ( $fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }

        $enabled = isset( $_POST['_product_enabled'] ) ? '1' : '0';
        update_post_meta( $post_id, '_product_enabled', $enabled );

        if ( isset( $_POST['_product_gallery'] ) && is_array( $_POST['_product_gallery'] ) ) {
            $gallery = array_map( 'intval', $_POST['_product_gallery'] );
            update_post_meta( $post_id, '_product_gallery', $gallery );
        } else {
            delete_post_meta( $post_id, '_product_gallery' );
        }
    }

    public function admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) || get_post_type() !== 'product' ) {
            return;
        }
        wp_enqueue_media();
    }
}

new UltiCommerce_Product_CPT();

add_action( 'wp_ajax_ulti_get_attachment_thumb', 'ulti_get_attachment_thumb_cb' );
function ulti_get_attachment_thumb_cb() {
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( 'Unauthorized.' );
    }
    check_ajax_referer( 'ulti_get_attachment_thumb', '_ajax_nonce' );
    $id = intval( $_POST['attachment_id'] );
    echo wp_get_attachment_image( $id, 'thumbnail' );
    wp_die();
}

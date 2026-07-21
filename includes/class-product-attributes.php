<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Product_Attributes {

    public function __construct() {
        add_action( 'init', [ $this, 'register_attribute_taxonomy' ] );
        add_action( 'product_add_form_fields', [ $this, 'add_attribute_fields' ] );
        add_action( 'product_edit_form_fields', [ $this, 'edit_attribute_fields' ] );
        add_action( 'save_post_product', [ $this, 'save_attributes' ] );
        add_filter( 'manage_product_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_product_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    public function register_attribute_taxonomy() {
        register_taxonomy( 'product_attribute', 'product', [
            'labels' => [
                'name'              => __( 'Product Attributes', 'ulticommerce' ),
                'singular_name'     => __( 'Product Attribute', 'ulticommerce' ),
                'search_items'      => __( 'Search Attributes', 'ulticommerce' ),
                'all_items'         => __( 'All Attributes', 'ulticommerce' ),
                'edit_item'         => __( 'Edit Attribute', 'ulticommerce' ),
                'update_item'       => __( 'Update Attribute', 'ulticommerce' ),
                'add_new_item'      => __( 'Add New Attribute', 'ulticommerce' ),
                'new_item_name'     => __( 'New Attribute', 'ulticommerce' ),
                'menu_name'         => __( 'Attributes', 'ulticommerce' ),
            ],
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
            'show_in_menu'      => false,
            'rewrite'           => [ 'slug' => 'attribute' ],
        ] );
    }

    public function add_attribute_fields() {
        $attributes = get_terms( [ 'taxonomy' => 'product_attribute', 'hide_empty' => false ] );
        $product_attrs = [];
        ?>
        <div class="form-field product-attributes-wrap">
            <label><?php esc_html_e( 'Attributes', 'ulticommerce' ); ?></label>
            <div id="product-attribute-rows">
                <?php $this->render_attribute_rows( 0, $product_attrs ); ?>
            </div>
            <button type="button" class="button" id="add-attribute-row"><?php esc_html_e( 'Add Attribute', 'ulticommerce' ); ?></button>
        </div>
        <?php
        $attr_nonce = wp_create_nonce( 'ulti_edit_attributes' );
        wp_enqueue_script( 'ulticommerce-admin' );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    var attrIndex = 0;
    var attrNonce = "' . esc_js( $attr_nonce ) . '";
    var availableAttrs = ' . wp_json_encode( wp_list_pluck( $attributes, 'slug', 'term_id' ) ) . ';
    var $container = $("#product-attribute-rows");

    $("#add-attribute-row").on("click", function() {
        attrIndex++;
        $.get(ajaxurl, { action: "ulti_get_attribute_row", index: attrIndex, _ajax_nonce: attrNonce }, function(html) {
            $container.append(html);
        });
    });

    $container.on("click", ".remove-attr-row", function(e) {
        e.preventDefault();
        $(this).closest(".attr-row").remove();
    });

    $container.on("change", ".attr-name-select", function() {
        var $row = $(this).closest(".attr-row");
        var $values = $row.find(".attr-values");
        var slug = $(this).val();
        if (slug && availableAttrs[slug]) {
            $.get(ajaxurl, { action: "ulti_get_attr_terms", attr_slug: slug, _ajax_nonce: attrNonce }, function(data) {
                if (data) {
                    $values.replaceWith(
                        "<input type=\"text\" class=\"attr-values\" name=\"product_attributes[" + $row.data("index") + "][values]\" value=\"" + data.join(", ") + "\" placeholder=\"' . esc_attr__( 'Comma-separated values', 'ulticommerce' ) . '\">"
                    );
                }
            }, "json");
        }
    });
});
' );
    }

    public function edit_attribute_fields( $post ) {
        $attributes = get_terms( [ 'taxonomy' => 'product_attribute', 'hide_empty' => false ] );
        $product_attrs = get_post_meta( $post->ID, '_product_attributes', true ) ?: [];
        if ( ! is_array( $product_attrs ) ) $product_attrs = [];
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e( 'Attributes', 'ulticommerce' ); ?></label></th>
                <td>
                    <div id="product-attribute-rows">
                        <?php $this->render_attribute_rows( $post->ID, $product_attrs ); ?>
                    </div>
                    <button type="button" class="button" id="add-attribute-row"><?php esc_html_e( 'Add Attribute', 'ulticommerce' ); ?></button>
                </td>
            </tr>
        </table>
        <?php
        $attr_nonce = wp_create_nonce( 'ulti_edit_attributes' );
        wp_enqueue_script( 'ulticommerce-admin' );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    var attrIndex = ' . count( $product_attrs ) . ';
    var attrNonce = "' . esc_js( $attr_nonce ) . '";
    var $container = $("#product-attribute-rows");

    $("#add-attribute-row").on("click", function() {
        attrIndex++;
        $.get(ajaxurl, { action: "ulti_get_attribute_row", index: attrIndex, _ajax_nonce: attrNonce }, function(html) {
            $container.append(html);
        });
    });

    $container.on("click", ".remove-attr-row", function(e) {
        e.preventDefault();
        $(this).closest(".attr-row").remove();
    });
});
' );
    }

    private function render_attribute_rows( $post_id, $product_attrs ) {
        $attributes = get_terms( [ 'taxonomy' => 'product_attribute', 'hide_empty' => false ] );
        $i = 0;
        if ( ! empty( $product_attrs ) ) {
            foreach ( $product_attrs as $attr_name => $attr_data ) {
                $values = is_array( $attr_data ) ? ( $attr_data['values'] ?? '' ) : '';
                $values = is_array( $values ) ? implode( ', ', $values ) : $values;
                ?>
                <div class="attr-row" data-index="<?php echo esc_attr( $i ); ?>">
                    <select name="product_attributes[<?php echo esc_attr( $i ); ?>][name]" class="attr-name-select">
                        <option value=""><?php esc_html_e( 'Select attribute&hellip;', 'ulticommerce' ); ?></option>
                        <?php foreach ( $attributes as $attr ) : ?>
                            <option value="<?php echo esc_attr( $attr->slug ); ?>" <?php selected( $attr->slug, $attr_name ); ?>>
                                <?php echo esc_html( $attr->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="product_attributes[<?php echo esc_attr( $i ); ?>][values]" class="attr-values"
                           value="<?php echo esc_attr( $values ); ?>"
                           placeholder="<?php esc_attr_e( 'Red, Blue, Green', 'ulticommerce' ); ?>">
                    <a href="#" class="remove-attr-row">&times;</a>
                </div>
                <?php
                $i++;
            }
        }
    }

    public function save_attributes( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( isset( $_POST['product_attributes'] ) && is_array( $_POST['product_attributes'] ) ) {
            $attrs = [];
            foreach ( wp_unslash( $_POST['product_attributes'] ) as $data ) {
            // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $name   = sanitize_text_field( $data['name'] ?? '' );
                $values = sanitize_text_field( $data['values'] ?? '' );
                if ( $name ) {
                    $terms = array_map( 'trim', explode( ',', $values ) );
                    $attrs[ $name ] = [ 'values' => $terms ];
                }
            }
            update_post_meta( $post_id, '_product_attributes', $attrs );
        } else {
            delete_post_meta( $post_id, '_product_attributes' );
        }
    }

    public function add_columns( $columns ) {
        $columns['product_attributes'] = esc_html__( 'Attributes', 'ulticommerce' );
        return $columns;
    }

    public function render_column( $column, $post_id ) {
        if ( $column !== 'product_attributes' ) return;
        $attrs = get_post_meta( $post_id, '_product_attributes', true ) ?: [];
        if ( ! empty( $attrs ) ) {
            echo esc_html( implode( ', ', array_keys( $attrs ) ) );
        } else {
            echo '&mdash;';
        }
    }
}

new UltiCommerce_Product_Attributes();

// AJAX handlers for attribute meta box — nonce sent via inline script in render_attributes_meta_box
add_action( 'wp_ajax_ulti_get_attribute_row', 'ulti_get_attribute_row_cb' );
function ulti_get_attribute_row_cb() {
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_die( 'Unauthorized.' );
    }
    check_ajax_referer( 'ulti_edit_attributes', '_ajax_nonce' );
    $index     = intval( wp_unslash( $_GET['index'] ?? 0 ) );
    $attributes = get_terms( [ 'taxonomy' => 'product_attribute', 'hide_empty' => false ] );
    ?>
    <div class="attr-row" data-index="<?php echo esc_attr( $index ); ?>">
        <select name="product_attributes[<?php echo esc_attr( $index ); ?>][name]" class="attr-name-select">
            <option value=""><?php esc_html_e( 'Select attribute&hellip;', 'ulticommerce' ); ?></option>
            <?php foreach ( $attributes as $attr ) : ?>
                <option value="<?php echo esc_attr( $attr->slug ); ?>"><?php echo esc_html( $attr->name ); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="product_attributes[<?php echo esc_attr( $index ); ?>][values]" class="attr-values"
               placeholder="<?php esc_attr_e( 'Red, Blue, Green', 'ulticommerce' ); ?>">
        <a href="#" class="remove-attr-row">&times;</a>
    </div>
    <?php
    wp_die();
}

add_action( 'wp_ajax_ulti_get_attr_terms', 'ulti_get_attr_terms_cb' );
function ulti_get_attr_terms_cb() {
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error();
    }
    check_ajax_referer( 'ulti_edit_attributes', '_ajax_nonce' );
    $slug = sanitize_text_field( wp_unslash( $_GET['attr_slug'] ?? '' ) );
    $terms = get_terms( [ 'taxonomy' => 'product_attribute', 'slug' => $slug, 'hide_empty' => false ] );
    if ( ! empty( $terms ) ) {
        $child_terms = get_terms( [ 'taxonomy' => 'product_attribute', 'parent' => $terms[0]->term_id, 'hide_empty' => false, 'fields' => 'names' ] );
        wp_send_json_success( $child_terms );
    }
    wp_send_json_error();
}

<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Product_Variations {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_variations_meta_box' ] );
        add_action( 'wp_ajax_ulti_save_variations', [ $this, 'ajax_save_variations' ] );
        add_filter( 'manage_product_posts_columns', [ $this, 'add_variations_column' ] );
        add_action( 'manage_product_posts_custom_column', [ $this, 'render_variations_column' ], 10, 2 );
    }

    public function add_variations_meta_box() {
        add_meta_box(
            'product_variations',
            esc_html__( 'Product Variations', 'ulticommerce-core' ),
            [ $this, 'render_variations_meta_box' ],
            'product',
            'normal',
            'default'
        );
    }

    public function render_variations_meta_box( $post ) {
        $attrs      = get_post_meta( $post->ID, '_product_attributes', true ) ?: [];
        $variations = $this->get_variations( $post->ID );
        $base_price = get_post_meta( $post->ID, '_product_unit_price', true );
        $base_sku   = get_post_meta( $post->ID, '_product_sku', true );
        ?>
        <div id="variations-wrap">
            <p><?php esc_html_e( 'Generate variations from attribute combinations.', 'ulticommerce-core' ); ?></p>

            <?php if ( empty( $attrs ) ) : ?>
                <p><em><?php esc_html_e( 'Add attributes first to create variations.', 'ulticommerce-core' ); ?></em></p>
            <?php else : ?>
                <table class="widefat fixed" id="variations-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'SKU', 'ulticommerce-core' ); ?></th>
                            <?php foreach ( $attrs as $attr_name => $attr_data ) : ?>
                                <th><?php echo esc_html( get_term_by( 'slug', $attr_name, 'product_attribute' )->name ?? $attr_name ); ?></th>
                            <?php endforeach; ?>
                            <th><?php esc_html_e( 'Price', 'ulticommerce-core' ); ?></th>
                            <th><?php esc_html_e( 'Quantity', 'ulticommerce-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ulticommerce-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $variations ) ) : ?>
                            <?php foreach ( $variations as $var ) : ?>
                                <tr data-variation-id="<?php echo esc_attr( $var['id'] ); ?>">
                                    <td><input type="text" class="var-sku" value="<?php echo esc_attr( $var['sku'] ); ?>"></td>
                                    <?php foreach ( $attrs as $attr_name => $attr_data ) : ?>
                                        <td>
                                            <select class="var-attr" data-attr="<?php echo esc_attr( $attr_name ); ?>">
                                                <option value="">—</option>
                                                <?php foreach ( ( $attr_data['values'] ?? [] ) as $val ) : ?>
                                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $var['attributes'][ $attr_name ] ?? '', $val ); ?>>
                                                        <?php echo esc_html( $val ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endforeach; ?>
                                    <td><input type="number" step="0.01" class="var-price" value="<?php echo esc_attr( $var['price'] ); ?>"></td>
                                    <td><input type="number" class="var-qty" value="<?php echo esc_attr( $var['quantity'] ); ?>"></td>
                                    <td><a href="#" class="remove-variation"><?php esc_html_e( 'Remove', 'ulticommerce-core' ); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="no-variations">
                                <td colspan="<?php echo count( $attrs ) + 4; ?>">
                                    <?php esc_html_e( 'No variations yet. Click "Generate All Combinations" to create them.', 'ulticommerce-core' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p style="margin-top:10px;">
                    <button type="button" class="button" id="generate-variations"><?php esc_html_e( 'Generate All Combinations', 'ulticommerce-core' ); ?></button>
                    <button type="button" class="button button-primary" id="save-variations"><?php esc_html_e( 'Save Variations', 'ulticommerce-core' ); ?></button>
                    <span class="spinner" style="float:none;margin-top:0;"></span>
                </p>
            <?php endif; ?>
        </div>

        <?php
        wp_enqueue_script( 'ulticommerce-admin' );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    var attrs = ' . wp_json_encode( $attrs ) . ';
    var basePrice = "' . esc_js( $base_price ) . '";
    var baseSku = "' . esc_js( $base_sku ) . '";

    function cartesianProduct(arrays) {
        return arrays.reduce(function(a, b) {
            return a.flatMap(function(d) { return b.map(function(e) { return [].concat(d, e); }); });
        }, [[]]);
    }

    $("#generate-variations").on("click", function() {
        var attrNames = Object.keys(attrs);
        var attrValues = attrNames.map(function(n) { return attrs[n].values || []; });
        var combinations = cartesianProduct(attrValues);

        var $tbody = $("#variations-table tbody");
        $tbody.empty();

        combinations.forEach(function(combo) {
            var $row = $("<tr>");
            $row.append("<td><input type=\"text\" class=\"var-sku\" value=\"" + baseSku + "-" + combo.join("-") + "\"></td>");
            attrNames.forEach(function(name, i) {
                var $select = $("<select class=\"var-attr\" data-attr=\"" + name + "\"><option value=\"\">—</option></select>");
                (attrs[name].values || []).forEach(function(v) {
                    $select.append("<option value=\"" + v + "\"" + (v === combo[i] ? " selected" : "") + ">" + v + "</option>");
                });
                $row.append($("<td>").append($select));
            });
            $row.append("<td><input type=\"number\" step=\"0.01\" class=\"var-price\" value=\"" + basePrice + "\"></td>");
            $row.append("<td><input type=\"number\" class=\"var-qty\" value=\"0\"></td>");
            $row.append("<td><a href=\"#\" class=\"remove-variation\">Remove</a></td>");
            $tbody.append($row);
        });
    });

    $(document).on("click", ".remove-variation", function(e) {
        e.preventDefault();
        $(this).closest("tr").remove();
    });

    $("#save-variations").on("click", function() {
        var $spinner = $(this).siblings(".spinner");
        $spinner.addClass("is-active");

        var variations = [];
        $("#variations-table tbody tr").each(function() {
            var $row = $(this);
            if ($row.hasClass("no-variations")) return;
            var attrs_data = {};
            $row.find(".var-attr").each(function() {
                attrs_data[$(this).data("attr")] = $(this).val();
            });
            variations.push({
                id: $row.data("variation-id") || 0,
                sku: $row.find(".var-sku").val(),
                attributes: attrs_data,
                price: $row.find(".var-price").val(),
                quantity: $row.find(".var-qty").val(),
            });
        });

        $.post(ajaxurl, {
            action: "ulti_save_variations",
            post_id: ' . intval( $post->ID ) . ',
            variations: variations,
            _ajax_nonce: "' . wp_create_nonce( 'ulti_save_variations' ) . '"
        }, function(resp) {
            $spinner.removeClass("is-active");
            if (resp.success) {
                alert("' . esc_js( __( 'Variations saved!', 'ulticommerce-core' ) ) . '");
            } else {
                alert("' . esc_js( __( 'Error saving variations.', 'ulticommerce-core' ) ) . '");
            }
        });
    });
});
' ); ?>
        <?php
    }

    public function ajax_save_variations() {
        check_ajax_referer( 'ulti_save_variations', '_ajax_nonce' );

        $post_id    = intval( $_POST['post_id'] );
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error();
        }
        $variations = json_decode( stripslashes( $_POST['variations'] ), true );

        if ( ! $variations || ! is_array( $variations ) ) {
            wp_send_json_error();
        }

        $existing_ids = [];

        foreach ( $variations as $var ) {
            $var_id = intval( $var['id'] );

            $attr_string = '';
            if ( isset( $var['attributes'] ) && is_array( $var['attributes'] ) ) {
                ksort( $var['attributes'] );
                $attr_string = http_build_query( $var['attributes'] );
            }

            $existing = get_posts( [
                'post_type'      => 'product_variation',
                'post_parent'    => $post_id,
                'meta_key'       => '_variation_attr_hash',
                'meta_value'     => md5( $attr_string ),
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ] );

            $existing_id = ! empty( $existing ) ? $existing[0] : 0;

            if ( $existing_id && $var_id && $existing_id != $var_id ) {
                wp_delete_post( $var_id, true );
            }

            $post_data = [
                'post_type'   => 'product_variation',
                'post_parent' => $post_id,
                'post_status' => 'publish',
                'post_title'  => 'Variation #' . ( $var_id ?: 'new' ),
            ];

            if ( $var_id && get_post( $var_id ) ) {
                $post_data['ID'] = $var_id;
            }

            $saved_id = wp_insert_post( $post_data );

            if ( $saved_id ) {
                update_post_meta( $saved_id, '_variation_sku', sanitize_text_field( $var['sku'] ) );
                update_post_meta( $saved_id, '_variation_price', sanitize_text_field( $var['price'] ) );
                update_post_meta( $saved_id, '_variation_quantity', intval( $var['quantity'] ) );
                update_post_meta( $saved_id, '_variation_attr_hash', md5( $attr_string ) );

                $safe_attrs = [];
                foreach ( ( $var['attributes'] ?? [] ) as $k => $v ) {
                    $safe_attrs[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
                }
                update_post_meta( $saved_id, '_variation_attributes', $safe_attrs );

                $existing_ids[] = $saved_id;
            }
        }

        $old_variations = get_posts( [
            'post_type'      => 'product_variation',
            'post_parent'    => $post_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ] );

        foreach ( $old_variations as $old_id ) {
            if ( ! in_array( $old_id, $existing_ids ) ) {
                wp_delete_post( $old_id, true );
            }
        }

        wp_send_json_success();
    }

    private function get_variations( $post_id ) {
        $variations = get_posts( [
            'post_type'      => 'product_variation',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $result = [];
        foreach ( $variations as $var ) {
            $result[] = [
                'id'         => $var->ID,
                'sku'        => get_post_meta( $var->ID, '_variation_sku', true ),
                'price'      => get_post_meta( $var->ID, '_variation_price', true ),
                'quantity'   => get_post_meta( $var->ID, '_variation_quantity', true ),
                'attributes' => get_post_meta( $var->ID, '_variation_attributes', true ) ?: [],
            ];
        }

        return $result;
    }

    public function add_variations_column( $columns ) {
        $columns['product_variations'] = esc_html__( 'Variations', 'ulticommerce-core' );
        return $columns;
    }

    public function render_variations_column( $column, $post_id ) {
        if ( $column !== 'product_variations' ) return;
        $count = count( $this->get_variations( $post_id ) );
        echo $count ? esc_html( $count ) : '&mdash;';
    }
}

new UltiCommerce_Product_Variations();

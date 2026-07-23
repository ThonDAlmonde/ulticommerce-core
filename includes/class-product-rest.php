<?php

defined( 'ABSPATH' ) || exit;

class Ultico_Product_REST {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'wpc/v1', '/products/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_product' ],
            'permission_callback' => '__return_true',
            'args'               => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ( $param ) {
                        return is_numeric( $param );
                    },
                ],
            ],
        ] );

        register_rest_route( 'wpc/v1', '/products', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_products_list' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function get_product( $request ) {
        $product_id = (int) $request['id'];
        $post       = get_post( $product_id );

        if ( ! $post || $post->post_type !== 'product' || $post->post_status !== 'publish' ) {
            return new WP_Error( 'not_found', __( 'Product not found', 'ulticommerce' ), [ 'status' => 404 ] );
        }

        return $this->format_product( $post );
    }

    public function get_products_list( $request ) {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $request['per_page'] ?: 20,
            'paged'          => $request['page'] ?: 1,
            's'              => $request['search'] ?? '',
        ];

        if ( $request['category'] ) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_category',
                'field'    => 'slug',
                'terms'    => explode( ',', $request['category'] ),
            ];
        }

        $query     = new WP_Query( $args );
        $products  = [];

        foreach ( $query->posts as $post ) {
            $products[] = $this->format_product( $post );
        }

        return [
            'products'     => $products,
            'total'        => $query->found_posts,
            'total_pages'  => $query->max_num_pages,
            'current_page' => $args['paged'],
        ];
    }

    private function format_product( $post ) {
        $gallery_ids  = get_post_meta( $post->ID, '_product_gallery', true ) ?: [];
        $gallery_urls = [];
        foreach ( (array) $gallery_ids as $img_id ) {
            $url = wp_get_attachment_url( $img_id );
            if ( $url ) $gallery_urls[] = $url;
        }

        $attributes   = get_post_meta( $post->ID, '_product_attributes', true ) ?: [];
        $formatted_attrs = [];
        foreach ( $attributes as $slug => $data ) {
            $term = get_term_by( 'slug', $slug, 'product_attribute' );
            $formatted_attrs[] = [
                'name'   => $term ? $term->name : $slug,
                'slug'   => $slug,
                'values' => is_array( $data['values'] ?? '' ) ? $data['values'] : explode( ',', $data['values'] ?? '' ),
            ];
        }

        $variation_posts = get_posts( [
            'post_type'      => 'product_variation',
            'post_parent'    => $post->ID,
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $variations = [];
        foreach ( $variation_posts as $vp ) {
            $variations[] = [
                'id'         => $vp->ID,
                'sku'        => get_post_meta( $vp->ID, '_variation_sku', true ),
                'price'      => get_post_meta( $vp->ID, '_variation_price', true ),
                'quantity'   => get_post_meta( $vp->ID, '_variation_quantity', true ),
                'attributes' => get_post_meta( $vp->ID, '_variation_attributes', true ) ?: [],
            ];
        }

        $lang = get_option( 'ultico_default_language', 'en_US' );
        if ( function_exists( 'pll_get_post_language' ) && $lang !== 'en_US' ) {
            $lang = pll_get_post_language( $post->ID );
        }

        return [
            'id'                => $post->ID,
            'name'              => $post->post_title,
            'slug'              => $post->post_name,
            'description'       => $post->post_content,
            'sku'               => get_post_meta( $post->ID, '_product_sku', true ),
            'unit_price'        => get_post_meta( $post->ID, '_product_unit_price', true ),
            'discount_price'    => get_post_meta( $post->ID, '_product_discount_price', true ),
            'quantity'          => get_post_meta( $post->ID, '_product_quantity', true ),
            'weight'            => get_post_meta( $post->ID, '_product_weight', true ),
            'width'             => get_post_meta( $post->ID, '_product_width', true ),
            'height'            => get_post_meta( $post->ID, '_product_height', true ),
            'enabled'           => (bool) get_post_meta( $post->ID, '_product_enabled', true ),
            'language'          => $lang,
            'currency'          => get_option( 'ultico_default_currency', 'USD' ),
            'featured_image'    => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: null,
            'gallery'           => $gallery_urls,
            'categories'        => $this->get_term_list( $post->ID, 'product_category' ),
            'brands'            => $this->get_term_list( $post->ID, 'product_brand' ),
            'collections'       => $this->get_term_list( $post->ID, 'product_collection' ),
            'tags'              => $this->get_term_list( $post->ID, 'product_tag' ),
            'attributes'        => $formatted_attrs,
            'variations'        => $variations,
            'seo'               => [
                'title'       => get_post_meta( $post->ID, '_yoast_wpseo_title', true ) ?: $post->post_title,
                'description' => get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ?: wp_trim_words( $post->post_content, 20 ),
                'focus_keyword' => get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true ) ?: '',
            ],
            'permalink'         => get_permalink( $post->ID ),
            'date_created'      => $post->post_date,
            'date_modified'     => $post->post_modified,
        ];
    }

    private function get_term_list( $post_id, $taxonomy ) {
        $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'all' ] );
        if ( is_wp_error( $terms ) ) return [];
        return array_map( function ( $t ) {
            return [
                'id'       => $t->term_id,
                'name'     => $t->name,
                'slug'     => $t->slug,
                'count'    => $t->count,
            ];
        }, $terms );
    }
}

new Ultico_Product_REST();

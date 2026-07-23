<?php

defined( 'ABSPATH' ) || exit;

class Ultico_Product_Taxonomies {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomies' ] );
    }

    public function register_taxonomies() {
        $taxonomies = [
            'product_category' => [
                'label'        => __( 'Product Categories', 'ulticommerce' ),
                'singular'     => __( 'Product Category', 'ulticommerce' ),
                'hierarchical' => true,
                'rewrite'      => [ 'slug' => 'product-category' ],
            ],
            'product_brand' => [
                'label'        => __( 'Brands', 'ulticommerce' ),
                'singular'     => __( 'Brand', 'ulticommerce' ),
                'hierarchical' => true,
                'rewrite'      => [ 'slug' => 'brand' ],
            ],
            'product_collection' => [
                'label'        => __( 'Collections', 'ulticommerce' ),
                'singular'     => __( 'Collection', 'ulticommerce' ),
                'hierarchical' => true,
                'rewrite'      => [ 'slug' => 'collection' ],
            ],
            'product_tag' => [
                'label'        => __( 'Product Tags', 'ulticommerce' ),
                'singular'     => __( 'Product Tag', 'ulticommerce' ),
                'hierarchical' => false,
                'rewrite'      => [ 'slug' => 'product-tag' ],
            ],
        ];

        foreach ( $taxonomies as $slug => $config ) {
            register_taxonomy( $slug, 'product', [
                'labels'        => [
                    'name'          => $config['label'],
                    'singular_name' => $config['singular'],
                    /* translators: %s: taxonomy label */
                    'search_items'  => sprintf( __( 'Search %s', 'ulticommerce' ), $config['label'] ),
                    /* translators: %s: taxonomy label */
                    'all_items'     => sprintf( __( 'All %s', 'ulticommerce' ), $config['label'] ),
                    /* translators: %s: taxonomy singular name */
                    'edit_item'     => sprintf( __( 'Edit %s', 'ulticommerce' ), $config['singular'] ),
                    /* translators: %s: taxonomy singular name */
                    'update_item'   => sprintf( __( 'Update %s', 'ulticommerce' ), $config['singular'] ),
                    /* translators: %s: taxonomy singular name */
                    'add_new_item'  => sprintf( __( 'Add New %s', 'ulticommerce' ), $config['singular'] ),
                    /* translators: %s: taxonomy singular name */
                    'new_item_name' => sprintf( __( 'New %s Name', 'ulticommerce' ), $config['singular'] ),
                    'menu_name'     => $config['label'],
                ],
                'hierarchical'      => $config['hierarchical'],
                'public'            => true,
                'show_in_nav_menus' => true,
                'show_in_rest'      => true,
                'rewrite'           => $config['rewrite'],
                'show_admin_column' => true,
            ] );
        }
    }
}

new Ultico_Product_Taxonomies();

<?php
/**
 * Plugin Name: UltiCommerce
 * Plugin URI:  https://github.com/ThonDAlmonde/ulticommerce
 * Description: Core e-commerce plugin for UltiCommerce. Products, orders, cart, checkout, shipping, and coupons.
 * Version:     1.1.0
 * Author:      UltiCommerce
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ulticommerce
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ULTI_COMMERCE_VERSION', '1.1.0' );
define( 'ULTI_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ULTI_COMMERCE_URL', plugin_dir_url( __FILE__ ) );

$ulti_vendor_autoload = ULTI_COMMERCE_PATH . 'vendor/autoload.php';
if ( file_exists( $ulti_vendor_autoload ) ) {
    require $ulti_vendor_autoload;
}

require_once ULTI_COMMERCE_PATH . 'includes/class-post-type-product.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-post-type-order.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-product-taxonomies.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-product-attributes.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-product-variations.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-product-rest.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-product-settings.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-product-import.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-shipping.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-order-statuses.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-payment-gateways.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-payment-settings.php';
require_once ULTI_COMMERCE_PATH . 'includes/class-post-type-subscriber.php';

add_action( 'plugins_loaded', 'ulti_commerce_init' );
function ulti_commerce_init() {
    if ( ! class_exists( 'UltiCommerceLogin' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'UltiCommerce requires UltiCommerceLogin plugin to be installed and activated.', 'ulticommerce' )
                . '</p></div>';
        } );
    }
}

add_action( 'admin_enqueue_scripts', 'ulti_commerce_admin_assets' );
function ulti_commerce_admin_assets() {
    wp_register_style( 'ulticommerce-admin', ULTI_COMMERCE_URL . 'assets/admin.css', [], ULTI_COMMERCE_VERSION );
    wp_register_script( 'ulticommerce-admin', ULTI_COMMERCE_URL . 'assets/admin.js', [ 'jquery' ], ULTI_COMMERCE_VERSION, true );
}

add_action( 'widgets_init', 'ulti_register_login_widget' );
function ulti_register_login_widget() {
    require_once ULTI_COMMERCE_PATH . 'widgets/class-login-widget.php';
    register_widget( 'UltiCommerce_Login_Widget' );
}

add_action( 'init', 'ulti_register_variation_post_type' );
function ulti_register_variation_post_type() {
    register_post_type( 'product_variation', [
        'labels'              => [
            'name'          => __( 'Variations', 'ulticommerce' ),
            'singular_name' => __( 'Variation', 'ulticommerce' ),
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => false,
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => true,
        'hierarchical'        => false,
        'supports'            => [ 'title', 'page-attributes' ],
    ] );
}

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

define( 'ULTICO_VERSION', '1.1.0' );
define( 'ULTICO_PATH', plugin_dir_path( __FILE__ ) );
define( 'ULTICO_URL', plugin_dir_url( __FILE__ ) );

$ultico_vendor_autoload = ULTICO_PATH . 'vendor/autoload.php';
if ( file_exists( $ultico_vendor_autoload ) ) {
    require $ultico_vendor_autoload;
}

require_once ULTICO_PATH . 'includes/class-post-type-product.php';
require_once ULTICO_PATH . 'includes/class-post-type-order.php';
require_once ULTICO_PATH . 'includes/class-product-taxonomies.php';
require_once ULTICO_PATH . 'includes/class-product-attributes.php';
require_once ULTICO_PATH . 'includes/class-product-variations.php';
require_once ULTICO_PATH . 'includes/class-product-rest.php';
require_once ULTICO_PATH . 'includes/class-product-settings.php';
require_once ULTICO_PATH . 'includes/class-product-import.php';
require_once ULTICO_PATH . 'includes/class-shipping.php';
require_once ULTICO_PATH . 'includes/class-order-statuses.php';
require_once ULTICO_PATH . 'includes/class-payment-gateways.php';
require_once ULTICO_PATH . 'includes/class-payment-settings.php';
require_once ULTICO_PATH . 'includes/class-post-type-subscriber.php';

add_action( 'plugins_loaded', 'ultico_init' );
function ultico_init() {
    if ( ! class_exists( 'UltiCommerceLogin' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'UltiCommerce requires UltiCommerceLogin plugin to be installed and activated.', 'ulticommerce' )
                . '</p></div>';
        } );
    }
}

add_action( 'admin_enqueue_scripts', 'ultico_admin_assets' );
function ultico_admin_assets() {
    wp_register_style( 'ulticommerce-admin', ULTICO_URL . 'assets/admin.css', [], ULTICO_VERSION );
    wp_register_script( 'ulticommerce-admin', ULTICO_URL . 'assets/admin.js', [ 'jquery' ], ULTICO_VERSION, true );
}

add_action( 'widgets_init', 'ultico_register_login_widget' );
function ultico_register_login_widget() {
    require_once ULTICO_PATH . 'widgets/class-login-widget.php';
    register_widget( 'Ultico_Login_Widget' );
}

add_action( 'init', 'ultico_register_variation_post_type' );
function ultico_register_variation_post_type() {
    register_post_type( 'ultico_product_variation', [
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

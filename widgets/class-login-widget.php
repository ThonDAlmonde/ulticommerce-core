<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Login_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'ulti_commerce_login_widget',
            __( 'UltiCommerce Login', 'ulticommerce' ),
            [ 'description' => __( 'Display SSO login buttons and a login form.', 'ulticommerce' ) ]
        );
    }

    public function widget( $args, $instance ) {
        echo wp_kses_post( $args['before_widget'] );

        if ( ! empty( $instance['title'] ) ) {
            echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
        }

        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            /* translators: %s: user display name */
            echo '<p>' . esc_html( sprintf( __( 'Hello, %s!', 'ulticommerce' ), $current_user->display_name ) ) . '</p>';
            echo '<a href="' . esc_url( wp_logout_url( home_url() ) ) . '">' . esc_html__( 'Log out', 'ulticommerce' ) . '</a>';
        } else {
            $this->render_sso_buttons();
            $this->render_login_form();
        }

        echo wp_kses_post( $args['after_widget'] );
    }

    private function render_sso_buttons() {
        if ( ! class_exists( 'UltiCommerceLogin' ) ) {
            return;
        }

        $login     = UltiCommerceLogin::instance();
        $providers = $login->get_active_providers();

        if ( empty( $providers ) ) {
            return;
        }

        echo '<div class="ulti-sso-buttons">';
        foreach ( $providers as $provider ) {
            $auth_url = $provider->get_auth_url( home_url() );
            echo '<a href="' . esc_url( $auth_url ) . '" class="ulti-sso-btn ulti-sso-' . esc_attr( $provider->get_id() ) . '">'
                . esc_html( $provider->get_label() ) . '</a>';
        }
        echo '</div>';
        echo '<div class="ulti-sso-divider"><span>' . esc_html__( 'OR', 'ulticommerce' ) . '</span></div>';
    }

    private function render_login_form() {
        $redirect = home_url();
        ?>
        <form method="post" action="<?php echo esc_url( wp_login_url() ); ?>" class="ulti-login-form">
            <p>
                <label for="ulti-user-login"><?php esc_html_e( 'Username or Email', 'ulticommerce' ); ?></label>
                <input type="text" name="log" id="ulti-user-login" required>
            </p>
            <p>
                <label for="ulti-user-pass"><?php esc_html_e( 'Password', 'ulticommerce' ); ?></label>
                <input type="password" name="pwd" id="ulti-user-pass" required>
            </p>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
            <p>
                <button type="submit"><?php esc_html_e( 'Sign In', 'ulticommerce' ); ?></button>
            </p>
        </form>
        <?php
    }

    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Login', 'ulticommerce' );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'ulticommerce' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
                   value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance          = [];
        $instance['title'] = sanitize_text_field( $new_instance['title'] );
        return $instance;
    }
}

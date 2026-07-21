<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Payment_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=order',
            __( 'Payment Settings', 'ulticommerce' ),
            __( 'Payments', 'ulticommerce' ),
            'manage_options',
            'ulti-payment-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'ulti_payment_settings', 'ulti_bank_wire_details', [
            'sanitize_callback' => [ $this, 'sanitize_bank_details' ],
            'default'           => [
                'account_holder' => '',
                'account_number' => '',
                'bank_name'      => '',
                'branch'         => '',
                'country'        => '',
                'swift'          => '',
                'ifsc'           => '',
                'iban'           => '',
            ],
        ] );
    }

    public function sanitize_bank_details( $value ) {
        if ( ! is_array( $value ) ) return [];
        $keys = [ 'account_holder', 'account_number', 'bank_name', 'branch', 'country', 'swift', 'ifsc', 'iban' ];
        $clean = [];
        foreach ( $keys as $key ) {
            $clean[ $key ] = isset( $value[ $key ] ) ? sanitize_text_field( $value[ $key ] ) : '';
        }
        return $clean;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $bank = get_option( 'ulti_bank_wire_details', [] );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'ulti_payment_settings' ); ?>

                <h2 style="margin-top:24px;"><?php esc_html_e( 'Bank Wire Details', 'ulticommerce' ); ?></h2>
                <p><?php esc_html_e( 'Enter your bank account details for customers who choose to pay via bank wire transfer. These details will be shown on the order confirmation page.', 'ulticommerce' ); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bank_account_holder"><?php esc_html_e( "Account Holder's Name", 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[account_holder]" id="bank_account_holder" value="<?php echo esc_attr( $bank['account_holder'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_account_number"><?php esc_html_e( 'Account Number', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[account_number]" id="bank_account_number" value="<?php echo esc_attr( $bank['account_number'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_name"><?php esc_html_e( 'Bank Name', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[bank_name]" id="bank_name" value="<?php echo esc_attr( $bank['bank_name'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_branch"><?php esc_html_e( 'Branch Number', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[branch]" id="bank_branch" value="<?php echo esc_attr( $bank['branch'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_country"><?php esc_html_e( 'Country', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[country]" id="bank_country" value="<?php echo esc_attr( $bank['country'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_swift"><?php esc_html_e( 'SWIFT / BIC Code', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[swift]" id="bank_swift" value="<?php echo esc_attr( $bank['swift'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_ifsc"><?php esc_html_e( 'IFSC Code', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[ifsc]" id="bank_ifsc" value="<?php echo esc_attr( $bank['ifsc'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bank_iban"><?php esc_html_e( 'IBAN', 'ulticommerce' ); ?></label></th>
                        <td><input type="text" name="ulti_bank_wire_details[iban]" id="bank_iban" value="<?php echo esc_attr( $bank['iban'] ?? '' ); ?>" class="regular-text" style="max-width:400px;"></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new UltiCommerce_Payment_Settings();

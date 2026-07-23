<?php

defined( 'ABSPATH' ) || exit;

class Ultico_Product_Settings {

    private $languages = [
        'en_US' => 'English (US)',
        'th'    => 'ไทย (Thai)',
        'zh_CN' => '中文 (Chinese)',
        'fr_FR' => 'Français (French)',
        'de_DE' => 'Deutsch (German)',
        'ru_RU' => 'Русский (Russian)',
        'hi_IN' => 'हिन्दी (Hindi)',
    ];

    private $currencies = [
        'USD' => 'USD ($)',
        'THB' => 'THB (฿)',
        'CNY' => 'CNY (¥)',
        'EUR' => 'EUR (€)',
        'GBP' => 'GBP (£)',
        'RUB' => 'RUB (₽)',
        'INR' => 'INR (₹)',
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ultico_product',
            __( 'Product Settings', 'ulticommerce' ),
            __( 'Settings', 'ulticommerce' ),
            'manage_options',
            'ultico-product-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'ultico_product_settings', 'ultico_default_language', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'en_US',
        ] );
        register_setting( 'ultico_product_settings', 'ultico_default_currency', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'USD',
        ] );
        register_setting( 'ultico_product_settings', 'ultico_currency_display', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'symbol_before',
        ] );
        register_setting( 'ultico_product_settings', 'ultico_thousand_sep', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ',',
        ] );
        register_setting( 'ultico_product_settings', 'ultico_decimal_sep', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '.',
        ] );
        register_setting( 'ultico_product_settings', 'ultico_deduct_stock_on_status', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'paid',
        ] );
        register_setting( 'ultico_product_settings', 'ultico_enable_reviews', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1',
        ] );
    }

    public static function get_display_formats() {
        return [
            'symbol_before' => __( 'Symbol before ( &yen;80 )', 'ulticommerce' ),
            'symbol_after'  => __( 'Symbol after ( 80&yen; )', 'ulticommerce' ),
            'code_before'   => __( 'Code before ( JPY80 )', 'ulticommerce' ),
            'code_after'    => __( 'Code after ( 80JPY )', 'ulticommerce' ),
        ];
    }

    public static function get_separator_presets() {
        return [
            'british' => [ 'label' => __( 'British', 'ulticommerce' ), 'thousand' => ',', 'decimal' => '.', 'example' => '1,234.56' ],
            'european' => [ 'label' => __( 'European', 'ulticommerce' ), 'thousand' => '.', 'decimal' => ',', 'example' => '1.234,56' ],
            'french' => [ 'label' => __( 'French', 'ulticommerce' ), 'thousand' => ' ', 'decimal' => ',', 'example' => '1 234,56' ],
            'swiss' => [ 'label' => __( 'Swiss', 'ulticommerce' ), 'thousand' => '\'', 'decimal' => '.', 'example' => '1\'234.56' ],
        ];
    }

    public static function format_price_demo( $amount, $currency, $format, $thousand_sep = ',', $decimal_sep = '.' ) {
        $symbols = [ 'USD' => '$', 'THB' => '฿', 'CNY' => '¥', 'EUR' => '€', 'GBP' => '£', 'RUB' => '₽', 'INR' => '₹' ];
        $symbol  = $symbols[ $currency ] ?? '$';
        $price   = number_format( (float) $amount, 2, $decimal_sep, $thousand_sep );
        switch ( $format ) {
            case 'symbol_after': return $price . $symbol;
            case 'code_before':  return $currency . $price;
            case 'code_after':   return $price . $currency;
            default:             return $symbol . $price;
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $current_currency = get_option( 'ultico_default_currency', 'USD' );
        $current_format   = get_option( 'ultico_currency_display', 'symbol_before' );
        $current_thousand = get_option( 'ultico_thousand_sep', ',' );
        $current_decimal  = get_option( 'ultico_decimal_sep', '.' );
        $formats          = self::get_display_formats();
        $presets          = self::get_separator_presets();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'ultico_product_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ultico_default_language"><?php esc_html_e( 'Default Language', 'ulticommerce' ); ?></label>
                        </th>
                        <td>
                            <select name="ultico_default_language" id="ultico_default_language">
                                <?php foreach ( $this->languages as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( get_option( 'ultico_default_language', 'en_US' ), $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Choose the default language for new products.', 'ulticommerce' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ultico_default_currency"><?php esc_html_e( 'Default Currency', 'ulticommerce' ); ?></label>
                        </th>
                        <td>
                            <select name="ultico_default_currency" id="ultico_default_currency">
                                <?php foreach ( $this->currencies as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_currency, $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Choose the default currency for product pricing.', 'ulticommerce' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ultico_currency_display"><?php esc_html_e( 'Currency Display Format', 'ulticommerce' ); ?></label>
                        </th>
                        <td>
                            <select name="ultico_currency_display" id="ultico_currency_display">
                                <?php foreach ( $formats as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_format, $code ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'How prices are displayed across the store.', 'ulticommerce' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Number Separators', 'ulticommerce' ); ?>
                        </th>
                        <td>
                            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:8px;">
                                <div>
                                    <label for="ultico_separator_preset" style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;"><?php esc_html_e( 'Preset', 'ulticommerce' ); ?></label>
                                    <select id="ultico_separator_preset">
                                        <?php foreach ( $presets as $key => $p ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" data-thousand="<?php echo esc_attr( $p['thousand'] ); ?>" data-decimal="<?php echo esc_attr( $p['decimal'] ); ?>" <?php selected( $p['thousand'] === $current_thousand && $p['decimal'] === $current_decimal, true ); ?>><?php echo esc_html( $p['label'] ); ?> (<?php echo esc_html( $p['example'] ); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="ultico_thousand_sep" style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;"><?php esc_html_e( 'Thousand', 'ulticommerce' ); ?></label>
                                    <input type="text" name="ultico_thousand_sep" id="ultico_thousand_sep" value="<?php echo esc_attr( $current_thousand ); ?>" size="3" maxlength="2" style="text-align:center;">
                                </div>
                                <div>
                                    <label for="ultico_decimal_sep" style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;"><?php esc_html_e( 'Decimal', 'ulticommerce' ); ?></label>
                                    <input type="text" name="ultico_decimal_sep" id="ultico_decimal_sep" value="<?php echo esc_attr( $current_decimal ); ?>" size="3" maxlength="2" style="text-align:center;">
                                </div>
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'Select a preset or enter custom separators.', 'ulticommerce' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Stock & Inventory', 'ulticommerce' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ultico_deduct_stock_on_status"><?php esc_html_e( 'Deduct Stock On Status', 'ulticommerce' ); ?></label>
                        </th>
                        <td>
                            <select name="ultico_deduct_stock_on_status" id="ultico_deduct_stock_on_status">
                                <option value="new" <?php selected( get_option( 'ultico_deduct_stock_on_status', 'paid' ), 'new' ); ?>><?php esc_html_e( 'New', 'ulticommerce' ); ?></option>
                                <option value="pending_payment" <?php selected( get_option( 'ultico_deduct_stock_on_status', 'paid' ), 'pending_payment' ); ?>><?php esc_html_e( 'Pending Payment', 'ulticommerce' ); ?></option>
                                <option value="paid" <?php selected( get_option( 'ultico_deduct_stock_on_status', 'paid' ), 'paid' ); ?>><?php esc_html_e( 'Paid', 'ulticommerce' ); ?></option>
                                <option value="deducted" <?php selected( get_option( 'ultico_deduct_stock_on_status', 'paid' ), 'deducted' ); ?>><?php esc_html_e( 'Deducted', 'ulticommerce' ); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Stock will be automatically deducted when an order reaches this status.', 'ulticommerce' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Customer Reviews', 'ulticommerce' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ultico_enable_reviews"><?php esc_html_e( 'Enable Reviews', 'ulticommerce' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="ultico_enable_reviews" id="ultico_enable_reviews" value="1" <?php checked( get_option( 'ultico_enable_reviews', '1' ), '1' ); ?>>
                                <?php esc_html_e( 'Allow customers to leave product reviews', 'ulticommerce' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When disabled, the reviews section and review form will be hidden on product pages.', 'ulticommerce' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div id="currency-preview" style="margin-top:16px;padding:16px;background:#f0f0f1;border-radius:4px;">
                    <div style="font-size:12px;font-weight:600;color:#666;margin-bottom:8px;"><?php esc_html_e( 'Live Preview', 'ulticommerce' ); ?></div>
                    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                        <div style="text-align:center;">
                            <div id="preview-amount" style="font-size:28px;font-weight:700;"></div>
                        </div>
                        <div style="display:flex;gap:20px;flex-wrap:wrap;">
                            <?php $demo_currencies = [ 'USD' => 1234567.89, 'THB' => 320.50, 'EUR' => 45999.99, 'JPY' => 1200 ]; ?>
                            <?php foreach ( $demo_currencies as $ccy => $amt ) : ?>
                            <div>
                                <div style="font-size:11px;color:#666;margin-bottom:2px;"><?php echo esc_html( $ccy ); ?></div>
                                <div class="currency-example" data-currency="<?php echo esc_attr( $ccy ); ?>" data-amount="<?php echo esc_attr( $amt ); ?>" style="font-size:18px;font-weight:600;"></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <?php
        wp_enqueue_script( 'ulticommerce-admin' );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    var symbols = ' . wp_json_encode( [ 'USD' => '$', 'THB' => '฿', 'CNY' => '¥', 'EUR' => '€', 'GBP' => '£', 'RUB' => '₽', 'INR' => '₹', 'JPY' => '¥' ] ) . ';

    function formatPrice(amount, currency, format, thousand, decimal) {
        var symbol = symbols[currency] || "$";
        var parts = parseFloat(amount).toFixed(2).split(".");
        var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand === " " ? " " : (thousand || ","));
        var price = intPart + (decimal || ".") + parts[1];
        switch (format) {
            case "symbol_after": return price + symbol;
            case "code_before":  return currency + price;
            case "code_after":   return price + currency;
            default:             return symbol + price;
        }
    }

    function updatePreview() {
        var format = $("#ultico_currency_display").val();
        var currency = $("#ultico_default_currency").val();
        var thousand = $("#ultico_thousand_sep").val() || ",";
        var decimal = $("#ultico_decimal_sep").val() || ".";
        var amount = 1234567.89;

        $("#preview-amount").text(formatPrice(amount, currency, format, thousand, decimal));

        $(".currency-example").each(function() {
            var $el = $(this);
            var ccy = $el.data("currency");
            var amt = $el.data("amount");
            $el.text(formatPrice(amt, ccy, format, thousand, decimal));
        });
    }

    $("#ultico_separator_preset").on("change", function() {
        var opt = $(this).find(":selected");
        $("#ultico_thousand_sep").val(opt.data("thousand"));
        $("#ultico_decimal_sep").val(opt.data("decimal"));
        updatePreview();
    });

    $("#ultico_currency_display, #ultico_default_currency, #ultico_thousand_sep, #ultico_decimal_sep").on("change keyup", updatePreview);
    updatePreview();
});
' );
    }
}

new Ultico_Product_Settings();

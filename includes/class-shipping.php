<?php

defined( 'ABSPATH' ) || exit;

class Ultico_Shipping {

    private static $country_alpha2_to_alpha3 = [
        'US' => 'USA', 'TH' => 'THA', 'CN' => 'CHN', 'FR' => 'FRA',
        'DE' => 'DEU', 'RU' => 'RUS', 'IN' => 'IND', 'JP' => 'JPN',
        'KR' => 'KOR', 'SG' => 'SGP', 'MY' => 'MYS', 'VN' => 'VNM',
        'PH' => 'PHL', 'ID' => 'IDN', 'AU' => 'AUS', 'GB' => 'GBR',
        'CA' => 'CAN', 'BR' => 'BRA', 'MX' => 'MEX', 'AE' => 'ARE',
        'LA' => 'LAO', 'MM' => 'MMR', 'KH' => 'KHM', 'HK' => 'HKG',
        'TW' => 'TWN', 'NZ' => 'NZL', 'ZA' => 'ZAF', 'SA' => 'SAU',
        'QA' => 'QAT', 'KW' => 'KWT', 'BH' => 'BHR', 'OM' => 'OMN',
        'TR' => 'TUR', 'IT' => 'ITA', 'ES' => 'ESP', 'NL' => 'NLD',
        'SE' => 'SWE', 'CH' => 'CHE', 'NO' => 'NOR', 'DK' => 'DNK',
        'FI' => 'FIN', 'PL' => 'POL', 'AT' => 'AUT', 'BE' => 'BEL',
        'PT' => 'PRT', 'GR' => 'GRC', 'IE' => 'IRL', 'CZ' => 'CZE',
        'HU' => 'HUN', 'RO' => 'ROU', 'UA' => 'UKR', 'IL' => 'ISR',
    ];

    private static $alpha3_to_alpha2 = [];

    private static $country_list_alpha3 = [
        'THA' => 'Thailand', 'USA' => 'United States', 'CHN' => 'China',
        'FRA' => 'France', 'DEU' => 'Germany', 'RUS' => 'Russia',
        'IND' => 'India', 'JPN' => 'Japan', 'KOR' => 'South Korea',
        'SGP' => 'Singapore', 'MYS' => 'Malaysia', 'VNM' => 'Vietnam',
        'PHL' => 'Philippines', 'IDN' => 'Indonesia', 'AUS' => 'Australia',
        'GBR' => 'United Kingdom', 'CAN' => 'Canada', 'BRA' => 'Brazil',
        'MEX' => 'Mexico', 'ARE' => 'United Arab Emirates',
        'LAO' => 'Laos', 'MMR' => 'Myanmar', 'KHM' => 'Cambodia',
        'HKG' => 'Hong Kong', 'TWN' => 'Taiwan', 'NZL' => 'New Zealand',
        'ZAF' => 'South Africa', 'SAU' => 'Saudi Arabia', 'QAT' => 'Qatar',
        'KWT' => 'Kuwait', 'BHR' => 'Bahrain', 'OMN' => 'Oman',
        'TUR' => 'Turkey', 'ITA' => 'Italy', 'ESP' => 'Spain',
        'NLD' => 'Netherlands', 'SWE' => 'Sweden', 'CHE' => 'Switzerland',
        'NOR' => 'Norway', 'DNK' => 'Denmark', 'FIN' => 'Finland',
        'POL' => 'Poland', 'AUT' => 'Austria', 'BEL' => 'Belgium',
        'PRT' => 'Portugal', 'GRC' => 'Greece', 'IRL' => 'Ireland',
        'CZE' => 'Czech Republic', 'HUN' => 'Hungary', 'ROU' => 'Romania',
        'UKR' => 'Ukraine', 'ISR' => 'Israel',
    ];

    private static $weight_units = [
        'g'   => [ 'label' => 'Grams (g)',       'to_g' => 1 ],
        'kg'  => [ 'label' => 'Kilograms (kg)',   'to_g' => 1000 ],
        'lb'  => [ 'label' => 'Pounds (lb)',      'to_g' => 453.592 ],
        'jin' => [ 'label' => 'Jin (斤)',          'to_g' => 500 ],
    ];

    public function __construct() {
        self::$alpha3_to_alpha2 = array_flip( self::$country_alpha2_to_alpha3 );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
        add_action( 'wp_ajax_ultico_estimate_shipping', [ $this, 'ajax_estimate_shipping' ] );
        add_action( 'wp_ajax_nopriv_ultico_estimate_shipping', [ $this, 'ajax_estimate_shipping' ] );
        add_action( 'admin_post_ultico_shipping_import_csv', [ $this, 'handle_csv_import' ] );
        add_action( 'admin_post_ultico_shipping_download_csv', [ $this, 'download_csv_template' ] );
        add_action( 'wp_ajax_ultico_get_shipping_rates', [ $this, 'ajax_get_shipping_rates' ] );
        add_action( 'wp_ajax_nopriv_ultico_get_shipping_rates', [ $this, 'ajax_get_shipping_rates' ] );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=ultico_product',
            __( 'Shipping', 'ulticommerce' ),
            __( 'Shipping', 'ulticommerce' ),
            'manage_options',
            'ultico-shipping',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'ultico_shipping_settings', 'ultico_shipping_warehouse', [
            'sanitize_callback' => [ $this, 'sanitize_warehouse' ],
            'default' => [ 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => 'THA' ],
        ] );
        register_setting( 'ultico_shipping_settings', 'ultico_shipping_cross_border', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ] );
        register_setting( 'ultico_shipping_settings', 'ultico_shipping_supported_countries', [
            'sanitize_callback' => [ $this, 'sanitize_countries' ],
            'default' => [ 'THA' ],
        ] );
        register_setting( 'ultico_shipping_settings', 'ultico_shipping_weight_unit', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'g',
        ] );
        register_setting( 'ultico_shipping_settings', 'ultico_shipping_rates', [
            'sanitize_callback' => [ $this, 'sanitize_rates' ],
            'default' => [],
        ] );
    }

    public function sanitize_warehouse( $input ) {
        if ( ! is_array( $input ) ) return [];
        return [
            'address' => sanitize_text_field( $input['address'] ?? '' ),
            'city'    => sanitize_text_field( $input['city'] ?? '' ),
            'state'   => sanitize_text_field( $input['state'] ?? '' ),
            'zip'     => sanitize_text_field( $input['zip'] ?? '' ),
            'country' => sanitize_text_field( $input['country'] ?? 'THA' ),
        ];
    }

    public function sanitize_countries( $input ) {
        if ( is_string( $input ) ) {
            $input = array_map( 'trim', explode( ',', $input ) );
        }
        if ( ! is_array( $input ) ) {
            return [];
        }
        return array_filter( array_map( 'sanitize_text_field', $input ) );
    }

    public function sanitize_rates( $input ) {
        if ( ! is_array( $input ) ) return [];
        $cleaned = [];
        foreach ( $input as $row ) {
            if ( empty( $row['provider'] ) && empty( $row['service'] ) ) continue;
            $cleaned[] = [
                'provider'   => sanitize_text_field( $row['provider'] ?? '' ),
                'service'    => sanitize_text_field( $row['service'] ?? '' ),
                'country'    => sanitize_text_field( strtoupper( $row['country'] ?? '' ) ),
                'min_weight' => floatval( $row['min_weight'] ?? 0 ),
                'max_weight' => floatval( $row['max_weight'] ?? 0 ),
                'postcode'   => sanitize_text_field( $row['postcode'] ?? '' ),
                'fee'        => floatval( $row['fee'] ?? 0 ),
            ];
        }
        return $cleaned;
    }

    public function admin_scripts( $hook ) {
        if ( $hook !== 'products_page_ultico-shipping' ) return;
        wp_enqueue_style( 'ulticommerce-admin', plugin_dir_url( __DIR__ ) . 'assets/admin.css', [], '1.0.0' );
        wp_enqueue_script( 'ulticommerce-admin', plugin_dir_url( __DIR__ ) . 'assets/admin.js', [ 'jquery' ], '1.0.0', true );
        wp_add_inline_script( 'ulticommerce-admin', '
jQuery(function($) {
    var rowIdx = 1000;
    $(document).on("click", ".add-rate-row", function() {
        var tpl = $("#rate-row-template").html().replace(/__ROWIDX__/g, rowIdx++);
        $("#rates-table-body").append(tpl);
    });
    $(document).on("click", ".remove-row", function() {
        $(this).closest("tr").remove();
    });
    $(document).on("change", "#cb-cross-border", function() {
        $(".cross-border-fields").toggle(this.checked);
    });
    $(document).on("change", "#country-multiselect", function() {
        var vals = $(this).val() || [];
        var tags = $("#country-tags");
        tags.empty();
        vals.forEach(function(v) {
            var label = $(this).find("option[value=\"" + v + "\"]").text();
            tags.append("<span class=\"tag\">" + label + " <span class=\"remove\" data-val=\"" + v + "\">&times;</span></span>");
        }.bind(this));
        $("#supported-countries-input").val(vals.join(","));
    });
    $(document).on("click", "#country-tags .remove", function() {
        var val = $(this).data("val");
        var select = $("#country-multiselect");
        var current = select.val() || [];
        select.val(current.filter(function(v) { return v !== val; })).trigger("change");
    });
});
' );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $warehouse = get_option( 'ultico_shipping_warehouse', [ 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => 'THA' ] );
        $cross_border = get_option( 'ultico_shipping_cross_border', false );
        $supported = get_option( 'ultico_shipping_supported_countries', [ 'THA' ] );
        $weight_unit = get_option( 'ultico_shipping_weight_unit', 'g' );
        $rates = get_option( 'ultico_shipping_rates', [] );
        ?>
        <div class="wrap ultico-shipping-wrap">
            <h1><?php esc_html_e( 'Shipping Settings', 'ulticommerce' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'ultico_shipping_settings' ); ?>

                <div class="ultico-ship-section">
                    <h2><?php esc_html_e( 'Warehouse Address', 'ulticommerce' ); ?></h2>
                    <div class="ultico-field-row">
                        <div>
                            <label><?php esc_html_e( 'Address', 'ulticommerce' ); ?></label>
                            <input type="text" name="ultico_shipping_warehouse[address]" value="<?php echo esc_attr( $warehouse['address'] ?? '' ); ?>" class="regular-text">
                        </div>
                        <div>
                            <label><?php esc_html_e( 'City', 'ulticommerce' ); ?></label>
                            <input type="text" name="ultico_shipping_warehouse[city]" value="<?php echo esc_attr( $warehouse['city'] ?? '' ); ?>">
                        </div>
                        <div>
                            <label><?php esc_html_e( 'State', 'ulticommerce' ); ?></label>
                            <input type="text" name="ultico_shipping_warehouse[state]" value="<?php echo esc_attr( $warehouse['state'] ?? '' ); ?>">
                        </div>
                    </div>
                    <div class="ultico-field-row">
                        <div>
                            <label><?php esc_html_e( 'ZIP Code', 'ulticommerce' ); ?></label>
                            <input type="text" name="ultico_shipping_warehouse[zip]" value="<?php echo esc_attr( $warehouse['zip'] ?? '' ); ?>">
                        </div>
                        <div>
                            <label><?php esc_html_e( 'Country', 'ulticommerce' ); ?></label>
                            <select name="ultico_shipping_warehouse[country]">
                                <?php foreach ( self::$country_list_alpha3 as $code => $label ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $warehouse['country'] ?? 'THA', $code ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div></div>
                    </div>
                </div>

                <div class="ultico-ship-section">
                    <h2>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="ultico_shipping_cross_border" value="1" id="cb-cross-border" <?php checked( $cross_border ); ?>>
                            <?php esc_html_e( 'Enable Cross Border Shipping', 'ulticommerce' ); ?>
                        </label>
                    </h2>
                    <p style="font-size:13px;color:#666;"><?php esc_html_e( 'When disabled, shipping is limited to domestic (warehouse country) and customers can only save domestic addresses.', 'ulticommerce' ); ?></p>

                    <div class="cross-border-fields" style="<?php echo $cross_border ? '' : 'display:none;'; ?>">
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'Supported Countries', 'ulticommerce' ); ?></label>
                        <select id="country-multiselect" multiple style="width:100%;height:120px;">
                            <?php foreach ( self::$country_list_alpha3 as $code => $label ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php echo in_array( $code, $supported ) ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="ultico_shipping_supported_countries" id="supported-countries-input" value="<?php echo esc_attr( implode( ',', $supported ) ); ?>">
                        <div id="country-tags">
                            <?php foreach ( $supported as $c ) : ?>
                                <?php if ( isset( self::$country_list_alpha3[ $c ] ) ) : ?>
                                    <span class="tag"><?php echo esc_html( self::$country_list_alpha3[ $c ] ); ?> <span class="remove" data-val="<?php echo esc_attr( $c ); ?>">&times;</span></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="ultico-ship-section">
                    <h2><?php esc_html_e( 'Weight Unit', 'ulticommerce' ); ?></h2>
                    <select name="ultico_shipping_weight_unit">
                        <?php foreach ( self::$weight_units as $key => $info ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $weight_unit, $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Unit used for product weight input and shipping rate table.', 'ulticommerce' ); ?></p>
                </div>

                <div class="ultico-ship-section">
                    <h2><?php esc_html_e( 'Shipping Rate Table', 'ulticommerce' ); ?></h2>
                    <p style="font-size:13px;color:#666;">
                        <?php esc_html_e( 'Define shipping rates. The system matches the first row where weight &le; Max Weight and Post Code matches the destination. Leave Post Code empty to match all.', 'ulticommerce' ); ?>
                        <?php esc_html_e( 'Wildcards: * matches anything, ? matches single char, ranges like 10xxx match 10000-10999.', 'ulticommerce' ); ?>
                    </p>

                    <div class="rates-table-wrap">
                        <table class="rates-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Provider', 'ulticommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Service Type', 'ulticommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Country', 'ulticommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Min Weight', 'ulticommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Max Weight', 'ulticommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Post Code', 'ulticommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Fee', 'ulticommerce' ); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="rates-table-body">
                                <?php foreach ( $rates as $ridx => $row ) : ?>
                                    <tr>
                                        <td><input type="text" name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][provider]" value="<?php echo esc_attr( $row['provider'] ); ?>" placeholder="e.g. Kerry"></td>
                                        <td><input type="text" name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][service]" value="<?php echo esc_attr( $row['service'] ); ?>" placeholder="e.g. Standard"></td>
                                        <td>
                                            <select name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][country]" style="width:120px;">
                                                <option value=""><?php esc_html_e( 'All', 'ulticommerce' ); ?></option>
                                                <?php foreach ( self::$country_list_alpha3 as $code => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $row['country'], $code ); ?>><?php echo esc_html( $code ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][min_weight]" value="<?php echo esc_attr( $row['min_weight'] ); ?>" step="0.001" min="0" class="weight-input"></td>
                                        <td><input type="number" name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][max_weight]" value="<?php echo esc_attr( $row['max_weight'] ); ?>" step="0.001" min="0" class="weight-input"></td>
                                        <td><input type="text" name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][postcode]" value="<?php echo esc_attr( $row['postcode'] ?? '' ); ?>" placeholder="*" style="width:100px;"></td>
                                        <td><input type="number" name="ultico_shipping_rates[<?php echo esc_attr( $ridx ); ?>][fee]" value="<?php echo esc_attr( $row['fee'] ); ?>" step="0.01" min="0" class="fee-input"></td>
                                        <td><span class="remove-row"><?php esc_html_e( 'Remove', 'ulticommerce' ); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="button add-rate-row" style="margin-top:8px;">+ <?php esc_html_e( 'Add Row', 'ulticommerce' ); ?></button>

                    <p><?php submit_button( __( 'Save Shipping Settings', 'ulticommerce' ) ); ?></p>
                </div>
            </form>

            <div class="ultico-ship-section import-section">
                <h2><?php esc_html_e( 'Import / Export Rate Table', 'ulticommerce' ); ?></h2>
                <p><?php esc_html_e( 'CSV format: provider, service, country, min_weight, max_weight, postcode, fee', 'ulticommerce' ); ?></p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="ultico_shipping_import_csv">
                        <?php wp_nonce_field( 'ultico_shipping_csv' ); ?>
                        <input type="file" name="shipping_csv" accept=".csv" required>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Import CSV', 'ulticommerce' ); ?></button>
                    </form>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=ultico_shipping_download_csv' ) ); ?>" class="button"><?php esc_html_e( 'Download Template', 'ulticommerce' ); ?></a>
                </div>
            </div>
        </div>

        <template id="rate-row-template">
            <tr>
                <td><input type="text" name="ultico_shipping_rates[__ROWIDX__][provider]" value="" placeholder="e.g. Kerry"></td>
                <td><input type="text" name="ultico_shipping_rates[__ROWIDX__][service]" value="" placeholder="e.g. Standard"></td>
                <td>
                    <select name="ultico_shipping_rates[__ROWIDX__][country]" style="width:120px;">
                        <option value=""><?php esc_html_e( 'All', 'ulticommerce' ); ?></option>
                        <?php foreach ( self::$country_list_alpha3 as $code => $label ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $code ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="ultico_shipping_rates[__ROWIDX__][min_weight]" value="" step="0.001" min="0" class="weight-input"></td>
                <td><input type="number" name="ultico_shipping_rates[__ROWIDX__][max_weight]" value="" step="0.001" min="0" class="weight-input"></td>
                <td><input type="text" name="ultico_shipping_rates[__ROWIDX__][postcode]" value="" placeholder="*" style="width:100px;"></td>
                <td><input type="number" name="ultico_shipping_rates[__ROWIDX__][fee]" value="" step="0.01" min="0" class="fee-input"></td>
                <td><span class="remove-row"><?php esc_html_e( 'Remove', 'ulticommerce' ); ?></span></td>
            </tr>
        </template>
        <?php
    }

    public function handle_csv_import() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        check_admin_referer( 'ultico_shipping_csv' );

        if ( empty( $_FILES['shipping_csv']['tmp_name'] ) ) {
            wp_die( esc_html__( 'No file uploaded.', 'ulticommerce' ) );
        }

        $csv_file = sanitize_text_field( wp_unslash( $_FILES['shipping_csv']['tmp_name'] ?? '' ) );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $csv_file, 'r' );
        if ( ! $handle ) wp_die( esc_html__( 'Cannot read file.', 'ulticommerce' ) );

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            fclose( $handle ); wp_die( esc_html__( 'Empty CSV.', 'ulticommerce' ) ); }

        $header = array_map( 'trim', $header );
        $header = array_map( 'strtolower', $header );

        $col_map = [
            'provider'    => array_search( 'provider', $header ),
            'service'     => array_search( 'service', $header ) ?: array_search( 'service type', $header ),
            'country'     => array_search( 'country', $header ),
            'min_weight'  => array_search( 'min weight', $header ) ?: array_search( 'min_weight', $header ),
            'max_weight'  => array_search( 'max weight', $header ) ?: array_search( 'max_weight', $header ),
            'postcode'    => array_search( 'post code', $header ) ?: array_search( 'postcode', $header ) ?: array_search( 'zip', $header ),
            'fee'         => array_search( 'fee', $header ) ?: array_search( 'shipping fee', $header ) ?: array_search( 'cost', $header ),
        ];

        $rates = [];
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $entry = [];
            foreach ( $col_map as $key => $idx ) {
                if ( $idx === false ) {
                    $entry[ $key ] = '';
                } else {
                    $entry[ $key ] = isset( $row[ $idx ] ) ? trim( $row[ $idx ] ) : '';
                }
            }

            $entry['country'] = strtoupper( $entry['country'] );
            if ( isset( self::$country_alpha2_to_alpha3[ $entry['country'] ] ) ) {
                $entry['country'] = self::$country_alpha2_to_alpha3[ $entry['country'] ];
            }

            $entry['min_weight'] = floatval( $entry['min_weight'] );
            $entry['max_weight'] = floatval( $entry['max_weight'] );
            $entry['fee'] = floatval( $entry['fee'] );

            if ( ! empty( $entry['provider'] ) ) {
                $rates[] = $entry;
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );

        if ( empty( $rates ) ) {
            wp_die( esc_html__( 'No valid rows found in CSV.', 'ulticommerce' ) );
        }

        $existing = get_option( 'ultico_shipping_rates', [] );
        $merged = array_merge( $existing, $rates );
        update_option( 'ultico_shipping_rates', $merged );

        wp_safe_redirect( admin_url( 'edit.php?post_type=ultico_product&page=ultico-shipping&imported=' . count( $rates ) ) );
        exit;
    }

    public function download_csv_template() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="shipping-rates-template.csv"' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Provider', 'Service', 'Country', 'Min Weight', 'Max Weight', 'Post Code', 'Fee' ] );
        fputcsv( $out, [ 'Kerry', 'Standard', 'THA', '0', '5', '', '50' ] );
        fputcsv( $out, [ 'ไปรษณีย์ไทย', 'EMS', 'THA', '0', '1', '', '32' ] );
        fputcsv( $out, [ 'J&T', 'Express', '', '0', '10', '10xxx', '35' ] );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $out );
        exit;
    }

    /* =============== WEIGHT HELPERS =============== */

    public static function convert_to_g( $value, $unit ) {
        $units = self::$weight_units;
        if ( isset( $units[ $unit ] ) ) {
            return $value * $units[ $unit ]['to_g'];
        }
        return $value;
    }

    public static function get_cart_weight_g() {
        $cart = ultico_get_cart();
        $unit = get_option( 'ultico_shipping_weight_unit', 'g' );
        $total = 0;
        foreach ( $cart as $item ) {
            $pid = intval( $item['product_id'] ?? 0 );
            $qty = intval( $item['quantity'] ?? 1 );
            if ( ! $pid ) continue;
            $attrs = $item['attributes'] ?? [];
            $weight = 0;
            if ( ! empty( $attrs ) ) {
                $vars = get_posts( [ 'post_type' => 'ultico_product_variation', 'post_parent' => $pid, 'posts_per_page' => -1 ] );
                foreach ( $vars as $var ) {
                    $var_attrs = get_post_meta( $var->ID, '_variation_attributes', true ) ?: [];
                    if ( md5( serialize( $var_attrs ) ) === md5( serialize( $attrs ) ) ) {
                        $weight = floatval( get_post_meta( $var->ID, '_variation_weight', true ) ?: 0 );
                        break;
                    }
                }
            }
            if ( ! $weight ) {
                $weight = floatval( get_post_meta( $pid, '_product_weight', true ) ?: 0 );
            }
            $total += $weight * $qty;
        }
        return self::convert_to_g( $total, $unit );
    }

    /* =============== RATE ENGINE =============== */

    public static function get_rates_for_destination( $country, $state = '', $zip = '' ) {
        $rates = get_option( 'ultico_shipping_rates', [] );
        $warehouse = get_option( 'ultico_shipping_warehouse', [ 'country' => 'THA' ] );
        $cross_border = get_option( 'ultico_shipping_cross_border', false );
        $supported = get_option( 'ultico_shipping_supported_countries', [ 'THA' ] );
        $total_weight_g = self::get_cart_weight_g();
        $cart = ultico_get_cart();
        $subtotal = ultico_cart_subtotal();

        $warehouse_country = $warehouse['country'] ?? 'THA';
        $domestic_only = ! $cross_border;

        if ( empty( $country ) ) {
            $country = $warehouse_country;
        }

        if ( $domestic_only && $country !== $warehouse_country ) {
            return [];
        }

        if ( $cross_border && ! empty( $supported ) && ! in_array( $country, $supported ) ) {
            return [];
        }

        $matching = [];
        foreach ( $rates as $row ) {
            $row_country = $row['country'] ?? '';

            if ( ! empty( $row_country ) && $row_country !== $country ) {
                continue;
            }

            $min_w = floatval( $row['min_weight'] ?? 0 );
            $max_w = floatval( $row['max_weight'] ?? 0 );

            if ( $min_w > 0 && $total_weight_g < self::convert_to_g( $min_w, get_option( 'ultico_shipping_weight_unit', 'g' ) ) ) continue;
            if ( $max_w > 0 && $total_weight_g > self::convert_to_g( $max_w, get_option( 'ultico_shipping_weight_unit', 'g' ) ) ) continue;

            $pc = $row['postcode'] ?? '';
            if ( ! empty( $pc ) && ! self::match_postcode( $zip, $pc ) ) continue;

            $matching[] = [
                'id'      => sanitize_title( $row['provider'] . '-' . $row['service'] . '-' . $row['country'] ),
                'provider' => $row['provider'] ?? '',
                'service' => $row['service'] ?? '',
                'cost'    => floatval( $row['fee'] ?? 0 ),
                'country' => $row_country,
            ];
        }

        if ( empty( $matching ) ) {
            $matching[] = [
                'id'       => 'standard',
                'provider' => '',
                'service'  => __( 'Standard Shipping', 'ulticommerce' ),
                'cost'     => 29,
                'country'  => $country,
            ];
        }

        return apply_filters( 'ultico_shipping_rates', $matching, $country, $state, $zip, $cart );
    }

    private static function match_postcode( $zip, $pattern ) {
        if ( empty( $pattern ) || $pattern === '*' ) return true;
        $ranges = array_map( 'trim', explode( ',', $pattern ) );
        foreach ( $ranges as $range ) {
            if ( strpos( $range, '-' ) !== false ) {
                list( $start, $end ) = array_map( 'trim', explode( '-', $range, 2 ) );
                $start = str_replace( [ 'x', 'X', '*' ], [ '0', '0', '0' ], $start );
                $end   = str_replace( [ 'x', 'X', '*' ], [ '9', '9', '9' ], $end );
                if ( $zip >= $start && $zip <= $end ) return true;
            } elseif ( strpos( $range, '?' ) !== false || strpos( $range, '*' ) !== false || strpos( $range, 'x' ) !== false || strpos( $range, 'X' ) !== false ) {
                $regex = '/^' . str_replace(
                    [ '\*', '\?', 'x', 'X' ],
                    [ '.*', '.', '.', '.' ],
                    preg_quote( $range, '/' )
                ) . '$/';
                if ( preg_match( $regex, $zip ) ) return true;
            } else {
                if ( $zip === $range ) return true;
            }
        }
        return false;
    }

    public static function get_country_list_alpha3() {
        return self::$country_list_alpha3;
    }

    public static function get_alpha3_from_alpha2( $alpha2 ) {
        return self::$country_alpha2_to_alpha3[ strtoupper( $alpha2 ) ] ?? strtoupper( $alpha2 );
    }

    public static function get_alpha2_from_alpha3( $alpha3 ) {
        return self::$alpha3_to_alpha2[ strtoupper( $alpha3 ) ] ?? strtolower( substr( $alpha3, 0, 2 ) );
    }

    public static function get_weight_unit_label( $unit = '' ) {
        if ( ! $unit ) $unit = get_option( 'ultico_shipping_weight_unit', 'g' );
        return self::$weight_units[ $unit ]['label'] ?? 'Grams (g)';
    }

    /* =============== AJAX =============== */

    public function ajax_estimate_shipping() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $state   = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $zip     = sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) );

        if ( empty( $country ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select a country.', 'ulticommerce' ) ] );
        }

        $country = self::get_alpha3_from_alpha2( $country );
        $rates = self::get_rates_for_destination( $country, $state, $zip );
        wp_send_json_success( [ 'rates' => $rates ] );
    }

    public function ajax_get_shipping_rates() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $state   = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $zip     = sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) );

        $country = self::get_alpha3_from_alpha2( $country );
        $rates = self::get_rates_for_destination( $country, $state, $zip );
        wp_send_json_success( [ 'rates' => $rates ] );
    }
}

new Ultico_Shipping();

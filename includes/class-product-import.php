<?php

defined( 'ABSPATH' ) || exit;

class UltiCommerce_Product_Import {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );
        add_action( 'admin_post_ulti_import_csv', [ $this, 'handle_import' ] );
        add_action( 'admin_post_ulti_download_sample_csv', [ $this, 'download_sample' ] );
    }

    public function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Import CSV', 'ulticommerce-core' ),
            __( 'Import CSV', 'ulticommerce-core' ),
            'manage_options',
            'product-import-csv',
            [ $this, 'render_page' ]
        );
    }

    public function admin_styles( $hook ) {
        if ( $hook !== 'product_page_product-import-csv' ) return;
        wp_enqueue_style( 'ulticommerce-admin' );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $results = get_transient( 'ulti_import_results' );
        delete_transient( 'ulti_import_results' );
        ?>
        <div class="wrap uti-import-wrap">
            <h1><?php esc_html_e( 'Import Products from CSV', 'ulticommerce-core' ); ?></h1>

            <?php if ( $results ) : ?>
                <div class="uti-import-card">
                    <h2><?php esc_html_e( 'Import Results', 'ulticommerce-core' ); ?></h2>
                    <div class="uti-import-stats">
                        <div class="uti-import-stat">
                            <span class="num success"><?php echo intval( $results['success'] ); ?></span>
                            <span class="label"><?php esc_html_e( 'Imported', 'ulticommerce-core' ); ?></span>
                        </div>
                        <div class="uti-import-stat">
                            <span class="num error"><?php echo intval( $results['error'] ); ?></span>
                            <span class="label"><?php esc_html_e( 'Failed', 'ulticommerce-core' ); ?></span>
                        </div>
                        <div class="uti-import-stat">
                            <span class="num skipped"><?php echo intval( $results['skipped'] ); ?></span>
                            <span class="label"><?php esc_html_e( 'Skipped', 'ulticommerce-core' ); ?></span>
                        </div>
                    </div>
                    <?php if ( ! empty( $results['log'] ) ) : ?>
                        <div class="uti-log-list">
                            <?php foreach ( $results['log'] as $entry ) : ?>
                                <div class="log-<?php echo esc_attr( $entry['type'] ); ?>">
                                    <?php echo esc_html( '[' . strtoupper( $entry['type'] ) . '] ' . $entry['message'] ); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="uti-import-card">
                <h2><?php esc_html_e( 'Upload CSV', 'ulticommerce-core' ); ?></h2>
                <p><?php esc_html_e( 'Upload a CSV file with product data. Maximum file size: 2MB.', 'ulticommerce-core' ); ?></p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ulti_import_csv">
                    <?php wp_nonce_field( 'ulti_import_csv', 'ulti_import_nonce' ); ?>
                    <input type="file" name="csv_file" accept=".csv" required style="margin-bottom:12px;display:block;">
                    <p style="margin-bottom:12px;">
                        <label>
                            <input type="checkbox" name="update_existing" value="1">
                            <?php esc_html_e( 'Update existing products by SKU', 'ulticommerce-core' ); ?>
                        </label>
                    </p>
                    <?php submit_button( __( 'Import CSV', 'ulticommerce-core' ), 'primary', 'submit', false ); ?>
                </form>
            </div>

            <div class="uti-import-card">
                <h2><?php esc_html_e( 'CSV Format', 'ulticommerce-core' ); ?></h2>
                <p><?php esc_html_e( 'The CSV file must include a header row. Download the sample file below for the correct format.', 'ulticommerce-core' ); ?></p>
                <table class="widefat fixed" style="font-size:12px;">
                    <thead><tr>
                        <th><?php esc_html_e( 'Column', 'ulticommerce-core' ); ?></th>
                        <th><?php esc_html_e( 'Required', 'ulticommerce-core' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'ulticommerce-core' ); ?></th>
                    </tr></thead>
                    <tbody>
                        <tr><td>title</td><td><?php esc_html_e( 'Yes', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Product name', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>content</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Product description', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>excerpt</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Short description', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>sku</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Unique SKU', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>unit_price</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Regular price', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>discount_price</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Sale/discount price', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>quantity</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Stock quantity', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>weight / width / height</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Dimensions', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>enabled</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( '1 or 0 (default 1)', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>slug</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Post slug (auto if empty)', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>featured_image</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Image URL for thumbnail', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>gallery</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Pipe | separated image URLs', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>categories</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Pipe | separated category names (existing or new)', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>brands</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Pipe | separated brand names', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>collections</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Pipe | separated collection names', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>tags</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'Pipe | separated tag names', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>attributes</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'JSON object {attr_slug:"val1,val2"}', 'ulticommerce-core' ); ?></td></tr>
                        <tr><td>variations</td><td><?php esc_html_e( 'No', 'ulticommerce-core' ); ?></td><td><?php esc_html_e( 'JSON array [{sku,price,quantity,attributes:{}}]', 'ulticommerce-core' ); ?></td></tr>
                    </tbody>
                </table>
                <p style="margin-top:12px;">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ulti_download_sample_csv' ), 'ulti_download_sample' ) ); ?>" class="button">
                        <?php esc_html_e( 'Download Sample CSV', 'ulticommerce-core' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'ulti_import_csv', 'ulti_import_nonce' );

        if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( 'Upload failed. Please try again.' );
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen( $tmp, 'r' );
        if ( ! $handle ) wp_die( 'Cannot read file.' );

        $header = fgetcsv( $handle );
        if ( ! $header ) wp_die( 'Empty CSV file.' );

        $header = array_map( 'strtolower', array_map( 'trim', $header ) );
        $update_existing = ! empty( $_POST['update_existing'] ) ? intval( $_POST['update_existing'] ) : 0;

        $results = [ 'success' => 0, 'error' => 0, 'skipped' => 0, 'log' => [] ];

        $row_num = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_num++;
            $row = array_map( 'trim', $row );
            $row = array_pad( $row, count( $header ), '' );
            $row = array_slice( $row, 0, count( $header ) );
            $data = array_combine( $header, $row );

            if ( empty( $data['title'] ) ) {
                $results['skipped']++;
                $results['log'][] = [ 'type' => 'skip', 'message' => "Row $row_num: empty title" ];
                continue;
            }

            try {
                $result = $this->import_product( $data, $update_existing );
                if ( $result === 'updated' ) {
                    $results['success']++;
                    $results['log'][] = [ 'type' => 'success', 'message' => "Row $row_num: updated \"{$data['title']}\" (ID {$result['id']})" ];
                } elseif ( $result === 'created' ) {
                    $results['success']++;
                    $results['log'][] = [ 'type' => 'success', 'message' => "Row $row_num: created \"{$data['title']}\" (ID {$result['id']})" ];
                } elseif ( $result === 'skipped' ) {
                    $results['skipped']++;
                    $results['log'][] = [ 'type' => 'skip', 'message' => "Row $row_num: {$result['reason']}" ];
                } else {
                    $results['error']++;
                    $results['log'][] = [ 'type' => 'error', 'message' => "Row $row_num: {$result['error']}" ];
                }
            } catch ( Exception $e ) {
                $results['error']++;
                $results['log'][] = [ 'type' => 'error', 'message' => "Row $row_num: " . $e->getMessage() ];
            }
        }

        fclose( $handle );
        set_transient( 'ulti_import_results', $results, 60 );
        wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=product-import-csv' ) );
        exit;
    }

    private function import_product( $data, $update_existing ) {
        $existing_id = 0;
        if ( $update_existing && ! empty( $data['sku'] ) ) {
            $existing = get_posts( [
                'post_type'      => 'product',
                'meta_key'       => '_product_sku',
                'meta_value'     => $data['sku'],
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ] );
            $existing_id = ! empty( $existing ) ? $existing[0] : 0;
        }

        if ( $existing_id ) {
            $post_id = $existing_id;
            $action  = 'updated';
        } else {
            $post_id = 0;
            $action  = 'created';
        }

        $post_data = [
            'post_type'    => 'product',
            'post_status'  => 'publish',
            'post_title'   => $data['title'],
        ];

        if ( ! empty( $data['content'] ) )  $post_data['post_content']  = $data['content'];
        if ( ! empty( $data['excerpt'] ) )  $post_data['post_excerpt']  = $data['excerpt'];
        if ( ! empty( $data['slug'] ) )     $post_data['post_name']     = $data['slug'];

        if ( $post_id ) {
            $post_data['ID'] = $post_id;
            $post_id = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            return [ 'error' => $post_id->get_error_message() ];
        }
        if ( ! $post_id ) {
            return [ 'error' => 'failed to save post' ];
        }

        $meta_fields = [
            'sku'             => '_product_sku',
            'unit_price'      => '_product_unit_price',
            'discount_price'  => '_product_discount_price',
            'quantity'        => '_product_quantity',
            'weight'          => '_product_weight',
            'width'           => '_product_width',
            'height'          => '_product_height',
        ];

        foreach ( $meta_fields as $csv_key => $meta_key ) {
            if ( isset( $data[ $csv_key ] ) && $data[ $csv_key ] !== '' ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $data[ $csv_key ] ) );
            }
        }

        update_post_meta( $post_id, '_product_enabled', isset( $data['enabled'] ) && $data['enabled'] === '0' ? '0' : '1' );

        if ( ! empty( $data['featured_image'] ) ) {
            $attach_id = $this->import_image( $data['featured_image'], $post_id );
            if ( $attach_id ) set_post_thumbnail( $post_id, $attach_id );
        }

        if ( ! empty( $data['gallery'] ) ) {
            $urls = array_map( 'trim', explode( '|', $data['gallery'] ) );
            $ids  = [];
            foreach ( $urls as $url ) {
                if ( $url ) {
                    $aid = $this->import_image( $url, $post_id );
                    if ( $aid ) $ids[] = $aid;
                }
            }
            if ( ! empty( $ids ) ) update_post_meta( $post_id, '_product_gallery', $ids );
        }

        $tax_map = [
            'categories'  => 'product_category',
            'brands'      => 'product_brand',
            'collections' => 'product_collection',
            'tags'        => 'product_tag',
        ];

        foreach ( $tax_map as $csv_key => $taxonomy ) {
            if ( ! empty( $data[ $csv_key ] ) ) {
                $terms = array_map( 'trim', explode( '|', $data[ $csv_key ] ) );
                $term_ids = [];
                foreach ( $terms as $term_name ) {
                    if ( ! $term_name ) continue;
                    $term = term_exists( $term_name, $taxonomy );
                    if ( ! $term ) {
                        $term = wp_insert_term( $term_name, $taxonomy );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = (int) $term['term_id'];
                    }
                }
                if ( ! empty( $term_ids ) ) {
                    wp_set_object_terms( $post_id, $term_ids, $taxonomy );
                }
            }
        }

        if ( ! empty( $data['attributes'] ) ) {
            $attrs = json_decode( $data['attributes'], true );
            if ( is_array( $attrs ) ) {
                $saved = [];
                foreach ( $attrs as $attr_slug => $values_str ) {
                    $values = array_map( 'trim', explode( ',', $values_str ) );
                    $saved[ sanitize_text_field( $attr_slug ) ] = [ 'values' => $values ];
                }
                update_post_meta( $post_id, '_product_attributes', $saved );
            }
        }

        if ( ! empty( $data['variations'] ) ) {
            $variations = json_decode( $data['variations'], true );
            if ( is_array( $variations ) ) {
                $this->import_variations( $post_id, $variations );
            }
        }

        return [ 'id' => $post_id, 'action' => $action ];
    }

    private function import_image( $url, $post_id ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_id = media_sideload_image( $url, $post_id, null, 'id' );
        if ( is_wp_error( $attach_id ) ) return 0;
        return (int) $attach_id;
    }

    private function import_variations( $product_id, $variations ) {
        $attr_hash = md5( serialize( $variations ) );
        $old = get_posts( [
            'post_type'      => 'product_variation',
            'post_parent'    => $product_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ] );

        $saved = [];
        foreach ( $variations as $var ) {
            $attr_string = '';
            if ( isset( $var['attributes'] ) && is_array( $var['attributes'] ) ) {
                ksort( $var['attributes'] );
                $attr_string = http_build_query( $var['attributes'] );
            }

            $existing = get_posts( [
                'post_type'      => 'product_variation',
                'post_parent'    => $product_id,
                'meta_key'       => '_variation_attr_hash',
                'meta_value'     => md5( $attr_string ),
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ] );

            $var_id = ! empty( $existing ) ? $existing[0] : 0;

            $post_data = [
                'post_type'   => 'product_variation',
                'post_parent' => $product_id,
                'post_status' => 'publish',
                'post_title'  => 'Variation',
            ];

            if ( $var_id ) $post_data['ID'] = $var_id;

            $saved_id = wp_insert_post( $post_data );
            if ( $saved_id ) {
                update_post_meta( $saved_id, '_variation_sku', sanitize_text_field( $var['sku'] ?? '' ) );
                update_post_meta( $saved_id, '_variation_price', sanitize_text_field( $var['price'] ?? '' ) );
                update_post_meta( $saved_id, '_variation_quantity', intval( $var['quantity'] ?? 0 ) );
                update_post_meta( $saved_id, '_variation_attr_hash', md5( $attr_string ) );

                $safe = [];
                foreach ( ( $var['attributes'] ?? [] ) as $k => $v ) {
                    $safe[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
                }
                update_post_meta( $saved_id, '_variation_attributes', $safe );
                $saved[] = $saved_id;
            }
        }

        foreach ( $old as $oid ) {
            if ( ! in_array( $oid, $saved ) ) wp_delete_post( $oid, true );
        }
    }

    public function download_sample() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'ulti_download_sample' );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ulti-product-import-sample.csv"' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );

        $columns = [
            'title', 'content', 'excerpt', 'slug', 'sku',
            'unit_price', 'discount_price', 'quantity',
            'weight', 'width', 'height', 'enabled',
            'featured_image', 'gallery',
            'categories', 'brands', 'collections', 'tags',
            'attributes', 'variations',
        ];

        fputcsv( $out, $columns );

        fputcsv( $out, [
            'Simple Product',
            'Product description here.',
            'Short excerpt.',
            '',
            'SP-001',
            '29.99',
            '19.99',
            '100',
            '0.5',
            '10',
            '15',
            '1',
            'https://picsum.photos/seed/1/600/600',
            'https://picsum.photos/seed/2/600/600|https://picsum.photos/seed/3/600/600',
            'Electronics|Gadgets',
            'BrandA',
            'Summer 2026',
            'new|sale',
            '{"color":"Red,Blue,Green","size":"S,M,L"}',
            '[{"sku":"SP-001-R-S","price":"29.99","quantity":"10","attributes":{"color":"Red","size":"S"}},{"sku":"SP-001-R-M","price":"29.99","quantity":"10","attributes":{"color":"Red","size":"M"}}]',
        ] );

        fputcsv( $out, [ 'Another Product', '', '', '', 'SP-002', '49.99', '', '50', '', '', '', '1', '', '', 'Home|Kitchen', 'BrandB', '', 'featured', '', '' ] );

        fclose( $out );
        exit;
    }
}

new UltiCommerce_Product_Import();

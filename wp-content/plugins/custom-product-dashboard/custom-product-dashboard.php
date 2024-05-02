<?php
/*
Plugin Name: Custom Product Dashboard
Description: Adds a custom product dashboard for managing products.
Version: 1.0
Author:loay
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Create a menu item in the dashboard
function cpd_create_menu() {
    add_menu_page(
        'Custom Product Dashboard',
        'Custom Product Dashboard',
        'activate_plugins',
        'custom-product-dashboard',
        'cpd_display_page'
    );
}

add_action('admin_menu', 'cpd_create_menu');

// Custom Dashboard UI
function cpd_display_page() {
    if (isset($_GET['success'])) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Product saved successfully!</p>
        </div>
        <?php
    }
    ?>
    <div class="wrap">
        <h1>Custom Product Dashboard</h1>
        <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('save_product', '_wpnonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="product_name">Product Name</label></th>
                    <td><input name="product_name" type="text" id="product_name" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_image">Product Image</label></th>
                    <td><input name="product_image" type="file" id="product_image" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_description">Product Description</label></th>
                    <td><textarea name="product_description" id="product_description" class="regular-text" rows="5"></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_price">Product Price</label></th>
                    <td><input name="product_price" type="text" id="product_price" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Save Product'); ?>
        </form>
        <!-- Products table to edit and delete -->
        <h2>Products</h2>
        <table class="wp-list-table widefat fixed striped" id="cpd-products-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Image</th>
                <th>Description</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td colspan="6" id="cpd-loading-message">Loading products...</td>
            </tr>
            </tbody>
        </table>
        <br>
        <button class="button button-secondary" id="cpd-purge-data">Purge All Data</button>
    </div>
    <?php
}

// Handle Form submission
function cpd_save_product() {
    if (!empty($_POST) && check_admin_referer('save_product', '_wpnonce')) {
        $product_data = array(
            'id' => time(),
            'name' => sanitize_text_field($_POST['product_name']),
            'description' => sanitize_textarea_field($_POST['product_description']),
            'price' => floatval($_POST['product_price']),
        );

        // Handle file upload
        if (!empty($_FILES['product_image']['tmp_name'])) {
            $file = wp_upload_bits($_FILES['product_image']['name'], null, file_get_contents($_FILES['product_image']['tmp_name']));
            if (!$file['error']) {
                $product_data['image'] = $file['url'];
            } else {
                wp_die('Error uploading image: ' . $file['error']);
            }
        }

        $products = get_option('cpd_products', array());
        $products[] = $product_data;
        update_option('cpd_products', $products);

        wp_redirect(admin_url('admin.php?page=custom-product-dashboard&success=1'));
        exit;
    }
}
add_action('admin_init', 'cpd_save_product');

// Register the REST route for a single product.
function cpd_register_product_endpoint() {
    register_rest_route('cpd/v1', '/products/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'cpd_get_product',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
}

add_action('rest_api_init', 'cpd_register_product_endpoint');

// The callback function to return a single product.
function cpd_get_product($data) {
    $products = get_option('cpd_products', array());
    $product_id = $data['id'];

    foreach ($products as $product) {
        if ($product['id'] == $product_id) {
            return new WP_REST_Response($product, 200);
        }
    }
    return new WP_REST_Response(array('message' => 'Product not found'), 404);
}

// Create custom REST API endpoint
function cpd_register_api_endpoints() {
    register_rest_route('cpd/v1', '/products', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'cpd_get_products',
    ));
    register_rest_route('cpd/v1', '/products', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'cpd_handle_post_product',
        'permission_callback' => function() {
            return current_user_can('activate_plugins');
        }
    ));
}

add_action('rest_api_init', 'cpd_register_api_endpoints');

function cpd_get_products() {
    $products = get_option('cpd_products', array());
    if (empty($products)) {
        return new WP_REST_Response(array(), 200);
    }
    return new WP_REST_Response($products, 200);
}

// Handle POST requests
function cpd_handle_post_product($request) {
    $params = $request->get_params();
    $products = get_option('cpd_products', array());

    // Simple validation and sanitation
    $new_product = array(
        'id' => isset($params['id']) ? $params['id'] : time(),  // Generate a new ID or use existing
        'name' => sanitize_text_field($params['name']),
        'description' => sanitize_textarea_field($params['description']),
        'price' => sanitize_text_field($params['price']),
        'image' => sanitize_text_field($params['image'])  // Assuming image URL is provided
    );

    // Add or update product in the existing array
    $update_index = array_search($new_product['id'], array_column($products, 'id'));
    if (false !== $update_index) {
        $products[$update_index] = $new_product;
    } else {
        $products[] = $new_product;  // Append new product
    }

    update_option('cpd_products', $products);
    return new WP_REST_Response($new_product, 200);
}

function cpd_enqueue_scripts($hook) {
    if ('toplevel_page_custom-product-dashboard' !== $hook) {
        return;
    }
    wp_enqueue_script('cpd-admin', plugins_url('cpd-admin.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_localize_script('cpd-admin', 'cpd_ajax', array('url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('cpd_nonce'), 'capability' => current_user_can('activate_plugins') ? 'yes' : 'no'));
}

add_action('admin_enqueue_scripts', 'cpd_enqueue_scripts');

function cpd_delete_product() {
    // Kontrollerer om nonce er gyldig og brugeren har rettigheder til at slette produkter
    if (!wp_verify_nonce($_POST['nonce'], 'cpd_nonce') || !current_user_can('activate_plugins')) {
        wp_send_json_error('Forbidden');
    }

    // Henter produktets id fra $_POST-data og sikrer, at det er en integer
    $id = intval($_POST['id']);

    // Henter produkter fra indstillinger, hvis de findes
    $products = get_option('cpd_products', array());

    // Finder indekset for produktet med det angivne id
    $product_index = array_search($id, array_column($products, 'id'));

    // Hvis produktet ikke findes, send en fejlmeddelelse tilbage
    if ($product_index === false) {
        wp_send_json_error('Product not found');
    }

    // Fjerner produktet fra arrayet
    array_splice($products, $product_index, 1);

    // Opdaterer produktindstillingerne med det nye array
    update_option('cpd_products', $products);

    // Sender en succesbesked tilbage
    wp_send_json_success();
}
add_action('wp_ajax_cpd_delete_product', 'cpd_delete_product');


function cpd_purge_data() {
    check_ajax_referer( 'cpd_nonce', 'nonce' );
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_send_json_error( 'Forbidden' );
    }

    update_option( 'cpd_products', array() );

    wp_send_json_success();
}

add_action( 'wp_ajax_cpd_purge_data', 'cpd_purge_data' );

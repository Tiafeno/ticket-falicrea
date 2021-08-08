<?php
/**
 * Created by IntelliJ IDEA.
 * User: you-f
 * Date: 03/07/2021
 * Time: 17:08
 */

final class FormerDetailsClass {
    public function __construct () {
        add_action('add_meta_boxes', [&$this, 'add_former_meta_boxes']);
        add_action('save_post', [&$this, 'save_former_details']);
        add_action('admin_init', function() {
            add_action('wp_ajax_action_former_details', [&$this, 'get_former_trainings']);
            add_action('wp_ajax_action_get_product_details', [&$this, 'get_product_details']);
        });
    }

    public function add_former_meta_boxes() {
        // Only for former post type...
        if (post_type_exists('former')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_meta_box(
                'former-details',
                __( 'Former Details', 'ticket-falicrea' ),
                [&$this, 'former_meta_box_callback'],
                'former'
            );
        }
    }

    public function former_meta_box_callback($post) {
        global $engine;
        wp_enqueue_script('former-details');
        // Add a nonce field so we can check for it later.
        $nonce = wp_create_nonce( 'former_details_nonce' );
        echo $engine->parseFile('former-details')->render(['nonce' => $nonce]);
    }

    public function save_former_details($post_id) {

        // Check if our nonce is set.
        if ( ! isset( $_POST['former_nonce'] ) ) {
            return;
        }

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $_POST['former_nonce'], 'former_details_nonce' ) ) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check the user's permissions.
        if ( isset( $_POST['post_type'] ) && 'former' == $_POST['post_type'] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return;
            }

        }
        else {

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

    }

    public function enqueue_scripts() {
        global $post;
        wp_register_script('axios', plugin_dir_url(__FILE__) . '../assets/js/axios.min.js', [], null, true);
        wp_register_script('xlsx', 'https://unpkg.com/xlsx@0.15.1/dist/xlsx.full.min.js', [], null, true);
        wp_register_script('vue', plugin_dir_url(__FILE__) . '../assets/js/vue.js', ['jquery'], null, true);
        wp_register_script('vue-router', plugin_dir_url(__FILE__) . '../assets/js/vue-router.js', ['vue'], null, true);
        wp_register_script('former-details', plugin_dir_url(__FILE__) . '../assets/js/former-details.js',
            ['vue', 'vue-router', 'axios', 'lodash', 'wp-api', 'xlsx'], null, true);
        wp_localize_script('former-details', 'apiSettings', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'former_id' => $post->ID,
            'currency' => get_woocommerce_currency_symbol(),
            'product_post_new' => admin_url('post-new.php?post_type=product'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style('semantic-table', plugin_dir_url(__FILE__) . '../assets/css/table.css');
        wp_enqueue_style('semantic-btn', plugin_dir_url(__FILE__) . '../assets/css/button.css');
        wp_enqueue_style('semantic-header', plugin_dir_url(__FILE__) . '../assets/css/header.min.css');
        wp_enqueue_style('semantic-icon', plugin_dir_url(__FILE__) . '../assets/css/icon.css');
    }

    /**
     * Cette function permet d'afficher tous les formations d'un formateur spécifié
     * dans une requete HTTP
     */
    public function get_former_trainings() {
        global $wpdb;
        if (!isset($_POST['former_id'])) {
            wp_send_json_error('param `former_id` not defined');
        }
        $former_id = intval($_POST['former_id']);
        $responses = [];
        // Récuperer tous les produits ou formation d'un formateur
        $sql = "SELECT post.ID, post.post_title, meta.meta_value as former_id FROM {$wpdb->posts} as post 
JOIN {$wpdb->postmeta} as meta ON (meta.post_id = post.ID)
WHERE post.post_type = %s AND meta.meta_value LIKE %s AND meta.meta_key = %s";
        $prepare = $wpdb->prepare($sql, 'product', $former_id, 'former' );
        $products = $wpdb->get_results($prepare);
        // Envoyer une tableau vide, s'il y a aucune formation
        if (empty($products)) wp_send_json_success([]);
        // Request params
        $request = new WP_REST_Request();
        $request->set_param('context', 'edit');
        foreach ($products as $product) {
            $product_controller = new WC_REST_Products_V1_Controller();
            $product_id = intval($product->ID);
            $product = wc_get_product($product_id);
            $response = $product_controller->prepare_item_for_response( $product , $request);
            $responses[] = $response->data;
        }
        $wpdb->flush();
        wp_send_json_success($responses);
    }

    /**
     * Recuperer tous les commandes d'un produit
     */
    public function get_product_details() {
        global $wpdb;
        $RESPONSES = [];
        if (!isset($_POST['product_id'])) {
            wp_send_json_error('param `product_id` not defined');
        }
        $product_id = intval($_POST['product_id']);
        // Define HERE the orders status to include in  <==  <==  <==  <==  <==  <==  <==
        $orders_statuses = "'wc-completed', 'wc-processing', 'wc-on-hold'";

        // Create filters by date
        $filter = '';
        if (isset($_POST['filter'])) {
            $date_string = $_POST['filter'];
            $date_arr = explode('|', $date_string);
            if (is_array($date_arr)) {
                $month_resp = $date_arr[0];
                $year_resp = $date_arr[1];
                $month = ('0' === $month_resp || empty($month_resp)) ? 0 : intval($month_resp);
                $year = ('0' === $year_resp || empty($year_resp)) ? 0 : intval($year_resp);
                if (0 !== $month || 0 !== $year) {
                    $month_begin = ($year && 0 === $month) ? '01' : $month;
                    $month_end = ($year && 0 === $month) ? '12' : $month;
                    $begin_date   = gmdate( 'Y-m-d H:i:s', strtotime( "$year-$month_begin-01" ) );
                    $end_date = gmdate( 'Y-m-d H:i:s', strtotime( "$year-$month_end-31" ) );
                    $filter = sprintf('AND p.post_date BETWEEN \'%s\' AND \'%s\' ', $begin_date, $end_date);
                }
            }
        }

        # Get All defined statuses Orders IDs for a defined product ID (or variation ID)
        $results = $wpdb->get_col( "
        SELECT DISTINCT woi.order_id
        FROM {$wpdb->prefix}woocommerce_order_itemmeta as woim, 
             {$wpdb->prefix}woocommerce_order_items as woi, 
             {$wpdb->prefix}posts as p
        WHERE  woi.order_item_id = woim.order_item_id
        AND woi.order_id = p.ID
        AND p.post_status IN ( $orders_statuses )
          $filter
        AND woim.meta_key IN ( '_product_id', '_variation_id' )
        AND woim.meta_value LIKE '$product_id'
        ORDER BY woi.order_item_id DESC");

        if (empty($results)) wp_send_json_success([]);

        // Request params
        $request = new WP_REST_Request();
        $request->set_param('context', 'edit');

        // Get all order for his product
        foreach ($results as $index => $order_id) {
            $totalTTC = 0;
            $customer_billing = new stdClass();
            $order_id = intval($order_id);
            $order = wc_get_order($order_id);
            $items = $order->get_items();
            foreach ( $items as $item ) {
                $data = $item->get_data();
                if (intval($product_id) !== intval($data['product_id'])) continue;
                $totalTTC += ($data['total'] + $data['total_tax']);
            }
//            $customer_id = $order->get_customer_id();
//            if (0 !== $customer_id || $customer_id) {
//                $customer_controller = new WC_REST_Customers_V1_Controller();
//                $customer_response = $customer_controller->prepare_item_for_response(new WC_Customer($customer_id), $request);
//                $customer_data = $customer_response->data;
//            } else {
//                $customer_data = $order->bill
//            }
            $props = ['phone', 'city', 'country', 'email', 'postcode'];
            $customer_billing->full_name = $order->get_formatted_billing_full_name();
            $customer_billing->full_address = $order->get_formatted_billing_address();
            foreach ($props as $prop) {
                $key_prop = 'get_billing_' . $prop;
                $customer_billing->{$prop} = $order->{$key_prop}();
            }
            $date_complete = $order->get_date_completed();
            $date_created = $order->get_date_created();
            $RESPONSES[] = [
                'TTC' => $totalTTC,
                'code' => $this->get_order_ticket($order_id),
                'oDateCreated' => $date_created->format('M d Y'),
                'oDate' => $date_complete->format('M d Y'),
                'oUrl' => $order->get_edit_order_url(),
                'customer' => $customer_billing
            ];
        }

        wp_send_json_success($RESPONSES);
    }

    private function get_order_ticket($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ticket_security';
        $request = $wpdb->prepare("SELECT code FROM $table WHERE order_id = %d", intval($order_id));
        $response = $wpdb->get_col($request);
        $wpdb->flush();
        if (empty($response) || !$response) return null;
        return reset($response);
    }

}

new FormerDetailsClass();
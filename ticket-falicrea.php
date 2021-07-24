<?php

/*
Plugin Name: Ticket Formation
Plugin URI: https://falicrea.net
Description: ...
Version: 1.2
Author: Falicrea
Author URI: https://falicrea.net
License: A "Slug" license name e.g. GPL2
*/

include plugin_dir_path(__FILE__) . 'vendor/autoload.php';
include plugin_dir_path(__FILE__) . 'inc/former-details.php';

use Liquid\Template;
use Liquid\Cache\Local;
use Liquid\Liquid;

Liquid::set('INCLUDE_PREFIX', '');
$engine = new Template (__DIR__ . '/templates');
$engine->setCache(new Local());

add_action('init', function() {
    //$order_id = 42;
    //TicketSecurity::getInstance()->thank_you($order_id);
});


class TicketSecurity {
    private $permitted_chars = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
    public function __construct() {
        add_action('woocommerce_thankyou', [&$this, 'thank_you']);
        add_action('woocommerce_order_status_completed',array(&$this, 'thank_you'));
        // Adding Meta container admin shop_order pages
        add_action( 'add_meta_boxes', function() {
            add_meta_box( 'rsender_form', 'Action de code', [&$this, 'rsend_code_form'], 'shop_order', 'side', 'core' );
        });
        add_action( 'save_post', [&$this, 'rsend_code_mail'], 10, 1 );
        add_action('init', [&$this, 'init']);
    }

    public static function getInstance() {
        return new self;
    }

    private function generate_string($strength = 8) {
        $input_length = strlen($this->permitted_chars);
        $random_string = '';
        for($i = 0; $i < $strength; $i++) {
            $random_character = $this->permitted_chars[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }
        return $random_string;
    }

    function init() {
        $content_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $content_type );
    }

    function rsend_code_form() {
        $body = '<input type="hidden" name="rsend_nonce" value="' . wp_create_nonce() . '">';
        $body .= '<select name="code_action" style="border: 4px solid #007cba;">
                    <option value="">Choisissez une action…</option>
                    <option value="send_order_code">E-mail de code de securité</option>
                </select>';
        echo $body;
    }

    function rsend_code_mail(int $order_id) {
        // Check if our nonce is set.
        if ( ! isset( $_POST[ 'rsend_nonce' ] ) ) {
            return $order_id;
        }
        $nonce = $_REQUEST[ 'rsend_nonce' ];
        //Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce ) ) {
            return $order_id;
        }
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $order_id;
        }
        $action = $_REQUEST['code_action'];
        if ($action !== 'send_order_code') return $order_id;

        global $wpdb;
        $table = $wpdb->prefix . 'ticket_security';
        // Verify key exist
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE order_id = %d", intval($order_id));
        $result = $wpdb->get_row($sql);
        if ($result) {
            $qrcode_url = $this->generateQRCodeURL($result->qrcode_file);
            $this->send_code($order_id, $result->code, $qrcode_url, true);
        }
    }

    // Initialize DB Tables
    function init_db() {
        global $table_prefix;
        // Customer Table
        $ticketTable = $table_prefix . 'ticket_security';
        // Query - Create Table
        $sql = "CREATE TABLE `$ticketTable` (";
        $sql .= " `id` bigint(11) NOT NULL auto_increment, ";
        $sql .= " `order_id` bigint(11) NOT NULL, ";
        $sql .= " `code` varchar(500) NOT NULL, ";
        $sql .= " `qrcode_file` varchar(250) NOT NULL, ";
        $sql .= " PRIMARY KEY `ticket_id` (`id`) ";
        $sql .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
        // Include Upgrade Script
        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
        // Create Table
        dbDelta( $sql );

    }

    /**
     * @param $filename
     * @return string
     */
    public function generateQRCodeURL($filename) {
        return plugin_dir_url(__FILE__).'qrcode/'.$filename;
    }

    /**
     * Action native de wordpress
     * @param $order_id Integer
     */
    public function thank_you(int $order_id) {
        $order = wc_get_order($order_id); // return WP_Order
        $paymethod = $order->payment_method_title;
        $orderstat = $order->get_status();
        if ($orderstat == 'completed') {
            // Create ticket security
            $code = $this->generate_string();
            include(plugin_dir_path(__FILE__) . 'inc/phpqrcode/qrlib.php');
            $tempDir = plugin_dir_path(__FILE__) . 'qrcode';
            chmod($tempDir, 0755);
            $fileName = 'file_'.$code.'.png';
            $message = "Code: {$code}";
            $pngAbsoluteFilePath = $tempDir . '/' . $fileName;
            $urlRelativeFilePath = $this->generateQRCodeURL($fileName);
            // generating
            if (!file_exists($pngAbsoluteFilePath)) {
                QRcode::png($message, $pngAbsoluteFilePath, QR_ECLEVEL_L, 4);
            }
            $his_code = $this->add_ticket_db($order_id, $code, $fileName);
            if ($his_code) {
                $this->send_code($order_id, $code, $urlRelativeFilePath);
            } else {
                $note = "Une erreur c'est produit dans la base de donnée. Veuillez contacter l'administrateur";
                $order->add_order_note( $note );
            }
        }
    }

    /**
     * Cette fonction permet de recuperer l'identifiant d'un formateur par son produit (formation)
     * @param $product_id Integer
     * @return int
     */
    public function get_training_former_id(int $product_id) {
        $former_id = get_post_meta($product_id,'former', true);
        $former_id = intval($former_id);
        return is_int($former_id) && !is_nan($former_id) ? $former_id : 0;
    }

    /**
     * Enregistrer le code dans la base de données
     * @param $order_id Integer
     * @param $code String
     * @param $filename String
     * @return false
     */
    public function add_ticket_db(int $order_id, $code, $filename) {
        global $wpdb;
        $table = $wpdb->prefix . 'ticket_security';
        // Verify key exist
        $key_check_sql = $wpdb->prepare("SELECT * FROM $table WHERE order_id = %d", intval($order_id));
        $key_check_row = $wpdb->get_results($key_check_sql);
        if (!$key_check_row) {
            // Add the note
            $order = wc_get_order($order_id);
            $note = "Code de sécurité: {$code}";
            $order->add_order_note( $note );
            // Insert in database
            $wpdb->insert($table, array(
                'order_id' => $order_id,
                'code' => $code,
                'qrcode_file' => $filename, // ... and so on
            ));
            $result = &$code;
        } else {
            $row = reset($key_check_row);
            $result = $row ? $row->code : false;
        }
        $wpdb->flush();
        return $result;
    }

    /**
     * Recuperer la commande et envoyer le code pour les produits respective
     * @param $order_id Integer
     * @param $code String
     * @param $qrcode_url String
     */
    public function send_code($order_id, $code, $qrcode_url ,$for_client_only = false) {
        $order = wc_get_order($order_id);
        $order_items = $order->get_items();
        foreach ($order_items as $item) {
            // Envoyer le mail au client et à l'administrateur
            $this->send_mail($item, $order, $code, $qrcode_url, $for_client_only);
        }
    }


    /**
     * Envoyer un email par item ou produit acheté dans le commande
     * @param $item WC_Order_Item_Product
     * @param $order WC_Order
     * @param $code String
     * @param $qrcode_url String
     * @throws \Liquid\Exception\MissingFilesystemException
     */
    private function send_mail($item, $order, $code, $qrcode_url, $for_client_only = false) {
        global $engine;
        $site_title = get_bloginfo( 'name' );
        $order_id = $order->get_id();
        $item_product_id = $item->get_product_id();
        $sujet = "#$order_id - Confirmation de commande";
        $product_name = $item->get_name();
        // Lien du produit externe (hors du site)
        $external_product_link = get_post_meta( $item_product_id, 'external_product_link', true );
        // Description du produit à vendre à l'externe
        $external_product_description = get_post_meta( $item_product_id, 'external_product_description', true );
        // Récuperer le contenue du template 'mail'
        $body = $engine->parseFile('mail')->render([
            'qrcode_url'   => $qrcode_url,
            'product_name' => $product_name,
            'external_product_link' => $external_product_link,
            'external_product_description' => $external_product_description,
            'sitename'     => $site_title,
            'code'         => $code,
            'author_name'  => $order->get_billing_first_name() . ' ' .$order->get_billing_last_name(),
            'currency'     => get_woocommerce_currency_symbol(),
            'total_ttc'    => $order->get_total()
        ]);
        $headers[] = 'From: Ma Formation Store <no-reply@maformation-store.net>';
        $to_client = $order->get_billing_email(); // Get client address email
        wp_mail($to_client, $sujet, $body, $headers);
        // Verifier si le mail est pour le client seulement
        if ($for_client_only) return;
        // get admin contact
        $admin_email  = get_option('admin_email');
        if (is_email($admin_email)) {
            wp_mail($admin_email, $sujet, $body, $headers);
        }
        $former_id = $this->get_training_former_id($item_product_id);
        if ($former_id):
            $this->send_mailtoformer($former_id, $item_product_id);
        endif;
    }

    private function send_mailtoformer($former_id, $product_id) {
        $product = new WC_Product($product_id);
        $former_email = get_post_meta( $former_id,  'email', true );
        // Verify is correct email address
        if (is_email($former_email)) {
            // TODO: Mail for former
            $headers[] = 'From: Ma Formation Store <no-reply@maformation-store.net>';
            $message = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam ut leo nisl. 
            Etiam sit amet purus maximus, finibus arcu ut, venenatis dui. In vehicula magna quis vestibulum rutrum. 
            Nulla eget sollicitudin lacus. Maecenas consequat mi et pretium semper. Nam posuere imperdiet metus quis posuere. 
            Ut at libero sit amet odio facilisis eleifend blandit vel nibh. Praesent eu neque id leo cursus pharetra non ut ex.";
            $objet_email = "Achat de formation: " . $product->get_title();
            wp_mail( $former_email, $objet_email, $message, $headers);
        }
    }
}

new TicketSecurity();

register_activation_hook( __FILE__, function() {
    $ticket = new TicketSecurity();
    $ticket->init_db();
    /* activation code here */
});

add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_style('ticket', plugin_dir_url(__FILE__) . 'assets/css/tickets.css');
} );

add_action( 'admin_menu', 'ticket_admin_menu' );
function ticket_admin_menu() {
    add_options_page( 'Les tickets', 'Tickets', 'manage_options', 'ticket-security', function() {
        global $wpdb, $engine;
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        $table = $wpdb->prefix .'ticket_security';
        $query = "SELECT * FROM $table";
        $results = $wpdb->get_results($query, OBJECT );
        $tickets = [];
        foreach ($results as $result) {
            $order = wc_get_order(intval($result->order_id));
            if (empty($order)) continue;

            $result->date = $order->get_date_created();
            $result->order_url = $order->get_edit_order_url();
            $result->author_name = $order->get_billing_first_name() . ' ' .$order->get_billing_last_name();
            $result->author_email = $order->get_billing_email();
            $tickets[] = $result;
        }
        echo $engine->parseFile('admin_menu_content')->render(['tickets' => $tickets]);
    } );
}


/**
 * Add extra dropdowns to the List Tables
 *
 * @param required string $post_type    The Post Type that is being displayed
 */
add_action('restrict_manage_posts', 'add_extra_tablenav');
function add_extra_tablenav($post_type){
    /** Ensure this is the correct Post Type*/
    if($post_type !== 'product') return;
    $results = get_posts(['post_type' => 'former', 'numberposts' => -1]);
    if(empty($results)) return;
    // get selected option if there is one selected
    if (isset( $_GET['former-name'] ) && $_GET['former-name'] != '') {
        $selectedName = $_GET['former-name'];
    } else {
        $selectedName = -1;
    }
    /** Grab all of the options that should be shown */
    $options[] = sprintf('<option value="0">%1$s</option>', __('All former', 'your-text-domain'));
    foreach($results as $result) :
        if ($result->ID == $selectedName) {
            $options[] = sprintf('<option value="%1$s" selected>%2$s</option>', esc_attr($result->ID), $result->post_title);
        } else {
            $options[] = sprintf('<option value="%1$s">%2$s</option>', esc_attr($result->ID), $result->post_title);
        }
    endforeach;
    /** Output the dropdown menu */
    echo '<select id="former-name" name="former-name">';
    echo join("\n", $options);
    echo '</select>';
}

add_filter( 'parse_query', 'filter_request_product_query' , 10);
function filter_request_product_query($query){
    //modify the query only if it admin and main query.
    if ( !(is_admin() AND $query->is_main_query()) ){
        return $query;
    }
    //we want to modify the query for the targeted custom post and filter option
    if ( !('product' === $query->query['post_type'] AND isset($_REQUEST['former-name']) ) ){
        return $query;
    }
    //for the default value of our filter no modification is required
    if (0 == $_REQUEST['former-name']){
        return $query;
    }
    //modify the query_vars.
    if ( ! $query->get( 'meta_query' )) {
        $query->set( 'meta_query', [
            [
                'key'     => 'former',
                'value'   => $_REQUEST['former-name'],
                'compare' => '='
            ]
        ] );
    }
    return $query;
}

/**
 * The hooks to create custom columns and their associated data for a custom post type are
 * manage_{$post_type}_posts_columns and manage_{$post_type}_posts_custom_column respectively,
 * where {$post_type} is the name of the custom post type.
 */

// Add the custom columns to the book post type:
add_filter( 'manage_product_posts_columns', 'set_custom_edit_book_columns' );
function set_custom_edit_book_columns($columns) {
    unset( $columns['author'] );
    $columns['former'] = 'Formateur';
    return $columns;
}

// Add the data to the custom columns for the book post type:
add_action( 'manage_product_posts_custom_column' , 'custom_book_column', 10, 2 );
function custom_book_column( $column, $post_id ) {
    switch ( $column ) {

        case 'former' :
            $former_id = get_post_meta($post_id,'former', true);
            if ( $former_id ):
                $former_id = intval($former_id);
                $former_post = get_post($former_id);
                echo $former_post->post_title;
            else:
                _e( 'Unable to get former(s)', 'ticket-falicrea' );
            endif;

            break;
    }
}




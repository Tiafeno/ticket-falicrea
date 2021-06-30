<?php

/*
Plugin Name: Ticket Formation
Plugin URI: https://falicrea.net
Description: ...
Version: 1.0
Author: Falicrea
Author URI: https://falicrea.net
License: A "Slug" license name e.g. GPL2
*/

include plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Liquid\Template;
use Liquid\Cache\Local;
use Liquid\Liquid;

Liquid::set('INCLUDE_PREFIX', '');
$engine = new Template (__DIR__ . '/templates');
$engine->setCache(new Local());


class TicketSecurity {
    private $permitted_chars = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
    public function __construct() {
        add_action('woocommerce_thankyou', [&$this, 'thank_you']);
        add_action('woocommerce_order_status_completed',array(&$this, 'thank_you'));
        add_action('init', [&$this, 'init']);
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
        // Le mail doit contenir un <DOCTYPE>, head, body...
        $content_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $content_type );
    }

    // Initialize DB Tables
    function init_db() {
        // WP Globals
        global $table_prefix, $wpdb;
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

    public function thank_you($order_id) {
        //Create an order instance
        $order = wc_get_order($order_id); // return WP_Order
        $paymethod = $order->payment_method_title;
        $orderstat = $order->get_status();
        if ($orderstat == 'completed') {
            // Create ticket security
            $code = $this->generate_string();
            include(plugin_dir_path(__FILE__) . 'inc/phpqrcode/qrlib.php');
            // how to save PNG codes to server
            $tempDir = plugin_dir_path(__FILE__) . '/qrcode';
            chmod($tempDir, 0755);
            $fileName = 'file_'.$code.'.png';
            $message = "Code: {$code}";
            $pngAbsoluteFilePath = $tempDir . '/' . $fileName;
            $urlRelativeFilePath = plugin_dir_url(__DIR__).'qrcode/'.$fileName;
            // generating
            if (!file_exists($pngAbsoluteFilePath)) {
                QRcode::png($message, $pngAbsoluteFilePath, QR_ECLEVEL_L, 4);
            }
            $his_code = $this->add_ticket_db($order_id, $code, $fileName);
            if ($his_code) {
                $this->send_code($order_id, $his_code, $urlRelativeFilePath);
            } else {
                $note = "Une erreur c'est produit dans la base de donnée";
                $order->add_order_note( $note );
            }
        }

    }

    public function add_ticket_db($order_id, $code, $filename) {
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
            // Save in db
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

    public function send_code($order_id, $code, $qrcode_url) {
        global $engine;
        $order = wc_get_order($order_id);
        $order_items = $order->get_items();
        $site_title = get_bloginfo( 'name' );
        $sujet = "#$order_id - Confirmation de commande";
        $body = $engine->parseFile('mail')->render([
            'qrcode_url' => $qrcode_url,
            'product_name' => $order_items[0]['name'],
            'sitename' => $site_title,
            'code' => $code,
            'author_name' => $order->get_billing_first_name() . ' ' .$order->get_billing_last_name(),
            'total_ttc' => $order->get_total()
        ]);
        $to_client = $order->get_billing_email(); // Get client address email
        wp_mail($to_client, $sujet, $body);
        $admin_email  = get_option('admin_email');
        if (is_email($admin_email)) {
            wp_mail($admin_email, $sujet, $body);
        }
    }
}

new TicketSecurity();

register_activation_hook( __FILE__, function() {
    $ticket = new TicketSecurity();
    $ticket->init_db();
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
        $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ticket_security");
        $results = $wpdb->get_results($query, OBJECT );
        $results = array_map(function($item){
            $order = wc_get_order(intval($item->order_id));
            $item->date = $order->get_date_created();
            $item->order_url = $order->get_edit_order_url();
            $item->author_name = $order->get_billing_first_name() . ' ' .$order->get_billing_last_name();
            $item->author_email = $order->get_billing_email();
            return $item;
        }, $results);
        echo $engine->parseFile('admin_menu_content')->render(['tickets' => $results]);

    } );
}


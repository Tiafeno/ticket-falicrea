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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_former_meta_boxes() {
        if (post_type_exists('former')) {
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
        wp_register_script('axios', plugin_dir_url(__FILE__) . '../assets/js/axios.min.js', [], null, true);
        wp_register_script('vue', plugin_dir_url(__FILE__) . '../assets/js/vue.js', ['jquery'], null, true);
        wp_register_script('vue-router', plugin_dir_url(__FILE__) . '../assets/js/vue-router.js', ['vue'], null, true);
        wp_register_script('former-details', plugin_dir_url(__FILE__) . '../assets/js/former-details.js',
            ['vue', 'vue-router', 'axios'], null, true);
        wp_localize_script('former-details', 'apiSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
            'root' => esc_url_raw(rest_url())
        ]);
    }
}

new FormerDetailsClass();
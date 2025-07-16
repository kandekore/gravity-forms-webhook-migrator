<?php
/**
 * Plugin Name: Gravity Forms Webhook Migrator
 * Plugin URI:  https://kandeshop.com
 * Description: Enables exporting and importing Gravity Forms Webhook feeds between sites.
 * Version:     1.0.0
 * Author:      Darren Kandekore
 * Author URI:  https://kandeshop.com
 * License:     GPL-2.0+
 * Text Domain: gf-webhook-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'GF_Webhook_Migrator' ) ) :

class GF_Webhook_Migrator {

    /**
     * Instance of this class.
     * @var object
     */
    protected static $instance = null;

    /**
     * Return an instance of this class.
     * @return object
     */
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Load dependencies
        $this->includes();

        // Add admin menu item
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Load required files.
     */
    private function includes() {
        require_once plugin_dir_path( __FILE__ ) . 'admin/class-gfwh-admin.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-gfwh-importer.php';
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'gf_settings', // Parent slug for Gravity Forms Settings
            __( 'Webhook Migrator', 'gf-webhook-migrator' ), // Page title
            __( 'Webhook Migrator', 'gf-webhook-migrator' ), // Menu title
            'gravityforms_edit_settings', // Capability: users who can edit GF settings
            'gf_webhook_migrator', // Menu slug
            array( $this, 'render_admin_page' ) // Callback to render the page
        );
    }

    /**
     * Render the admin page.
     */
    public function render_admin_page() {
        GFWH_Admin::render_page();
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', array( 'GF_Webhook_Migrator', 'get_instance' ) );

endif;
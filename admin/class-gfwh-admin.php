<?php
/**
 * Admin page for Gravity Forms Webhook Migrator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class GFWH_Admin {

    /**
     * Renders the admin page content.
     */
    public static function render_page() {
        if ( ! current_user_can( 'gravityforms_edit_settings' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'gf-webhook-migrator' ) );
        }

        // Process export
        if ( isset( $_POST['gfwh_export_nonce'] ) && wp_verify_nonce( $_POST['gfwh_export_nonce'], 'gfwh_export_webhooks' ) ) {
            self::handle_export();
        }

        // Process import
        if ( isset( $_POST['gfwh_import_nonce'] ) && wp_verify_nonce( $_POST['gfwh_import_nonce'], 'gfwh_import_webhooks' ) ) {
            self::handle_import();
        }

        // Get all active Gravity Forms
        $forms = GFAPI::get_forms( null, false, false ); // args: $active (all), $trash (no trash), $include_entry_meta (no meta)
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Gravity Forms Webhook Migrator', 'gf-webhook-migrator' ); ?></h1>

            <div id="gfwh-messages">
                <?php settings_errors(); // Display success/error messages ?>
            </div>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content" style="position: relative;">

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Export Webhook Feeds', 'gf-webhook-migrator' ); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e( 'Select a Gravity Form to export its associated Webhook feeds. The feeds will be downloaded as a JSON file.', 'gf-webhook-migrator' ); ?></p>
                                <p class="description"><?php esc_html_e( 'This file may contain sensitive information (e.g., API keys in URLs). Handle it securely.', 'gf-webhook-migrator' ); ?></p>
                                <form method="post">
                                    <?php wp_nonce_field( 'gfwh_export_webhooks', 'gfwh_export_nonce' ); ?>
                                    <table class="form-table">
                                        <tbody>
                                            <tr>
                                                <th scope="row"><label for="gfwh_export_form_id"><?php esc_html_e( 'Select Form', 'gf-webhook-migrator' ); ?></label></th>
                                                <td>
                                                    <select name="gfwh_export_form_id" id="gfwh_export_form_id" required>
                                                        <option value=""><?php esc_html_e( '-- Select a Form --', 'gf-webhook-migrator' ); ?></option>
                                                        <?php
                                                        foreach ( $forms as $form ) {
                                                            printf( '<option value="%d">%s (ID: %d)</option>',
                                                                esc_attr( $form['id'] ),
                                                                esc_html( $form['title'] ),
                                                                esc_attr( $form['id'] )
                                                            );
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <?php submit_button( __( 'Export Webhooks', 'gf-webhook-migrator' ), 'primary', 'submit_export', false ); ?>
                                </form>
                            </div>
                        </div>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Import Webhook Feeds', 'gf-webhook-migrator' ); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e( 'Upload a JSON file containing Webhook feeds previously exported from another site. Select the target form on this site where the feeds should be imported.', 'gf-webhook-migrator' ); ?></p>
                                <p class="description"><?php esc_html_e( 'Existing feeds for the target form will NOT be overwritten unless they have the exact same meta data; new feeds will be created. Review imported feeds in Form Settings -> Webhooks.', 'gf-webhook-migrator' ); ?></p>
                                <form method="post" enctype="multipart/form-data">
                                    <?php wp_nonce_field( 'gfwh_import_webhooks', 'gfwh_import_nonce' ); ?>
                                    <table class="form-table">
                                        <tbody>
                                            <tr>
                                                <th scope="row"><label for="gfwh_import_file"><?php esc_html_e( 'Select JSON File', 'gf-webhook-migrator' ); ?></label></th>
                                                <td>
                                                    <input type="file" name="gfwh_import_file" id="gfwh_import_file" accept=".json" required />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><label for="gfwh_import_form_id"><?php esc_html_e( 'Target Form', 'gf-webhook-migrator' ); ?></label></th>
                                                <td>
                                                    <select name="gfwh_import_form_id" id="gfwh_import_form_id" required>
                                                        <option value=""><?php esc_html_e( '-- Select a Form --', 'gf-webhook-migrator' ); ?></option>
                                                        <?php
                                                        foreach ( $forms as $form ) {
                                                            printf( '<option value="%d">%s (ID: %d)</option>',
                                                                esc_attr( $form['id'] ),
                                                                esc_html( $form['title'] ),
                                                                esc_attr( $form['id'] )
                                                            );
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <?php submit_button( __( 'Import Webhooks', 'gf-webhook-migrator' ), 'primary', 'submit_import', false ); ?>
                                </form>
                            </div>
                        </div>

                    </div></div></div></div><?php
    }

    /**
     * Handles the export process.
     */
    private static function handle_export() {
        if ( empty( $_POST['gfwh_export_form_id'] ) ) {
            add_settings_error( 'gfwh_export', 'gfwh_export_error', __( 'Please select a form to export.', 'gf-webhook-migrator' ), 'error' );
            return;
        }

        $form_id = absint( $_POST['gfwh_export_form_id'] );
        $form_title = GFAPI::get_form( $form_id )['title'] ?? 'unknown';

        $feeds_addon = GFAddon::get_addon_instance( 'gravityformswebhooks' );

        if ( ! $feeds_addon ) {
            add_settings_error( 'gfwh_export', 'gfwh_export_error', __( 'Gravity Forms Webhooks Add-On is not active or installed.', 'gf-webhook-migrator' ), 'error' );
            return;
        }

        $feeds = $feeds_addon->get_feeds( $form_id );

        if ( empty( $feeds ) ) {
            add_settings_error( 'gfwh_export', 'gfwh_export_warning', sprintf( __( 'No Webhook feeds found for form "%s" (ID: %d).', 'gf-webhook-migrator' ), $form_title, $form_id ), 'updated' );
            return;
        }

        // Prepare data for export
        $export_data = array(
            'form_id'    => $form_id,
            'form_title' => $form_title,
            'exported_at' => current_time( 'mysql' ),
            'webhook_feeds' => array(),
        );

        foreach ( $feeds as $feed ) {
            $feed_settings = $feeds_addon->get_feed_settings( $feed['id'] ); // Get full settings including meta
            $export_data['webhook_feeds'][] = array(
                'feed_name' => $feed['feedName'],
                'is_active' => $feed['is_active'],
                'meta'      => $feed_settings, // All feed settings are in 'meta' array for Webhooks
            );
        }

        // Set headers for file download
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename=gf-webhooks-form-' . $form_id . '-' . date( 'YmdHis' ) . '.json' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        echo json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    /**
     * Handles the import process.
     */
    private static function handle_import() {
        if ( empty( $_FILES['gfwh_import_file']['tmp_name'] ) || empty( $_POST['gfwh_import_form_id'] ) ) {
            add_settings_error( 'gfwh_import', 'gfwh_import_error', __( 'Please select a JSON file and a target form.', 'gf-webhook-migrator' ), 'error' );
            return;
        }

        $target_form_id = absint( $_POST['gfwh_import_form_id'] );
        if ( ! GFAPI::get_form( $target_form_id ) ) {
            add_settings_error( 'gfwh_import', 'gfwh_import_error', __( 'Target form does not exist.', 'gf-webhook-migrator' ), 'error' );
            return;
        }

        $feeds_addon = GFAddon::get_addon_instance( 'gravityformswebhooks' );
        if ( ! $feeds_addon ) {
            add_settings_error( 'gfwh_import', 'gfwh_import_error', __( 'Gravity Forms Webhooks Add-On is not active or installed on this site.', 'gf-webhook-migrator' ), 'error' );
            return;
        }

        $file_content = file_get_contents( $_FILES['gfwh_import_file']['tmp_name'] );
        $import_data = json_decode( $file_content, true );

        if ( ! is_array( $import_data ) || ! isset( $import_data['webhook_feeds'] ) || ! is_array( $import_data['webhook_feeds'] ) ) {
            add_settings_error( 'gfwh_import', 'gfwh_import_error', __( 'Invalid JSON file format. Please upload a valid Webhook feeds export file.', 'gf-webhook-migrator' ), 'error' );
            return;
        }

        $imported_count = 0;
        foreach ( $import_data['webhook_feeds'] as $feed_data ) {
            $feed_name = $feed_data['feed_name'] ?? 'Imported Webhook Feed';
            $is_active = $feed_data['is_active'] ?? true;
            $feed_meta = $feed_data['meta'] ?? array();

            // Webhook add-on expects feed meta to contain specific keys for its settings
            // The meta should contain 'feedName', 'requestMethod', 'requestURL', etc.
            // Ensure necessary fields are present, even if empty, for the add-on to recognize.
            $default_webhook_meta = array(
                'feedName'      => $feed_name,
                'requestMethod' => 'POST', // Default if not in exported meta
                'requestURL'    => '',
                'requestFormat' => 'json',
                'requestBody'   => 'all_fields', // 'all_fields' or 'select_fields'
                'conditionalLogic' => array( 'enabled' => false ), // Default conditional logic
                // ... add other default keys expected by the webhook feed settings
            );

            $feed_meta = wp_parse_args( $feed_meta, $default_webhook_meta );

            // Ensure the form_id is correctly set for the new feed
            $feed_meta['form_id'] = $target_form_id;

            // Create the feed
            // The GFAddon::update_plugin_settings method often used for updates,
            // but we need to create a new feed. The add-on usually has an add_feed() or similar.
            // For Webhooks, it's typically $addon->update_feed( $feed_id, $form_id, $is_active, $name, $settings );
            // If feed_id is 0 or null, it creates a new one.

            $new_feed_id = $feeds_addon->update_feed(
                0, // 0 for new feed
                $target_form_id,
                $is_active,
                $feed_name,
                $feed_meta
            );

            if ( ! is_wp_error( $new_feed_id ) && $new_feed_id ) {
                $imported_count++;
            } else {
                error_log( 'GF Webhook Migrator: Failed to import feed "' . $feed_name . '" for form ID ' . $target_form_id . '. Error: ' . ( is_wp_error( $new_feed_id ) ? $new_feed_id->get_error_message() : 'Unknown' ) );
            }
        }

        if ( $imported_count > 0 ) {
            add_settings_error( 'gfwh_import', 'gfwh_import_success', sprintf( __( 'Successfully imported %d Webhook feeds to form ID %d.', 'gf-webhook-migrator' ), $imported_count, $target_form_id ), 'success' );
        } else {
            add_settings_error( 'gfwh_import', 'gfwh_import_warning', __( 'No Webhook feeds were imported. Check your JSON file and error logs.', 'gf-webhook-migrator' ), 'error' );
        }

        // Redirect to clear POST data and show messages
        $redirect_url = remove_query_arg( array( 'gfwh_export_nonce', 'gfwh_export_form_id', 'submit_export' ) );
        $redirect_url = add_query_arg( 'settings-updated', 'true', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }
}
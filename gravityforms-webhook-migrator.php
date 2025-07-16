<?php
/**
 * Plugin Name: Gravity Forms Webhook Transfer
 * Description: Export and import Gravity Forms Webhooks between sites.
 * Version: 1.0
 * Author: Darren Kandekore
 * Author URI: https://kandeshop.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gravity-forms-webhook-transfer
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'Webhook Transfer',
        'Webhook Transfer',
        'manage_options',
        'gf-webhook-transfer',
        'gf_webhook_transfer_page'
    );
});

function gf_webhook_transfer_page()
{
    if (!class_exists('GFAPI') || !class_exists('GFAddOn')) {
        echo '<div class="notice notice-error"><p>Gravity Forms and Webhooks Add-On must be installed and active.</p></div>';
        return;
    }

    $forms = GFAPI::get_forms();

    echo '<div class="wrap"><h1>Gravity Forms Webhook Transfer</h1>';

    // EXPORT FORM
    echo '<h2>Export Webhooks</h2>';
    echo '<form method="post">';
    echo '<select name="form_id">';
    foreach ($forms as $form) {
        printf('<option value="%d">%s</option>', $form['id'], esc_html($form['title']));
    }
    echo '</select>';
    submit_button('Export Webhooks');
    echo '</form>';

    if (!empty($_POST['form_id']) && current_user_can('manage_options')) {
        $form_id = absint($_POST['form_id']);
        global $wpdb;
        $feed_table = $wpdb->prefix . 'gf_addon_feed';

        $feeds = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$feed_table} WHERE form_id = %d AND addon_slug = %s", $form_id, 'gravityformswebhooks'),
            ARRAY_A
        );

        if ($feeds) {
            $json = json_encode($feeds, JSON_PRETTY_PRINT);
            $filename = 'gf-webhooks-form-' . $form_id . '-' . time() . '.json';

            // Clean any previous output
            if (ob_get_length()) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($json));

            echo $json;
            exit;
        } else {
            echo '<p>No webhooks found for this form.</p>';
        }
    }

    // IMPORT FORM
    echo '<hr><h2>Import Webhooks</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<select name="import_form_id">';
    foreach ($forms as $form) {
        printf('<option value="%d">%s</option>', $form['id'], esc_html($form['title']));
    }
    echo '</select><br><br>';
    echo '<input type="file" name="webhook_json" required><br><br>';
    submit_button('Import Webhooks');
    echo '</form>';

    if (!empty($_FILES['webhook_json']) && !empty($_POST['import_form_id']) && current_user_can('manage_options')) {
        $form_id = absint($_POST['import_form_id']);
        $json = file_get_contents($_FILES['webhook_json']['tmp_name']);
        $feeds = json_decode($json, true);

        if (!is_array($feeds)) {
            echo '<p><strong>Invalid JSON format.</strong></p>';
        } else {
            global $wpdb;
            $feed_table = $wpdb->prefix . 'gf_addon_feed';

            foreach ($feeds as $feed) {
                unset($feed['id']); // Let DB auto-assign ID
                $feed['form_id'] = $form_id;

                $wpdb->insert($feed_table, $feed);
            }

            echo '<p><strong>Webhooks imported successfully!</strong></p>';
        }
    }

    echo '</div>';
}

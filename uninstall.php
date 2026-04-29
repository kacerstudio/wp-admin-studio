<?php
/**
 * Uninstall WP Admin Studio
 *
 * Fired when the plugin is uninstalled (deleted via WP admin).
 * Removes all plugin options and cleans up role capabilities.
 *
 * @package WP_Admin_Studio
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options
delete_option('wpc_settings');
delete_option('wpc_language');

// Remove transients
delete_transient('wpc_settings_saved');
delete_transient('wpc_detected_widgets');

// Clean up unfiltered_upload capability that may have been granted in older versions
$administrator_role = get_role('administrator');
if ($administrator_role && $administrator_role->has_cap('unfiltered_upload')) {
    $administrator_role->remove_cap('unfiltered_upload');
}

$editor_role = get_role('editor');
if ($editor_role && $editor_role->has_cap('unfiltered_upload')) {
    $editor_role->remove_cap('unfiltered_upload');
}

// Clean up any bug-report transients
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
        $wpdb->esc_like('_transient_wpc_bug_report_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
        $wpdb->esc_like('_transient_timeout_wpc_bug_report_') . '%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

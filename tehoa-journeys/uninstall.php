<?php
// Removes all Tehoa Journeys data when the plugin is deleted from wp-admin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tj_events, {$wpdb->prefix}tj_visits" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( 'tj_sites', 'tj_retention_days', 'tj_db_version', 'tj_salt', 'tj_last_site_id' ) as $opt ) {
	delete_option( $opt );
}
wp_clear_scheduled_hook( 'tj_daily_cleanup' );

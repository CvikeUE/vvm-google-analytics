<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tj_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'tj_' . $name;
}

function tj_activate() {
	tj_install_schema();
	tj_default_options();
	if ( ! wp_next_scheduled( 'tj_daily_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tj_daily_cleanup' );
	}
}

function tj_deactivate() {
	wp_clear_scheduled_hook( 'tj_daily_cleanup' );
}

function tj_maybe_upgrade() {
	if ( get_option( 'tj_db_version' ) !== TJ_DB_VERSION ) {
		tj_install_schema();
		tj_default_options();
	}
}

function tj_install_schema() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$events  = tj_table( 'events' );
	$visits  = tj_table( 'visits' );

	// type: 1 = pageview, 2 = click. kind: 0 = interaction without a link, 1 = internal link, 2 = outbound,
	// 3 = affiliate, 4 = review page link. widget: tracker source key (see tj_sources()). page_type: see tj_page_types().
	dbDelta(
		"CREATE TABLE $events (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  visit_id char(16) NOT NULL,
  site_id smallint(5) unsigned NOT NULL,
  type tinyint(3) unsigned NOT NULL,
  kind tinyint(3) unsigned NOT NULL DEFAULT 0,
  path varchar(255) NOT NULL DEFAULT '',
  title varchar(150) NOT NULL DEFAULT '',
  target varchar(500) NOT NULL DEFAULT '',
  label varchar(150) NOT NULL DEFAULT '',
  widget varchar(32) NOT NULL DEFAULT '',
  page_type tinyint(3) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY visit (visit_id,id),
  KEY site_time (site_id,created_at),
  KEY site_type_time (site_id,type,created_at)
) $charset;"
	);

	// device: 0 = unknown, 1 = mobile, 2 = tablet, 3 = desktop.
	dbDelta(
		"CREATE TABLE $visits (
  visit_id char(16) NOT NULL,
  site_id smallint(5) unsigned NOT NULL,
  visitor char(16) NOT NULL,
  started_at datetime NOT NULL,
  last_at datetime NOT NULL,
  entry_path varchar(255) NOT NULL DEFAULT '',
  referrer varchar(190) NOT NULL DEFAULT '',
  device tinyint(3) unsigned NOT NULL DEFAULT 0,
  pageviews smallint(5) unsigned NOT NULL DEFAULT 0,
  clicks smallint(5) unsigned NOT NULL DEFAULT 0,
  aff_clicks smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (visit_id),
  KEY site_last (site_id,last_at),
  KEY site_visitor (site_id,visitor,last_at)
) $charset;"
	);

	if ( ! get_option( 'tj_widgets_since' ) ) {
		update_option( 'tj_widgets_since', gmdate( 'Y-m-d H:i:s' ), false );
	}
	update_option( 'tj_db_version', TJ_DB_VERSION, false );
}

function tj_default_options() {
	if ( false === get_option( 'tj_sites' ) ) {
		$sites = array();
		$id    = 1;
		foreach ( array( 'vironkasinot.fi', 'vedonlyontisivu.fi', 'kaikkimgakasinot.fi' ) as $domain ) {
			$sites[ $id ] = array(
				'name'     => $domain,
				'domain'   => $domain,
				'key'      => wp_generate_password( 20, false ),
				'patterns' => tj_default_patterns(),
				'reviews'  => tj_default_review_patterns(),
			);
			$id++;
		}
		add_option( 'tj_sites', $sites, '', false );
	}
	add_option( 'tj_retention_days', 90, '', false );
}

function tj_default_patterns() {
	return "/go/\n/out/\n/visit/\n/goto/\n/recommends/\n/refer/\n/link/\n/play/\n/siirry/";
}

/** Daily cron: drop data older than the retention window. */
function tj_cleanup() {
	global $wpdb;
	$days = max( 1, (int) get_option( 'tj_retention_days', 90 ) );
	$cut  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . tj_table( 'events' ) . ' WHERE created_at < %s', $cut ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . tj_table( 'visits' ) . ' WHERE last_at < %s', $cut ) );
}

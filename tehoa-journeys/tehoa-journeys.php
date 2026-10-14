<?php
/**
 * Plugin Name: Tehoa Journeys
 * Description: Lightweight, cookieless visitor journey tracking (pages + clicks) for external sites, viewable in wp-admin.
 * Version:     2.1.0
 * Author:      VVM Agency
 * Requires PHP: 7.4
 * Requires at least: 5.6
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TJ_VERSION', '2.1.0' );
define( 'TJ_DB_VERSION', '2' );
define( 'TJ_FILE', __FILE__ );
define( 'TJ_DIR', plugin_dir_path( __FILE__ ) );
define( 'TJ_URL', plugin_dir_url( __FILE__ ) );

// A new visit starts after this many seconds of inactivity.
define( 'TJ_VISIT_GAP', 30 * MINUTE_IN_SECONDS );
// Max events accepted per client IP per minute.
define( 'TJ_RATE_LIMIT', 60 );

require_once TJ_DIR . 'includes/install.php';
require_once TJ_DIR . 'includes/sites.php';
require_once TJ_DIR . 'includes/collector.php';

if ( is_admin() ) {
	require_once TJ_DIR . 'includes/admin.php';
	require_once TJ_DIR . 'includes/widgets-page.php';
}

register_activation_hook( __FILE__, 'tj_activate' );
register_deactivation_hook( __FILE__, 'tj_deactivate' );

add_action( 'plugins_loaded', 'tj_maybe_upgrade' );
add_action( 'rest_api_init', 'tj_register_routes' );
add_action( 'tj_daily_cleanup', 'tj_cleanup' );

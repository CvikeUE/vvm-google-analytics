<?php
/**
 * Public collection endpoint: POST /wp-json/tehoa/v1/e
 *
 * Accepts a tiny JSON body (sent as text/plain via navigator.sendBeacon, so no CORS preflight)
 * only from the configured site origins. Nothing is ever echoed back, all writes go through
 * $wpdb->prepare/insert, and no raw IP address is stored.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tj_register_routes() {
	register_rest_route(
		'tehoa/v1',
		'/e',
		array(
			'methods'             => 'POST',
			'callback'            => 'tj_collect',
			'permission_callback' => '__return_true',
		)
	);
}

function tj_empty_response() {
	$res = new WP_REST_Response( null, 204 );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

function tj_collect( WP_REST_Request $request ) {
	$body = $request->get_body();
	if ( '' === $body || strlen( $body ) > 4096 ) {
		return tj_empty_response();
	}
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || empty( $data['k'] ) || ! is_string( $data['k'] ) ) {
		return tj_empty_response();
	}

	// Resolve the site by its public key, then require the browser's Origin to be that site.
	$site_id = 0;
	$site    = null;
	foreach ( tj_get_sites() as $id => $s ) {
		if ( hash_equals( (string) $s['key'], $data['k'] ) ) {
			$site_id = (int) $id;
			$site    = $s;
			break;
		}
	}
	if ( ! $site ) {
		return tj_empty_response();
	}
	$origin = (string) $request->get_header( 'origin' );
	if ( ! tj_host_matches( wp_parse_url( $origin, PHP_URL_HOST ), $site['domain'] ) ) {
		return tj_empty_response();
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 512 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only hashed / regex-tested.
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|gtmetrix|pingdom|preview|monitor|curl|wget|python/i', $ua ) ) {
		return tj_empty_response();
	}

	$ip = tj_client_ip();
	if ( ! tj_rate_ok( $ip ) ) {
		return tj_empty_response();
	}

	$type = isset( $data['t'] ) ? $data['t'] : '';
	$path = tj_clean_path( isset( $data['p'] ) ? $data['p'] : '' );
	if ( '' === $path || ( 'pv' !== $type && 'c' !== $type ) ) {
		return tj_empty_response();
	}

	$now     = time();
	$now_sql = gmdate( 'Y-m-d H:i:s', $now );
	$visitor = tj_visitor_hash( $site_id, $ip, $ua, isset( $data['v'] ) ? $data['v'] : '' );

	$page_type = array_search( isset( $data['pt'] ) ? $data['pt'] : '', tj_page_types(), true );
	$widget    = isset( $data['wg'] ) && is_string( $data['wg'] ) && preg_match( '/^[a-z0-9_]{1,32}$/', $data['wg'] ) ? $data['wg'] : '';

	$event = array(
		'site_id'    => $site_id,
		'type'       => 'pv' === $type ? 1 : 2,
		'kind'       => 0,
		'path'       => $path,
		'title'      => '',
		'target'     => '',
		'label'      => '',
		'widget'     => 'c' === $type ? $widget : '',
		'page_type'  => $page_type ? (int) $page_type : 0,
		'created_at' => $now_sql,
	);

	if ( 'pv' === $type ) {
		$event['title'] = tj_clean_text( isset( $data['ti'] ) ? $data['ti'] : '', 150 );
	} else {
		$target = isset( $data['h'] ) && is_string( $data['h'] ) ? $data['h'] : '';
		$rel    = isset( $data['rel'] ) && is_string( $data['rel'] ) ? $data['rel'] : '';
		list( $event['kind'], $event['target'] ) = tj_classify_click( $target, $rel, $site );
		$event['label'] = tj_clean_text( isset( $data['x'] ) ? $data['x'] : '', 150 );
	}

	$extra = array(
		'referrer' => 'pv' === $type ? tj_clean_host( isset( $data['r'] ) ? $data['r'] : '', $site['domain'] ) : '',
		'device'   => tj_device( isset( $data['w'] ) ? $data['w'] : 0 ),
	);

	$event['visit_id'] = tj_touch_visit( $site_id, $visitor, $now, $event, $extra );
	tj_insert_event( $event );

	return tj_empty_response();
}

function tj_insert_event( array $event ) {
	global $wpdb;
	$wpdb->insert(
		tj_table( 'events' ),
		$event,
		array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
	);
}

/**
 * Find the visitor's open visit (activity within TJ_VISIT_GAP) or start a new one,
 * and bump its counters. Returns the visit id.
 */
function tj_touch_visit( $site_id, $visitor, $now, array $event, array $extra ) {
	global $wpdb;
	$visits  = tj_table( 'visits' );
	$now_sql = gmdate( 'Y-m-d H:i:s', $now );

	$visit_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT visit_id FROM $visits WHERE site_id = %d AND visitor = %s AND last_at >= %s ORDER BY last_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			$site_id,
			$visitor,
			gmdate( 'Y-m-d H:i:s', $now - TJ_VISIT_GAP )
		)
	);

	$is_pv  = 1 === $event['type'] ? 1 : 0;
	$is_clk = 2 === $event['type'] ? 1 : 0;
	$is_aff = 3 === $event['kind'] ? 1 : 0;

	if ( $visit_id ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $visits SET last_at = %s, pageviews = pageviews + %d, clicks = clicks + %d, aff_clicks = aff_clicks + %d WHERE visit_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				$now_sql,
				$is_pv,
				$is_clk,
				$is_aff,
				$visit_id
			)
		);
		return $visit_id;
	}

	$visit_id = bin2hex( random_bytes( 8 ) );
	$wpdb->insert(
		$visits,
		array(
			'visit_id'   => $visit_id,
			'site_id'    => $site_id,
			'visitor'    => $visitor,
			'started_at' => $now_sql,
			'last_at'    => $now_sql,
			'entry_path' => $event['path'],
			'referrer'   => $extra['referrer'],
			'device'     => $extra['device'],
			'pageviews'  => $is_pv,
			'clicks'     => $is_clk,
			'aff_clicks' => $is_aff,
		),
		array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
	);
	return $visit_id;
}

/**
 * Pseudonymous visitor id. By default it is cookieless: a keyed hash of IP + user agent with a
 * salt that rotates daily, so the same person is recognised within a day but cannot be linked
 * across days and the raw IP is never stored. If the site opted in to a persistent browser id
 * (data-persist, only after consent), that id is hashed instead.
 */
function tj_visitor_hash( $site_id, $ip, $ua, $persistent ) {
	if ( is_string( $persistent ) && preg_match( '/^[a-f0-9]{16,32}$/', $persistent ) ) {
		return substr( hash_hmac( 'sha256', 'p|' . $site_id . '|' . $persistent, wp_salt( 'auth' ) ), 0, 16 );
	}
	return substr( hash_hmac( 'sha256', $site_id . '|' . $ip . '|' . $ua, tj_daily_salt() ), 0, 16 );
}

function tj_daily_salt() {
	$today = gmdate( 'Y-m-d' );
	$salt  = get_option( 'tj_salt' );
	if ( ! is_array( $salt ) || ! isset( $salt['day'], $salt['value'] ) || $salt['day'] !== $today ) {
		$salt = array(
			'day'   => $today,
			'value' => bin2hex( random_bytes( 32 ) ),
		);
		update_option( 'tj_salt', $salt, false );
	}
	return $salt['value'];
}

function tj_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
	// Behind a local reverse proxy (nginx/Varnish) REMOTE_ADDR is private; use the first forwarded hop.
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		foreach ( array( 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$first = trim( explode( ',', (string) $_SERVER[ $h ] )[0] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					return $first;
				}
			}
		}
	}
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/** Simple fixed-window limiter per (hashed) client IP. */
function tj_rate_ok( $ip ) {
	$key   = 'tj_rl_' . substr( md5( $ip . gmdate( 'YmdHi' ) ), 0, 20 );
	$count = (int) get_transient( $key );
	if ( $count >= TJ_RATE_LIMIT ) {
		return false;
	}
	set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
	return true;
}

function tj_clean_text( $value, $max ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = wp_strip_all_tags( $value );
	$value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
	$value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	return mb_substr( $value, 0, $max );
}

/** Keep only a URL path ("/foo/bar/"); query strings and fragments are dropped (may contain PII). */
function tj_clean_path( $value ) {
	if ( ! is_string( $value ) || '' === $value || '/' !== $value[0] ) {
		return '';
	}
	$value = (string) strtok( $value, '?#' );
	// Browsers send location.pathname percent-encoded, so only RFC 3986 path characters are kept.
	$value = preg_replace( '/[^A-Za-z0-9\-._~%!$&\'()*+,;=:@\/]/', '', $value );
	return substr( (string) $value, 0, 255 );
}

/** Referrer reduced to its host; empty for direct traffic or internal navigation. */
function tj_clean_host( $value, $domain ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}
	$host = strtolower( (string) wp_parse_url( ( false === strpos( $value, '//' ) ? 'https://' : '' ) . $value, PHP_URL_HOST ) );
	if ( '' === $host || ! preg_match( '/^[a-z0-9.-]+$/', $host ) || tj_host_matches( $host, $domain ) ) {
		return '';
	}
	return substr( $host, 0, 190 );
}

function tj_device( $width ) {
	$width = (int) $width;
	if ( $width <= 0 ) {
		return 0;
	}
	return $width < 768 ? 1 : ( $width < 1024 ? 2 : 3 );
}

/**
 * @return array{0:int,1:string} [kind, stored target]
 */
function tj_classify_click( $target, $rel, array $site ) {
	$target = substr( trim( $target ), 0, 1000 );
	$parts  = wp_parse_url( $target );
	if ( ! $parts || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
		// Buttons, tel:/mailto:, javascript: etc. Keep only the scheme for context.
		$scheme = $parts && ! empty( $parts['scheme'] ) ? preg_replace( '/[^a-z]/', '', strtolower( $parts['scheme'] ) ) : '';
		return array( 0, $scheme ? $scheme . ':' : '' );
	}

	$internal = tj_host_matches( $parts['host'], $site['domain'] );
	$path     = isset( $parts['path'] ) ? $parts['path'] : '/';
	$clean    = esc_url_raw( $target );

	$affiliate = false !== stripos( $rel, 'sponsored' );
	if ( ! $affiliate ) {
		foreach ( preg_split( '/\R/', (string) $site['patterns'] ) as $pattern ) {
			$pattern = trim( $pattern );
			if ( '' !== $pattern && false !== stripos( $path, $pattern ) ) {
				$affiliate = true;
				break;
			}
		}
	}
	if ( ! $affiliate && ! $internal && isset( $parts['query'] ) && preg_match( '/(^|&)(btag|affid|aff_id|affiliate|aff|a_aid|clickid|tracking_code|payload|pid|subid)=/i', $parts['query'] ) ) {
		$affiliate = true;
	}

	if ( $affiliate ) {
		return array( 3, substr( $clean, 0, 500 ) );
	}
	if ( $internal ) {
		foreach ( preg_split( '/\R/', tj_review_patterns( $site ) ) as $pattern ) {
			$pattern = trim( $pattern );
			if ( '' !== $pattern && 0 === stripos( $path, $pattern ) && strlen( $path ) > strlen( $pattern ) ) {
				return array( 4, substr( tj_clean_path( $path ), 0, 500 ) );
			}
		}
		return array( 1, substr( tj_clean_path( $path ), 0, 500 ) );
	}
	return array( 2, substr( $clean, 0, 500 ) );
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int,array{name:string,domain:string,key:string,patterns:string}> */
function tj_get_sites() {
	$sites = get_option( 'tj_sites', array() );
	return is_array( $sites ) ? $sites : array();
}

/** Normalise a user-entered domain to a bare lowercase host without "www.". */
function tj_normalize_domain( $value ) {
	$value = strtolower( trim( (string) $value ) );
	if ( false !== strpos( $value, '//' ) ) {
		$value = (string) wp_parse_url( $value, PHP_URL_HOST );
	}
	$value = preg_replace( '/^www\./', '', $value );
	return preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $value ) ? $value : '';
}

/** True when $host is the site's domain or its www. variant. */
function tj_host_matches( $host, $domain ) {
	$host = strtolower( (string) $host );
	return $host === $domain || $host === 'www.' . $domain;
}

function tj_snippet( array $site ) {
	// A tiny inline loader adds the date to the tracker URL, so browsers pick up tracker updates
	// within a day while the file itself stays a static, long-cached asset.
	return sprintf(
		"<script>(function(d){var s=d.createElement('script');s.async=true;s.src='%s?d='+new Date().toISOString().slice(0,10);s.setAttribute('data-endpoint','%s');s.setAttribute('data-key','%s');d.head.appendChild(s)})(document);</script>",
		esc_js( esc_url_raw( TJ_URL . 'assets/t.js' ) ),
		esc_js( esc_url_raw( rest_url( 'tehoa/v1/e' ) ) ),
		esc_js( $site['key'] )
	);
}

function tj_default_review_patterns() {
	return "/arvostelut/\n/arvostelu/\n/reviews/\n/kokemuksia/\n/recension/\n/resenas/";
}

/** Review page path prefixes for a site (older site settings predate this field). */
function tj_review_patterns( array $site ) {
	return isset( $site['reviews'] ) ? (string) $site['reviews'] : tj_default_review_patterns();
}

/** Page types sent by the tracker (body class based), stored as small ints. */
function tj_page_types() {
	return array( 1 => 'home', 2 => 'review', 3 => 'page', 4 => 'article', 5 => 'list', 6 => 'other' );
}

/**
 * Click sources the tracker recognises in the Tehoa theme: key => [label, is_widget].
 * Widgets are the theme's content shortcodes; the rest are fixed page areas.
 */
function tj_sources() {
	return array(
		'review_cards_rows'    => array( 'Casino list (rows)', true ),
		'review_cards_columns' => array( 'Casino cards (columns)', true ),
		'affiliate_buttons'    => array( 'Affiliate buttons', true ),
		'comment_cards'        => array( 'Comment cards', true ),
		'table_list'           => array( 'Table list', true ),
		'faq'                  => array( 'FAQ', true ),
		'how_to'               => array( 'How-to', true ),
		'tabbed_info'          => array( 'Tabbed info', true ),
		'pros_and_cons'        => array( 'Pros & cons', true ),
		'info_boxes'           => array( 'Info boxes', true ),
		'payment_methods'      => array( 'Payment methods', true ),
		'game_providers'       => array( 'Game providers', true ),
		'news'                 => array( 'News', true ),
		'author_box'           => array( 'Author box', true ),
		'toc'                  => array( 'Table of contents', true ),
		'video'                => array( 'Video', true ),
		'gallery'              => array( 'Image gallery', true ),
		'icon_cards'           => array( 'Icon cards', true ),
		'pie_charts'           => array( 'Pie charts', true ),
		'chart'                => array( 'Chart', true ),
		'crypto_converter'     => array( 'Crypto converter', true ),
		'review_hero'          => array( 'Review hero (Play button)', false ),
		'review_compare_table' => array( 'Review comparison table', false ),
		'hero'                 => array( 'Page hero', false ),
		'sidebar'              => array( 'Sidebar', false ),
		'compare_drawer'       => array( 'Compare drawer', false ),
		'exit_popup'           => array( 'Exit popup', false ),
		'header'               => array( 'Header & menu', false ),
		'footer'               => array( 'Footer', false ),
		'content'              => array( 'Text links in content', false ),
		''                     => array( 'Other', false ),
	);
}

function tj_source_label( $key ) {
	$all = tj_sources();
	return isset( $all[ $key ] ) ? $all[ $key ][0] : ucwords( str_replace( '_', ' ', $key ) );
}

/** @return string[] keys of the theme widgets. */
function tj_widget_keys() {
	return array_keys( array_filter( tj_sources(), function ( $s ) { return $s[1]; } ) );
}

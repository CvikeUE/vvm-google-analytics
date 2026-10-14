<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'tj_admin_menu' );
add_action( 'admin_post_tj_save_settings', 'tj_save_settings' );

function tj_admin_menu() {
	add_menu_page( 'Visitor Journeys', 'Visitor Journeys', 'manage_options', 'tehoa-journeys', 'tj_render_journeys', 'dashicons-chart-line', 3 );
	add_submenu_page( 'tehoa-journeys', 'Visitor Journeys', 'Visits', 'manage_options', 'tehoa-journeys', 'tj_render_journeys' );
	add_submenu_page( 'tehoa-journeys', 'Widgets & Sources', 'Widgets & Sources', 'manage_options', 'tehoa-journeys-widgets', 'tj_render_widgets' );
	add_submenu_page( 'tehoa-journeys', 'Sites & Code', 'Sites & Code', 'manage_options', 'tehoa-journeys-settings', 'tj_render_settings' );
}

function tj_fmt_time( $gmt, $format = null ) {
	$ts = strtotime( $gmt . ' UTC' );
	return wp_date( $format ? $format : get_option( 'date_format' ) . ' H:i', $ts );
}

function tj_device_label( $device ) {
	$labels = array( 0 => 'Unknown device', 1 => 'Mobile', 2 => 'Tablet', 3 => 'Desktop' );
	return isset( $labels[ $device ] ) ? $labels[ $device ] : 'Unknown device';
}

function tj_device_icon( $device ) {
	$icons = array( 1 => 'dashicons-smartphone', 2 => 'dashicons-tablet', 3 => 'dashicons-desktop' );
	return isset( $icons[ $device ] ) ? $icons[ $device ] : 'dashicons-editor-help';
}

/** Stable hue (0-359) per visitor so the same visitor always gets the same avatar colour. */
function tj_visitor_hue( $visitor ) {
	return hexdec( substr( $visitor, 0, 4 ) ) % 360;
}

function tj_admin_styles() {
	?>
	<style>
		.tj-wrap{--tj-ink:#1d2327;--tj-muted:#646970;--tj-line:#e2e4e7;--tj-soft:#f6f7f7;--tj-blue:#2271b1;--tj-blue-soft:#e8f1fa;--tj-red:#d63638;--tj-red-soft:#fcebeb;--tj-amber:#b26200;--tj-amber-soft:#fcf0e1;--tj-green:#00a32a;--tj-green-soft:#e6f6ea;--tj-violet:#7c3aed;--tj-violet-soft:#f1ebfe;max-width:1280px;color:var(--tj-ink)}
		.tj-wrap *{box-sizing:border-box}
		.tj-head{display:flex;align-items:center;gap:14px;margin:18px 0 18px}
		.tj-logo{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#2271b1,#7c3aed);color:#fff;box-shadow:0 4px 12px rgba(34,113,177,.25)}
		.tj-logo .dashicons{font-size:24px;width:24px;height:24px}
		.tj-head h1{margin:0;padding:0;font-size:23px;font-weight:600;line-height:1.2}
		.tj-head p{margin:2px 0 0;color:var(--tj-muted)}
		.tj-card{background:#fff;border:1px solid var(--tj-line);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.tj-toolbar{display:flex;flex-wrap:wrap;gap:12px 18px;align-items:center;padding:12px 16px;margin-bottom:16px}
		.tj-field{display:flex;align-items:center;gap:8px;font-weight:600;color:var(--tj-muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
		.tj-field select{min-width:200px;border-radius:8px;border-color:var(--tj-line);font-size:14px;text-transform:none;letter-spacing:0;font-weight:500;color:var(--tj-ink)}
		.tj-seg{display:inline-flex;background:var(--tj-soft);border:1px solid var(--tj-line);border-radius:9px;padding:3px}
		.tj-seg a{padding:5px 12px;border-radius:7px;text-decoration:none;color:var(--tj-muted);font-weight:500;font-size:13px}
		.tj-seg a:hover{color:var(--tj-ink)}
		.tj-seg a.on{background:#fff;color:var(--tj-ink);box-shadow:0 1px 2px rgba(0,0,0,.08)}
		.tj-chip-toggle{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;border:1px solid var(--tj-line);cursor:pointer;font-size:13px;user-select:none}
		.tj-chip-toggle input{margin:0}
		.tj-chip-toggle.on{background:var(--tj-red-soft);border-color:#f3b7b8;color:var(--tj-red);font-weight:600}
		.tj-filter-pill{display:inline-flex;align-items:center;gap:8px;padding:5px 6px 5px 12px;border-radius:20px;background:var(--tj-blue-soft);color:var(--tj-blue);font-size:13px;font-weight:500}
		.tj-filter-pill a{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:#fff;text-decoration:none;color:var(--tj-blue);font-size:14px;line-height:1}
		.tj-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:0 0 22px}
		.tj-stat{padding:16px;display:flex;gap:12px;align-items:center}
		.tj-ico{flex:none;width:40px;height:40px;border-radius:10px;display:grid;place-items:center}
		.tj-ico .dashicons{font-size:20px;width:20px;height:20px}
		.tj-stat b{display:block;font-size:24px;line-height:1.15;font-weight:700;font-variant-numeric:tabular-nums}
		.tj-stat span.l{color:var(--tj-muted);font-size:12.5px}
		.c-blue{background:var(--tj-blue-soft);color:var(--tj-blue)}.c-violet{background:var(--tj-violet-soft);color:var(--tj-violet)}.c-green{background:var(--tj-green-soft);color:var(--tj-green)}.c-amber{background:var(--tj-amber-soft);color:var(--tj-amber)}.c-red{background:var(--tj-red-soft);color:var(--tj-red)}
		.tj-section-title{display:flex;justify-content:space-between;align-items:baseline;margin:0 0 10px}
		.tj-section-title h2{margin:0;font-size:15px;font-weight:600}
		.tj-section-title span{color:var(--tj-muted);font-size:12.5px}
		.tj-visit{margin-bottom:10px;overflow:hidden;transition:box-shadow .15s,border-color .15s}
		.tj-visit:hover{border-color:#c8ccd1;box-shadow:0 3px 10px rgba(0,0,0,.06)}
		.tj-visit.has-aff{border-left:4px solid var(--tj-red)}
		.tj-visit>summary{list-style:none;cursor:pointer;padding:14px 16px;display:flex;gap:14px;align-items:center}
		.tj-visit>summary::-webkit-details-marker{display:none}
		.tj-visit[open]>summary{border-bottom:1px solid var(--tj-line);background:#fcfcfd}
		.tj-avatar{flex:none;width:38px;height:38px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:12px;font-family:monospace;text-decoration:none}
		.tj-avatar:hover,.tj-avatar:focus{color:#fff;box-shadow:0 0 0 3px rgba(0,0,0,.08)}
		.tj-main{flex:1;min-width:0}
		.tj-line1{display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}
		.tj-line1 strong{font-size:14px}
		.tj-line1 a{font-size:12.5px;text-decoration:none}
		.tj-line2{display:flex;gap:6px 16px;flex-wrap:wrap;margin-top:4px;color:var(--tj-muted);font-size:12.5px;align-items:center}
		.tj-line2 .dashicons{font-size:15px;width:15px;height:15px;vertical-align:-3px;margin-right:2px}
		.tj-line2 code{background:var(--tj-soft);border-radius:4px;padding:1px 6px;font-size:12px}
		.tj-right{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
		.tj-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;background:var(--tj-soft);color:#3c434a;font-size:12px;font-weight:500;white-space:nowrap}
		.tj-badge .dashicons{font-size:14px;width:14px;height:14px}
		.tj-badge.aff{background:var(--tj-red);color:#fff}
		.tj-badge.out{background:var(--tj-amber-soft);color:var(--tj-amber)}
		.tj-badge.rev{background:var(--tj-green-soft);color:var(--tj-green)}
		.tj-badge.wg{background:var(--tj-violet-soft);color:var(--tj-violet)}
		.tj-badge.time{background:transparent;color:var(--tj-muted);font-variant-numeric:tabular-nums}
		.tj-chev{color:#a7aaad;transition:transform .15s}
		.tj-visit[open] .tj-chev{transform:rotate(180deg)}
		.tj-steps{margin:0;padding:14px 18px 8px 18px;list-style:none;position:relative}
		.tj-steps li{position:relative;display:flex;gap:12px;align-items:flex-start;padding:0 0 14px 0;margin:0}
		.tj-steps li:not(:last-child)::before{content:"";position:absolute;left:73px;top:28px;bottom:2px;width:2px;background:var(--tj-line)}
		.tj-steps .tj-t{width:50px;flex:none;color:var(--tj-muted);font-variant-numeric:tabular-nums;font-size:12px;padding-top:6px;text-align:right}
		.tj-dot{flex:none;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;position:relative;z-index:1}
		.tj-dot .dashicons{font-size:15px;width:15px;height:15px}
		.tj-step-body{padding-top:4px;min-width:0;line-height:1.5}
		.tj-step-body code{word-break:break-all;background:var(--tj-soft);border-radius:4px;padding:1px 6px}
		.tj-step-body .tj-muted{font-size:12.5px}
		.tj-muted{color:var(--tj-muted)}
		.tj-empty{padding:40px 20px;text-align:center;color:var(--tj-muted)}
		.tj-empty .dashicons{font-size:40px;width:40px;height:40px;color:#c3c4c7}
		.tj-pages{margin-top:16px;display:flex;justify-content:center;gap:4px}
		.tj-pages .page-numbers{display:inline-block;min-width:34px;padding:6px 10px;text-align:center;border-radius:8px;background:#fff;border:1px solid var(--tj-line);text-decoration:none;color:var(--tj-ink)}
		.tj-pages .page-numbers.current{background:var(--tj-blue);border-color:var(--tj-blue);color:#fff}
		.tj-sites{display:grid;gap:14px}
		.tj-site{padding:18px}
		.tj-site-head{display:flex;gap:12px;align-items:center;margin-bottom:14px}
		.tj-site-head .tj-ico{border-radius:50%}
		.tj-site-head h3{margin:0;font-size:15px}
		.tj-site-head .tj-del{margin-left:auto;font-size:12.5px;color:var(--tj-red);display:flex;gap:6px;align-items:center}
		.tj-site-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1.1fr);gap:14px}
		.tj-site-grid label{display:block;font-size:12px;font-weight:600;color:var(--tj-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px}
		.tj-site-grid input[type=text],.tj-site-grid textarea{width:100%;border-radius:8px;border-color:var(--tj-line)}
		.tj-site-grid textarea{min-height:96px}
		.tj-codebox{position:relative;margin-top:14px}
		.tj-wrap textarea.tj-code,.tj-wrap textarea.tj-code[readonly]{width:100%;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.6;background:#1e1e2e;color:#cdd6f4;border:0;border-radius:10px;padding:12px 120px 12px 14px;resize:none;box-shadow:none}
		.tj-wrap textarea.tj-code:focus{box-shadow:0 0 0 2px #7c3aed}
		.tj-copy{position:absolute;top:8px;right:8px}
		.tj-copy.done{background:var(--tj-green)!important;border-color:var(--tj-green)!important;color:#fff!important}
		.tj-new{padding:18px;border-style:dashed;background:#fcfcfd}
		.tj-new .tj-site-grid{grid-template-columns:1fr 1fr 1.1fr;align-items:end}
		.tj-foot{display:flex;flex-wrap:wrap;gap:12px 24px;align-items:center;padding:16px 18px;margin-top:14px}
		.tj-foot input[type=number]{width:80px;border-radius:8px}
		.tj-help{margin:14px 2px;color:var(--tj-muted);font-size:12.5px;max-width:900px}
		.tj-intro{padding:14px 18px;margin-bottom:16px;display:flex;gap:12px;align-items:flex-start;background:var(--tj-blue-soft);border-color:#c5d9ed}
		.tj-intro .dashicons{color:var(--tj-blue);margin-top:1px}
		.tj-intro p{margin:0}
		@media (max-width:900px){.tj-site-grid,.tj-new .tj-site-grid{grid-template-columns:1fr}.tj-visit>summary{flex-wrap:wrap;align-items:flex-start}.tj-main{flex:1 1 0}.tj-right{flex:1 1 100%;justify-content:flex-start;padding-left:52px}.tj-stat{padding:12px}.tj-field select{min-width:0}}
	</style>
	<?php
}

function tj_render_journeys() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;

	$sites = tj_get_sites();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$site_id  = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : (int) key( $sites );
	$range    = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 1;
	$aff_only = ! empty( $_GET['aff'] );
	$visitor  = isset( $_GET['visitor'] ) && preg_match( '/^[a-f0-9]{16}$/', wp_unslash( $_GET['visitor'] ) ) ? wp_unslash( $_GET['visitor'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- regex-validated.
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable
	if ( ! in_array( $range, array( 1, 7, 30, 90 ), true ) ) {
		$range = 1;
	}
	$per_page = 30;

	tj_admin_styles();
	?>
	<div class="wrap tj-wrap">
		<div class="tj-head">
			<div class="tj-logo"><span class="dashicons dashicons-chart-line"></span></div>
			<div><h1>Visitor Journeys</h1><p>Every page and click, visit by visitor.</p></div>
		</div>
	<?php

	if ( ! $sites ) {
		echo '<div class="tj-card tj-empty"><span class="dashicons dashicons-admin-site-alt3"></span><p>No sites yet. Add them under <a href="' . esc_url( admin_url( 'admin.php?page=tehoa-journeys-settings' ) ) . '">Sites &amp; Code</a>.</p></div></div>';
		return;
	}
	if ( ! isset( $sites[ $site_id ] ) ) {
		$site_id = (int) key( $sites );
	}

	$base = array(
		'page'    => 'tehoa-journeys',
		'site'    => $site_id,
		'range'   => $range,
		'aff'     => $aff_only ? 1 : false,
		'visitor' => $visitor ? $visitor : false,
	);
	?>
	<form method="get" class="tj-card tj-toolbar">
		<input type="hidden" name="page" value="tehoa-journeys">
		<input type="hidden" name="range" value="<?php echo (int) $range; ?>">
		<label class="tj-field">Site
			<select name="site" onchange="this.form.submit()">
				<?php foreach ( $sites as $id => $s ) : ?>
					<option value="<?php echo (int) $id; ?>" <?php selected( $id, $site_id ); ?>><?php echo esc_html( $s['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<div class="tj-seg" role="group" aria-label="Period">
			<?php foreach ( array( 1 => '24 hours', 7 => '7 days', 30 => '30 days', 90 => '90 days' ) as $val => $label ) : ?>
				<a class="<?php echo $val === $range ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $base, array( 'range' => $val ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<label class="tj-chip-toggle <?php echo $aff_only ? 'on' : ''; ?>"><input type="checkbox" name="aff" value="1" <?php checked( $aff_only ); ?> onchange="this.form.submit()"> Only visits with an affiliate click</label>
		<?php if ( $visitor ) : ?>
			<input type="hidden" name="visitor" value="<?php echo esc_attr( $visitor ); ?>">
			<span class="tj-filter-pill">Visitor <?php echo esc_html( substr( $visitor, 0, 6 ) ); ?> <a href="<?php echo esc_url( remove_query_arg( array( 'visitor', 'paged' ) ) ); ?>" title="Clear filter">×</a></span>
		<?php endif; ?>
		<noscript><button class="button">Show</button></noscript>
	</form>
	<?php

	$visits_t = tj_table( 'visits' );
	$events_t = tj_table( 'events' );
	$since    = gmdate( 'Y-m-d H:i:s', time() - $range * DAY_IN_SECONDS );

	$where = $wpdb->prepare( 'site_id = %d AND last_at >= %s', $site_id, $since );
	if ( $aff_only ) {
		$where .= ' AND aff_clicks > 0';
	}
	if ( $visitor ) {
		$where .= $wpdb->prepare( ' AND visitor = %s', $visitor );
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from prepare() above.
	$stats = $wpdb->get_row(
		"SELECT COUNT(*) AS visits, COUNT(DISTINCT visitor) AS visitors, COALESCE(SUM(pageviews),0) AS pageviews,
			COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(aff_clicks),0) AS aff_clicks, COALESCE(SUM(aff_clicks > 0),0) AS aff_visits
		FROM $visits_t WHERE $where"
	);
	$rows  = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $visits_t WHERE $where ORDER BY last_at DESC LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page )
	);
	// phpcs:enable

	$aff_rate = $stats && $stats->visits ? round( 100 * $stats->aff_visits / $stats->visits, 1 ) : 0;
	$cards    = array(
		array( 'c-blue', 'dashicons-backup', number_format_i18n( (int) $stats->visits ), 'Visits' ),
		array( 'c-violet', 'dashicons-groups', number_format_i18n( (int) $stats->visitors ), 'Visitors' ),
		array( 'c-green', 'dashicons-media-document', number_format_i18n( (int) $stats->pageviews ), 'Pageviews' ),
		array( 'c-amber', 'dashicons-admin-links', number_format_i18n( (int) $stats->clicks ), 'Clicks' ),
		array( 'c-red', 'dashicons-money-alt', number_format_i18n( (int) $stats->aff_clicks ), 'Affiliate clicks' ),
		array( 'c-red', 'dashicons-chart-pie', $aff_rate . '%', 'Visits with an affiliate click' ),
	);
	?>
	<div class="tj-stats">
		<?php foreach ( $cards as $c ) : ?>
			<div class="tj-card tj-stat"><div class="tj-ico <?php echo esc_attr( $c[0] ); ?>"><span class="dashicons <?php echo esc_attr( $c[1] ); ?>"></span></div><div><b><?php echo esc_html( $c[2] ); ?></b><span class="l"><?php echo esc_html( $c[3] ); ?></span></div></div>
		<?php endforeach; ?>
	</div>
	<?php

	if ( ! $rows ) {
		echo '<div class="tj-card tj-empty"><span class="dashicons dashicons-visibility"></span><p>No visits in the selected period.<br>If the site was just connected, check that its code from Sites &amp; Code has been added to the site.</p></div></div>';
		return;
	}

	// Load all events for the visits on this page in one query.
	$ids          = wp_list_pluck( $rows, 'visit_id' );
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
	$events       = $wpdb->get_results(
		$wpdb->prepare( "SELECT visit_id, type, kind, path, title, target, label, widget, created_at FROM $events_t WHERE visit_id IN ($placeholders) ORDER BY id ASC", $ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders -- placeholders built above.
	);
	$by_visit     = array();
	foreach ( $events as $e ) {
		$by_visit[ $e->visit_id ][] = $e;
	}

	$total_pages = (int) ceil( $stats->visits / $per_page );
	$first       = ( $paged - 1 ) * $per_page + 1;
	?>
	<div class="tj-section-title"><h2>Visits</h2><span><?php echo esc_html( sprintf( 'Showing %d–%d of %s · newest first · click a visit to see its path', $first, $first + count( $rows ) - 1, number_format_i18n( (int) $stats->visits ) ) ); ?></span></div>
	<?php
	foreach ( $rows as $v ) {
		$start = strtotime( $v->started_at . ' UTC' );
		$secs  = strtotime( $v->last_at . ' UTC' ) - $start;
		$hue   = tj_visitor_hue( $v->visitor );
		$short = substr( $v->visitor, 0, 6 );
		$vlink = add_query_arg( array( 'visitor' => $v->visitor, 'paged' => false, 'range' => 90 ) );
		?>
		<details class="tj-card tj-visit <?php echo $v->aff_clicks ? 'has-aff' : ''; ?>">
			<summary>
				<a class="tj-avatar" style="background:hsl(<?php echo (int) $hue; ?>,55%,48%)" href="<?php echo esc_url( $vlink ); ?>" title="All visits by this visitor"><?php echo esc_html( substr( $short, 0, 2 ) ); ?></a>
				<div class="tj-main">
					<div class="tj-line1">
						<strong><?php echo esc_html( tj_fmt_time( $v->started_at ) ); ?></strong>
						<a href="<?php echo esc_url( $vlink ); ?>" title="All visits by this visitor">Visitor <?php echo esc_html( $short ); ?></a>
					</div>
					<div class="tj-line2">
						<span><span class="dashicons <?php echo esc_attr( tj_device_icon( (int) $v->device ) ); ?>"></span><?php echo esc_html( tj_device_label( (int) $v->device ) ); ?></span>
						<span><span class="dashicons dashicons-randomize"></span>Source: <?php echo esc_html( $v->referrer ? $v->referrer : 'direct' ); ?></span>
						<span><span class="dashicons dashicons-migrate"></span>Entry: <code><?php echo esc_html( rawurldecode( $v->entry_path ) ); ?></code></span>
					</div>
				</div>
				<div class="tj-right">
					<span class="tj-badge"><span class="dashicons dashicons-media-document"></span><?php echo (int) $v->pageviews; ?> <?php echo 1 === (int) $v->pageviews ? 'page' : 'pages'; ?></span>
					<span class="tj-badge"><span class="dashicons dashicons-admin-links"></span><?php echo (int) $v->clicks; ?> <?php echo 1 === (int) $v->clicks ? 'click' : 'clicks'; ?></span>
					<?php if ( $v->aff_clicks ) : ?>
						<span class="tj-badge aff"><span class="dashicons dashicons-money-alt"></span><?php echo (int) $v->aff_clicks; ?> affiliate</span>
					<?php endif; ?>
					<span class="tj-badge time"><span class="dashicons dashicons-clock"></span><?php echo esc_html( gmdate( $secs >= HOUR_IN_SECONDS ? 'G\h i\m' : 'i:s', max( 0, $secs ) ) ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2 tj-chev"></span>
				</div>
			</summary>
			<ol class="tj-steps">
				<?php
				foreach ( isset( $by_visit[ $v->visit_id ] ) ? $by_visit[ $v->visit_id ] : array() as $e ) {
					tj_render_step( $e, $start );
				}
				?>
			</ol>
		</details>
		<?php
	}

	if ( $total_pages > 1 ) {
		echo '<div class="tj-pages">';
		echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated markup.
			array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => '‹',
				'next_text' => '›',
			)
		);
		echo '</div>';
	}
	echo '</div>';
}

function tj_render_step( $e, $start ) {
	$offset = max( 0, strtotime( $e->created_at . ' UTC' ) - $start );
	$offset = $offset >= HOUR_IN_SECONDS ? gmdate( 'G:i:s', $offset ) : gmdate( 'i:s', $offset );
	$kind   = (int) $e->kind;

	if ( 1 === (int) $e->type ) {
		$icon  = 'dashicons-media-document';
		$color = 'c-blue';
		$html  = '<strong>Viewed page</strong> <code>' . esc_html( rawurldecode( $e->path ) ) . '</code>';
		if ( '' !== $e->title ) {
			$html .= '<br><span class="tj-muted">' . esc_html( $e->title ) . '</span>';
		}
	} else {
		$icon  = 3 === $kind ? 'dashicons-money-alt' : ( 2 === $kind ? 'dashicons-external' : ( 4 === $kind ? 'dashicons-star-filled' : ( 0 === $kind ? 'dashicons-marker' : 'dashicons-admin-links' ) ) );
		$color = 3 === $kind ? 'c-red' : ( 2 === $kind ? 'c-amber' : ( 4 === $kind ? 'c-green' : 'c-violet' ) );
		$label = '' !== $e->label ? '“' . esc_html( $e->label ) . '”' : '<span class="tj-muted">(no text)</span>';
		$html  = '<strong>' . ( 0 === $kind ? 'Interacted with' : 'Clicked' ) . '</strong> ' . $label;
		if ( 3 === $kind ) {
			$html .= ' <span class="tj-badge aff">affiliate</span>';
		} elseif ( 2 === $kind ) {
			$html .= ' <span class="tj-badge out">external link</span>';
		} elseif ( 4 === $kind ) {
			$html .= ' <span class="tj-badge rev">review</span>';
		}
		if ( isset( $e->widget ) && '' !== $e->widget ) {
			$html .= ' <span class="tj-badge wg"><span class="dashicons dashicons-screenoptions"></span>' . esc_html( tj_source_label( $e->widget ) ) . '</span>';
		}
		if ( '' !== $e->target ) {
			$shown = 1 === $kind ? rawurldecode( $e->target ) : $e->target;
			$html .= '<br>→ <code>' . esc_html( $shown ) . '</code>';
		}
		$html .= ' <span class="tj-muted">on ' . esc_html( rawurldecode( $e->path ) ) . '</span>';
	}

	printf(
		'<li><span class="tj-t">+%s</span><span class="tj-dot %s"><span class="dashicons %s"></span></span><span class="tj-step-body">%s</span></li>',
		esc_html( $offset ),
		esc_attr( $color ),
		esc_attr( $icon ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every dynamic part escaped above.
	);
}

function tj_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$sites = tj_get_sites();
	tj_admin_styles();
	?>
	<div class="wrap tj-wrap">
		<div class="tj-head">
			<div class="tj-logo"><span class="dashicons dashicons-admin-site-alt3"></span></div>
			<div><h1>Sites &amp; Tracking Code</h1><p>Tracked sites, their affiliate rules and the code to add to each one.</p></div>
		</div>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
		<?php endif; ?>
		<div class="tj-card tj-intro"><span class="dashicons dashicons-info-outline"></span><p>Add each site's code to that site's <code>&lt;head&gt;</code> (or just before <code>&lt;/body&gt;</code>). The script is about 1 KB, loads <code>async</code> and does not block page rendering. It sets no cookies.</p></div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tj_save_settings">
			<?php wp_nonce_field( 'tj_save_settings' ); ?>
			<div class="tj-sites">
			<?php foreach ( $sites as $id => $s ) : ?>
				<div class="tj-card tj-site">
					<div class="tj-site-head">
						<div class="tj-ico c-blue"><span class="dashicons dashicons-admin-site"></span></div>
						<h3><?php echo esc_html( $s['name'] ); ?></h3>
						<label class="tj-del"><input type="checkbox" name="sites[<?php echo (int) $id; ?>][delete]" value="1"> Remove site</label>
					</div>
					<div class="tj-site-grid">
						<div><label for="tj-name-<?php echo (int) $id; ?>">Name</label><input id="tj-name-<?php echo (int) $id; ?>" type="text" name="sites[<?php echo (int) $id; ?>][name]" value="<?php echo esc_attr( $s['name'] ); ?>"></div>
						<div><label for="tj-domain-<?php echo (int) $id; ?>">Domain</label><input id="tj-domain-<?php echo (int) $id; ?>" type="text" name="sites[<?php echo (int) $id; ?>][domain]" value="<?php echo esc_attr( $s['domain'] ); ?>"></div>
						<div><label for="tj-pat-<?php echo (int) $id; ?>">Affiliate paths (one per line)</label><textarea id="tj-pat-<?php echo (int) $id; ?>" name="sites[<?php echo (int) $id; ?>][patterns]" rows="3"><?php echo esc_textarea( $s['patterns'] ); ?></textarea>
						<label for="tj-rev-<?php echo (int) $id; ?>" style="margin-top:10px">Review page paths (one per line)</label><textarea id="tj-rev-<?php echo (int) $id; ?>" name="sites[<?php echo (int) $id; ?>][reviews]" rows="3"><?php echo esc_textarea( tj_review_patterns( $s ) ); ?></textarea></div>
					</div>
					<div class="tj-codebox">
						<textarea class="tj-code" rows="3" readonly onclick="this.select()"><?php echo esc_textarea( tj_snippet( $s ) ); ?></textarea>
						<button type="button" class="button button-primary tj-copy" onclick="var b=this,t=b.previousElementSibling;t.select();(navigator.clipboard?navigator.clipboard.writeText(t.value):Promise.reject()).catch(function(){document.execCommand('copy')}).finally(function(){b.textContent='Copied';b.classList.add('done');setTimeout(function(){b.textContent='Copy code';b.classList.remove('done')},1600)})">Copy code</button>
					</div>
				</div>
			<?php endforeach; ?>
				<div class="tj-card tj-new">
					<div class="tj-site-head"><div class="tj-ico c-green"><span class="dashicons dashicons-plus-alt2"></span></div><h3>Add a site</h3></div>
					<div class="tj-site-grid">
						<div><label for="tj-new-name">Name</label><input id="tj-new-name" type="text" name="new_site[name]" placeholder="New site"></div>
						<div><label for="tj-new-domain">Domain</label><input id="tj-new-domain" type="text" name="new_site[domain]" placeholder="example.fi"></div>
						<div class="tj-muted">The code will appear after saving.</div>
					</div>
				</div>
			</div>
			<div class="tj-card tj-foot">
				<label>Keep data for <input type="number" name="retention" min="1" max="730" value="<?php echo (int) get_option( 'tj_retention_days', 90 ); ?>"> days</label>
				<span class="tj-muted">Older visits are deleted automatically.</span>
				<span style="margin-left:auto"><?php submit_button( 'Save changes', 'primary', 'submit', false ); ?></span>
			</div>
			<p class="tj-help">A click counts as affiliate if the link has <code>rel="sponsored"</code>, if its path contains one of the affiliate paths above (e.g. <code>/go/</code>), or if an external link carries a typical affiliate parameter (<code>btag</code>, <code>affid</code>, <code>clickid</code>...). A click counts as a review click if the link goes to a page under one of the review page paths.</p>
		</form>
	</div>
	<?php
}
function tj_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden', 403 );
	}
	check_admin_referer( 'tj_save_settings' );

	$current = tj_get_sites();
	$posted  = isset( $_POST['sites'] ) && is_array( $_POST['sites'] ) ? wp_unslash( $_POST['sites'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.
	$sites   = array();

	foreach ( $current as $id => $s ) {
		$p = isset( $posted[ $id ] ) && is_array( $posted[ $id ] ) ? $posted[ $id ] : array();
		if ( ! empty( $p['delete'] ) ) {
			continue;
		}
		$domain       = isset( $p['domain'] ) ? tj_normalize_domain( $p['domain'] ) : $s['domain'];
		$sites[ $id ] = array(
			'name'     => isset( $p['name'] ) && '' !== trim( $p['name'] ) ? sanitize_text_field( $p['name'] ) : $s['name'],
			'domain'   => $domain ? $domain : $s['domain'],
			'key'      => $s['key'],
			'patterns' => isset( $p['patterns'] ) ? sanitize_textarea_field( $p['patterns'] ) : $s['patterns'],
			'reviews'  => isset( $p['reviews'] ) ? sanitize_textarea_field( $p['reviews'] ) : tj_review_patterns( $s ),
		);
	}

	// Remember the highest id ever used so ids are never reused (old data can't attach to a different site).
	$last_id = (int) max( array_merge( array_keys( $current ), array( (int) get_option( 'tj_last_site_id', 0 ) ) ) );

	$new = isset( $_POST['new_site'] ) && is_array( $_POST['new_site'] ) ? wp_unslash( $_POST['new_site'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
	$new_domain = isset( $new['domain'] ) ? tj_normalize_domain( $new['domain'] ) : '';
	if ( $new_domain && ! in_array( $new_domain, wp_list_pluck( $sites, 'domain' ), true ) ) {
		$next_id           = ++$last_id;
		$sites[ $next_id ] = array(
			'name'     => ! empty( $new['name'] ) ? sanitize_text_field( $new['name'] ) : $new_domain,
			'domain'   => $new_domain,
			'key'      => wp_generate_password( 20, false ),
			'patterns' => tj_default_patterns(),
			'reviews'  => tj_default_review_patterns(),
		);
	}
	update_option( 'tj_last_site_id', $last_id, false );

	update_option( 'tj_sites', $sites, false );
	if ( isset( $_POST['retention'] ) ) {
		update_option( 'tj_retention_days', min( 730, max( 1, absint( $_POST['retention'] ) ) ), false );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=tehoa-journeys-settings&saved=1' ) );
	exit;
}

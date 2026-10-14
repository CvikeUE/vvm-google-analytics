<?php
/**
 * "Widgets & Sources" admin page: which theme widgets and page areas get interactions and
 * affiliate clicks, and whether affiliate clicks start on review pages, in widgets or elsewhere.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tj_render_widgets() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;

	$sites = tj_get_sites();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$site_id = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : (int) key( $sites );
	$range   = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30;
	// phpcs:enable
	if ( ! in_array( $range, array( 1, 7, 30, 90 ), true ) ) {
		$range = 30;
	}

	tj_admin_styles();
	tj_widgets_styles();
	?>
	<div class="wrap tj-wrap">
		<div class="tj-head">
			<div class="tj-logo"><span class="dashicons dashicons-screenoptions"></span></div>
			<div><h1>Widgets &amp; Sources</h1><p>Which widgets people use, and where affiliate clicks come from.</p></div>
		</div>
	<?php
	if ( ! $sites ) {
		echo '<div class="tj-card tj-empty"><span class="dashicons dashicons-admin-site-alt3"></span><p>No sites yet. Add them under <a href="' . esc_url( admin_url( 'admin.php?page=tehoa-journeys-settings' ) ) . '">Sites &amp; Code</a>.</p></div></div>';
		return;
	}
	if ( ! isset( $sites[ $site_id ] ) ) {
		$site_id = (int) key( $sites );
	}
	?>
	<form method="get" class="tj-card tj-toolbar">
		<input type="hidden" name="page" value="tehoa-journeys-widgets">
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
				<a class="<?php echo $val === $range ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'tehoa-journeys-widgets', 'site' => $site_id, 'range' => $val ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<noscript><button class="button">Show</button></noscript>
	</form>
	<?php

	$events_t = tj_table( 'events' );
	$visits_t = tj_table( 'visits' );
	$since_ts = time() - $range * DAY_IN_SECONDS;
	$since    = gmdate( 'Y-m-d H:i:s', $since_ts );
	$started  = (string) get_option( 'tj_widgets_since', '' );
	if ( $started && strtotime( $started . ' UTC' ) > $since_ts ) {
		echo '<div class="tj-card tj-intro"><span class="dashicons dashicons-info-outline"></span><p>Widget tracking started on <strong>' . esc_html( tj_fmt_time( $started ) ) . '</strong>, so this page only includes clicks from that moment on.</p></div>';
	}

	$widget_keys = tj_widget_keys();
	$in_widgets  = implode( ',', array_map( function ( $k ) { return "'" . esc_sql( $k ) . "'"; }, $widget_keys ) );
	$bucket_sql  = "CASE WHEN e.page_type = 2 THEN 'review' WHEN e.widget IN ($in_widgets) THEN 'widget' ELSE 'other' END";
	// Only clicks sent by the widget-aware tracker carry a page type.
	$base = $wpdb->prepare( "FROM $events_t e JOIN $visits_t v ON v.visit_id = e.visit_id WHERE e.site_id = %d AND e.type = 2 AND e.page_type > 0 AND e.created_at >= %s", $site_id, $since ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- built from prepare() and fixed keys above.
	$totals  = $wpdb->get_row( "SELECT COUNT(*) AS clicks, COUNT(DISTINCT v.visitor) AS visitors $base AND e.kind = 3" );
	$buckets = $wpdb->get_results( "SELECT $bucket_sql AS bucket, COUNT(*) AS clicks, COUNT(DISTINCT v.visitor) AS visitors $base AND e.kind = 3 GROUP BY bucket", OBJECT_K );
	$by_src  = $wpdb->get_results( "SELECT $bucket_sql AS bucket, e.widget, COUNT(*) AS clicks, COUNT(DISTINCT v.visitor) AS visitors $base AND e.kind = 3 GROUP BY bucket, e.widget ORDER BY clicks DESC" );
	$usage   = $wpdb->get_results(
		"SELECT e.widget, SUM(e.kind = 3) AS aff, SUM(e.kind = 4) AS rev, SUM(e.kind NOT IN (3,4)) AS other, COUNT(*) AS total,
			COUNT(DISTINCT v.visitor) AS visitors, COUNT(DISTINCT e.path) AS pages
		$base GROUP BY e.widget ORDER BY aff DESC, total DESC"
	);
	$tops    = $wpdb->get_results( "SELECT e.widget, e.kind, e.label, e.target, COUNT(*) AS c $base GROUP BY e.widget, e.kind, e.label, e.target ORDER BY c DESC LIMIT 3000" );
	// phpcs:enable

	$total_aff = (int) $totals->clicks;
	$meta      = array(
		'review' => array( 'From review pages', 'Affiliate clicks made while on a review page (hero button, comparison table, sidebar or widgets on that page).', 'dashicons-star-filled', 'c-green' ),
		'widget' => array( 'From widgets', 'Affiliate clicks in theme widgets on any page that is not a review page.', 'dashicons-screenoptions', 'c-violet' ),
		'other'  => array( 'From other parts', 'Sidebar, page hero, exit popup, menus and text links outside of review pages.', 'dashicons-layout', 'c-amber' ),
	);
	$colors    = array( 'review' => '#00a32a', 'widget' => '#7c3aed', 'other' => '#dba617' );
	?>
	<div class="tj-section-title"><h2>Where affiliate clicks come from</h2><span><?php echo esc_html( sprintf( '%s affiliate clicks by %s visitors', number_format_i18n( $total_aff ), number_format_i18n( (int) $totals->visitors ) ) ); ?></span></div>
	<div class="tj-buckets">
		<?php
		foreach ( $meta as $key => $m ) :
			$b     = isset( $buckets[ $key ] ) ? $buckets[ $key ] : null;
			$c     = $b ? (int) $b->clicks : 0;
			$share = $total_aff ? round( 100 * $c / $total_aff, 1 ) : 0;
			?>
			<div class="tj-card tj-bucket">
				<div class="tj-bucket-top"><div class="tj-ico <?php echo esc_attr( $m[3] ); ?>"><span class="dashicons <?php echo esc_attr( $m[2] ); ?>"></span></div><strong><?php echo esc_html( $m[0] ); ?></strong><span class="tj-share"><?php echo esc_html( $share ); ?>%</span></div>
				<div class="tj-bucket-nums"><div><b><?php echo esc_html( number_format_i18n( $c ) ); ?></b><span>affiliate clicks</span></div><div><b><?php echo esc_html( number_format_i18n( $b ? (int) $b->visitors : 0 ) ); ?></b><span>visitors</span></div></div>
				<p class="tj-muted"><?php echo esc_html( $m[1] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( $total_aff ) : ?>
		<div class="tj-stack" role="img" aria-label="Share of affiliate clicks by source">
			<?php
			foreach ( $meta as $key => $m ) {
				$c = isset( $buckets[ $key ] ) ? (int) $buckets[ $key ]->clicks : 0;
				if ( $c ) {
					printf( '<span style="width:%s%%;background:%s" title="%s: %s"></span>', esc_attr( round( 100 * $c / $total_aff, 2 ) ), esc_attr( $colors[ $key ] ), esc_attr( $m[0] ), esc_attr( number_format_i18n( $c ) ) );
				}
			}
			?>
		</div>
		<div class="tj-card tj-table-card">
			<table class="tj-table">
				<thead><tr><th>Source</th><th>Where</th><th class="num">Affiliate clicks</th><th class="num">Visitors</th><th class="bar-col">Share</th></tr></thead>
				<tbody>
				<?php
				foreach ( $by_src as $r ) :
					$share = round( 100 * $r->clicks / $total_aff, 1 );
					?>
					<tr>
						<td><strong><?php echo esc_html( tj_source_label( $r->widget ) ); ?></strong></td>
						<td><span class="tj-badge" style="background:<?php echo esc_attr( $colors[ $r->bucket ] ); ?>1f;color:<?php echo esc_attr( $colors[ $r->bucket ] ); ?>"><?php echo esc_html( 'review' === $r->bucket ? 'Review page' : ( 'widget' === $r->bucket ? 'Widget' : 'Other part' ) ); ?></span></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $r->clicks ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $r->visitors ) ); ?></td>
						<td class="bar-col"><span class="tj-bar"><span style="width:<?php echo esc_attr( $share ); ?>%;background:<?php echo esc_attr( $colors[ $r->bucket ] ); ?>"></span></span><span class="tj-muted"><?php echo esc_html( $share ); ?>%</span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<div class="tj-card tj-empty"><span class="dashicons dashicons-money-alt"></span><p>No affiliate clicks in this period yet.</p></div>
	<?php endif; ?>
	<?php

	// Top clicked items per source, for the expandable rows.
	$top_by = array();
	foreach ( $tops as $t ) {
		if ( ! isset( $top_by[ $t->widget ] ) ) {
			$top_by[ $t->widget ] = array();
		}
		if ( count( $top_by[ $t->widget ] ) < 6 ) {
			$top_by[ $t->widget ][] = $t;
		}
	}
	$widgets = array();
	$areas   = array();
	foreach ( $usage as $u ) {
		if ( in_array( $u->widget, $widget_keys, true ) ) {
			$widgets[] = $u;
		} else {
			$areas[] = $u;
		}
	}

	tj_render_usage_table( 'Widgets', 'Sorted by affiliate clicks · click a row to see what was clicked', $widgets, $top_by, 'No widget interactions in this period yet.' );
	tj_render_usage_table( 'Other page areas', 'Hero, sidebar, comparison table, popups, menus and text links', $areas, $top_by, 'No interactions outside widgets in this period yet.' );
	echo '</div>';
}

function tj_render_usage_table( $title, $hint, array $rows, array $top_by, $empty ) {
	$max = 1;
	foreach ( $rows as $r ) {
		$max = max( $max, (int) $r->total );
	}
	?>
	<div class="tj-section-title" style="margin-top:26px"><h2><?php echo esc_html( $title ); ?></h2><span><?php echo esc_html( $hint ); ?></span></div>
	<?php if ( ! $rows ) : ?>
		<div class="tj-card tj-empty"><span class="dashicons dashicons-screenoptions"></span><p><?php echo esc_html( $empty ); ?></p></div>
		<?php
		return;
	endif;
	?>
	<div class="tj-card tj-table-card">
		<div class="tj-urow tj-uhead"><span>Widget / area</span><span class="num">Affiliate clicks</span><span class="num">Review clicks</span><span class="num">Other interactions</span><span class="num">Total</span><span class="num">Visitors</span><span class="num">Pages</span><span></span></div>
		<?php
		$rank = 0;
		foreach ( $rows as $r ) :
			$rank++;
			$w = (int) $r->total;
			?>
			<details class="tj-urow-wrap">
				<summary class="tj-urow">
					<span class="tj-uname"><span class="tj-rank"><?php echo (int) $rank; ?></span><strong><?php echo esc_html( tj_source_label( $r->widget ) ); ?></strong></span>
					<span class="num" data-l="Affiliate"><span class="tj-pill aff"><?php echo esc_html( number_format_i18n( (int) $r->aff ) ); ?></span></span>
					<span class="num" data-l="Review"><span class="tj-pill rev"><?php echo esc_html( number_format_i18n( (int) $r->rev ) ); ?></span></span>
					<span class="num" data-l="Other"><?php echo esc_html( number_format_i18n( (int) $r->other ) ); ?></span>
					<span class="num tj-total" data-l="Total"><span class="tj-bar"><span style="width:<?php echo esc_attr( round( 100 * $w / $max, 1 ) ); ?>%"></span></span><b><?php echo esc_html( number_format_i18n( $w ) ); ?></b></span>
					<span class="num" data-l="Visitors"><?php echo esc_html( number_format_i18n( (int) $r->visitors ) ); ?></span>
					<span class="num" data-l="Pages"><?php echo esc_html( number_format_i18n( (int) $r->pages ) ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2 tj-chev"></span>
				</summary>
				<div class="tj-utop">
					<div class="tj-muted" style="margin-bottom:6px">Most clicked in this <?php echo in_array( $r->widget, tj_widget_keys(), true ) ? 'widget' : 'area'; ?>:</div>
					<?php foreach ( isset( $top_by[ $r->widget ] ) ? $top_by[ $r->widget ] : array() as $t ) : ?>
						<?php $kind = (int) $t->kind; ?>
						<div class="tj-utop-row">
							<span class="tj-badge <?php echo 3 === $kind ? 'aff' : ( 4 === $kind ? 'rev' : ( 2 === $kind ? 'out' : '' ) ); ?>"><?php echo esc_html( 3 === $kind ? 'affiliate' : ( 4 === $kind ? 'review' : ( 2 === $kind ? 'external' : ( 1 === $kind ? 'link' : 'interaction' ) ) ) ); ?></span>
							<span><?php echo '' !== $t->label ? '“' . esc_html( $t->label ) . '”' : '<span class="tj-muted">(no text)</span>'; ?></span>
							<?php if ( '' !== $t->target ) : ?>
								<code><?php echo esc_html( 1 === $kind || 4 === $kind ? rawurldecode( $t->target ) : $t->target ); ?></code>
							<?php endif; ?>
							<b><?php echo esc_html( number_format_i18n( (int) $t->c ) ); ?>×</b>
						</div>
					<?php endforeach; ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
}

function tj_widgets_styles() {
	?>
	<style>
		.tj-buckets{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:12px}
		.tj-bucket{padding:16px 18px}
		.tj-bucket-top{display:flex;align-items:center;gap:10px}
		.tj-bucket-top strong{font-size:14px}
		.tj-share{margin-left:auto;font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}
		.tj-bucket-nums{display:flex;gap:28px;margin:14px 0 8px}
		.tj-bucket-nums b{display:block;font-size:26px;line-height:1.1;font-variant-numeric:tabular-nums}
		.tj-bucket-nums span{color:var(--tj-muted);font-size:12.5px}
		.tj-bucket p{margin:0;font-size:12.5px}
		.tj-stack{display:flex;height:12px;border-radius:8px;overflow:hidden;background:var(--tj-line);margin:0 0 14px}
		.tj-stack span{display:block;height:100%}
		.tj-table-card{overflow:hidden}
		.tj-table{width:100%;border-collapse:collapse}
		.tj-table th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--tj-muted);font-weight:600;padding:10px 16px;border-bottom:1px solid var(--tj-line);background:#fcfcfd}
		.tj-table td{padding:10px 16px;border-bottom:1px solid #f0f0f1}
		.tj-table tr:last-child td{border-bottom:0}
		.tj-table .num{text-align:right;font-variant-numeric:tabular-nums}
		.tj-table .bar-col{width:28%}
		.tj-table .bar-col .tj-bar{width:calc(100% - 52px);display:inline-block;vertical-align:middle;margin-right:8px}
		.tj-bar{display:block;height:8px;background:var(--tj-soft);border-radius:6px;overflow:hidden}
		.tj-bar>span{display:block;height:100%;background:var(--tj-blue);border-radius:6px}
		.tj-urow{display:grid;grid-template-columns:minmax(180px,2.2fr) repeat(3,minmax(70px,1fr)) minmax(120px,1.6fr) repeat(2,minmax(60px,.8fr)) 24px;gap:10px;align-items:center;padding:11px 16px}
		.tj-uhead{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--tj-muted);font-weight:600;border-bottom:1px solid var(--tj-line);background:#fcfcfd}
		.tj-urow .num{text-align:right;font-variant-numeric:tabular-nums}
		.tj-urow-wrap{border-bottom:1px solid #f0f0f1}
		.tj-urow-wrap:last-child{border-bottom:0}
		.tj-urow-wrap>summary{list-style:none;cursor:pointer}
		.tj-urow-wrap>summary::-webkit-details-marker{display:none}
		.tj-urow-wrap>summary:hover{background:#fafafb}
		.tj-urow-wrap[open] .tj-chev{transform:rotate(180deg)}
		.tj-uname{display:flex;align-items:center;gap:10px;min-width:0}
		.tj-rank{flex:none;width:24px;height:24px;border-radius:50%;background:var(--tj-soft);display:grid;place-items:center;font-size:11.5px;font-weight:600;color:var(--tj-muted)}
		.tj-total{display:flex;align-items:center;gap:8px;justify-content:flex-end}
		.tj-total .tj-bar{flex:1}
		.tj-pill{display:inline-block;min-width:28px;padding:2px 8px;border-radius:12px;font-weight:600;text-align:center}
		.tj-pill.aff{background:var(--tj-red-soft);color:var(--tj-red)}
		.tj-pill.rev{background:var(--tj-green-soft);color:var(--tj-green)}
		.tj-utop{padding:4px 16px 14px 50px}
		.tj-utop-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:4px 0;font-size:13px}
		.tj-utop-row code{background:var(--tj-soft);border-radius:4px;padding:1px 6px;font-size:12px;word-break:break-all}
		.tj-utop-row b{margin-left:auto;font-variant-numeric:tabular-nums}
		@media (max-width:1100px){.tj-uhead{display:none}.tj-urow{grid-template-columns:1fr 1fr 1fr;row-gap:6px}.tj-uname{grid-column:1/-1}.tj-urow .num{text-align:left}.tj-urow .tj-chev{display:none}.tj-urow [data-l]::before{content:attr(data-l) ' ';color:var(--tj-muted);font-size:11.5px;margin-right:4px}.tj-total .tj-bar{display:none}}
	</style>
	<?php
}

/* Tehoa Journeys tracker. ~1.5 KB gz, no dependencies, no cookies. Loaded async; never blocks rendering. */
(function (w, d, n) {
	var s = d.currentScript;
	if (!s || n.webdriver) return;
	var E = s.getAttribute('data-endpoint'), K = s.getAttribute('data-key'), V = '';
	if (!E || !K) return;

	// Optional persistent id: only when the site sets data-persist="1" (use after consent).
	if (s.getAttribute('data-persist') === '1') {
		try {
			V = localStorage.getItem('_tj') || '';
			if (!/^[a-f0-9]{32}$/.test(V)) {
				V = '';
				for (var i = 0; i < 32; i++) V += (Math.random() * 16 | 0).toString(16);
				localStorage.setItem('_tj', V);
			}
		} catch (e) { V = ''; }
	}

	// Tehoa theme widgets and page areas: the nearest matching ancestor of a click names its source.
	// Broad layout areas (third item = 1) only count when no widget or specific area encloses the click,
	// because some widgets re-open the theme's content wrapper inside themselves.
	var Z = [
		['.popup-overlay', 'exit_popup'],
		['.compare-reviews-wrapper', 'compare_drawer'],
		['.sidebar-content', 'sidebar'],
		['#compare-casinos', 'review_compare_table'],
		['.affiliate-buttons-wrapper,.affiliate-buttons', 'affiliate_buttons'],
		['.review-cards-columns', 'review_cards_columns'],
		['.comment-cards-wrapper,.single-comment-card-wrapper', 'comment_cards'],
		['.table-reviews,.table-reviews-wrapper', 'table_list'],
		['.crt-main-wrapper,.table-tabs', 'review_cards_rows'],
		['.faq-wrapper', 'faq'],
		['.how-to-section,.how-to-wrapper', 'how_to'],
		['.tabbed-info-wrapper', 'tabbed_info'],
		['.pros-and-cons-wrapper', 'pros_and_cons'],
		['.casino-table-left-right', 'info_boxes'],
		['.casino-payment-methods', 'payment_methods'],
		['.game-providers-widget', 'game_providers'],
		['.news-container', 'news'],
		['.author-box', 'author_box'],
		['.toc', 'toc'],
		['.video-wrapper', 'video'],
		['.tehoa-gallery', 'gallery'],
		['.icon-cards-wrapper', 'icon_cards'],
		['.pie-chart', 'pie_charts'],
		['.chart-wrapper', 'chart'],
		['.crypto-converter-wrapper', 'crypto_converter'],
		['#single-review-hero', 'review_hero'],
		['.hero-wrapper,.single-hero-wrapper', 'hero', 1],
		['.site-header,header', 'header', 1],
		['.site-footer,footer', 'footer', 1],
		['.ugc', 'content', 1]
	];

	function zone(el) {
		var weak = '';
		for (; el && el.nodeType === 1 && el !== d.body; el = el.parentElement) {
			for (var i = 0; i < Z.length; i++) {
				try {
					if (el.matches(Z[i][0])) {
						if (!Z[i][2]) return Z[i][1];
						if (!weak) weak = Z[i][1];
					}
				} catch (e) {}
			}
		}
		return weak;
	}

	function pageType() {
		var c = ' ' + ((d.body && d.body.className) || '') + ' ';
		if (/ single-review /.test(c)) return 'review';
		if (/ home /.test(c)) return 'home';
		if (/ single /.test(c)) return 'article';
		if (/ page /.test(c)) return 'page';
		if (/ (archive|search|blog) /.test(c)) return 'list';
		return 'other';
	}

	function send(o) {
		o.k = K;
		o.p = w.location.pathname;
		o.pt = pageType();
		if (V) o.v = V;
		var b = JSON.stringify(o);
		try { if (n.sendBeacon && n.sendBeacon(E, b)) return; } catch (e) {}
		try { fetch(E, { method: 'POST', body: b, keepalive: true, mode: 'no-cors', credentials: 'omit' }); } catch (e) {}
	}

	function text(el) {
		var x = el.getAttribute('data-tj') || el.innerText || el.getAttribute('aria-label') || el.title || el.getAttribute('alt') || '';
		return x.replace(/\s+/g, ' ').trim().slice(0, 150);
	}

	function pv() {
		var r = '';
		try { r = d.referrer ? new URL(d.referrer).hostname : ''; } catch (e) {}
		send({ t: 'pv', ti: (d.title || '').slice(0, 150), r: r, w: w.innerWidth || 0 });
	}
	// The script can run before <body> exists; wait for it so the page type is known.
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', pv); else pv();

	function onClick(e) {
		if (e.type === 'auxclick' && e.button !== 1) return;
		var t = e.target;
		if (!t || !t.closest) return;
		var el = t.closest('a,button,[data-tj],[role=button],summary,label'), z = zone(t), h = '';
		if (!el) {
			// Inside a widget, also count clicks on clickable non-links (tabs, toggles, "Quick facts").
			if (!z) return;
			for (var c = t, i = 0; c && c.nodeType === 1 && c !== d.body && i < 6; c = c.parentElement, i++) {
				if (w.getComputedStyle(c).cursor === 'pointer') { el = c; break; }
			}
			if (!el) return;
		}
		if (typeof el.href === 'string') h = el.href;
		send({ t: 'c', h: h.slice(0, 1000), x: text(el), rel: (el.getAttribute('rel') || '').slice(0, 100), wg: z });
	}
	d.addEventListener('click', onClick, { capture: true, passive: true });
	d.addEventListener('auxclick', onClick, { capture: true, passive: true });
})(window, document, navigator);

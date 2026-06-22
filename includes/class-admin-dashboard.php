<?php
/**
 * Admin dashboard for quiz completions.
 *
 * Multi-tab dashboard surfacing quiz performance, results, structure, and
 * the live Mautic campaigns triggered by the quiz. Designed to be the
 * single pane of glass for the quiz operator.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Admin_Dashboard {

	const PAGE_SLUG = 'sego-lily-routine-quiz-dashboard';

	public static function register_menu() {
		add_menu_page(
			'Routine Quiz',
			'Routine Quiz',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-bar',
			58
		);
		add_submenu_page(
			self::PAGE_SLUG,
			'Dashboard',
			'Dashboard',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = isset( $_GET['slrq_action'] ) ? sanitize_key( $_GET['slrq_action'] ) : '';

		if ( $action === 'export_csv' ) {
			check_admin_referer( 'slrq_export_csv' );
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="quiz-completions-' . gmdate( 'Y-m-d' ) . '.csv"' );
			SLRQ_Completions::export_csv();
			exit;
		}

		if ( $action === 'resync' && ! empty( $_GET['id'] ) ) {
			check_admin_referer( 'slrq_resync_' . absint( $_GET['id'] ) );
			$result = SLRQ_Completions::resync( absint( $_GET['id'] ) );
			$flag = ! empty( $result['success'] ) ? 'resynced' : 'resync_failed';
			wp_safe_redirect( add_query_arg(
				array_filter( array(
					'page'        => self::PAGE_SLUG,
					'tab'         => sanitize_key( $_GET['tab'] ?? '' ),
					'slrq_status' => $flag,
				) ),
				admin_url( 'admin.php' )
			) );
			exit;
		}

		if ( $action === 'bulk_resync_failed' ) {
			check_admin_referer( 'slrq_bulk_resync' );
			$rows = SLRQ_Completions::query( array(
				'sync_status' => 'failed',
				'per_page'    => 200,
			) );
			$ok = 0;
			$fail = 0;
			foreach ( $rows['rows'] as $r ) {
				$res = SLRQ_Completions::resync( (int) $r['id'] );
				if ( ! empty( $res['success'] ) ) {
					$ok++;
				} else {
					$fail++;
				}
			}
			wp_safe_redirect( add_query_arg( array(
				'page'        => self::PAGE_SLUG,
				'tab'         => 'results',
				'slrq_status' => 'bulk_' . $ok . '_' . $fail,
			), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
		$valid_tabs = array( 'overview', 'results', 'structure', 'campaigns' );
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			$tab = 'overview';
		}

		echo '<div class="wrap slrq-wrap">';
		self::render_styles();
		self::render_status_messages();
		self::render_header( $tab );

		switch ( $tab ) {
			case 'results':
				self::render_tab_results();
				break;
			case 'structure':
				self::render_tab_structure();
				break;
			case 'campaigns':
				self::render_tab_campaigns();
				break;
			case 'overview':
			default:
				self::render_tab_overview();
		}

		echo '</div>';
	}

	private static function render_styles() {
		?>
		<style>
		.slrq-wrap { --slrq-bg:#F7F6F3; --slrq-card:#ffffff; --slrq-border:#e5e1d8; --slrq-text:#2C2C2C; --slrq-muted:#628393; --slrq-accent:#386174; --slrq-success:#1a7e3a; --slrq-error:#b8302e; --slrq-warm:#c97a26; --slrq-shadow:0 1px 2px rgba(20,20,20,0.04); }
		.slrq-wrap { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:transparent; }
		.slrq-wrap h1, .slrq-wrap h2, .slrq-wrap h3 { font-family:Georgia,"Times New Roman",serif; color:var(--slrq-text); }
		.slrq-header { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:6px; border-bottom:1px solid var(--slrq-border); padding-bottom:14px; }
		.slrq-header h1 { margin:0 0 4px 0; font-size:24px; font-weight:600; }
		.slrq-header .slrq-sub { color:var(--slrq-muted); font-size:13px; }
		.slrq-actions { display:flex; gap:8px; align-items:center; }
		.slrq-pill { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
		.slrq-pill-live { background:rgba(26,126,58,0.12); color:var(--slrq-success); }
		.slrq-pill-live::before { content:""; width:6px; height:6px; border-radius:50%; background:var(--slrq-success); box-shadow:0 0 0 0 rgba(26,126,58,0.5); animation:slrq-pulse 2s infinite; }
		.slrq-pill-draft { background:rgba(98,131,147,0.12); color:var(--slrq-muted); }
		.slrq-pill-ended { background:rgba(184,48,46,0.10); color:var(--slrq-error); }
		@keyframes slrq-pulse { 0% { box-shadow:0 0 0 0 rgba(26,126,58,0.5);} 70% { box-shadow:0 0 0 6px rgba(26,126,58,0);} 100% { box-shadow:0 0 0 0 rgba(26,126,58,0);} }
		.slrq-tabs { display:flex; gap:4px; margin:14px 0 22px; border-bottom:1px solid var(--slrq-border); }
		.slrq-tabs a { padding:10px 18px; text-decoration:none; color:var(--slrq-muted); font-weight:500; font-size:14px; border-bottom:2px solid transparent; transition:color 0.15s, border-color 0.15s; }
		.slrq-tabs a:hover { color:var(--slrq-text); }
		.slrq-tabs a.active { color:var(--slrq-accent); border-bottom-color:var(--slrq-accent); }
		.slrq-grid { display:grid; gap:14px; }
		.slrq-grid.cols-5 { grid-template-columns:repeat(auto-fit, minmax(170px,1fr)); }
		.slrq-grid.cols-4 { grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); }
		.slrq-grid.cols-3 { grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); }
		.slrq-grid.cols-2 { grid-template-columns:repeat(auto-fit, minmax(360px,1fr)); }
		.slrq-card { background:var(--slrq-card); border:1px solid var(--slrq-border); border-radius:6px; padding:16px 18px; box-shadow:var(--slrq-shadow); }
		.slrq-card.with-accent { border-left:4px solid var(--slrq-accent); }
		.slrq-stat-label { font-size:11px; text-transform:uppercase; letter-spacing:0.6px; color:var(--slrq-muted); font-weight:600; }
		.slrq-stat-value { font-size:30px; font-weight:600; color:var(--slrq-text); margin-top:4px; line-height:1.1; font-family:Georgia,"Times New Roman",serif; }
		.slrq-stat-sub { font-size:12px; color:var(--slrq-muted); margin-top:4px; }
		.slrq-stat-value.good { color:var(--slrq-success); }
		.slrq-stat-value.bad { color:var(--slrq-error); }
		.slrq-section-title { font-size:16px; font-weight:600; color:var(--slrq-text); margin:24px 0 10px; font-family:Georgia,"Times New Roman",serif; }
		.slrq-dist-row { margin-bottom:10px; }
		.slrq-dist-meta { display:flex; justify-content:space-between; font-size:12px; color:#444; margin-bottom:3px; }
		.slrq-dist-meta .slrq-dist-count { color:var(--slrq-muted); font-variant-numeric:tabular-nums; }
		.slrq-dist-bar { background:#f0ece4; height:6px; border-radius:3px; overflow:hidden; }
		.slrq-dist-bar > div { height:100%; background:var(--slrq-accent); border-radius:3px; transition:width 0.4s; }
		.slrq-table { width:100%; border-collapse:separate; border-spacing:0; background:var(--slrq-card); border:1px solid var(--slrq-border); border-radius:6px; overflow:hidden; box-shadow:var(--slrq-shadow); }
		.slrq-table th { background:#fafaf6; padding:12px 14px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--slrq-muted); font-weight:600; border-bottom:1px solid var(--slrq-border); }
		.slrq-table th a { color:var(--slrq-muted); text-decoration:none; }
		.slrq-table th a.sorted { color:var(--slrq-text); }
		.slrq-table th a.sorted::after { content:" ↑"; }
		.slrq-table th a.sorted.desc::after { content:" ↓"; }
		.slrq-table td { padding:12px 14px; font-size:13px; border-bottom:1px solid #f0ece4; color:var(--slrq-text); }
		.slrq-table tr:last-child td { border-bottom:0; }
		.slrq-table tr:hover td { background:#fbfaf6; }
		.slrq-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
		.slrq-badge-synced { background:rgba(26,126,58,0.12); color:var(--slrq-success); }
		.slrq-badge-failed { background:rgba(184,48,46,0.12); color:var(--slrq-error); }
		.slrq-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; padding:14px 16px; background:var(--slrq-card); border:1px solid var(--slrq-border); border-radius:6px; margin-bottom:14px; box-shadow:var(--slrq-shadow); }
		.slrq-filters label { display:flex; flex-direction:column; gap:4px; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--slrq-muted); font-weight:600; }
		.slrq-filters input, .slrq-filters select { padding:6px 10px; border:1px solid var(--slrq-border); border-radius:4px; background:#fff; font-size:13px; min-width:140px; font-family:inherit; color:var(--slrq-text); }
		.slrq-filters .submit-row { display:flex; gap:6px; margin-left:auto; }
		.slrq-btn { display:inline-block; padding:7px 14px; font-size:13px; font-weight:500; border-radius:4px; text-decoration:none; cursor:pointer; border:1px solid transparent; transition:background 0.15s, border-color 0.15s; font-family:inherit; }
		.slrq-btn-primary { background:var(--slrq-accent); color:#fff; border-color:var(--slrq-accent); }
		.slrq-btn-primary:hover { background:#2c4e5e; color:#fff; }
		.slrq-btn-secondary { background:#fff; color:var(--slrq-text); border-color:var(--slrq-border); }
		.slrq-btn-secondary:hover { background:#fafaf6; }
		.slrq-btn-ghost { background:transparent; color:var(--slrq-muted); }
		.slrq-btn-ghost:hover { color:var(--slrq-text); }
		.slrq-spark { vertical-align:middle; }
		.slrq-spark path { fill:none; stroke:var(--slrq-accent); stroke-width:2; }
		.slrq-spark .area { fill:rgba(56,97,116,0.10); stroke:none; }
		.slrq-empty { text-align:center; padding:40px 20px; color:var(--slrq-muted); }
		.slrq-question-card { background:var(--slrq-card); border:1px solid var(--slrq-border); border-radius:6px; padding:18px 20px; box-shadow:var(--slrq-shadow); }
		.slrq-question-card h3 { margin:0 0 12px 0; font-size:16px; font-weight:600; display:flex; align-items:center; gap:10px; }
		.slrq-question-card h3 .slrq-q-num { background:var(--slrq-accent); color:#fff; width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; font-family:-apple-system,BlinkMacSystemFont,sans-serif; }
		.slrq-answer-list { list-style:none; margin:0; padding:0; }
		.slrq-answer-list li { display:flex; justify-content:space-between; align-items:center; padding:9px 12px; background:#fafaf6; border:1px solid var(--slrq-border); border-radius:4px; margin-bottom:6px; font-size:13px; }
		.slrq-answer-list li:last-child { margin-bottom:0; }
		.slrq-answer-list .slrq-tag { font-family:Menlo,Consolas,monospace; font-size:11px; background:#fff; padding:2px 8px; border-radius:3px; border:1px solid var(--slrq-border); color:var(--slrq-accent); }
		.slrq-matrix { width:100%; border-collapse:separate; border-spacing:0; background:var(--slrq-card); border:1px solid var(--slrq-border); border-radius:6px; box-shadow:var(--slrq-shadow); overflow:hidden; }
		.slrq-matrix th, .slrq-matrix td { padding:10px 12px; font-size:12.5px; border-bottom:1px solid var(--slrq-border); text-align:left; vertical-align:top; }
		.slrq-matrix th { background:#fafaf6; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--slrq-muted); font-weight:600; }
		.slrq-matrix tr:last-child th, .slrq-matrix tr:last-child td { border-bottom:0; }
		.slrq-matrix .slrq-prod-pri { font-weight:600; color:var(--slrq-text); }
		.slrq-matrix .slrq-prod-sec { color:var(--slrq-muted); font-size:12px; margin-top:2px; }
		.slrq-notice { padding:11px 14px; border-radius:4px; font-size:13px; margin-bottom:14px; border-left:4px solid; }
		.slrq-notice-success { background:rgba(26,126,58,0.08); border-color:var(--slrq-success); color:var(--slrq-success); }
		.slrq-notice-error { background:rgba(184,48,46,0.08); border-color:var(--slrq-error); color:var(--slrq-error); }
		.slrq-notice-info { background:rgba(56,97,116,0.06); border-color:var(--slrq-accent); color:var(--slrq-text); }
		.slrq-pagination { display:flex; justify-content:space-between; align-items:center; margin-top:14px; padding:0 4px; font-size:13px; color:var(--slrq-muted); }
		.slrq-pagination .page-numbers { display:inline-block; padding:5px 10px; margin:0 2px; text-decoration:none; color:var(--slrq-muted); border-radius:3px; }
		.slrq-pagination .page-numbers.current { background:var(--slrq-accent); color:#fff; }
		.slrq-pagination .page-numbers:hover:not(.current) { background:#fafaf6; color:var(--slrq-text); }
		.slrq-mono { font-family:Menlo,Consolas,monospace; font-size:12px; color:var(--slrq-muted); }
		.slrq-link { color:var(--slrq-accent); text-decoration:none; border-bottom:1px solid transparent; transition:border-color 0.15s; }
		.slrq-link:hover { border-bottom-color:var(--slrq-accent); }
		.slrq-empty-state { padding:60px 20px; text-align:center; background:var(--slrq-card); border:1px dashed var(--slrq-border); border-radius:6px; color:var(--slrq-muted); }
		.slrq-empty-state h3 { margin:0 0 8px 0; color:var(--slrq-text); font-size:18px; }
		</style>
		<?php
	}

	private static function render_status_messages() {
		if ( empty( $_GET['slrq_status'] ) ) {
			return;
		}
		$flag = sanitize_text_field( wp_unslash( $_GET['slrq_status'] ) );
		if ( $flag === 'resynced' ) {
			echo '<div class="slrq-notice slrq-notice-success">Re-synced to Mautic successfully.</div>';
		} elseif ( $flag === 'resync_failed' ) {
			echo '<div class="slrq-notice slrq-notice-error">Re-sync failed. Inspect the failed-row error message in the Results tab.</div>';
		} elseif ( strpos( $flag, 'bulk_' ) === 0 ) {
			$parts = explode( '_', $flag );
			$ok    = isset( $parts[1] ) ? (int) $parts[1] : 0;
			$fail  = isset( $parts[2] ) ? (int) $parts[2] : 0;
			$msg   = sprintf( 'Bulk re-sync complete: %d succeeded, %d failed.', $ok, $fail );
			$class = $fail === 0 ? 'slrq-notice-success' : 'slrq-notice-info';
			echo '<div class="slrq-notice ' . esc_attr( $class ) . '">' . esc_html( $msg ) . '</div>';
		}
	}

	private static function quiz_meta() {
		$page    = get_page_by_path( 'your-routine' );
		$page_id = $page ? (int) $page->ID : 0;
		return array(
			'id'         => 'sego-lily-routine-quiz',
			'name'       => 'Routine Quiz',
			'url'        => home_url( '/your-routine' ),
			'status'     => $page_id && $page->post_status === 'publish' ? 'live' : 'draft',
			'created_at' => $page ? $page->post_date : null,
			'version'    => defined( 'SLRQ_VERSION' ) ? SLRQ_VERSION : 'dev',
		);
	}

	private static function render_header( $active_tab ) {
		$quiz  = self::quiz_meta();
		$stats = SLRQ_Completions::get_stats();
		$tabs  = array(
			'overview'   => 'Overview',
			'results'    => 'Results',
			'structure'  => 'Quiz Structure',
			'campaigns'  => 'Campaigns',
		);
		?>
		<div class="slrq-header">
			<div>
				<div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
					<h1><?php echo esc_html( $quiz['name'] ); ?></h1>
					<?php if ( $quiz['status'] === 'live' ) : ?>
						<span class="slrq-pill slrq-pill-live">Live</span>
					<?php else : ?>
						<span class="slrq-pill slrq-pill-draft">Draft</span>
					<?php endif; ?>
				</div>
				<div class="slrq-sub">
					<a class="slrq-link" href="<?php echo esc_url( $quiz['url'] ); ?>" target="_blank"><?php echo esc_html( $quiz['url'] ); ?></a>
					&nbsp;&middot;&nbsp; <?php echo (int) $stats['total']; ?> total completions
					&nbsp;&middot;&nbsp; v<?php echo esc_html( $quiz['version'] ); ?>
				</div>
			</div>
			<div class="slrq-actions">
				<a class="slrq-btn slrq-btn-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'slrq_action' => 'export_csv' ), admin_url( 'admin.php' ) ), 'slrq_export_csv' ) ); ?>">Export CSV</a>
			</div>
		</div>

		<div class="slrq-tabs">
			<?php foreach ( $tabs as $slug => $label ) :
				$url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			?>
				<a class="<?php echo $active_tab === $slug ? 'active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_tab_overview() {
		$stats   = SLRQ_Completions::get_stats();
		$daily   = SLRQ_Completions::get_daily_counts( 30 );
		$skin    = SLRQ_Completions::get_distribution( 'skin_concern' );
		$frust   = SLRQ_Completions::get_distribution( 'frustration' );
		$pc      = SLRQ_Completions::get_distribution( 'product_count' );
		$rec     = SLRQ_Completions::get_distribution( 'recommendation_slug' );
		$recent  = SLRQ_Completions::get_recent( 10, 0 );
		$conv    = SLRQ_Completions::conversion_metrics( gmdate( 'Y-m-d', strtotime( '-30 days' ) ), gmdate( 'Y-m-d' ) );

		?>
		<div class="slrq-grid cols-5">
			<?php
			self::stat_card( 'Total completions', number_format_i18n( $stats['total'] ) );
			self::stat_card( 'Last 7 days', number_format_i18n( $stats['last_7_days'] ), self::sparkline( array_slice( $daily, -7 ) ) );
			self::stat_card( 'Last 30 days', number_format_i18n( $stats['last_30_days'] ), self::sparkline( $daily ) );
			self::stat_card( 'Mautic sync rate', $stats['sync_rate'] . '%', null, ( $stats['sync_rate'] >= 95 ? 'good' : 'bad' ) );
			self::stat_card( 'Failed syncs', number_format_i18n( $stats['failed'] ), null, ( $stats['failed'] === 0 ? 'good' : 'bad' ) );
			?>
		</div>

		<?php if ( $conv['wc_active'] ) : ?>
			<div class="slrq-section-title">Conversion (last 30 days)</div>
			<div class="slrq-grid cols-4">
				<?php
				self::stat_card( 'Completions', number_format_i18n( $conv['completions'] ) );
				self::stat_card( 'Converted to order', number_format_i18n( $conv['converted'] ), null, $conv['converted'] > 0 ? 'good' : '' );
				self::stat_card( 'Conversion rate', $conv['conversion_rate'] . '%' );
				self::stat_card( 'Revenue', '$' . number_format_i18n( $conv['revenue'], 2 ), $conv['avg_days'] !== null ? '<span class="slrq-stat-sub">avg ' . esc_html( $conv['avg_days'] ) . ' days to purchase</span>' : null );
				?>
			</div>
		<?php endif; ?>

		<div class="slrq-section-title">Answer distribution</div>
		<div class="slrq-grid cols-2">
			<?php
			self::distribution_card( 'Skin concern', $skin );
			self::distribution_card( 'Frustration', $frust );
			self::distribution_card( 'Product count', $pc );
			self::distribution_card( 'Recommendation', $rec );
			?>
		</div>

		<div class="slrq-section-title">Latest completions</div>
		<?php if ( empty( $recent ) ) : ?>
			<div class="slrq-empty-state">
				<h3>No completions yet</h3>
				<p>Once someone finishes the quiz at <a class="slrq-link" target="_blank" href="<?php echo esc_url( home_url( '/your-routine' ) ); ?>"><?php echo esc_html( home_url( '/your-routine' ) ); ?></a>, they will appear here.</p>
			</div>
		<?php else : ?>
			<table class="slrq-table">
				<thead><tr><th>When</th><th>Name</th><th>Email</th><th>Skin concern</th><th>Frustration</th><th>Recommendation</th><th>Mautic</th></tr></thead>
				<tbody>
				<?php foreach ( $recent as $r ) : ?>
					<tr>
						<td><?php echo esc_html( self::format_when( $r['completed_at'] ) ); ?></td>
						<td><?php echo esc_html( $r['firstname'] ); ?></td>
						<td><span class="slrq-mono"><?php echo esc_html( $r['email'] ); ?></span></td>
						<td><?php echo esc_html( $r['skin_concern'] ); ?></td>
						<td><?php echo esc_html( $r['frustration'] ); ?></td>
						<td><span class="slrq-mono"><?php echo esc_html( $r['recommendation_slug'] ); ?></span></td>
						<td><?php echo (int) $r['mautic_synced'] === 1 ? '<span class="slrq-badge slrq-badge-synced">Synced</span>' : '<span class="slrq-badge slrq-badge-failed">Failed</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top:10px;"><a class="slrq-link" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'results' ), admin_url( 'admin.php' ) ) ); ?>">View all completions &rarr;</a></p>
		<?php endif; ?>
		<?php
	}

	private static function render_tab_results() {
		$args = array(
			'date_from'    => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'      => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'skin_concern' => isset( $_GET['skin_concern'] ) ? sanitize_text_field( wp_unslash( $_GET['skin_concern'] ) ) : '',
			'frustration'  => isset( $_GET['frustration'] ) ? sanitize_text_field( wp_unslash( $_GET['frustration'] ) ) : '',
			'sync_status'  => isset( $_GET['sync_status'] ) ? sanitize_key( $_GET['sync_status'] ) : '',
			'search'       => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'orderby'      => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'completed_at',
			'order'        => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
			'paged'        => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page'     => 25,
		);
		$result = SLRQ_Completions::query( $args );
		$skin_options = array_column( SLRQ_Completions::get_distribution( 'skin_concern' ), 'k' );
		$frust_options = array_column( SLRQ_Completions::get_distribution( 'frustration' ), 'k' );
		?>

		<form class="slrq-filters" method="get" action="">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<input type="hidden" name="tab" value="results" />
			<label>Search<input type="text" name="search" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="email or name" /></label>
			<label>From<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>" /></label>
			<label>To<input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>" /></label>
			<label>Skin concern
				<select name="skin_concern">
					<option value="">All</option>
					<?php foreach ( $skin_options as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $args['skin_concern'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Frustration
				<select name="frustration">
					<option value="">All</option>
					<?php foreach ( $frust_options as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $args['frustration'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Sync
				<select name="sync_status">
					<option value="">All</option>
					<option value="synced" <?php selected( $args['sync_status'], 'synced' ); ?>>Synced</option>
					<option value="failed" <?php selected( $args['sync_status'], 'failed' ); ?>>Failed</option>
				</select>
			</label>
			<div class="submit-row">
				<button class="slrq-btn slrq-btn-primary" type="submit">Apply</button>
				<a class="slrq-btn slrq-btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'results' ), admin_url( 'admin.php' ) ) ); ?>">Reset</a>
			</div>
		</form>

		<?php if ( ! empty( $result['rows'] ) ) :
			$failed_in_view = 0;
			foreach ( $result['rows'] as $r ) {
				if ( (int) $r['mautic_synced'] !== 1 ) { $failed_in_view++; }
			}
		?>
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
				<div style="color:var(--slrq-muted); font-size:13px;">
					<?php echo (int) $result['total']; ?> results
				</div>
				<?php if ( $failed_in_view > 0 ) :
					$bulk_url = wp_nonce_url(
						add_query_arg( array( 'page' => self::PAGE_SLUG, 'slrq_action' => 'bulk_resync_failed' ), admin_url( 'admin.php' ) ),
						'slrq_bulk_resync'
					);
				?>
					<a class="slrq-btn slrq-btn-secondary" href="<?php echo esc_url( $bulk_url ); ?>">Re-sync all failed</a>
				<?php endif; ?>
			</div>

			<table class="slrq-table">
				<thead>
					<tr>
						<th><?php echo self::sort_link( 'completed_at', 'When', $args ); ?></th>
						<th><?php echo self::sort_link( 'firstname', 'Name', $args ); ?></th>
						<th><?php echo self::sort_link( 'email', 'Email', $args ); ?></th>
						<th><?php echo self::sort_link( 'skin_concern', 'Skin concern', $args ); ?></th>
						<th><?php echo self::sort_link( 'frustration', 'Frustration', $args ); ?></th>
						<th>Products</th>
						<th>Recommendation</th>
						<th><?php echo self::sort_link( 'mautic_synced', 'Mautic', $args ); ?></th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $result['rows'] as $r ) :
					$synced = (int) $r['mautic_synced'] === 1;
					$resync_url = wp_nonce_url(
						add_query_arg(
							array( 'page' => self::PAGE_SLUG, 'tab' => 'results', 'slrq_action' => 'resync', 'id' => (int) $r['id'] ),
							admin_url( 'admin.php' )
						),
						'slrq_resync_' . (int) $r['id']
					);
				?>
					<tr>
						<td><?php echo esc_html( self::format_when( $r['completed_at'] ) ); ?></td>
						<td><?php echo esc_html( $r['firstname'] ); ?></td>
						<td><span class="slrq-mono"><?php echo esc_html( $r['email'] ); ?></span></td>
						<td><?php echo esc_html( $r['skin_concern'] ); ?></td>
						<td><?php echo esc_html( $r['frustration'] ); ?></td>
						<td><?php echo esc_html( $r['product_count'] ); ?></td>
						<td><span class="slrq-mono"><?php echo esc_html( $r['recommendation_slug'] ); ?></span></td>
						<td>
							<?php if ( $synced ) : ?>
								<span class="slrq-badge slrq-badge-synced">Synced</span>
							<?php else : ?>
								<span class="slrq-badge slrq-badge-failed" title="<?php echo esc_attr( $r['mautic_error'] ?? '' ); ?>">Failed</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! $synced ) : ?>
								<a class="slrq-btn slrq-btn-secondary" style="padding:4px 10px; font-size:12px;" href="<?php echo esc_url( $resync_url ); ?>">Re-sync</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $result['pages'] > 1 ) :
				$carry = array_filter( array(
					'date_from'    => $args['date_from'],
					'date_to'      => $args['date_to'],
					'skin_concern' => $args['skin_concern'],
					'frustration'  => $args['frustration'],
					'sync_status'  => $args['sync_status'],
					'search'       => $args['search'],
					'orderby'      => $args['orderby'] !== 'completed_at' ? $args['orderby'] : '',
					'order'        => strtolower( $args['order'] ) !== 'desc' ? $args['order'] : '',
				), 'strlen' );
				$base = add_query_arg(
					array_merge( array( 'page' => self::PAGE_SLUG, 'tab' => 'results' ), $carry ),
					admin_url( 'admin.php' )
				);
			?>
				<div class="slrq-pagination">
					<span><?php echo (int) $result['total']; ?> total &middot; page <?php echo (int) $args['paged']; ?> of <?php echo (int) $result['pages']; ?></span>
					<span>
						<?php
						echo paginate_links( array(
							'base'      => $base . '%_%',
							'format'    => '&paged=%#%',
							'current'   => (int) $args['paged'],
							'total'     => (int) $result['pages'],
							'prev_text' => '&laquo; prev',
							'next_text' => 'next &raquo;',
						) );
						?>
					</span>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="slrq-empty-state">
				<h3>No results match these filters</h3>
				<p>Adjust the filters above or <a class="slrq-link" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'results' ), admin_url( 'admin.php' ) ) ); ?>">reset to see all completions</a>.</p>
			</div>
		<?php endif; ?>
		<?php
	}

	private static function render_tab_structure() {
		$questions = array(
			array(
				'q' => 'What is your first name?',
				'type' => 'text',
				'options' => array(),
			),
			array(
				'q' => 'What bugs you most about your skin?',
				'type' => 'single-choice',
				'options' => array(
					'Wrinkles & dark spots'  => 'skin-aging',
					'Dryness & tightness'    => 'skin-dryness',
					'Redness & sensitivity'  => 'skin-sensitivity',
					'Breakouts'              => 'skin-breakouts',
				),
			),
			array(
				'q' => 'How many skincare products do you use daily?',
				'type' => 'single-choice',
				'options' => array(
					'1 to 3 products' => '(no tag)',
					'4 to 6 products' => '(no tag)',
					'7 or more'       => '(no tag)',
				),
			),
			array(
				'q' => 'What frustrates you most about skincare?',
				'type' => 'single-choice',
				'options' => array(
					'Nothing works long enough'  => 'frustration-durability',
					'Too many products'          => 'frustration-simplify',
					"Don't trust ingredients"    => 'frustration-ingredients',
					'Just want something simple' => 'frustration-simple',
				),
			),
			array(
				'q' => 'Where should we send your routine?',
				'type' => 'email',
				'options' => array(),
			),
		);

		$tags_always = array( 'quiz-completed', 'retail-quiz-lead' );
		?>
		<div class="slrq-notice slrq-notice-info">
			Quiz questions and recommendations are version-controlled in code. To change wording, edit <span class="slrq-mono">includes/class-quiz.php</span> + ship a new release. This view renders the current structure live from the running plugin.
		</div>

		<div class="slrq-section-title">The 5 questions and the tags each answer applies</div>
		<div class="slrq-grid cols-2">
			<?php foreach ( $questions as $idx => $q ) : ?>
				<div class="slrq-question-card">
					<h3><span class="slrq-q-num"><?php echo (int) ( $idx + 1 ); ?></span><?php echo esc_html( $q['q'] ); ?></h3>
					<?php if ( $q['type'] === 'text' ) : ?>
						<div class="slrq-mono" style="padding:9px 12px; background:#fafaf6; border:1px solid var(--slrq-border); border-radius:4px;">(text input, 30 char max, stripped at first space)</div>
					<?php elseif ( $q['type'] === 'email' ) : ?>
						<div class="slrq-mono" style="padding:9px 12px; background:#fafaf6; border:1px solid var(--slrq-border); border-radius:4px;">(email input, validated server-side)</div>
					<?php else : ?>
						<ul class="slrq-answer-list">
							<?php foreach ( $q['options'] as $answer => $tag ) : ?>
								<li><span><?php echo esc_html( $answer ); ?></span><span class="slrq-tag"><?php echo esc_html( $tag ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="slrq-section-title">Tags every completer gets</div>
		<div class="slrq-card">
			<?php foreach ( $tags_always as $t ) : ?>
				<span class="slrq-tag slrq-mono" style="display:inline-block; padding:4px 10px; background:#fafaf6; border:1px solid var(--slrq-border); border-radius:3px; margin-right:6px; font-size:12px; color:var(--slrq-accent);"><?php echo esc_html( $t ); ?></span>
			<?php endforeach; ?>
		</div>

		<div class="slrq-section-title">Recommendation matrix</div>
		<p style="color:var(--slrq-muted); font-size:13px; margin-top:0;">
			Default routing shown. Male-detected first names route the secondary product to Moxie Bourbon Coffee on non-sensitivity paths. Sensitivity path always uses unscented Baby + Mom regardless of gender.
		</p>
		<?php self::render_recommendation_matrix(); ?>
		<?php
	}

	private static function render_recommendation_matrix() {
		$skin_concerns = array( 'Wrinkles & dark spots', 'Dryness & tightness', 'Redness & sensitivity', 'Breakouts' );
		$frustrations  = array( 'Nothing works long enough', 'Too many products', "Don't trust ingredients", 'Just want something simple' );
		?>
		<div style="overflow-x:auto;">
		<table class="slrq-matrix">
			<thead>
				<tr>
					<th>Skin concern</th>
					<?php foreach ( $frustrations as $f ) : ?>
						<th><?php echo esc_html( $f ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $skin_concerns as $sc ) : ?>
				<tr>
					<th><?php echo esc_html( $sc ); ?></th>
					<?php foreach ( $frustrations as $f ) :
						$rec = SLRQ_Recommendations::pair_for( $sc, $f, '4-6', 'Sarah' );
						$pri = isset( $rec['primary']['name'] ) ? $rec['primary']['name'] : '';
						$sec = isset( $rec['secondary']['name'] ) ? $rec['secondary']['name'] : '';
					?>
						<td>
							<div class="slrq-prod-pri"><?php echo esc_html( $pri ); ?></div>
							<div class="slrq-prod-sec">+ <?php echo esc_html( $sec ); ?></div>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	private static function render_tab_campaigns() {
		$creds = SLRQ_Mautic::get_credentials();
		if ( ! $creds ) {
			echo '<div class="slrq-notice slrq-notice-error">Mautic is not configured, so live campaign data cannot be shown. Add Mautic credentials in the Sego Lily Wholesale plugin (Mautic settings); this plugin reads them automatically.</div>';
			return;
		}

		echo '<div class="slrq-notice slrq-notice-info">Live read from the Mautic API, cached for 60 seconds. Showing recent campaigns and emails; open each in Mautic for full editing and stats.</div>';

		$campaigns = self::cached_mautic_get( 'campaigns', '/api/campaigns?limit=50&orderBy=dateAdded&orderByDir=DESC' );
		$emails    = self::cached_mautic_get( 'emails', '/api/emails?limit=50&orderBy=dateAdded&orderByDir=DESC' );
		$base_url  = SLRQ_Mautic::get_dashboard_base_url();

		self::render_campaigns_block( $campaigns, $base_url );
		self::render_emails_block( $emails, $base_url );
	}

	private static function render_campaigns_block( $campaigns, $base_url ) {
		echo '<div class="slrq-section-title">Recent Mautic campaigns</div>';
		$rows = array();
		if ( is_array( $campaigns ) && ! empty( $campaigns['campaigns'] ) ) {
			$rows = $campaigns['campaigns'];
		}
		if ( empty( $rows ) ) {
			echo '<div class="slrq-empty-state"><h3>No campaigns returned</h3><p>Either the Mautic API is unreachable, the token is invalid, or no campaigns exist yet.</p></div>';
			return;
		}
		?>
		<table class="slrq-table">
			<thead><tr><th>Name</th><th>Status</th><th>Added</th><th>Events</th><th>Open</th></tr></thead>
			<tbody>
			<?php
			$count = 0;
			foreach ( $rows as $id => $c ) {
				if ( ! is_array( $c ) ) { continue; }
				$count++;
				$name      = $c['name'] ?? '(unnamed)';
				$is_pub    = ! empty( $c['isPublished'] );
				$event_ct  = isset( $c['events'] ) && is_array( $c['events'] ) ? count( $c['events'] ) : ( $c['eventCount'] ?? 0 );
				$added     = $c['dateAdded'] ?? '';
				$cid       = $c['id'] ?? $id;
				$pill      = $is_pub ? '<span class="slrq-pill slrq-pill-live">Live</span>' : '<span class="slrq-pill slrq-pill-draft">Draft</span>';
				$link      = $base_url ? esc_url( trailingslashit( $base_url ) . 's/campaigns/view/' . (int) $cid ) : '';
				?>
				<tr>
					<td><strong><?php echo esc_html( $name ); ?></strong></td>
					<td><?php echo $pill; ?></td>
					<td><?php echo esc_html( self::format_when( $added ) ); ?></td>
					<td><?php echo (int) $event_ct; ?></td>
					<td><?php if ( $link ) : ?><a class="slrq-link" target="_blank" href="<?php echo esc_url( $link ); ?>">Open in Mautic &rarr;</a><?php endif; ?></td>
				</tr>
				<?php
				if ( $count >= 15 ) { break; }
			}
			?>
			</tbody>
		</table>
		<?php
	}

	private static function render_emails_block( $emails, $base_url ) {
		echo '<div class="slrq-section-title">Recent Mautic emails</div>';
		$rows = array();
		if ( is_array( $emails ) && ! empty( $emails['emails'] ) ) {
			$rows = $emails['emails'];
		}
		if ( empty( $rows ) ) {
			echo '<div class="slrq-empty-state"><h3>No emails returned</h3></div>';
			return;
		}
		?>
		<table class="slrq-table">
			<thead><tr><th>Subject</th><th>Status</th><th>Sent</th><th>Read</th><th>Clicked</th><th>Open</th></tr></thead>
			<tbody>
			<?php
			$count = 0;
			foreach ( $rows as $id => $e ) {
				if ( ! is_array( $e ) ) { continue; }
				$count++;
				$name      = $e['name'] ?? '(unnamed)';
				$subject   = $e['subject'] ?? '';
				$sent      = (int) ( $e['sentCount'] ?? 0 );
				$read      = (int) ( $e['readCount'] ?? 0 );
				$is_pub    = ! empty( $e['isPublished'] );
				$pill      = $is_pub ? '<span class="slrq-pill slrq-pill-live">Live</span>' : '<span class="slrq-pill slrq-pill-draft">Draft</span>';
				$eid       = $e['id'] ?? $id;
				$link      = $base_url ? esc_url( trailingslashit( $base_url ) . 's/emails/view/' . (int) $eid ) : '';
				$read_rate = $sent > 0 ? round( ( $read / $sent ) * 100, 1 ) : 0;
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $subject ); ?></strong>
						<div class="slrq-mono" style="margin-top:2px;"><?php echo esc_html( $name ); ?></div>
					</td>
					<td><?php echo $pill; ?></td>
					<td><?php echo number_format_i18n( $sent ); ?></td>
					<td><?php echo number_format_i18n( $read ); ?> <span class="slrq-mono">(<?php echo esc_html( $read_rate ); ?>%)</span></td>
					<td><?php echo number_format_i18n( (int) ( $e['variantSentCount'] ?? 0 ) ); ?></td>
					<td><?php if ( $link ) : ?><a class="slrq-link" target="_blank" href="<?php echo esc_url( $link ); ?>">Open &rarr;</a><?php endif; ?></td>
				</tr>
				<?php
				if ( $count >= 20 ) { break; }
			}
			?>
			</tbody>
		</table>
		<?php
	}

	private static function stat_card( $label, $value, $sub = null, $tone = '' ) {
		$tone_class = '';
		if ( $tone === 'good' ) { $tone_class = ' good'; }
		if ( $tone === 'bad' )  { $tone_class = ' bad'; }
		echo '<div class="slrq-card">';
		echo '<div class="slrq-stat-label">' . esc_html( $label ) . '</div>';
		echo '<div class="slrq-stat-value' . esc_attr( $tone_class ) . '">' . wp_kses_post( $value ) . '</div>';
		if ( $sub ) {
			echo '<div class="slrq-stat-sub">' . wp_kses_post( $sub ) . '</div>';
		}
		echo '</div>';
	}

	private static function distribution_card( $title, $rows ) {
		$total = array_sum( array_map( function( $r ) { return (int) $r['c']; }, (array) $rows ) );
		echo '<div class="slrq-card">';
		echo '<div class="slrq-stat-label">' . esc_html( $title ) . '</div>';
		if ( empty( $rows ) ) {
			echo '<div class="slrq-stat-sub" style="margin-top:8px;">No data yet.</div></div>';
			return;
		}
		echo '<div style="margin-top:10px;">';
		foreach ( $rows as $r ) {
			$key = $r['k'] !== '' ? $r['k'] : '(blank)';
			$pct = $total > 0 ? round( ( (int) $r['c'] / $total ) * 100, 1 ) : 0;
			$bar_w = max( 2, (int) round( $pct ) );
			echo '<div class="slrq-dist-row">';
			echo '<div class="slrq-dist-meta"><span>' . esc_html( $key ) . '</span><span class="slrq-dist-count">' . (int) $r['c'] . ' &middot; ' . esc_html( $pct ) . '%</span></div>';
			echo '<div class="slrq-dist-bar"><div style="width:' . esc_attr( $bar_w ) . '%;"></div></div>';
			echo '</div>';
		}
		echo '</div></div>';
	}

	private static function sparkline( $daily ) {
		$values = array_values( $daily );
		if ( count( $values ) < 2 || max( $values ) === 0 ) {
			return null;
		}
		$w = 120;
		$h = 28;
		$max = max( $values );
		$step = $w / ( count( $values ) - 1 );
		$pts = array();
		$area_pts = array( '0,' . $h );
		foreach ( $values as $i => $v ) {
			$x = round( $i * $step, 2 );
			$y = round( $h - ( ( $v / $max ) * ( $h - 4 ) ) - 2, 2 );
			$pts[]      = $x . ',' . $y;
			$area_pts[] = $x . ',' . $y;
		}
		$area_pts[] = $w . ',' . $h;
		$path  = 'M' . implode( ' L', $pts );
		$area  = 'M' . implode( ' L', $area_pts ) . ' Z';
		return '<svg class="slrq-spark" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><path class="area" d="' . esc_attr( $area ) . '" /><path d="' . esc_attr( $path ) . '" /></svg>';
	}

	private static function sort_link( $col, $label, $args ) {
		$current   = $args['orderby'];
		$order     = strtoupper( $args['order'] ) === 'ASC' ? 'asc' : 'desc';
		$new_order = ( $current === $col && $order === 'asc' ) ? 'desc' : 'asc';
		$carry = array_filter( array(
			'date_from'    => $args['date_from'],
			'date_to'      => $args['date_to'],
			'skin_concern' => $args['skin_concern'],
			'frustration'  => $args['frustration'],
			'sync_status'  => $args['sync_status'],
			'search'       => $args['search'],
		), 'strlen' );
		$url = add_query_arg(
			array_merge(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'results',
					'orderby' => $col,
					'order'   => $new_order,
				),
				$carry
			),
			admin_url( 'admin.php' )
		);
		$class = $current === $col ? 'sorted ' . $order : '';
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function cached_mautic_get( $key, $path ) {
		$transient_key = 'slrq_mautic_' . $key;
		$cached = get_transient( $transient_key );
		if ( $cached !== false ) {
			return $cached;
		}
		$data = SLRQ_Mautic::api_get( $path, 10 );
		if ( is_array( $data ) ) {
			set_transient( $transient_key, $data, 60 );
		}
		return $data;
	}

	private static function format_when( $datetime_string ) {
		if ( empty( $datetime_string ) ) {
			return '';
		}
		$ts = strtotime( $datetime_string );
		if ( ! $ts ) {
			return $datetime_string;
		}
		$now  = current_time( 'timestamp' );
		$diff = $now - $ts;
		if ( $diff < 60 ) {
			return 'just now';
		}
		if ( $diff < 3600 ) {
			$m = (int) ( $diff / 60 );
			return $m . ' min ago';
		}
		if ( $diff < 86400 ) {
			$h = (int) ( $diff / 3600 );
			return $h . 'h ago';
		}
		if ( $diff < 86400 * 7 ) {
			$d = (int) ( $diff / 86400 );
			return $d . 'd ago';
		}
		return date_i18n( 'M j, Y', $ts );
	}
}

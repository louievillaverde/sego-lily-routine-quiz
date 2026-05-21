<?php
/**
 * Admin settings page for Sego Lily Routine Quiz.
 *
 * Settings > Sego Lily Routine Quiz lets the site owner confirm Mautic
 * credentials (auto-detected from sego-lily-wholesale) and preview the
 * 5 quiz questions. Question editing is not exposed in this UI; questions
 * are version-controlled in includes/class-quiz.php.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Settings {

	const OPTION_GROUP = 'lprq_settings';
	const PAGE_SLUG    = 'sego-lily-routine-quiz';

	public static function register_menu() {
		add_options_page(
			'Routine Quiz',
			'Routine Quiz',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		$fields = array(
			'lprq_mautic_url'           => array( 'label' => 'Mautic URL', 'desc' => 'Your Mautic instance URL, no trailing slash. Auto-detected from the wholesale plugin if blank.' ),
			'lprq_mautic_client_id'     => array( 'label' => 'Mautic OAuth2 Client ID', 'desc' => 'From Mautic > Settings > API Credentials. Auto-detected from the wholesale plugin if blank.' ),
			'lprq_mautic_client_secret' => array( 'label' => 'Mautic OAuth2 Client Secret', 'desc' => 'From Mautic > Settings > API Credentials. Auto-detected from the wholesale plugin if blank.', 'type' => 'password' ),
		);

		foreach ( $fields as $option_name => $cfg ) {
			register_setting( self::OPTION_GROUP, $option_name );
		}

		add_settings_section( 'lprq_main', 'Mautic Connection', function() {
			echo '<p>The quiz lives at <code>/your-routine</code> (auto-created on plugin activation). Leads sync to Mautic with tags for skin concern and frustration.</p>';
			$creds = SLRQ_Mautic::get_credentials();
			if ( $creds ) {
				echo '<p style="color:#1a7e3a;"><strong>&#10003; Mautic credentials detected</strong> (' . esc_html( $creds['url'] ) . '). Pulled from the Sego Lily Wholesale plugin. Leave the fields below blank to keep using the wholesale plugin as the source of truth.</p>';
			} else {
				echo '<p style="color:#b8302e;"><strong>Mautic not yet configured.</strong> Either fill in the fields below or check that the Sego Lily Wholesale plugin has Mautic credentials saved.</p>';
			}
		}, self::PAGE_SLUG );

		foreach ( $fields as $option_name => $cfg ) {
			add_settings_field( $option_name, $cfg['label'], function() use ( $option_name, $cfg ) {
				$value = get_option( $option_name, '' );
				$type  = $cfg['type'] ?? 'text';
				printf(
					'<input type="%s" name="%s" value="%s" class="regular-text" /><p class="description">%s</p>',
					esc_attr( $type ),
					esc_attr( $option_name ),
					esc_attr( $value ),
					esc_html( $cfg['desc'] )
				);
			}, self::PAGE_SLUG, 'lprq_main' );
		}
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Routine Quiz</h1>
			<p style="font-size:14px;color:#555;max-width:720px;">A 5-question quiz that captures retail leads, syncs them to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at <a href="<?php echo esc_url( home_url( '/your-routine' ) ); ?>"><?php echo esc_html( home_url( '/your-routine' ) ); ?></a>.</p>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr/>

			<h2>The 5 questions customers see</h2>
			<p>Question text is version-controlled in code. To change wording, edit <code>includes/class-quiz.php</code> and ship a new release. The recommendation logic (which 2 products map to which answer combo) lives in <code>includes/class-recommendations.php</code>.</p>

			<ol style="font-size:14px;line-height:1.7;background:#fff;padding:20px 20px 20px 40px;border:1px solid #ddd;border-radius:4px;max-width:720px;">
				<li><strong>What's your first name?</strong><br/><span style="color:#666;">(text input)</span></li>
				<li><strong>What bugs you most about your skin?</strong>
					<ul style="margin-top:6px;list-style:disc inside;">
						<li>Wrinkles &amp; dark spots</li>
						<li>Dryness &amp; tightness</li>
						<li>Redness &amp; sensitivity</li>
						<li>Breakouts</li>
					</ul>
				</li>
				<li><strong>How many skincare products do you use daily?</strong>
					<ul style="margin-top:6px;list-style:disc inside;">
						<li>1 to 3 products</li>
						<li>4 to 6 products</li>
						<li>7 or more</li>
					</ul>
				</li>
				<li><strong>What frustrates you most about skincare?</strong>
					<ul style="margin-top:6px;list-style:disc inside;">
						<li>Nothing works long enough</li>
						<li>Too many products</li>
						<li>Don&#39;t trust the ingredients</li>
						<li>Just want something simple</li>
					</ul>
				</li>
				<li><strong>Where should we send your routine?</strong><br/><span style="color:#666;">(email input)</span></li>
			</ol>

			<h2>What happens when a customer completes the quiz</h2>
			<ul style="font-size:14px;line-height:1.7;background:#fff;padding:20px 20px 20px 40px;border:1px solid #ddd;border-radius:4px;max-width:720px;list-style:disc inside;">
				<li>Lead lands in Mautic as a new (or updated) contact</li>
				<li>Tags applied: <code>quiz-completed</code>, <code>retail-quiz-lead</code>, the matching skin tag (e.g. <code>skin-aging</code>), and the matching frustration tag (e.g. <code>frustration-simplify</code>)</li>
				<li>Customer sees a 2-product routine recommendation matched to their answers, with a personal note from Holly</li>
				<li>Each product card links to its WooCommerce shop page</li>
			</ul>

			<h2>Tags applied per answer</h2>
			<table class="widefat" style="max-width:720px;margin-top:8px;">
				<thead><tr><th>Skin concern answer</th><th>Tag applied</th></tr></thead>
				<tbody>
					<tr><td>Wrinkles &amp; dark spots</td><td><code>skin-aging</code></td></tr>
					<tr><td>Dryness &amp; tightness</td><td><code>skin-dryness</code></td></tr>
					<tr><td>Redness &amp; sensitivity</td><td><code>skin-sensitivity</code></td></tr>
					<tr><td>Breakouts</td><td><code>skin-breakouts</code></td></tr>
				</tbody>
			</table>

			<table class="widefat" style="max-width:720px;margin-top:16px;">
				<thead><tr><th>Frustration answer</th><th>Tag applied</th></tr></thead>
				<tbody>
					<tr><td>Nothing works long enough</td><td><code>frustration-durability</code></td></tr>
					<tr><td>Too many products</td><td><code>frustration-simplify</code></td></tr>
					<tr><td>Don&#39;t trust ingredients</td><td><code>frustration-ingredients</code></td></tr>
					<tr><td>Just want something simple</td><td><code>frustration-simple</code></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}

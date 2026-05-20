<?php
/**
 * Admin settings page for LP Quiz Suite.
 *
 * Settings > LP Quiz Suite lets the site owner configure Mautic credentials
 * + a few brand-level defaults. Per-client recommendations + product mapping
 * are NOT in this UI; they ship as PHP via the `lprq_recommendation` filter
 * (lighter admin, lets us version-control per-client logic).
 *
 * @package LPQuizSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Settings {

	const OPTION_GROUP = 'lprq_settings';
	const PAGE_SLUG    = 'lp-quiz-suite';

	public static function register_menu() {
		add_options_page(
			'LP Quiz Suite',
			'LP Quiz Suite',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		$fields = array(
			'lprq_mautic_url'           => array( 'label' => 'Mautic URL', 'desc' => 'Your Mautic instance URL, no trailing slash (e.g. https://marketing.example.com).' ),
			'lprq_mautic_client_id'     => array( 'label' => 'Mautic OAuth2 Client ID', 'desc' => 'From Mautic > Settings > API Credentials.' ),
			'lprq_mautic_client_secret' => array( 'label' => 'Mautic OAuth2 Client Secret', 'desc' => 'From Mautic > Settings > API Credentials.', 'type' => 'password' ),
			'lprq_brand_name'           => array( 'label' => 'Brand name', 'desc' => 'Used in default copy and quiz results. Defaults to "your routine" if blank.' ),
			'lprq_shop_url'             => array( 'label' => 'Default shop URL', 'desc' => 'Where product cards link if no per-product URL is provided. Defaults to your site\'s /shop.' ),
		);

		foreach ( $fields as $option_name => $cfg ) {
			register_setting( self::OPTION_GROUP, $option_name );
		}

		add_settings_section( 'lprq_main', 'Configuration', function() {
			echo '<p>The retail quiz lives at any WordPress page where you drop the <code>[lp_routine_quiz]</code> shortcode. Mautic credentials below are required for lead sync.</p>';
			$creds = SLRQ_Mautic::get_credentials();
			if ( $creds ) {
				echo '<p style="color:#1a7e3a;"><strong>✓ Mautic credentials detected</strong> (' . esc_html( $creds['url'] ) . ').</p>';
			} else {
				echo '<p style="color:#b8302e;"><strong>Mautic not yet configured.</strong> Fill in the fields below to enable lead sync.</p>';
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
			<h1>LP Quiz Suite</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<hr/>
			<h2>How to use</h2>
			<ol>
				<li>Save your Mautic credentials above.</li>
				<li>Create a WordPress page (e.g. <code>/your-routine</code>).</li>
				<li>Drop the shortcode: <code>[lp_routine_quiz]</code></li>
				<li>(Optional) Customize the heading: <code>[lp_routine_quiz heading="Build your routine"]</code></li>
				<li>Publish the page.</li>
			</ol>
			<h2>Customizing recommendations</h2>
			<p>Default recommendations are generic. Override them per-site by hooking the <code>lprq_recommendation</code> filter in a small child plugin or your theme's <code>functions.php</code>. See the plugin's README for an example.</p>
		</div>
		<?php
	}
}

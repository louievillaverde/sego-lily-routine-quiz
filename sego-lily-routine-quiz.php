<?php
/**
 * Plugin Name:       Routine Quiz
 * Plugin URI:        https://github.com/louievillaverde/sego-lily-routine-quiz
 * Description:       Five-question quiz that captures retail leads, syncs to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at /your-routine, auto-created on activation.
 * Version:           1.8.0
 * Author:            Lead Piranha
 * Author URI:        https://leadpiranha.com
 * License:           Proprietary
 * Text Domain:       sego-lily-routine-quiz
 * Requires PHP:      7.4
 * Requires at least: 6.0
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SLRQ_VERSION', '1.8.0' );
define( 'SLRQ_PLUGIN_FILE', __FILE__ );
define( 'SLRQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SLRQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SLRQ_PLUGIN_DIR . 'includes/class-mautic.php';
require_once SLRQ_PLUGIN_DIR . 'includes/class-recommendations.php';
require_once SLRQ_PLUGIN_DIR . 'includes/class-quiz.php';
require_once SLRQ_PLUGIN_DIR . 'includes/class-settings.php';
require_once SLRQ_PLUGIN_DIR . 'includes/class-updater.php';

add_action( 'plugins_loaded', array( 'SLRQ_Quiz', 'init' ) );
add_action( 'admin_init', array( 'SLRQ_Updater', 'init' ) );
add_action( 'admin_menu', array( 'SLRQ_Settings', 'register_menu' ) );
add_action( 'admin_init', array( 'SLRQ_Settings', 'register_settings' ) );

register_activation_hook( __FILE__, 'slrq_activate' );

function slrq_activate() {
	$existing = get_page_by_path( 'your-routine' );
	if ( $existing ) {
		return;
	}
	wp_insert_post( array(
		'post_title'   => 'Your Routine',
		'post_name'    => 'your-routine',
		'post_content' => '[lp_routine_quiz heading="Build Your Sego Lily Routine" subheading="Two minutes. Five questions. A routine matched to your skin."]',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => get_current_user_id() ?: 1,
	) );
}

add_filter( 'lprq_signoff', function() {
	return 'Holly';
} );

/**
 * Memorial Day 2026 free-shipping callout on the quiz results screen.
 * Renders only during the active offer window (Mountain Time):
 *   Sat 2026-05-23 09:00 MT  through  Mon 2026-05-25 23:59 MT
 * Before and after the window, returns empty and the section hides.
 */
add_filter( 'lprq_results_callout', function( $existing ) {
	if ( ! empty( $existing ) ) {
		return $existing;
	}
	$mt          = new DateTimeZone( 'America/Denver' );
	$now         = new DateTime( 'now', $mt );
	$offer_start = new DateTime( '2026-05-23 09:00', $mt );
	$offer_end   = new DateTime( '2026-05-25 23:59', $mt );
	if ( $now >= $offer_start && $now <= $offer_end ) {
		return '<strong>Free shipping through Monday midnight.</strong><br/>No minimum. Every order. Memorial Day weekend only.';
	}
	return '';
} );

/**
 * Product image URLs — pulled from segolilyskincare.com WP media library.
 * Returns 600x600 webp images for each product slug. Falls back to ageless honey
 * if the product slug doesn't match any known variant.
 */
add_filter( 'lprq_product_image', function( $url, $product, $scent ) {
	$base = 'https://segolilyskincare.com/wp-content/uploads/2026/01/';
	$map  = array(
		'ageless'  => $base . 'ageless_honey_1x-1-600x600.webp',
		'renewal'  => array(
			'mandarin-orange' => $base . 'renewal_mandarin_orange_1x-600x600.webp',
			'unscented'       => $base . 'babymom3-300x300.webp',
			'default'         => $base . 'renewal_mandarin_orange_1x-600x600.webp',
		),
		'moxie'    => $base . 'moxie_vanilla_spice_1x-600x600.webp',
	);
	if ( $product === 'ageless' ) {
		return $map['ageless'];
	}
	if ( $product === 'renewal' ) {
		return $map['renewal'][ $scent ] ?? $map['renewal']['default'];
	}
	if ( $product === 'moxie' ) {
		return $map['moxie'];
	}
	return $url;
}, 10, 3 );


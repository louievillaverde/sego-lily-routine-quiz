<?php
/**
 * Plugin Name:       Routine Quiz
 * Plugin URI:        https://github.com/louievillaverde/sego-lily-routine-quiz
 * Description:       Five-question quiz that captures retail leads, syncs to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at /your-routine, auto-created on activation.
 * Version:           1.13.7
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

define( 'SLRQ_VERSION', '1.13.7' );
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
 * Memorial Day 2026 free-shipping callout — two phase narrative:
 *   Phase 1: Sun 5/24 09:00 MT → Mon 5/25 23:59 MT
 *     "Free shipping through Monday midnight" (offer reads as Memorial Day weekend close)
 *   Phase 2: Tue 5/26 00:00 MT → Tue 5/26 23:59 MT
 *     "Extended through tonight" (surprise extension on Tuesday)
 *
 * Monday positions as last day so customers feel real urgency. Tuesday morning
 * the surprise extension framing kicks in for the late-converters.
 */
add_filter( 'lprq_results_callout', function( $existing ) {
	if ( ! empty( $existing ) ) {
		return $existing;
	}
	$mt           = new DateTimeZone( 'America/Denver' );
	$now          = new DateTime( 'now', $mt );
	$phase1_start = new DateTime( '2026-05-24 09:00', $mt );
	$phase1_end   = new DateTime( '2026-05-25 23:59', $mt );
	$phase2_start = new DateTime( '2026-05-26 00:00', $mt );
	$phase2_end   = new DateTime( '2026-05-26 23:59', $mt );
	if ( $now >= $phase1_start && $now <= $phase1_end ) {
		return '<strong>Free shipping through Monday midnight.</strong><br/>No minimum. Every order. Memorial Day weekend only.';
	}
	if ( $now >= $phase2_start && $now <= $phase2_end ) {
		return '<strong>Extended through tonight.</strong><br/>Free shipping until midnight Mountain Time. Surprise extra day.';
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

/**
 * Cart-add endpoints driven by the quiz results page.
 *
 * Two actions handled, both via wp_loaded so WC()->cart is ready:
 *   slrq_action=add_routine  &p_slug=...&p_scent=...&s_slug=...&s_scent=...
 *     Adds primary + secondary product variations to the cart.
 *   slrq_action=add_one      &slug=...&scent=...
 *     Adds a single product variation to the cart.
 *
 * Both resolve the variation by scent + default size (2 oz.) + default payment
 * (One-Time Purchase), then call WC()->cart->add_to_cart with the variation_id
 * and full attribute array — the WC-native pattern for variable products.
 * Redirects to /cart/ when done.
 */
add_action( 'wp_loaded', function() {
	if ( ! isset( $_GET['slrq_action'] ) ) {
		return;
	}
	$action = $_GET['slrq_action'];
	if ( ! in_array( $action, array( 'add_routine', 'add_one' ), true ) ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	if ( $action === 'add_one' ) {
		$items = array(
			array(
				'slug'  => isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '',
				'scent' => isset( $_GET['scent'] ) ? sanitize_text_field( wp_unslash( $_GET['scent'] ) ) : '',
			),
		);
	} else {
		$items = array(
			array(
				'slug'  => isset( $_GET['p_slug'] ) ? sanitize_text_field( wp_unslash( $_GET['p_slug'] ) ) : '',
				'scent' => isset( $_GET['p_scent'] ) ? sanitize_text_field( wp_unslash( $_GET['p_scent'] ) ) : '',
			),
			array(
				'slug'  => isset( $_GET['s_slug'] ) ? sanitize_text_field( wp_unslash( $_GET['s_slug'] ) ) : '',
				'scent' => isset( $_GET['s_scent'] ) ? sanitize_text_field( wp_unslash( $_GET['s_scent'] ) ) : '',
			),
		);
	}

	$added = false;
	foreach ( $items as $item ) {
		if ( empty( $item['slug'] ) ) {
			continue;
		}
		$post = get_page_by_path( $item['slug'], OBJECT, 'product' );
		if ( ! $post ) {
			continue;
		}
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			continue;
		}

		if ( $product->is_type( 'simple' ) || $product->is_type( 'subscription' ) ) {
			if ( WC()->cart->add_to_cart( $product->get_id() ) ) {
				$added = true;
			}
			continue;
		}

		// Variable / variable-subscription product: find the variation matching
		// scent + default size (2 oz.) + default payment (One-Time Purchase).
		if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
			$variation_id   = 0;
			$matched_attrs  = array();
			$target_scent   = $item['scent'];
			$target_size    = '2 oz.';
			$target_payment = 'One-Time Purchase';
			foreach ( $product->get_available_variations() as $v ) {
				$attrs = $v['attributes'] ?? array();
				$scent_match   = empty( $target_scent ) || ( isset( $attrs['attribute_scent'] ) && strcasecmp( $attrs['attribute_scent'], $target_scent ) === 0 );
				$size_match    = ! isset( $attrs['attribute_size'] ) || strcasecmp( $attrs['attribute_size'], $target_size ) === 0;
				$payment_match = ! isset( $attrs['attribute_payment-options'] ) || stripos( $attrs['attribute_payment-options'], 'one-time' ) !== false;
				if ( $scent_match && $size_match && $payment_match ) {
					$variation_id  = $v['variation_id'];
					$matched_attrs = $attrs;
					break;
				}
			}
			if ( $variation_id && WC()->cart->add_to_cart( $product->get_id(), 1, $variation_id, $matched_attrs ) ) {
				$added = true;
			}
		}
	}

	if ( $added ) {
		WC()->cart->calculate_totals();
		if ( method_exists( WC()->cart, 'set_session' ) ) {
			WC()->cart->set_session();
		}
	}
	wp_safe_redirect( wc_get_cart_url() );
	exit;
}, 20 );


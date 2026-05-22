<?php
/**
 * Plugin Name:       Routine Quiz
 * Plugin URI:        https://github.com/louievillaverde/sego-lily-routine-quiz
 * Description:       Five-question quiz that captures retail leads, syncs to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at /your-routine, auto-created on activation.
 * Version:           1.13.23
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

define( 'SLRQ_VERSION', '1.13.23' );
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
	$phase1_start = new DateTime( '2026-05-23 09:00', $mt );
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
	// Forward cta_id through to the cart URL so analytics can attribute
	// the conversion to the specific quiz-results-page CTA.
	$cart_url = wc_get_cart_url();
	if ( ! empty( $_GET['cta_id'] ) ) {
		$cart_url = add_query_arg( 'cta_id', sanitize_text_field( wp_unslash( $_GET['cta_id'] ) ), $cart_url );
	}
	wp_safe_redirect( $cart_url );
	exit;
}, 20 );

/**
 * AJAX add-to-cart for the quiz results page. Mirrors the wp_loaded
 * redirect endpoint but returns JSON instead of redirecting, so the
 * site's side-drawer cart can pop open in place via the standard
 * WooCommerce `added_to_cart` jQuery event. This matches the rest of
 * Holly's site UX (no separate cart page; everything lives in the
 * side drawer).
 *
 * Request:  POST /wp-admin/admin-ajax.php?action=lprq_add_to_cart
 *           nonce=...  (the lprq_quiz nonce)
 *           cart_action=add_one | add_routine
 *           For add_one:    slug, scent
 *           For add_routine: p_slug, p_scent, s_slug, s_scent
 *
 * Response: { success: true, data: { fragments, cart_hash, cart_count } }
 *           Fragments include the cart-icon-bubble HTML so the site
 *           header updates automatically. The client then fires the
 *           `added_to_cart` event which every modern WC side-cart
 *           plugin listens for to open its drawer.
 */
add_action( 'wp_ajax_lprq_add_to_cart',        'slrq_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_lprq_add_to_cart', 'slrq_ajax_add_to_cart' );
function slrq_ajax_add_to_cart() {
	check_ajax_referer( 'lprq_quiz', 'nonce' );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'Cart not available' ) );
	}

	$cart_action = sanitize_text_field( wp_unslash( $_POST['cart_action'] ?? '' ) );
	if ( ! in_array( $cart_action, array( 'add_one', 'add_routine' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid action' ) );
	}

	if ( $cart_action === 'add_one' ) {
		$items = array(
			array(
				'slug'  => sanitize_text_field( wp_unslash( $_POST['slug']  ?? '' ) ),
				'scent' => sanitize_text_field( wp_unslash( $_POST['scent'] ?? '' ) ),
			),
		);
	} else {
		$items = array(
			array(
				'slug'  => sanitize_text_field( wp_unslash( $_POST['p_slug']  ?? '' ) ),
				'scent' => sanitize_text_field( wp_unslash( $_POST['p_scent'] ?? '' ) ),
			),
			array(
				'slug'  => sanitize_text_field( wp_unslash( $_POST['s_slug']  ?? '' ) ),
				'scent' => sanitize_text_field( wp_unslash( $_POST['s_scent'] ?? '' ) ),
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

	if ( ! $added ) {
		wp_send_json_error( array( 'message' => 'Could not add to cart' ) );
	}

	WC()->cart->calculate_totals();
	if ( method_exists( WC()->cart, 'set_session' ) ) {
		WC()->cart->set_session();
	}

	// WC fragments for the cart icon bubble + any other registered fragments
	// in the site header. Standard pattern, same as WC's native AJAX add.
	ob_start();
	woocommerce_mini_cart();
	$mini_cart = ob_get_clean();
	$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array(
		'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
	) );

	wp_send_json_success( array(
		'fragments'  => $fragments,
		'cart_hash'  => WC()->cart->get_cart_hash(),
		'cart_count' => WC()->cart->get_cart_contents_count(),
	) );
}

/**
 * Per-skin-concern testimonials, pulled from real verified customer reviews
 * on segolilyskincare.com (WP comments / WC reviews, retrieved 2026-05-22).
 * Each quote is trimmed for length but stays in the customer's own words.
 */
add_filter( 'lprq_testimonial_for_concern', function( $existing, $skin_concern ) {
	if ( ! empty( $existing ) ) {
		return $existing;
	}
	$quotes = array(
		'Wrinkles & dark spots' => array(
			'quote'       => 'Five months in, my skin has never looked better. More even-toned, very soft. It&rsquo;s replaced both my eye cream and my moisturizer.',
			'attribution' => 'Trish P, 5 months in',
		),
		'Dryness & tightness' => array(
			'quote'       => 'I&rsquo;d tried lotions, exfoliants, pedicures. Nothing helped my cracked heels. One week of daily tallow and they were healed up.',
			'attribution' => 'Shannon, verified customer',
		),
		'Redness & sensitivity' => array(
			'quote'       => 'We use it for poison ivy rash. The conventional cream left our skin dry and itchy. Sego Lily tallow stops the itch and moisturizes.',
			'attribution' => 'Jackie S., Michigan',
		),
		'Breakouts' => array(
			'quote'       => 'I&rsquo;d tried everything for acne, including prescriptions. Since switching to the tallow it&rsquo;s drastically cleared my skin, texture and scarring too.',
			'attribution' => 'Chloe, verified customer',
		),
	);
	return $quotes[ $skin_concern ] ?? null;
}, 10, 2 );

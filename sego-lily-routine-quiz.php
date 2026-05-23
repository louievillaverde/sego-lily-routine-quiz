<?php
/**
 * Plugin Name:       Routine Quiz
 * Plugin URI:        https://github.com/louievillaverde/sego-lily-routine-quiz
 * Description:       Five-question quiz that captures retail leads, syncs to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at /your-routine, auto-created on activation.
 * Version:           1.13.28
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

define( 'SLRQ_VERSION', '1.13.28' );
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
 * SEO + social meta tags for the quiz page. Injected into wp_head when the
 * current post contains the [lp_routine_quiz] shortcode. Covers the basics:
 * title, description, Open Graph (Facebook, LinkedIn, SMS preview cards on
 * iMessage / WhatsApp), and Twitter card. All values filterable so the
 * plugin can be reused on other client sites without forking.
 *
 * SEO plugins (Yoast, RankMath, AIOSEO) override these automatically when
 * the page has its own SEO settings configured. This is the no-SEO-plugin
 * fallback so shared links don't pull the site's default home-page image
 * + title.
 */
add_action( 'wp_head', function() {
	if ( ! is_singular() ) return;
	global $post;
	if ( ! $post || ! is_a( $post, 'WP_Post' ) ) return;
	if ( ! has_shortcode( $post->post_content, 'lp_routine_quiz' ) ) return;

	$title = apply_filters( 'lprq_meta_title',
		'Build Your Sego Lily Routine | Free 2-Minute Quiz'
	);
	$description = apply_filters( 'lprq_meta_description',
		'Take Holly\'s 2-minute skin quiz and get a matched 2-product tallow routine. Five food-grade ingredients, made by hand in Montana.'
	);
	$image = apply_filters( 'lprq_og_image',
		'https://segolilyskincare.com/wp-content/uploads/2026/01/ageless_honey_1x-1-600x600.webp'
	);
	$url   = apply_filters( 'lprq_canonical_url', get_permalink( $post->ID ) );

	$image_width  = apply_filters( 'lprq_og_image_width',  600 );
	$image_height = apply_filters( 'lprq_og_image_height', 600 );

	echo "\n<!-- Routine Quiz SEO -->\n";
	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";

	echo '<meta property="og:type" content="website" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
	echo '<meta property="og:image:width" content="' . esc_attr( $image_width ) . '" />' . "\n";
	echo '<meta property="og:image:height" content="' . esc_attr( $image_height ) . '" />' . "\n";

	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
	echo "<!-- /Routine Quiz SEO -->\n";
}, 1 );

/**
 * Filter the document <title> on the quiz page so the browser tab + search
 * engine snippet reads cleanly. Runs at priority 99 so any SEO plugin can
 * still override.
 */
add_filter( 'pre_get_document_title', function( $title ) {
	if ( ! is_singular() ) return $title;
	global $post;
	if ( ! $post || ! is_a( $post, 'WP_Post' ) ) return $title;
	if ( ! has_shortcode( $post->post_content, 'lp_routine_quiz' ) ) return $title;
	return apply_filters( 'lprq_meta_title',
		'Build Your Sego Lily Routine | Free 2-Minute Quiz'
	);
}, 99 );

/**
 * Memorial Day 2026 free-shipping callout. Single continuous window:
 * Sat 5/23 09:00 MT through Tue 5/26 23:59 MT. All three days announced
 * up front (no surprise-extension framing). On the final day Tuesday
 * the callout shifts to "closes tonight" so the urgency lands without
 * walking back a previously-promised close.
 */
add_filter( 'lprq_results_callout', function( $existing ) {
	if ( ! empty( $existing ) ) {
		return $existing;
	}
	$mt          = new DateTimeZone( 'America/Denver' );
	$now         = new DateTime( 'now', $mt );
	$open_start  = new DateTime( '2026-05-23 09:00', $mt );
	$final_start = new DateTime( '2026-05-26 00:00', $mt );
	$close       = new DateTime( '2026-05-26 23:59', $mt );
	if ( $now >= $final_start && $now <= $close ) {
		return '<strong>Free shipping closes tonight at midnight MT.</strong><br/>Last day. No minimum. Every order.';
	}
	if ( $now >= $open_start && $now <= $close ) {
		return '<strong>Free shipping through Tuesday at midnight MT.</strong><br/>No minimum. Every order. Memorial Day weekend.';
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
	// Redirect to /cart/ so the customer can review what they're getting +
	// the total before entering shipping details. Cart page should use the
	// classic [woocommerce_cart] shortcode (mobile-responsive, has a built-in
	// Proceed to Checkout button). The new WC Cart Block has known mobile
	// layout issues. Override via filter if a future client needs a
	// different post-add destination (e.g., wc_get_checkout_url() to skip).
	$redirect_url = apply_filters( 'lprq_post_add_redirect_url', wc_get_cart_url() );
	if ( ! empty( $_GET['cta_id'] ) ) {
		$redirect_url = add_query_arg( 'cta_id', sanitize_text_field( wp_unslash( $_GET['cta_id'] ) ), $redirect_url );
	}
	wp_safe_redirect( $redirect_url );
	exit;
}, 20 );


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

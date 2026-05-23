<?php
/**
 * Plugin Name:       Routine Quiz
 * Plugin URI:        https://github.com/louievillaverde/sego-lily-routine-quiz
 * Description:       Five-question quiz that captures retail leads, syncs to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at /your-routine, auto-created on activation.
 * Version:           1.13.34
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

define( 'SLRQ_VERSION', '1.13.34' );
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
 * Cart page styling. Only fires on the cart page so it doesn't bleed
 * into the rest of the theme. Targets the classic [woocommerce_cart]
 * shortcode markup (LV swapped the WC Cart Block for the shortcode on
 * 2026-05-22 because the block was broken on mobile).
 *
 * On-brand colors (teal #386174 + cream #F7F6F3) match the quiz
 * results page so the post-quiz flow feels continuous. Mobile breakpoint
 * converts the table to stacked cards so phone customers actually see
 * the cart contents without horizontal scroll.
 */
add_action( 'wp_head', function() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) return;
	?>
	<style>
	/* Sego Lily cart page styling (injected by routine quiz plugin) */
	.woocommerce-cart .entry-content { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
	.woocommerce-cart .woocommerce { font-family: Georgia, 'Times New Roman', serif; }
	.woocommerce-cart .woocommerce-notices-wrapper { max-width: 760px; margin: 0 auto 16px; }

	.woocommerce-cart .shop_table { border: none; margin-bottom: 28px; width: 100%; border-collapse: collapse; background: #ffffff; }
	.woocommerce-cart .shop_table th { background: #F7F6F3; color: #2C2C2C; font-family: Georgia, 'Times New Roman', serif; font-size: 12px; text-transform: uppercase; letter-spacing: 1.2px; padding: 14px 16px; border: none; border-bottom: 1px solid #E8E2D6; text-align: left; font-weight: 700; }
	.woocommerce-cart .shop_table td { padding: 16px; border-bottom: 1px solid #E8E2D6; vertical-align: middle; background: #ffffff; }
	.woocommerce-cart .shop_table .product-thumbnail img { max-width: 84px; height: auto; border-radius: 10px; }
	.woocommerce-cart .shop_table .product-name { font-weight: 600; color: #2C2C2C; }
	.woocommerce-cart .shop_table .product-name a { color: #386174; text-decoration: none; }
	.woocommerce-cart .shop_table .product-name a:hover { color: #2a4a5a; text-decoration: underline; }
	.woocommerce-cart .shop_table .variation { font-size: 13px; color: #8A9499; margin-top: 4px; }
	.woocommerce-cart .shop_table .variation dd { margin: 0; }
	.woocommerce-cart .shop_table .product-price,
	.woocommerce-cart .shop_table .product-subtotal { color: #2C2C2C; font-weight: 600; }
	.woocommerce-cart .shop_table .product-remove a { color: #B8A98C !important; font-size: 22px; text-decoration: none; line-height: 1; display: inline-block; }
	.woocommerce-cart .shop_table .product-remove a:hover { color: #c0392b !important; }
	.woocommerce-cart .quantity .qty { width: 64px; padding: 10px; text-align: center; border: 1px solid #B8A98C; border-radius: 6px; font-size: 15px; background: #ffffff; }

	.woocommerce-cart .coupon { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 18px 0 24px; }
	.woocommerce-cart .coupon label { color: #4a5d68; font-size: 14px; margin-right: 8px; }
	.woocommerce-cart .coupon input[name="coupon_code"] { padding: 10px 14px; border: 1px solid #B8A98C; border-radius: 6px; font-size: 15px; flex: 1; min-width: 180px; background: #ffffff; }
	.woocommerce-cart .button[name="apply_coupon"],
	.woocommerce-cart .button[name="update_cart"] { background: transparent !important; border: 1px solid #386174 !important; color: #386174 !important; padding: 10px 18px !important; font-family: Georgia, 'Times New Roman', serif !important; font-size: 14px !important; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; font-weight: 600 !important; letter-spacing: 0.5px !important; text-transform: none !important; }
	.woocommerce-cart .button[name="apply_coupon"]:hover,
	.woocommerce-cart .button[name="update_cart"]:hover { background: #386174 !important; color: #ffffff !important; }

	.woocommerce-cart .cart-collaterals,
	.woocommerce-cart .cart_totals { background: #F7F6F3; padding: 28px; border-radius: 12px; border: 1px solid #E8E2D6; margin-top: 28px; }
	.woocommerce-cart .cart_totals h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 22px; color: #2C2C2C; margin: 0 0 18px; font-weight: 600; }
	.woocommerce-cart .cart_totals table { width: 100%; background: transparent; border-collapse: collapse; }
	.woocommerce-cart .cart_totals th,
	.woocommerce-cart .cart_totals td { padding: 12px 0 !important; border-bottom: 1px solid #E8E2D6 !important; background: transparent !important; }
	.woocommerce-cart .cart_totals th { color: #4a5d68; font-weight: 600; font-family: Georgia, 'Times New Roman', serif; text-align: left; }
	.woocommerce-cart .cart_totals .order-total .amount { font-size: 22px; color: #2C2C2C; font-weight: 700; }
	.woocommerce-cart .cart_totals tr:last-child th,
	.woocommerce-cart .cart_totals tr:last-child td { border-bottom: none !important; }

	.woocommerce-cart .wc-proceed-to-checkout { margin-top: 22px; padding: 0 !important; }
	.woocommerce-cart .checkout-button,
	.woocommerce-cart a.checkout-button { display: block !important; width: 100% !important; padding: 16px 24px !important; background: #386174 !important; color: #ffffff !important; text-align: center !important; border-radius: 8px !important; font-family: Georgia, 'Times New Roman', serif !important; font-size: 16px !important; font-weight: 700 !important; text-decoration: none !important; box-shadow: 0 4px 14px rgba(56, 97, 116, 0.28) !important; transition: all 0.15s ease !important; letter-spacing: 0.4px !important; box-sizing: border-box !important; border: none !important; }
	.woocommerce-cart .checkout-button:hover { background: #2a4a5a !important; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(56, 97, 116, 0.35) !important; }

	@media (max-width: 600px) {
		.woocommerce-cart .entry-content { padding: 16px 12px 36px; }
		.woocommerce-cart .shop_table thead { display: none; }
		.woocommerce-cart .shop_table tbody tr { display: block; margin-bottom: 14px; padding: 14px; background: #ffffff; border: 1px solid #E8E2D6; border-radius: 12px; }
		.woocommerce-cart .shop_table tbody td { display: flex; justify-content: space-between; align-items: center; text-align: right; padding: 8px 0 !important; border-bottom: 1px dashed #E8E2D6 !important; }
		.woocommerce-cart .shop_table tbody td:last-child { border-bottom: none !important; }
		.woocommerce-cart .shop_table tbody td:before { content: attr(data-title); font-weight: 700; color: #8A9499; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; text-align: left; flex: 0 0 auto; }
		/* Product image: force visible block layout with explicit min-height
		   so the image slot doesn't collapse to a blank gap if WC Subs
		   markup changes its DOM. img force-shown above the rest of the row. */
		.woocommerce-cart .shop_table tbody td.product-thumbnail { display: block !important; text-align: center !important; min-height: 140px; padding: 8px 0 16px !important; border-bottom: 1px dashed #E8E2D6 !important; }
		.woocommerce-cart .shop_table tbody td.product-thumbnail:before { content: '' !important; display: none !important; }
		.woocommerce-cart .shop_table tbody td.product-thumbnail img,
		.woocommerce-cart .shop_table tbody td.product-thumbnail a img,
		.woocommerce-cart .shop_table tbody td.product-thumbnail .attachment-woocommerce_thumbnail { display: block !important; visibility: visible !important; max-width: 140px !important; width: 140px !important; height: auto !important; margin: 0 auto !important; border-radius: 10px; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.actions { border-bottom: none !important; display: block; }
		.woocommerce-cart .coupon { flex-direction: column; align-items: stretch; }
		.woocommerce-cart .coupon input[name="coupon_code"] { min-width: 0; width: 100%; }
		.woocommerce-cart .button[name="apply_coupon"],
		.woocommerce-cart .button[name="update_cart"] { width: 100%; letter-spacing: 0.3px !important; }
		.woocommerce-cart .cart_totals { padding: 20px; }
	}
	</style>
	<?php
}, 100 );

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
 * Strip the WC Subscriptions "for X month/period" suffix on prices when
 * the customer picked the "One-Time Purchase" variation. WC Subs appends
 * the period string to every variable-subscription variation by default,
 * even the one-time options, which confuses customers ("$36.00 for 1 month"
 * reads like a recurring charge when it's actually a one-time order).
 */
add_filter( 'woocommerce_cart_item_price', function( $price_html, $cart_item, $cart_item_key ) {
	if ( ! isset( $cart_item['variation'] ) || ! is_array( $cart_item['variation'] ) ) return $price_html;
	$payment_opt = $cart_item['variation']['attribute_payment-options'] ?? '';
	if ( stripos( $payment_opt, 'one-time' ) === false ) return $price_html;
	// Strip " for 1 month" / " for N months" / " for N year" etc.
	return preg_replace( '/\s*for\s+\d+\s+(month|months|year|years|week|weeks|day|days)/i', '', $price_html );
}, 20, 3 );

add_filter( 'woocommerce_cart_item_subtotal', function( $subtotal_html, $cart_item, $cart_item_key ) {
	if ( ! isset( $cart_item['variation'] ) || ! is_array( $cart_item['variation'] ) ) return $subtotal_html;
	$payment_opt = $cart_item['variation']['attribute_payment-options'] ?? '';
	if ( stripos( $payment_opt, 'one-time' ) === false ) return $subtotal_html;
	return preg_replace( '/\s*for\s+\d+\s+(month|months|year|years|week|weeks|day|days)/i', '', $subtotal_html );
}, 20, 3 );

/**
 * Auto-apply the FREESHIPPING coupon during the Memorial Day window so
 * customers don&rsquo;t have to type a code. Matches the announced offer
 * window (Sat 5/23 9am MT through Tue 5/26 11:59pm MT including the
 * Tuesday surprise extension). Outside that window, the hook does nothing.
 *
 * Defensive: pre-validates the coupon against the current cart context
 * BEFORE calling apply_coupon, and swallows any WC notices generated by
 * the apply so the customer doesn't see a confusing error/success pair
 * on cart load. If the coupon would be rejected (e.g., WC Subscriptions
 * sees a misconfigured-for-subs cart), the hook silently skips and the
 * customer can still type the code manually.
 */
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
	if ( ! $cart || ! is_object( $cart ) ) return;

	$mt    = new DateTimeZone( 'America/Denver' );
	$now   = new DateTime( 'now', $mt );
	$start = new DateTime( '2026-05-23 09:00', $mt );
	$end   = new DateTime( '2026-05-26 23:59', $mt );
	if ( $now < $start || $now > $end ) return;

	$code = apply_filters( 'lprq_auto_coupon_code', 'freeshipping' );
	if ( empty( $code ) ) return;
	if ( method_exists( $cart, 'has_discount' ) && $cart->has_discount( $code ) ) return;

	// Verify the coupon exists in WC before attempting to apply (cheap check
	// that avoids errors if the code was deleted). DO NOT use
	// WC_Discounts::is_coupon_valid here — it returns a WP_Error on
	// variable-subscription carts even when the coupon would actually apply
	// fine, silently blocking auto-apply for Holly's subscription products.
	if ( class_exists( 'WC_Coupon' ) ) {
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) return;
	}

	// Snapshot notices before apply, restore after. This swallows any
	// success/error/notice that apply_coupon would surface to the customer
	// (we don't want them to see a Mautic-style "Applied!" toast every cart
	// load, nor any WC Subs warnings). The coupon is still actually applied.
	$notices_before = function_exists( 'wc_get_notices' ) ? wc_get_notices() : array();
	if ( method_exists( $cart, 'apply_coupon' ) ) {
		$cart->apply_coupon( $code );
	}
	if ( function_exists( 'wc_set_notices' ) ) {
		wc_set_notices( $notices_before );
	}
}, 10, 1 );

/**
 * Memorial Day 2026 free-shipping callout. Two-phase narrative matching
 * the Mautic email sequence:
 *
 *   Phase 1: Sat 5/23 09:00 MT through Mon 5/25 23:59 MT
 *     "Free shipping through Monday midnight" (announced close)
 *   Phase 2: Tue 5/26 00:00 MT through Tue 5/26 23:59 MT
 *     "Extended through tonight" (surprise extension Tuesday)
 *
 * Monday positions as the announced last day so customers feel real
 * urgency. Tuesday morning the surprise extension lands as a gift,
 * which converts the "I almost missed it" panic-buyers.
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
		return '<strong>Free shipping through Monday at midnight MT.</strong><br/>No minimum. Every order. Memorial Day weekend only.';
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

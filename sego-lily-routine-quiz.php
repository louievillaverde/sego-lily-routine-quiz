<?php
/**
 * Plugin Name:       Routine Quiz
 * Plugin URI:        https://github.com/louievillaverde/sego-lily-routine-quiz
 * Description:       Five-question quiz that captures retail leads, syncs to Mautic with tags, and shows each customer a 2-product recommendation from the Sego Lily line. Lives at /your-routine, auto-created on activation.
 * Version:           1.13.47
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

define( 'SLRQ_VERSION', '1.13.47' );
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
 * Add a body class when an auto-applied coupon is on the cart, so the
 * cart-page JS can pre-populate the visible "Coupon code" input field
 * with the code. Without this, customers see an empty input even though
 * the coupon is applied, which reads as "did it work?"
 */
add_filter( 'body_class', function( $classes ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return $classes;
	$code = apply_filters( 'lprq_auto_coupon_code', 'freeshipping' );
	if ( empty( $code ) ) return $classes;
	if ( WC()->cart->has_discount( $code ) ) {
		$classes[] = 'lprq-auto-coupon-applied';
		$classes[] = 'lprq-auto-coupon-' . sanitize_html_class( strtolower( $code ) );
	}
	return $classes;
}, 10, 1 );

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
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) return;
	if ( ! is_cart() && ! is_checkout() ) return;
	$is_checkout = is_checkout() && ! is_cart();
	if ( $is_checkout ) {
		// Checkout-only fixes: payment method icon alignment, applied
		// coupon chip styling, etc.
		?>
		<style>
		/* Vertical-align all payment method icons on the same baseline.
		   AmEx default SVG/PNG has different aspect ratio than VISA + MC
		   so it floats higher without explicit alignment. */
		.woocommerce-checkout .wc-block-components-payment-method-icons,
		.woocommerce-checkout .wc-block-components-payment-method-label__icons,
		.woocommerce-checkout .payment-method__icons,
		.woocommerce-checkout .wc-block-checkout__payment-method-icons { display: flex !important; align-items: center !important; gap: 6px !important; flex-wrap: wrap !important; }
		.woocommerce-checkout .wc-block-components-payment-method-icons img,
		.woocommerce-checkout .wc-block-components-payment-method-label__icons img,
		.woocommerce-checkout .payment-method__icons img,
		.woocommerce-checkout .wc-block-checkout__payment-method-icons img { display: block !important; vertical-align: middle !important; height: 24px !important; width: auto !important; max-width: none !important; object-fit: contain !important; margin: 0 !important; }
		/* Hide non-selected shipping methods on the checkout REVIEW step
		   so customer sees only the method they picked, not the full list
		   (cart page still shows all options). Targets WC Block + classic
		   markup variations. Uses :has() selector for modern browsers. */
		.woocommerce-checkout .wc-block-components-shipping-rates-control__package-item:not(:has(input:checked)),
		.woocommerce-checkout-review-order .shipping_method li:not(.shipping-method-selected):not(:has(input:checked)),
		.woocommerce-checkout #shipping_method li:not(.shipping-method-selected):not(:has(input:checked)),
		.woocommerce-checkout-review-order tr.shipping ul#shipping_method li:not(:has(input:checked)) { display: none !important; }
		/* Privacy notice ("Your personal data...") spacing + font matching.
		   WC adds this at the end of checkout fields and the default
		   styling often differs from preceding paragraph text. */
		.woocommerce-checkout .woocommerce-privacy-policy-text,
		.woocommerce-checkout .wc-block-checkout__terms,
		.woocommerce-checkout .wc-block-checkout-terms,
		.woocommerce-checkout .privacy-policy,
		.woocommerce-checkout p.privacy,
		.woocommerce-checkout .woocommerce-terms-and-conditions-wrapper { margin-top: 24px !important; padding-top: 16px !important; font-size: 14px !important; line-height: 1.6 !important; color: #4a5d68 !important; }
		.woocommerce-checkout .woocommerce-privacy-policy-text p,
		.woocommerce-checkout .wc-block-checkout__terms p { font-size: 14px !important; line-height: 1.6 !important; margin: 0 0 12px !important; color: #4a5d68 !important; }
		</style>
		<?php
	}
	if ( ! is_cart() ) return;
	?>
	<style>
	/* Sego Lily cart page styling (injected by routine quiz plugin) */
	.woocommerce-cart .entry-content { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
	.woocommerce-cart .woocommerce { font-family: Georgia, 'Times New Roman', serif; }
	.woocommerce-cart .woocommerce-notices-wrapper { max-width: 760px; margin: 0 auto 16px; }

	/* Cart product table: no outer border, no row background, no
	   table-level container styling. Each cart_item TR gets its own
	   card via the mobile @media block below. Above 600px width,
	   uses standard table layout. */
	.woocommerce-cart .shop_table,
	.woocommerce-cart .woocommerce-cart-form .shop_table { border: none !important; margin-bottom: 28px; width: 100%; border-collapse: separate !important; border-spacing: 0 !important; background: transparent !important; box-shadow: none !important; outline: none !important; }
	.woocommerce-cart .shop_table th { background: transparent !important; color: #2C2C2C; font-family: Georgia, 'Times New Roman', serif; font-size: 12px; text-transform: uppercase; letter-spacing: 1.2px; padding: 14px 16px; border: none !important; border-bottom: 1px solid #E8E2D6 !important; text-align: left; font-weight: 700; }
	.woocommerce-cart .shop_table td { padding: 16px; border: none !important; border-bottom: 1px solid #E8E2D6 !important; vertical-align: middle; background: transparent !important; }
	/* Cart-page form wrapper resets so any theme-level form/section
	   border doesn't add a wrapper outline around the cards. */
	.woocommerce-cart .woocommerce { background: transparent !important; border: none !important; box-shadow: none !important; padding: 0 !important; }
	.woocommerce-cart form.woocommerce-cart-form { background: transparent !important; border: none !important; box-shadow: none !important; padding: 0 !important; }
	.woocommerce-cart .woocommerce-cart-form__contents { background: transparent !important; border: none !important; box-shadow: none !important; }
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

	/* Cart totals container -- widen on desktop so internal label/value
	   columns have room to breathe. .cart-collaterals is the parent
	   wrapper WC uses; expanded width prevents the squish-wrap. */
	.woocommerce-cart .cart-collaterals { width: 100% !important; max-width: 540px !important; float: none !important; margin: 28px auto 0 auto !important; }
	.woocommerce-cart .cart-collaterals .cart_totals { width: 100% !important; float: none !important; }
	.woocommerce-cart .cart_totals { background: #F7F6F3 !important; padding: 32px 32px !important; border-radius: 12px !important; border: 1px solid #E8E2D6 !important; margin-top: 28px !important; box-shadow: none !important; }
	.woocommerce-cart .cart_totals h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 22px; color: #2C2C2C; margin: 0 0 18px; font-weight: 600; }
	.woocommerce-cart .cart_totals table { width: 100% !important; background: transparent !important; border-collapse: collapse !important; border: none !important; box-shadow: none !important; table-layout: fixed !important; }
	.woocommerce-cart .cart_totals table * { box-shadow: none !important; }
	/* KILL all per-row borders inside cart_totals so no nested mini-boxes
	   appear. Single bottom-divider between rows for readability. */
	.woocommerce-cart .cart_totals tr { background: transparent !important; border: none !important; outline: none !important; box-shadow: none !important; }
	.woocommerce-cart .cart_totals th,
	.woocommerce-cart .cart_totals td { padding: 14px 0 !important; border: none !important; border-bottom: 1px solid #E8E2D6 !important; background: transparent !important; box-shadow: none !important; outline: none !important; vertical-align: top !important; }
	.woocommerce-cart .cart_totals th { color: #4a5d68; font-weight: 600; font-family: Georgia, 'Times New Roman', serif; text-align: left; width: 40% !important; padding-right: 16px !important; }
	.woocommerce-cart .cart_totals td { width: 60% !important; text-align: right; word-wrap: break-word; }
	.woocommerce-cart .cart_totals .order-total .amount { font-size: 22px; color: #2C2C2C; font-weight: 700; }
	.woocommerce-cart .cart_totals tr:last-child th,
	.woocommerce-cart .cart_totals tr:last-child td { border-bottom: none !important; }

	/* ================================================================
	   These styles apply ON ALL VIEWPORTS (not scoped to mobile).
	   Previously these were inside the @media block, so desktop saw
	   the WC defaults (Initial Shipment label, pink Change address
	   link, awkward shipping radio layout). Now they apply everywhere.
	   ================================================================ */

	/* Shipping options: radio + label left-aligned in a clean list. */
	.woocommerce-cart .cart_totals #shipping_method,
	.woocommerce-cart .cart_totals .woocommerce-shipping-methods { list-style: none !important; padding: 0 !important; margin: 0 0 12px !important; }
	.woocommerce-cart .cart_totals #shipping_method li,
	.woocommerce-cart .cart_totals .woocommerce-shipping-methods li { display: flex !important; align-items: center !important; gap: 8px !important; padding: 4px 0 !important; text-align: left !important; margin: 0 !important; }
	.woocommerce-cart .cart_totals #shipping_method li input[type="radio"] { margin: 0 !important; flex-shrink: 0 !important; }
	.woocommerce-cart .cart_totals #shipping_method li label { margin: 0 !important; flex: 1 !important; text-align: left !important; font-weight: 500 !important; }
	.woocommerce-cart .cart_totals .woocommerce-shipping-destination { margin: 12px 0 6px !important; font-size: 13px !important; color: #4a5d68 !important; text-align: left !important; }
	/* Brand "Change address" / shipping-calculator link teal everywhere. */
	.woocommerce-cart .cart_totals .shipping-calculator-button,
	.woocommerce-cart .cart_totals .shipping-calculator-button-toggle,
	.woocommerce-cart .cart_totals .shipping-calculator-form-wrapper a { color: #386174 !important; text-decoration: underline !important; font-size: 13px !important; }
	/* Hide WC Subs "Initial Shipment:" / "Recurring Total:" label text;
	   show "Shipping" instead. (CSS fallback in case the gettext filter
	   doesn't fire for the current rendering context.) */
	.woocommerce-cart .cart_totals tr.shipping > th,
	.woocommerce-cart .cart_totals tr.recurring-total > th { font-size: 0 !important; line-height: 1 !important; }
	.woocommerce-cart .cart_totals tr.shipping > th:before,
	.woocommerce-cart .cart_totals tr.recurring-total > th:before { content: 'Shipping' !important; font-size: 13px !important; font-weight: 600 !important; color: #4a5d68 !important; text-transform: uppercase !important; letter-spacing: 1px !important; display: inline-block !important; line-height: 1.4 !important; }

	/* Coupon row Remove link: keep on one line, teal brand color. WC
	   default pink color leaks from theme link styles. */
	.woocommerce-cart .cart_totals a.woocommerce-remove-coupon,
	.woocommerce-cart .cart_totals .woocommerce-remove-coupon,
	.woocommerce-cart .cart_totals tr.cart-discount a,
	.woocommerce-cart .cart_totals tr.coupon-freeshipping a { color: #386174 !important; white-space: nowrap !important; text-decoration: underline !important; font-weight: 600 !important; font-size: 13px !important; }

	/* Change address link + shipping calculator + truck icon: teal brand color. */
	.woocommerce-cart .cart_totals a.shipping-calculator-button,
	.woocommerce-cart .cart_totals .shipping-calculator-button,
	.woocommerce-cart .cart_totals .shipping-calculator-button-toggle,
	.woocommerce-cart .cart_totals .shipping-calculator-form-wrapper a,
	.woocommerce-cart .cart_totals .woocommerce-shipping-destination a,
	.woocommerce-cart .cart_totals .woocommerce-shipping-calculator a { color: #386174 !important; text-decoration: underline !important; }
	.woocommerce-cart .cart_totals a.shipping-calculator-button svg,
	.woocommerce-cart .cart_totals a.shipping-calculator-button img { filter: hue-rotate(180deg) saturate(0) brightness(0.5) sepia(1) hue-rotate(155deg) !important; }

	.woocommerce-cart .wc-proceed-to-checkout { margin-top: 22px; padding: 0 !important; }
	.woocommerce-cart .checkout-button,
	.woocommerce-cart a.checkout-button { display: block !important; width: 100% !important; padding: 16px 24px !important; background: #386174 !important; color: #ffffff !important; text-align: center !important; border-radius: 8px !important; font-family: Georgia, 'Times New Roman', serif !important; font-size: 16px !important; font-weight: 700 !important; text-decoration: none !important; box-shadow: 0 4px 14px rgba(56, 97, 116, 0.28) !important; transition: all 0.15s ease !important; letter-spacing: 0.4px !important; box-sizing: border-box !important; border: none !important; }
	.woocommerce-cart .checkout-button:hover { background: #2a4a5a !important; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(56, 97, 116, 0.35) !important; }

	@media (max-width: 600px) {
		.woocommerce-cart .entry-content { padding: 16px 12px 36px; }
		.woocommerce-cart .shop_table thead { display: none; }
		/* Clean block layout for mobile cart cards. No grid, no internal
		   dividers. Each section is a stacked block with a small label
		   above and the value below. */
		.woocommerce-cart .shop_table tbody tr.cart_item { display: block !important; margin-bottom: 16px !important; padding: 24px 18px !important; background: #F7F6F3 !important; border: 1px solid #E8E2D6 !important; border-radius: 12px !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td { display: block !important; width: 100% !important; padding: 10px 0 !important; border: none !important; text-align: left !important; box-sizing: border-box !important; float: none !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td:before { content: attr(data-title) !important; display: block !important; font-weight: 700 !important; color: #8A9499 !important; text-transform: uppercase !important; font-size: 11px !important; letter-spacing: 1px !important; margin-bottom: 6px !important; float: none !important; text-align: left !important; }
		/* Remove icon (X) at top of card, no label. */
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-remove { text-align: right !important; padding: 0 0 8px !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-remove:before { content: '' !important; display: none !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-remove a { font-size: 22px !important; color: #B8A98C !important; text-decoration: none !important; }
		/* Product image: centered, no label. */
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-thumbnail { text-align: center !important; padding: 0 0 20px !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-thumbnail:before { content: '' !important; display: none !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-thumbnail img,
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-thumbnail a img,
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-thumbnail .attachment-woocommerce_thumbnail { display: block !important; visibility: visible !important; max-width: 140px !important; width: 140px !important; height: auto !important; margin: 0 auto !important; border-radius: 10px !important; }
		/* Product name: big bold title, then variation list stacked beneath. */
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name { padding: 0 0 16px !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name:before { content: '' !important; display: none !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name > a { font-size: 18px !important; font-weight: 700 !important; color: #2C2C2C !important; text-decoration: none !important; display: block !important; margin-bottom: 12px !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name .variation { margin: 0 !important; padding: 0 !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name .variation dt { font-weight: 700 !important; color: #8A9499 !important; text-transform: uppercase !important; font-size: 10px !important; letter-spacing: 0.8px !important; margin: 10px 0 2px 0 !important; padding: 0 !important; display: block !important; float: none !important; clear: both !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name .variation dt:first-child { margin-top: 0 !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name .variation dd { margin: 0 !important; padding: 0 !important; font-size: 14px !important; color: #2C2C2C !important; font-weight: 500 !important; display: block !important; float: none !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-name .variation dd p { margin: 0 !important; }
		/* Price + Subtotal: bold value, larger, on its own line under the label. */
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-price,
		.woocommerce-cart .shop_table tbody tr.cart_item td.product-subtotal { font-size: 18px !important; font-weight: 700 !important; color: #2C2C2C !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.actions { padding: 0 !important; }
		.woocommerce-cart .shop_table tbody tr.cart_item td.actions:before { content: '' !important; display: none !important; }
		/* Coupon + Update Cart row (NOT a cart_item, separate TR with td.actions
		   that contains the coupon code form + update button). SCOPED to
		   the woocommerce-cart-form shop_table so it does NOT hit the
		   cart_totals shop_table inside .cart-collaterals (which has its
		   own TRs for subtotal/shipping/total that should stay flat). */
		.woocommerce-cart .woocommerce-cart-form .shop_table tbody tr:not(.cart_item) { display: block !important; margin-top: 20px !important; margin-bottom: 16px !important; padding: 22px 18px !important; background: #F7F6F3 !important; border: 1px solid #E8E2D6 !important; border-radius: 12px !important; }
		.woocommerce-cart .woocommerce-cart-form .shop_table tbody tr:not(.cart_item) td { display: block !important; padding: 0 !important; border: none !important; background: transparent !important; }
		.woocommerce-cart .woocommerce-cart-form .shop_table tbody tr:not(.cart_item) td:before { content: '' !important; display: none !important; }
		.woocommerce-cart .coupon { flex-direction: column; align-items: stretch; }
		.woocommerce-cart .coupon input[name="coupon_code"] { min-width: 0; width: 100%; }
		.woocommerce-cart .button[name="apply_coupon"],
		.woocommerce-cart .button[name="update_cart"] { width: 100%; letter-spacing: 0.3px !important; }
		.woocommerce-cart .cart_totals { padding: 20px !important; }
		.woocommerce-cart .cart-collaterals { max-width: 100% !important; margin: 20px 0 0 0 !important; }
		.woocommerce-cart .cart_totals th { width: 45% !important; }
		.woocommerce-cart .cart_totals td { width: 55% !important; }
	}

	/* Hide coupon description / WC default "Free shipping coupon" label
	   inline beside the code in WC Checkout Block, classic checkout, and
	   cart totals. Customer should see just "FREESHIPPING", not
	   "FREESHIPPINGFree shipping coupon" jammed together. Covers many
	   class variations across WC versions. */
	.wc-block-components-totals-coupon-summary__chip-description,
	.wc-block-components-totals-coupon-summary__chip-discount,
	.wc-block-components-totals-coupon-summary__description,
	.wc-block-coupon-code-applied__description,
	.cart-discount .description,
	.coupon-description,
	.wc-block-components-totals-coupon__discount-rate { display: none !important; }
	/* WC Block coupon summary chip layout fallback: if our description
	   selectors miss but the chip still has multiple text spans, hide
	   anything after the first child. */
	.wc-block-components-totals-coupon-summary__chip > *:not(.wc-block-components-totals-coupon-summary__chip-code):not(.wc-block-components-chip__remove):not(button) {
		display: none !important;
	}
	</style>
	<script>
	(function() {
		// Populate the visible "Coupon code" input field with the auto-applied
		// code so customers can see the discount is in effect, not just feel
		// confused that the field is empty. The body class lprq-auto-coupon-applied
		// is set in PHP only when WC()->cart->has_discount() confirms the
		// coupon is actually on the cart.
		function fillCouponField() {
			if (!document.body.classList.contains('lprq-auto-coupon-applied')) return;
			var field = document.querySelector('input[name="coupon_code"]');
			if (!field || field.value) return;
			// Find the applied code by reading the lprq-auto-coupon-{code} class
			var match = (document.body.className.match(/lprq-auto-coupon-([a-z0-9_-]+)/) || [])[1];
			if (!match) return;
			field.value = match.toUpperCase();
			field.style.color = '#386174';
			field.style.fontWeight = '600';
			field.style.background = '#F7F6F3';
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fillCouponField);
		} else {
			fillCouponField();
		}
	})();
	</script>
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
/**
 * URL-based coupon auto-apply. Append ?apply_coupon=FREESHIPPING (or any
 * coupon code) to any URL on the site. The coupon applies on next cart
 * recalc. Standard pattern Stripe + many ecom platforms use; lets LV
 * embed pre-applied coupons in email CTAs without depending on the
 * time-window fallback below.
 *
 * Example: https://segolilyskincare.com/cart/?apply_coupon=FREESHIPPING
 */
add_action( 'wp_loaded', function() {
	if ( empty( $_GET['apply_coupon'] ) && empty( $_GET['coupon'] ) ) return;
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
	$code = sanitize_text_field( wp_unslash( $_GET['apply_coupon'] ?? $_GET['coupon'] ?? '' ) );
	if ( empty( $code ) ) return;
	if ( WC()->cart->has_discount( $code ) ) return;
	if ( ! class_exists( 'WC_Coupon' ) ) return;
	$coupon = new WC_Coupon( $code );
	if ( ! $coupon->get_id() ) return;
	$notices_before = function_exists( 'wc_get_notices' ) ? wc_get_notices() : array();
	WC()->cart->apply_coupon( $code );
	if ( function_exists( 'wc_set_notices' ) ) {
		wc_set_notices( $notices_before );
	}
	WC()->cart->calculate_totals();
}, 30 );

/**
 * Replace WC Subscriptions "Initial Shipment" / "Recurring Total" label
 * text with just "Shipping". Variable-subscription products in cart cause
 * WC Subs to wrap shipping labels with "Initial Shipment" prefix even
 * when the customer picked the One-Time Purchase variation. The text
 * is i18n-translated via WC Subs's text domain, so the gettext filter
 * catches it regardless of where it's rendered (cart, checkout, etc.).
 */
add_filter( 'gettext', function( $translation, $text, $domain ) {
	if ( $domain !== 'woocommerce-subscriptions' ) return $translation;
	if ( $text === 'Initial Shipment' )           return 'Shipping';
	if ( $text === 'Initial Shipment:' )          return 'Shipping:';
	if ( $text === 'Recurring Total' )            return 'Recurring';
	if ( $text === 'Recurring Total:' )           return 'Recurring:';
	return $translation;
}, 10, 3 );

add_filter( 'gettext_with_context', function( $translation, $text, $context, $domain ) {
	if ( $domain !== 'woocommerce-subscriptions' ) return $translation;
	if ( $text === 'Initial Shipment' )           return 'Shipping';
	if ( $text === 'Initial Shipment:' )          return 'Shipping:';
	return $translation;
}, 10, 4 );

/**
 * Force WC to display the coupon CODE (e.g., "FREESHIPPING") instead of
 * the coupon description (e.g., "Free shipping coupon") in the cart /
 * checkout totals applied-coupons list. Cleaner UX: customer sees the
 * actual code that's active.
 *
 * WC core hardcodes "Free shipping coupon" for free_shipping type
 * coupons in wc_cart_totals_coupon_label() regardless of the coupon's
 * description field. This filter overrides that to the actual code.
 */
add_filter( 'woocommerce_cart_totals_coupon_label', function( $label, $coupon ) {
	if ( is_object( $coupon ) && method_exists( $coupon, 'get_code' ) ) {
		return strtoupper( $coupon->get_code() );
	}
	return $label;
}, 10, 2 );

/**
 * WC Checkout Block + Store API uses a different label generation path
 * that doesn't go through wc_cart_totals_coupon_label. Override the
 * label at the Store API serialization level by filtering the cart
 * coupon schema response.
 */
add_filter( 'woocommerce_store_api_cart_coupon_data', function( $data, $coupon_code ) {
	if ( is_array( $data ) ) {
		$data['label'] = strtoupper( $coupon_code );
		$data['description'] = '';
	} elseif ( is_object( $data ) ) {
		$data->label = strtoupper( $coupon_code );
		$data->description = '';
	}
	return $data;
}, 10, 2 );

/**
 * WC core also generates the discount-amount HTML for the cart row.
 * Strip the description from there too so the row reads cleanly.
 */
add_filter( 'woocommerce_cart_totals_coupon_html', function( $html, $coupon ) {
	if ( is_object( $coupon ) && method_exists( $coupon, 'get_code' ) ) {
		// Default WC builds: [Remove] link wrapped in HTML. Replace any
		// "Free shipping coupon" text with empty.
		$html = str_replace( 'Free shipping coupon', '', $html );
		$html = str_replace( 'free shipping coupon', '', $html );
	}
	return $html;
}, 10, 2 );

/**
 * Clear the FREESHIPPING coupon's post_excerpt (where WC stores the
 * "description") directly in the database on EVERY init. The earlier
 * one-shot-with-flag approach failed silently in v1.13.40 (flag was
 * set but DB update didn't take). This version runs every page load:
 * a single SQL query checks if the excerpt is set; if so, clears it.
 * Negligible overhead per request.
 *
 * Resolves "FREESHIPPING Free shipping coupon" jammed together in the
 * checkout coupon chip. Source-of-truth fix that beats every display
 * code path (classic cart, WC Block, Store API REST, custom themes).
 */
add_action( 'init', function() {
	if ( ! function_exists( 'wc_get_coupon_id_from_code' ) ) return;
	$coupon_codes = apply_filters( 'lprq_coupons_to_clear_description', array( 'freeshipping' ) );
	global $wpdb;
	foreach ( $coupon_codes as $code ) {
		$cid = wc_get_coupon_id_from_code( $code );
		if ( ! $cid ) continue;
		// One SQL UPDATE only if the field is non-empty (cheap)
		$current = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_excerpt FROM {$wpdb->posts} WHERE ID = %d LIMIT 1", $cid
		) );
		if ( $current !== '' && $current !== null ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'post_excerpt' => '' ),
				array( 'ID' => $cid ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $cid );
		}
	}
}, 100 );

/**
 * Strip the coupon description ("Free shipping coupon") from EVERY frontend
 * rendering. WC stores it as post_excerpt on the shop_coupon CPT, and
 * different display contexts (classic cart, checkout block, custom
 * checkout markup by themes) all pull it via different filters. To cover
 * them all reliably, filter the post_excerpt field for shop_coupon posts
 * on frontend cart/checkout contexts.
 */
add_filter( 'get_the_excerpt', function( $excerpt, $post = null ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return $excerpt;
	if ( ! $post ) $post = get_post();
	if ( $post && isset( $post->post_type ) && $post->post_type === 'shop_coupon' ) return '';
	return $excerpt;
}, 10, 2 );

/**
 * Also filter the raw post object's post_excerpt accessor on coupon posts.
 * Some checkout markup reads $coupon->get_description() which goes
 * through this path. Returns empty so the customer never sees a
 * description regardless of which display layer reads it.
 */
add_filter( 'woocommerce_coupon_get_description', function( $description, $coupon ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return $description;
	return '';
}, 10, 2 );

/**
 * Final fallback: directly clear post_excerpt on shop_coupon posts when
 * they're fetched on frontend cart/checkout. This catches any code path
 * that reads $post->post_excerpt directly without going through filters.
 */
add_action( 'the_post', function( $post ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( isset( $post->post_type ) && $post->post_type === 'shop_coupon' ) {
		$post->post_excerpt = '';
	}
}, 10, 1 );

add_action( 'template_redirect', function() {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) return;
	if ( ! is_cart() && ! is_checkout() ) return;
	if ( WC()->cart->is_empty() ) return;

	$mt    = new DateTimeZone( 'America/Denver' );
	$now   = new DateTime( 'now', $mt );
	$start = new DateTime( '2026-05-23 09:00', $mt );
	$end   = new DateTime( '2026-05-26 23:59', $mt );
	if ( $now < $start || $now > $end ) return;

	$code = apply_filters( 'lprq_auto_coupon_code', 'freeshipping' );
	if ( empty( $code ) ) return;
	if ( WC()->cart->has_discount( $code ) ) return;

	if ( ! class_exists( 'WC_Coupon' ) ) return;
	$coupon = new WC_Coupon( $code );
	if ( ! $coupon->get_id() ) return;

	// Apply with notice suppression so the customer never sees a confusing
	// auto-apply notice on every cart load. The coupon is still applied;
	// only the notice toast is hidden.
	$notices_before = function_exists( 'wc_get_notices' ) ? wc_get_notices() : array();
	WC()->cart->apply_coupon( $code );
	if ( function_exists( 'wc_set_notices' ) ) {
		wc_set_notices( $notices_before );
	}
	WC()->cart->calculate_totals();
}, 5 );

/**
 * Memorial Day 2026 free-shipping callout has been intentionally disabled.
 * The customer already saw the offer in the email they clicked from, and
 * the auto-applied coupon shows in the cart totals after they add. A
 * separate callout card on the results page just competes with the
 * primary "Add to cart" CTAs.
 */
add_filter( 'lprq_results_callout', function( $existing ) {
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
		'moxie'    => $base . 'moxie_bourbon_coffee_1x.webp',
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

<?php
/**
 * Routine recommendation engine.
 *
 * Default recommendations are generic 2-product pairs. Override per-client
 * by hooking the `lprq_recommendation` filter and returning a custom pair.
 *
 * The shipped defaults match Sego Lily Skincare's product line (Ageless +
 * Renewal tallow butters with sensitivity-aware scent routing). Future
 * clients override with their own product names + shop URLs.
 *
 * @package LPQuizSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Recommendations {

	/**
	 * Return a 2-product pair for the given quiz answers.
	 *
	 * @param string $skin_concern  Skin concern answer.
	 * @param string $frustration   Frustration answer.
	 * @return array { primary, secondary, why }
	 */
	public static function pair_for( $skin_concern, $frustration = '' ) {
		$default = self::default_pair( $skin_concern, $frustration );
		$primary_id   = $default['primary']['product_id']   ?? 0;
		$secondary_id = $default['secondary']['product_id'] ?? 0;
		$default['add_both_url'] = self::add_routine_url( $primary_id, $secondary_id );
		return apply_filters( 'lprq_recommendation', $default, $skin_concern, $frustration );
	}

	private static function default_pair( $skin_concern, $frustration ) {
		$is_sensitive  = ( $skin_concern === 'Redness & sensitivity' );
		$is_simplifier = in_array( $frustration, array( 'Too many products', 'Just want something simple' ), true );

		$why = self::why_for( $skin_concern, $frustration );

		switch ( $skin_concern ) {

			case 'Wrinkles & dark spots':
				// Default to scented Mandarin Orange (no sensitivity signal here);
				// Baby + Mom Pure Butter routes through the Sensitivity path only.
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => self::renewal( 'mandarin-orange' ),
					'why'       => $why,
				);

			case 'Dryness & tightness':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => self::ageless( 'honey-creme' ),
					'why'       => $why,
				);

			case 'Redness & sensitivity':
				// The one path where Baby + Mom Pure Butter is the right answer:
				// it IS our most reactive-safe formulation in the line.
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'rosewood-lavender' ),
					'why'       => $why,
				);

			case 'Breakouts':
				// Adult acne is inflammation, not reactivity — scented Mandarin
				// Orange works (still non-comedogenic), no need for the
				// Baby + Mom positioning.
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => self::ageless( 'honey-creme' ),
					'why'       => $why,
				);

			default:
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => self::renewal( 'mandarin-orange' ),
					'why'       => $why,
				);
		}
	}

	/**
	 * Per-combination "why" copy. 4 skin concerns x 4 frustrations = 16
	 * product-specific variants. Each one validates the user's lived pain
	 * deeply in the first 2-3 sentences (no clinical detachment), explains
	 * the mechanism, then introduces the products as the no-brainer answer.
	 * Both products are named explicitly. No em dashes. No age callouts.
	 */
	private static function why_for( $skin_concern, $frustration ) {
		$map = array(
			'Wrinkles & dark spots' => array(
				'Nothing works long enough'   => 'You buy the cream. It works for a few weeks. Your skin slides back. You try another, same pattern. The issue isn&rsquo;t your routine, it&rsquo;s the chemistry: stabilized actives can&rsquo;t replace the lipids your skin actually needs. <strong>Ageless Honey Creme</strong> is whipped tallow rich in vitamins A, D, E, K, the exact nutrients your skin&rsquo;s been making less of. <strong>Renewal Mandarin Orange</strong> at night locks it in. Softer texture in 4 to 6 weeks.',
				'Too many products'           => 'Your bathroom shelf has a serum, a cream, an eye cream, a treatment, probably a retinol. Each one fighting the same problem with a different active. Your skin isn&rsquo;t confused, it&rsquo;s overloaded. <strong>Ageless Honey Creme</strong> is the lipid replacement all those actives are trying to mimic. <strong>Renewal Mandarin Orange</strong> at night locks it in. Two jars replace the shelf.',
				"Don't trust ingredients"     => 'You read every label. You can&rsquo;t pronounce half of them. And the ones you CAN pronounce are usually solvents and stabilizers. <strong>Ageless Honey Creme</strong> has six ingredients: grass-fed tallow, raw honey, olive oil, vitamin E, jojoba, beeswax. <strong>Renewal Mandarin Orange</strong> is similar. Your skin recognizes all of them.',
				'Just want something simple'  => 'You don&rsquo;t want to learn skincare chemistry. You want products that work without a 5-step routine. <strong>Ageless Honey Creme</strong> rebuilds the lipid barrier in the morning. <strong>Renewal Mandarin Orange</strong> locks it in at night. Two jars, twice a day. That&rsquo;s it.',
			),
			'Dryness & tightness' => array(
				'Nothing works long enough'   => 'You apply moisturizer. An hour later your skin feels tight again. Two hours later you&rsquo;re reapplying. Most lotions are water-based, and water evaporates. Your skin needs fats to hold water in. <strong>Renewal Mandarin Orange</strong> is whipped tallow plus organic oils, the fat your skin recognizes. <strong>Ageless Honey Creme</strong> on top reinforces the barrier overnight. Moisture stays 8 hours, not one.',
				'Too many products'           => 'Hyaluronic serum, hydrating toner, gel cream, sleeping mask. You stack four products to feel moisturized for a few hours. The stack doesn&rsquo;t fix the actual problem: your skin can&rsquo;t hold water because the lipids are missing. <strong>Renewal Mandarin Orange</strong> puts those lipids back. <strong>Ageless Honey Creme</strong> at night reinforces. Softer skin in 2 to 3 weeks.',
				"Don't trust ingredients"     => 'Most moisturizers list 20+ ingredients. Most of them are solvents, stabilizers, fillers, not what your skin actually absorbs. <strong>Renewal Mandarin Orange</strong> has five: tallow, organic oils, vitamin E, beeswax, mandarin essential oil. <strong>Ageless Honey Creme</strong> is similar. Nothing your skin won&rsquo;t recognize.',
				'Just want something simple'  => 'Tight skin means your barrier can&rsquo;t hold moisture. The fix is fats your skin recognizes, not more products. <strong>Renewal Mandarin Orange</strong> in the morning (whipped tallow, absorbs in 30 seconds). <strong>Ageless Honey Creme</strong> at night (vitamins A, D, E, K). Done.',
			),
			'Redness & sensitivity' => array(
				'Nothing works long enough'   => 'You find something that works. A week in, your skin flares. Another product, another flare. The products themselves are the issue: 15 to 30 ingredients each, your skin reacts to one of them, you don&rsquo;t know which. <strong>Baby + Mom Pure Butter</strong> has five (Holly named it for newborn skin, but it&rsquo;s our most reactive-safe formulation, period). <strong>Ageless Rosewood Lavender</strong> is the gentlest scented option in the line.',
				'Too many products'           => 'Your routine keeps growing because nothing alone works. But every new product is another 15 to 30 ingredients your skin has to recognize. <strong>Baby + Mom Pure Butter</strong> has five total, all food-grade. <strong>Ageless Rosewood Lavender</strong> has six. Reactive skin doesn&rsquo;t need more, it needs less.',
				"Don't trust ingredients"     => 'You read every label. Most labels make you nervous. <strong>Baby + Mom Pure Butter</strong> has five ingredients you&rsquo;d recognize from a kitchen: whipped tallow, olive oil, beeswax, vitamin E, jojoba. <strong>Ageless Rosewood Lavender</strong> is similar. Safe for newborns, rosacea, post-procedure.',
				'Just want something simple'  => 'Reactive skin needs the fewest possible ingredients, full stop. <strong>Baby + Mom Pure Butter</strong> has five (Holly named it for newborns, but it&rsquo;s the gentlest formulation in the line for any sensitive adult skin). <strong>Ageless Rosewood Lavender</strong> if you want a daytime layer.',
			),
			'Breakouts' => array(
				'Nothing works long enough'   => 'Your acne products work for a week. Your skin flares. You try a stronger one. Same thing. The cycle is the products themselves: stripping treatments calm breakouts short-term but inflame the barrier long-term, and inflamed barriers cause more breakouts. <strong>Renewal Mandarin Orange</strong> calms the barrier (whipped tallow, non-comedogenic). <strong>Ageless Honey Creme</strong> on top for texture repair.',
				'Too many products'           => 'Cleanser, toner, treatment, serum, spot cream. Your face has had a 5-step war declared on it. The war is making it worse: each strip-and-treat product inflames the barrier, which makes oil overproduction worse, which causes more breakouts. <strong>Renewal Mandarin Orange</strong> calms (whipped tallow, non-comedogenic). <strong>Ageless Honey Creme</strong> supports texture repair.',
				"Don't trust ingredients"     => 'Most acne products list sulfates, retinoids, salicylic acid, parabens. Things you wouldn&rsquo;t put in your kitchen. <strong>Renewal Mandarin Orange</strong> has six ingredients, mostly tallow and organic oils, all food-grade. <strong>Ageless Honey Creme</strong> is similar. Non-comedogenic, won&rsquo;t trigger reactive flare.',
				'Just want something simple'  => 'Adult breakouts are a barrier problem, not an oil problem. Stop fighting your skin with actives. <strong>Renewal Mandarin Orange</strong> calms the barrier (whipped tallow, non-comedogenic). <strong>Ageless Honey Creme</strong> supports texture repair. Two jars.',
			),
		);

		$default = 'A clean two-product routine that fits most starting points. <strong>Ageless Honey Creme</strong> rebuilds your lipid barrier through the day. <strong>Renewal Mandarin Orange</strong> locks in deeper moisture overnight.';

		if ( ! isset( $map[ $skin_concern ] ) ) {
			return $default;
		}
		$concern_map = $map[ $skin_concern ];
		if ( isset( $concern_map[ $frustration ] ) ) {
			return $concern_map[ $frustration ];
		}
		// Normalize curly apostrophe / HTML entity for tolerant lookup.
		$normalized = str_replace( array( "\xE2\x80\x99", "&rsquo;" ), "'", $frustration );
		foreach ( $concern_map as $key => $value ) {
			$norm_key = str_replace( array( "\xE2\x80\x99", "&rsquo;" ), "'", $key );
			if ( $norm_key === $normalized ) {
				return $value;
			}
		}
		return reset( $concern_map );
	}
	private static function ageless( $scent ) {
		$scents = array(
			'honey-creme'       => 'Honey Creme',
			'rosewood-lavender' => 'Rosewood Lavender',
			'citrus-breeze'     => 'Citrus Breeze',
			'mango'             => 'Mango',
		);
		$wc_slug = 'ageless-tallow-butter';
		$wc_id   = self::wc_product_id( $wc_slug );
		return array(
			'slug'              => 'ageless-' . $scent,
			'name'              => 'Ageless Tallow Butter',
			'scent'             => $scents[ $scent ] ?? 'Honey Creme',
			'blurb'             => 'Anti-aging. Face, body, hands.',
			'shop_url'          => self::shop_url( $wc_slug ),
			'add_to_cart_url'   => self::add_to_cart_url( $wc_id ),
			'product_id'        => $wc_id,
			'image_url'         => apply_filters( 'lprq_product_image', '', 'ageless', $scent ),
		);
	}

	private static function renewal( $scent ) {
		$scents = array(
			'mandarin-orange'   => 'Mandarin Orange',
			'cardamom-primrose' => 'Cardamom Primrose',
			'cherry'            => 'Cherry',
			'unscented'         => 'Unscented',
		);
		// Unscented is sold as a separate WC product (baby-mom-pure-butter), not a
		// scent variation of renewal-tallow-butter. Route accordingly.
		$wc_slug = ( $scent === 'unscented' ) ? 'baby-mom-pure-butter' : 'renewal-tallow-butter';
		$name    = ( $scent === 'unscented' ) ? 'Baby + Mom Pure Butter' : 'Renewal Tallow Butter';
		$wc_id   = self::wc_product_id( $wc_slug );
		return array(
			'slug'              => 'renewal-' . $scent,
			'name'              => $name,
			'scent'             => $scents[ $scent ] ?? 'Unscented',
			'blurb'             => 'Daily moisture. Tone, texture, problem skin.',
			'shop_url'          => self::shop_url( $wc_slug ),
			'add_to_cart_url'   => self::add_to_cart_url( $wc_id ),
			'product_id'        => $wc_id,
			'image_url'         => apply_filters( 'lprq_product_image', '', 'renewal', $scent ),
		);
	}

	/**
	 * Build the public product URL. WooCommerce default is /product/{slug}/.
	 */
	private static function shop_url( $product_slug ) {
		$url = home_url( '/product/' . $product_slug . '/' );
		return apply_filters( 'lprq_product_url', $url, $product_slug );
	}

	/**
	 * Resolve a WC product slug → product ID via WP post lookup.
	 * Caches the lookup in a static map for the request.
	 */
	private static function wc_product_id( $product_slug ) {
		static $cache = array();
		if ( isset( $cache[ $product_slug ] ) ) {
			return $cache[ $product_slug ];
		}
		$post = get_page_by_path( $product_slug, OBJECT, 'product' );
		$id   = $post ? (int) $post->ID : 0;
		$cache[ $product_slug ] = $id;
		return $id;
	}

	/**
	 * WC native add-to-cart URL.
	 * WC's WC_Form_Handler::add_to_cart_action hooks on wp_loaded and
	 * processes the `add-to-cart` query var on ANY page. Putting the param
	 * on /cart/ directly is unreliable (cart page can render before the
	 * cart-add logic completes). Putting it on home_url('/') is the canonical
	 * WC pattern — WC adds the item, then redirects per the
	 * "Redirect to cart after add" WC setting.
	 */
	private static function add_to_cart_url( $product_id ) {
		if ( ! $product_id ) {
			return home_url( '/cart/' );
		}
		return add_query_arg( 'add-to-cart', $product_id, home_url( '/' ) );
	}

	/**
	 * Build the URL for the plugin&rsquo;s "add routine" endpoint that adds both
	 * primary + secondary products in one request, then redirects to cart.
	 */
	public static function add_routine_url( $primary_id, $secondary_id ) {
		if ( ! $primary_id && ! $secondary_id ) {
			return home_url( '/cart/' );
		}
		return add_query_arg(
			array(
				'slrq_action' => 'add_routine',
				'p'           => (int) $primary_id,
				's'           => (int) $secondary_id,
			),
			home_url( '/' )
		);
	}
}

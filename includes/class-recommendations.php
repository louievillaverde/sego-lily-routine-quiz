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
	public static function pair_for( $skin_concern, $frustration = '', $product_count = '' ) {
		$default = self::default_pair( $skin_concern, $frustration, $product_count );
		// add_both_url removed in v1.13.0 — Holly's variable-subscription
		// products don't support multi-product URL cart-add.
		return apply_filters( 'lprq_recommendation', $default, $skin_concern, $frustration );
	}

	private static function default_pair( $skin_concern, $frustration, $product_count = '' ) {
		$is_sensitive  = ( $skin_concern === 'Redness & sensitivity' );
		$is_simplifier = in_array( $frustration, array( 'Too many products', 'Just want something simple' ), true );

		$why = self::why_for( $skin_concern, $frustration, $product_count );

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
	private static function why_for( $skin_concern, $frustration, $product_count = '' ) {
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

		// Normalize curly apostrophe / HTML entity for tolerant lookup.
		$normalized = str_replace( array( "\xE2\x80\x99", "&rsquo;" ), "'", $frustration );
		$matched    = null;
		if ( isset( $concern_map[ $frustration ] ) ) {
			$matched = $concern_map[ $frustration ];
		} else {
			foreach ( $concern_map as $key => $value ) {
				$norm_key = str_replace( array( "\xE2\x80\x99", "&rsquo;" ), "'", $key );
				if ( $norm_key === $normalized ) {
					$matched = $value;
					break;
				}
			}
		}
		if ( $matched === null ) {
			$matched = reset( $concern_map );
		}
		return $matched . self::product_count_tail( $product_count );
	}

	/**
	 * Append a personalized closing line based on the customer's stated
	 * product count. Different shelves need different framings of the
	 * "two jars" answer. Empty string if no product_count signal.
	 */
	private static function product_count_tail( $product_count ) {
		switch ( $product_count ) {
			case '1-3':
				return ' Since you keep it minimal already, this slots in without adding steps.';
			case '4-6':
				return ' These two consolidate the half of your shelf that&rsquo;s doing the actual work.';
			case '7+':
				return ' These two replace most of what&rsquo;s on your shelf right now.';
			default:
				return '';
		}
	}
	private static function ageless( $scent ) {
		$scents = array(
			'honey-creme'       => 'Honey Creme',
			'rosewood-lavender' => 'Rosewood Lavender',
			'citrus-breeze'     => 'Citrus Breeze',
			'mango'             => 'Mango',
		);
		$wc_slug   = 'ageless-tallow-butter';
		$wc_id     = self::wc_product_id( $wc_slug );
		$scent_lbl = $scents[ $scent ] ?? 'Honey Creme';
		$pdp_url   = self::pdp_url( $wc_slug, array( 'attribute_scent' => $scent_lbl ) );
		return array(
			'slug'              => 'ageless-' . $scent,
			'name'              => 'Ageless Tallow Butter',
			'scent'             => $scent_lbl,
			'blurb'             => 'Anti-aging. Face, body, hands.',
			'shop_url'          => $pdp_url,
			'add_to_cart_url'   => $pdp_url,
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
		// Unscented = baby-mom-pure-butter (separate WC product). Other scents
		// = renewal-tallow-butter with scent pre-selected.
		$is_unscented = ( $scent === 'unscented' );
		$wc_slug      = $is_unscented ? 'baby-mom-pure-butter' : 'renewal-tallow-butter';
		$name         = $is_unscented ? 'Baby + Mom Pure Butter' : 'Renewal Tallow Butter';
		$wc_id        = self::wc_product_id( $wc_slug );
		$scent_lbl    = $scents[ $scent ] ?? 'Unscented';
		$query        = $is_unscented ? array() : array( 'attribute_scent' => $scent_lbl );
		$pdp_url      = self::pdp_url( $wc_slug, $query );
		return array(
			'slug'              => 'renewal-' . $scent,
			'name'              => $name,
			'scent'             => $scent_lbl,
			'blurb'             => 'Daily moisture. Tone, texture, problem skin.',
			'shop_url'          => $pdp_url,
			'add_to_cart_url'   => $pdp_url,
			'product_id'        => $wc_id,
			'image_url'         => apply_filters( 'lprq_product_image', '', 'renewal', $scent ),
		);
	}

	/**
	 * Build a product PDP URL with optional pre-selected attributes.
	 * Holly's products are variable-subscription, so a direct
	 * ?add-to-cart=ID doesn't work (WC requires size + scent + payment
	 * attributes). Pre-selecting scent in the URL lands the customer on
	 * the PDP with the right variant highlighted; they pick size + payment
	 * type, then add. Two clicks instead of one, but reliable.
	 *
	 * @param string $product_slug e.g. ageless-tallow-butter
	 * @param array  $attrs        e.g. ['attribute_scent' => 'Honey Creme']
	 */
	private static function pdp_url( $product_slug, $attrs = array() ) {
		$url = home_url( '/product/' . $product_slug . '/' );
		if ( ! empty( $attrs ) ) {
			$url = add_query_arg( $attrs, $url );
		}
		return apply_filters( 'lprq_product_url', $url, $product_slug );
	}

	/**
	 * Resolve a WC product slug → product ID via WP post lookup.
	 * Kept around in case future client products are simple (cart-addable)
	 * and we want to use the ID for direct cart integration.
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
	 * "Add both" link — for variable products this can't add to cart in one
	 * shot (variations require attribute selection). For now this just links
	 * to the primary product PDP. When clients have simple products, this
	 * can route through the slrq_action=add_routine endpoint instead.
	 *
	 * @deprecated v1.13.0  Kept for backward compatibility with v1.10-v1.12.
	 */
	public static function add_routine_url( $primary_id, $secondary_id ) {
		return home_url( '/shop/' );
	}
}

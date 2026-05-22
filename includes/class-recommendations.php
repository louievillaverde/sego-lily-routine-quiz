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
				return array(
					'primary'   => self::ageless( $is_sensitive ? 'rosewood-lavender' : 'honey-creme' ),
					'secondary' => self::renewal( 'unscented' ),
					'why'       => $why,
				);

			case 'Dryness & tightness':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => self::ageless( $is_sensitive ? 'rosewood-lavender' : 'honey-creme' ),
					'why'       => $why,
				);

			case 'Redness & sensitivity':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'rosewood-lavender' ),
					'why'       => $why,
				);

			case 'Breakouts':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'honey-creme' ),
					'why'       => $why,
				);

			default:
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => self::renewal( 'unscented' ),
					'why'       => $why,
				);
		}
	}

	/**
	 * Per-combination "why" copy. 4 skin concerns x 4 frustrations = 16
	 * variants. Each opens by validating the frustration, then explains the
	 * mechanism specific to the concern, then closes with a number or
	 * timeline. No age callouts (the user has already self-selected, no
	 * need to gate by birth year).
	 */
	private static function why_for( $skin_concern, $frustration ) {
		$map = array(
			'Wrinkles & dark spots' => array(
				'Nothing works long enough'   => 'Most anti-aging products work for a few hours then quit on you. Your skin&rsquo;s lipid production has been declining, and lab actives can&rsquo;t refill those lipids long-term. Tallow can, because your skin reads it as its own. Softer texture in 4 to 6 weeks, sustained.',
				'Too many products'           => 'You&rsquo;re trying to fight a biology problem with shelf volume. Your skin stopped making the lipids that keep it firm. Layering 6 actives doesn&rsquo;t replace what&rsquo;s missing. Two jars of the right thing does.',
				"Don't trust ingredients"     => 'Most anti-aging products list 15+ ingredients you can&rsquo;t pronounce. Your skin doesn&rsquo;t recognize them, which is why they don&rsquo;t last. Tallow is structurally identical to your skin&rsquo;s own lipids. Softer texture in 4 to 6 weeks.',
				'Just want something simple'  => 'The whole anti-aging story is simpler than the industry makes it. Your skin stopped making the lipids that keep it firm. Tallow puts them back. Two jars, twice a day.',
			),
			'Dryness & tightness' => array(
				'Nothing works long enough'   => 'Most moisturizers wear off in a few hours. They&rsquo;re water-based, and water evaporates. Your skin needs the fats that hold moisture in. Tallow is mostly those fats. It absorbs in 30 seconds and stays for 8 hours.',
				'Too many products'           => 'You don&rsquo;t need a 6-step routine. You need one product that actually moisturizes. Most lotions are water-based and evaporate. Tallow is mostly fats your skin recognizes and stays for 8 hours.',
				"Don't trust ingredients"     => 'Most moisturizers list 20+ ingredients, most of them solvents and stabilizers. Your skin needs the fats that hold water in, not the chemistry holding the formula together. Renewal has 5 ingredients, no fillers.',
				'Just want something simple'  => 'Tight skin means your barrier can&rsquo;t hold moisture. The fix is fats your skin recognizes, not more products. Tallow absorbs in 30 seconds and locks moisture for 8 hours. Two jars.',
			),
			'Redness & sensitivity' => array(
				'Nothing works long enough'   => 'If most products start fine then your skin reacts a few days in, the issue is ingredient overload. Renewal Unscented has 5 ingredients, all food-grade. Nothing reactive to build up. Safe even for newborns.',
				'Too many products'           => 'Reactive skin doesn&rsquo;t need more products. It needs fewer ingredients. Most products have 15 to 30 of them. Renewal Unscented has 5, all food-grade.',
				"Don't trust ingredients"     => 'Reactive skin reacts because it identifies foreign ingredients fast. Most products have 15 to 30 of them. Renewal Unscented has 5: tallow, a touch of organic oil, vitamin E, that&rsquo;s it. Safe for newborns, rosacea, post-procedure.',
				'Just want something simple'  => 'The simplest answer for reactive skin is the fewest ingredients possible. Renewal Unscented has 5, all food-grade. Safe for newborns and rosacea. One jar covers most situations.',
			),
			'Breakouts' => array(
				'Nothing works long enough'   => 'If your acne products work for a week then your skin flares again, the issue is your barrier. Stripping treatments calm breakouts short-term but inflame the barrier long-term. Tallow calms without stripping and is non-comedogenic.',
				'Too many products'           => 'You don&rsquo;t need 5 acne products. Most acne routines inflame your barrier, which makes oil overproduction worse. Tallow calms the barrier and won&rsquo;t clog pores.',
				"Don't trust ingredients"     => 'Most acne products list harsh actives and stabilizers your skin reacts to. Tallow is one ingredient, structurally identical to what your skin makes. Non-comedogenic. Calms inflammation instead of triggering it.',
				'Just want something simple'  => 'Adult breakouts are usually a barrier problem, not an oil problem. The simplest fix: stop using actives that inflame the barrier. Tallow calms and won&rsquo;t clog pores.',
			),
		);

		$default = 'A clean two-product routine that fits most starting points. Ageless rebuilds your lipid barrier through the day. Renewal locks in deeper moisture overnight.';

		if ( ! isset( $map[ $skin_concern ] ) ) {
			return $default;
		}
		$concern_map = $map[ $skin_concern ];
		if ( isset( $concern_map[ $frustration ] ) ) {
			return $concern_map[ $frustration ];
		}
		// Frustration may carry curly apostrophe ('Don’t') or HTML entity.
		// Normalize both sides for a tolerant lookup.
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
	 * WC native add-to-cart URL with redirect to cart.
	 * Example: https://site.com/cart/?add-to-cart=123
	 */
	private static function add_to_cart_url( $product_id ) {
		if ( ! $product_id ) {
			return home_url( '/cart/' );
		}
		return add_query_arg( 'add-to-cart', $product_id, wc_get_cart_url() );
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

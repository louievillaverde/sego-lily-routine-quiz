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

		switch ( $skin_concern ) {

			case 'Wrinkles & dark spots':
				return array(
					'primary'   => self::ageless( $is_sensitive ? 'rosewood-lavender' : 'honey-creme' ),
					'secondary' => self::renewal( 'unscented' ),
					'why'       => $is_sensitive
						? 'Reactive skin can&rsquo;t handle the actives most anti-aging products rely on. Tallow rebuilds your skin&rsquo;s lipid barrier without irritation, because your skin recognizes it as its own. Softer texture, no flare-ups, in 4 to 6 weeks.'
						: 'Here&rsquo;s what nobody tells you about wrinkles: your skin&rsquo;s lipid production drops ~30% after 35. Lab actives can&rsquo;t replace those lipids, but tallow can, because your skin reads it as its own. Most see softer texture in 4 to 6 weeks.',
				);

			case 'Dryness & tightness':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => self::ageless( $is_sensitive ? 'rosewood-lavender' : 'honey-creme' ),
					'why'       => 'Most moisturizers fail on dry skin because they&rsquo;re water-based, and water evaporates. Your skin needs the fats that hold water IN. Tallow is mostly those fats. It absorbs in 30 seconds and locks moisture for 8 hours.',
				);

			case 'Redness & sensitivity':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'rosewood-lavender' ),
					'why'       => 'Reactive skin doesn&rsquo;t mean your skin is broken. It means it can identify foreign ingredients fast. Most products have 15 to 30 of them. Renewal Unscented has 5, all food-grade. Safe even for newborns and post-procedure skin.',
				);

			case 'Breakouts':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'honey-creme' ),
					'why'       => 'Adult breakouts are usually a barrier problem, not an oil problem. When your barrier is inflamed, your skin overproduces oil to compensate, and the cycle continues. Tallow calms the barrier and is non-comedogenic, so it won&rsquo;t add to the problem.',
				);

			default:
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => self::renewal( 'unscented' ),
					'why'       => 'A clean two-product routine that fits most starting points. Ageless rebuilds your lipid barrier through the day. Renewal locks in deeper moisture overnight.',
				);
		}
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

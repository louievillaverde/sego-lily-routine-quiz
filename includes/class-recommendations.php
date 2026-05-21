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
						? 'Your skin is reactive AND showing age. Most anti-aging actives flare sensitivity. Tallow won&rsquo;t.'
						: 'Your skin stopped making the lipids that keep it firm. Tallow puts them back. Softer texture in 4 to 6 weeks.',
				);

			case 'Dryness & tightness':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => self::ageless( $is_sensitive ? 'rosewood-lavender' : 'honey-creme' ),
					'why'       => 'Tight skin means your barrier can&rsquo;t hold moisture. Tallow absorbs because your skin recognizes it. Softer skin in 2 to 3 weeks.',
				);

			case 'Redness & sensitivity':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'rosewood-lavender' ),
					'why'       => 'Reactive skin reacts to ingredients. Renewal Unscented has five. Safe for newborns, rosacea, post-procedure.',
				);

			case 'Breakouts':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'honey-creme' ),
					'why'       => 'Adult breakouts are usually an inflamed barrier, not excess oil. Tallow is non-comedogenic. It calms without clogging.',
				);

			default:
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => self::renewal( 'unscented' ),
					'why'       => 'The cleanest starting routine in our line. Two jars, no overlap.',
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
		return array(
			'slug'      => 'ageless-' . $scent,
			'name'      => 'Ageless Tallow Butter',
			'scent'     => $scents[ $scent ] ?? 'Honey Creme',
			'blurb'     => 'Anti-aging. Face, body, hands.',
			'shop_url'  => self::shop_url( 'ageless-tallow-butter' ),
			'image_url' => apply_filters( 'lprq_product_image', '', 'ageless', $scent ),
		);
	}

	private static function renewal( $scent ) {
		$scents = array(
			'mandarin-orange'   => 'Mandarin Orange',
			'cardamom-primrose' => 'Cardamom Primrose',
			'cherry'            => 'Cherry',
			'unscented'         => 'Unscented (Baby and Mom safe)',
		);
		// Unscented is sold as a separate WC product (baby-mom-pure-butter), not a
		// scent variation of renewal-tallow-butter. Route accordingly.
		$wc_slug = ( $scent === 'unscented' ) ? 'baby-mom-pure-butter' : 'renewal-tallow-butter';
		$name    = ( $scent === 'unscented' ) ? 'Baby + Mom Pure Butter' : 'Renewal Tallow Butter';
		return array(
			'slug'      => 'renewal-' . $scent,
			'name'      => $name,
			'scent'     => $scents[ $scent ] ?? 'Unscented',
			'blurb'     => 'Daily moisture. Tone, texture, problem skin.',
			'shop_url'  => self::shop_url( $wc_slug ),
			'image_url' => apply_filters( 'lprq_product_image', '', 'renewal', $scent ),
		);
	}

	/**
	 * Build the public product URL. WooCommerce default is /product/{slug}/.
	 * NOT /shop/product/{slug}/ — that was the bug in v1.0-v1.8 that sent
	 * customers to a 404.
	 */
	private static function shop_url( $product_slug ) {
		$url = home_url( '/product/' . $product_slug . '/' );
		return apply_filters( 'lprq_product_url', $url, $product_slug );
	}
}

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
						? 'Anti-aging power, gentle enough for sensitive skin. Two jars, no overlap.'
						: 'Anti-aging on the face, daily moisture on the body. Two jars cover your whole routine.',
				);

			case 'Dryness & tightness':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => self::ageless( $is_sensitive ? 'rosewood-lavender' : 'honey-creme' ),
					'why'       => 'Deep moisture first. Anti-aging support on top. Designed to be your only two skincare products.',
				);

			case 'Redness & sensitivity':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'rosewood-lavender' ),
					'why'       => 'Both Unscented or low-scent. Plant-based fragrance only. Safe for newborns, sensitive skin, post-sun.',
				);

			case 'Breakouts':
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'honey-creme' ),
					'why'       => 'Renewal Unscented for problem skin. Ageless on top for tone and texture. Both formulas are non-comedogenic.',
				);

			default:
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => self::renewal( 'unscented' ),
					'why'       => 'Anti-aging plus daily moisture. Most customers start here.',
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
		return array(
			'slug'      => 'renewal-' . $scent,
			'name'      => 'Renewal Tallow Butter',
			'scent'     => $scents[ $scent ] ?? 'Unscented',
			'blurb'     => 'Daily moisture. Tone, texture, problem skin.',
			'shop_url'  => self::shop_url( 'renewal-tallow-butter' ),
			'image_url' => apply_filters( 'lprq_product_image', '', 'renewal', $scent ),
		);
	}

	private static function shop_url( $product_slug ) {
		$base = get_option( 'lprq_shop_url', '' );
		if ( ! $base ) {
			$base = home_url( '/shop' );
		}
		return apply_filters( 'lprq_product_url', trailingslashit( $base ) . 'product/' . $product_slug, $product_slug );
	}
}

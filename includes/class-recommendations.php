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
	public static function pair_for( $skin_concern, $frustration = '', $product_count = '', $firstname = '' ) {
		$default = self::default_pair( $skin_concern, $frustration, $product_count, $firstname );

		// If Moxie is the secondary, rewrite the "why" text so the SECONDARY
		// product mention becomes Moxie. The secondary differs by skin
		// concern path (per default_pair's switch):
		//   - Wrinkles default secondary = Renewal Mandarin Orange
		//   - Dryness default secondary  = Ageless Honey Creme (so primary stays Renewal)
		//   - Breakouts default secondary = Ageless Honey Creme (so primary stays Renewal)
		//   - Default fallback secondary = Renewal Mandarin Orange
		// Swap the right product per path, leave the primary mention intact.
		if ( strpos( $default['secondary']['slug'] ?? '', 'moxie-' ) === 0 ) {
			switch ( $skin_concern ) {
				case 'Dryness & tightness':
				case 'Breakouts':
					$swap_from = 'Ageless Honey Creme';
					break;
				case 'Wrinkles & dark spots':
				default:
					$swap_from = 'Renewal Mandarin Orange';
					break;
			}
			$default['why'] = str_replace(
				array( '<strong>' . $swap_from . '</strong>', $swap_from ),
				'<strong>Moxie Intensive Moisture</strong>',
				$default['why']
			);
			// Each why variant mentions the secondary product in its own standalone
			// sentence ("<strong>X</strong> at night ...."). The str_replace above
			// rewrote that sentence to start with "<strong>Moxie Intensive Moisture</strong>".
			// Find the whole sentence (up to its first period) and replace it with
			// the for-him Moxie pitch, which carries the real reason Moxie earns a
			// spot in the routine: thicker formula for facial skin under a beard,
			// hands that actually work, body, heavier moisture men's skin needs.
			// One-step swap, no duplication.
			$moxie_pitch = '<strong>Moxie Intensive Moisture</strong> is built thicker on purpose. Holly designed it for facial skin under a beard, hands that actually work, and the heavier moisture most men&rsquo;s skin needs through the day. Same clean tallow base as the rest of the line, just denser.';
			$swap = preg_replace(
				'/<strong>Moxie Intensive Moisture<\/strong>[^.]*?\./',
				$moxie_pitch,
				$default['why'],
				1
			);
			if ( $swap !== null ) {
				$default['why'] = $swap;
			}
		}

		// Build add_both_url with primary + secondary slugs + scents
		$default['add_both_url'] = self::add_routine_url(
			$default['primary']['slug'] ?? '',
			$default['primary']['scent'] ?? '',
			$default['secondary']['slug'] ?? '',
			$default['secondary']['scent'] ?? ''
		);

		// Per-skin-concern testimonial (filter-driven, defaults to none).
		// Holly can populate via functions.php:
		//   add_filter('lprq_testimonial_for_concern', function($t, $concern) { ... });
		$testimonial = apply_filters( 'lprq_testimonial_for_concern', null, $skin_concern );
		if ( ! empty( $testimonial ) && is_array( $testimonial ) && ! empty( $testimonial['quote'] ) ) {
			$default['testimonial'] = $testimonial;
		}

		$default['diagnostic'] = self::diagnostic_for( $skin_concern, $frustration );

		return apply_filters( 'lprq_recommendation', $default, $skin_concern, $frustration );
	}

	/**
	 * One-sentence diagnostic shown directly under the "Your match" heading.
	 * Maps each (skin_concern, frustration) combo to a derm-chart-style read
	 * of the customer's situation. The point is to make the results feel
	 * personally read, not generic, before the product copy lands.
	 */
	private static function diagnostic_for( $skin_concern, $frustration ) {
		$normalize = function ( $s ) {
			return str_replace( array( "\xE2\x80\x99", '&rsquo;' ), "'", $s );
		};
		$f = $normalize( $frustration );
		$map = array(
			'Wrinkles & dark spots' => array(
				'Nothing works long enough'   => 'Maturing skin that needs sustained barrier support, not stop-start actives.',
				'Too many products'           => 'Maturing skin that&rsquo;s been over-treated with competing actives chasing the same biology.',
				"Don't trust ingredients"     => 'Maturing skin plus a clean-label gut check. You want ingredients you can pronounce.',
				'Just want something simple'  => 'Maturing skin on a minimalist routine. Two jars, two specific jobs.',
			),
			'Dryness & tightness' => array(
				'Nothing works long enough'   => 'Barrier-depleted skin that loses moisture faster than your products can replace it.',
				'Too many products'           => 'Dehydrated barrier that&rsquo;s been chased with water-based fixes instead of the lipids it actually needs.',
				"Don't trust ingredients"     => 'Dry skin plus ingredient caution. You read every label and most of them make you nervous.',
				'Just want something simple'  => 'Dry skin, minimalist preference. The fix is the right fats, not more steps.',
			),
			'Redness & sensitivity' => array(
				'Nothing works long enough'   => 'Reactive skin where most products start a flare within a week.',
				'Too many products'           => 'Reactive skin compounded by ingredient overload.',
				"Don't trust ingredients"     => 'Reactive skin plus ingredient caution. The label is often the trigger before the product is.',
				'Just want something simple'  => 'Reactive skin, minimalist by necessity. Fewer ingredients, fewer flares.',
			),
			'Breakouts' => array(
				'Nothing works long enough'   => 'Barrier-stressed skin where stripping treatments are driving the breakout cycle.',
				'Too many products'           => 'Inflamed barrier from a five-step war against your own oil production.',
				"Don't trust ingredients"     => 'Breakout-prone plus ingredient caution. The chemistry-lab labels are part of the cycle.',
				'Just want something simple'  => 'Breakout-prone, minimalist preference. Calm the barrier, drop the actives.',
			),
		);
		if ( ! isset( $map[ $skin_concern ] ) ) {
			return '';
		}
		foreach ( $map[ $skin_concern ] as $key => $value ) {
			if ( $normalize( $key ) === $f ) {
				return $value;
			}
		}
		return reset( $map[ $skin_concern ] );
	}

	private static function default_pair( $skin_concern, $frustration, $product_count = '', $firstname = '' ) {
		$is_sensitive  = ( $skin_concern === 'Redness & sensitivity' );
		$is_simplifier = in_array( $frustration, array( 'Too many products', 'Just want something simple' ), true );
		$is_male       = self::is_likely_male( $firstname );

		$why = self::why_for( $skin_concern, $frustration, $product_count );

		// For male users, swap secondary to Moxie (Holly's men's positioning)
		// in non-sensitivity paths. Sensitivity needs unscented, so keep Baby + Mom.
		switch ( $skin_concern ) {

			case 'Wrinkles & dark spots':
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => $is_male ? self::moxie() : self::renewal( 'mandarin-orange' ),
					'why'       => $why,
				);

			case 'Dryness & tightness':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => $is_male ? self::moxie() : self::ageless( 'honey-creme' ),
					'why'       => $why,
				);

			case 'Redness & sensitivity':
				// Sensitivity needs unscented. Baby + Mom Pure Butter is the right
				// product here regardless of gender (scented Moxie would trigger
				// reactive skin).
				return array(
					'primary'   => self::renewal( 'unscented' ),
					'secondary' => self::ageless( 'rosewood-lavender' ),
					'why'       => $why,
				);

			case 'Breakouts':
				return array(
					'primary'   => self::renewal( 'mandarin-orange' ),
					'secondary' => $is_male ? self::moxie() : self::ageless( 'honey-creme' ),
					'why'       => $why,
				);

			default:
				return array(
					'primary'   => self::ageless( 'honey-creme' ),
					'secondary' => $is_male ? self::moxie() : self::renewal( 'mandarin-orange' ),
					'why'       => $why,
				);
		}
	}

	/**
	 * Per-combination "why" copy. 16 variants written in Holly's flowing
	 * conversational voice (long sentences, real connectives, specific
	 * mechanism, both products named with a reason for the pairing). No
	 * fragment-stacking AI-listicle pattern. No age callouts. No em dashes.
	 */
	private static function why_for( $skin_concern, $frustration, $product_count = '' ) {
		$map = array(
			'Wrinkles & dark spots' => array(
				'Nothing works long enough'   => 'Most anti-aging creams work for a few weeks then quit on you, and it usually has nothing to do with the brand. The issue is that they&rsquo;re built around stabilized actives your skin doesn&rsquo;t actually recognize, so the moment you stop applying, your skin slides back because it still doesn&rsquo;t have what it actually needs. <strong>Ageless Honey Creme</strong> is whipped tallow plus raw honey and propolis, structurally identical to the lipids your skin used to make on its own, and the honey adds the humectant pull that keeps moisture in the rebuilding layer instead of evaporating off. <strong>Renewal Mandarin Orange</strong> at night brings in mandarin essential oil, a natural source of vitamin C that supports the collagen your barrier needs to firm back up, so you&rsquo;re rebuilding twice, once during the day and once overnight.',
				'Too many products'           => 'Most anti-aging routines are five or six products fighting the same biological problem with five or six different actives, and your skin gets confused trying to recognize all of them at once. The simpler answer is to replace the actual lipids your skin stopped producing, instead of layering things that try to mimic them. <strong>Ageless Honey Creme</strong> in the morning gives you whipped tallow plus raw honey for the humectant pull, which keeps moisture sealed into the barrier through the day. <strong>Renewal Mandarin Orange</strong> at night adds mandarin essential oil, a natural source of vitamin C that helps collagen rebuild while your skin does its overnight repair work.',
				"Don't trust ingredients"     => 'Most anti-aging products list fifteen or more ingredients on the back of the jar, and at least half are solvents, stabilizers, and synthetic actives your skin doesn&rsquo;t recognize. <strong>Ageless Honey Creme</strong> has six ingredients you would recognize in a kitchen: grass-fed tallow, raw honey, olive oil, beeswax, vitamin E, and a touch of essential oil, all of which your skin reads as its own lipids. <strong>Renewal Mandarin Orange</strong> uses the same clean profile (five ingredients, no honey but with mandarin essential oil for vitamin C) so layering them at night doesn&rsquo;t introduce a single thing your skin can&rsquo;t read.',
				'Just want something simple'  => 'The whole anti-aging story is simpler than the industry makes it sound, because your skin made the lipids that kept it firm and now it doesn&rsquo;t, and the job of a good product is to put those lipids back. <strong>Ageless Honey Creme</strong> in the morning gives you whipped tallow plus raw honey, which is what handles the daytime barrier repair. <strong>Renewal Mandarin Orange</strong> at night layers in mandarin essential oil for the overnight collagen support your skin needs while it does its repair cycle, two jars, two specific jobs.',
			),
			'Dryness & tightness' => array(
				'Nothing works long enough'   => 'Most moisturizers wear off in a few hours because they&rsquo;re built mostly on water, and water evaporates off your skin instead of soaking in, which is why you keep reapplying. <strong>Renewal Mandarin Orange</strong> is whipped tallow that absorbs in about thirty seconds and delivers the saturated fats your skin actually uses to seal water in, holding moisture for about eight hours instead of one. <strong>Ageless Honey Creme</strong> at night is denser, with raw honey and propolis added, which pull humectant moisture into the barrier overnight when your skin loses about twenty percent of its water through TEWL, the layer that keeps you from waking up tight.',
				'Too many products'           => 'A stack of four moisturizers stops being moisturizers and starts being a complicated chemistry experiment, and the reason it doesn&rsquo;t actually fix the tightness is because none of them addresses why your skin can&rsquo;t hold water in the first place. The barrier needs the right fats, not more water layered with hyaluronic acid. <strong>Renewal Mandarin Orange</strong> is whipped tallow that absorbs in about thirty seconds and delivers the saturated fats your skin uses to seal water in, which is why one jar replaces the serum-toner-cream daytime stack. <strong>Ageless Honey Creme</strong> at night does the deeper barrier repair, a denser formula with raw honey and propolis, which pull humectant moisture into the rebuilding layer while you sleep.',
				"Don't trust ingredients"     => 'Most moisturizers list twenty or more ingredients on the back, and most of those are solvents and stabilizers that keep the formula on a shelf, not what your skin actually absorbs. <strong>Renewal Mandarin Orange</strong> has five ingredients (whipped grass-fed tallow, olive oil, mandarin essential oil, beeswax, vitamin E), all five of which your skin reads as lipids it can use. <strong>Ageless Honey Creme</strong> at night uses six ingredients on the same clean profile, with raw honey and propolis added for the humectant pull your barrier needs overnight.',
				'Just want something simple'  => 'Tight skin means your barrier can&rsquo;t hold moisture, and the fix is fats your skin recognizes, not more products that pretend to be moisturizing. <strong>Renewal Mandarin Orange</strong> in the morning absorbs in thirty seconds and locks in moisture for about eight hours, so daytime tightness goes away. <strong>Ageless Honey Creme</strong> at night brings in raw honey and propolis for the humectant pull that keeps moisture in the barrier overnight, which is why you stop waking up tight.',
			),
			'Redness & sensitivity' => array(
				'Nothing works long enough'   => 'Reactive skin almost always works the same way: you find a product, it feels great for a week, and then your skin flares because there&rsquo;s an ingredient in there it&rsquo;s reacting to, and you don&rsquo;t know which of the fifteen it is. The way out is to use products with so few ingredients that there&rsquo;s nothing left to react to. <strong>Baby + Mom Pure Butter</strong> (Holly named it for newborn skin, but it&rsquo;s our most reactive-safe formulation, period) has five ingredients, all food-grade, with no fragrance or essential oils to trigger a flare. <strong>Ageless Rosewood Lavender</strong> at night is the gentlest scented option we make, with rosewood and lavender (the two essential oils most reactive skin tolerates better than citrus or floral), so when you do want a touch of evening scent, this is the safest entry point.',
				'Too many products'           => 'When your skin is reactive, every new product is another fifteen or twenty ingredients your skin has to recognize, and the longer the routine the higher the chance one of them triggers a flare. <strong>Baby + Mom Pure Butter</strong> has five ingredients total, all food-grade and fragrance-free, which is what makes it Holly&rsquo;s safest formulation for daytime reactive skin. <strong>Ageless Rosewood Lavender</strong> has six on the same clean profile, with rosewood and lavender essential oils added for a touch of evening scent (chosen because they&rsquo;re the two scents reactive skin tolerates best), so you&rsquo;ve dropped your ingredient count by 80 percent or more.',
				"Don't trust ingredients"     => 'When you read every label and most of them make you nervous, the answer isn&rsquo;t to keep searching, it&rsquo;s to use products with so few ingredients there&rsquo;s nothing to be nervous about. <strong>Baby + Mom Pure Butter</strong> has five ingredients you&rsquo;d recognize from a kitchen (whipped grass-fed tallow, olive oil, vitamin E, beeswax, rosehip seed oil), and no fragrance at all. <strong>Ageless Rosewood Lavender</strong> has six on the same clean profile, safe enough for newborn skin, post-procedure recovery, and rosacea.',
				'Just want something simple'  => 'Reactive skin gets simpler the fewer ingredients you put on it, full stop. <strong>Baby + Mom Pure Butter</strong> has five ingredients and zero fragrance, which is what makes it Holly&rsquo;s gentlest formulation for any sensitive adult skin (she named it for newborns). <strong>Ageless Rosewood Lavender</strong> at night is the softest scented option we make, with only rosewood and lavender essential oils, which reactive skin tolerates better than citrus or floral scents.',
			),
			'Breakouts' => array(
				'Nothing works long enough'   => 'Most acne products work for about a week and then your skin flares again, and the reason is almost always your barrier rather than your oil production. Stripping treatments calm the breakouts short-term by removing the top layer of skin, but they also inflame the barrier underneath, which makes your skin produce more oil to compensate, which causes more breakouts a few days later. <strong>Renewal Mandarin Orange</strong> is whipped tallow that calms the inflamed barrier without stripping (and it&rsquo;s non-comedogenic), so your skin stops producing the extra oil that&rsquo;s driving the cycle. <strong>Ageless Honey Creme</strong> at night adds raw honey, which is naturally antimicrobial, supporting the texture repair while you sleep without the harsh actives that started the cycle.',
				'Too many products'           => 'Cleanser, toner, treatment, serum, spot cream. Your face has had a five-step war declared on it, and every step in that war inflames your barrier a little more, which is exactly why the oil production keeps getting worse and the breakouts keep coming back. <strong>Renewal Mandarin Orange</strong> is whipped tallow that calms the barrier without stripping (non-comedogenic so it won&rsquo;t clog), which lets your oil production reset to normal. <strong>Ageless Honey Creme</strong> at night brings in raw honey for its natural antimicrobial properties, so you trade five products for two and you&rsquo;re finally treating the breakouts without creating new ones.',
				"Don't trust ingredients"     => 'Most acne products list ingredients that read like a chemistry lab (sulfates, retinoids, salicylic acid, parabens), and these strip your barrier while they treat the breakouts, which is part of why the cycle never ends. <strong>Renewal Mandarin Orange</strong> is six ingredients, mostly whipped tallow and organic oils, all food-grade and non-comedogenic. <strong>Ageless Honey Creme</strong> at night uses six ingredients on the same clean profile, with raw honey added for the antimicrobial support your skin actually needs overnight.',
				'Just want something simple'  => 'Adult breakouts are usually a barrier problem and not an oil problem, which means the simplest fix is to stop using anything that inflames your barrier (which is most acne products). <strong>Renewal Mandarin Orange</strong> is whipped tallow that calms the barrier and won&rsquo;t clog pores, so your oil production resets to normal. <strong>Ageless Honey Creme</strong> at night adds raw honey for antimicrobial support overnight, two jars, no harsh actives, and the cycle finally has a chance to break.',
			),
		);

		$default = 'A clean two-product routine that fits most starting points. <strong>Ageless Honey Creme</strong> rebuilds your lipid barrier through the day, and <strong>Renewal Mandarin Orange</strong> locks in deeper moisture overnight.';

		if ( ! isset( $map[ $skin_concern ] ) ) {
			return $default . self::product_count_tail( $product_count );
		}
		$concern_map = $map[ $skin_concern ];
		$matched     = null;
		if ( isset( $concern_map[ $frustration ] ) ) {
			$matched = $concern_map[ $frustration ];
		} else {
			$normalized = str_replace( array( "\xE2\x80\x99", "&rsquo;" ), "'", $frustration );
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
			'blurb'             => 'Anti-aging &middot; Day &middot; Night &middot; Body',
			'badge'             => 'Bestseller',
			'shop_url'          => $pdp_url,
			'add_to_cart_url'   => self::add_one_url( $wc_slug, $scent_lbl ),
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
		$is_unscented = ( $scent === 'unscented' );
		$wc_slug      = $is_unscented ? 'baby-mom-pure-butter' : 'renewal-tallow-butter';
		$name         = $is_unscented ? 'Baby + Mom Pure Butter' : 'Renewal Tallow Butter';
		$wc_id        = self::wc_product_id( $wc_slug );
		$scent_lbl    = $scents[ $scent ] ?? 'Unscented';
		$query        = $is_unscented ? array() : array( 'attribute_scent' => $scent_lbl );
		$pdp_url      = self::pdp_url( $wc_slug, $query );
		$blurb        = $is_unscented
			? 'Sensitive &middot; Post-procedure &middot; Newborn safe'
			: 'Daily moisture &middot; Hydration &middot; Calming';
		$badge        = $is_unscented ? 'Gentlest' : 'Top-rated';
		return array(
			'slug'              => 'renewal-' . $scent,
			'name'              => $name,
			'scent'             => $scent_lbl,
			'blurb'             => $blurb,
			'badge'             => $badge,
			'shop_url'          => $pdp_url,
			'add_to_cart_url'   => self::add_one_url( $wc_slug, $is_unscented ? '' : $scent_lbl ),
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
	 * Build "Add both to routine" URL. Routes through the slrq_action endpoint
	 * in sego-lily-routine-quiz.php, which looks up the right variation IDs
	 * by scent (default size = 2 oz, default payment = One-Time Purchase) and
	 * adds both to the cart in one request, then redirects to /cart/.
	 *
	 * URL pattern:
	 *   /?slrq_action=add_routine&p_slug=...&p_scent=...&s_slug=...&s_scent=...
	 */
	public static function add_routine_url( $p_slug, $p_scent, $s_slug, $s_scent ) {
		if ( empty( $p_slug ) || empty( $s_slug ) ) {
			return home_url( '/shop-all/?cta_id=add_both' );
		}
		$p_wc_slug = self::wc_slug_for( $p_slug );
		$s_wc_slug = self::wc_slug_for( $s_slug );
		return add_query_arg(
			array(
				'slrq_action' => 'add_routine',
				'p_slug'      => $p_wc_slug,
				'p_scent'     => $p_scent,
				's_slug'      => $s_wc_slug,
				's_scent'     => $s_scent,
				'cta_id'      => 'add_both',
			),
			home_url( '/' )
		);
	}

	/**
	 * Map internal recommendation slug -> WC product slug.
	 * Internal slugs are like 'ageless-honey-creme', 'renewal-unscented'.
	 * WC product slugs are the parent product, e.g. 'ageless-tallow-butter'.
	 */
	/**
	 * Moxie Intensive Moisture. Holly is positioning this as her men's
	 * line. Used as the secondary product when a male user takes the quiz
	 * (except in the sensitivity path, which routes to unscented).
	 *
	 * Default scent: Bourbon Coffee (strongest masculine positioning).
	 */
	private static function moxie( $scent = 'bourbon-coffee' ) {
		$scents = array(
			'bourbon-coffee' => 'Bourbon Coffee',
			'eucalyptus'     => 'Eucalyptus',
			'vanilla-spice'  => 'Vanilla Spice',
		);
		$wc_slug   = 'moxie-intensive-moisture';
		$wc_id     = self::wc_product_id( $wc_slug );
		$scent_lbl = $scents[ $scent ] ?? 'Bourbon Coffee';
		$pdp_url   = self::pdp_url( $wc_slug, array( 'attribute_scent' => $scent_lbl ) );
		return array(
			'slug'              => 'moxie-' . $scent,
			'name'              => 'Moxie Intensive Moisture',
			'scent'             => $scent_lbl,
			'blurb'             => 'For him &middot; Face &middot; Beard &middot; Body',
			'badge'             => 'Men&rsquo;s pick',
			'shop_url'          => $pdp_url,
			'add_to_cart_url'   => self::add_one_url( $wc_slug, $scent_lbl ),
			'product_id'        => $wc_id,
			'image_url'         => apply_filters( 'lprq_product_image', '', 'moxie', $scent ),
		);
	}

	/**
	 * Lightweight gender inference from first name. Hardcoded list of
	 * ~150 common US male first names. ~85% accurate for matched names,
	 * defaults to false (gender-neutral) for unknown names. Good enough
	 * for "default to male secondary" routing without forcing users to
	 * answer a gender question explicitly.
	 *
	 * @param string $firstname
	 * @return bool true if firstname strongly indicates male
	 */
	private static function is_likely_male( $firstname ) {
		$name = strtolower( trim( $firstname ) );
		if ( empty( $name ) ) {
			return false;
		}
		static $males = null;
		if ( $males === null ) {
			$males = array_flip( array(
				'aaron','adam','adrian','alan','albert','alex','alexander','andrew','anthony','arthur',
				'austin','barry','ben','benjamin','bernard','bill','billy','bob','bobby','brad','bradley',
				'brandon','brent','brett','brian','bruce','bryan','bryce','caleb','calvin','cameron',
				'carl','carlos','casey','chad','charles','charlie','chase','chris','christian','christopher',
				'clark','cody','colby','cole','colin','connor','corey','cory','craig','curtis',
				'damon','dan','daniel','danny','darrell','darren','dave','david','dean','dennis',
				'derek','derrick','devin','diego','dirk','don','donald','doug','douglas','drew','duane',
				'dustin','dwayne','dylan','earl','eddie','edgar','edward','eli','elias','elliot',
				'emmanuel','enrique','eric','erik','ernest','ethan','eugene','evan','everett',
				'felix','fernando','francisco','frank','franklin','fred','frederick','gabriel','gary','gavin',
				'george','gerald','gilbert','glenn','gordon','grant','greg','gregory','harold','harry',
				'harvey','henry','herbert','holden','howard','hugh','hugo','hunter','ian','isaac','isaiah',
				'jack','jackson','jacob','jaden','jake','james','jamie','jared','jason','javier',
				'jay','jayden','jeff','jeffery','jeffrey','jeremiah','jeremy','jerome','jerry','jesse',
				'jim','jimmy','joaquin','joe','joel','joey','john','johnny','jon','jonathan','jordan','jorge',
				'jose','joseph','josh','joshua','juan','judd','julian','justin','karl','keenan','keith',
				'kelly','kelvin','ken','kendrick','kenneth','kenny','kent','kevin','kirk','kurt','kyle',
				'lance','larry','lawrence','lee','leo','leon','leonard','leroy','lewis','liam','lloyd',
				'logan','lonnie','louie','louis','lucas','luis','luke','manuel','marcus','mario','mark',
				'marshall','martin','marvin','mason','matt','matthew','maurice','max','maxwell','melvin',
				'michael','micheal','miguel','mike','milton','mitchell','morgan','nate','nathan','nathaniel',
				'neal','neil','nelson','nicholas','nick','noah','norman','oliver','omar','oscar','otis',
				'owen','pablo','parker','patrick','paul','pedro','perry','pete','peter','phil','philip',
				'phillip','quincy','quinn','rafael','ralph','randy','ray','raymond','reggie','rene',
				'reuben','rex','ricardo','rich','richard','rick','ricky','rob','robbie','robert','roberto',
				'rocco','rodney','roger','roland','roman','ron','ronald','ronnie','ross','roy','ruben',
				'russell','ryan','sam','samuel','scott','sean','sebastian','seth','shane','shawn','sidney',
				'silas','simon','solomon','spencer','stan','stanley','stephen','steve','steven','stuart',
				'ted','terrance','terrell','terrence','terry','theodore','thomas','tim','timmy','timothy',
				'tobias','todd','tom','tommy','tony','tracy','travis','trent','trevor','tristan','troy',
				'tucker','ty','tyler','tyrone','vance','vernon','victor','vincent','virgil','wade',
				'walter','warren','wayne','wendell','wes','wesley','will','william','willie','wilson',
				'winston','wyatt','xander','xavier','zach','zachary','zack',
			) );
		}
		return isset( $males[ $name ] );
	}

	/**
	 * Single-product direct-cart URL. Carries cta_id=primary so the
	 * post-redirect /cart/ URL can show which CTA drove the conversion
	 * in analytics.
	 */
	public static function add_one_url( $wc_slug, $scent = '' ) {
		if ( empty( $wc_slug ) ) {
			return home_url( '/shop-all/?cta_id=primary' );
		}
		return add_query_arg(
			array(
				'slrq_action' => 'add_one',
				'slug'        => $wc_slug,
				'scent'       => $scent,
				'cta_id'      => 'primary',
			),
			home_url( '/' )
		);
	}

	private static function wc_slug_for( $internal_slug ) {
		if ( strpos( $internal_slug, 'ageless-' ) === 0 ) {
			return 'ageless-tallow-butter';
		}
		if ( $internal_slug === 'renewal-unscented' ) {
			return 'baby-mom-pure-butter';
		}
		if ( strpos( $internal_slug, 'renewal-' ) === 0 ) {
			return 'renewal-tallow-butter';
		}
		if ( strpos( $internal_slug, 'moxie-' ) === 0 ) {
			return 'moxie-intensive-moisture';
		}
		return $internal_slug;
	}
}

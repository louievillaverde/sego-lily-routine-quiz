<?php
/**
 * Routine Quiz: renders the on-site routine quiz + handles submission.
 *
 * Uses SLRQ_Mautic for lead sync. No dependency on sibling plugins.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Quiz {

	public static function init() {
		add_shortcode( 'lp_routine_quiz', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_ajax_lprq_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_lprq_submit', array( __CLASS__, 'handle_submit' ) );
		add_filter( 'template_include', array( __CLASS__, 'maybe_landing_template' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Landing-page override: when the requested page contains the quiz shortcode,
	 * serve our minimal landing template instead of the theme's page template.
	 * This hides the site header, footer, nav, and any wholesale-plugin toggles
	 * so the quiz feels like a true landing page.
	 */
	public static function maybe_landing_template( $template ) {
		if ( ! is_page() ) {
			return $template;
		}
		$post = get_post();
		if ( ! $post || ! has_shortcode( $post->post_content, 'lp_routine_quiz' ) ) {
			return $template;
		}
		$landing = SLRQ_PLUGIN_DIR . 'includes/template-landing.php';
		if ( file_exists( $landing ) ) {
			return $landing;
		}
		return $template;
	}

	public static function body_class( $classes ) {
		if ( is_page() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'lp_routine_quiz' ) ) {
				$classes[] = 'lprq-landing';
			}
		}
		return $classes;
	}

	public static function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array(
			'heading'    => 'Build Your Skincare Routine',
			'subheading' => 'Two minutes. Five questions. A routine matched to your skin.',
		), $atts );

		ob_start();
		?>
		<style>
		/* Hide common theme chrome selectors as a safety net for any theme
		   that doesn't fully respect template_include (some themes inject
		   header/footer via hooks anyway). */
		body.lprq-landing > header,
		body.lprq-landing > footer,
		body.lprq-landing > .site-header,
		body.lprq-landing > .site-footer,
		body.lprq-landing .main-navigation,
		body.lprq-landing .site-navigation,
		body.lprq-landing #masthead,
		body.lprq-landing #colophon,
		body.lprq-landing .header-main,
		body.lprq-landing .footer-main,
		body.lprq-landing .entry-header,
		body.lprq-landing .entry-title,
		body.lprq-landing .page-title,
		body.lprq-landing .breadcrumb,
		body.lprq-landing .breadcrumbs,
		body.lprq-landing [class*="slw"], body.lprq-landing [class*="wholesale"], body.lprq-landing [id*="slw"], body.lprq-landing [id*="wholesale"], body.lprq-landing .slw-customer-mode-toggle,
		body.lprq-landing .slw-mode-toggle,
		body.lprq-landing .wholesale-toggle,
		body.lprq-landing .for-my-store-toggle { display: none !important; }

		body.lprq-landing { background: linear-gradient(135deg, #F7F6F3 0%, #EEF3F5 100%); margin: 0; }

		.lprq-wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 16px; box-sizing: border-box; }
		.lprq { max-width: 620px; width: 100%; padding: 40px 32px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 40px rgba(56, 97, 116, 0.12), 0 2px 8px rgba(56, 97, 116, 0.06); font-family: Georgia, 'Times New Roman', serif; color: #2C2C2C; box-sizing: border-box; }
		.lprq * { box-sizing: border-box; }
		.lprq__brand { text-align: center; font-size: 13px; letter-spacing: 2px; color: #386174; font-weight: bold; margin-bottom: 24px; }
		.lprq__steps { display: flex; align-items: center; justify-content: center; gap: 0; margin: 0 0 36px; padding: 0; list-style: none; }
		.lprq__step-dot { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; min-width: 36px; border-radius: 50%; background: #ffffff; border: 2px solid #D4CFC4; color: #8A9499; font-size: 14px; font-weight: 600; font-family: Georgia, 'Times New Roman', serif; cursor: not-allowed; transition: all 0.2s ease; padding: 0; }
		.lprq__step-line { flex: 1; max-width: 36px; height: 2px; background: #D4CFC4; transition: background 0.3s ease; }
		.lprq__step-dot--done { background: #386174 !important; border-color: #386174 !important; color: #ffffff !important; cursor: pointer !important; }
		.lprq__step-dot--done:hover { transform: scale(1.08); box-shadow: 0 2px 8px rgba(56, 97, 116, 0.3); }
		.lprq__step-dot--current { background: #ffffff !important; border-color: #386174 !important; color: #386174 !important; box-shadow: 0 0 0 4px rgba(56, 97, 116, 0.15); cursor: default !important; }
		.lprq__step-line--done { background: #386174; }
		.lprq__step-label { text-align: center; font-size: 12px; color: #8A9499; margin: 0 0 28px; letter-spacing: 1px; text-transform: uppercase; }
		.lprq__step { display: none; opacity: 0; transition: opacity 0.3s ease; }
		.lprq__step--active { display: block; opacity: 1; }
		.lprq__step h2 { font-size: 28px; font-weight: 600; margin: 0 0 32px; text-align: center; line-height: 1.3; color: #2C2C2C; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__input { width: 100%; padding: 16px 18px; font-size: 17px; border: 2px solid #D4CFC4; border-radius: 10px; background: #FAFAF7; outline: none; font-family: Georgia, 'Times New Roman', serif; color: #2C2C2C; transition: all 0.15s ease; }
		.lprq__input:focus { border-color: #386174; background: #ffffff; box-shadow: 0 0 0 3px rgba(56, 97, 116, 0.1); }
		.lprq__input-error { border-color: #b8302e !important; }
		.lprq__pills { display: flex; flex-direction: column; gap: 12px; margin: 0 0 24px; }
		.lprq__pill, button.lprq__pill { display: block !important; width: 100% !important; padding: 18px 22px !important; font-size: 16px !important; font-weight: 500 !important; background: #ffffff !important; border: 2px solid #386174 !important; color: #386174 !important; border-radius: 10px !important; cursor: pointer !important; text-align: left !important; font-family: Georgia, 'Times New Roman', serif !important; transition: all 0.15s ease !important; line-height: 1.4 !important; text-transform: none !important; letter-spacing: normal !important; opacity: 1 !important; visibility: visible !important; }
		.lprq__pill:hover, button.lprq__pill:hover { background: #386174 !important; color: #ffffff !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__pill--selected, button.lprq__pill--selected { background: #386174 !important; color: #ffffff !important; box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__btn { display: block; width: 100%; padding: 16px 24px; font-size: 17px; font-weight: 600; background: #386174; color: #ffffff; border: none; border-radius: 10px; cursor: pointer; margin-top: 20px; font-family: Georgia, 'Times New Roman', serif; transition: all 0.15s ease; letter-spacing: 0.3px; }
		.lprq__btn:hover { background: #2a4a5a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__error { color: #b8302e; font-size: 14px; margin-top: 10px; min-height: 20px; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__loading { text-align: center; padding: 80px 20px; color: #8A9499; font-size: 16px; font-style: italic; }
		.lprq__results { text-align: center; }

		.lprq__results-heading { font-size: 32px; font-weight: 600; margin: 0 0 6px; color: #2C2C2C; line-height: 1.3; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__credibility { text-align: center; font-size: 11px; color: #8A9499; letter-spacing: 1.5px; text-transform: uppercase; font-weight: 600; margin: -10px 0 22px; padding: 10px 16px; border-top: 1px solid #E8E2D6; border-bottom: 1px solid #E8E2D6; }
		.lprq__testimonial { margin: 28px 0 24px; }
		.lprq__testimonial blockquote { background: #FAFAF7; border-left: 3px solid #B8A98C; padding: 18px 22px; margin: 0; font-style: italic; color: #4a5d68; font-size: 15px; line-height: 1.6; border-radius: 0 8px 8px 0; }
		.lprq__testimonial cite { display: block; margin-top: 10px; font-size: 12px; color: #8A9499; font-style: normal; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
		.lprq__results-why { font-size: 15.5px; color: #4a5d68; line-height: 1.7; margin: 0 0 32px !important; padding: 22px 26px; background: #F7F6F3; border-radius: 12px; text-align: left; border-left: 4px solid #386174; display: block; }
		.lprq__results-why p { margin: 0 0 14px; }
		.lprq__results-why p:last-child { margin-bottom: 0; }
		.lprq__results-why p.lprq__why-aside { font-size: 13px; color: #8A9499; font-style: italic; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #D4CFC4; }
		.lprq__results-why p.lprq__why-aside em { font-style: italic; }
		.lprq__results-why strong { color: #2C2C2C; }
		.lprq__primary-product { background: #ffffff; border: 2px solid #386174; border-radius: 14px; padding: 32px; margin: 0 0 36px !important; margin-top: 0 !important; text-align: left; display: flex; gap: 28px; align-items: center; box-shadow: 0 6px 20px rgba(56, 97, 116, 0.10); }
		#lprq-result-primary { display: block; margin-top: 0 !important; padding-top: 0 !important; }
		#lprq-result-why + #lprq-result-primary { margin-top: 0; }
		.lprq__primary-product .lprq__product-image { width: 200px; min-width: 200px; aspect-ratio: 1; background: #F7F6F3; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #B8A98C; font-size: 13px; overflow: hidden; margin: 0; }
		.lprq__primary-product .lprq__product-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
		.lprq__primary-product .lprq__product-body { flex: 1; }
		.lprq__primary-product .lprq__product-label { display: inline-block; font-size: 10px; color: #8A9499; background: transparent; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; margin: 0 0 8px; padding: 0; border: none; }
		.lprq__primary-product .lprq__product-name { font-size: 24px; font-weight: 600; margin: 0 0 8px; color: #2C2C2C; line-height: 1.25; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__product-badge { display: inline-block; font-size: 11px; font-weight: 700; color: #2C2C2C; background: #E8DEC4; padding: 3px 9px; border-radius: 999px; letter-spacing: 0.5px; text-transform: uppercase; vertical-align: middle; white-space: nowrap; margin-left: 4px; line-height: 1.4; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__product-badge--small { font-size: 9px; padding: 2px 7px; }
		.lprq__primary-product .lprq__product-scent { font-size: 15px; color: #8A9499; margin: 0 0 20px; font-style: italic; }
		.lprq__primary-product .lprq__product-blurb { font-size: 15px; color: #4a5d68; line-height: 1.6; margin: 0 0 28px; }
		.lprq__primary-product .lprq__product-link { display: inline-block; padding: 16px 30px; font-size: 16px; font-weight: 700; background: #386174; color: #ffffff !important; text-decoration: none; border-radius: 8px; transition: all 0.15s ease; letter-spacing: 0.4px; box-shadow: 0 4px 14px rgba(56, 97, 116, 0.28); }
		.lprq__primary-product .lprq__product-link:hover { background: #2a4a5a; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(56, 97, 116, 0.35); }
		.lprq__pairs-note { font-size: 15px; color: #4a5d68; text-align: center; margin: 0 0 32px; padding: 18px 22px; background: #FAFAF7; border-radius: 10px; line-height: 1.5; font-family: Georgia, 'Times New Roman', serif; border: 1px solid #E8E2D6; }
		.lprq__pairs-note-label { display: block; font-size: 11px; color: #8A9499; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 10px; font-weight: 600; }
		.lprq__pairs-row { display: flex; align-items: center; gap: 14px; padding: 4px 0; max-width: 100%; }
		.lprq__pairs-thumb { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: #ffffff; border: 1px solid #E8E2D6; }
		.lprq__pairs-info { text-align: left; flex: 1; min-width: 0; }
		.lprq__pairs-name { font-weight: 600; color: #2C2C2C; font-size: 15px; line-height: 1.3; }
		.lprq__pairs-scent { font-size: 13px; color: #8A9499; font-style: italic; margin: 2px 0 4px; }
		.lprq__pairs-blurb { font-size: 13px; color: #4a5d68; line-height: 1.4; }
		.lprq__add-both { display: block; width: 100%; margin: 20px 0 0; padding: 16px 24px; background: #386174; color: #ffffff !important; border-radius: 10px; text-decoration: none; font-size: 16px; font-weight: 700; text-align: center; letter-spacing: 0.4px; transition: all 0.15s ease; box-shadow: 0 4px 14px rgba(56, 97, 116, 0.25); font-family: Georgia, 'Times New Roman', serif; box-sizing: border-box; }
		.lprq__add-both:hover { background: #2a4a5a; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(56, 97, 116, 0.35); }
		.lprq__shop-all { text-align: center; margin: 24px 0 0; }
		.lprq__shop-all a { color: #8A9499; font-size: 13px; text-decoration: underline; text-underline-offset: 3px; font-style: italic; }
		.lprq__shop-all a:hover { color: #386174; }
		.lprq__pairs-add-both { display: block; margin: 14px auto 0; padding: 11px 18px; background: #386174; color: #ffffff !important; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; text-align: center; letter-spacing: 0.3px; transition: all 0.15s ease; max-width: 280px; }
		.lprq__pairs-add-both:hover { background: #2a4a5a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__privacy { font-size: 13px; color: #8A9499; line-height: 1.5; margin: 10px 0 16px; text-align: center; font-style: italic; }
		.lprq__callout { background: #386174; color: #ffffff; padding: 16px 20px; border-radius: 10px; margin: 0 0 24px; text-align: center; font-size: 15px; line-height: 1.5; font-family: Georgia, \'Times New Roman\', serif; }
		.lprq__callout strong { font-weight: 700; }
		.lprq__reassurance { font-size: 14px; color: #628393; margin: 16px 0 0; text-align: center; line-height: 1.5; }
		.lprq__signoff { font-size: 15px; color: #628393; font-style: italic; margin-top: 28px; }

		.lprq__diagnostic { font-size: 14.5px; color: #628393; font-style: italic; line-height: 1.5; margin: 0 auto 24px; text-align: center; max-width: 480px; text-wrap: balance; }
		.lprq__diagnostic:empty { display: none; }

		.lprq__product-name-link { color: inherit; text-decoration: none; border-bottom: 1px dotted transparent; transition: border-color 0.15s ease; }
		.lprq__product-name-link:hover { border-bottom-color: #386174; color: #386174; }
		.lprq__primary-product .lprq__product-image,
		.lprq__pairs-thumb { cursor: zoom-in; transition: transform 0.15s ease; }
		.lprq__primary-product .lprq__product-image:hover { transform: scale(1.02); }
		.lprq__pairs-thumb:hover { transform: scale(1.06); }

		.lprq__lightbox { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.86); display: none; align-items: center; justify-content: center; z-index: 99999; padding: 24px; cursor: zoom-out; }
		.lprq__lightbox.is-open { display: flex; }
		.lprq__lightbox-img { max-width: 92vw; max-height: 88vh; width: auto; height: auto; border-radius: 8px; box-shadow: 0 18px 48px rgba(0, 0, 0, 0.4); cursor: default; }
		.lprq__lightbox-close { position: absolute; top: 18px; right: 24px; background: transparent; border: none; color: #ffffff; font-size: 40px; line-height: 1; cursor: pointer; padding: 4px 12px; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__lightbox-close:hover { color: #E8DEC4; }

		@media (max-width: 540px) {
			.lprq { padding: 28px 20px; }
			.lprq__step h2 { font-size: 22px; margin: 0 0 24px; }
			.lprq__pill { padding: 16px 18px; font-size: 15px; }
			.lprq__results-heading { font-size: 26px; }
			.lprq__primary-product { flex-direction: column; gap: 20px; padding: 22px; text-align: center; }
			.lprq__primary-product .lprq__product-image { width: 100%; min-width: 0; max-width: 240px; margin: 0 auto; }
			.lprq__primary-product .lprq__product-body { text-align: left; }
			.lprq__diagnostic { font-size: 14px; padding: 0 4px; }
		}

		</style>

		<div class="lprq-wrap">
			<div class="lprq" id="lprq">

				<div class="lprq__brand">SEGO LILY SKINCARE</div>
				<?php
				$cred = apply_filters( 'lprq_results_credibility', 'Built by Holly in Montana. Five food-grade ingredients. Made by hand.' );
				if ( ! empty( $cred ) ) {
					echo '<div class="lprq__credibility">' . wp_kses_post( $cred ) . '</div>';
				}
				?>

				<ol class="lprq__steps" id="lprq-steps">
					<li><button type="button" class="lprq__step-dot lprq__step-dot--current" data-goto="1" aria-label="Step 1">1</button></li>
					<li class="lprq__step-line"></li>
					<li><button type="button" class="lprq__step-dot" data-goto="2" aria-label="Step 2">2</button></li>
					<li class="lprq__step-line"></li>
					<li><button type="button" class="lprq__step-dot" data-goto="3" aria-label="Step 3">3</button></li>
					<li class="lprq__step-line"></li>
					<li><button type="button" class="lprq__step-dot" data-goto="4" aria-label="Step 4">4</button></li>
					<li class="lprq__step-line"></li>
					<li><button type="button" class="lprq__step-dot" data-goto="5" aria-label="Step 5">5</button></li>
				</ol>
				<p class="lprq__step-label" id="lprq-label">Step 1 of 5</p>

				<form id="lprq-form" autocomplete="on" onsubmit="return false;">
					<?php wp_nonce_field( 'lprq_quiz', 'lprq_nonce' ); ?>

					<div class="lprq__step lprq__step--active" data-step="1">
						<h2>What&rsquo;s your first&nbsp;name?</h2>
						<input type="text" class="lprq__input" id="lprq-name" placeholder="First name" autocomplete="given-name" maxlength="30" />
						<div class="lprq__error" id="lprq-name-error"></div>
						<button type="button" class="lprq__btn" data-next>Next</button>
					</div>

					<div class="lprq__step" data-step="2">
						<h2>What bugs you most about your&nbsp;skin?</h2>
						<div class="lprq__pills" data-field="skin_concern">
							<button type="button" class="lprq__pill" data-value="Wrinkles &amp; dark spots">Wrinkles &amp; dark spots</button>
							<button type="button" class="lprq__pill" data-value="Dryness &amp; tightness">Dryness &amp; tightness</button>
							<button type="button" class="lprq__pill" data-value="Redness &amp; sensitivity">Redness &amp; sensitivity</button>
							<button type="button" class="lprq__pill" data-value="Breakouts">Breakouts</button>
						</div>
					</div>

					<div class="lprq__step" data-step="3">
						<h2>How many skincare products do you use&nbsp;daily?</h2>
						<div class="lprq__pills" data-field="product_count">
							<button type="button" class="lprq__pill" data-value="1-3">1 to 3 products</button>
							<button type="button" class="lprq__pill" data-value="4-6">4 to 6 products</button>
							<button type="button" class="lprq__pill" data-value="7+">7 or more</button>
						</div>
					</div>

					<div class="lprq__step" data-step="4">
						<h2>What frustrates you most about&nbsp;skincare?</h2>
						<div class="lprq__pills" data-field="frustration">
							<button type="button" class="lprq__pill" data-value="Nothing works long enough">Nothing works long enough</button>
							<button type="button" class="lprq__pill" data-value="Too many products">Too many products</button>
							<button type="button" class="lprq__pill" data-value="Don&rsquo;t trust ingredients">Don&rsquo;t trust the ingredients</button>
							<button type="button" class="lprq__pill" data-value="Just want something simple">Just want something simple</button>
						</div>
					</div>

					<div class="lprq__step" data-step="5">
						<h2>Where should we send your&nbsp;routine?</h2>
						<input type="email" class="lprq__input" id="lprq-email" placeholder="you@email.com" autocomplete="email" />
						<p class="lprq__privacy">One email with your matches. We don&rsquo;t share your address. Unsubscribe anytime.</p>
						<button type="button" class="lprq__btn" data-submit>Get My Routine</button>
						<div class="lprq__error" id="lprq-error"></div>
					</div>

					<div class="lprq__step" data-step="loading">
						<div class="lprq__loading">Building your routine&hellip;</div>
					</div>

					<div class="lprq__step" data-step="results">
						<div class="lprq__results">
							<h2 class="lprq__results-heading" id="lprq-result-greeting">Your match</h2>
							<p class="lprq__diagnostic" id="lprq-result-diagnostic"></p>
							<p class="lprq__results-why" id="lprq-result-why"></p>
							<div id="lprq-result-primary"></div>
							<div class="lprq__testimonial" id="lprq-result-testimonial"></div>
							<div class="lprq__pairs-note" id="lprq-result-pairs"></div>
							<div class="lprq__shop-all"><a class="lprq__cta-track" data-cta-id="shop_all" href="<?php echo esc_url( add_query_arg( 'cta_id', 'shop_all', home_url( '/shop-all/' ) ) ); ?>">Or shop the full line &rarr;</a></div>
							<?php
							$callout = apply_filters( 'lprq_results_callout', '' );
							if ( ! empty( $callout ) ) {
								echo '<div class="lprq__callout">' . wp_kses_post( $callout ) . '</div>';
							}
							?>
							<p class="lprq__reassurance" id="lprq-reassurance"></p>
							<p class="lprq__signoff"><?php echo esc_html( apply_filters( 'lprq_signoff', '' ) ); ?></p>
						</div>
					</div>
				</form>
				<div class="lprq__lightbox" id="lprq-lightbox" role="dialog" aria-modal="true" aria-label="Product image preview">
					<button type="button" class="lprq__lightbox-close" id="lprq-lightbox-close" aria-label="Close preview">&times;</button>
					<img class="lprq__lightbox-img" id="lprq-lightbox-img" alt="" />
				</div>

			</div>
		</div>

		<script>
		(function() {
			var STORAGE_KEY = 'lprq_progress_v1';
			var STORAGE_TTL = 24 * 60 * 60 * 1000; // 24 hours
			var quizData = {};
			var stepHistory = [1];
			var currentStep = 1;

			function saveProgress() {
				try {
					if (currentStep === 'results' || currentStep === 'loading') return;
					localStorage.setItem(STORAGE_KEY, JSON.stringify({
						quizData: quizData,
						stepHistory: stepHistory,
						currentStep: currentStep,
						ts: Date.now()
					}));
				} catch (e) { /* localStorage blocked, fail silent */ }
			}

			function clearProgress() {
				try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
			}

			function restoreProgress() {
				try {
					var raw = localStorage.getItem(STORAGE_KEY);
					if (!raw) return false;
					var saved = JSON.parse(raw);
					if (!saved || !saved.ts || (Date.now() - saved.ts) > STORAGE_TTL) {
						clearProgress();
						return false;
					}
					if (typeof saved.currentStep !== 'number' || saved.currentStep < 2) return false;
					quizData = saved.quizData || {};
					stepHistory = saved.stepHistory || [1];
					var nameInput = document.getElementById('lprq-name');
					if (nameInput && quizData.firstname) nameInput.value = quizData.firstname;
					// Mark previously selected pills
					Object.keys(quizData).forEach(function(field) {
						var pill = document.querySelector('[data-field="' + field + '"] [data-value="' + quizData[field] + '"]');
						if (pill) pill.classList.add('lprq__pill--selected');
					});
					return saved.currentStep;
				} catch (e) { return false; }
			}
			var label = document.getElementById('lprq-label');
			var form = document.getElementById('lprq-form');
			var nameInput = document.getElementById('lprq-name');
			var nameError = document.getElementById('lprq-name-error');
			var emailInput = document.getElementById('lprq-email');
			var errorEl = document.getElementById('lprq-error');

			function showStep(n) {
				document.querySelectorAll('.lprq__step').forEach(function(s) { s.classList.remove('lprq__step--active'); });
				var target = document.querySelector('[data-step="' + n + '"]');
				if (target) target.classList.add('lprq__step--active');
				if (typeof n === 'number') {
					currentStep = n;
					label.textContent = 'Step ' + n + ' of 5';
					updateStepIndicators(n);
				}
				window.scrollTo({ top: 0, behavior: 'smooth' });
				saveProgress();
			}

			function updateStepIndicators(current) {
				var dots = document.querySelectorAll('.lprq__step-dot');
				var lines = document.querySelectorAll('.lprq__step-line');
				dots.forEach(function(dot, idx) {
					var stepNum = idx + 1;
					dot.classList.remove('lprq__step-dot--done', 'lprq__step-dot--current');
					if (stepNum < current) {
						dot.classList.add('lprq__step-dot--done');
					} else if (stepNum === current) {
						dot.classList.add('lprq__step-dot--current');
					}
				});
				lines.forEach(function(line, idx) {
					if (idx + 1 < current) {
						line.classList.add('lprq__step-line--done');
					} else {
						line.classList.remove('lprq__step-line--done');
					}
				});
			}

			// Wire up clickable step indicators (only past steps are clickable)
			document.querySelectorAll('.lprq__step-dot').forEach(function(dot) {
				dot.addEventListener('click', function() {
					var target = parseInt(dot.getAttribute('data-goto'), 10);
					if (target && target < currentStep) {
						// Pop history back to target step
						while (stepHistory.length > 1 && stepHistory[stepHistory.length - 1] > target) {
							stepHistory.pop();
						}
						showStep(target);
					}
				});
			});

			function validateFirstName(raw) {
				var trimmed = raw.trim();
				if (!trimmed) { return { ok: false, msg: 'Tell us your first name.' }; }
				if (trimmed.length > 30) { return { ok: false, msg: 'Too long. First name only please.' }; }
				if (/\s/.test(trimmed)) { return { ok: false, msg: 'Just your first name, no spaces.' }; }
				if (!/^[A-Za-zÀ-ſ'\-]+$/.test(trimmed)) { return { ok: false, msg: 'Letters only.' }; }
				return { ok: true, value: trimmed };
			}

			// Live validation on first name field
			nameInput.addEventListener('input', function() {
				// Strip anything after first space as a UX nudge
				var val = nameInput.value;
				if (val.indexOf(' ') !== -1) {
					nameInput.value = val.split(' ')[0];
				}
				nameInput.classList.remove('lprq__input-error');
				nameError.textContent = '';
			});

			document.querySelectorAll('.lprq__pill').forEach(function(pill) {
				pill.addEventListener('click', function() {
					var container = pill.parentElement;
					var field = container.getAttribute('data-field');
					container.querySelectorAll('.lprq__pill').forEach(function(p) { p.classList.remove('lprq__pill--selected'); });
					pill.classList.add('lprq__pill--selected');
					quizData[field] = pill.getAttribute('data-value');
					setTimeout(function() {
						var next = currentStep + 1;
						stepHistory.push(next);
						showStep(next);
					}, 250);
				});
			});

			document.querySelectorAll('[data-next]').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (currentStep === 1) {
						var v = validateFirstName(nameInput.value);
						if (!v.ok) {
							nameError.textContent = v.msg;
							nameInput.classList.add('lprq__input-error');
							nameInput.focus();
							return;
						}
						quizData.firstname = v.value;
					}
					var next = currentStep + 1;
					stepHistory.push(next);
					showStep(next);
				});
			});

			nameInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					document.querySelector('[data-next]').click();
				}
			});
			emailInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					document.querySelector('[data-submit]').click();
				}
			});

			document.querySelector('[data-submit]').addEventListener('click', function() {
				var email = emailInput.value.trim();
				if (!email || email.indexOf('@') < 1) { errorEl.textContent = 'Please enter a valid email.'; emailInput.focus(); return; }
				errorEl.textContent = '';
				quizData.email = email;
				if (!quizData.skin_concern || !quizData.product_count || !quizData.frustration || !quizData.firstname) {
					errorEl.textContent = 'Looks like an answer is missing. Use Back to check.';
					return;
				}

				showStep('loading');

				var fd = new FormData();
				fd.append('action', 'lprq_submit');
				fd.append('nonce', form.querySelector('#lprq_nonce').value);
				fd.append('firstname', quizData.firstname);
				fd.append('email', quizData.email);
				fd.append('skin_concern', quizData.skin_concern);
				fd.append('product_count', quizData.product_count);
				fd.append('frustration', quizData.frustration);

				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data && data.success) {
							renderResults(data.data);
						} else {
							errorEl.textContent = (data && data.data && data.data.message) ? data.data.message : 'Something went wrong. Please try again.';
							showStep(5);
						}
					})
					.catch(function() {
						errorEl.textContent = 'Network error. Please try again.';
						showStep(5);
					});
			});

			function renderResults(payload) {
				clearProgress();
				window.lprqAddBothUrl = payload.add_both_url || '';
				document.getElementById('lprq-result-greeting').textContent = quizData.firstname ? 'Your match, ' + quizData.firstname : 'Your match';
				var diag = document.getElementById('lprq-result-diagnostic');
				if (diag) {
					diag.innerHTML = payload.diagnostic || '';
				}
				var reass = document.getElementById('lprq-reassurance');
				if (reass) {
					reass.textContent = 'Saved your match. I’ll check in via email.';
				}
				var whyText = payload.why || '';
				// Split into multiple paragraphs at every '<strong>' transition for
				// scannable mobile reading. Also break out the (Moxie addendum) as
				// its own italic line.
				whyText = whyText.replace(/\. <strong>/g, '.</p><p><strong>');
				whyText = whyText.replace(/ <em>\(Holly built/g, '</p><p class="lprq__why-aside"><em>(Holly built');
				whyText = whyText.replace(/line\.\)<\/em>/g, 'line.)</em>');
				whyText = '<p>' + whyText + '</p>';
				document.getElementById('lprq-result-why').innerHTML = whyText;
				var testimonialEl = document.getElementById('lprq-result-testimonial');
				if (testimonialEl && payload.testimonial) {
					testimonialEl.innerHTML = '<blockquote>&ldquo;' + payload.testimonial.quote + '&rdquo;<cite>&mdash; ' + (payload.testimonial.attribution || 'verified customer') + '</cite></blockquote>';
				} else if (testimonialEl) {
					testimonialEl.innerHTML = '';
				}
				renderPrimary(payload.primary);
				renderSecondary(payload.secondary);
				showStep('results');
				if (typeof gtag === 'function') {
					gtag('event', 'quiz_completed', { skin_concern: quizData.skin_concern });
				}
			}

			function renderPrimary(p) {
				var slot = document.getElementById('lprq-result-primary');
				if (!slot || !p) return;
				var altText = 'Sego Lily ' + p.name + (p.scent ? ', ' + p.scent + ' scent' : '') + ', small-batch tallow skincare from Montana';
				var imgHtml = p.image_url
					? '<img src="' + p.image_url + '" alt="' + altText + '" loading="lazy" width="200" height="200" data-lprq-zoom="1" data-lprq-zoom-src="' + p.image_url + '" data-lprq-zoom-alt="' + altText + '" />'
					: p.name;
				var badgeHtml = p.badge ? '<span class="lprq__product-badge">' + p.badge + '</span>' : '';
				var cartUrl = p.add_to_cart_url || p.shop_url;
				var pdpUrl = p.shop_url || '';
				var nameHtml = pdpUrl
					? '<a class="lprq__product-name-link lprq__cta-track" data-cta-id="primary_name" data-product="' + (p.slug || '') + '" href="' + pdpUrl + '" target="_blank" rel="noopener">' + p.name + '</a>'
					: p.name;
				slot.innerHTML =
					'<div class="lprq__primary-product">' +
						'<div class="lprq__product-image">' + imgHtml + '</div>' +
						'<div class="lprq__product-body">' +
							'<div class="lprq__product-label">Start here</div>' +
							'<div class="lprq__product-name">' + nameHtml + ' ' + badgeHtml + '</div>' +
							'<div class="lprq__product-scent">' + p.scent + '</div>' +
							'<div class="lprq__product-blurb">' + p.blurb + '</div>' +
							'<a class="lprq__product-link lprq__cta-track" data-cta-id="primary" data-product="' + (p.slug || '') + '" href="' + cartUrl + '" rel="nofollow">Build my routine\u00a0&rarr;</a>' +
						'</div>' +
					'</div>';
			}

			function renderSecondary(p) {
				var slot = document.getElementById('lprq-result-pairs');
				if (!slot || !p) return;
				var addBothHtml = '';
				if (window.lprqAddBothUrl) {
					addBothHtml = '<a class="lprq__add-both lprq__cta-track" data-cta-id="add_both" href="' + window.lprqAddBothUrl + '" rel="nofollow">Add both to my routine\u00a0&rarr;</a>';
				}
				var badgeHtml = p.badge ? '<span class="lprq__product-badge lprq__product-badge--small">' + p.badge + '</span>' : '';
				var pdpUrl = p.shop_url || '';
				var nameHtml = pdpUrl
					? '<a class="lprq__product-name-link lprq__cta-track" data-cta-id="secondary_name" data-product="' + (p.slug || '') + '" href="' + pdpUrl + '" target="_blank" rel="noopener">' + p.name + '</a>'
					: p.name;
				var thumbHtml = '';
				if (p.image_url) {
					thumbHtml = '<img class="lprq__pairs-thumb" src="' + p.image_url + '" alt="' + p.name + '" loading="lazy" width="56" height="56" data-lprq-zoom="1" data-lprq-zoom-src="' + p.image_url + '" data-lprq-zoom-alt="' + p.name + '" />';
				}
				var pairsContent =
					'<span class="lprq__pairs-note-label">Pairs well with</span>' +
					'<div class="lprq__pairs-row">' +
						thumbHtml +
						'<div class="lprq__pairs-info">' +
							'<div class="lprq__pairs-name">' + nameHtml + ' ' + badgeHtml + '</div>' +
							'<div class="lprq__pairs-scent">' + p.scent + '</div>' +
							'<div class="lprq__pairs-blurb">' + (p.blurb || '') + '</div>' +
						'</div>' +
					'</div>' +
					addBothHtml;
				slot.innerHTML = pairsContent;
			}

			// Lightbox: clicking a product image opens a fullscreen preview.
			// Click anywhere on the dark backdrop (or the X) to close. The image
			// itself doesn't propagate the click, so users can pan-touch on
			// mobile without accidentally closing.
			(function() {
				var lb       = document.getElementById('lprq-lightbox');
				var lbImg    = document.getElementById('lprq-lightbox-img');
				var lbClose  = document.getElementById('lprq-lightbox-close');
				if (!lb || !lbImg) return;
				function open(src, alt) {
					lbImg.src = src;
					lbImg.alt = alt || '';
					lb.classList.add('is-open');
					document.body.style.overflow = 'hidden';
				}
				function close() {
					lb.classList.remove('is-open');
					lbImg.src = '';
					document.body.style.overflow = '';
				}
				document.addEventListener('click', function(e) {
					var trigger = e.target.closest && e.target.closest('[data-lprq-zoom]');
					if (!trigger) return;
					e.preventDefault();
					open(trigger.getAttribute('data-lprq-zoom-src') || trigger.src, trigger.getAttribute('data-lprq-zoom-alt') || trigger.alt);
				});
				lb.addEventListener('click', function(e) {
					if (e.target === lbImg) return;
					close();
				});
				if (lbClose) lbClose.addEventListener('click', close);
				document.addEventListener('keydown', function(e) {
					if (e.key === 'Escape' && lb.classList.contains('is-open')) close();
				});
			})();


			// Per-CTA tracking on results-page actions. Fires a gtag event
			// before the link navigates (works because gtag is async but the
			// browser still sends the beacon before unload). Falls back silently
			// if gtag isn't loaded.
			document.addEventListener('click', function(e) {
				var link = e.target.closest && e.target.closest('.lprq__cta-track');
				if (!link) return;
				var ctaId   = link.getAttribute('data-cta-id') || 'unknown';
				var product = link.getAttribute('data-product') || '';
				if (typeof gtag === 'function') {
					gtag('event', 'quiz_cta_click', {
						cta_id: ctaId,
						product_slug: product,
						skin_concern: quizData.skin_concern || '',
						frustration: quizData.frustration || ''
					});
				}
			});

			// Resume quiz from a recent saved state (within 24h)
			var resumeStep = restoreProgress();
			if (resumeStep) {
				showStep(resumeStep);
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	public static function handle_submit() {
		check_ajax_referer( 'lprq_quiz', 'nonce' );

		$firstname     = sanitize_text_field( wp_unslash( $_POST['firstname'] ?? '' ) );
		$email         = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$skin_concern  = sanitize_text_field( wp_unslash( $_POST['skin_concern'] ?? '' ) );
		$product_count = sanitize_text_field( wp_unslash( $_POST['product_count'] ?? '' ) );
		$frustration   = sanitize_text_field( wp_unslash( $_POST['frustration'] ?? '' ) );

		// Server-side first name validation: strip after first space, cap at 30.
		$firstname = preg_replace( '/\s.*$/', '', $firstname );
		$firstname = substr( $firstname, 0, 30 );

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}
		if ( empty( $firstname ) || empty( $skin_concern ) ) {
			wp_send_json_error( array( 'message' => 'Looks like an answer is missing.' ) );
		}

		$recommendation = SLRQ_Recommendations::pair_for( $skin_concern, $frustration, $product_count, $firstname );

		$mautic_result = SLRQ_Mautic::send_quiz_lead( array(
			'email'         => $email,
			'firstname'     => $firstname,
			'skin_concern'  => $skin_concern,
			'product_count' => $product_count,
			'frustration'   => $frustration,
		) );

		// Quiz still succeeds for the user even if Mautic sync fails. They get
		// their recommendation. We log the failure for the admin to address.
		if ( ! $mautic_result['success'] ) {
			error_log( '[LPRQ] Mautic sync failed for ' . $email . ': ' . $mautic_result['message'] );
		}

		do_action( 'lprq_quiz_completed', array(
			'email'           => $email,
			'firstname'       => $firstname,
			'skin_concern'    => $skin_concern,
			'product_count'   => $product_count,
			'frustration'     => $frustration,
			'recommendation'  => $recommendation,
			'mautic_synced'   => $mautic_result['success'],
		) );

		wp_send_json_success( $recommendation );
	}
}

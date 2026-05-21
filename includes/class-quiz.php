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
		body.lprq-landing .slw-customer-mode-toggle,
		body.lprq-landing .slw-mode-toggle,
		body.lprq-landing .wholesale-toggle,
		body.lprq-landing .for-my-store-toggle { display: none !important; }

		body.lprq-landing { background: linear-gradient(135deg, #F7F6F3 0%, #EEF3F5 100%); margin: 0; }

		.lprq-wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 16px; box-sizing: border-box; }
		.lprq { max-width: 620px; width: 100%; padding: 40px 32px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 40px rgba(56, 97, 116, 0.12), 0 2px 8px rgba(56, 97, 116, 0.06); font-family: Georgia, 'Times New Roman', serif; color: #2C2C2C; box-sizing: border-box; }
		.lprq * { box-sizing: border-box; }
		.lprq__brand { text-align: center; font-size: 13px; letter-spacing: 2px; color: #386174; font-weight: bold; margin-bottom: 24px; }
		.lprq__progress { margin: 0 0 40px; }
		.lprq__progress-bar { background: #E8E2D6; height: 4px; border-radius: 2px; overflow: hidden; }
		.lprq__progress-fill { background: #386174; height: 100%; transition: width 0.4s ease; width: 20%; }
		.lprq__progress-label { font-size: 12px; color: #8A9499; text-align: center; margin-top: 10px; letter-spacing: 1px; text-transform: uppercase; }
		.lprq__step { display: none; opacity: 0; transition: opacity 0.3s ease; }
		.lprq__step--active { display: block; opacity: 1; }
		.lprq__step h2 { font-size: 28px; font-weight: 600; margin: 0 0 32px; text-align: center; line-height: 1.3; color: #2C2C2C; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__input { width: 100%; padding: 16px 18px; font-size: 17px; border: 2px solid #D4CFC4; border-radius: 10px; background: #FAFAF7; outline: none; font-family: Georgia, 'Times New Roman', serif; color: #2C2C2C; transition: all 0.15s ease; }
		.lprq__input:focus { border-color: #386174; background: #ffffff; box-shadow: 0 0 0 3px rgba(56, 97, 116, 0.1); }
		.lprq__input-error { border-color: #b8302e !important; }
		.lprq__pills { display: flex; flex-direction: column; gap: 12px; margin: 0 0 24px; }
		.lprq__pill { display: block; width: 100%; padding: 18px 22px; font-size: 16px; font-weight: 500; background: #ffffff; border: 2px solid #386174; color: #386174; border-radius: 10px; cursor: pointer; text-align: left; font-family: Georgia, 'Times New Roman', serif; transition: all 0.15s ease; line-height: 1.4; }
		.lprq__pill:hover { background: #386174; color: #ffffff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__pill--selected { background: #386174; color: #ffffff; box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__btn { display: block; width: 100%; padding: 16px 24px; font-size: 17px; font-weight: 600; background: #386174; color: #ffffff; border: none; border-radius: 10px; cursor: pointer; margin-top: 20px; font-family: Georgia, 'Times New Roman', serif; transition: all 0.15s ease; letter-spacing: 0.3px; }
		.lprq__btn:hover { background: #2a4a5a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 97, 116, 0.2); }
		.lprq__back { display: block; margin: 20px auto 0; padding: 8px 16px; font-size: 14px; color: #8A9499; background: none; border: none; cursor: pointer; font-family: Georgia, 'Times New Roman', serif; text-decoration: underline; }
		.lprq__back:hover { color: #386174; }
		.lprq__error { color: #b8302e; font-size: 14px; margin-top: 10px; min-height: 20px; font-family: Georgia, 'Times New Roman', serif; }
		.lprq__loading { text-align: center; padding: 80px 20px; color: #8A9499; font-size: 16px; font-style: italic; }
		.lprq__results { text-align: center; }
		.lprq__results-greeting { font-size: 14px; color: #8A9499; letter-spacing: 1px; text-transform: uppercase; margin: 0 0 12px; }
		.lprq__results-heading { font-size: 32px; font-weight: 600; margin: 0 0 16px; color: #2C2C2C; line-height: 1.3; }
		.lprq__results-why { font-size: 16px; color: #4a5d68; line-height: 1.6; margin: 0 0 36px; padding: 20px 24px; background: #F7F6F3; border-radius: 10px; text-align: left; }
		.lprq__products { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px; text-align: left; }
		.lprq__product { background: #ffffff; border: 1px solid #E8E2D6; border-radius: 12px; padding: 24px; }
		.lprq__product-image { width: 100%; aspect-ratio: 1; background: #F7F6F3; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; color: #B8A98C; font-size: 13px; overflow: hidden; }
		.lprq__product-image img { width: 100%; height: 100%; object-fit: cover; }
		.lprq__product-name { font-size: 18px; font-weight: 600; margin: 0 0 4px; color: #2C2C2C; }
		.lprq__product-scent { font-size: 14px; color: #8A9499; margin: 0 0 10px; font-style: italic; }
		.lprq__product-blurb { font-size: 14px; color: #4a5d68; line-height: 1.5; margin: 0 0 16px; }
		.lprq__product-link { display: inline-block; padding: 10px 18px; font-size: 14px; font-weight: 600; background: #386174; color: #ffffff !important; text-decoration: none; border-radius: 6px; }
		.lprq__product-link:hover { background: #2a4a5a; }
		.lprq__signoff { font-size: 15px; color: #628393; font-style: italic; margin-top: 28px; }

		@media (max-width: 540px) {
			.lprq { padding: 28px 20px; }
			.lprq__step h2 { font-size: 22px; margin: 0 0 24px; }
			.lprq__pill { padding: 16px 18px; font-size: 15px; }
			.lprq__products { grid-template-columns: 1fr; gap: 16px; }
			.lprq__results-heading { font-size: 26px; }
		}
		</style>

		<div class="lprq-wrap">
			<div class="lprq" id="lprq">

				<div class="lprq__brand">SEGO LILY SKINCARE</div>

				<div class="lprq__progress">
					<div class="lprq__progress-bar"><div class="lprq__progress-fill" id="lprq-fill"></div></div>
					<div class="lprq__progress-label" id="lprq-label">Step 1 of 5</div>
				</div>

				<form id="lprq-form" autocomplete="on" onsubmit="return false;">
					<?php wp_nonce_field( 'lprq_quiz', 'lprq_nonce' ); ?>

					<div class="lprq__step lprq__step--active" data-step="1">
						<h2>What&rsquo;s your first name?</h2>
						<input type="text" class="lprq__input" id="lprq-name" placeholder="First name" autocomplete="given-name" maxlength="30" />
						<div class="lprq__error" id="lprq-name-error"></div>
						<button type="button" class="lprq__btn" data-next>Next</button>
					</div>

					<div class="lprq__step" data-step="2">
						<h2>What bugs you most about your skin?</h2>
						<div class="lprq__pills" data-field="skin_concern">
							<button type="button" class="lprq__pill" data-value="Wrinkles &amp; dark spots">Wrinkles &amp; dark spots</button>
							<button type="button" class="lprq__pill" data-value="Dryness &amp; tightness">Dryness &amp; tightness</button>
							<button type="button" class="lprq__pill" data-value="Redness &amp; sensitivity">Redness &amp; sensitivity</button>
							<button type="button" class="lprq__pill" data-value="Breakouts">Breakouts</button>
						</div>
						<button type="button" class="lprq__back" data-back>&larr; Back</button>
					</div>

					<div class="lprq__step" data-step="3">
						<h2>How many skincare products do you use daily?</h2>
						<div class="lprq__pills" data-field="product_count">
							<button type="button" class="lprq__pill" data-value="1-3">1 to 3 products</button>
							<button type="button" class="lprq__pill" data-value="4-6">4 to 6 products</button>
							<button type="button" class="lprq__pill" data-value="7+">7 or more</button>
						</div>
						<button type="button" class="lprq__back" data-back>&larr; Back</button>
					</div>

					<div class="lprq__step" data-step="4">
						<h2>What frustrates you most about skincare?</h2>
						<div class="lprq__pills" data-field="frustration">
							<button type="button" class="lprq__pill" data-value="Nothing works long enough">Nothing works long enough</button>
							<button type="button" class="lprq__pill" data-value="Too many products">Too many products</button>
							<button type="button" class="lprq__pill" data-value="Don&rsquo;t trust ingredients">Don&rsquo;t trust the ingredients</button>
							<button type="button" class="lprq__pill" data-value="Just want something simple">Just want something simple</button>
						</div>
						<button type="button" class="lprq__back" data-back>&larr; Back</button>
					</div>

					<div class="lprq__step" data-step="5">
						<h2>Where should we send your routine?</h2>
						<input type="email" class="lprq__input" id="lprq-email" placeholder="you@email.com" autocomplete="email" />
						<button type="button" class="lprq__btn" data-submit>Get My Routine</button>
						<div class="lprq__error" id="lprq-error"></div>
						<button type="button" class="lprq__back" data-back>&larr; Back</button>
					</div>

					<div class="lprq__step" data-step="loading">
						<div class="lprq__loading">Building your routine&hellip;</div>
					</div>

					<div class="lprq__step" data-step="results">
						<div class="lprq__results">
							<p class="lprq__results-greeting" id="lprq-result-greeting"></p>
							<h2 class="lprq__results-heading">Your routine is 2 things.</h2>
							<p class="lprq__results-why" id="lprq-result-why"></p>
							<div class="lprq__products" id="lprq-result-products"></div>
							<p class="lprq__signoff"><?php echo esc_html( apply_filters( 'lprq_signoff', '' ) ); ?></p>
						</div>
					</div>
				</form>

			</div>
		</div>

		<script>
		(function() {
			var quizData = {};
			var stepHistory = [1];
			var currentStep = 1;
			var fill = document.getElementById('lprq-fill');
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
					var pct = Math.round((n / 5) * 100);
					fill.style.width = pct + '%';
					label.textContent = 'Step ' + n + ' of 5';
				}
				// Scroll the step into view (helps on mobile)
				window.scrollTo({ top: 0, behavior: 'smooth' });
			}

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

			document.querySelectorAll('[data-back]').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (stepHistory.length > 1) {
						stepHistory.pop();
						showStep(stepHistory[stepHistory.length - 1]);
					}
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
				document.getElementById('lprq-result-greeting').textContent = 'For ' + (quizData.firstname || 'you');
				document.getElementById('lprq-result-why').textContent = payload.why || '';
				var grid = document.getElementById('lprq-result-products');
				grid.innerHTML = '';
				[payload.primary, payload.secondary].forEach(function(p) {
					if (!p) return;
					var card = document.createElement('div');
					card.className = 'lprq__product';
					card.innerHTML =
						'<div class="lprq__product-image">' + (p.image_url ? '<img src="' + p.image_url + '" alt="' + p.name + '" />' : p.name) + '</div>' +
						'<div class="lprq__product-name">' + p.name + '</div>' +
						'<div class="lprq__product-scent">' + p.scent + '</div>' +
						'<div class="lprq__product-blurb">' + p.blurb + '</div>' +
						'<a class="lprq__product-link" href="' + p.shop_url + '">Shop &rarr;</a>';
					grid.appendChild(card);
				});
				showStep('results');
				if (typeof gtag === 'function') {
					gtag('event', 'quiz_completed', { skin_concern: quizData.skin_concern });
				}
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

		$recommendation = SLRQ_Recommendations::pair_for( $skin_concern, $frustration );

		$mautic_result = SLRQ_Mautic::send_quiz_lead( array(
			'email'         => $email,
			'firstname'     => $firstname,
			'skin_concern'  => $skin_concern,
			'product_count' => $product_count,
			'frustration'   => $frustration,
		) );

		// Quiz still succeeds for the user even if Mautic sync fails — they get
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

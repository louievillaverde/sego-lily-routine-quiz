<?php
/**
 * LP Quiz Suite: renders the on-site routine quiz + handles submission.
 *
 * Uses SLRQ_Mautic for lead sync. No dependency on sibling plugins.
 *
 * @package LPQuizSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Quiz {

	public static function init() {
		add_shortcode( 'lp_routine_quiz', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_ajax_lprq_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_lprq_submit', array( __CLASS__, 'handle_submit' ) );
	}

	public static function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array(
			'heading'    => 'Build Your Skincare Routine',
			'subheading' => 'Two minutes. Five questions. A routine matched to your skin.',
		), $atts );

		ob_start();
		?>
		<style>
		.lprq { max-width: 580px; margin: 0 auto; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #2c3e44; }
		.lprq__intro { text-align: center; margin-bottom: 32px; }
		.lprq__title { font-size: 28px; font-weight: 600; margin: 0 0 12px; color: #2c3e44; }
		.lprq__subtitle { font-size: 16px; color: #628393; margin: 0; line-height: 1.5; }
		.lprq__progress { margin: 24px 0; }
		.lprq__progress-bar { background: #E8E2D6; height: 4px; border-radius: 2px; overflow: hidden; }
		.lprq__progress-fill { background: #386174; height: 100%; transition: width 0.3s ease; width: 20%; }
		.lprq__progress-label { font-size: 13px; color: #628393; text-align: center; margin-top: 8px; }
		.lprq__step { display: none; }
		.lprq__step--active { display: block; }
		.lprq__step h2 { font-size: 22px; font-weight: 600; margin: 0 0 24px; text-align: center; line-height: 1.4; }
		.lprq__input { width: 100%; padding: 14px 16px; font-size: 16px; border: 1px solid #D4CFC4; border-radius: 8px; background: #FAFAF7; box-sizing: border-box; font-family: inherit; }
		.lprq__input:focus { outline: none; border-color: #386174; background: #fff; }
		.lprq__pills { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
		.lprq__pill { padding: 14px 18px; font-size: 15px; background: #FAFAF7; border: 1px solid #D4CFC4; border-radius: 8px; cursor: pointer; text-align: left; font-family: inherit; color: #2c3e44; transition: all 0.15s; }
		.lprq__pill:hover { border-color: #386174; background: #fff; }
		.lprq__pill--selected { border-color: #386174; background: #EEF3F5; }
		.lprq__btn { width: 100%; padding: 14px 20px; font-size: 16px; font-weight: 600; background: #386174; color: #fff; border: none; border-radius: 8px; cursor: pointer; margin-top: 16px; font-family: inherit; }
		.lprq__btn:hover { background: #2a4a5a; }
		.lprq__back { display: inline-block; margin-top: 12px; padding: 8px 0; font-size: 14px; color: #628393; background: none; border: none; cursor: pointer; font-family: inherit; }
		.lprq__back:hover { color: #386174; }
		.lprq__error { color: #b8302e; font-size: 14px; margin-top: 8px; min-height: 20px; }
		.lprq__loading { text-align: center; padding: 60px 20px; color: #628393; }
		.lprq__results { text-align: center; }
		.lprq__results-name { font-size: 18px; color: #628393; margin: 0 0 8px; }
		.lprq__results-heading { font-size: 26px; font-weight: 600; margin: 0 0 12px; color: #2c3e44; }
		.lprq__results-why { font-size: 15px; color: #4a5d68; line-height: 1.5; margin: 0 0 32px; padding: 16px; background: #FAFAF7; border-radius: 8px; }
		.lprq__products { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 28px; }
		@media (max-width: 480px) { .lprq__products { grid-template-columns: 1fr; } .lprq__pills { gap: 8px; } .lprq__pill { padding: 12px 14px; font-size: 14px; } }
		.lprq__product { background: #fff; border: 1px solid #E8E2D6; border-radius: 12px; padding: 20px; text-align: left; }
		.lprq__product-image { width: 100%; aspect-ratio: 1; background: #F7F6F3; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; color: #B8A98C; font-size: 14px; overflow: hidden; }
		.lprq__product-image img { width: 100%; height: 100%; object-fit: cover; }
		.lprq__product-name { font-size: 17px; font-weight: 600; margin: 0 0 4px; color: #2c3e44; }
		.lprq__product-scent { font-size: 14px; color: #628393; margin: 0 0 8px; }
		.lprq__product-blurb { font-size: 13px; color: #4a5d68; line-height: 1.4; margin: 0 0 12px; }
		.lprq__product-link { display: inline-block; font-size: 14px; font-weight: 600; color: #386174; text-decoration: none; }
		.lprq__product-link:hover { text-decoration: underline; }
		.lprq__signoff { font-size: 14px; color: #628393; font-style: italic; margin-top: 24px; }
		</style>

		<div class="lprq" id="lprq">

			<div class="lprq__intro">
				<h1 class="lprq__title"><?php echo esc_html( $atts['heading'] ); ?></h1>
				<p class="lprq__subtitle"><?php echo esc_html( $atts['subheading'] ); ?></p>
			</div>

			<div class="lprq__progress">
				<div class="lprq__progress-bar"><div class="lprq__progress-fill" id="lprq-fill"></div></div>
				<div class="lprq__progress-label" id="lprq-label">Step 1 of 5</div>
			</div>

			<form id="lprq-form" autocomplete="on" onsubmit="return false;">
				<?php wp_nonce_field( 'lprq_quiz', 'lprq_nonce' ); ?>

				<div class="lprq__step lprq__step--active" data-step="1">
					<h2>What's your first name?</h2>
					<input type="text" class="lprq__input" id="lprq-name" placeholder="First name" autocomplete="given-name" />
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
					<button type="button" class="lprq__back" data-back>Back</button>
				</div>

				<div class="lprq__step" data-step="3">
					<h2>How many skincare products do you use daily?</h2>
					<div class="lprq__pills" data-field="product_count">
						<button type="button" class="lprq__pill" data-value="1-3">1 to 3 products</button>
						<button type="button" class="lprq__pill" data-value="4-6">4 to 6 products</button>
						<button type="button" class="lprq__pill" data-value="7+">7 or more</button>
					</div>
					<button type="button" class="lprq__back" data-back>Back</button>
				</div>

				<div class="lprq__step" data-step="4">
					<h2>What frustrates you most about skincare?</h2>
					<div class="lprq__pills" data-field="frustration">
						<button type="button" class="lprq__pill" data-value="Nothing works long enough">Nothing works long enough</button>
						<button type="button" class="lprq__pill" data-value="Too many products">Too many products</button>
						<button type="button" class="lprq__pill" data-value="Don't trust ingredients">Don't trust the ingredients</button>
						<button type="button" class="lprq__pill" data-value="Just want something simple">Just want something simple</button>
					</div>
					<button type="button" class="lprq__back" data-back>Back</button>
				</div>

				<div class="lprq__step" data-step="5">
					<h2>Where should we send your routine?</h2>
					<input type="email" class="lprq__input" id="lprq-email" placeholder="you@email.com" autocomplete="email" />
					<button type="button" class="lprq__btn" data-submit>Get My Routine</button>
					<div class="lprq__error" id="lprq-error"></div>
					<button type="button" class="lprq__back" data-back>Back</button>
				</div>

				<div class="lprq__step" data-step="loading">
					<div class="lprq__loading">Building your routine...</div>
				</div>

				<div class="lprq__step" data-step="results">
					<div class="lprq__results">
						<p class="lprq__results-name" id="lprq-result-greeting"></p>
						<h2 class="lprq__results-heading">Your routine is 2 things.</h2>
						<p class="lprq__results-why" id="lprq-result-why"></p>
						<div class="lprq__products" id="lprq-result-products"></div>
						<p class="lprq__signoff"><?php echo esc_html( apply_filters( 'lprq_signoff', '' ) ); ?></p>
					</div>
				</div>
			</form>

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
			}

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
					}, 200);
				});
			});

			document.querySelectorAll('[data-next]').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (currentStep === 1) {
						var name = nameInput.value.trim();
						if (!name) { nameInput.focus(); return; }
						quizData.firstname = name;
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
				document.getElementById('lprq-result-greeting').textContent = 'For you, ' + (quizData.firstname || 'friend') + '.';
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

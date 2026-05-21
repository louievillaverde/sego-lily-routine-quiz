<?php
/**
 * Minimal landing-page template for the quiz.
 *
 * Bypasses the theme's normal page template so the quiz renders without
 * site header, footer, navigation, or wholesale-plugin toggles. Customer
 * sees only the quiz on a clean canvas.
 *
 * Loaded via the template_include filter in class-quiz.php when the page
 * contains the [lp_routine_quiz] shortcode.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="index, follow">
<?php wp_head(); ?>
<style>
html, body { margin: 0; padding: 0; background: linear-gradient(135deg, #F7F6F3 0%, #EEF3F5 100%); min-height: 100vh; font-family: Georgia, 'Times New Roman', serif; }
body { color: #2C2C2C; }
</style>
</head>
<body <?php body_class( 'lprq-landing' ); ?>>
<?php
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
wp_footer();
?>
</body>
</html>

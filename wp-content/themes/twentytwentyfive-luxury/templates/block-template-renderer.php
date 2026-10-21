<?php
/**
 * Minimal renderer for language-specific block templates.
 * Included via template_include (see mu-plugins/velore-i18n.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lang = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
$file = $lang ? get_theme_file_path( "templates/front-page-{$lang}.html" ) : '';

if ( $lang && $file && file_exists( $file ) ) {
	velore_i18n_render_block_template( $file );
} else {
	// Fallback: render the default front-page template content.
	get_header();
	$default = get_theme_file_path( 'templates/front-page.html' );
	if ( $default ) {
		echo do_blocks( (string) file_get_contents( $default ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	get_footer();
}

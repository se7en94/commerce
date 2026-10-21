<?php
/**
 * Plugin Name: VELORÉ Core — i18n Template Switching
 * Description: Serves language-specific theme files under Polylang: swaps the navigation block ref, the header/footer template parts and the front-page template to their {lang} variants when they exist. English (default language) keeps using the original files untouched.
 * Version:     1.0.0
 * Text Domain: velore-core
 *
 * mu-plugin: always on by design.
 *
 * How it works:
 * - core/navigation   → pre_render_block re-renders the block with a per-language
 *                       wp_navigation post id (option `velore_nav_ids`).
 * - core/template-part → pre_render_block swaps slug `header` / `footer` to
 *                       parts/header-{lang}.html / parts/footer-{lang}.html.
 * - front page        → template_include renders templates/front-page-{lang}.html
 *                       through a tiny block renderer.
 *
 * English always falls through — zero overhead for the default language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function velore_i18n_lang_slug(): string {
	if ( ! function_exists( 'pll_current_language' ) ) {
		return '';
	}
	$slug = pll_current_language( 'slug' );
	return $slug ? (string) $slug : '';
}

function velore_i18n_theme_file( string $rel ): ?string {
	$path = get_theme_file_path( $rel );
	return ( $path && file_exists( $path ) ) ? $path : null;
}

/* ---------------------------------------------------------------- nav block -- */

add_filter( 'pre_render_block', function ( $pre, $parsed_block ) {
	if ( 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $pre;
	}
	$lang = velore_i18n_lang_slug();
	if ( '' === $lang || 'en' === $lang ) {
		return $pre;
	}

	$ids   = get_option( 'velore_nav_ids', array() );
	$ref   = isset( $ids[ $lang ] ) ? (int) $ids[ $lang ] : 0;
	$attrs = $parsed_block['attrs'] ?? array();

	// Only swap the header's primary nav (the one that references an existing nav post).
	if ( ! $ref || empty( $attrs['ref'] ) || (int) $attrs['ref'] === $ref ) {
		return $pre;
	}

	static $in_swap = false;
	if ( $in_swap ) {
		return $pre; // guard against recursion through render_block()
	}
	$in_swap        = true;
	$swapped        = $parsed_block;
	$swapped['attrs']['ref'] = $ref;
	$html           = render_block( $swapped );
	$in_swap        = false;

	return $html;
}, 10, 2 );

/* ------------------------------------------------------- header/footer parts -- */

add_filter( 'pre_render_block', function ( $pre, $parsed_block ) {
	if ( 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $pre;
	}
	$lang = velore_i18n_lang_slug();
	if ( '' === $lang || 'en' === $lang ) {
		return $pre;
	}

	$slug = $parsed_block['attrs']['slug'] ?? '';
	if ( '' === $slug ) {
		return $pre;
	}

	$file = velore_i18n_theme_file( "parts/{$slug}-{$lang}.html" );
	if ( null === $file ) {
		return $pre; // no translation yet — keep original part
	}

	return do_blocks( (string) file_get_contents( $file ) );
}, 20, 2 );

/* -------------------------------------------------------------- front page -- */

add_filter( 'template_include', function ( $template ) {
	if ( ! is_front_page() ) {
		return $template;
	}
	$lang = velore_i18n_lang_slug();
	if ( '' === $lang || 'en' === $lang ) {
		return $template;
	}

	$file = velore_i18n_theme_file( "templates/front-page-{$lang}.html" );
	if ( null === $file ) {
		return $template;
	}

	return get_theme_file_path( 'templates/block-template-renderer.php' );
}, 20 );

/**
 * Renderer used by template_include above: renders a raw block-template HTML
 * file (path in $velore_block_template_file) wrapped in the site header/footer.
 */
function velore_i18n_render_block_template( string $file ): void {
	get_header();
	echo do_blocks( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme file
	get_footer();
}

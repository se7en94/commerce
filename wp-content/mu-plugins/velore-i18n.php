<?php
/**
 * Plugin Name: VELORÉ Core — i18n Template Switching
 * Description: Serves language-specific theme templates under Polylang. For non-default languages, swaps the front-page block template content to front-page-{lang}.html and re-renders the core/navigation block from a per-language wp_navigation post; template parts fall back to the original files when no {lang} variant exists. English (default language) is never touched.
 * Version:     1.1.0
 * Text Domain: velore-core
 *
 * mu-plugin: always on by design.
 *
 * How it works:
 * - front page   → get_block_template filter replaces the template CONTENT for
 *                  'front-page' with front-page-{lang}.html; WordPress renders
 *                  it through the normal block-template pipeline (document
 *                  shell + template parts handled by core — no custom
 *                  renderer, no duplicated header/footer).
 * - navigation   → pre_render_block re-renders core/navigation with the
 *                  per-language wp_navigation id (option `velore_nav_ids`).
 * - template part→ pre_render_block swaps slug `footer` to footer-{lang}.html.
 *                  (header-{lang}.html not used; the header is language-neutral
 *                  apart from the nav, which is swapped above.)
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

/* ---------------------------------------------------------------- front page -- */

add_filter( 'template_include', function ( $template ) {
	global $_wp_current_template_content;

	// Only for requests resolved to a block template (WP renders those through
	// wp-includes/template-canvas.php, which prints the global below).
	if ( ABSPATH . WPINC . '/template-canvas.php' !== $template || ! is_front_page() ) {
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

	// Swap the resolved block-template content; the canvas keeps handling the
	// document shell and template parts exactly like the default language.
	$_wp_current_template_content = (string) file_get_contents( $file );
	return $template;
}, 20 );

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

	// Only swap navs that reference a wp_navigation post (the header nav).
	if ( ! $ref || empty( $attrs['ref'] ) || (int) $attrs['ref'] === $ref ) {
		return $pre;
	}

	static $in_swap = false;
	if ( $in_swap ) {
		return $pre; // guard against recursion through render_block()
	}
	$in_swap               = true;
	$swapped               = $parsed_block;
	$swapped['attrs']['ref'] = $ref;
	$html                  = render_block( $swapped );
	$in_swap               = false;

	return $html;
}, 10, 2 );

/* ------------------------------------------------------- footer template part -- */

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

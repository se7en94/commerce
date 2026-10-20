<?php

if (!defined('ABSPATH')) {
    exit;
}

function tt5_luxury_setup() {
    load_theme_textdomain('tt5-luxury', get_stylesheet_directory() . '/languages');

    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');

    remove_action('wp_head', '_wp_render_fonts', 1);
    add_action('wp_enqueue_scripts', 'tt5_luxury_enqueue_fonts');
    add_action('admin_enqueue_scripts', 'tt5_luxury_enqueue_fonts');

    // Block themes don't auto-load style.css — enqueue it manually
    add_action('wp_enqueue_scripts', function () {
        wp_enqueue_style('tt5-luxury-style', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
    });
}
add_action('after_setup_theme', 'tt5_luxury_setup');

function tt5_luxury_enqueue_fonts() {
    wp_enqueue_style(
        'tt5-luxury-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap',
        [],
        null
    );
}

add_filter('use_block_editor_for_post_type', function ($use_block, $post_type) {
    if ('product' === $post_type) {
        return true;
    }
    return $use_block;
}, 10, 2);

add_filter('block_categories_all', function ($categories) {
    return array_merge($categories, [
        [
            'slug' => 'tt5-luxury',
            'title' => 'TT5 Luxury',
            'icon' => 'star-filled',
        ],
    ]);
}, 10, 1);

<?php
/**
 * Plugin Name: VELORÉ Admin Tweaks
 * Description: 后台界面微调（编辑器侧栏等）。SEO 自动模板（标题/描述/OG/sitemap）不受影响，仅隐藏面板。
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * 隐藏商品/页面编辑器里的 Yoast SEO 侧栏。
 * 商品 SEO 标题与元描述由全局模板自动生成，无需逐商品填写；
 * 如需临时查看某内容的 Yoast 面板，注释掉此行并重启容器即可。
 */
add_filter( 'wpseo_metabox', '__return_false' );

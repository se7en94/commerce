<?php
/**
 * Plugin Name: VELORÉ Email Dependencies (Emogrifier)
 * Description: 捆绑 pelago/emogrifier v7.3.0 + symfony/css-selector v6.4.0（PSR-4 spl 自动加载）。WooCommerce 10.x 经典邮件路径的 style_inline() 依赖 CssInliner 将 <style> 规则内联进邮件元素；缺库时邮件会裸样式发出（Emogrifier 自 WC 9.9 起不再随 WC 捆绑）。
 * Version:     1.0.0
 * Author:      VELORÉ Dev
 */

defined('ABSPATH') || exit;

spl_autoload_register( function ( $class ) {
	$maps = array(
		'Pelago\\Emogrifier\\'               => 'vendor-libs/emogrifier-7.3.0/src/',
		'Symfony\\Component\\CssSelector\\'  => 'vendor-libs/css-selector-6.4.0/',
		'Sabberworm\\CSS\\'                  => 'vendor-libs/PHP-CSS-Parser-8.7.0/src/',
	);
	foreach ( $maps as $prefix => $dir ) {
		if ( 0 === strpos( $class, $prefix ) ) {
			$rel  = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file = __DIR__ . '/' . $dir . $rel . '.php';
			if ( is_file( $file ) ) {
				require $file;
			}
			return;
		}
	}
} );

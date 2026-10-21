<?php
/**
 * Plugin Name: VELORÉ Currency Bridge
 * Description: CURCY 集成层：Polylang 语言→货币自动映射 + Noir 风格货币切换短代码。购物车/结账始终回到基准货币（CURCY pmcs_cart/checkout_default_currency=yes 保证 PayPal 安全）。
 * Version:     1.0.0
 * Author:      VELORÉ Dev
 */

defined('ABSPATH') || exit;

/**
 * 语言→货币映射。切换语言后自动跟随（用户手动选过货币则尊重手动选择）。
 * SGD/CAD 无语言绑定，仅通过切换器手动选择。
 */
function velore_cur_map() {
	return array(
		'en' => 'USD',
		'zh' => 'USD',
		'es' => 'EUR',
		'de' => 'EUR',
		'fr' => 'EUR',
		'pt' => 'BRL',
	);
}

/**
 * CURCY 合法货币列表。
 */
function velore_cur_valid() {
	$rows = get_option( 'pmcs_currencies', array() );
	$codes = array();
	foreach ( (array) $rows as $row ) {
		if ( ! empty( $row['currency_code'] ) ) {
			$codes[] = $row['currency_code'];
		}
	}
	return $codes;
}

/**
 * 同步货币到当前语言。挂 init 20 —— CURCY 在 wp 钩子才解析货币，同请求即可生效。
 * 规则：
 *  - ?currency=XXX 出现（用户手动切换）→ 写 30 天手动覆盖 cookie
 *  - 无手动覆盖 → cookie 跟随语言映射
 */
add_action( 'init', function () {
	// GeoIP 自动检测与语言映射冲突，关闭（确定性：语言是唯一驱动）
	if ( 'no' !== get_option( 'pmcs_currency_by_ip' ) ) {
		update_option( 'pmcs_currency_by_ip', 'no' );
	}

	if ( isset( $_GET['currency'] ) ) {
		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) );
		if ( in_array( $code, velore_cur_valid(), true ) ) {
			setcookie( 'velore_currency_manual', $code, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/' );
		}
		return; // CURCY 自行处理 GET 参数与 cookie
	}

	if ( isset( $_COOKIE['velore_currency_manual'] ) ) {
		return; // 尊重手动选择
	}

	$lang     = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : 'en';
	$map      = velore_cur_map();
	$expected = isset( $map[ $lang ] ) ? $map[ $lang ] : 'USD';

	$current = isset( $_COOKIE['currency'] ) ? strtoupper( (string) $_COOKIE['currency'] ) : '';
	if ( $current !== $expected ) {
		setcookie( 'currency', $expected, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/' );
		$_COOKIE['currency'] = $expected; // CURCY 在 wp 钩子读取，本请求立即生效
	}
}, 20 );

/**
 * 当前显示货币（切换器选中态）。优先 CURCY 运行时值，回退 cookie / 基准。
 */
function velore_cur_current() {
	if ( function_exists( 'pmcs' ) && isset( pmcs()->switcher->current_currency ) && pmcs()->switcher->current_currency ) {
		return pmcs()->switcher->current_currency;
	}
	if ( isset( $_COOKIE['currency'] ) && in_array( strtoupper( (string) $_COOKIE['currency'] ), velore_cur_valid(), true ) ) {
		return strtoupper( (string) $_COOKIE['currency'] );
	}
	return (string) get_option( 'pmcs_default_currency', 'USD' );
}

/**
 * [velore_currency_switcher] —— header 货币下拉，样式复用语言切换器 Noir 风格。
 * onchange 跳转 ?currency=CODE：CURCY 写货币 cookie，本插件写手动覆盖标记。
 */
add_shortcode( 'velore_currency_switcher', function () {
	$codes  = velore_cur_valid();
	if ( empty( $codes ) ) {
		return '';
	}
	$current = velore_cur_current();

	$out = '<div class="luxury-currency-switcher">';
	$out .= '<select aria-label="' . esc_attr__( 'Currency', 'velore-core' ) . '" onchange="var u=new URL(location.href);u.searchParams.set(\'currency\',this.value);location.href=u.toString();">';
	foreach ( $codes as $code ) {
		$out .= '<option value="' . esc_attr( $code ) . '"' . selected( $code, $current, false ) . '>' . esc_html( $code ) . '</option>';
	}
	$out .= '</select></div>';
	return $out;
} );

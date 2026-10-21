<?php
/**
 * Plugin Name: VELORÉ Email Branding
 * Description: WooCommerce 邮件 Noir 品牌化：黑色 header 排版字标 + 金色点缀样式 + 品牌页脚。颜色主设置存于 WooCommerce 邮件选项（DB），此插件仅追加 hook 层样式。
 * Version:     1.0.0
 * Author:      VELORÉ Dev
 */

defined('ABSPATH') || exit;

/**
 * 邮件 header：黑底金色字标（table 布局保证 Outlook 兼容）。
 * 追加在 WC 自带 header（base_color 黑色条）之后。
 */
add_action( 'woocommerce_email_header', function ( $email ) {
	?>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#111111;border-collapse:collapse;">
		<tr>
			<td align="center" style="padding:28px 20px 10px 20px;font-family:Georgia,'Times New Roman',serif;font-size:30px;letter-spacing:0.32em;color:#C9A96A;font-weight:normal;">
				VELOR&#201;
			</td>
		</tr>
		<tr>
			<td align="center" style="padding:0 60px 24px 60px;">
				<div style="border-top:1px solid #C9A96A;width:60px;margin:0 auto;">&nbsp;</div>
			</td>
		</tr>
	</table>
	<?php
}, 15, 1 );

/**
 * 邮件正文附加样式：金色标题/链接/价格点缀（inline CSS 由 WC style_inline 处理）。
 */
add_filter( 'woocommerce_email_styles', function ( $css ) {
	$css .= '
	#template_header h1, #template_header h2 { color:#C9A96A; font-family: Georgia, "Times New Roman", serif; }
	h2 { color:#B08D4C; font-family: Georgia, "Times New Roman", serif; }
	a { color:#B08D4C; }
	.order-items-table th { color:#111111; }
	.order-items-table .product-name a { color:#B08D4C; text-decoration:none; }
	.tfoot .product-name { color:#111111; }
	';
	return $css;
} );

/**
 * 页脚品牌句（WC 默认 footer_text 含 {site_title} 已够用；此处置换更精致的句式）。
 */
add_filter( 'woocommerce_email_footer_text', function ( $text ) {
	$address = WC()->countries ? WC()->countries->get_base_address() : '';
	$city    = WC()->countries ? WC()->countries->get_base_city() : '';
	return 'VELOR&#201; &mdash; Curated Luxury<br />' . $address . ( $city ? ', ' . $city : '' ) . '<br /><span style="font-size:11px;color:#8a8a8a;">' . get_bloginfo( 'name' ) . '</span>';
} );

/**
 * 第三方邮件块品牌化：AST（Advanced Shipment Tracking）追踪块自带蓝色内联样式，
 * 在 style_inline 之后（woocommerce_mail_content）整体替换为 Noir 金色调。
 * - 按钮黑底金字（含 Outlook 的 bgcolor 属性）
 * - 追踪号链接改金色
 */
add_filter( 'woocommerce_mail_content', function ( $content ) {
	if ( false !== strpos( $content, '005b9a' ) || false !== strpos( $content, '03a9f4' ) ) {
		$content = str_replace(
			array(
				'background: #005b9a',
				'bgcolor="#005b9a"',
				'color: #03a9f4',
			),
			array(
				'background: #111111',
				'bgcolor="#111111"',
				'color: #b08d4c',
			),
			$content
		);
		// 按钮文字白 → 金（仅针对 track-button 的内联串，避免误伤其他白字）
		$content = str_replace(
			'white-space: nowrap; color: #fff; font-size: 14px;',
			'white-space: nowrap; color: #c9a96a; font-size: 14px;',
			$content
		);
	}
	return $content;
} );

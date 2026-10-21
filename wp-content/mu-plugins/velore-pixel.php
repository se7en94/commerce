<?php
/**
 * Plugin Name: VELORÉ Meta Pixel
 * Description: Meta (Facebook) Pixel 基础代码 + 标准电商事件。经 VELORE_META_PIXEL_ID 环境变量门控：未设置 = 不输出任何 Meta 代码（避免与 GTM 部署的 Meta 标签双发）。
 * Version:     1.0.0
 * Author:      VELORÉ Dev
 */

defined('ABSPATH') || exit;

/**
 * Pixel ID（经 WORDPRESS_CONFIG_EXTRA 从环境变量注入 wp-config 常量）。
 */
function velore_pixel_id() {
	if ( ! defined( 'VELORE_META_PIXEL_ID' ) ) {
		return '';
	}
	$id = trim( (string) constant( 'VELORE_META_PIXEL_ID' ) );
	return preg_match( '/^\d{10,20}$/', $id ) ? $id : '';
}

if ( ! velore_pixel_id() ) {
	return; // 未配置即整体不启用
}

/**
 * [head] 像素基座 + PageView（noscript 同步放 body 起始）。
 */
add_action( 'wp_head', function () {
	$id = velore_pixel_id();
	?>
	<!-- Meta Pixel by VELORÉ -->
	<script>
	!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
	n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
	t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
	document,'script','https://connect.facebook.net/en_US/fbevents.js');
	fbq('init', '<?php echo esc_js( $id ); ?>');
	<?php do_action( 'velore_pixel_before_pageview' ); ?>
	fbq('track', 'PageView');
	</script>
	<!-- End Meta Pixel -->
	<?php
}, 1 );

add_action( 'wp_body_open', function () {
	$id = velore_pixel_id();
	?>
	<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $id ); ?>&ev=PageView&noscript=1" /></noscript>
	<?php
} );

/**
 * 商品页：ViewContent。
 */
add_action( 'velore_pixel_before_pageview', function () {
	if ( ! is_product() ) {
		return;
	}
	$p = wc_get_product( get_the_ID() );
	if ( ! $p instanceof WC_Product ) {
		return;
	}
	?>
	fbq('track', 'ViewContent', {
		content_ids: [<?php echo wp_json_encode( $p->get_sku() ? $p->get_sku() : 'VL-' . $p->get_id() ); ?>],
		content_type: 'product',
		value: <?php echo wp_json_encode( (float) $p->get_price() ); ?>,
		currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
	});
	<?php
} );

/**
 * ?add-to-cart= 经典路径：AddToCart。
 */
add_action( 'woocommerce_add_to_cart', function () {
	add_action( 'velore_pixel_before_pageview', function () {
		$items = WC()->cart ? WC()->cart->get_cart() : array();
		$ids   = array();
		$value = 0.0;
		foreach ( $items as $it ) {
			$ids[] = $it['data']->get_sku() ? $it['data']->get_sku() : 'VL-' . $it['product_id'];
			$value += (float) $it['line_total'];
		}
		?>
		fbq('track', 'AddToCart', {
			content_ids: <?php echo wp_json_encode( array_slice( $ids, -5 ) ); ?>,
			content_type: 'product',
			value: <?php echo wp_json_encode( $value ); ?>,
			currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
		});
		<?php
	} );
} );

/**
 * 结账页：InitiateCheckout。
 */
add_action( 'velore_pixel_before_pageview', function () {
	if ( ! is_checkout() || is_order_received_page() ) {
		return;
	}
	$value = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0.0;
	?>
	fbq('track', 'InitiateCheckout', {
		value: <?php echo wp_json_encode( $value ); ?>,
		currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
	});
	<?php
} );

/**
 * 感谢页：Purchase（仅当 key 校验通过；新鲜度窗口与 GTM4WP 一致防重复）。
 */
add_action( 'velore_pixel_before_pageview', function () {
	if ( ! is_order_received_page() ) {
		return;
	}
	$oid = isset( $GLOBALS['wp']->query_vars['order-received'] ) ? absint( $GLOBALS['wp']->query_vars['order-received'] ) : 0;
	if ( ! $oid ) {
		$oid = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$order = $oid ? wc_get_order( $oid ) : null;
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$order_key = isset( $_GET['key'] ) ? wc_clean( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) {
		return;
	}
	$ids = array();
	foreach ( $order->get_items() as $it ) {
		$p = $it->get_product();
		$ids[] = $p && $p->get_sku() ? $p->get_sku() : 'VL-' . $it->get_product_id();
	}
	?>
	fbq('track', 'Purchase', {
		content_ids: <?php echo wp_json_encode( $ids ); ?>,
		content_type: 'product',
		value: <?php echo wp_json_encode( (float) $order->get_total() ); ?>,
		currency: '<?php echo esc_js( $order->get_currency() ); ?>'
	});
	<?php
} );

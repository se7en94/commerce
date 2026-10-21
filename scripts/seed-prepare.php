<?php
/**
 * Seed preparation — run before import-products.php:
 *   docker exec woocommerce-wordpress-1 php /var/www/html/wp-content/plugins/woocommerce/scripts/seed-prepare.php
 *
 * 1) Registers the global "Brand" product attribute (pa_brand) so the CSV
 *    import can attach filterable brand terms.
 * 2) Deletes every product whose SKU does not start with VL- (WooCommerce
 *    sample data and any leftovers), along with its attachments.
 */
if ( ! defined( 'ABSPATH' ) ) {
	require '/var/www/html/wp-load.php';
}

// --- 1) Global Brand attribute ---
$has_brand = false;
foreach ( wc_get_attribute_taxonomies() as $attr ) {
	if ( 'brand' === $attr->attribute_name ) {
		$has_brand = true;
		break;
	}
}

if ( ! $has_brand ) {
	$id = wc_create_attribute( array(
		'name'         => 'Brand',
		'slug'         => 'brand',
		'type'         => 'select',
		'order_by'     => 'menu_order',
		'has_archives' => true,
	) );
	if ( is_wp_error( $id ) || ! $id ) {
		echo "ERROR: could not register Brand attribute\n";
		exit( 1 );
	}
	delete_transient( 'wc_attribute_taxonomies' );
	echo "Registered global attribute: Brand (pa_brand)\n";
} else {
	echo "Brand attribute already registered\n";
}

// --- 2) Remove non-VELORÉ sample products ---
$products = wc_get_products( array( 'limit' => -1, 'status' => 'any' ) );
$deleted  = 0;
foreach ( $products as $product ) {
	$sku = $product->get_sku();
	if ( 0 === strpos( $sku, 'VL-' ) ) {
		continue;
	}

	$image_ids = array_filter( array_merge(
		array( $product->get_image_id() ),
		$product->get_gallery_image_ids()
	) );

	$product->delete( true );
	$deleted++;

	foreach ( $image_ids as $image_id ) {
		wp_delete_attachment( $image_id, true );
	}
}

$remaining = wp_count_posts( 'product' );
echo "Deleted {$deleted} sample products\n";
echo "Remaining products: " . array_sum( (array) $remaining ) . "\n";

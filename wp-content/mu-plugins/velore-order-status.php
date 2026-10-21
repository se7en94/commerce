<?php
/**
 * Plugin Name: VELORÉ Core — Order Status
 * Description: Registers the "Pending supplier payment" (wc-pending-supplier) order status, inserted after Processing. Part of the consignment flow: customer paid → store pays supplier → supplier ships.
 * Version:     1.0.0
 * Text Domain: velore-core
 *
 * mu-plugin: always on by design — this status is core business logic and must
 * not be deactivatable from the admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELORE_SUPPLIER_STATUS   = 'wc-pending-supplier';
const VELORE_SUPPLIER_LABEL    = 'Pending supplier payment';
const VELORE_SUPPLIER_SLUG_RAW = 'pending-supplier'; // same slug without the wc- prefix

/**
 * Register the underlying post status (WP admin counts / status lists).
 */
add_action( 'init', function () {
	register_post_status( VELORE_SUPPLIER_STATUS, array(
		'label'                     => VELORE_SUPPLIER_LABEL,
		'public'                    => false,
		'internal'                  => false,
		'exclude_from_search'       => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: number of orders. */
		'label_count'               => _n_noop(
			'Pending supplier payment <span class="count">(%s)</span>',
			'Pending supplier payment <span class="count">(%s)</span>',
			'velore-core'
		),
	) );
} );

/**
 * Add the status to WooCommerce's status list, right after Processing.
 * This drives the status dropdown on the order edit screen, quick edit,
 * the list-table status filter and the order status name everywhere.
 */
add_filter( 'wc_order_statuses', function ( array $statuses ): array {
	if ( isset( $statuses[ VELORE_SUPPLIER_STATUS ] ) ) {
		return $statuses;
	}

	$ordered = array();
	foreach ( $statuses as $key => $label ) {
		$ordered[ $key ] = $label;
		if ( 'wc-processing' === $key ) {
			$ordered[ VELORE_SUPPLIER_STATUS ] = VELORE_SUPPLIER_LABEL;
		}
	}

	// Fallback if processing ever disappears from the list.
	if ( ! isset( $ordered[ VELORE_SUPPLIER_STATUS ] ) ) {
		$ordered[ VELORE_SUPPLIER_STATUS ] = VELORE_SUPPLIER_LABEL;
	}

	return $ordered;
} );

/**
 * The customer has already paid before an order reaches this status —
 * it must count as a paid status (affects is_paid(), analytics revenue, etc.).
 * Note: this filter uses slugs WITHOUT the wc- prefix.
 */
add_filter( 'woocommerce_order_is_paid_statuses', function ( array $statuses ): array {
	$statuses[] = VELORE_SUPPLIER_SLUG_RAW;
	return $statuses;
} );

/**
 * Include the status in legacy report revenue (customer already paid).
 */
add_filter( 'woocommerce_reports_order_statuses', function ( array $statuses ): array {
	if ( ! in_array( VELORE_SUPPLIER_SLUG_RAW, $statuses, true ) ) {
		$statuses[] = VELORE_SUPPLIER_SLUG_RAW;
	}
	return $statuses;
} );

/**
 * Bulk action "Change status to ..." in the orders list.
 * WooCommerce handles any mark_{slug} action automatically once the status is
 * registered in wc_get_order_statuses(), so only the label is needed here.
 * Two different screen ids: HPOS list table vs legacy posts list table.
 */
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'velore_supplier_bulk_action_label' );
add_filter( 'bulk_actions-edit-shop_order', 'velore_supplier_bulk_action_label' );
function velore_supplier_bulk_action_label( array $actions ): array {
	$actions[ 'mark_' . VELORE_SUPPLIER_SLUG_RAW ] = 'Change status to pending supplier payment';
	return $actions;
}

/**
 * Amber/gold badge in the orders list, visually distinct from the green
 * "Processing" badge while keeping the WooCommerce badge look.
 */
add_action( 'admin_head', function () {
	echo '<style>
		.order-status.status-pending-supplier {
			background: #f3e3b3;
			color: #7d5e0e;
		}
	</style>';
} );

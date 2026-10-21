<?php
/**
 * Plugin Name: VELORÉ Core — Invite Codes
 * Description: Optional invite code at registration. Adds an "Invite code" field to the My Account registration form; a submitted code is validated on the backend and atomically consumed, a blank code registers without one. Admin page (WooCommerce → Invite Codes) to generate / revoke / inspect codes and usage records.
 * Version:     1.1.0
 * Text Domain: velore-core
 *
 * mu-plugin: always on by design — registration gating is core business logic.
 *
 * Security model (hard constraint: backend atomic validation, no front-end-only checks):
 * - Validation runs in `woocommerce_register_post`, which fires inside
 *   wc_create_new_customer() and therefore covers every customer-creation path
 *   (My Account form, classic checkout signup, Store API checkout, wc/v3 REST).
 *   An empty code is allowed through (codes are optional); a submitted code must
 *   be valid or account creation is aborted.
 * - Consumption runs as a single conditional UPDATE (atomic claim) — two
 *   concurrent registrations can never exceed a code's max_uses.
 * - Admin / privileged-API contexts (current_user_can('manage_woocommerce'))
 *   are exempt, so shop managers can still create accounts manually.
 *
 * Known limitation: if checkout account creation is ever enabled, the block
 * checkout has no invite-code field, so checkout signup stays blocked until
 * an additional-checkout-field for the code is wired (see T-3.2 notes).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELORE_IC_DB_VERSION   = '1.0';
const VELORE_IC_ALPHABET     = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I — unambiguous when read aloud
const VELORE_IC_ERROR        = 'This invite code is invalid or no longer valid.';

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

function velore_ic_table_codes(): string {
	global $wpdb;
	return $wpdb->prefix . 'velore_invite_codes';
}

function velore_ic_table_uses(): string {
	global $wpdb;
	return $wpdb->prefix . 'velore_invite_code_uses';
}

function velore_ic_normalize( string $raw ): string {
	return strtoupper( preg_replace( '/\s+/', '', trim( $raw ) ) );
}

function velore_ic_random_block(): string {
	$max = strlen( VELORE_IC_ALPHABET ) - 1;
	$out = '';
	for ( $i = 0; $i < 4; $i++ ) {
		$out .= VELORE_IC_ALPHABET[ random_int( 0, $max ) ];
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Install (dbDelta)
 * ---------------------------------------------------------------------- */

function velore_ic_install(): void {
	if ( get_option( 'velore_ic_db_version' ) === VELORE_IC_DB_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$codes   = velore_ic_table_codes();
	$uses    = velore_ic_table_uses();

	dbDelta( "CREATE TABLE {$codes} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		code VARCHAR(32) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		max_uses INT UNSIGNED NOT NULL DEFAULT 1,
		used_count INT UNSIGNED NOT NULL DEFAULT 0,
		note VARCHAR(191) NULL DEFAULT NULL,
		created_by BIGINT UNSIGNED NULL DEFAULT NULL,
		created_at DATETIME NOT NULL,
		expires_at DATETIME NULL DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY code (code),
		KEY status (status)
	) {$charset};" );

	dbDelta( "CREATE TABLE {$uses} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		code_id BIGINT UNSIGNED NOT NULL,
		code VARCHAR(32) NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL,
		user_email VARCHAR(191) NOT NULL,
		used_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY code_id (code_id),
		KEY user_id (user_id)
	) {$charset};" );

	update_option( 'velore_ic_db_version', VELORE_IC_DB_VERSION );
}
add_action( 'init', 'velore_ic_install' );

/* -------------------------------------------------------------------------
 * Core API
 * ---------------------------------------------------------------------- */

/**
 * Generate $count codes with the given policy. Returns the plain codes.
 */
function velore_ic_generate( int $count, int $max_uses, int $expires_in_days, string $note, int $created_by ): array {
	global $wpdb;

	$expires_at = $expires_in_days > 0
		? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $expires_in_days * DAY_IN_SECONDS )
		: null;

	$generated = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$attempts = 0;
		do {
			// 8 chars from a 31-char alphabet (31^8 ≈ 8.5e11) — short to type, brute-force infeasible.
			$code = velore_ic_random_block() . '-' . velore_ic_random_block();
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . velore_ic_table_codes() . ' WHERE code = %s', $code ) );
			$attempts++;
		} while ( $exists && $attempts < 5 );

		if ( $exists ) {
			continue; // 31^8 space — collision after 5 retries is effectively impossible; skip rather than fail the batch
		}

		$ok = $wpdb->insert(
			velore_ic_table_codes(),
			array(
				'code'       => $code,
				'status'     => 'active',
				'max_uses'   => $max_uses,
				'used_count' => 0,
				'note'       => $note,
				'created_by' => $created_by,
				'created_at' => current_time( 'mysql' ),
				'expires_at' => $expires_at,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( $ok ) {
			$generated[] = $code;
		}
	}

	return $generated;
}

/**
 * Read-only check used during registration validation (no mutation).
 */
function velore_ic_is_claimable( string $code ): bool {
	global $wpdb;
	$found = $wpdb->get_var( $wpdb->prepare(
		'SELECT id FROM ' . velore_ic_table_codes() .
		" WHERE code = %s AND status = 'active' AND used_count < max_uses
		  AND ( expires_at IS NULL OR expires_at > %s )",
		$code,
		current_time( 'mysql' )
	) );

	return (bool) $found;
}

/**
 * Atomically consume one use of a code.
 *
 * The conditional UPDATE is the whole critical section: MySQL guarantees the
 * row is locked for the statement, so concurrent claims can never push
 * used_count past max_uses. Returns false when the code is unknown, revoked,
 * exhausted or expired — or lost a race for the last slot.
 */
function velore_ic_claim( string $code, int $user_id, string $email ): bool {
	global $wpdb;

	$code    = velore_ic_normalize( $code );
	$claimed = $wpdb->query( $wpdb->prepare(
		'UPDATE ' . velore_ic_table_codes() .
		' SET used_count = used_count + 1' .
		" WHERE code = %s AND status = 'active' AND used_count < max_uses
		  AND ( expires_at IS NULL OR expires_at > %s )",
		$code,
		current_time( 'mysql' )
	) );

	if ( 1 !== (int) $claimed ) {
		return false;
	}

	$wpdb->insert(
		velore_ic_table_uses(),
		array(
			'code_id'    => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . velore_ic_table_codes() . ' WHERE code = %s', $code ) ),
			'code'       => $code,
			'user_id'    => $user_id,
			'user_email' => $email,
			'used_at'    => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%d', '%s', '%s' )
	);

	return true;
}

/* -------------------------------------------------------------------------
 * Front-end: registration field + backend enforcement
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_register_form', function () {
	$value = isset( $_POST['velore_invite_code'] ) ? sanitize_text_field( wp_unslash( $_POST['velore_invite_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- mirrors core form fields, nonce verified by WC
	?>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="velore_invite_code"><?php esc_html_e( 'Invite code', 'velore-core' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'velore-core' ); ?>)</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="velore_invite_code" id="velore_invite_code" value="<?php echo esc_attr( $value ); ?>" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX" />
	</p>
	<?php
} );

/**
 * Pre-creation validation. Fires inside wc_create_new_customer() for every
 * registration path; a WP_Error here aborts account creation.
 */
add_action( 'woocommerce_register_post', function ( $username, $email, WP_Error $errors ) {
	// Shop managers / privileged API integrations may create accounts directly.
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$code = isset( $_POST['velore_invite_code'] ) ? velore_ic_normalize( wp_unslash( $_POST['velore_invite_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WC form handler

	// Codes are optional: blank means "registering without an invite".
	if ( '' === $code ) {
		return;
	}

	// A submitted code must be claimable — one generic message, never reveal
	// whether it exists, was revoked, is expired or used up.
	if ( ! velore_ic_is_claimable( $code ) ) {
		$errors->add( 'velore_invite_code_invalid', esc_html__( VELORE_IC_ERROR, 'velore-core' ) );
	}
}, 10, 3 );

/**
 * Atomic consumption right after the account row exists — only for registrations
 * that actually submitted a code. If the claim loses a race for the last slot
 * (validation and claim are separate instants), roll the just-created account
 * back and surface the same generic error.
 */
add_action( 'woocommerce_created_customer', function ( $customer_id, $new_customer_data ) {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$code = isset( $_POST['velore_invite_code'] ) ? velore_ic_normalize( wp_unslash( $_POST['velore_invite_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( '' === $code ) {
		return;
	}

	$email = isset( $new_customer_data['user_email'] ) ? (string) $new_customer_data['user_email'] : '';

	if ( velore_ic_claim( $code, (int) $customer_id, $email ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $customer_id );

	throw new Exception( esc_html__( VELORE_IC_ERROR, 'velore-core' ) ); // caught by WC form handler → error notice
}, 10, 2 );

/* -------------------------------------------------------------------------
 * Admin: WooCommerce → Invite Codes
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Invite Codes', 'velore-core' ),
		__( 'Invite Codes', 'velore-core' ),
		'manage_woocommerce',
		'velore-invite-codes',
		'velore_ic_render_admin_page'
	);
} );

function velore_ic_admin_link( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => 'velore-invite-codes' ), $args ), admin_url( 'admin.php' ) );
}

function velore_ic_render_admin_page(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage invite codes.', 'velore-core' ) );
	}

	global $wpdb;
	$codes_table = velore_ic_table_codes();
	$uses_table  = velore_ic_table_uses();

	// Notices (set by admin_post handlers via query args).
	$msg = isset( $_GET['velore_msg'] ) ? sanitize_key( wp_unslash( $_GET['velore_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'generated' === $msg ) {
		$new_codes = get_transient( 'velore_ic_last_generated' );
		echo '<div class="notice notice-success is-dismissible"><p>';
		if ( is_array( $new_codes ) && $new_codes ) {
			echo '<strong>' . esc_html__( 'Codes generated — copy them now, they are shown once:', 'velore-core' ) . '</strong><br />';
			foreach ( $new_codes as $c ) {
				echo '<code style="font-size:14px">' . esc_html( $c ) . '</code><br />';
			}
		} else {
			esc_html_e( 'No codes were generated.', 'velore-core' );
		}
		echo '</p></div>';
	} elseif ( 'revoked' === $msg || 'activated' === $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Code status updated.', 'velore-core' ) . '</p></div>';
	} elseif ( 'deleted' === $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Code deleted.', 'velore-core' ) . '</p></div>';
	} elseif ( 'error' === $msg ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Action failed.', 'velore-core' ) . '</p></div>';
	}

	$per_page  = 20;
	$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$codes_table}" );
	$pages     = max( 1, (int) ceil( $total / $per_page ) );
	$codes     = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$codes_table} ORDER BY id DESC LIMIT %d OFFSET %d",
		$per_page,
		( $paged - 1 ) * $per_page
	) );

	$filter_code = isset( $_GET['velore_uses_code'] ) ? velore_ic_normalize( wp_unslash( $_GET['velore_uses_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $filter_code ) {
		$uses_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$uses_table} WHERE code = %s ORDER BY id DESC LIMIT 100",
			$filter_code
		) );
	} else {
		$uses_rows = $wpdb->get_results( "SELECT * FROM {$uses_table} ORDER BY id DESC LIMIT 100" );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Invite Codes', 'velore-core' ); ?></h1>

		<h2><?php esc_html_e( 'Generate new codes', 'velore-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
			<input type="hidden" name="action" value="velore_ic_generate" />
			<?php wp_nonce_field( 'velore_ic_generate' ); ?>
			<p style="margin:0">
				<label><strong><?php esc_html_e( 'How many', 'velore-core' ); ?></strong><br />
				<input type="number" name="count" min="1" max="50" value="1" class="small-text" required /></label>
			</p>
			<p style="margin:0">
				<label><strong><?php esc_html_e( 'Uses per code', 'velore-core' ); ?></strong><br />
				<input type="number" name="max_uses" min="1" max="9999" value="1" class="small-text" required /></label>
			</p>
			<p style="margin:0">
				<label><strong><?php esc_html_e( 'Expires in days (0 = never)', 'velore-core' ); ?></strong><br />
				<input type="number" name="expires_days" min="0" max="3650" value="0" class="small-text" /></label>
			</p>
			<p style="margin:0">
				<label><strong><?php esc_html_e( 'Note (internal memo)', 'velore-core' ); ?></strong><br />
				<input type="text" name="note" maxlength="191" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. for influencer X, VIP customer…', 'velore-core' ); ?>" /></label>
			</p>
			<p style="margin:0">
				<?php submit_button( __( 'Generate', 'velore-core' ), 'primary', 'submit', false ); ?>
			</p>
		</form>

		<h2 style="margin-top:32px"><?php esc_html_e( 'All codes', 'velore-core' ); ?> <span class="count">(<?php echo esc_html( $total ); ?>)</span></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Code', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Status', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Uses', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Note', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Created', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'velore-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $codes ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No codes yet — generate the first batch above.', 'velore-core' ); ?></td></tr>
			<?php else : foreach ( $codes as $row ) :
				$expired   = $row->expires_at && $row->expires_at <= current_time( 'mysql' );
				$exhausted = (int) $row->used_count >= (int) $row->max_uses;
				?>
				<tr>
					<td><code style="font-size:13px"><?php echo esc_html( $row->code ); ?></code></td>
					<td>
						<?php if ( 'active' === $row->status && ! $expired && ! $exhausted ) : ?>
							<span style="color:#00a32a;font-weight:600"><?php esc_html_e( 'Active', 'velore-core' ); ?></span>
						<?php elseif ( 'revoked' === $row->status ) : ?>
							<span style="color:#d63638;font-weight:600"><?php esc_html_e( 'Revoked', 'velore-core' ); ?></span>
						<?php elseif ( $expired ) : ?>
							<span style="color:#dba617;font-weight:600"><?php esc_html_e( 'Expired', 'velore-core' ); ?></span>
						<?php else : ?>
							<span style="color:#dba617;font-weight:600"><?php esc_html_e( 'Used up', 'velore-core' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row->used_count . ' / ' . $row->max_uses ); ?></td>
					<td><?php echo esc_html( $row->expires_at ? $row->expires_at : '—' ); ?></td>
					<td><?php echo esc_html( $row->note ? $row->note : '—' ); ?></td>
					<td><?php echo esc_html( $row->created_at ); ?></td>
					<td>
						<?php if ( 'active' === $row->status ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=velore_ic_toggle&id=' . (int) $row->id . '&to=revoked' ), 'velore_ic_toggle_' . (int) $row->id ) ); ?>"><?php esc_html_e( 'Revoke', 'velore-core' ); ?></a> |
						<?php else : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=velore_ic_toggle&id=' . (int) $row->id . '&to=active' ), 'velore_ic_toggle_' . (int) $row->id ) ); ?>"><?php esc_html_e( 'Activate', 'velore-core' ); ?></a> |
						<?php endif; ?>
						<a href="<?php echo esc_url( velore_ic_admin_link( array( 'velore_uses_code' => $row->code ) ) ); ?>"><?php esc_html_e( 'Uses', 'velore-core' ); ?></a> |
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=velore_ic_delete&id=' . (int) $row->id ), 'velore_ic_delete_' . (int) $row->id ) ); ?>"
							onclick="return confirm('<?php echo esc_js( __( 'Delete this code and its usage records?', 'velore-core' ) ); ?>');"
							style="color:#d63638"><?php esc_html_e( 'Delete', 'velore-core' ); ?></a>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core output
					'base'    => add_query_arg( 'paged', '%#%' ),
					'total'   => $pages,
					'current' => $paged,
				) ); ?>
			</div></div>
		<?php endif; ?>

		<h2 style="margin-top:32px"><?php esc_html_e( 'Usage log', 'velore-core' ); ?>
			<?php if ( '' !== $filter_code ) : ?>
				<span class="count">(<?php echo esc_html( $filter_code ); ?>) — <a href="<?php echo esc_url( velore_ic_admin_link() ); ?>"><?php esc_html_e( 'show all', 'velore-core' ); ?></a></span>
			<?php endif; ?>
		</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Code', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'User', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'User ID', 'velore-core' ); ?></th>
					<th><?php esc_html_e( 'Used at', 'velore-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $uses_rows ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No registrations yet.', 'velore-core' ); ?></td></tr>
			<?php else : foreach ( $uses_rows as $u ) : ?>
				<tr>
					<td><code><?php echo esc_html( $u->code ); ?></code></td>
					<td><a href="<?php echo esc_url( get_edit_user_link( (int) $u->user_id ) ); ?>"><?php echo esc_html( $u->user_email ); ?></a></td>
					<td><?php echo esc_html( $u->user_id ); ?></td>
					<td><?php echo esc_html( $u->used_at ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/* ---------------------------- admin_post handlers ----------------------- */

add_action( 'admin_post_velore_ic_generate', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage invite codes.', 'velore-core' ) );
	}
	check_admin_referer( 'velore_ic_generate' );

	$count  = min( 50, max( 1, absint( $_POST['count'] ?? 1 ) ) );
	$uses   = min( 9999, max( 1, absint( $_POST['max_uses'] ?? 1 ) ) );
	$days   = min( 3650, absint( $_POST['expires_days'] ?? 0 ) );
	$note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

	$codes = velore_ic_generate( $count, $uses, $days, $note, get_current_user_id() );
	set_transient( 'velore_ic_last_generated', $codes, HOUR_IN_SECONDS );

	wp_safe_redirect( velore_ic_admin_link( array( 'velore_msg' => $codes ? 'generated' : 'error' ) ) );
	exit;
} );

add_action( 'admin_post_velore_ic_toggle', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage invite codes.', 'velore-core' ) );
	}
	$id = absint( $_GET['id'] ?? 0 );
	check_admin_referer( 'velore_ic_toggle_' . $id );

	$to     = isset( $_GET['to'] ) && 'active' === $_GET['to'] ? 'active' : 'revoked';
	$result = $GLOBALS['wpdb']->update(
		velore_ic_table_codes(),
		array( 'status' => $to ),
		array( 'id' => $id ),
		array( '%s' ),
		array( '%d' )
	);

	wp_safe_redirect( velore_ic_admin_link( array( 'velore_msg' => $result ? ( 'active' === $to ? 'activated' : 'revoked' ) : 'error' ) ) );
	exit;
} );

add_action( 'admin_post_velore_ic_delete', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage invite codes.', 'velore-core' ) );
	}
	$id = absint( $_GET['id'] ?? 0 );
	check_admin_referer( 'velore_ic_delete_' . $id );

	global $wpdb;
	$code = $wpdb->get_var( $wpdb->prepare( 'SELECT code FROM ' . velore_ic_table_codes() . ' WHERE id = %d', $id ) );
	if ( $code ) {
		$wpdb->delete( velore_ic_table_codes(), array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( velore_ic_table_uses(), array( 'code' => $code ), array( '%s' ) );
	}

	wp_safe_redirect( velore_ic_admin_link( array( 'velore_msg' => 'deleted' ) ) );
	exit;
} );

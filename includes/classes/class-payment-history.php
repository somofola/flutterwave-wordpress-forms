<?php
/**
 * Global payment history admin page with search + filtering.
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payment_History {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	public function register_page() {
		add_submenu_page(
			'edit.php?post_type=flutterwave_form',
			esc_html__( 'Payment History', 'pff-flutterwave' ),
			esc_html__( 'Payment History', 'pff-flutterwave' ),
			'edit_posts',
			'payment-history',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		self::load_list_table_class();

		$table = new Payment_History_List_Table();
		$table->prepare_items();

		$forms = get_posts(
			[
				'post_type'      => 'flutterwave_form',
				'posts_per_page' => -1,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$selected_form    = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$selected_status  = isset( $_GET['paid_status'] ) ? sanitize_text_field( wp_unslash( $_GET['paid_status'] ) ) : '';
		$selected_pt      = isset( $_GET['payment_type'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_type'] ) ) : '';
		$from_date        = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
		$to_date          = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';

		global $wpdb;
		$payments_table = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$db_pts = $wpdb->get_col( "SELECT DISTINCT payment_type FROM `{$payments_table}` WHERE payment_type <> '' AND deleted_at IS NULL" );

		// Also pull Payment Option values out of metadata JSON (covers pre-1.2 rows with empty payment_type column).
		$meta_like = '%' . $wpdb->esc_like( '"variable_name":"Payment Option"' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$meta_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT metadata FROM `{$payments_table}` WHERE deleted_at IS NULL AND metadata LIKE %s LIMIT 2000",
				$meta_like
			)
		);
		$meta_pts = [];
		foreach ( $meta_rows as $raw ) {
			$decoded = json_decode( $raw );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			foreach ( $decoded as $entry ) {
				$is_pay_opt = ( isset( $entry->variable_name ) && 'Payment Option' === $entry->variable_name )
					|| ( isset( $entry->display_name ) && 'Payment Option' === $entry->display_name );
				if ( $is_pay_opt && isset( $entry->value ) && '' !== trim( $entry->value ) ) {
					$meta_pts[] = trim( $entry->value );
					break;
				}
			}
		}

		// Dedupe case-insensitively, keep first-seen casing.
		$seen          = [];
		$payment_types = [];
		foreach ( array_merge( $db_pts, $meta_pts ) as $v ) {
			$k = strtolower( $v );
			if ( '' === $k || isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ]      = true;
			$payment_types[] = $v;
		}
		sort( $payment_types );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Payment History', 'pff-flutterwave' ); ?></h1>
			<p class="description"><?php esc_html_e( 'All payments across every Flutterwave form. Use the filters below to narrow results.', 'pff-flutterwave' ); ?></p>

			<form method="get">
				<input type="hidden" name="post_type" value="flutterwave_form" />
				<input type="hidden" name="page" value="payment-history" />

				<div class="tablenav top" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
					<label for="form_id" class="screen-reader-text"><?php esc_html_e( 'Filter by form', 'pff-flutterwave' ); ?></label>
					<select name="form_id" id="form_id">
						<option value="0"><?php esc_html_e( 'All forms', 'pff-flutterwave' ); ?></option>
						<?php foreach ( $forms as $f ) : ?>
							<option value="<?php echo esc_attr( $f->ID ); ?>" <?php selected( $selected_form, $f->ID ); ?>>
								<?php echo esc_html( $f->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="paid_status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'pff-flutterwave' ); ?></label>
					<select name="paid_status" id="paid_status">
						<option value=""><?php esc_html_e( 'All statuses', 'pff-flutterwave' ); ?></option>
						<option value="paid" <?php selected( $selected_status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'pff-flutterwave' ); ?></option>
						<option value="pending" <?php selected( $selected_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'pff-flutterwave' ); ?></option>
					</select>

					<label for="payment_type" class="screen-reader-text"><?php esc_html_e( 'Filter by payment option', 'pff-flutterwave' ); ?></label>
					<select name="payment_type" id="payment_type">
						<option value=""><?php esc_html_e( 'All payment options', 'pff-flutterwave' ); ?></option>
						<?php foreach ( $payment_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $selected_pt, $pt ); ?>>
								<?php echo esc_html( ucwords( str_replace( [ '_', '-' ], ' ', $pt ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="from_date"><?php esc_html_e( 'From', 'pff-flutterwave' ); ?></label>
					<input type="date" name="from_date" id="from_date" value="<?php echo esc_attr( $from_date ); ?>" />

					<label for="to_date"><?php esc_html_e( 'To', 'pff-flutterwave' ); ?></label>
					<input type="date" name="to_date" id="to_date" value="<?php echo esc_attr( $to_date ); ?>" />

					<button type="submit" class="button"><?php esc_html_e( 'Apply Filters', 'pff-flutterwave' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=flutterwave_form&page=payment-history' ) ); ?>">
						<?php esc_html_e( 'Reset', 'pff-flutterwave' ); ?>
					</a>
				</div>

				<?php $table->search_box( esc_html__( 'Search', 'pff-flutterwave' ), 'pff_search' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function load_list_table_class() {
		if ( class_exists( __NAMESPACE__ . '\\Payment_History_List_Table' ) ) {
			return;
		}
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once __DIR__ . '/class-payment-history-list-table.php';
	}
}

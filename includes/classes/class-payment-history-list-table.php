<?php
/**
 * Payment History WP_List_Table subclass. Lazy-loaded from Payment_History::load_list_table_class()
 * so WP_List_Table parent class is available before this file is parsed.
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Payment_History_List_Table extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			[
				'singular' => 'payment',
				'plural'   => 'payments',
				'ajax'     => false,
			]
		);
	}

	public function get_columns() {
		return [
			'id'             => esc_html__( '#', 'pff-flutterwave' ),
			'form'           => esc_html__( 'Form', 'pff-flutterwave' ),
			'email'          => esc_html__( 'Email', 'pff-flutterwave' ),
			'amount'         => esc_html__( 'Amount', 'pff-flutterwave' ),
			'txn_code'       => esc_html__( 'Txn Code', 'pff-flutterwave' ),
			'transaction_id' => esc_html__( 'Flw Txn ID', 'pff-flutterwave' ),
			'payment_type'   => esc_html__( 'Payment Option', 'pff-flutterwave' ),
			'paid'           => esc_html__( 'Status', 'pff-flutterwave' ),
			'created_at'     => esc_html__( 'Created', 'pff-flutterwave' ),
			'paid_at'        => esc_html__( 'Paid', 'pff-flutterwave' ),
		];
	}

	public function get_sortable_columns() {
		return [
			'id'         => [ 'id', true ],
			'email'      => [ 'email', false ],
			'amount'     => [ 'amount', false ],
			'created_at' => [ 'created_at', false ],
			'paid_at'    => [ 'paid_at', false ],
		];
	}

	protected function get_hidden_columns() {
		return [];
	}

	public function prepare_items() {
		global $wpdb;

		$table    = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;
		$per_page = 20;
		$paged    = max( 1, $this->get_pagenum() );
		$offset   = ( $paged - 1 ) * $per_page;

		$where  = 'WHERE deleted_at IS NULL';
		$params = [];

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		if ( $form_id > 0 ) {
			$where   .= ' AND post_id = %d';
			$params[] = $form_id;
		}

		$paid_status = isset( $_GET['paid_status'] ) ? sanitize_text_field( wp_unslash( $_GET['paid_status'] ) ) : '';
		if ( 'paid' === $paid_status ) {
			$where .= ' AND paid = 1';
		} elseif ( 'pending' === $paid_status ) {
			$where .= ' AND paid = 0';
		}

		$payment_type_f = isset( $_GET['payment_type'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_type'] ) ) : '';
		if ( '' !== $payment_type_f ) {
			$where   .= ' AND payment_type = %s';
			$params[] = $payment_type_f;
		}

		$from_date = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
		if ( $from_date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from_date ) ) {
			$where   .= ' AND created_at >= %s';
			$params[] = $from_date . ' 00:00:00';
		}

		$to_date = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';
		if ( $to_date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to_date ) ) {
			$where   .= ' AND created_at <= %s';
			$params[] = $to_date . ' 23:59:59';
		}

		$search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : '';
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND ( email LIKE %s OR txn_code LIKE %s OR txn_code_2 LIKE %s OR transaction_id LIKE %s OR flw_ref LIKE %s OR metadata LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$allowed_orderby = [ 'id', 'email', 'amount', 'created_at', 'paid_at' ];
		$orderby         = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
		$count_sql = $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total_items = (int) $wpdb->get_var( $count_sql );

		$data_sql        = "SELECT * FROM `{$table}` {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$data_params     = array_merge( $params, [ $per_page, $offset ] );
		$data_sql_prep   = $wpdb->prepare( $data_sql, $data_params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->items = $wpdb->get_results( $data_sql_prep );

		$this->_column_headers = [ $this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns() ];

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			]
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return (int) $item->id;

			case 'form':
				$title = get_the_title( $item->post_id );
				$link  = admin_url( 'edit.php?post_type=flutterwave_form&page=submissions&form=' . absint( $item->post_id ) );
				return '<a href="' . esc_url( $link ) . '">' . esc_html( $title ? $title : '#' . $item->post_id ) . '</a>';

			case 'email':
				return '<a href="mailto:' . esc_attr( $item->email ) . '">' . esc_html( $item->email ) . '</a>';

			case 'amount':
				$currency = get_post_meta( $item->post_id, '_currency', true );
				$currency = $currency ? $currency : 'NGN';
				return esc_html( $currency . ' ' . number_format_i18n( (float) $item->amount, 2 ) );

			case 'txn_code':
				return esc_html( '' !== $item->txn_code_2 ? $item->txn_code_2 : $item->txn_code );

			case 'transaction_id':
				return esc_html( $item->transaction_id );

			case 'payment_type':
				$pt = isset( $item->payment_type ) ? trim( $item->payment_type ) : '';
				return '' !== $pt ? esc_html( ucwords( str_replace( [ '_', '-' ], ' ', $pt ) ) ) : '&mdash;';

			case 'paid':
				return ( 1 == $item->paid )
					? '<span style="color:#1a7e1a;font-weight:bold">' . esc_html__( 'Paid', 'pff-flutterwave' ) . '</span>'
					: '<span style="color:#b26b00;font-weight:bold">' . esc_html__( 'Pending', 'pff-flutterwave' ) . '</span>';

			case 'created_at':
				return esc_html( $item->created_at );

			case 'paid_at':
				return $item->paid_at ? esc_html( $item->paid_at ) : '&mdash;';
		}
		return '';
	}

	public function no_items() {
		esc_html_e( 'No payments found.', 'pff-flutterwave' );
	}
}

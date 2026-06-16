<?php
namespace flutterwave\payment_forms;

class Payments_List_Table extends \WP_List_Table
{

	/**
	 * Holds the current form ID
	 *
	 * @var integer
	 */
	public $form_id = 0;

	public function prepare_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['form'] ) || empty( $_GET['form'] ) ) { 
			return esc_html__( 'No form set', 'pff-flutterwave' );
		}
		$this->form_id  = sanitize_text_field( wp_unslash( $_GET['form'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$helpers      = Helpers::get_instance();
		$data         = array();
		$row_data     = $helpers->get_payments_by_id( $this->form_id, $this->get_args() );
		$data         = $this->format_row_data( $row_data );
		$columns      = $this->get_columns();
		$hidden       = $this->get_hidden_columns();
		$sortable     = $this->get_sortable_columns();
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = count( $data );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page
			)
		);
		$data                  = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;

		$rows = count( $row_data );
		return $rows;
    }

	/**
	 * Returns the headers and keys for our column headers
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'id'  => '#',
			'email' => esc_html__( 'Email', 'pff-flutterwave' ),
			'amount' => esc_html__( 'Amount', 'pff-flutterwave' ),
			'txn_code' => esc_html__( 'Txn Code', 'pff-flutterwave' ),
			'metadata' => esc_html__( 'Data', 'pff-flutterwave' ),
			'date'  => esc_html__( 'Date', 'pff-flutterwave' ),
		);
		return $columns;
	}

	/**
	 * Returns an array of the hidden columns
	 *
	 * @return array
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * Set which of our columns are sortable.
	 *
	 * @return void
	 */
	public function get_sortable_columns() {
		return array(
			'email' => array(
				'email',
				false
			),
			'date' => array(
				'created_at',
				false
			),
			'amount' => array(
				'amount',
				false
			)
		);
	}

	/**
	 * Get the table data
	 *
	 * @return Array
	 */
	private function table_data( $data ) {
		return $data;
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param Array  $item        Data
	 * @param String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'email':
			case 'amount':
			case 'txn_code':
			case 'metadata':
			case 'date':
				return $item[ $column_name ];

			default:
			return print_r( $item, true );
		}
	}

	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @return int
	 */
	private function get_args() {
		$args = array(
			'orderby' => 'created_at',
			'order'   => 'desc',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['order'] ) ) {
			$args['order'] = sanitize_text_field( wp_unslash( $_GET['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'date' === $args['order'] ) {
				$args['order'] = 'created_at';
			}
		}
		return $args;
	}

	/**
	 * Format each row into a readable HTML string.
	 *
	 * @param array $data
	 * @return array
	 */
	public function format_row_data( $alldata ) {
		$currency = get_post_meta( $this->form_id, '_currency', true );
		$new_data = [];
		foreach ( $alldata as $key => $row ) {
			$new_key = $key + 1;
			if ( $row->txn_code_2 != "" ) {
				$txn_code = $row->txn_code_2;
			} else {
				$txn_code = $row->txn_code;
			}
			$new_data[] = array(
				'id'       => (int) $new_key,
				'email'    => '<a href="mailto:' . esc_attr( $row->email ) . '">' . esc_html( $row->email ) . '</a>',
				'amount'   => esc_html( $currency ) . '<b>' . esc_html( number_format( $row->amount ) ) . '</b>',
				'txn_code' => esc_html( $txn_code ),
				'metadata' => $this->format_metadata( $row->metadata ),
				'date'     => esc_html( $row->created_at ),
			);
		}
		return $new_data;
	}

	/**
	 * Format the Meta Data for output in each table row.
	 *
	 * @param string $data
	 * @return string
	 */
	public function format_metadata( $data ) {
		$new  = json_decode( $data );
		$text = '';

		if ( null === $new ) {
			return $text;
		}

		// Determine both for backwards compatibility.
		if ( is_array( $new ) || ( is_object( $new ) && property_exists( $new, '0' ) ) ) {
			foreach ( (array) $new as $item ) {
				if ( ! is_object( $item ) ) {
					continue;
				}
				$display = isset( $item->display_name ) ? esc_html( $item->display_name ) : '';
				$value   = isset( $item->value ) ? $item->value : '';
				if ( isset( $item->type ) && 'text' === $item->type ) {
					$text .= '<b>' . $display . ': </b> ' . esc_html( $value ) . '<br />';
				} else {
					$text .= '<b>' . $display . ": </b>  <a target='_blank' href='" . esc_url( $value ) . "'>link</a><br />";
				}
			}
		} else {
			if ( count( (array) $new ) > 0 ) {
				foreach ( (array) $new as $key => $item ) {
					$text .= '<b>' . esc_html( $key ) . ': </b> ' . esc_html( (string) $item ) . '<br />';
				}
			}
		}
		return $text;
	}
}
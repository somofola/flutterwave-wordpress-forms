<?php
/**
 * The functions to handle the confirm payment action
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Confirm_Payment Class
 *
 * Note: Flutterwave's verify endpoint requires the numeric transaction_id returned by
 * the inline checkout callback (data.transaction_id), not the tx_ref. The frontend
 * therefore POSTs both `code` (tx_ref) and `transaction_id` to this handler.
 */
class Confirm_Payment {

	public $helpers;
	protected $meta = array();
	protected $transaction = false;
	protected $payment_meta;
	protected $form_id = 0;
	protected $amount = 0;
	protected $oamount = 0;
	protected $quantity = 1;
	protected $txn_column = 'txn_code';
	protected $reference = '';

	public function __construct() {
		add_action( 'wp_ajax_pff_flutterwave_confirm_payment', [ $this, 'confirm_payment' ] );
		add_action( 'wp_ajax_nopriv_pff_flutterwave_confirm_payment', [ $this, 'confirm_payment' ] );
	}

	protected function setup_data( $payment ) {
		$this->payment_meta = $payment;
		$this->meta         = $this->helpers->parse_meta_values( get_post( $this->payment_meta->post_id ) );
		$this->form_id      = $this->payment_meta->post_id;
		$this->amount       = $this->payment_meta->amount;
		$this->oamount      = $this->amount;
		$this->reference    = $this->payment_meta->txn_code;
		if ( isset( $this->payment_meta->txn_code_2 ) && ! empty( $this->payment_meta->txn_code_2 ) ) {
			$this->reference = $this->payment_meta->txn_code_2;
		}
	}

	public function confirm_payment() {

		if ( ! isset( $_POST['nonce'] ) || false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pff-flutterwave-confirm' ) ) {
			exit( wp_json_encode( array(
				'error'         => true,
				'error_message' => esc_html__( 'Nonce verification is required.', 'pff-flutterwave' ),
			) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! isset( $_POST['code'] ) || '' === trim( wp_unslash( $_POST['code'] ) ) ) {
			exit( wp_json_encode( array(
				'error'         => true,
				'error_message' => esc_html__( 'Did you make a payment?', 'pff-flutterwave' ),
			) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! isset( $_POST['transaction_id'] ) || '' === trim( wp_unslash( $_POST['transaction_id'] ) ) ) {
			exit( wp_json_encode( array(
				'error'         => true,
				'error_message' => esc_html__( 'Missing Flutterwave transaction id.', 'pff-flutterwave' ),
			) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $_POST['retry'] ) ) {
			$this->txn_column = 'txn_code_2';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $_POST['quantity'] ) ) {
			$this->quantity = sanitize_text_field( wp_unslash( $_POST['quantity'] ) );
		}

		$this->helpers   = new Helpers();
		$code            = sanitize_text_field( wp_unslash( $_POST['code'] ) );
		$transaction_id  = sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) );
		$record          = $this->helpers->get_db_record( $code, $this->txn_column );

		if ( false !== $record ) {

			$this->setup_data( $record );

			// Verify our transaction with the Flutterwave API by transaction id.
			$transaction = pff_flutterwave()->classes['transaction-verify']->verify_transaction( $transaction_id );

			if ( ! empty( $transaction ) && isset( $transaction['data'] ) ) {
				$transaction['data'] = json_decode( $transaction['data'] );
				// Flutterwave returns data.status === 'successful' on success.
				if ( isset( $transaction['data']->status ) && 'successful' === $transaction['data']->status ) {
					// Also ensure the tx_ref returned matches the one we created.
					if ( isset( $transaction['data']->tx_ref ) && $transaction['data']->tx_ref === $code ) {
						$this->update_sold_inventory();
						$response = $this->update_payment_dates( $transaction['data'] );
					} else {
						$response = [
							'message' => esc_html__( 'Transaction reference mismatch.', 'pff-flutterwave' ),
							'result'  => 'failed',
						];
					}
				} else {
					$response = [
						'message' => esc_html__( 'Payment Verification Failed', 'pff-flutterwave' ),
						'result'  => 'failed',
					];
				}
			} else {
				$response = [
					'message' => esc_html__( 'Failed to connect to Flutterwave.', 'pff-flutterwave' ),
					'result'  => 'failed',
				];
			}

		} else {
			$response = [
				'message' => esc_html__( 'Payment Verification Failed', 'pff-flutterwave' ),
				'result'  => 'failed',
			];
		}

		if ( 'success' === $response['result'] ) {

			$this->maybe_create_subscription();

			$sendreceipt = $this->meta['sendreceipt'];
			$decoded     = json_decode( $this->payment_meta->metadata );
			$fullname    = isset( $decoded[1]->value ) ? $decoded[1]->value : '';

			if ( 'yes' === $sendreceipt ) {
				do_action( 'pff_flutterwave_send_receipt',
					$this->payment_meta->post_id,
					$this->payment_meta->currency,
					$this->payment_meta->amount,
					$fullname,
					$this->payment_meta->email,
					$this->reference,
					$this->payment_meta->metadata
				);

				do_action( 'pff_flutterwave_send_receipt_owner',
					$this->payment_meta->post_id,
					$this->payment_meta->currency,
					$this->payment_meta->amount,
					$fullname,
					$this->payment_meta->email,
					$this->reference,
					$this->payment_meta->metadata
				);
			}
		}

		if ( 'success' === $response['result'] && '' !== $this->meta['redirect'] ) {
			$response['result'] = 'success2';
			$response['link']   = $this->add_param_to_url( $this->meta['redirect'], $this->reference );
		}

		echo wp_json_encode( $response );
		die();
	}

	public function add_param_to_url( $url, $ref ) {
		$parsed_url = wp_parse_url( $url );
		parse_str( isset( $parsed_url['query'] ) ? $parsed_url['query'] : '', $query_params );

		// Use tx_ref / transaction_reference to mirror Flutterwave's redirect param conventions
		// while keeping the legacy keys for back-compat with any redirect handlers ported from
		// the Paystack edition.
		$query_params['tx_ref']                = $ref;
		$query_params['transaction_reference'] = $ref;
		$query_params['trxref']                = $ref;
		$query_params['reference']             = $ref;

		$query_string = http_build_query( $query_params );

		$new_url  = ( isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '' );
		$new_url .= ( isset( $parsed_url['user'] ) ? $parsed_url['user'] . ( isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '' ) . '@' : '' );
		$new_url .= ( isset( $parsed_url['host'] ) ? $parsed_url['host'] : '' );
		$new_url .= ( isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '' );
		$new_url .= ( isset( $parsed_url['path'] ) ? $parsed_url['path'] : '' );
		$new_url .= ( ! empty( $query_string ) ? '?' . $query_string : '' );
		$new_url .= ( isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '' );

		return $new_url;
	}

	protected function update_sold_inventory() {
		$usequantity = $this->meta['usequantity'];
		$sold        = (int) $this->meta['sold'];

		if ( 'yes' === $usequantity ) {
			$quantity = 1;
			// phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_POST['quantity'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification
				$quantity = (int) sanitize_text_field( wp_unslash( $_POST['quantity'] ) );
			}
			$sold = $this->meta['sold'];

			if ( '' === $sold ) {
				$sold = 0;
			}
			$sold += $quantity;
		} else {
			$sold++;
		}

		if ( $this->meta['sold'] ) {
			update_post_meta( $this->form_id, '_sold', $sold );
		} else {
			add_post_meta( $this->form_id, '_sold', $sold, true );
		}
	}

	/**
	 * Updates the paid Date for the current record.
	 *
	 * Flutterwave verify response fields used:
	 *   $data->amount     — major-unit amount (no kobo division required)
	 *   $data->tx_ref     — our generated reference
	 *   $data->created_at — ISO 8601 timestamp of the payment
	 *
	 * @param object $data
	 * @return array
	 */
	protected function update_payment_dates( $data ) {
		global $wpdb;
		$table  = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;
		$return = [
			'message' => esc_html__( 'DB not updated.', 'pff-flutterwave' ),
			'result'  => 'failed',
		];

		// Flutterwave amounts are already in major units — no /100 needed.
		$amount_paid     = isset( $data->amount ) ? floatval( $data->amount ) : 0;
		$flutterwave_ref = isset( $data->tx_ref ) ? $data->tx_ref : $this->reference;
		$paid_at         = isset( $data->created_at ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data->created_at ) ) : current_time( 'mysql', true );

		if ( 'optional' === $this->meta['recur'] || 'plan' === $this->meta['recur'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'paid' => 1, 'amount' => $amount_paid, 'paid_at' => $paid_at ),
				array( $this->txn_column => $flutterwave_ref )
			);
			$return = [
				'message' => $this->meta['successmsg'],
				'result'  => 'success',
			];
		} else {
			if ( 0 === (int) $this->oamount || 1 === $this->meta['usevariableamount'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$table,
					array( 'paid' => 1, 'amount' => $amount_paid, 'paid_at' => $paid_at ),
					array( $this->txn_column => $flutterwave_ref )
				);
				$return = [
					'message' => $this->meta['successmsg'],
					'result'  => 'success',
				];
			} else {
				if ( (int) $this->oamount !== (int) $amount_paid ) {
					$return = [
						// translators: %1$s: currency, %2$s: formatted amount required
						'message' => sprintf( esc_html__( 'Invalid amount Paid. Amount required is %1$s<b>%2$s</b>', 'pff-flutterwave' ), $this->meta['currency'], number_format( $this->oamount ) ),
						'result'  => 'failed',
					];
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->update(
						$table,
						array( 'paid' => 1, 'paid_at' => $paid_at ),
						array( $this->txn_column => $flutterwave_ref )
					);
					$return = [
						'message' => $this->meta['successmsg'],
						'result'  => 'success',
					];
				}
			}
		}
		return $return;
	}

	protected function maybe_create_subscription() {
		if ( 1 == $this->meta['startdate_enabled'] && ! empty( $this->meta['startdate_days'] ) && ! empty( $this->meta['startdate_plan_code'] ) ) {
			$start_date = gmdate( 'c', strtotime( '+' . $this->meta['startdate_days'] . ' days' ) );
			$body       = array(
				'start_date' => $start_date,
				'plan'       => $this->meta['startdate_plan_code'],
				'customer'   => $this->payment_meta->email,
			);

			$created_sub = pff_flutterwave()->classes['request-subscription']->create_subscription( $body );
			if ( false !== $created_sub ) {
				// noop
			}
		}
	}
}

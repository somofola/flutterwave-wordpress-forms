<?php
/**
 * Verifies a Flutterwave transaction by transaction id.
 *
 * Flutterwave verification endpoint:
 *   GET https://api.flutterwave.com/v3/transactions/{id}/verify
 *
 * Success response shape:
 *   { status: "success", message: "Transaction fetched successfully",
 *     data: { id, tx_ref, flw_ref, amount, currency, status: "successful",
 *             customer: { email, name, phone_number }, ... } }
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transaction_Verify
 */
class Transaction_Verify extends API {

	public function __construct() {
		parent::__construct();
		$this->set_module( 'transactions' );
	}

	/**
	 * Verifies a transaction.
	 *
	 * Flutterwave verify needs the numeric transaction_id (not tx_ref). The
	 * frontend callback returns response.transaction_id which we pass here.
	 *
	 * @param string $transaction_id
	 * @return boolean|array
	 */
	public function verify_transaction( $transaction_id = '' ) {
		if ( '' === $transaction_id || ! $this->api_ready() ) {
			return false;
		}

		$this->set_url_args( $transaction_id . '/verify' );
		$response = $this->get_request();
		return $this->verify_response( $response );
	}

	/**
	 * Reviews the transaction and returns success or an error and a message.
	 *
	 * @param object $response
	 * @return array
	 */
	public function verify_response( $response ) {
		if ( false === $response || empty( $response ) ) {
			return [
				'message' => esc_html__( 'Payment Verification Failed', 'pff-flutterwave' ),
				'result'  => 'failed',
			];
		}

		// Flutterwave v3 returns top-level "status":"success" + data.status:"successful".
		if ( isset( $response->status ) && 'success' === $response->status
			&& isset( $response->data->status ) && 'successful' === $response->data->status ) {
			return [
				'message' => esc_html__( 'Payment Verification Passed', 'pff-flutterwave' ),
				'result'  => 'success',
				'data'    => wp_json_encode( $response->data ),
			];
		}

		return [
			'message' => esc_html__( 'Transaction Failed/Invalid Reference', 'pff-flutterwave' ),
			'result'  => 'failed',
		];
	}
}

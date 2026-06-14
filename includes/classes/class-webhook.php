<?php
/**
 * Flutterwave Webhook handler.
 *
 * Flutterwave sends webhooks with the configured secret hash in the
 * `verif-hash` HTTP header. We listen on a query-arg endpoint
 * (`?pff_flutterwave_webhook=1`) and verify against the saved hash before
 * trusting any payload. Successful charge events trigger transaction
 * verification + DB update, mirroring the synchronous confirm-payment flow.
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhook {

	public function __construct() {
		add_action( 'init', [ $this, 'maybe_handle_webhook' ] );
	}

	public function maybe_handle_webhook() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['pff_flutterwave_webhook'] ) ) {
			return;
		}
		$this->handle();
	}

	protected function get_expected_hash() {
		$mode = esc_attr( get_option( 'mode' ) );
		return ( 'test' === $mode ) ? esc_attr( get_option( 'tsh' ) ) : esc_attr( get_option( 'lsh' ) );
	}

	protected function handle() {
		$expected = $this->get_expected_hash();
		$received = isset( $_SERVER['HTTP_VERIF_HASH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_VERIF_HASH'] ) ) : '';

		if ( '' === $expected || '' === $received || ! hash_equals( $expected, $received ) ) {
			status_header( 401 );
			exit;
		}

		$raw     = file_get_contents( 'php://input' );
		$payload = json_decode( $raw );

		if ( ! is_object( $payload ) || ! isset( $payload->data ) ) {
			status_header( 400 );
			exit;
		}

		$event = isset( $payload->event ) ? $payload->event : '';
		$data  = $payload->data;

		// We only act on successful charges. Subscriptions / failures are no-ops here.
		if ( false === strpos( $event, 'charge.completed' ) && false === strpos( $event, 'charge' ) ) {
			status_header( 200 );
			exit;
		}

		if ( ! isset( $data->status ) || 'successful' !== $data->status || ! isset( $data->tx_ref ) || ! isset( $data->id ) ) {
			status_header( 200 );
			exit;
		}

		global $wpdb;
		$helpers = new Helpers();

		// Find by tx_ref under either the primary code column or the retry column.
		$record = $helpers->get_db_record( $data->tx_ref, 'txn_code' );
		$column = 'txn_code';
		if ( false === $record ) {
			$record = $helpers->get_db_record( $data->tx_ref, 'txn_code_2' );
			$column = 'txn_code_2';
		}

		if ( false === $record ) {
			status_header( 200 );
			exit;
		}

		// Re-verify with the API rather than trusting the webhook payload alone.
		$verify = pff_flutterwave()->classes['transaction-verify']->verify_transaction( $data->id );
		if ( empty( $verify ) || ! isset( $verify['result'] ) || 'success' !== $verify['result'] ) {
			status_header( 200 );
			exit;
		}

		$table  = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;
		$amount = isset( $data->amount ) ? floatval( $data->amount ) : floatval( $record->amount );
		$paid_at = isset( $data->created_at ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data->created_at ) ) : current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'paid' => 1, 'amount' => $amount, 'paid_at' => $paid_at ),
			array( $column => $data->tx_ref )
		);

		status_header( 200 );
		exit;
	}
}

<?php
/**
 * Flutterwave Subscriptions wrapper.
 *
 * NOTE: Flutterwave handles subscriptions natively once a customer pays for a
 * payment_plan inline — there is no explicit "create subscription" endpoint
 * mirroring Paystack's. We expose list / cancel-style helpers so the rest of
 * the plugin keeps the same shape.
 *
 * Endpoint:
 *   GET https://api.flutterwave.com/v3/subscriptions
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Request_Subscription extends API {

	public function __construct() {
		parent::__construct();
		$this->set_module( 'subscriptions' );
	}

	/**
	 * "Create" a subscription. With Flutterwave, attaching `payment_plan` to
	 * the inline checkout is what creates the subscription server-side, so this
	 * method only POSTs override params if explicitly provided.
	 *
	 * @return boolean|object
	 */
	public function create_subscription( $body = [] ) {
		$sub = false;
		if ( empty( $body ) || ! $this->api_ready() ) {
			return false;
		}
		$response = $this->post_request( $body );
		if ( isset( $response->status ) && 'success' === $response->status ) {
			$sub = $response;
		}
		return $sub;
	}
}

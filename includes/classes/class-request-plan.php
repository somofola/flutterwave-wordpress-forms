<?php
/**
 * Flutterwave Payment Plans wrapper.
 *
 * Endpoints used:
 *   POST  https://api.flutterwave.com/v3/payment-plans
 *   GET   https://api.flutterwave.com/v3/payment-plans/{id}
 *   GET   https://api.flutterwave.com/v3/payment-plans?amount=X&interval=monthly
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin API Class
 */
class Request_Plan extends API {

	public function __construct() {
		parent::__construct();
		$this->set_module( 'payment-plans' );
	}

	/**
	 * Fetch a single plan by ID.
	 *
	 * @return boolean|object
	 */
	public function fetch_plan( $code = '' ) {
		$plan = false;
		if ( '' === $code || ! $this->api_ready() ) {
			return false;
		}
		$this->set_url_args( $code );
		$response = $this->get_request();
		if ( $this->is_plan_valid( $response ) ) {
			$plan = $response;
		}
		return $plan;
	}

	/**
	 * Confirms a Flutterwave payment plan is active.
	 *
	 * @param object $plan
	 * @return boolean
	 */
	public function is_plan_valid( $plan ) {
		if ( null === $plan || empty( $plan ) ) {
			return false;
		}
		if ( ! isset( $plan->status ) || 'success' !== $plan->status ) {
			return false;
		}
		if ( ! isset( $plan->data ) ) {
			return false;
		}
		// Flutterwave payment-plans return data.status of "active" / "cancelled".
		if ( isset( $plan->data->status ) && 'active' !== $plan->data->status ) {
			return false;
		}
		return true;
	}

	/**
	 * List plans matching the query args (amount, interval).
	 *
	 * @return boolean|object
	 */
	public function list_plans( $url_args = '' ) {
		$plan = false;
		if ( '' === $url_args || ! $this->api_ready() ) {
			return false;
		}
		$this->set_url_args( $url_args );
		$response = $this->get_request();
		if ( isset( $response->data ) && is_array( $response->data ) && isset( $response->data[0] ) ) {
			$plan = $response->data[0];
		}
		return $plan;
	}

	/**
	 * Create a payment plan.
	 *
	 * Flutterwave expects: { amount, name, interval, duration (optional) }
	 *
	 * @return boolean|object
	 */
	public function create_plan( $body = [] ) {
		$plan = false;
		if ( empty( $body ) || ! $this->api_ready() ) {
			return false;
		}
		$response = $this->post_request( $body );
		if ( isset( $response->status ) && 'success' === $response->status && isset( $response->data->id ) ) {
			$plan = $response;
		}
		return $plan;
	}
}

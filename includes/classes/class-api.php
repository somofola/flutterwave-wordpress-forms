<?php
/**
 * Flutterwave v3 API base class.
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
class API {

	/**
	 * The API Request URL (Flutterwave v3 base).
	 *
	 * @var string
	 */
	protected $url = 'https://api.flutterwave.com/v3/';

	/**
	 * The module e.g. "transactions", "payment-plans", "subscriptions".
	 *
	 * @var string
	 */
	protected $module = '';

	/**
	 * Additional path/query arguments appended after the module.
	 *
	 * @var string
	 */
	protected $url_args = '';

	/**
	 * Public API Key.
	 *
	 * @var string
	 */
	protected $public = '';

	/**
	 * Secret API Key.
	 *
	 * @var string
	 */
	private $secret = '';

	public function __construct() {
		$mode = esc_attr( get_option( 'mode' ) );
		if ( $mode == 'test' ) {
			$this->public = esc_attr( get_option( 'tpk' ) );
			$this->secret = esc_attr( get_option( 'tsk' ) );
		} else {
			$this->public = esc_attr( get_option( 'lpk' ) );
			$this->secret = esc_attr( get_option( 'lsk' ) );
		}
	}

	protected function set_module( $module = '' ) {
		$this->module = $module . '/';
	}

	protected function set_url_args( $args = '' ) {
		$this->url_args = $args;
	}

	protected function get_headers(){
		return array(
			'Authorization' => 'Bearer ' . $this->secret,
			'Content-Type'  => 'application/json',
		);
	}

	protected function get_url(){
		return $this->url . $this->module . $this->url_args;
	}

	protected function get_args(){
		return array(
			'headers' => $this->get_headers(),
			'timeout' => 60,
		);
	}

	/**
	 * Sends a GET request.
	 *
	 * @return boolean|object
	 */
	public function get_request() {
		$response = false;
		$return   = wp_remote_get( $this->get_url(), $this->get_args() );
		if ( ! is_wp_error( $return ) && 200 === wp_remote_retrieve_response_code( $return ) ) {
			$response = json_decode( wp_remote_retrieve_body( $return ) );
		}
		return $response;
	}

	/**
	 * Sends a POST request (JSON body, per Flutterwave v3).
	 *
	 * @return boolean|object
	 */
	public function post_request( $body = [] ) {
		$response = false;
		$args     = array(
			'body'    => wp_json_encode( $body ),
			'headers' => $this->get_headers(),
			'timeout' => 60,
		);
		$return   = wp_remote_post( $this->get_url(), $args );
		if ( ! empty( $body ) && ! is_wp_error( $return ) ) {
			$code = wp_remote_retrieve_response_code( $return );
			if ( 200 === $code || 201 === $code ) {
				$response = json_decode( wp_remote_retrieve_body( $return ) );
			}
		}
		return $response;
	}

	/**
	 * Determines if all the settings have been entered.
	 *
	 * @return boolean
	 */
	public function api_ready() {
		return ( '' !== $this->secret );
	}
}

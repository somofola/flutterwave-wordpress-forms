<?php
/**
 * The functions to handle the form submission, this will trigger the payment requests to Flutterwave.
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Form Submit Class
 */
class Form_Submit {

	/**
	 * The helpers class.
	 *
	 * @var object
	 */
	public $helpers;

	protected $response = array();
	protected $meta = array();
	protected $form_id = 0;
	protected $form_data = 0;
	protected $metadata = array();
	protected $untouched = array();
	protected $fixed_metadata = array();
	protected $referer_url = '';

	public function __construct() {
		add_action( 'wp_ajax_pff_flutterwave_submit_action', [ $this, 'submit_action' ] );
		add_action( 'wp_ajax_nopriv_pff_flutterwave_submit_action', [ $this, 'submit_action' ] );
	}

	protected function valid_submission() {
		if ( ! isset( $_POST['pf-nonce'] ) || false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pf-nonce'] ) ), 'pff-flutterwave-invoice' ) ) {
			$this->response['result']  = 'failed';
			$this->response['message'] = esc_html__( 'Nonce verification is required.', 'pff-flutterwave' );
			return false;
		}

		if ( ! isset( $_POST['pf-id'] ) || '' == trim( sanitize_text_field( wp_unslash( $_POST['pf-id'] ) ) ) ) {
			$this->response['result']  = 'failed';
			$this->response['message'] = esc_html__( 'A form ID is required', 'pff-flutterwave' );
			return false;
		} else {
			$this->form_id = sanitize_text_field( wp_unslash( $_POST['pf-id'] ) );
		}

		if ( ! isset( $_POST['pf-pemail'] ) || '' == trim( sanitize_text_field( wp_unslash( $_POST['pf-pemail'] ) ) ) ) {
			$this->response['result']  = 'failed';
			$this->response['message'] = esc_html__( 'Email is required', 'pff-flutterwave' );
			return false;
		}
		return true;
	}

	protected function setup_data() {
		$this->helpers   = new Helpers();
		$this->meta      = $this->helpers->parse_meta_values( get_post( $this->form_id ) );
		$this->form_data = filter_input_array( INPUT_POST );

		$this->sanitize_form_data();

		$this->metadata = $this->form_data;
		unset(
			$this->metadata['action'],
			$this->metadata['pf-recur'],
			$this->metadata['pf-id'],
			$this->metadata['pf-pemail'],
			$this->metadata['pf-amount'],
			$this->metadata['pf-user_id'],
			$this->metadata['pf-interval']
		);
		$this->untouched = $this->helpers->format_meta_as_custom_fields( $this->metadata );

		if ( ! isset( $this->form_data['pf-quantity'] ) ) {
			$this->form_data['pf-quantity'] = 1;
		}

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$this->referer_url = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
	}

	public function sanitize_form_data() {
		foreach ( $this->form_data as $key => $value ) {
			switch ( $key ) {
				case 'pf-amount':
				case 'pf-vamount':
				case 'pf-quantity':
				case 'pf-id':
				case 'pf-user_id':
					$this->form_data[ $key ] = sanitize_text_field( $value );
				break;
				case 'pf-pemail':
					$this->form_data[ $key ] = sanitize_email( $value );
				break;
				default:
					$this->form_data[ $key ] = sanitize_text_field( $value );
			}
		}
	}

	public function process_amount( $amount = 0 ) {
		if ( 'no' === $this->meta['recur'] && 1 !== $this->meta['usevariableamount'] ) {
			if ( 0 !== (int) floatval( $this->meta['amount'] ) ) {
				$amount = floatval( $this->meta['amount'] );
			} else {
				$amount = $this->form_data['pf-amount'];
			}
			$amount = (int) str_replace( ' ', '', floatval( $amount ) );
		}

		if ( 1 === $this->meta['minimum'] && 0 !== floatval( $this->form_data['pf-amount'] ) ) {
			$amount = floatval( $this->form_data['pf-amount'] );
		}

		if ( 1 === $this->meta['usevariableamount'] ) {
			$payment_options = explode( ',', $this->meta['variableamount'] );
			if ( count( $payment_options ) > 0 ) {
				foreach ( $payment_options as $key => $payment_option ) {
					list( $a, $b ) = explode( ':', $payment_option );
					if ( $this->form_data['pf-vname'] == $a ) {
						$amount = $b;
					}
				}
			}
		}

		return $amount;
	}

	public function process_amount_quantity( $amount = 0 ) {
		if ( $this->meta['usequantity'] === 'yes' && ! ( 'optional' === $this->meta['recur'] || 'plan' === $this->meta['recur'] ) ) {
			$quantity = $this->form_data['pf-quantity'];
			$unit_amt = (int) str_replace( ' ', '', $amount );
			$amount   = (int) $quantity * $unit_amt;
		}
		return $amount;
	}

	public function process_images() {
		$max_file_size = $this->meta['filelimit'] * 1024 * 1024;
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_FILES ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			foreach ( $_FILES as $key_name => $value ) {
				if ( $value['size'] > 0 ) {
					if ( $value['size'] > $max_file_size ) {
						$response['result']  = 'failed';
						// translators: %s: maximum upload file size in MB
						$response['message'] = sprintf( esc_html__( 'Max upload size is %sMB', 'pff-flutterwave' ), $this->meta['filelimit'] );
						exit( wp_json_encode( $response ) );
					} else {
						$attachment_id  = media_handle_upload( $key_name, $this->form_id );
						$url            = wp_get_attachment_url( $attachment_id );
						$this->fixed_metadata[] = array(
							'display_name'  => ucwords( str_replace( '_', ' ', $key_name ) ),
							'variable_name' => $key_name,
							'type'          => 'link',
							'value'         => $url,
						);
					}
				} else {
					$this->fixed_metadata[] = array(
						'display_name'  => ucwords( str_replace( '_', ' ', $key_name ) ),
						'variable_name' => $key_name,
						'type'          => 'text',
						'value'         => esc_html__( 'No file Uploaded', 'pff-flutterwave' ),
					);
				}
			}
		}
	}

	public function submit_action() {
		if ( ! $this->valid_submission() ) {
			exit( wp_json_encode( $this->response ) );
		}

		$this->setup_data();

		/**
		 * Hookable location. Allows other plugins use a fresh submission before it is saved to the database.
		 */
		do_action( 'pff_flutterwave_before_save', $this );

		global $wpdb;
		$code  = $this->generate_code();
		$table = esc_sql( $wpdb->prefix . PFF_FLUTTERWAVE_TABLE );

		$this->fixed_metadata = [];

		$amount = (int) str_replace( ' ', '', $this->form_data['pf-amount'] );
		$amount = $this->process_amount( $amount );
		$amount = $this->process_amount_quantity( $amount );

		$this->fixed_metadata[] = array(
			'display_name'  => esc_html__( 'Unit Price', 'pff-flutterwave' ),
			'variable_name' => 'Unit_Price',
			'type'          => 'text',
			'value'         => $this->meta['currency'] . number_format( $amount ),
		);

		if ( 'customer' === $this->meta['txncharge'] ) {
			$amount = $this->helpers->process_transaction_fees( $amount );
		}

		$this->process_images();
		$this->process_recurring_plans( $amount );
		$this->fixed_metadata = json_decode( wp_json_encode( $this->fixed_metadata, JSON_NUMERIC_CHECK ), true );
		$this->fixed_metadata = array_merge( $this->untouched, $this->fixed_metadata );

		$insert = array(
			'post_id'  => $this->form_data['pf-id'],
			'email'    => $this->form_data['pf-pemail'],
			'user_id'  => $this->form_data['pf-user_id'],
			'amount'   => $amount,
			'plan'     => $this->meta['plancode'],
			'ip'       => $this->helpers->get_the_user_ip(),
			'txn_code' => $code,
			'metadata' => wp_json_encode( $this->fixed_metadata ),
		);

		$current_version = get_bloginfo('version');
		if ( version_compare( '6.2', $current_version, '<=' ) ) {
			// phpcs:disable WordPress.DB
			$exist = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE post_id = %d AND email = %s AND user_id = %d AND amount = %f AND plan = %s AND ip = %s AND paid = '0' AND metadata = %s",
					$insert['post_id'], $insert['email'], $insert['user_id'], $insert['amount'], $insert['plan'], $insert['ip'], $insert['metadata']
				)
			);
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB
			$exist = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `$table` WHERE post_id = '%d' AND email = '%s' AND user_id = '%d' AND amount = '%f' AND plan = '%s' AND ip = '%s' AND paid = '0' AND metadata = '%s'",
					$insert['post_id'], $insert['email'], $insert['user_id'], $insert['amount'], $insert['plan'], $insert['ip'], $insert['metadata']
				)
			);
			// phpcs:enable
		}

		if ( count( $exist ) > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'txn_code' => $code, 'plan' => $insert['plan'] ),
				array( 'id' => $exist[0]->id )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $insert );
		}

		if ( 'yes' === $this->meta['sendinvoice'] ) {
			do_action( 'pff_flutterwave_send_invoice', $this->form_id, $this->meta['currency'], $insert['amount'], $this->form_data['pf-fname'], $insert['email'], $code, $this->referer_url );
		}

		// Flutterwave subaccount support: a single subaccount id (string) — fee split is handled
		// by the Flutterwave dashboard rule attached to the subaccount. We carry the value through
		// so the frontend can include it in the FlutterwaveCheckout `subaccounts` array.
		$subaccount = ( isset( $this->meta['subaccount'] ) && '' !== $this->meta['subaccount'] ) ? $this->meta['subaccount'] : null;

		// Flutterwave expects amounts in major units (no kobo multiplication).
		$amount_total = round( floatval( $insert['amount'] ), 2 );

		$response = array(
			'result'        => 'success',
			'code'          => $insert['txn_code'],
			'plan'          => $insert['plan'],
			'quantity'      => $this->form_data['pf-quantity'],
			'email'         => $insert['email'],
			'name'          => $this->form_data['pf-fname'],
			'total'         => $amount_total,
			'currency'      => $this->meta['currency'],
			'custom_fields' => $this->fixed_metadata,
			'subaccount'    => $subaccount,
			'logo'          => PFF_FLUTTERWAVE_PLUGIN_URL . 'assets/images/logo.png',
			'title'         => get_the_title( $this->form_id ),
			'description'   => get_the_title( $this->form_id ),
		);

		$response['invoiceNonce'] = wp_create_nonce( 'pff-flutterwave-invoice' );
		$response['confirmNonce'] = wp_create_nonce( 'pff-flutterwave-confirm' );

		echo wp_json_encode( $response );
		die();
	}

	public function process_recurring_plans( $amount ) {
		$plan_code    = 'none';
		$has_interval = false;

		if ( 'no' !== $this->meta['recur'] ) {
			if ( 'optional' === $this->meta['recur'] ) {
				$interval = $this->form_data['pf-interval'];

				if ( 'no' !== $interval ) {
					// Flutterwave plan amounts are also in major units.
					$unit_amount   = $amount;
					$possible_plan = pff_flutterwave()->classes['request-plan']->list_plans( '?amount=' . $unit_amount . '&interval=' . $interval );

					if ( false !== $possible_plan && isset( $possible_plan->plan_code ) ) {
						$plan_code    = $possible_plan->plan_code;
						$has_interval = $possible_plan->interval;
					} else {
						$body = array(
							'name'     => get_the_title( $this->form_id ) . ' [' . $this->meta['currency'] . number_format( $amount ) . '] - ' . $interval,
							'amount'   => $unit_amount,
							'interval' => $interval,
							'currency' => $this->meta['currency'],
						);
						$created_plan = pff_flutterwave()->classes['request-plan']->create_plan( $body );
						if ( false !== $created_plan && isset( $created_plan->plan_code ) ) {
							$plan_code    = $created_plan->data->plan_code;
							$has_interval = $created_plan->data->interval;
						}
					}
				}
			} else {
				$plan_code = sanitize_text_field( wp_unslash( $this->form_data['pf-plancode'] ) );
				unset( $this->metadata['pf-plancode'] );
			}
		}

		if ( 'none' !== $plan_code ) {
			$this->meta['plancode'] = $plan_code;
			$this->fixed_metadata[] = array(
				'display_name'  => esc_html__( 'Plan', 'pff-flutterwave' ),
				'variable_name' => 'Plan',
				'type'          => 'text',
				'value'         => $plan_code,
			);

			if ( false !== $has_interval ) {
				$this->fixed_metadata[] = array(
					'display_name'  => esc_html__( 'Plan Interval', 'pff-flutterwave' ),
					'variable_name' => 'Plan Interval',
					'type'          => 'text',
					'value'         => $has_interval,
				);
			}
		} else if ( ! isset( $this->meta['plancode'] )  ) {
			$this->meta['plancode'] = '';
		}
	}

	/**
	 * Generate a unique Flutterwave tx_ref that does not yet exist in the database.
	 *
	 * @return string Generated unique code.
	 */
	public function generate_code() {
		do {
			$code  = $this->helpers->generate_new_code();
			$check = $this->helpers->check_code( $code );
		} while ( $check );

		return $code;
	}
}

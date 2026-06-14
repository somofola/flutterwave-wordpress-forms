<?php
/**
 * The Transaction Fee calculation class for Flutterwave.
 *
 * Flutterwave NGN local-card fee (at time of writing) is 1.4% capped at NGN 2,000
 * with no flat add-on. For international cards / other currencies the rate
 * differs. Configurable from the settings page so merchants can tune to plan.
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transaction_Fee {

	public $percentage;
	public $additional_charge;
	public $crossover_total;
	public $cap;

	public $charge_divider;
	public $crossover;
	public $flatline_plus_charge;
	public $flatline;

	public function __construct( $percentage = 0.014, $additional_charge = 0, $crossover_total = 250000, $cap = 200000 ) {
		$this->percentage        = $percentage;
		$this->additional_charge = $additional_charge;
		$this->crossover_total   = $crossover_total;
		$this->cap               = $cap;
		$this->__setup();
	}

	private function __setup() {
		$this->charge_divider       = $this->__charge_divider();
		$this->crossover            = $this->__crossover();
		$this->flatline_plus_charge = $this->__flatline_plus_charge();
		$this->flatline             = $this->__flatline();
	}

	private function __charge_divider() {
		return floatval( 1 - $this->percentage );
	}

	private function __crossover() {
		return ceil( ( $this->crossover_total * $this->charge_divider ) - $this->additional_charge );
	}

	private function __flatline_plus_charge() {
		if ( 0 == $this->percentage ) {
			return PHP_INT_MAX;
		}
		return floor( ( $this->cap - $this->additional_charge ) / $this->percentage );
	}

	private function __flatline() {
		return $this->flatline_plus_charge - $this->cap;
	}

	/**
	 * Charge for amount in the smallest currency unit (kobo for NGN).
	 */
	public function add_for_kobo( $amountinkobo ) {
		if ( $amountinkobo > $this->flatline ) {
			return $amountinkobo + $this->cap;
		} elseif ( $amountinkobo > $this->crossover ) {
			return ceil( ( $amountinkobo + $this->additional_charge ) / $this->charge_divider );
		} else {
			return ceil( $amountinkobo / $this->charge_divider );
		}
	}

	/**
	 * Charge for amount in the main currency unit (NGN).
	 */
	public function add_for_ngn( $amountinngn ) {
		return $this->add_for_kobo( ceil( $amountinngn * 100 ) ) / 100;
	}
}

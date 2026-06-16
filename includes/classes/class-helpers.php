<?php
/**
 * A class of helper functions that are used in many places.
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Helper class.
 */
class Helpers {

	/**
	 * Holds class isntance
	 *
	 * @var object \flutterwave\payment_forms\Helpers
	 */
	protected static $instance = null;

	/**
	 * The array of meta keys and their default values.
	 *
	 * @var array
	 */
	protected $defaults = [];

	/**
	 * An array of the allowed HTML tags
	 *
	 * @var array
	 */
	protected $allowed_html = [];

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->defaults = [
			'amount'              => 0,
			'merchant'            => '',
			'paybtn'              => esc_html__( 'Pay', 'pff-flutterwave' ),
			'successmsg'          => sprintf(
				/* translators: %s: support email address */
				esc_html__( 'Thank you for your payment! A receipt has been sent to your email. If you have any questions or did not receive your receipt, contact support at %s and we will get back to you shortly.', 'pff-flutterwave' ),
				get_option( 'admin_email' )
			),
			'txncharge'           => 'merchant',
			'loggedin'            => '',
			'currency'            => 'NGN',
			'filelimit'           => 2,
			'redirect'            => '',
			'minimum'             => 0,
			'usevariableamount'   => 0,
			'variableamount'      => 'Please configure your options:0,None:0',
			'hidetitle'           => 0,
			'loggedin'            => 'no',
			'recur'               => 'no',
			'recurplan'           => '',
			'subject'             => esc_html__( 'Thank you for your payment', 'pff-flutterwave' ),
			'heading'             => esc_html__( 'We\'ve received your payment', 'pff-flutterwave' ),
			'message'             => esc_html__( 'Your payment was received and we appreciate it.', 'pff-flutterwave' ),
			'sendreceipt'         => 'yes',
			'sendinvoice'         => 'yes',
			'usequantity'         => 'no',
			'useinventory'        => 'no',
			'inventory'           => 0,
			'sold'                => 0,
			'quantity'            => 10,
			'quantityunit'        => esc_html__( 'Quantity', 'pff-flutterwave' ),
			'useagreement'        => 'no',
			'agreementlink'       => '',
			'subaccount'          => '',
			'txnbearer'           => 'account',
			'merchantamount'      => '',
			'startdate_days'      => '',
			'startdate_plan_code' => '',
			'startdate_enabled'   => 0,
		];

		$this->allowed_html = array(
			'small' => array(
				'href' => true,
				'target' => true
			),
			'a' => array(
				'href' => true,
				'target' => true
			),
			'p' => array(),
			'input' => array(
				'type' => true,
				'name' => true,
				'value' => true,
				'class' => true,
				'checked' => true
			),
			'br' => array(),
			'label' => array(
				'for' => true
			),
			'code' => array(),
			'select' => array(
				'class' => true,
				'name' => true,
				'id' => true,
				'style' => true
			),
			'option' => array(
				'value' => true,
				'selected' => true
			),
			'textarea' => array(
				'rows' => true,
				'name' => true,
				'class' => true
			)
			);
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object \flutterwave\payment_forms\Payment_Forms
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// GETTERS

	/**
	 * Returns the fee settings save or the default values.
	 *
	 * @return array
	 */
	public function get_fees() {
		$ret = [];
		$ret['prc'] = intval( floatval( esc_attr( get_option( 'prc', PFF_FLUTTERWAVE_PERCENTAGE ) ) ) * 100 ) / 10000;
		$ret['ths'] = intval( floatval( esc_attr( get_option( 'ths', PFF_FLUTTERWAVE_CROSSOVER_TOTAL ) ) ) * 100 );
		$ret['adc'] = intval( floatval( esc_attr( get_option( 'adc', PFF_FLUTTERWAVE_ADDITIONAL_CHARGE ) ) ) * 100 );
		$ret['cap'] = intval( floatval( esc_attr( get_option( 'cap', PFF_FLUTTERWAVE_LOCAL_CAP ) ) ) * 100 );
		return $ret;
	}

	/**
	 * Gets the public key from the settings.
	 *
	 * @return string
	 */
	public function get_public_key() {
		$mode =  esc_attr( get_option( 'mode' ) );
		if ( 'test' === $mode ) {
			$key = esc_attr( get_option( 'tpk', '' ) );
		} else {
			$key = esc_attr( get_option( 'lpk', '' ) );
		}
		return $key;
	}

	/**
	 * Fetch an array of the payments by the form ID.
	 *
	 * @param integer $form_id
	 * @param array $args
	 * @return array
	 */
	public function get_payments_by_id( $form_id = 0, $args = array() ) {
        global $wpdb;
		$results = array();
		if ( 0 === $form_id ) {
			return $results;
		}

		$defaults = array(
			'paid'     => '1', 
			'order'    => 'desc',
			'orderby'  => 'created_at',
		);
		$args  = wp_parse_args( $args, $defaults );
        $table = esc_sql( $wpdb->prefix . PFF_FLUTTERWAVE_TABLE );
		$order = strtoupper( $args['order'] );

		// Whitelist sort direction.
		if ( 'ASC' !== $order ) {
			$order = 'DESC';
		}

		// Whitelist sortable columns.
		$allowed_orderby = array( 'created_at', 'paid_at', 'id', 'amount', 'email' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

		// phpcs:disable WordPress.DB
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE post_id = %d AND paid = %s ORDER BY `{$orderby}` {$order}",
				$form_id,
				$args['paid']
			)
		);
		// phpcs:enable

		return $results;
	}

	/**
	 * Gets the payments count for the current form.
	 *
	 * @param int|string $form_id
	 * @return int
	 */
	public function get_payments_count( $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;
		$num   = wp_cache_get( 'form_payments_' . $form_id, 'pff_flutterwave' );
		if ( false === $num ) {

			$table = esc_sql( $table );
			// phpcs:disable WordPress.DB
			$num = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE post_id = %d AND paid = '1'",
					$form_id
				)
			);
			// phpcs:enable

			wp_cache_set( 'form_payments_' . $form_id, $num, 'pff_flutterwave', 60*5 );
		}
		return $num;
	}

	/**
	 * Returns an array | string of the countries
	 *
	 * @param boolean $implode
	 * @return array|string
	 */
	public function get_countries( $implode = false ) {
		$countries = [
			esc_html__( "Afghanistan", 'pff-flutterwave' ),
			esc_html__( "Albania", 'pff-flutterwave' ),
			esc_html__( "Algeria", 'pff-flutterwave' ),
			esc_html__( "American Samoa", 'pff-flutterwave' ),
			esc_html__( "Andorra", 'pff-flutterwave' ),
			esc_html__( "Angola", 'pff-flutterwave' ),
			esc_html__( "Anguilla", 'pff-flutterwave' ),
			esc_html__( "Antarctica", 'pff-flutterwave' ),
			esc_html__( "Antigua and Barbuda", 'pff-flutterwave' ),
			esc_html__( "Argentina", 'pff-flutterwave' ),
			esc_html__( "Armenia", 'pff-flutterwave' ),
			esc_html__( "Aruba", 'pff-flutterwave' ),
			esc_html__( "Australia", 'pff-flutterwave' ),
			esc_html__( "Austria", 'pff-flutterwave' ),
			esc_html__( "Azerbaijan", 'pff-flutterwave' ),
			esc_html__( "Bahamas", 'pff-flutterwave' ),
			esc_html__( "Bahrain", 'pff-flutterwave' ),
			esc_html__( "Bangladesh", 'pff-flutterwave' ),
			esc_html__( "Barbados", 'pff-flutterwave' ),
			esc_html__( "Belarus", 'pff-flutterwave' ),
			esc_html__( "Belgium", 'pff-flutterwave' ),
			esc_html__( "Belize", 'pff-flutterwave' ),
			esc_html__( "Benin", 'pff-flutterwave' ),
			esc_html__( "Bermuda", 'pff-flutterwave' ),
			esc_html__( "Bhutan", 'pff-flutterwave' ),
			esc_html__( "Bolivia", 'pff-flutterwave' ),
			esc_html__( "Bosnia and Herzegovina", 'pff-flutterwave' ),
			esc_html__( "Botswana", 'pff-flutterwave' ),
			esc_html__( "Bouvet Island", 'pff-flutterwave' ),
			esc_html__( "Brazil", 'pff-flutterwave' ),
			esc_html__( "British Indian Ocean Territory", 'pff-flutterwave' ),
			esc_html__( "Brunei Darussalam", 'pff-flutterwave' ),
			esc_html__( "Bulgaria", 'pff-flutterwave' ),
			esc_html__( "Burkina Faso", 'pff-flutterwave' ),
			esc_html__( "Burundi", 'pff-flutterwave' ),
			esc_html__( "Cambodia", 'pff-flutterwave' ),
			esc_html__( "Cameroon", 'pff-flutterwave' ),
			esc_html__( "Canada", 'pff-flutterwave' ),
			esc_html__( "Cape Verde", 'pff-flutterwave' ),
			esc_html__( "Cayman Islands", 'pff-flutterwave' ),
			esc_html__( "Central African Republic", 'pff-flutterwave' ),
			esc_html__( "Chad", 'pff-flutterwave' ),
			esc_html__( "Chile", 'pff-flutterwave' ),
			esc_html__( "China", 'pff-flutterwave' ),
			esc_html__( "Christmas Island", 'pff-flutterwave' ),
			esc_html__( "Cocos (Keeling) Islands", 'pff-flutterwave' ),
			esc_html__( "Colombia", 'pff-flutterwave' ),
			esc_html__( "Comoros", 'pff-flutterwave' ),
			esc_html__( "Congo", 'pff-flutterwave' ),
			esc_html__( "Congo, The Democratic Republic of The", 'pff-flutterwave' ),
			esc_html__( "Cook Islands", 'pff-flutterwave' ),
			esc_html__( "Costa Rica", 'pff-flutterwave' ),
			esc_html__( "Cote D'ivoire", 'pff-flutterwave' ),
			esc_html__( "Croatia", 'pff-flutterwave' ),
			esc_html__( "Cuba", 'pff-flutterwave' ),
			esc_html__( "Cyprus", 'pff-flutterwave' ),
			esc_html__( "Czech Republic", 'pff-flutterwave' ),
			esc_html__( "Denmark", 'pff-flutterwave' ),
			esc_html__( "Djibouti", 'pff-flutterwave' ),
			esc_html__( "Dominica", 'pff-flutterwave' ),
			esc_html__( "Dominican Republic", 'pff-flutterwave' ),
			esc_html__( "Ecuador", 'pff-flutterwave' ),
			esc_html__( "Egypt", 'pff-flutterwave' ),
			esc_html__( "El Salvador", 'pff-flutterwave' ),
			esc_html__( "Equatorial Guinea", 'pff-flutterwave' ),
			esc_html__( "Eritrea", 'pff-flutterwave' ),
			esc_html__( "Estonia", 'pff-flutterwave' ),
			esc_html__( "Ethiopia", 'pff-flutterwave' ),
			esc_html__( "Falkland Islands (Malvinas)", 'pff-flutterwave' ),
			esc_html__( "Faroe Islands", 'pff-flutterwave' ),
			esc_html__( "Fiji", 'pff-flutterwave' ),
			esc_html__( "Finland", 'pff-flutterwave' ),
			esc_html__( "France", 'pff-flutterwave' ),
			esc_html__( "French Guiana", 'pff-flutterwave' ),
			esc_html__( "French Polynesia", 'pff-flutterwave' ),
			esc_html__( "French Southern Territories", 'pff-flutterwave' ),
			esc_html__( "Gabon", 'pff-flutterwave' ),
			esc_html__( "Gambia", 'pff-flutterwave' ),
			esc_html__( "Georgia", 'pff-flutterwave' ),
			esc_html__( "Germany", 'pff-flutterwave' ),
			esc_html__( "Ghana", 'pff-flutterwave' ),
			esc_html__( "Gibraltar", 'pff-flutterwave' ),
			esc_html__( "Greece", 'pff-flutterwave' ),
			esc_html__( "Greenland", 'pff-flutterwave' ),
			esc_html__( "Grenada", 'pff-flutterwave' ),
			esc_html__( "Guadeloupe", 'pff-flutterwave' ),
			esc_html__( "Guam", 'pff-flutterwave' ),
			esc_html__( "Guatemala", 'pff-flutterwave' ),
			esc_html__( "Guinea", 'pff-flutterwave' ),
			esc_html__( "Guinea-bissau", 'pff-flutterwave' ),
			esc_html__( "Guyana", 'pff-flutterwave' ),
			esc_html__( "Haiti", 'pff-flutterwave' ),
			esc_html__( "Heard Island and Mcdonald Islands", 'pff-flutterwave' ),
			esc_html__( "Holy See (Vatican City State)", 'pff-flutterwave' ),
			esc_html__( "Honduras", 'pff-flutterwave' ),
			esc_html__( "Hong Kong", 'pff-flutterwave' ),
			esc_html__( "Hungary", 'pff-flutterwave' ),
			esc_html__( "Iceland", 'pff-flutterwave' ),
			esc_html__( "India", 'pff-flutterwave' ),
			esc_html__( "Indonesia", 'pff-flutterwave' ),
			esc_html__( "Iran, Islamic Republic of", 'pff-flutterwave' ),
			esc_html__( "Iraq", 'pff-flutterwave' ),
			esc_html__( "Ireland", 'pff-flutterwave' ),
			esc_html__( "Israel", 'pff-flutterwave' ),
			esc_html__( "Italy", 'pff-flutterwave' ),
			esc_html__( "Jamaica", 'pff-flutterwave' ),
			esc_html__( "Japan", 'pff-flutterwave' ),
			esc_html__( "Jordan", 'pff-flutterwave' ),
			esc_html__( "Kazakhstan", 'pff-flutterwave' ),
			esc_html__( "Kenya", 'pff-flutterwave' ),
			esc_html__( "Kiribati", 'pff-flutterwave' ),
			esc_html__( "Korea, Democratic People's Republic of", 'pff-flutterwave' ),
			esc_html__( "Korea, Republic of", 'pff-flutterwave' ),
			esc_html__( "Kuwait", 'pff-flutterwave' ),
			esc_html__( "Kyrgyzstan", 'pff-flutterwave' ),
			esc_html__( "Lao People's Democratic Republic", 'pff-flutterwave' ),
			esc_html__( "Latvia", 'pff-flutterwave' ),
			esc_html__( "Lebanon", 'pff-flutterwave' ),
			esc_html__( "Lesotho", 'pff-flutterwave' ),
			esc_html__( "Liberia", 'pff-flutterwave' ),
			esc_html__( "Libyan Arab Jamahiriya", 'pff-flutterwave' ),
			esc_html__( "Liechtenstein", 'pff-flutterwave' ),
			esc_html__( "Lithuania", 'pff-flutterwave' ),
			esc_html__( "Luxembourg", 'pff-flutterwave' ),
			esc_html__( "Macao", 'pff-flutterwave' ),
			esc_html__( "Macedonia, The Former Yugoslav Republic of", 'pff-flutterwave' ),
			esc_html__( "Madagascar", 'pff-flutterwave' ),
			esc_html__( "Malawi", 'pff-flutterwave' ),
			esc_html__( "Malaysia", 'pff-flutterwave' ),
			esc_html__( "Maldives", 'pff-flutterwave' ),
			esc_html__( "Mali", 'pff-flutterwave' ),
			esc_html__( "Malta", 'pff-flutterwave' ),
			esc_html__( "Marshall Islands", 'pff-flutterwave' ),
			esc_html__( "Martinique", 'pff-flutterwave' ),
			esc_html__( "Mauritania", 'pff-flutterwave' ),
			esc_html__( "Mauritius", 'pff-flutterwave' ),
			esc_html__( "Mayotte", 'pff-flutterwave' ),
			esc_html__( "Mexico", 'pff-flutterwave' ),
			esc_html__( "Micronesia, Federated States of", 'pff-flutterwave' ),
			esc_html__( "Moldova, Republic of", 'pff-flutterwave' ),
			esc_html__( "Monaco", 'pff-flutterwave' ),
			esc_html__( "Mongolia", 'pff-flutterwave' ),
			esc_html__( "Montserrat", 'pff-flutterwave' ),
			esc_html__( "Morocco", 'pff-flutterwave' ),
			esc_html__( "Mozambique", 'pff-flutterwave' ),
			esc_html__( "Myanmar", 'pff-flutterwave' ),
			esc_html__( "Namibia", 'pff-flutterwave' ),
			esc_html__( "Nauru", 'pff-flutterwave' ),
			esc_html__( "Nepal", 'pff-flutterwave' ),
			esc_html__( "Netherlands", 'pff-flutterwave' ),
			esc_html__( "Netherlands Antilles", 'pff-flutterwave' ),
			esc_html__( "New Caledonia", 'pff-flutterwave' ),
			esc_html__( "New Zealand", 'pff-flutterwave' ),
			esc_html__( "Nicaragua", 'pff-flutterwave' ),
			esc_html__( "Niger", 'pff-flutterwave' ),
			esc_html__( "Nigeria", 'pff-flutterwave' ),
			esc_html__( "Niue", 'pff-flutterwave' ),
			esc_html__( "Norfolk Island", 'pff-flutterwave' ),
			esc_html__( "Northern Mariana Islands", 'pff-flutterwave' ),
			esc_html__( "Norway", 'pff-flutterwave' ),
			esc_html__( "Oman", 'pff-flutterwave' ),
			esc_html__( "Pakistan", 'pff-flutterwave' ),
			esc_html__( "Palau", 'pff-flutterwave' ),
			esc_html__( "Palestinian Territory, Occupied", 'pff-flutterwave' ),
			esc_html__( "Panama", 'pff-flutterwave' ),
			esc_html__( "Papua New Guinea", 'pff-flutterwave' ),
			esc_html__( "Paraguay", 'pff-flutterwave' ),
			esc_html__( "Peru", 'pff-flutterwave' ),
			esc_html__( "Philippines", 'pff-flutterwave' ),
			esc_html__( "Pitcairn", 'pff-flutterwave' ),
			esc_html__( "Poland", 'pff-flutterwave' ),
			esc_html__( "Portugal", 'pff-flutterwave' ),
			esc_html__( "Puerto Rico", 'pff-flutterwave' ),
			esc_html__( "Qatar", 'pff-flutterwave' ),
			esc_html__( "Reunion", 'pff-flutterwave' ),
			esc_html__( "Romania", 'pff-flutterwave' ),
			esc_html__( "Russian Federation", 'pff-flutterwave' ),
			esc_html__( "Rwanda", 'pff-flutterwave' ),
			esc_html__( "Saint Helena", 'pff-flutterwave' ),
			esc_html__( "Saint Kitts and Nevis", 'pff-flutterwave' ),
			esc_html__( "Saint Lucia", 'pff-flutterwave' ),
			esc_html__( "Saint Pierre and Miquelon", 'pff-flutterwave' ),
			esc_html__( "Saint Vincent and The Grenadines", 'pff-flutterwave' ),
			esc_html__( "Samoa", 'pff-flutterwave' ),
			esc_html__( "San Marino", 'pff-flutterwave' ),
			esc_html__( "Sao Tome and Principe", 'pff-flutterwave' ),
			esc_html__( "Saudi Arabia", 'pff-flutterwave' ),
			esc_html__( "Senegal", 'pff-flutterwave' ),
			esc_html__( "Serbia and Montenegro", 'pff-flutterwave' ),
			esc_html__( "Seychelles", 'pff-flutterwave' ),
			esc_html__( "Sierra Leone", 'pff-flutterwave' ),
			esc_html__( "Singapore", 'pff-flutterwave' ),
			esc_html__( "Slovakia", 'pff-flutterwave' ),
			esc_html__( "Slovenia", 'pff-flutterwave' ),
			esc_html__( "Solomon Islands", 'pff-flutterwave' ),
			esc_html__( "Somalia", 'pff-flutterwave' ),
			esc_html__( "South Africa", 'pff-flutterwave' ),
			esc_html__( "South Georgia and The South Sandwich Islands", 'pff-flutterwave' ),
			esc_html__( "Spain", 'pff-flutterwave' ),
			esc_html__( "Sri Lanka", 'pff-flutterwave' ),
			esc_html__( "Sudan", 'pff-flutterwave' ),
			esc_html__( "Suriname", 'pff-flutterwave' ),
			esc_html__( "Svalbard and Jan Mayen", 'pff-flutterwave' ),
			esc_html__( "Swaziland", 'pff-flutterwave' ),
			esc_html__( "Sweden", 'pff-flutterwave' ),
			esc_html__( "Switzerland", 'pff-flutterwave' ),
			esc_html__( "Syrian Arab Republic", 'pff-flutterwave' ),
			esc_html__( "Taiwan, Province of China", 'pff-flutterwave' ),
			esc_html__( "Tajikistan", 'pff-flutterwave' ),
			esc_html__( "Tanzania, United Republic of", 'pff-flutterwave' ),
			esc_html__( "Thailand", 'pff-flutterwave' ),
			esc_html__( "Timor-leste", 'pff-flutterwave' ),
			esc_html__( "Togo", 'pff-flutterwave' ),
			esc_html__( "Tokelau", 'pff-flutterwave' ),
			esc_html__( "Tonga", 'pff-flutterwave' ),
			esc_html__( "Trinidad and Tobago", 'pff-flutterwave' ),
			esc_html__( "Tunisia", 'pff-flutterwave' ),
			esc_html__( "Turkey", 'pff-flutterwave' ),
			esc_html__( "Turkmenistan", 'pff-flutterwave' ),
			esc_html__( "Turks and Caicos Islands", 'pff-flutterwave' ),
			esc_html__( "Tuvalu", 'pff-flutterwave' ),
			esc_html__( "Uganda", 'pff-flutterwave' ),
			esc_html__( "Ukraine", 'pff-flutterwave' ),
			esc_html__( "United Arab Emirates", 'pff-flutterwave' ),
			esc_html__( "United Kingdom", 'pff-flutterwave' ),
			esc_html__( "United States", 'pff-flutterwave' ),
			esc_html__( "United States Minor Outlying Islands", 'pff-flutterwave' ),
			esc_html__( "Uruguay", 'pff-flutterwave' ),
			esc_html__( "Uzbekistan", 'pff-flutterwave' ),
			esc_html__( "Vanuatu", 'pff-flutterwave' ),
			esc_html__( "Venezuela", 'pff-flutterwave' ),
			esc_html__( "Viet Nam", 'pff-flutterwave' ),
			esc_html__( "Virgin Islands; British", 'pff-flutterwave' ),
			esc_html__( "Virgin Islands; U.S.", 'pff-flutterwave' ),
			esc_html__( "Wallis and Futuna", 'pff-flutterwave' ),
			esc_html__( "Western Sahara", 'pff-flutterwave' ),
			esc_html__( "Yemen", 'pff-flutterwave' ),
			esc_html__( "Zambia", 'pff-flutterwave' ),
			esc_html__( "Zimbabwe", 'pff-flutterwave' ),
		];	
		if ( $implode ) {
			$countries = implode( ',', $countries );
		}
		return $countries;
	}

	/**
	 * Returns the states available.
	 *
	 * @param boolean $implode
	 * @return array|string
	 */
	public function get_states( $implode = false ) {
		$states = [
			esc_html__( 'Abia', 'pff-flutterwave' ),
			esc_html__( 'Adamawa', 'pff-flutterwave' ),
			esc_html__( 'Akwa Ibom', 'pff-flutterwave' ),
			esc_html__( 'Anambra', 'pff-flutterwave' ),
			esc_html__( 'Bauchi', 'pff-flutterwave' ),
			esc_html__( 'Bayelsa', 'pff-flutterwave' ),
			esc_html__( 'Benue', 'pff-flutterwave' ),
			esc_html__( 'Borno', 'pff-flutterwave' ),
			esc_html__( 'Cross River', 'pff-flutterwave' ),
			esc_html__( 'Delta', 'pff-flutterwave' ),
			esc_html__( 'Ebonyi', 'pff-flutterwave' ),
			esc_html__( 'Edo', 'pff-flutterwave' ),
			esc_html__( 'Ekiti', 'pff-flutterwave' ),
			esc_html__( 'Enugu', 'pff-flutterwave' ),
			esc_html__( 'FCT', 'pff-flutterwave' ),
			esc_html__( 'Gombe', 'pff-flutterwave' ),
			esc_html__( 'Imo', 'pff-flutterwave' ),
			esc_html__( 'Jigawa', 'pff-flutterwave' ),
			esc_html__( 'Kaduna', 'pff-flutterwave' ),
			esc_html__( 'Kano', 'pff-flutterwave' ),
			esc_html__( 'Katsina', 'pff-flutterwave' ),
			esc_html__( 'Kebbi', 'pff-flutterwave' ),
			esc_html__( 'Kogi', 'pff-flutterwave' ),
			esc_html__( 'Kwara', 'pff-flutterwave' ),
			esc_html__( 'Lagos', 'pff-flutterwave' ),
			esc_html__( 'Nasarawa', 'pff-flutterwave' ),
			esc_html__( 'Niger', 'pff-flutterwave' ),
			esc_html__( 'Ogun', 'pff-flutterwave' ),
			esc_html__( 'Ondo', 'pff-flutterwave' ),
			esc_html__( 'Osun', 'pff-flutterwave' ),
			esc_html__( 'Oyo', 'pff-flutterwave' ),
			esc_html__( 'Plateau', 'pff-flutterwave' ),
			esc_html__( 'Rivers', 'pff-flutterwave' ),
			esc_html__( 'Sokoto', 'pff-flutterwave' ),
			esc_html__( 'Taraba', 'pff-flutterwave' ),
			esc_html__( 'Yobe', 'pff-flutterwave' ),
			esc_html__( 'Zamfara', 'pff-flutterwave' ),
		];
		if ( $implode ) {
			$states = implode( ',', $states );
		}
		return $states;
	}

	/**
	 * Returns the meta fields and their default values.
	 *
	 * @return array
	 */
	public function get_meta_defaults() {
		return $this->defaults;
	}

	/**
	 * Returns the allowed HTML for wp_kses()
	 *
	 * @return array
	 */
	public function get_allowed_html() {
		return $this->allowed_html;
	}

	/**
	 * Retrieve the user's IP address.
	 *
	 * @return string User's IP address.
	 */
	public function get_the_user_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
	
	/**
	 * Get the DB records by the transaction code supplied.
	 *
	 * @param string $code
	 * @return object
	 */
	public function get_db_record( $code, $column = 'txn_code' ) {
		global $wpdb;
		$return = false;

		// Whitelist the column name — never interpolate a caller-supplied identifier.
		$allowed_columns = array( 'txn_code', 'txn_code_2' );
		if ( ! in_array( $column, $allowed_columns, true ) ) {
			$column = 'txn_code';
		}

		$table = esc_sql( $wpdb->prefix . PFF_FLUTTERWAVE_TABLE );

		// phpcs:disable WordPress.DB
		$record = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE `{$column}` = %s",
				$code
			),
			'OBJECT'
		);
		// phpcs:enable

		if ( ! empty( $record ) && isset( $record[0] ) ) {
			$return = $record[0];
		}
		return $return;
	}

	// FUNCTIONS

	/**
	 * Gets the current forms meta fields values and set the defaults if needed.
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	public function parse_meta_values( $post ) {
		$new_values = [];
		foreach ( $this->defaults as $key => $default ) {
			$value = get_post_meta( $post->ID, '_' . $key, true );
			if ( false !== $value && ! empty( $value ) ) {
				$new_values[ $key ] = $value;
			}
		}

		$meta = wp_parse_args( $new_values, $this->defaults );
		if ( '' === $meta['inventory'] || '0' === $meta['inventory'] ) {
			if ( '' !== $meta['sold'] ) {
				$meta['inventory'] = $meta['sold'];
			} else {
				$meta['inventory'] = '1';
			}
		}

		// Strip any text from the variable amount field.
		if ( isset( $meta['usevariableamount'] ) && is_string( $meta['usevariableamount'] ) ) {
			$meta['usevariableamount'] = (int) $meta['usevariableamount'];
		}

		$meta['minimum']   = (int) $meta['minimum'];
		//$meta['txncharge'] = floatval( $meta['txncharge'] );
		return $meta;
	}

	/**
	 * Take an array of the submitted form values and formats it for a flutterwave request.
	 *
	 * @param array $metadata
	 * @return void
	 */
	public function format_meta_as_custom_fields( $metadata ) {
		$fields = array();

		foreach ( $metadata as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			switch ( $key ) {
				case 'pf-fname':
					$fields[] = array(
						'display_name'  => esc_html__( 'Full Name', 'pff-flutterwave' ),
						'variable_name' => 'Full_Name',
						'type'          => 'text',
						'value'         => $value,
					);
					break;

				case 'pf-plancode':
					$fields[] = array(
						'display_name'  => esc_html__( 'Plan', 'pff-flutterwave' ),
						'variable_name' => 'Plan',
						'type'          => 'text',
						'value'         => $value,
					);
					break;

				case 'pf-vname':
					$fields[] = array(
						'display_name'  => esc_html__( 'Payment Option', 'pff-flutterwave' ),
						'variable_name' => 'Payment Option',
						'type'          => 'text',
						'value'         => $value,
					);
					break;

				case 'pf-interval':
					$fields[] = array(
						'display_name'  => esc_html__( 'Plan Interval', 'pff-flutterwave' ),
						'variable_name' => 'Plan Interval',
						'type'          => 'text',
						'value'         => $value,
					);
					break;

				case 'pf-quantity':
					$fields[] = array(
						'display_name'  => esc_html__( 'Quantity', 'pff-flutterwave' ),
						'variable_name' => 'Quantity',
						'type'          => 'text',
						'value'         => $value,
					);
					break;

				default:
					$display_name = ucwords( str_replace( array( '_', '-', 'pf' ), ' ', $key ) );
					$fields[] = array(
						'display_name'  => $display_name,
						'variable_name' => $key,
						'type'          => 'text',
						'value'         => (string) $value,
					);
					break;
			}
		}
		return $fields;
	}

	/**
	 * Formats the metadata for output on the retry form page.
	 *
	 * @param string $data
	 * @return string
	 */
	public function format_meta_as_display_fields( $data ) {
		$new  = json_decode( $data );
		$text = '';
		
		if ( is_array( $new ) && array_key_exists( 0, $new ) ) {
			foreach ( $new as $item ) {
				if ( 'text' === $item->type ) {
					$text .= sprintf(
						'<div class="span12 unit">
							<label class="label inline">%s:</label>
							<strong>%s</strong>
						</div>',
						esc_html( $item->display_name ),
						esc_html( $item->value )
					);
				} else {
					$text .= sprintf(
						'<div class="span12 unit">
							<label class="label inline">%s:</label>
							<strong><a target="_blank" href="%s">%s</a></strong>
						</div>',
						esc_html( $item->display_name ),
						esc_url( $item->value ),
						esc_html__( 'link', 'pff-flutterwave' )
					);
				}
			}
		} elseif ( is_object( $new ) ) {
			if ( count( get_object_vars( $new ) ) > 0 ) {
				foreach ( $new as $key => $item ) {
					$text .= sprintf(
						'<div class="span12 unit">
							<label class="label inline">%s:</label>
							<strong>%s</strong>
						</div>',
						esc_html( $key ),
						esc_html( $item )
					);
				}
			}
		}
		return $text;
	}

	/**
	 * Generate an HMAC signature for a retry-invoice code so the retry URL
	 * cannot be enumerated against the DB by random visitors.
	 *
	 * @param string $code
	 * @return string
	 */
	public function make_retry_token( $code ) {
		return hash_hmac( 'sha256', (string) $code, wp_salt( 'auth' ) );
	}

	/**
	 * Constant-time verify a retry signature against a code.
	 *
	 * @param string $code
	 * @param string $sig
	 * @return bool
	 */
	public function verify_retry_token( $code, $sig ) {
		if ( ! is_string( $sig ) || '' === $sig ) {
			return false;
		}
		return hash_equals( $this->make_retry_token( $code ), $sig );
	}

	/**
	 * Generate a new Flutterwave code.
	 *
	 * @param int $length Length of the code to generate. Default 10.
	 * @return string Generated code.
	 */
	public function generate_new_code( $length = 10 ) {
		// Use a CSPRNG so the tx_ref cannot be predicted or enumerated.
		try {
			$random_string = substr( bin2hex( random_bytes( (int) ceil( $length / 2 ) ) ), 0, $length );
		} catch ( \Exception $e ) {
			// random_bytes() will throw only if no CSPRNG is available — fall back to wp_generate_uuid4().
			$random_string = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, $length );
		}

		return time() . '_' . strtoupper( $random_string );
	}

	/**
	 * Check if the given code exists in the database.
	 *
	 * @param string $code The code to check.
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return bool True if the code exists, false otherwise.
	 */
	public function check_code( $code ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . PFF_FLUTTERWAVE_TABLE );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// phpcs:disable WordPress.DB
		$o_exist = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE txn_code = %s",
				$code
			)
		);
		// phpcs:enable

		return ( count( $o_exist ) > 0 );
	}


	/**
	 * Takes the amount and processes the "transactional" fees.
	 *
	 * @param integer $amount
	 * @return integer
	 */
	public function process_transaction_fees( $amount ) {
		$fees = $this->get_fees();
		$pc   = new Transaction_Fee(
			$fees['prc'],
			$fees['adc'],
			$fees['ths'],
			$fees['cap']
		);
		return $pc->add_for_ngn( $amount );
	}
}
<?php
/**
 * The main plugin class, this will return the and instance of the class.
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class.
 */
final class Payment_Forms {

	/**
	 * Holds class instance
	 *
	 * @var object \flutterwave\payment_forms\Payment_Forms
	 */
	protected static $instance = null;

	/**
	 * The package namespace for the plugin.
	 *
	 * @var string
	 */
	public $namespace = '\flutterwave\payment_forms\\';

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	public $plugin_name = PFF_FLUTTERWAVE_PLUGIN_NAME;

	/**
	 * The plugin version number.
	 *
	 * @var string
	 */
	public $version = PFF_FLUTTERWAVE_VERSION;

	/**
	 * Holds the array of classes key => object.
	 *
	 * @var array
	 */
	public $classes = array();

	/**
	 * Helpers functions for the custom payments.
	 *
	 * @var \flutterwave\payment_forms\Helpers
	 */
	public $helpers;

	/**
	 * Initialize the plugin by setting localization, filters, and
	 * administration functions.
	 *
	 * @access private
	 */
	private function __construct() {
		$this->set_variables();
		$this->include_classes();
		$this->init_hooks();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object \flutterwave\payment_forms\Payment_Forms
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Sets our plugin variables.
	 *
	 * @return void
	 */
	private function set_variables() {
		$this->classes = array(
			'activation'           => '',
			'setup'                => 'Setup',
			'helpers'              => '',
			'settings'             => 'Settings',
			'forms-list'           => 'Forms_List',
			'submissions'          => 'Submissions',
			'payment-history'      => 'Payment_History',
			'forms-update'         => 'Forms_Update',
			'tinymce-plugin'       => 'TinyMCE_Plugin',
			'form-shortcode'       => 'Form_Shortcode',
			'field-shortcodes'     => 'Field_Shortcodes',
			'api'                  => '',
			'request-plan'         => 'Request_Plan',
			'request-subscription' => 'Request_Subscription',
			'transaction-verify'   => 'Transaction_Verify',
			'form-submit'          => 'Form_Submit',
			'transaction-fee'      => '',
			'confirm-payment'      => 'Confirm_Payment',
			'webhook'              => 'Webhook',
			'email'                => '',
			'email-invoice'        => 'Email_Invoice',
			'email-receipt'        => 'Email_Receipt',
			'email-receipt-owner'  => 'Email_Receipt_Owner',
			'retry-submit'         => 'Retry_Submit',
		);
	}

	/**
	 * Includes our class files
	 *
	 * @return void
	 */
	private function include_classes() {
		foreach ( $this->classes as $key => $name ) {
			include_once PFF_FLUTTERWAVE_PLUGIN_PATH . '/includes/classes/class-' . $key . '.php';
			if ( '' !== $name ) {
				$className = $this->namespace . $name;
				$this->classes[$key] = new $className();
			}
		}
		include_once PFF_FLUTTERWAVE_PLUGIN_PATH . '/includes/classes/deprecated.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( PFF_FLUTTERWAVE_MAIN_FILE, array( '\flutterwave\payment_forms\Activation', 'install' ) );
		add_action( 'plugins_loaded', array( '\flutterwave\payment_forms\Activation', 'maybe_run_upgrades' ) );
	}
}

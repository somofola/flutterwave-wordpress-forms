<?php
/*
  Plugin Name:  Payment Forms for Flutterwave
  Plugin URI:   https://github.com/somofola/flutterwave-wordpress-forms
  Description:  Payment Forms for Flutterwave allows you create forms that will be used to bill clients for goods and services via Flutterwave.
  Version:      1.0.7
  Author:       Shola Omofola
  Author URI:   https://github.com/somofola
  License:      GPL-2.0+
  License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
  Text Domain:  pff-flutterwave
*/
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
define( 'PFF_FLUTTERWAVE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PFF_FLUTTERWAVE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PFF_FLUTTERWAVE_MAIN_FILE', __FILE__ );
define( 'PFF_FLUTTERWAVE_VERSION', '1.0.7' );
define( 'PFF_FLUTTERWAVE_TABLE', 'flutterwave_forms_payments' );
define( 'PFF_FLUTTERWAVE_PLUGIN_BASENAME', plugin_basename(__FILE__) );
define( 'PFF_FLUTTERWAVE_PLUGIN_NAME', 'pff-flutterwave' );

// Transaction definitions.
// NOTE: Flutterwave's local NGN fee is 1.4% capped at NGN 2,000 (no flat add-on below threshold),
// while Paystack adds NGN 100 above a NGN 2,500 threshold. We keep the same configurable shape
// (percentage / threshold / additional / cap) so merchants can model whichever Flutterwave plan
// applies to their account (local / international / mobile money). Defaults below reflect a
// reasonable NGN local-card baseline.
define( 'PFF_FLUTTERWAVE_PERCENTAGE', 1.4 );
define( 'PFF_FLUTTERWAVE_CROSSOVER_TOTAL', 2500 );
define( 'PFF_FLUTTERWAVE_ADDITIONAL_CHARGE', 0 );
define( 'PFF_FLUTTERWAVE_LOCAL_CAP', 2000 );

include_once PFF_FLUTTERWAVE_PLUGIN_PATH . '/includes/classes/class-flutterwave-forms.php';

/**
 * Returns an instance of the Flutterwave Payment forms Object
 *
 * @return object \flutterwave\payment_forms\Payment_Forms
 */
function pff_flutterwave() {
	return \flutterwave\payment_forms\Payment_Forms::get_instance();
}
$_GLOBAL['pff_flutterwave'] = pff_flutterwave();

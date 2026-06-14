<?php
/**
 * The deprecated functions that might be in use.
 */

/**
 * The old plugin initilizer.
 *
 * @return \flutterwave\payment_forms\Payment_Forms
 * @deprecated 3.4.2
 */
function kkd_pff_flutterwave_run_flutterwave_forms() {
    return \flutterwave\payment_forms\Payment_Forms::get_instance();
}
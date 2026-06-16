<?php
/**
 * Plugin settings page.
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Settings class.
 */
class Settings {

	/**
	 * Holds the array of settings fields.
	 *
	 * @var array
	 */
	private $fields = array();

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->fields = array(
			'general' => array(
				'mode' => array(
					'title'   => esc_html__( 'Mode', 'pff-flutterwave' ),
					'type'    => 'select',
					'default' => 'test',
				),
				'tsk' => array(
					'title'   => esc_html__( 'Test Secret Key', 'pff-flutterwave' ),
					'type'    => 'password',
					'default' => '',
				),
				'tpk' => array(
					'title'   => esc_html__( 'Test Public Key', 'pff-flutterwave' ),
					'type'    => 'text',
					'default' => '',
				),
				'tsh' => array(
					'title'   => esc_html__( 'Test Webhook Secret Hash', 'pff-flutterwave' ),
					'type'    => 'password',
					'default' => '',
				),
				'lsk' => array(
					'title'   => esc_html__( 'Live Secret Key', 'pff-flutterwave' ),
					'type'    => 'password',
					'default' => '',
				),
				'lpk' => array(
					'title'   => esc_html__( 'Live Public Key', 'pff-flutterwave' ),
					'type'    => 'text',
					'default' => '',
				),
				'lsh' => array(
					'title'   => esc_html__( 'Live Webhook Secret Hash', 'pff-flutterwave' ),
					'type'    => 'password',
					'default' => '',
				),
			),
			'fees' => array(
				'prc' => array(
					'title'   => esc_html__( 'Percentage', 'pff-flutterwave' ),
					'type'    => 'text',
					'default' => 1.4,
				),
				'ths' => array(
					'title'   => wp_kses_post( __( 'Threshold <br> <small>(amount above which an extra flat fee is added, if your Flutterwave plan defines one)</small>', 'pff-flutterwave' ) ),
					'type'    => 'text',
					'default' => 2500,
				),
				'adc' => array(
					'title'   => wp_kses_post( __( 'Additional Charge <br> <small>(flat fee added when transaction amount is above threshold; set 0 if Flutterwave does not charge a flat fee for your currency)</small>', 'pff-flutterwave' ) ),
					'type'    => 'text',
					'default' => 0,
				),
				'cap' => array(
					'title'   => wp_kses_post( __( 'Cap <br> <small>(maximum charge Flutterwave can charge on your transactions; NGN local cards capped at 2000)</small>', 'pff-flutterwave' ) ),
					'type'    => 'text',
					'default' => 2000,
				),
			),
		);
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_menu', [ $this, 'register_settings_fields' ] );
	}

	/**
	 * Registers our settings sub page under the Flutterwave Forms menu item.
	 */
	public function register_settings_page() {
		add_submenu_page( 'edit.php?post_type=flutterwave_form', esc_html__( 'Settings', 'pff-flutterwave' ), esc_html__( 'Settings', 'pff-flutterwave' ), 'manage_options', 'settings', [ $this, 'output_settings_page' ] );
	}

	/**
	 * Registers our Settings fields with the WP API.
	 */
	public function register_settings_fields() {
		$fields = $this->get_settings_fields();
		foreach ( $fields as $group => $fields ) {
			foreach ( $fields as $field_key => $args ) {
				register_setting( 'pff-flutterwave-settings-group', $field_key, [ $this, 'sanitise_field' ] );
			}
		}
	}

	public function output_settings_page() {

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			add_settings_error(
				'pff_flutterwave_settings',
				'pff_flutterwave_settings_saved',
				esc_html__( 'Settings saved successfully.', 'pff-flutterwave' ),
				'success'
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flutterwave Forms Settings', 'pff-flutterwave' ); ?></h1>
			<?php settings_errors( 'pff_flutterwave_settings' ); ?>
			<h2><?php esc_html_e( 'API Keys Settings', 'pff-flutterwave' ); ?></h2>

			<span><?php echo wp_kses_post( __( 'Get your API Keys <a href="https://dashboard.flutterwave.com/dashboard/settings/apis" target="_blank">here</a>. The Webhook Secret Hash is configured under Settings &raquo; Webhooks.', 'pff-flutterwave' ) ); ?> </span>

			<p><?php echo wp_kses_post( sprintf( __( 'Webhook URL: <code>%s</code>', 'pff-flutterwave' ), esc_url( home_url( '/?pff_flutterwave_webhook=1' ) ) ) ); ?></p>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'pff-flutterwave-settings-group' );
					do_settings_sections( 'pff-flutterwave-settings-group' );
					$settings_fields = $this->get_settings_fields();
				?>
				<table class="form-table flutterwave_setting_page">
				<?php
					foreach ( $settings_fields['general'] as $key => $field ) {
						?>
						<tr valign="top">
							<th scope="row"><?php echo wp_kses_post( $field['title'] ); ?></th>
							<td>
							<?php if ( 'mode' === $key ) {
								$saved_val = get_option( 'mode', $field['default'] );
								?>
								<select class="form-control" name="<?php echo esc_attr( $key ); ?>" id="parent_id">
									<option value="test" <?php echo esc_attr( $this->is_option_selected( 'test', $saved_val ) ); ?>><?php esc_html_e( 'Test Mode', 'pff-flutterwave' ); ?></option>
									<option value="live" <?php echo esc_attr( $this->is_option_selected( 'live', $saved_val ) ); ?>><?php esc_html_e( 'Live Mode', 'pff-flutterwave' ); ?></option>
								</select>
							<?php } else { ?>
								<input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( $key, $field['default'] ) ); ?>" />
							<?php } ?>
							</td>
						</tr>
						<?php
					}
				?>
				</table>
				<hr>
				<table class="form-table flutterwave_setting_page" id="flutterwave_setting_fees">
					<h2><?php esc_html_e( 'Fees Settings', 'pff-flutterwave' ); ?></h2>
					<?php
					foreach ( $settings_fields['fees'] as $key => $field ) {
						?>
						<tr valign="top">
							<th scope="row"><?php echo wp_kses_post( $field['title'] ); ?></th>
							<td>
								<input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( $key, $field['default'] ) ); ?>" />
							</td>
						</tr>
						<?php
					}
					?>
				</table>

				<?php submit_button(); ?>

			</form>
		</div>
		<?php
	}

	public function get_settings_fields() {
		return apply_filters( 'pff_flutterwave_settings_fields', $this->fields );
	}

	public function is_option_selected( $value, $compare ) {
		return ( $value == $compare ) ? 'selected' : '';
	}

	private function sanitise_field( $value ) {
		return sanitize_text_field( $value );
	}
}

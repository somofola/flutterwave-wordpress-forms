<?php
/**
 * Plugin activation / DB install.
 *
 * @package    \flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Activation class.
 */
class Activation {

	/**
	 * Install Flutterwave DB Table
	 */
	public static function install() {
		global $wpdb;
		$table_name = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::create_tables( $table_name );
		self::maybe_upgrade( $table_name );
		update_option( 'pff_flutterwave_db_version', '1.4' );
	}

	/**
	 * Create the Flutterwave payments table.
	 */
	public static function create_tables( $table_name ) {
		global $wpdb;
		$query = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
				id int(11) NOT NULL AUTO_INCREMENT,
				post_id int(11) NOT NULL,
				user_id int(11) NOT NULL,
				email varchar(255) DEFAULT '' NOT NULL,
				metadata text,
				paid tinyint(1) NOT NULL DEFAULT 0,
				plan varchar(255) DEFAULT '' NOT NULL,
				txn_code varchar(64) DEFAULT '' NOT NULL,
				txn_code_2 varchar(64) DEFAULT '' NOT NULL,
				flw_ref varchar(64) DEFAULT '' NOT NULL,
				transaction_id varchar(64) DEFAULT '' NOT NULL,
				payment_type varchar(32) DEFAULT '' NOT NULL,
				amount decimal(11,2) NOT NULL DEFAULT 0.00,
				ip varchar(45) NOT NULL DEFAULT '',
				deleted_at datetime DEFAULT NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				paid_at timestamp NULL DEFAULT NULL,
				modified timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY post_id (post_id),
				KEY user_id (user_id),
				KEY transaction_id (transaction_id)
			) {$wpdb->get_charset_collate()};";
		dbDelta( $query );
	}

	/**
	 * Run any DB column additions for upgrades.
	 */
	public static function maybe_upgrade( $table_name ) {
		global $wpdb;

		$table_name = esc_sql( $table_name );
		$version    = get_option( 'pff_flutterwave_db_version', '1.0' );

		if ( version_compare( $version, '1.1', '<' ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY amount decimal(11,2) NOT NULL DEFAULT 0.00" );
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY ip varchar(45) NOT NULL DEFAULT ''" );
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY paid tinyint(1) NOT NULL DEFAULT 0" );
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY deleted_at datetime DEFAULT NULL" );
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY paid_at timestamp NULL DEFAULT NULL" );
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY modified timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
			$wpdb->query( "ALTER TABLE `{$table_name}` DROP INDEX id" );
		}

		if ( version_compare( $version, '1.2', '<' ) ) {
			$col_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
					DB_NAME,
					$table_name,
					'payment_type'
				)
			);
			if ( ! $col_exists ) {
				$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN payment_type varchar(32) NOT NULL DEFAULT '' AFTER transaction_id" );
			}
			update_option( 'pff_flutterwave_db_version', '1.2' );
		}

		if ( version_compare( $version, '1.4', '<' ) ) {
			$admin_email = get_option( 'admin_email' );
			$new_msg     = sprintf(
				/* translators: %s: support email */
				esc_html__( 'Thank you for your payment! A receipt has been sent to your email. If you have any questions or did not receive your receipt, contact support at %s and we will get back to you shortly.', 'pff-flutterwave' ),
				$admin_email
			);
			$like = $wpdb->esc_like( 'Thank you for paying' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$wpdb->postmeta}` SET meta_value = %s WHERE meta_key = %s AND meta_value LIKE %s",
					$new_msg,
					'_successmsg',
					$like
				)
			);
			update_option( 'pff_flutterwave_db_version', '1.4' );
		}
	}

	/**
	 * Idempotent upgrade runner — called on every request via plugins_loaded.
	 * Cheap: short-circuits when db_version already current.
	 */
	public static function maybe_run_upgrades() {
		global $wpdb;
		$current_target = '1.4';
		$installed      = get_option( 'pff_flutterwave_db_version', '1.0' );
		if ( version_compare( $installed, $current_target, '>=' ) ) {
			return;
		}
		$table_name = $wpdb->prefix . PFF_FLUTTERWAVE_TABLE;
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		self::create_tables( $table_name );
		self::maybe_upgrade( $table_name );
	}
}

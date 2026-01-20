<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Install database tables for Booking Package Tracker
 * 
 * Creates the following tables:
 * - bt_credits_ledger: Tracks credit additions and subtractions
 * - bt_bookings: Stores Cal.com booking snapshots
 */
function bt_install_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$ledger_table   = $wpdb->prefix . 'bt_credits_ledger';
	$bookings_table = $wpdb->prefix . 'bt_bookings';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Ledger table: tracks +credits and -credits
	$sql1 = "CREATE TABLE $ledger_table (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(190) NOT NULL,
		delta INT NOT NULL,
		source VARCHAR(50) NOT NULL,
		external_id VARCHAR(190) NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY email_idx (email),
		KEY source_idx (source),
		KEY external_idx (external_id)
	) $charset_collate;";

	// Bookings table: stores Cal booking snapshots
	$sql2 = "CREATE TABLE $bookings_table (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(190) NOT NULL,
		cal_booking_id VARCHAR(190) NOT NULL,
		status VARCHAR(50) NOT NULL DEFAULT 'created',
		start_time DATETIME NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY cal_booking_unique (cal_booking_id),
		KEY email_idx (email),
		KEY status_idx (status)
	) $charset_collate;";

	dbDelta($sql1);
	dbDelta($sql2);
}

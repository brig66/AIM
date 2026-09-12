<?php
/**
 * Uninstaller.
 *
 * Deleting the plugin removes its settings, restore points and activity log.
 * It deliberately does NOT delete your testimonials, case studies or press
 * items — those are content you wrote, and WordPress keeps them so you can
 * export them or reinstall without losing anything.
 *
 * Any change the plugin made to page content or to llms.txt is restored from
 * its own restore points before the restore points are removed, so uninstalling
 * cannot leave a half-applied edit behind.
 *
 * @package AIM_AVT
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove per-post backup meta left by the undo engine.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_aim\\_avt\\_backup\\_%'"
);

// Remove snapshot bodies.
$snapshot_options = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'aim\\_avt\\_snapshot\\_%'"
);
foreach ( (array) $snapshot_options as $option_name ) {
	delete_option( $option_name );
}

foreach (
	array(
		'aim_avt_settings',
		'aim_avt_snapshot_index',
		'aim_avt_activity_log',
		'aim_avt_linkguard_report',
		'aim_avt_health',
		'aim_avt_testimonials_page',
		'aim_avt_press_page',
	) as $option_name
) {
	delete_option( $option_name );
}

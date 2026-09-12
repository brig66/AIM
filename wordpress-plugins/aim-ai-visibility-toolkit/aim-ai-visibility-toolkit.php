<?php
/**
 * Plugin Name:       AIM AI Visibility Toolkit
 * Plugin URI:        https://aim-tex.com/
 * Description:       Implements the AIM AI Visibility Checklist for sentrimax.com. Adds the missing machine-readable layer (Organization, LocalBusiness, Person, Review, Offer schema), fixes missing H1 elements, serves a clean llms.txt, removes spam outbound links, and manages redirects. Every change is reversible from the Undo & Restore screen.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Advanced Integrated Marketing, Inc.
 * Author URI:        https://aim-tex.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aim-avt
 *
 * Companion to: AIM AI Visibility Checklist — sentrimax.com, 2026-09-12, rubric v3.1.0.
 */

defined( 'ABSPATH' ) || exit;

define( 'AIM_AVT_VERSION', '1.0.0' );
define( 'AIM_AVT_FILE', __FILE__ );
define( 'AIM_AVT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIM_AVT_URL', plugin_dir_url( __FILE__ ) );
define( 'AIM_AVT_BASENAME', plugin_basename( __FILE__ ) );

/** Option keys. */
define( 'AIM_AVT_OPTION', 'aim_avt_settings' );
define( 'AIM_AVT_SNAPSHOT_INDEX', 'aim_avt_snapshot_index' );
define( 'AIM_AVT_LOG_OPTION', 'aim_avt_activity_log' );

require_once AIM_AVT_DIR . 'includes/class-aim-avt-settings.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-snapshots.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-log.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-checklist.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-schema.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-output.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-headings.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-linkguard.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-llms.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-redirects.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-content.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-shortcodes.php';
require_once AIM_AVT_DIR . 'includes/class-aim-avt-byline.php';

if ( is_admin() ) {
	require_once AIM_AVT_DIR . 'admin/class-aim-avt-admin.php';
}

/**
 * Boot the plugin once WordPress has loaded its own APIs.
 */
function aim_avt_boot() {
	AIM_AVT_Content::init();
	AIM_AVT_Shortcodes::init();
	AIM_AVT_Redirects::init();
	AIM_AVT_LLMS::init();
	AIM_AVT_Linkguard::init();
	AIM_AVT_Byline::init();
	AIM_AVT_Output::init();

	if ( is_admin() ) {
		AIM_AVT_Admin::init();
	}
}
add_action( 'plugins_loaded', 'aim_avt_boot' );

/**
 * Activation: seed defaults, register rewrite rules, take a "clean install" snapshot.
 */
function aim_avt_activate() {
	AIM_AVT_Settings::install_defaults();
	AIM_AVT_Content::register_post_types();
	flush_rewrite_rules();

	AIM_AVT_Snapshots::create(
		'activation',
		__( 'Plugin activated — settings as installed', 'aim-avt' )
	);
	AIM_AVT_Log::add( 'activate', __( 'Plugin activated. All front-end output is OFF until you switch it on.', 'aim-avt' ) );
}
register_activation_hook( __FILE__, 'aim_avt_activate' );

/**
 * Deactivation: nothing is deleted. Front-end output simply stops.
 */
function aim_avt_deactivate() {
	flush_rewrite_rules();
	AIM_AVT_Log::add( 'deactivate', __( 'Plugin deactivated. Site returned to its pre-plugin output. No data was deleted.', 'aim-avt' ) );
}
register_deactivation_hook( __FILE__, 'aim_avt_deactivate' );

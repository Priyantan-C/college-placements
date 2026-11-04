<?php
/**
 * Plugin Name:       College Placements
 * Plugin URI:        https://github.com/Priyantan-C/college-placements
 * Description:       Placement charts and year-wise student placement tables for a college website. Upload the year's placement Excel file and the plugin builds the charts and tables.
 * Version:           1.3.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Priyantan-C
 * Author URI:        https://github.com/Priyantan-C
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       college-placements
 */

defined( 'ABSPATH' ) || exit;

define( 'CPL_VERSION', '1.3.2' );
define( 'CPL_FILE', __FILE__ );
define( 'CPL_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPL_URL', plugin_dir_url( __FILE__ ) );
define( 'CPL_REPO', 'https://github.com/Priyantan-C/college-placements/' );

require_once CPL_DIR . 'includes/class-db.php';
require_once CPL_DIR . 'includes/class-data.php';
require_once CPL_DIR . 'includes/class-xlsx-import.php';
require_once CPL_DIR . 'includes/class-page-import.php';
require_once CPL_DIR . 'includes/class-summary-import.php';
require_once CPL_DIR . 'includes/class-settings.php';
require_once CPL_DIR . 'includes/class-admin.php';
require_once CPL_DIR . 'includes/class-shortcodes.php';

register_activation_hook( __FILE__, array( 'CPL_DB', 'activate' ) );

add_action( 'plugins_loaded', array( 'CPL_DB', 'maybe_upgrade' ) );
add_action( 'init', array( 'CPL_Shortcodes', 'init' ) );

if ( is_admin() ) {
	CPL_Admin::init();
}

// Updates straight from the GitHub repo's main branch.
require_once CPL_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$cpl_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		CPL_REPO,
		__FILE__,
		'college-placements'
	);
	$cpl_updater->setBranch( 'main' );
}

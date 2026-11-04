<?php
/**
 * Plugin Name:       STCET Placements
 * Plugin URI:        https://github.com/Priyantan-C/stcet-placements
 * Description:       Placement charts and year-wise student placement tables for STCET. Upload the year's placement Excel file and the plugin builds the charts and tables.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            STCET
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stcet-placements
 */

defined( 'ABSPATH' ) || exit;

define( 'STCET_PL_VERSION', '1.0.0' );
define( 'STCET_PL_FILE', __FILE__ );
define( 'STCET_PL_DIR', plugin_dir_path( __FILE__ ) );
define( 'STCET_PL_URL', plugin_dir_url( __FILE__ ) );
define( 'STCET_PL_REPO', 'https://github.com/Priyantan-C/stcet-placements/' );

require_once STCET_PL_DIR . 'includes/class-db.php';
require_once STCET_PL_DIR . 'includes/class-data.php';
require_once STCET_PL_DIR . 'includes/class-xlsx-import.php';
require_once STCET_PL_DIR . 'includes/class-page-import.php';
require_once STCET_PL_DIR . 'includes/class-admin.php';
require_once STCET_PL_DIR . 'includes/class-shortcodes.php';

register_activation_hook( __FILE__, array( 'STCET_PL_DB', 'activate' ) );

add_action( 'plugins_loaded', array( 'STCET_PL_DB', 'maybe_upgrade' ) );
add_action( 'init', array( 'STCET_PL_Shortcodes', 'init' ) );

if ( is_admin() ) {
	STCET_PL_Admin::init();
}

// Updates straight from the GitHub repo's main branch.
require_once STCET_PL_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$stcet_pl_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		STCET_PL_REPO,
		__FILE__,
		'stcet-placements'
	);
	$stcet_pl_updater->setBranch( 'main' );
}

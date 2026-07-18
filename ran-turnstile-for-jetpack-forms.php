<?php
/**
 * Plugin Name: RAN Turnstile for Jetpack Forms
 * Description: Adds Cloudflare Turnstile protection to one selected Jetpack contact form.
 * Version: 0.1.0
 * Author: bnjmnrsh
 * Author URI: https://github.com/RocketsAreNostalgic/
 * Text Domain: ran-turnstile-for-jetpack-forms
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: jetpack
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION', '0.1.0' );
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_FILE', __FILE__ );
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/Settings.php';
require_once RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/FormTarget.php';
require_once RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/Turnstile.php';
require_once RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/HealthCheck.php';
require_once RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/Admin.php';
require_once RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/Plugin.php';

register_activation_hook( __FILE__, array( '\\RAN\\TurnstileForJetpackForms\\Plugin', 'activate' ) );

\RAN\TurnstileForJetpackForms\Plugin::register();

<?php
/**
 * Plugin coordinator.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

namespace RAN\TurnstileForJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates migration and runtime hooks.
 */
final class Plugin {
	/**
	 * Import legacy settings without changing the source option.
	 *
	 * @return void
	 */
	public static function activate() {
		Settings::migrate_from_octopus();
	}

	/**
	 * Register plugin hooks.
	 *
	 * Runtime Turnstile hooks are deliberately withheld while the source plugin's
	 * Turnstile feature is active. This is a second line of defence against a
	 * duplicate widget if an administrator overlooks the cutover instructions.
	 *
	 * @return void
	 */
	public static function register() {
		Admin::register();

		if ( Settings::has_legacy_runtime_conflict() ) {
			return;
		}

		Turnstile::register();
	}
}

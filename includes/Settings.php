<?php
/**
 * Plugin settings and migration.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

namespace RAN\TurnstileForJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes Turnstile settings.
 */
final class Settings {
	/** Independent settings option. */
	const OPTION_NAME = 'ran_turnstile_for_jetpack_forms_settings';

	/** Source option used only for a non-destructive first-run import. */
	const OCTOPUS_OPTION_NAME = 'ran_octopus_forms_settings';

	/** Source plugin basename used by the runtime conflict guard. */
	const OCTOPUS_PLUGIN_BASENAME = 'ran-octopus-forms/ran-octopus-forms.php';

	/** Cloudflare always-pass visible test site key. */
	const TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';

	/** Cloudflare always-pass test secret key. */
	const TURNSTILE_TEST_SECRET_KEY = '1x0000000000000000000000000000000AA';

	/** Cloudflare always-fail visible test site key. */
	const TURNSTILE_FAIL_TEST_SITE_KEY = '2x00000000000000000000AB';

	/** Cloudflare always-fail test secret key. */
	const TURNSTILE_FAIL_TEST_SECRET_KEY = '2x0000000000000000000000000000000AA';

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'turnstile_enabled'        => 0,
			'turnstile_always_visible' => 0,
			'turnstile_site_key'       => '',
			'turnstile_secret_key'     => '',
		);
	}

	/**
	 * Import only Turnstile-related values from RAN Octopus Forms once.
	 *
	 * The original option is never updated or deleted.
	 *
	 * @return bool Whether an option was imported.
	 */
	public static function migrate_from_octopus() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return false;
		}

		$source = get_option( self::OCTOPUS_OPTION_NAME, false );

		if ( ! is_array( $source ) ) {
			return false;
		}

		$settings = self::get_defaults();

		foreach ( array_keys( $settings ) as $key ) {
			if ( array_key_exists( $key, $source ) ) {
				$settings[ $key ] = $source[ $key ];
			}
		}

		$settings = self::sanitize_values( $settings, self::get_defaults() );

		return add_option( self::OPTION_NAME, $settings, '', false );
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all() {
		$settings = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();

		return array_intersect_key( wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults ), $defaults );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::get_all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Sanitize Settings API input.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = self::get_all();

		return self::sanitize_values( $input, $current );
	}

	/**
	 * Sanitize a complete value set without issuing Settings API errors.
	 *
	 * @param array<string,mixed> $input   Input values.
	 * @param array<string,mixed> $current Existing values.
	 * @return array<string,mixed>
	 */
	private static function sanitize_values( $input, $current ) {
		$settings = self::get_defaults();

		$settings['turnstile_enabled']        = empty( $input['turnstile_enabled'] ) ? 0 : 1;
		$settings['turnstile_always_visible'] = empty( $input['turnstile_always_visible'] ) ? 0 : 1;
		$settings['turnstile_site_key']       = sanitize_text_field( $input['turnstile_site_key'] ?? '' );

		$secret_key                       = sanitize_text_field( $input['turnstile_secret_key'] ?? '' );
		$settings['turnstile_secret_key'] = '' === $secret_key ? (string) ( $current['turnstile_secret_key'] ?? '' ) : $secret_key;

		if ( ! empty( $input['turnstile_setup_local_dev'] ) ) {
			$settings['turnstile_enabled']    = 1;
			$settings['turnstile_site_key']   = self::TURNSTILE_TEST_SITE_KEY;
			$settings['turnstile_secret_key'] = self::TURNSTILE_TEST_SECRET_KEY;
		}

		return $settings;
	}

	/**
	 * Hash settings that affect health diagnostics.
	 *
	 * @return string
	 */
	public static function get_health_hash() {
		return md5( (string) wp_json_encode( self::get_all() ) );
	}

	/** Whether Turnstile is enabled. */
	public static function is_turnstile_enabled() {
		return (bool) self::get( 'turnstile_enabled' );
	}

	/** Whether the frontend widget should remain visible throughout verification. */
	public static function is_turnstile_always_visible() {
		return (bool) self::get( 'turnstile_always_visible' );
	}

	/** Get environment type. */
	public static function get_environment_type() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		/** Filters the environment used by production-key safety checks. */
		return (string) apply_filters( 'ran_turnstile_for_jetpack_forms_environment_type', $environment );
	}

	/** Whether this is production. */
	public static function is_production_environment() {
		return 'production' === self::get_environment_type();
	}

	/** Get site key. */
	public static function get_turnstile_site_key() {
		$key = defined( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SITE_KEY' ) ? constant( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SITE_KEY' ) : self::get( 'turnstile_site_key' );

		return is_string( $key ) ? $key : '';
	}

	/** Get secret key. */
	public static function get_turnstile_secret_key() {
		$key = defined( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SECRET_KEY' ) ? constant( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SECRET_KEY' ) : self::get( 'turnstile_secret_key' );

		return is_string( $key ) ? $key : '';
	}

	/** Whether both keys exist. */
	public static function has_turnstile_keys() {
		return '' !== self::get_turnstile_site_key() && '' !== self::get_turnstile_secret_key();
	}

	/** Whether always-pass test keys are paired. */
	public static function is_turnstile_test_key_pair() {
		return self::TURNSTILE_TEST_SITE_KEY === self::get_turnstile_site_key() && self::TURNSTILE_TEST_SECRET_KEY === self::get_turnstile_secret_key();
	}

	/** Whether production blocks always-pass keys. */
	public static function blocks_turnstile_test_keys() {
		return self::is_production_environment() && self::is_turnstile_test_key_pair();
	}

	/** Whether runtime rendering/validation is fully configured. */
	public static function can_use_turnstile() {
		return self::is_turnstile_enabled() && self::has_turnstile_keys() && ! self::blocks_turnstile_test_keys();
	}

	/**
	 * Whether the old plugin is active with its Turnstile feature enabled.
	 *
	 * @return bool
	 */
	public static function has_legacy_runtime_conflict() {
		$active_plugins         = (array) get_option( 'active_plugins', array() );
		$network_active_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		$legacy                 = get_option( self::OCTOPUS_OPTION_NAME, array() );
		$is_active              = in_array( self::OCTOPUS_PLUGIN_BASENAME, $active_plugins, true ) || in_array( self::OCTOPUS_PLUGIN_BASENAME, $network_active_plugins, true );

		return $is_active && is_array( $legacy ) && ! empty( $legacy['turnstile_enabled'] );
	}
}

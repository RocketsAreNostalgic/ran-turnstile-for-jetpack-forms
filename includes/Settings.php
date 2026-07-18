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
 * Centralizes Turnstile and target-form settings.
 */
final class Settings {
	/** Independent settings option. */
	const OPTION_NAME = 'ran_turnstile_for_jetpack_forms_settings';

	/** Source option used only for a non-destructive first-run import. */
	const OCTOPUS_OPTION_NAME = 'ran_octopus_forms_settings';

	/** Source plugin basename used by the runtime conflict guard. */
	const OCTOPUS_PLUGIN_BASENAME = 'ran-octopus-forms/ran-octopus-forms.php';

	/** Marker for forms configured by this plugin. */
	const TARGET_FORM_CLASS = 'ran-turnstile-for-jetpack-forms-contact-form';

	/** Existing marker retained for a migration without content edits. */
	const LEGACY_TARGET_FORM_CLASS = 'ran-octopus-forms-contact-form';

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
			'contact_page_id'      => 0,
			'turnstile_enabled'    => 0,
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
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

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::get_defaults() );
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
		$page_id = absint( $input['contact_page_id'] ?? 0 );

		if ( 0 < $page_id && 1 !== self::get_contact_form_count_for_page( $page_id ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'ran_turnstile_for_jetpack_forms_invalid_contact_page',
				sprintf(
					/* translators: %d: number of Jetpack contact forms found. */
					__( 'Cannot save settings: the selected page contains %d Jetpack contact forms. RAN Turnstile for Jetpack Forms requires exactly one.', 'ran-turnstile-for-jetpack-forms' ),
					self::get_contact_form_count_for_page( $page_id )
				),
				'error'
			);

			return $current;
		}

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

		$settings['contact_page_id']    = absint( $input['contact_page_id'] ?? 0 );
		$settings['turnstile_enabled']  = empty( $input['turnstile_enabled'] ) ? 0 : 1;
		$settings['turnstile_site_key'] = sanitize_text_field( $input['turnstile_site_key'] ?? '' );

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

	/**
	 * Get configured page ID when it remains a page.
	 *
	 * @return int
	 */
	public static function get_contact_page_id() {
		$page_id = absint( self::get( 'contact_page_id' ) );

		return 0 < $page_id && 'page' === get_post_type( $page_id ) ? $page_id : 0;
	}

	/**
	 * Count Jetpack contact forms on a page.
	 *
	 * @param int $page_id Page ID.
	 * @return int
	 */
	public static function get_contact_form_count_for_page( $page_id ) {
		$content = get_post_field( 'post_content', absint( $page_id ) );

		return self::count_contact_form_blocks( parse_blocks( is_string( $content ) ? $content : '' ) );
	}

	/**
	 * Count forms recursively.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
	 * @return int
	 */
	private static function count_contact_form_blocks( $blocks ) {
		$count = 0;

		foreach ( $blocks as $block ) {
			if ( 'jetpack/contact-form' === ( $block['blockName'] ?? '' ) ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_contact_form_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	/**
	 * Whether the configured page has one and only one Jetpack contact form.
	 *
	 * @return bool
	 */
	public static function has_single_contact_form() {
		$page_id = self::get_contact_page_id();

		return 0 < $page_id && 1 === self::get_contact_form_count_for_page( $page_id );
	}

	/**
	 * Whether a parsed block is the configured page's unique target form.
	 *
	 * Existing and new marker classes are both recognized. A unique form on the
	 * selected page is also a target, so new installations do not need automatic
	 * content mutation.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @return bool
	 */
	public static function is_target_contact_form_block( $block ) {
		if ( 'jetpack/contact-form' !== ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		$class_name = (string) ( $block['attrs']['className'] ?? '' );

		foreach ( array( self::TARGET_FORM_CLASS, self::LEGACY_TARGET_FORM_CLASS ) as $marker ) {
			if ( 1 === preg_match( '/(?:^|\\s)' . preg_quote( $marker, '/' ) . '(?:\\s|$)/', $class_name ) ) {
				return true;
			}
		}

		return self::has_single_contact_form();
	}

	/**
	 * Whether a submitted Jetpack form ID belongs to the selected page.
	 *
	 * @param string|int|null $form_id Jetpack form ID.
	 * @return bool
	 */
	public static function is_contact_form_id( $form_id ) {
		$page_id = self::get_contact_page_id();

		if ( 0 >= $page_id || null === $form_id ) {
			return false;
		}

		$form_id = (string) $form_id;
		$base_id = (string) $page_id;

		return $form_id === $base_id || 0 === strpos( $form_id, $base_id . '-' );
	}

	/** Get submitted form ID. */
	public static function get_submitted_form_id() {
		return isset( $_POST['contact-form-id'] ) ? sanitize_text_field( wp_unslash( $_POST['contact-form-id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only routing check.
	}

	/** Whether Turnstile is enabled. */
	public static function is_turnstile_enabled() {
		return (bool) self::get( 'turnstile_enabled' );
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
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$legacy         = get_option( self::OCTOPUS_OPTION_NAME, array() );

		return in_array( self::OCTOPUS_PLUGIN_BASENAME, $active_plugins, true ) && is_array( $legacy ) && ! empty( $legacy['turnstile_enabled'] );
	}
}

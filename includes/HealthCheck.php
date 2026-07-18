<?php
/**
 * Safe plugin diagnostics.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

namespace RAN\TurnstileForJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs read-only diagnostics, plus a user-requested Turnstile verification.
 */
final class HealthCheck {
	/**
	 * Run all checks.
	 *
	 * @param string $turnstile_token Token created by the settings-page widget.
	 * @return array<string,mixed>
	 */
	public static function run( $turnstile_token = '' ) {
		$checks  = array_merge(
			self::check_runtime(),
			self::check_target(),
			self::check_turnstile( $turnstile_token ),
			self::check_frontend_render()
		);
		$overall = 'pass';

		foreach ( $checks as $check ) {
			if ( 'error' === $check['status'] ) {
				$overall = 'error';
				break;
			}

			if ( 'warning' === $check['status'] ) {
				$overall = 'warning';
			}
		}

		return array(
			'overall' => $overall,
			'checks'  => $checks,
		);
	}

	/** Check plugin/Jetpack runtime. */
	private static function check_runtime() {
		return array(
			self::status( class_exists( __CLASS__ ), __( 'RAN Turnstile', 'ran-turnstile-for-jetpack-forms' ), __( 'RAN Turnstile for Jetpack Forms is loaded.', 'ran-turnstile-for-jetpack-forms' ), __( 'The plugin is not loaded correctly.', 'ran-turnstile-for-jetpack-forms' ) ),
			self::status( class_exists( '\\Automattic\\Jetpack\\Forms\\ContactForm\\Contact_Form' ), __( 'Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ), __( 'Jetpack Forms is available.', 'ran-turnstile-for-jetpack-forms' ), __( 'Jetpack Forms is not available.', 'ran-turnstile-for-jetpack-forms' ) ),
			self::status( ! Settings::has_legacy_runtime_conflict(), __( 'Duplicate protection', 'ran-turnstile-for-jetpack-forms' ), __( 'The old RAN Octopus Forms Turnstile feature is not running.', 'ran-turnstile-for-jetpack-forms' ), __( 'RAN Octopus Forms still has Turnstile enabled. Disable its Turnstile feature before using this plugin.', 'ran-turnstile-for-jetpack-forms' ) ),
		);
	}

	/** Check selected page and unique form. */
	private static function check_target() {
		$page_id = Settings::get_contact_page_id();
		$post    = 0 < $page_id ? get_post( $page_id ) : null;
		$count   = 0 < $page_id ? Settings::get_contact_form_count_for_page( $page_id ) : 0;

		return array(
			self::status( $post instanceof \WP_Post && 'publish' === $post->post_status, __( 'Contact page', 'ran-turnstile-for-jetpack-forms' ), __( 'The selected contact page is published.', 'ran-turnstile-for-jetpack-forms' ), __( 'Select a published contact page.', 'ran-turnstile-for-jetpack-forms' ) ),
			self::status(
				1 === $count,
				__( 'Jetpack form count', 'ran-turnstile-for-jetpack-forms' ),
				__( 'The selected page contains exactly one Jetpack contact form.', 'ran-turnstile-for-jetpack-forms' ),
				sprintf(
					/* translators: %d: number of Jetpack contact forms found. */
					__( 'The selected page contains %d Jetpack contact forms; exactly one is required.', 'ran-turnstile-for-jetpack-forms' ),
					$count
				)
			),
		);
	}

	/**
	 * Check credentials and optionally validate them.
	 *
	 * @param string $turnstile_token Token.
	 * @return array<int,array<string,string>>
	 */
	private static function check_turnstile( $turnstile_token ) {
		if ( ! Settings::is_turnstile_enabled() ) {
			return array( self::row( 'skipped', __( 'Turnstile', 'ran-turnstile-for-jetpack-forms' ), __( 'Turnstile is disabled.', 'ran-turnstile-for-jetpack-forms' ) ) );
		}

		$checks = array(
			self::status( '' !== Settings::get_turnstile_site_key(), __( 'Turnstile site key', 'ran-turnstile-for-jetpack-forms' ), __( 'A site key is present.', 'ran-turnstile-for-jetpack-forms' ), __( 'The site key is missing.', 'ran-turnstile-for-jetpack-forms' ) ),
			self::status( '' !== Settings::get_turnstile_secret_key(), __( 'Turnstile secret key', 'ran-turnstile-for-jetpack-forms' ), __( 'A secret key is present.', 'ran-turnstile-for-jetpack-forms' ), __( 'The secret key is missing.', 'ran-turnstile-for-jetpack-forms' ) ),
		);

		if ( ! Settings::has_turnstile_keys() ) {
			return $checks;
		}

		if ( Settings::blocks_turnstile_test_keys() ) {
			$checks[] = self::row( 'error', __( 'Environment safety', 'ran-turnstile-for-jetpack-forms' ), __( 'Cloudflare always-pass test keys are blocked in a production environment.', 'ran-turnstile-for-jetpack-forms' ) );
			return $checks;
		}

		if ( '' === $turnstile_token && Settings::is_turnstile_test_key_pair() ) {
			$turnstile_token = 'XXXX.DUMMY.TOKEN.XXXX';
		}

		if ( '' === $turnstile_token ) {
			$checks[] = self::row( 'error', __( 'Turnstile validation', 'ran-turnstile-for-jetpack-forms' ), __( 'No token was submitted. Run the health check after the widget loads.', 'ran-turnstile-for-jetpack-forms' ) );
			return $checks;
		}

		$result   = Turnstile::verify_token( $turnstile_token );
		$checks[] = self::row( is_wp_error( $result ) ? 'error' : 'pass', __( 'Turnstile validation', 'ran-turnstile-for-jetpack-forms' ), is_wp_error( $result ) ? $result->get_error_message() : __( 'Cloudflare accepted the configured key pair and token.', 'ran-turnstile-for-jetpack-forms' ) );

		return $checks;
	}

	/** Check rendered page without submitting anything. */
	private static function check_frontend_render() {
		$url = get_permalink( Settings::get_contact_page_id() );

		if ( ! $url ) {
			return array( self::row( 'error', __( 'Frontend render', 'ran-turnstile-for-jetpack-forms' ), __( 'The selected page URL is unavailable.', 'ran-turnstile-for-jetpack-forms' ) ) );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( self::row( 'error', __( 'Frontend render', 'ran-turnstile-for-jetpack-forms' ), $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );

		return array(
			self::status( 200 === wp_remote_retrieve_response_code( $response ), __( 'Contact page response', 'ran-turnstile-for-jetpack-forms' ), __( 'The contact page returns HTTP 200.', 'ran-turnstile-for-jetpack-forms' ), __( 'The contact page did not return HTTP 200.', 'ran-turnstile-for-jetpack-forms' ) ),
			self::status( false !== strpos( $body, 'jetpack-contact-form' ), __( 'Rendered Jetpack form', 'ran-turnstile-for-jetpack-forms' ), __( 'The rendered page includes a Jetpack contact form.', 'ran-turnstile-for-jetpack-forms' ), __( 'The rendered page is missing the Jetpack contact form.', 'ran-turnstile-for-jetpack-forms' ) ),
			self::status( ! Settings::can_use_turnstile() || false !== strpos( $body, 'cf-turnstile' ), __( 'Rendered Turnstile', 'ran-turnstile-for-jetpack-forms' ), __( 'The rendered widget state matches the settings.', 'ran-turnstile-for-jetpack-forms' ), __( 'Turnstile is enabled but the widget is missing.', 'ran-turnstile-for-jetpack-forms' ) ),
		);
	}

	/** Build a pass/error row. */
	private static function status( $condition, $label, $pass_message, $fail_message ) {
		return self::row( $condition ? 'pass' : 'error', $label, $condition ? $pass_message : $fail_message );
	}

	/** Build a status row. */
	private static function row( $status, $label, $message ) {
		return array(
			'status'  => $status,
			'label'   => $label,
			'message' => $message,
		);
	}
}

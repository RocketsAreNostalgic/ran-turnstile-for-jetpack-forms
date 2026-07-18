<?php
/**
 * Cloudflare Turnstile integration.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

namespace RAN\TurnstileForJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and validates Turnstile for the selected Jetpack contact form.
 */
final class Turnstile {
	/** Cloudflare Siteverify endpoint. */
	const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/** Register hooks. */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_script' ) );
		add_filter( 'jetpack_contact_form_html', array( __CLASS__, 'append_widget' ) );
		add_filter( 'jetpack_contact_form_is_spam', array( __CLASS__, 'validate_submission' ), 5, 2 );
	}

	/** Enqueue the widget script on the selected page. */
	public static function enqueue_script() {
		if ( ! self::should_render_on_current_page() ) {
			return;
		}

		wp_enqueue_script( 'ran-turnstile-for-jetpack-forms', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External service script.
	}

	/**
	 * Append the widget before the submit button.
	 *
	 * @param string $form_html Rendered form HTML.
	 * @return string
	 */
	public static function append_widget( $form_html ) {
		if ( ! self::should_render_on_current_page() || ! FormTarget::is_target_form_html( $form_html ) || false !== strpos( $form_html, 'cf-turnstile' ) ) {
			return $form_html;
		}

		$widget  = self::get_widget_html();
		$count   = 0;
		$updated = preg_replace( '/<div class="wp-block-button/', $widget . ' <div class="wp-block-button', $form_html, 1, $count );

		if ( is_string( $updated ) && 0 < $count ) {
			return $updated;
		}

		return str_replace( '</form>', $widget . '</form>', $form_html );
	}

	/**
	 * Validate before Jetpack accepts the submission.
	 *
	 * @param bool|\WP_Error $is_spam        Existing spam state.
	 * @param array          $akismet_values Akismet values.
	 * @return bool|\WP_Error
	 */
	public static function validate_submission( $is_spam, $akismet_values ) {
		unset( $akismet_values );

		if ( is_wp_error( $is_spam ) || true === $is_spam ) {
			return $is_spam;
		}

		if ( ! Settings::is_turnstile_enabled() || ! FormTarget::is_target_submission() ) {
			return $is_spam;
		}

		if ( ! Settings::can_use_turnstile() ) {
			return new \WP_Error( 'ran_turnstile_for_jetpack_forms_misconfigured', __( 'Verification is not configured correctly. Please contact the site administrator.', 'ran-turnstile-for-jetpack-forms' ) );
		}

		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Jetpack owns submission validation; target nonce was verified above.

		$result = self::verify_token( $token );

		return is_wp_error( $result ) ? $result : $is_spam;
	}

	/**
	 * Verify a Turnstile token.
	 *
	 * @param string $token Turnstile response token.
	 * @return true|\WP_Error
	 */
	public static function verify_token( $token ) {
		$secret = Settings::get_turnstile_secret_key();

		if ( '' === $secret || '' === $token ) {
			return new \WP_Error( 'ran_turnstile_for_jetpack_forms_missing', __( 'Verification is required. Please try again.', 'ran-turnstile-for-jetpack-forms' ) );
		}

		$payload   = array(
			'secret'   => $secret,
			'response' => $token,
		);
		$remote_ip = self::get_remote_ip();

		if ( '' !== $remote_ip ) {
			$payload['remoteip'] = $remote_ip;
		}

		$response = wp_remote_post(
			self::SITEVERIFY_URL,
			array(
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => $payload,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ran_turnstile_for_jetpack_forms_unreachable', __( 'Verification could not be completed. Please try again.', 'ran-turnstile-for-jetpack-forms' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $body ) && ! empty( $body['success'] ) ) {
			return true;
		}

		return new \WP_Error( 'ran_turnstile_for_jetpack_forms_failed', __( 'Verification failed. Please try again.', 'ran-turnstile-for-jetpack-forms' ), is_array( $body ) ? $body : array() );
	}

	/** Get widget HTML. */
	private static function get_widget_html() {
		return sprintf(
			'<div class="ran-turnstile-for-jetpack-forms"><div class="cf-turnstile" data-sitekey="%s"></div></div>',
			esc_attr( Settings::get_turnstile_site_key() )
		);
	}

	/** Whether the current page is eligible for a widget. */
	private static function should_render_on_current_page() {
		return Settings::can_use_turnstile() && Settings::has_single_contact_form() && is_page( Settings::get_contact_page_id() );
	}

	/** Get a validated client IP address. */
	private static function get_remote_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$parts = explode( ',', $value );
			$ip    = trim( (string) reset( $parts ) );

			if ( rest_is_ip_address( $ip ) ) {
				return $ip;
			}
		}

		return '';
	}
}

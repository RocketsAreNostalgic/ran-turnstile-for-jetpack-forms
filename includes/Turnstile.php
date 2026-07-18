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
 * Renders and validates Turnstile for Jetpack contact forms.
 */
final class Turnstile {
	/** Cloudflare Siteverify endpoint. */
	const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/** Frontend script handle. */
	const SCRIPT_HANDLE = 'ran-turnstile-for-jetpack-forms';

	/** Plugin-owned client behavior handle. */
	const CLIENT_SCRIPT_HANDLE = 'ran-turnstile-for-jetpack-forms-client';

	/** Plugin-specific response field, isolated from other Turnstile integrations. */
	const RESPONSE_FIELD = 'ran-turnstile-for-jetpack-forms-response';

	/** Posted when another Turnstile widget already occupies the form. */
	const CONFLICT_FIELD = 'ran-turnstile-for-jetpack-forms-conflict';

	/** Register hooks. */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_script' ) );
		add_filter( 'jetpack_contact_form_html', array( __CLASS__, 'append_widget' ), PHP_INT_MAX );
		add_filter( 'jetpack_contact_form_is_spam', array( __CLASS__, 'validate_submission' ), 5, 2 );
	}

	/** Register the widget script without loading it on pages that have no forms. */
	public static function register_script() {
		wp_register_script( self::SCRIPT_HANDLE, 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External service script.
		wp_register_script(
			self::CLIENT_SCRIPT_HANDLE,
			plugins_url( 'assets/turnstile.js', RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_FILE ),
			array( self::SCRIPT_HANDLE ),
			RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION,
			true
		);
	}

	/**
	 * Append the widget before the submit button.
	 *
	 * @param string $form_html Rendered form HTML.
	 * @return string
	 */
	public static function append_widget( $form_html ) {
		if ( ! Settings::is_turnstile_enabled() ) {
			return $form_html;
		}

		$context = self::get_form_context_from_html( $form_html );

		if ( ! self::should_protect_form( $context['form_id'], $context['form_hash'], 'render' ) ) {
			return $form_html;
		}

		if ( self::has_class( $form_html, 'ran-turnstile-for-jetpack-forms' ) ) {
			if ( Settings::can_use_turnstile() ) {
				self::enqueue_script();
			}

			return $form_html;
		}

		if ( self::has_class( $form_html, 'cf-turnstile' ) ) {
			if ( self::has_input_named( $form_html, self::CONFLICT_FIELD ) ) {
				return $form_html;
			}

			return self::insert_before_form_end(
				$form_html,
				'<input type="hidden" name="' . esc_attr( self::CONFLICT_FIELD ) . '" value="1" />'
			);
		}

		if ( ! Settings::can_use_turnstile() ) {
			return $form_html;
		}

		self::enqueue_script();

		$widget  = self::get_widget_html( $context );
		$count   = 0;
		$updated = preg_replace( '/<div class="wp-block-button/', $widget . ' <div class="wp-block-button', $form_html, 1, $count );

		if ( is_string( $updated ) && 0 < $count ) {
			return $updated;
		}

		return self::insert_before_form_end( $form_html, $widget );
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

		if ( ! Settings::is_turnstile_enabled() ) {
			return $is_spam;
		}

		$context = self::get_submitted_form_context();

		if ( ! self::should_protect_form( $context['form_id'], $context['form_hash'], 'submission' ) ) {
			return $is_spam;
		}

		if ( isset( $_POST[ self::CONFLICT_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only collision marker; removing it does not bypass token validation.
			return new \WP_Error( 'ran_turnstile_for_jetpack_forms_conflict', __( 'This form has more than one Turnstile integration configured. Please contact the site administrator.', 'ran-turnstile-for-jetpack-forms' ) );
		}

		if ( ! Settings::can_use_turnstile() ) {
			return new \WP_Error( 'ran_turnstile_for_jetpack_forms_misconfigured', __( 'Verification is not configured correctly. Please contact the site administrator.', 'ran-turnstile-for-jetpack-forms' ) );
		}

		$token = isset( $_POST[ self::RESPONSE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::RESPONSE_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Jetpack owns submission validation; this filter only reads the Turnstile response.

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

	/**
	 * Get widget HTML.
	 *
	 * @param array<string,string> $context Jetpack form identity.
	 */
	private static function get_widget_html( $context ) {
		return sprintf(
			'<div class="ran-turnstile-for-jetpack-forms"><div id="%1$s" class="cf-turnstile" data-sitekey="%2$s" data-response-field-name="%3$s" data-appearance="%4$s"></div></div>',
			esc_attr( wp_unique_id( 'ran-turnstile-widget-' ) ),
			esc_attr( Settings::get_turnstile_site_key() ),
			esc_attr( self::RESPONSE_FIELD ),
			esc_attr( self::get_widget_appearance( $context ) )
		);
	}

	/** Get the validated frontend appearance for one form. */
	private static function get_widget_appearance( $context ) {
		$default = Settings::is_turnstile_always_visible() ? 'always' : 'interaction-only';

		/**
		 * Filters whether one frontend widget is always visible or interaction-only.
		 *
		 * @param string              $appearance Either always or interaction-only.
		 * @param array<string,mixed> $context    Jetpack form identity.
		 */
		$appearance = (string) apply_filters( 'ran_turnstile_for_jetpack_forms_widget_appearance', $default, $context );

		return in_array( $appearance, array( 'always', 'interaction-only' ), true ) ? $appearance : $default;
	}

	/** Enqueue the frontend script after the first protected form renders. */
	private static function enqueue_script() {
		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			self::register_script();
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );
		wp_enqueue_script( self::CLIENT_SCRIPT_HANDLE );
	}

	/**
	 * Decide whether this integration protects one form.
	 *
	 * Callbacks should make the same deterministic decision during both stages.
	 * The form hash distinguishes multiple forms that share one post or template.
	 *
	 * @param string $form_id   Jetpack form ID.
	 * @param string $form_hash Jetpack form hash.
	 * @param string $phase     Either render or submission.
	 * @return bool
	 */
	private static function should_protect_form( $form_id, $form_hash, $phase ) {
		$context = array(
			'phase'     => $phase,
			'form_id'   => $form_id,
			'form_hash' => $form_hash,
			'post_id'   => ctype_digit( $form_id ) ? absint( $form_id ) : 0,
		);

		/**
		 * Filters whether RAN Turnstile protects a Jetpack form.
		 *
		 * @param bool                $protect Whether to render and validate Turnstile.
		 * @param array<string,mixed> $context Form identity and render/submission phase.
		 */
		return (bool) apply_filters( 'ran_turnstile_for_jetpack_forms_should_protect_form', true, $context );
	}

	/** Get form identity from Jetpack's rendered hidden fields. */
	private static function get_form_context_from_html( $form_html ) {
		$context = array(
			'form_id'   => '',
			'form_hash' => '',
		);

		$processor = new \WP_HTML_Tag_Processor( $form_html );

		while ( $processor->next_tag( 'input' ) ) {
			$name = $processor->get_attribute( 'name' );

			if ( 'contact-form-id' === $name ) {
				$context['form_id'] = sanitize_text_field( (string) $processor->get_attribute( 'value' ) );
			} elseif ( 'contact-form-hash' === $name ) {
				$context['form_hash'] = sanitize_text_field( (string) $processor->get_attribute( 'value' ) );
			}
		}

		return $context;
	}

	/** Get form identity from Jetpack's submitted hidden fields. */
	private static function get_submitted_form_context() {
		return array(
			'form_id'   => isset( $_POST['contact-form-id'] ) ? sanitize_text_field( wp_unslash( $_POST['contact-form-id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only routing context.
			'form_hash' => isset( $_POST['contact-form-hash'] ) ? sanitize_text_field( wp_unslash( $_POST['contact-form-hash'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only routing context.
		);
	}

	/** Insert plugin markup immediately before the form closes. */
	private static function insert_before_form_end( $form_html, $markup ) {
		return str_replace( '</form>', $markup . '</form>', $form_html );
	}

	/** Whether rendered HTML contains an element with an exact class name. */
	private static function has_class( $form_html, $class_name ) {
		$processor = new \WP_HTML_Tag_Processor( $form_html );

		return $processor->next_tag( array( 'class_name' => $class_name ) );
	}

	/** Whether rendered HTML contains an input with an exact field name. */
	private static function has_input_named( $form_html, $field_name ) {
		$processor = new \WP_HTML_Tag_Processor( $form_html );

		while ( $processor->next_tag( 'input' ) ) {
			if ( $field_name === $processor->get_attribute( 'name' ) ) {
				return true;
			}
		}

		return false;
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

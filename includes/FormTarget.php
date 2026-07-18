<?php
/**
 * Jetpack target-form routing.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

namespace RAN\TurnstileForJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identifies one selected Jetpack form during rendering and submission.
 */
final class FormTarget {
	/** @var bool Whether the current block-render context is the target form. */
	private static $rendering_target_form = false;

	/** Register hooks. */
	public static function register() {
		add_filter( 'pre_render_block', array( __CLASS__, 'before_render_block' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'after_render_block' ), 10, 2 );
		add_filter( 'jetpack_contact_form_html', array( __CLASS__, 'mark_target_form_submission' ) );
	}

	/**
	 * Enter target form render context.
	 *
	 * @param string|null         $pre_render   Existing pre-rendered content.
	 * @param array<string,mixed> $parsed_block Parsed block data.
	 * @return string|null
	 */
	public static function before_render_block( $pre_render, $parsed_block ) {
		if ( ! is_admin() && is_page( Settings::get_contact_page_id() ) && Settings::is_target_contact_form_block( $parsed_block ) ) {
			self::$rendering_target_form = true;
		}

		return $pre_render;
	}

	/**
	 * Leave target form render context.
	 *
	 * @param string              $block_content Rendered content.
	 * @param array<string,mixed> $parsed_block   Parsed block data.
	 * @return string
	 */
	public static function after_render_block( $block_content, $parsed_block ) {
		if ( Settings::is_target_contact_form_block( $parsed_block ) ) {
			self::$rendering_target_form = false;
		}

		return $block_content;
	}

	/**
	 * Add a plugin-owned marker nonce to the target form.
	 *
	 * @param string $form_html Rendered form HTML.
	 * @return string
	 */
	public static function mark_target_form_submission( $form_html ) {
		if ( ! self::is_target_form_html( $form_html ) || false !== strpos( $form_html, 'ran_turnstile_for_jetpack_forms_target' ) ) {
			return $form_html;
		}

		$marker = sprintf(
			'<input class="%1$s" type="hidden" name="ran_turnstile_for_jetpack_forms_target" value="%2$s" />',
			esc_attr( Settings::TARGET_FORM_CLASS ),
			esc_attr( wp_create_nonce( self::get_target_nonce_action() ) )
		);

		return str_replace( '</form>', $marker . '</form>', $form_html );
	}

	/**
	 * Whether rendered markup belongs to the target form.
	 *
	 * @param string $form_html Rendered form HTML.
	 * @return bool
	 */
	public static function is_target_form_html( $form_html ) {
		if ( ! Settings::has_single_contact_form() ) {
			return false;
		}

		return self::$rendering_target_form
			|| false !== strpos( $form_html, Settings::TARGET_FORM_CLASS )
			|| false !== strpos( $form_html, Settings::LEGACY_TARGET_FORM_CLASS )
			|| false !== strpos( $form_html, 'ran_turnstile_for_jetpack_forms_target' );
	}

	/**
	 * Whether the request is from the marked selected form.
	 *
	 * @return bool
	 */
	public static function is_target_submission() {
		if ( ! Settings::has_single_contact_form() || ! Settings::is_contact_form_id( Settings::get_submitted_form_id() ) || ! isset( $_POST['ran_turnstile_for_jetpack_forms_target'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['ran_turnstile_for_jetpack_forms_target'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is the nonce being verified.

		return (bool) wp_verify_nonce( $nonce, self::get_target_nonce_action() );
	}

	/** Get the target nonce action. */
	private static function get_target_nonce_action() {
		return 'ran_turnstile_for_jetpack_forms_target_' . Settings::get_contact_page_id();
	}
}

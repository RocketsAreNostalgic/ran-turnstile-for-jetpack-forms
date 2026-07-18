<?php
/**
 * WordPress admin UI.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

namespace RAN\TurnstileForJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers settings and troubleshooting UI.
 */
final class Admin {
	/** Settings page slug. */
	const PAGE_SLUG = 'ran-turnstile-for-jetpack-forms';

	/** Health transient prefix. */
	const HEALTH_TRANSIENT_PREFIX = 'ran_turnstile_for_jetpack_forms_health_';

	/** Register hooks. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_conflict_notice' ) );
		add_action( 'admin_post_ran_turnstile_for_jetpack_forms_run_health_check', array( __CLASS__, 'run_health_check' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
	}

	/** Add Settings submenu. */
	public static function add_page() {
		add_options_page(
			__( 'RAN Turnstile for Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ),
			__( 'RAN Turnstile', 'ran-turnstile-for-jetpack-forms' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/** Register independent option. */
	public static function register_settings() {
		register_setting(
			'ran_turnstile_for_jetpack_forms',
			Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::get_defaults(),
			)
		);
	}

	/** Add Settings action link. */
	public static function plugin_action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				esc_html__( 'Settings', 'ran-turnstile-for-jetpack-forms' )
			)
		);

		return $links;
	}

	/**
	 * Warn when the source plugin would duplicate protection.
	 *
	 * @return void
	 */
	public static function render_conflict_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! Settings::has_legacy_runtime_conflict() ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'RAN Turnstile is paused to prevent duplicate widgets.', 'ran-turnstile-for-jetpack-forms' ); ?></strong></p>
			<p><?php esc_html_e( 'RAN Octopus Forms is active with its Turnstile feature enabled. Disable Turnstile in RAN Octopus Forms before enabling this plugin’s runtime protection.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
		</div>
		<?php
	}

	/** Enqueue the health-check widget on this settings page. */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix || ! Settings::can_use_turnstile() || Settings::has_legacy_runtime_conflict() ) {
			return;
		}

		wp_enqueue_script( 'ran-turnstile-for-jetpack-forms-admin', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External service script.
		wp_add_inline_script(
			'ran-turnstile-for-jetpack-forms-admin',
			'window.ranTurnstileForJetpackFormsReady=function(){var button=document.getElementById("ran-turnstile-for-jetpack-forms-run-health-check");if(button){button.disabled=false;}};window.ranTurnstileForJetpackFormsExpired=function(){var button=document.getElementById("ran-turnstile-for-jetpack-forms-run-health-check");if(button){button.disabled=true;}};document.addEventListener("DOMContentLoaded",function(){var widget=document.querySelector("#ran-turnstile-for-jetpack-forms-health-check-form .cf-turnstile");var button=document.getElementById("ran-turnstile-for-jetpack-forms-run-health-check");if(widget&&button){button.disabled=true;}});',
			'before'
		);
	}

	/** Render settings and diagnostics. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$health   = self::get_health_result();
		?>
		<div class="wrap">
			<style>
				.ran-turnstile-settings-form,
				.ran-turnstile-settings-section { max-width: 960px; }
				.ran-turnstile-fieldset { padding: 0; }
				.ran-turnstile-fieldset > legend.hndle,
				.ran-turnstile-settings-section > .hndle { box-sizing: border-box; display: block; font-size: 14px; font-weight: 600; margin: 0; padding: 10px 12px; width: 100%; }
				.ran-turnstile-field { margin: 0 0 20px; }
				.ran-turnstile-field:last-child { margin-bottom: 0; }
				.ran-turnstile-field > label,
				.ran-turnstile-field-label { display: block; font-weight: 600; margin: 0 0 6px; }
				.ran-turnstile-details { background: #fff; border: 1px solid #c3c4c7; }
				.ran-turnstile-details summary { cursor: pointer; font-weight: 600; padding: 12px; }
				.ran-turnstile-details .inside { border-top: 1px solid #c3c4c7; }
				.ran-turnstile-health-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 12px; }
				.ran-turnstile-health-actions .button { min-height: 65px; }
				.ran-turnstile-status-pass { color: #008a20; }
				.ran-turnstile-status-error { color: #b32d2e; }
				.ran-turnstile-status-warning { color: #996800; }
				.ran-turnstile-status-skipped { color: #646970; }
			</style>
			<h1><?php esc_html_e( 'RAN Turnstile for Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ); ?></h1>
			<p><?php esc_html_e( 'Protect every Jetpack form on this site with Cloudflare Turnstile.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
			<p>
				<a href="https://developers.cloudflare.com/turnstile/get-started/server-side-validation/"><?php esc_html_e( 'Turnstile validation', 'ran-turnstile-for-jetpack-forms' ); ?></a>
				<?php echo esc_html_x( '|', 'settings help link separator', 'ran-turnstile-for-jetpack-forms' ); ?>
				<a href="https://developers.cloudflare.com/turnstile/troubleshooting/testing/"><?php esc_html_e( 'Turnstile testing keys', 'ran-turnstile-for-jetpack-forms' ); ?></a>
			</p>

			<?php if ( Settings::has_legacy_runtime_conflict() ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Runtime protection is paused because RAN Octopus Forms still has Turnstile enabled. Disable the old feature before cutover; otherwise both plugins would render and validate a widget.', 'ran-turnstile-for-jetpack-forms' ); ?></p></div>
			<?php endif; ?>

			<form class="ran-turnstile-settings-form" method="post" action="options.php">
				<?php settings_fields( 'ran_turnstile_for_jetpack_forms' ); ?>
				<fieldset class="postbox ran-turnstile-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'Cloudflare Turnstile', 'ran-turnstile-for-jetpack-forms' ); ?></span></legend>
					<div class="inside">
						<div class="ran-turnstile-field">
							<label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_enabled]" value="1" <?php checked( ! empty( $settings['turnstile_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable Turnstile protection for all Jetpack forms', 'ran-turnstile-for-jetpack-forms' ); ?></label>
							<p class="description"><?php esc_html_e( 'Jetpack Akismet can remain enabled. Do not run another Turnstile integration on the same form unless code explicitly excludes that form from this plugin.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
						</div>
						<div class="ran-turnstile-field">
							<label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_always_visible]" value="1" <?php checked( ! empty( $settings['turnstile_always_visible'] ) ); ?> /> <?php esc_html_e( 'Always show the Turnstile widget', 'ran-turnstile-for-jetpack-forms' ); ?></label>
							<p class="description"><?php esc_html_e( 'Leave off to show the frontend widget only when Cloudflare requires visitor interaction (recommended). This does not change the widget mode configured for the site key in Cloudflare. The troubleshooting widget remains visible.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
						</div>
						<div class="ran-turnstile-field">
							<label for="ran-turnstile-site-key"><?php esc_html_e( 'Site key', 'ran-turnstile-for-jetpack-forms' ); ?></label>
							<input id="ran-turnstile-site-key" class="regular-text code" type="text" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_site_key]" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" autocomplete="off" />
						</div>
						<div class="ran-turnstile-field">
							<label for="ran-turnstile-secret-key"><?php esc_html_e( 'Secret key', 'ran-turnstile-for-jetpack-forms' ); ?></label>
							<input id="ran-turnstile-secret-key" class="regular-text code" type="password" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_secret_key]" value="" autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the existing stored secret.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
						</div>
						<div class="ran-turnstile-field">
							<span class="ran-turnstile-field-label"><?php esc_html_e( 'Local testing', 'ran-turnstile-for-jetpack-forms' ); ?></span>
							<?php self::render_local_testing_details(); ?>
						</div>
					</div>
				</fieldset>
				<?php submit_button( __( 'Save settings', 'ran-turnstile-for-jetpack-forms' ) ); ?>
			</form>

			<div class="postbox ran-turnstile-settings-section">
				<h2 class="hndle"><span><?php esc_html_e( 'Troubleshooting', 'ran-turnstile-for-jetpack-forms' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Runs safe diagnostics without sending mail, submitting a form, or creating feedback posts. Cloudflare validation occurs only when you press the button.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
					<form id="ran-turnstile-for-jetpack-forms-health-check-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ran_turnstile_for_jetpack_forms_run_health_check" />
						<?php wp_nonce_field( 'ran_turnstile_for_jetpack_forms_run_health_check' ); ?>
						<?php if ( Settings::can_use_turnstile() && ! Settings::has_legacy_runtime_conflict() ) : ?>
							<div class="ran-turnstile-health-actions">
								<input id="ran-turnstile-for-jetpack-forms-run-health-check" class="button button-secondary button-hero" type="submit" value="<?php echo esc_attr__( 'Run health check', 'ran-turnstile-for-jetpack-forms' ); ?>" />
								<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( Settings::get_turnstile_site_key() ); ?>" data-appearance="always" data-callback="ranTurnstileForJetpackFormsReady" data-expired-callback="ranTurnstileForJetpackFormsExpired" data-timeout-callback="ranTurnstileForJetpackFormsExpired"></div>
							</div>
						<?php else : ?>
							<input id="ran-turnstile-for-jetpack-forms-run-health-check" class="button button-secondary" type="submit" value="<?php echo esc_attr__( 'Run health check', 'ran-turnstile-for-jetpack-forms' ); ?>" />
						<?php endif; ?>
					</form>
					<?php self::render_health_result( $health ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/** Run health action. */
	public static function run_health_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run this health check.', 'ran-turnstile-for-jetpack-forms' ) );
		}

		check_admin_referer( 'ran_turnstile_for_jetpack_forms_run_health_check' );
		$token                   = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Sanitized after capability and nonce checks.
		$health                  = HealthCheck::run( $token );
		$health['settings_hash'] = Settings::get_health_hash();

		set_transient( self::HEALTH_TRANSIENT_PREFIX . get_current_user_id(), $health, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&ran_turnstile_health=1' ) );
		exit;
	}

	/** Render always-pass and always-fail key guidance. */
	private static function render_local_testing_details() {
		?>
		<details class="ran-turnstile-details">
			<summary><?php esc_html_e( 'Localhost test keys', 'ran-turnstile-for-jetpack-forms' ); ?></summary>
			<div class="inside">
				<p><?php esc_html_e( 'For local development, use Cloudflare’s test keys. The setup button saves the always-pass pair and enables Turnstile.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
				<p><button class="button button-secondary" type="submit" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_setup_local_dev]" value="1"><?php esc_html_e( 'Set up local dev', 'ran-turnstile-for-jetpack-forms' ); ?></button></p>
				<p><strong><?php esc_html_e( 'Always-pass site key', 'ran-turnstile-for-jetpack-forms' ); ?></strong><br /><input class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_TEST_SITE_KEY ); ?>" /></p>
				<p><strong><?php esc_html_e( 'Always-pass secret key', 'ran-turnstile-for-jetpack-forms' ); ?></strong><br /><input class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_TEST_SECRET_KEY ); ?>" /></p>
				<p class="description"><?php esc_html_e( 'Always-pass test keys are blocked when WordPress reports a production environment.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
				<p><strong><?php esc_html_e( 'Always-fail site key', 'ran-turnstile-for-jetpack-forms' ); ?></strong><br /><input class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_FAIL_TEST_SITE_KEY ); ?>" /></p>
				<p><strong><?php esc_html_e( 'Always-fail secret key', 'ran-turnstile-for-jetpack-forms' ); ?></strong><br /><input class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_FAIL_TEST_SECRET_KEY ); ?>" /></p>
				<p class="description"><?php esc_html_e( 'Use the always-fail pair only when testing validation errors, failed health checks, and visitor retry messaging.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
			</div>
		</details>
		<?php
	}

	/** Get non-stale health result. */
	private static function get_health_result() {
		$result = get_transient( self::HEALTH_TRANSIENT_PREFIX . get_current_user_id() );

		return is_array( $result ) && ( $result['settings_hash'] ?? '' ) === Settings::get_health_hash() ? $result : false;
	}

	/** Render latest result table. */
	private static function render_health_result( $health ) {
		if ( ! is_array( $health ) ) {
			return;
		}
		?>
		<?php /* translators: %s: health-check status. */ ?>
		<h3><?php echo esc_html( sprintf( __( 'Latest result: %s', 'ran-turnstile-for-jetpack-forms' ), ucfirst( (string) $health['overall'] ) ) ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Check', 'ran-turnstile-for-jetpack-forms' ); ?></th><th><?php esc_html_e( 'Status', 'ran-turnstile-for-jetpack-forms' ); ?></th><th><?php esc_html_e( 'Detail', 'ran-turnstile-for-jetpack-forms' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $health['checks'] as $check ) : ?>
				<tr>
					<td><?php echo esc_html( $check['label'] ); ?></td>
					<td><strong class="<?php echo esc_attr( 'ran-turnstile-status-' . sanitize_html_class( $check['status'] ) ); ?>"><?php echo esc_html( 'error' === $check['status'] ? __( 'FAIL', 'ran-turnstile-for-jetpack-forms' ) : strtoupper( $check['status'] ) ); ?></strong></td>
					<td><?php echo esc_html( $check['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

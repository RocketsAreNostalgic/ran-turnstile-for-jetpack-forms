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

	/** Shared shell style handle. */
	const SHELL_STYLE_HANDLE = 'ran-turnstile-for-jetpack-forms-admin-shell';

	/** Consumer admin style handle. */
	const ADMIN_STYLE_HANDLE = 'ran-turnstile-for-jetpack-forms-admin';

	/** Cloudflare diagnostics script handle. */
	const HEALTH_SCRIPT_HANDLE = 'ran-turnstile-for-jetpack-forms-health';

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
			__( 'RAN Turnstile for Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ),
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
				esc_url( add_query_arg( 'tab', 'settings', admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) ),
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

	/** Return the selected shell tab, defaulting invalid input to Overview. */
	private static function get_active_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.

		return 'settings' === $tab ? 'settings' : 'overview';
	}

	/** Enqueue exact-screen styles and the conditional health-check widget. */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( self::SHELL_STYLE_HANDLE, RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_URL . 'assets/ran-admin-shell.css', array(), RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION );
		wp_enqueue_style( self::ADMIN_STYLE_HANDLE, RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_URL . 'assets/admin.css', array( self::SHELL_STYLE_HANDLE ), RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION );

		if ( 'settings' !== self::get_active_tab() || ! Settings::can_use_turnstile() || Settings::has_legacy_runtime_conflict() ) {
			return;
		}

		wp_enqueue_script( self::HEALTH_SCRIPT_HANDLE, 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External service script.
		wp_add_inline_script(
			self::HEALTH_SCRIPT_HANDLE,
			'window.ranTurnstileForJetpackFormsReady=function(){var button=document.getElementById("ran-turnstile-for-jetpack-forms-run-health-check");if(button){button.disabled=false;}};window.ranTurnstileForJetpackFormsExpired=function(){var button=document.getElementById("ran-turnstile-for-jetpack-forms-run-health-check");if(button){button.disabled=true;}};document.addEventListener("DOMContentLoaded",function(){var widget=document.querySelector("#ran-turnstile-for-jetpack-forms-health-check-form .cf-turnstile");var button=document.getElementById("ran-turnstile-for-jetpack-forms-run-health-check");if(widget&&button){button.disabled=true;}});',
			'before'
		);
	}

	/** Render the consumer-owned product overview and support links. */
	private static function render_overview() {
		?>
		<div class="ran-turnstile-overview">
			<section class="ran-turnstile-overview__panel" aria-labelledby="ran-turnstile-overview-title">
				<header class="ran-turnstile-overview__header">
					<div class="ran-turnstile-overview__brands" aria-hidden="true">
						<img class="ran-turnstile-overview__turnstile-logo" src="<?php echo esc_url( RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_URL . 'assets/cloudflare-turnstile-logo.svg' ); ?>" width="54" height="54" alt="" />
						<span class="ran-turnstile-overview__plus">+</span>
						<img class="ran-turnstile-overview__jetpack-logo" src="<?php echo esc_url( RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_URL . 'assets/jetpack-logo.svg' ); ?>" width="140" height="38" alt="" />
					</div>
					<h2 id="ran-turnstile-overview-title"><?php esc_html_e( 'Turnstile protection for Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ); ?></h2>
				</header>
				<p><?php esc_html_e( 'RAN Turnstile for Jetpack Forms adds Cloudflare Turnstile verification to every Jetpack Form on this site while preserving Jetpack’s existing form and Akismet workflow.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
				<p><?php esc_html_e( 'The plugin renders a Turnstile challenge with each protected form and verifies its token before the Jetpack submission continues. Configuration is site-wide; use the Settings tab to add credentials, choose the widget presentation and run a health check.', 'ran-turnstile-for-jetpack-forms' ); ?></p>
			</section>

			<section class="ran-turnstile-overview__panel" aria-labelledby="ran-turnstile-resources-title">
				<h2 id="ran-turnstile-resources-title"><?php esc_html_e( 'Documentation and support', 'ran-turnstile-for-jetpack-forms' ); ?></h2>
				<div class="ran-turnstile-overview__resources">
					<div class="ran-turnstile-overview__resource-group">
						<h3><?php esc_html_e( 'Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ); ?></h3>
						<ul>
							<li><a href="https://jetpack.com/forms/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
							<li><a href="https://jetpack.com/resources/wordpress-contact-form/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'How to create a WordPress contact form', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
							<li><a href="https://jetpack.com/support/jetpack-blocks/contact-form/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Jetpack Form block support', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
						</ul>
					</div>
					<div class="ran-turnstile-overview__resource-group">
						<h3><?php esc_html_e( 'Cloudflare Turnstile', 'ran-turnstile-for-jetpack-forms' ); ?></h3>
						<ul>
							<li><a href="https://developers.cloudflare.com/turnstile/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Turnstile documentation', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
							<li><a href="https://developers.cloudflare.com/turnstile/get-started/server-side-validation/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Turnstile validation', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
							<li><a href="https://developers.cloudflare.com/turnstile/troubleshooting/testing/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Turnstile testing keys', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
						</ul>
					</div>
					<div class="ran-turnstile-overview__resource-group">
						<h3><?php esc_html_e( 'Plugin support', 'ran-turnstile-for-jetpack-forms' ); ?></h3>
						<ul>
							<li><a href="https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report an issue on GitHub', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
							<li><a href="https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Plugin repository', 'ran-turnstile-for-jetpack-forms' ); ?></a></li>
						</ul>
					</div>
				</div>
			</section>
		</div>
		<?php
	}

	/** Render settings and diagnostics. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings          = Settings::get_all();
		$has_saved_secret  = '' !== (string) $settings['turnstile_secret_key'];
		$health            = self::get_health_result();
		$page_url          = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$active_tab        = self::get_active_tab();
		$footer_headers    = get_file_data(
			RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_FILE,
			array(
				'author'     => 'Author',
				'author_uri' => 'Author URI',
			),
			'plugin'
		);
		$footer_author     = is_string( $footer_headers['author'] ?? null ) ? trim( $footer_headers['author'] ) : '';
		$footer_author_url = is_string( $footer_headers['author_uri'] ?? null ) ? trim( $footer_headers['author_uri'] ) : '';
		$ran_admin_shell   = array(
			'name'             => __( 'RAN Turnstile for Jetpack Forms', 'ran-turnstile-for-jetpack-forms' ),
			'strapline'        => __( 'Protect your Jetpack forms with Cloudflare Turnstile.', 'ran-turnstile-for-jetpack-forms' ),
			'logo'             => array(
				'url'    => RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_URL . 'assets/ran-turnstile-mark.svg',
				'width'  => 54,
				'height' => 54,
			),
			'navigation_label' => __( 'RAN Turnstile', 'ran-turnstile-for-jetpack-forms' ),
			'navigation'       => array(
				array(
					'label'   => __( 'Overview', 'ran-turnstile-for-jetpack-forms' ),
					'url'     => $page_url,
					'current' => 'overview' === $active_tab,
				),
				array(
					'label'   => __( 'Settings', 'ran-turnstile-for-jetpack-forms' ),
					'url'     => add_query_arg( 'tab', 'settings', $page_url ),
					'current' => 'settings' === $active_tab,
				),
			),
		);
		?>
		<?php include RAN_TURNSTILE_FOR_JETPACK_FORMS_PLUGIN_DIR . 'includes/generated/ran-admin-shell.php'; ?>
		<div class="wrap">
			<?php if ( 'overview' === $active_tab ) : ?>
				<?php self::render_overview(); ?>
			<?php else : ?>
				<?php if ( Settings::has_legacy_runtime_conflict() ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Runtime protection is paused because RAN Octopus Forms still has Turnstile enabled. Disable the old feature before cutover; otherwise both plugins would render and validate a widget.', 'ran-turnstile-for-jetpack-forms' ); ?></p></div>
			<?php endif; ?>

			<form id="ran-turnstile-settings" class="ran-turnstile-settings-form" method="post" action="options.php">
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
							<input id="ran-turnstile-secret-key" class="regular-text code" type="password" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_secret_key]" value=""
							<?php if ( $has_saved_secret ) : ?>
								placeholder="••••••••••••"
							<?php endif; ?>
							autocomplete="new-password" aria-describedby="ran-turnstile-secret-key-description" />
							<p id="ran-turnstile-secret-key-description" class="description">
								<?php if ( $has_saved_secret ) : ?>
									<?php esc_html_e( 'Cloudflare secret saved.', 'ran-turnstile-for-jetpack-forms' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Enter the Cloudflare secret key. After it is saved, this field will show dots instead of the key.', 'ran-turnstile-for-jetpack-forms' ); ?>
								<?php endif; ?>
							</p>
						</div>
						<div id="ran-turnstile-local-testing" class="ran-turnstile-field">
							<span class="ran-turnstile-field-label"><?php esc_html_e( 'Local testing', 'ran-turnstile-for-jetpack-forms' ); ?></span>
							<?php self::render_local_testing_details(); ?>
						</div>
					</div>
				</fieldset>
				<?php submit_button( __( 'Save settings', 'ran-turnstile-for-jetpack-forms' ) ); ?>
			</form>

			<div id="ran-turnstile-troubleshooting" class="postbox ran-turnstile-settings-section">
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
			<?php endif; ?>
			<hr>
			<div class="ran-turnstile-footer">
				<p>
					<?php
					/* translators: %s: current year. */
					echo esc_html( sprintf( __( 'Copyright © %s', 'ran-turnstile-for-jetpack-forms' ), wp_date( 'Y' ) ) );
					?>
					<?php if ( '' !== $footer_author && '' !== $footer_author_url ) : ?>
						<a href="<?php echo esc_url( $footer_author_url ); ?>"><?php echo esc_html( $footer_author ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $footer_author ); ?>
					<?php endif; ?>
				</p>
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

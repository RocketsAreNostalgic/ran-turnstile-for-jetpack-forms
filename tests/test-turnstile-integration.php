<?php
/**
 * Integration coverage for settings, global protection, validation, and diagnostics.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

use RAN\TurnstileForJetpackForms\Admin;
use RAN\TurnstileForJetpackForms\HealthCheck;
use RAN\TurnstileForJetpackForms\Settings;
use RAN\TurnstileForJetpackForms\Turnstile;

/**
 * Verify the standalone plugin without activating it on the Local site.
 */
class RAN_Turnstile_For_Jetpack_Forms_Test extends WP_UnitTestCase {
	/** Reset options and request state. */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::OCTOPUS_OPTION_NAME );
		delete_site_option( 'active_sitewide_plugins' );
		update_option( 'active_plugins', array() );
		$GLOBALS['wp_settings_errors'] = array();
		$_POST                         = array();
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_environment_type' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_should_protect_form' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_widget_appearance' );
		wp_dequeue_script( Turnstile::CLIENT_SCRIPT_HANDLE );
		wp_deregister_script( Turnstile::CLIENT_SCRIPT_HANDLE );
		wp_dequeue_script( Turnstile::SCRIPT_HANDLE );
		wp_deregister_script( Turnstile::SCRIPT_HANDLE );
	}

	/** Restore request state. */
	public function tear_down() {
		$_POST = array();
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_environment_type' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_should_protect_form' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_widget_appearance' );
		delete_site_option( 'active_sitewide_plugins' );
		wp_dequeue_script( Turnstile::CLIENT_SCRIPT_HANDLE );
		wp_deregister_script( Turnstile::CLIENT_SCRIPT_HANDLE );
		wp_dequeue_script( Turnstile::SCRIPT_HANDLE );
		wp_deregister_script( Turnstile::SCRIPT_HANDLE );
		parent::tear_down();
	}

	/** Migration copies only relevant values and preserves the source. */
	public function test_migration_copies_turnstile_settings_without_mutating_source() {
		$source = array(
			'contact_page_id'      => 123,
			'turnstile_enabled'    => 1,
			'turnstile_site_key'   => ' site-key ',
			'turnstile_secret_key' => ' secret-key ',
			'emailoctopus_list_id' => 'must-not-migrate',
		);
		update_option( Settings::OCTOPUS_OPTION_NAME, $source );

		$this->assertTrue( Settings::migrate_from_octopus() );
		$this->assertSame( $source, get_option( Settings::OCTOPUS_OPTION_NAME ) );
		$this->assertSame(
			array(
				'turnstile_enabled'        => 1,
				'turnstile_always_visible' => 0,
				'turnstile_site_key'       => 'site-key',
				'turnstile_secret_key'     => 'secret-key',
			),
			get_option( Settings::OPTION_NAME )
		);
	}

	/** Existing new settings always win over later source changes. */
	public function test_migration_does_not_overwrite_existing_option() {
		update_option( Settings::OPTION_NAME, array( 'turnstile_site_key' => 'new' ) );
		update_option( Settings::OCTOPUS_OPTION_NAME, array( 'turnstile_site_key' => 'old' ) );

		$this->assertFalse( Settings::migrate_from_octopus() );
		$this->assertSame( 'new', get_option( Settings::OPTION_NAME )['turnstile_site_key'] );
	}

	/** Obsolete page targeting remains harmless for an upgraded installation. */
	public function test_existing_settings_ignore_obsolete_contact_page_id() {
		update_option(
			Settings::OPTION_NAME,
			array(
				'contact_page_id'      => 123,
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => 'site-key',
				'turnstile_secret_key' => 'secret-key',
			)
		);

		$settings = Settings::get_all();

		$this->assertArrayNotHasKey( 'contact_page_id', $settings );
		$this->assertSame( 1, $settings['turnstile_enabled'] );
		$this->assertSame( 0, $settings['turnstile_always_visible'] );
	}

	/** Sanitization retains a blank secret and sets local keys intentionally. */
	public function test_sanitize_retains_secret_and_supports_local_setup() {
		update_option( Settings::OPTION_NAME, array( 'turnstile_secret_key' => 'stored-secret' ) );

		$settings = Settings::sanitize(
			array(
				'turnstile_enabled'        => 1,
				'turnstile_always_visible' => 1,
				'turnstile_site_key'       => 'updated-site',
				'turnstile_secret_key'     => '',
			)
		);

		$this->assertSame( 'stored-secret', $settings['turnstile_secret_key'] );
		$this->assertSame( 'updated-site', $settings['turnstile_site_key'] );
		$this->assertSame( 1, $settings['turnstile_always_visible'] );

		$local = Settings::sanitize( array( 'turnstile_setup_local_dev' => 1 ) );

		$this->assertSame( 1, $local['turnstile_enabled'] );
		$this->assertSame( Settings::TURNSTILE_TEST_SITE_KEY, $local['turnstile_site_key'] );
		$this->assertSame( Settings::TURNSTILE_TEST_SECRET_KEY, $local['turnstile_secret_key'] );
		$this->assertSame( 0, $local['turnstile_always_visible'] );
	}

	/** Every rendered Jetpack form receives its own widget and the script loads lazily. */
	public function test_widget_is_added_to_every_jetpack_form() {
		$this->configure_enabled_plugin();

		$this->assertFalse( wp_script_is( Turnstile::SCRIPT_HANDLE, 'enqueued' ) );

		$first  = Turnstile::append_widget( $this->form_html( '101', 'first-hash' ) );
		$second = Turnstile::append_widget( $this->form_html( '202', 'second-hash' ) );

		$this->assertStringContainsString( 'class="ran-turnstile-for-jetpack-forms"', $first );
		$this->assertStringContainsString( 'class="ran-turnstile-for-jetpack-forms"', $second );
		$this->assertStringContainsString( 'data-response-field-name="' . Turnstile::RESPONSE_FIELD . '"', $first );
		$this->assertStringContainsString( 'data-appearance="interaction-only"', $first );
		$this->assertMatchesRegularExpression( '/id="ran-turnstile-widget-\d+"/', $first );
		$this->assertTrue( wp_script_is( Turnstile::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( Turnstile::CLIENT_SCRIPT_HANDLE, 'enqueued' ) );
	}

	/** The one presentation toggle can force visible frontend widgets. */
	public function test_visibility_toggle_and_filter_control_frontend_appearance() {
		$this->configure_enabled_plugin();
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_all(), array( 'turnstile_always_visible' => 1 ) )
		);

		$always = Turnstile::append_widget( $this->form_html( '101', 'always-hash' ) );
		$this->assertStringContainsString( 'data-appearance="always"', $always );

		add_filter(
			'ran_turnstile_for_jetpack_forms_widget_appearance',
			static function ( $appearance, $context ) {
				return 'filtered-hash' === $context['form_hash'] ? 'interaction-only' : $appearance;
			},
			10,
			2
		);

		$filtered = Turnstile::append_widget( $this->form_html( '202', 'filtered-hash' ) );
		$this->assertStringContainsString( 'data-appearance="interaction-only"', $filtered );
	}

	/** The admin exposes one toggle while keeping diagnostics visibly rendered. */
	public function test_admin_visibility_toggle_does_not_hide_health_widget() {
		$this->configure_enabled_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		Admin::render_page();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, Settings::OPTION_NAME . '[turnstile_always_visible]' ) );
		$this->assertStringContainsString( 'only when Cloudflare requires visitor interaction (recommended)', $html );
		$this->assertMatchesRegularExpression( '/id="ran-turnstile-for-jetpack-forms-health-check-form"[\s\S]+class="cf-turnstile"[^>]+data-appearance="always"/', $html );
	}

	/** Runtime hooks run late for collision detection and before Akismet for validation. */
	public function test_runtime_hook_priorities_are_intentional() {
		$this->assertSame( PHP_INT_MAX, has_filter( 'jetpack_contact_form_html', array( Turnstile::class, 'append_widget' ) ) );
		$this->assertSame( 5, has_filter( 'jetpack_contact_form_is_spam', array( Turnstile::class, 'validate_submission' ) ) );
	}

	/** Filtering the same form twice cannot duplicate its widget. */
	public function test_widget_insertion_is_idempotent() {
		$this->configure_enabled_plugin();

		$once  = Turnstile::append_widget( $this->form_html() );
		$twice = Turnstile::append_widget( $once );

		$this->assertSame( 1, substr_count( $twice, 'class="ran-turnstile-for-jetpack-forms"' ) );
		$this->assertSame( 1, substr_count( $twice, 'class="cf-turnstile"' ) );
	}

	/** Disabled protection leaves forms and submissions untouched. */
	public function test_disabled_plugin_leaves_forms_and_submissions_unchanged() {
		$html     = $this->form_html();
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				return false;
			}
		);

		$this->assertSame( $html, Turnstile::append_widget( $html ) );
		$this->assertFalse( Turnstile::validate_submission( false, array() ) );
		$this->assertFalse( wp_script_is( Turnstile::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertSame( 0, $requests );
	}

	/** The public filter can consistently delegate one form to another integration. */
	public function test_exclusion_filter_skips_rendering_and_submission() {
		$this->configure_enabled_plugin();
		$contexts = array();
		$requests = 0;
		add_filter(
			'ran_turnstile_for_jetpack_forms_should_protect_form',
			static function ( $protect, $context ) use ( &$contexts ) {
				$contexts[] = $context;
				return 'excluded-hash' === $context['form_hash'] ? false : $protect;
			},
			10,
			2
		);
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				return false;
			}
		);

		$html = $this->form_html( '303', 'excluded-hash' );
		$this->assertSame( $html, Turnstile::append_widget( $html ) );
		$this->prepare_submission( '303', 'excluded-hash' );
		$this->assertFalse( Turnstile::validate_submission( false, array() ) );

		$this->assertSame( array( 'render', 'submission' ), wp_list_pluck( $contexts, 'phase' ) );
		$this->assertSame( 303, $contexts[0]['post_id'] );
		$this->assertFalse( wp_script_is( Turnstile::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertSame( 0, $requests );
	}

	/** Enabled protection fails closed when the browser provides no token. */
	public function test_missing_token_fails_closed_without_http_request() {
		$this->configure_enabled_plugin();
		$this->prepare_submission();
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				return false;
			}
		);

		$result = Turnstile::validate_submission( false, array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ran_turnstile_for_jetpack_forms_missing', $result->get_error_code() );
		$this->assertSame( 0, $requests );
	}

	/** Successful validation sends a valid remote IP and preserves false state. */
	public function test_validation_success_includes_valid_remote_ip() {
		$this->configure_enabled_plugin();
		$this->prepare_submission( '101', 'form-hash', 'accepted-token' );
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.9';
		$captured_body                    = array();
		$this->mock_cloudflare_success( $captured_body );

		$this->assertFalse( Turnstile::validate_submission( false, array() ) );
		$this->assertSame( '203.0.113.9', $captured_body['remoteip'] );
		$this->assertSame( 'accepted-token', $captured_body['response'] );
	}

	/** Invalid IP values are not forwarded to Cloudflare. */
	public function test_validation_omits_invalid_remote_ip() {
		$this->configure_enabled_plugin();
		$this->prepare_submission( '101', 'form-hash', 'accepted-token' );
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		$captured_body          = array();
		$this->mock_cloudflare_success( $captured_body );

		Turnstile::validate_submission( false, array() );
		$this->assertArrayNotHasKey( 'remoteip', $captured_body );
	}

	/** Failed verification returns a visitor-safe WP_Error. */
	public function test_validation_failure_returns_wp_error() {
		$this->configure_enabled_plugin();
		$this->prepare_submission( '101', 'form-hash', 'rejected-token' );
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => '{"success":false,"error-codes":["invalid-input-response"]}',
					'response' => array( 'code' => 200 ),
					'cookies'  => array(),
				);
			}
		);

		$result = Turnstile::validate_submission( false, array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ran_turnstile_for_jetpack_forms_failed', $result->get_error_code() );
	}

	/** Existing spam and error states bypass Cloudflare unchanged. */
	public function test_preexisting_spam_and_error_states_are_preserved() {
		$this->configure_enabled_plugin();
		$this->prepare_submission( '101', 'form-hash', 'accepted-token' );
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				return false;
			}
		);
		$error = new WP_Error( 'existing', 'Existing failure' );

		$this->assertTrue( Turnstile::validate_submission( true, array() ) );
		$this->assertSame( $error, Turnstile::validate_submission( $error, array() ) );
		$this->assertSame( 0, $requests );
	}

	/** A valid challenge continues into Akismet-like filters at priority ten. */
	public function test_successful_turnstile_result_continues_to_akismet() {
		$this->configure_enabled_plugin();
		$this->prepare_submission( '101', 'form-hash', 'accepted-token' );
		$captured_body = array();
		$called        = false;
		$this->mock_cloudflare_success( $captured_body );
		$akismet = static function ( $is_spam ) use ( &$called ) {
			$called = true;
			return false === $is_spam;
		};
		add_filter( 'jetpack_contact_form_is_spam', $akismet, 10 );

		$result = apply_filters( 'jetpack_contact_form_is_spam', false, array() );

		remove_filter( 'jetpack_contact_form_is_spam', $akismet, 10 );
		$this->assertTrue( $called );
		$this->assertTrue( $result );
	}

	/** A later spam filter receives an existing Turnstile error unchanged. */
	public function test_turnstile_error_is_preserved_for_later_spam_filters() {
		$this->configure_enabled_plugin();
		$this->prepare_submission();
		$received = null;
		$later    = static function ( $is_spam ) use ( &$received ) {
			$received = $is_spam;
			return $is_spam;
		};
		add_filter( 'jetpack_contact_form_is_spam', $later, 10 );

		$result = apply_filters( 'jetpack_contact_form_is_spam', false, array() );

		remove_filter( 'jetpack_contact_form_is_spam', $later, 10 );
		$this->assertWPError( $result );
		$this->assertSame( $result, $received );
	}

	/** A foreign widget is not duplicated or silently accepted as our own. */
	public function test_foreign_turnstile_widget_fails_closed_as_a_conflict() {
		$this->configure_enabled_plugin();
		$foreign = str_replace(
			'<div class="wp-block-button">',
			'<div class="cf-turnstile" data-sitekey="foreign"></div><div class="wp-block-button">',
			$this->form_html()
		);

		$updated = Turnstile::append_widget( $foreign );
		$updated = Turnstile::append_widget( $updated );

		$this->assertSame( 1, substr_count( $updated, 'class="cf-turnstile"' ) );
		$this->assertSame( 1, substr_count( $updated, 'name="' . Turnstile::CONFLICT_FIELD . '"' ) );
		$this->assertFalse( wp_script_is( Turnstile::SCRIPT_HANDLE, 'enqueued' ) );

		$this->prepare_submission();
		$_POST[ Turnstile::CONFLICT_FIELD ] = '1';
		$result                             = Turnstile::validate_submission( false, array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ran_turnstile_for_jetpack_forms_conflict', $result->get_error_code() );
	}

	/** Unrelated CAPTCHA markup is not mistaken for a Turnstile conflict. */
	public function test_other_captcha_markup_does_not_disable_turnstile() {
		$this->configure_enabled_plugin();
		$html = str_replace(
			'<div class="wp-block-button">',
			'<div class="g-recaptcha"></div><div class="wp-block-button">',
			$this->form_html()
		);

		$this->assertStringContainsString( 'class="ran-turnstile-for-jetpack-forms"', Turnstile::append_widget( $html ) );
	}

	/** Text that happens to mention a class name is not a structural conflict. */
	public function test_turnstile_class_names_in_text_do_not_suppress_widget() {
		$this->configure_enabled_plugin();
		$html = str_replace(
			'<div class="wp-block-button">',
			'<p>Help for cf-turnstile and ran-turnstile-for-jetpack-forms.</p><div class="wp-block-button">',
			$this->form_html()
		);

		$updated = Turnstile::append_widget( $html );

		$this->assertStringContainsString( 'class="ran-turnstile-for-jetpack-forms"', $updated );
		$this->assertStringNotContainsString( 'name="' . Turnstile::CONFLICT_FIELD . '"', $updated );
	}

	/** Always-pass keys are unavailable in production. */
	public function test_production_environment_blocks_always_pass_keys() {
		update_option(
			Settings::OPTION_NAME,
			array(
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => Settings::TURNSTILE_TEST_SITE_KEY,
				'turnstile_secret_key' => Settings::TURNSTILE_TEST_SECRET_KEY,
			)
		);
		add_filter( 'ran_turnstile_for_jetpack_forms_environment_type', static fn() => 'production' );

		$this->assertTrue( Settings::blocks_turnstile_test_keys() );
		$this->assertFalse( Settings::can_use_turnstile() );
	}

	/** Old active protection is detected without calling old plugin code. */
	public function test_legacy_runtime_conflict_is_detected() {
		update_option( 'active_plugins', array( Settings::OCTOPUS_PLUGIN_BASENAME ) );
		update_option( Settings::OCTOPUS_OPTION_NAME, array( 'turnstile_enabled' => 1 ) );

		$this->assertTrue( Settings::has_legacy_runtime_conflict() );
	}

	/** A network-active legacy plugin is also a duplicate runtime. */
	public function test_network_active_legacy_runtime_conflict_is_detected() {
		update_site_option( 'active_sitewide_plugins', array( Settings::OCTOPUS_PLUGIN_BASENAME => time() ) );
		update_option( Settings::OCTOPUS_OPTION_NAME, array( 'turnstile_enabled' => 1 ) );

		$this->assertTrue( Settings::has_legacy_runtime_conflict() );

		delete_site_option( 'active_sitewide_plugins' );
	}

	/** Health check reports global scope and the isolated Jetpack runtime accurately. */
	public function test_health_check_reports_global_coverage_and_disabled_turnstile() {
		$result   = HealthCheck::run();
		$labels   = wp_list_pluck( $result['checks'], 'label' );
		$statuses = wp_list_pluck( $result['checks'], 'status', 'label' );

		$this->assertContains( 'Jetpack Forms coverage', $labels );
		$this->assertContains( 'Turnstile', $labels );
		$this->assertSame( 'skipped', $statuses['Jetpack Forms coverage'] );
		$this->assertSame( 'error', $statuses['Jetpack Forms'], 'This plugin must not mistake its own Jetpack filter registration for the Jetpack Forms runtime.' );
		$this->assertSame( 'error', $result['overall'], 'The isolated test runtime deliberately does not load Jetpack Forms.' );
	}

	/** Coverage cannot report success when enabled protection is misconfigured. */
	public function test_health_check_reports_unusable_global_coverage() {
		update_option( Settings::OPTION_NAME, array( 'turnstile_enabled' => 1 ) );

		$result   = HealthCheck::run();
		$statuses = wp_list_pluck( $result['checks'], 'status', 'label' );

		$this->assertSame( 'error', $statuses['Jetpack Forms coverage'] );
	}

	/** Configure enabled non-test keys. */
	private function configure_enabled_plugin() {
		update_option(
			Settings::OPTION_NAME,
			array(
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => 'site-key',
				'turnstile_secret_key' => 'secret-key',
			)
		);
	}

	/** Get representative Jetpack form HTML. */
	private function form_html( $form_id = '101', $form_hash = 'form-hash' ) {
		return '<form class="jetpack-contact-form"><input type="hidden" name="contact-form-id" value="' . esc_attr( $form_id ) . '" /><input type="hidden" name="contact-form-hash" value="' . esc_attr( $form_hash ) . '" /><div class="wp-block-button"><button type="submit">Send</button></div></form>';
	}

	/** Prepare Jetpack form identity and an optional plugin-owned token. */
	private function prepare_submission( $form_id = '101', $form_hash = 'form-hash', $token = '' ) {
		$_POST = array(
			'contact-form-id'   => $form_id,
			'contact-form-hash' => $form_hash,
		);

		if ( '' !== $token ) {
			$_POST[ Turnstile::RESPONSE_FIELD ] = $token;
		}
	}

	/** Mock an accepted Cloudflare response and capture its request body. */
	private function mock_cloudflare_success( &$captured_body ) {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$captured_body ) {
				unset( $preempt, $url );
				$captured_body = $args['body'];
				return array(
					'headers'  => array(),
					'body'     => '{"success":true}',
					'response' => array( 'code' => 200 ),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
	}
}

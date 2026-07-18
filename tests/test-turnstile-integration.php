<?php
/**
 * Integration coverage for settings, targeting, validation, and diagnostics.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

use RAN\TurnstileForJetpackForms\FormTarget;
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
		update_option( 'active_plugins', array() );
		$GLOBALS['wp_settings_errors'] = array();
		$_POST                         = array();
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_environment_type' );
	}

	/** Restore request state. */
	public function tear_down() {
		$_POST = array();
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'ran_turnstile_for_jetpack_forms_environment_type' );
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
				'contact_page_id'      => 123,
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => 'site-key',
				'turnstile_secret_key' => 'secret-key',
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

	/** Sanitization retains a blank secret and sets local keys intentionally. */
	public function test_sanitize_retains_secret_and_supports_local_setup() {
		$page_id = $this->create_contact_page();
		update_option(
			Settings::OPTION_NAME,
			array(
				'contact_page_id'      => $page_id,
				'turnstile_secret_key' => 'stored-secret',
			)
		);

		$settings = Settings::sanitize(
			array(
				'contact_page_id'      => $page_id,
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => 'updated-site',
				'turnstile_secret_key' => '',
			)
		);

		$this->assertSame( 'stored-secret', $settings['turnstile_secret_key'] );
		$this->assertSame( 'updated-site', $settings['turnstile_site_key'] );

		$local = Settings::sanitize(
			array(
				'contact_page_id'           => $page_id,
				'turnstile_setup_local_dev' => 1,
			)
		);

		$this->assertSame( 1, $local['turnstile_enabled'] );
		$this->assertSame( Settings::TURNSTILE_TEST_SITE_KEY, $local['turnstile_site_key'] );
		$this->assertSame( Settings::TURNSTILE_TEST_SECRET_KEY, $local['turnstile_secret_key'] );
	}

	/** Pages without exactly one form are rejected. */
	public function test_sanitize_rejects_ambiguous_target_page() {
		$current_page = $this->create_contact_page();
		$bad_page     = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->form_block() . $this->form_block(),
			)
		);
		$current      = array_merge( Settings::get_defaults(), array( 'contact_page_id' => $current_page ) );
		update_option( Settings::OPTION_NAME, $current );

		$input                    = $current;
		$input['contact_page_id'] = $bad_page;

		$this->assertSame( $current, Settings::sanitize( $input ) );
		$this->assertSame( 'ran_turnstile_for_jetpack_forms_invalid_contact_page', get_settings_errors( Settings::OPTION_NAME )[0]['code'] );
	}

	/** Both marker generations remain valid targets. */
	public function test_new_and_legacy_form_markers_are_supported() {
		$page_id = $this->create_contact_page();
		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );

		$this->assertTrue( Settings::is_target_contact_form_block( $this->parsed_form( Settings::TARGET_FORM_CLASS ) ) );
		$this->assertTrue( Settings::is_target_contact_form_block( $this->parsed_form( Settings::LEGACY_TARGET_FORM_CLASS ) ) );
	}

	/** The rendered target receives a nonce marker and no other form does. */
	public function test_target_form_receives_submission_marker() {
		$page_id = $this->create_contact_page();
		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );
		$this->go_to( get_permalink( $page_id ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		FormTarget::before_render_block( null, $this->parsed_form( '' ) );
		$html = FormTarget::mark_target_form_submission( '<form class="jetpack-contact-form"></form>' );
		FormTarget::after_render_block( '', $this->parsed_form( '' ) );

		$this->assertStringContainsString( 'ran_turnstile_for_jetpack_forms_target', $html );
		$this->assertSame( '<form></form>', FormTarget::mark_target_form_submission( '<form></form>' ) );
	}

	/** Widget insertion is limited to the selected page and marked form. */
	public function test_widget_insertion_is_scoped_to_target_form() {
		$page_id = $this->configure_enabled_plugin();
		$this->go_to( get_permalink( $page_id ) );
		$target = '<form class="' . Settings::LEGACY_TARGET_FORM_CLASS . '"><div class="wp-block-button"><button>Send</button></div></form>';
		$other  = '<form><div class="wp-block-button"><button>Send</button></div></form>';

		$this->assertStringContainsString( 'cf-turnstile', Turnstile::append_widget( $target ) );
		$this->assertSame( $other, Turnstile::append_widget( $other ) );
	}

	/** Successful validation sends a valid remote IP and preserves false state. */
	public function test_validation_success_includes_valid_remote_ip() {
		$page_id = $this->configure_enabled_plugin();
		$this->prepare_target_submission( $page_id, 'accepted-token' );
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.9';
		$captured_body                    = array();

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

		$this->assertFalse( Turnstile::validate_submission( false, array() ) );
		$this->assertSame( '203.0.113.9', $captured_body['remoteip'] );
		$this->assertSame( 'accepted-token', $captured_body['response'] );
	}

	/** Invalid IP values are not forwarded to Cloudflare. */
	public function test_validation_omits_invalid_remote_ip() {
		$page_id = $this->configure_enabled_plugin();
		$this->prepare_target_submission( $page_id, 'accepted-token' );
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		$captured_body          = array();

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args ) use ( &$captured_body ) {
				unset( $preempt );
				$captured_body = $args['body'];
				return array(
					'headers'  => array(),
					'body'     => '{"success":true}',
					'response' => array( 'code' => 200 ),
					'cookies'  => array(),
				);
			},
			10,
			2
		);

		Turnstile::validate_submission( false, array() );
		$this->assertArrayNotHasKey( 'remoteip', $captured_body );
	}

	/** Failed verification returns a visitor-safe WP_Error. */
	public function test_validation_failure_returns_wp_error() {
		$page_id = $this->configure_enabled_plugin();
		$this->prepare_target_submission( $page_id, 'rejected-token' );
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

	/** Health check is read-only and reports the disabled state safely. */
	public function test_health_check_reports_target_and_disabled_turnstile() {
		$page_id = $this->create_contact_page();
		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => '<form class="jetpack-contact-form"></form>',
					'response' => array( 'code' => 200 ),
					'cookies'  => array(),
				);
			}
		);

		$result   = HealthCheck::run();
		$labels   = wp_list_pluck( $result['checks'], 'label' );
		$statuses = wp_list_pluck( $result['checks'], 'status', 'label' );

		$this->assertContains( 'Jetpack form count', $labels );
		$this->assertContains( 'Turnstile', $labels );
		$this->assertSame( 'error', $statuses['Jetpack Forms'], 'This plugin must not mistake its own Jetpack filter registration for the Jetpack Forms runtime.' );
		$this->assertSame( 'error', $result['overall'], 'The isolated test runtime deliberately does not load Jetpack Forms.' );
	}

	/** Create a published page with one contact form. */
	private function create_contact_page( $class_name = '' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->form_block( $class_name ),
			)
		);
	}

	/** Get contact-form block markup. */
	private function form_block( $class_name = '' ) {
		$attrs = '' === $class_name ? '{}' : wp_json_encode( array( 'className' => $class_name ) );

		return '<!-- wp:jetpack/contact-form ' . $attrs . ' --><div class="wp-block-jetpack-contact-form"></div><!-- /wp:jetpack/contact-form -->';
	}

	/** Get one parsed form block. */
	private function parsed_form( $class_name ) {
		return parse_blocks( $this->form_block( $class_name ) )[0];
	}

	/** Configure enabled non-test keys. */
	private function configure_enabled_plugin() {
		$page_id = $this->create_contact_page( Settings::LEGACY_TARGET_FORM_CLASS );
		update_option(
			Settings::OPTION_NAME,
			array(
				'contact_page_id'      => $page_id,
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => 'site-key',
				'turnstile_secret_key' => 'secret-key',
			)
		);

		return $page_id;
	}

	/** Prepare a nonce-marked request. */
	private function prepare_target_submission( $page_id, $token ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'contact-form-id'                        => (string) $page_id,
			'ran_turnstile_for_jetpack_forms_target' => wp_create_nonce( 'ran_turnstile_for_jetpack_forms_target_' . $page_id ),
			'cf-turnstile-response'                  => $token,
		);
	}
}

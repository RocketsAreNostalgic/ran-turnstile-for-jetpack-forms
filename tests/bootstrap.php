<?php
/**
 * WordPress integration-test bootstrap.
 *
 * @package RAN_Turnstile_For_Jetpack_Forms
 */

$_tests_dir  = getenv( 'WP_TESTS_DIR' );
$_vendor_dir = getenv( 'RAN_TURNSTILE_VENDOR_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! $_vendor_dir ) {
	$_vendor_dir = dirname( __DIR__ ) . '/vendor';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library is not installed. Set WP_TESTS_DIR before running PHPUnit.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Runs before WordPress loads.
	exit( 1 );
}

if ( file_exists( $_vendor_dir . '/autoload.php' ) ) {
	require_once $_vendor_dir . '/autoload.php';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_vendor_dir . '/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/ran-turnstile-for-jetpack-forms.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

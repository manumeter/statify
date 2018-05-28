<?php
/**
 * Unit Test for Statify_Frontend class
 *
 * @package   Statify
 */

use Brain\Monkey;
use Brain\Monkey\Filters;

require_once __DIR__ . '/../inc/class-statify.php';
require_once __DIR__ . '/../inc/class-statify-frontend.php';

/**
 * Class Statify_Frontend_Test
 */
class Statify_Frontend_Test extends \PHPUnit\Framework\TestCase {

	const TARGET_1 = '/some/page/';
	const TARGET_2 = '/another/page/';
	const REFERRER = 'https://pluginkollektiv.org';
	const UA_BLOCKED = 'curl/7.58.0';
	const UA_VALID = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.170 Safari/537.36 OPR/53.0.2907.68';

	/**
	 * Set up test.
	 */
	protected function setUp() {
		parent::setUp();
		Monkey\setUp();

		global $_SERVER;
		global $wpdb;
		global $wp_rewrite;

		$wpdb                            = Mockery::mock( '\WPDB' );
		$wpdb->statify                   = 'statify';
		$wp_rewrite                      = Mockery::mock( '\WP_Rewrite' );
		$wp_rewrite->permalink_structure = null;
	}

	/**
	 * Tear down test.
	 */
	protected function tearDown() {
		Monkey\tearDown();
		parent::tearDown();
	}


	/**
	 * Test the track_visit() method.
	 *
	 * @return void
	 */
	public function test_track_visit() {
		global $wpdb;

		// Without valid target and user agent nothing should happen.
		$result = Statify_Frontend::track_visit();
		$this->assertFalse( $result );
		$wpdb->shouldNotHaveReceived( 'insert' );

		// Set target, still nothing expected.
		$_SERVER['REQUEST_URI'] = self::TARGET_1;
		Statify_Frontend::track_visit();
		$wpdb->shouldNotHaveReceived( 'insert' );

		// Set some blacklisted user agent.
		$_SERVER['HTTP_USER_AGENT'] = self::UA_BLOCKED;
		Statify_Frontend::track_visit();
		$wpdb->shouldNotHaveReceived( 'insert' );

		// Set valid user agent, this time an entry should be generated.
		$_SERVER['HTTP_USER_AGENT'] = self::UA_VALID;

		$capture   = null;
		$insert_id = 0;
		$wpdb->shouldReceive( 'insert' )
			->times( 2 )
			->andSet( 'insert_id', ++ $insert_id )
			->with( 'statify', Mockery::on( function ( $arg ) use ( &$capture ) {
				$capture = $arg;

				return true;
			} ) );

		Statify_Frontend::track_visit();
		$wpdb->shouldHaveReceived( 'insert' );

		// Validate captured insert.
		$this->assertNotNull( $capture );
		$this->assertEquals( 3, count( $capture ) );
		$this->assertEquals( date( 'Y-m-d' ), $capture['created'], 'Unexpected creation date' );
		$this->assertEmpty( $capture['referrer'], 'Referrer inserted where non was present' );
		$this->assertEquals( self::TARGET_1, $capture['target'], 'Unexpected target inserted' );

		// Set referrer.
		$_SERVER['REQUEST_URI']  = self::TARGET_2;
		$_SERVER['HTTP_REFERER'] = self::REFERRER;

		Statify_Frontend::track_visit();
		$wpdb->shouldHaveReceived( 'insert' );
		$this->assertEquals( self::REFERRER, $capture['referrer'], 'Unexpected referrer inserted' );
		$this->assertEquals( self::TARGET_2, $capture['target'], 'Unexpected target inserted' );

		// Not test excluded cases (fails, because receive limit was 2).
		global $mock;
		$mock->is_feed = true;
		Statify_Frontend::track_visit();
		$mock->is_feed      = false;
		$mock->is_trackback = true;
		Statify_Frontend::track_visit();
		$mock->is_trackback = false;
		$mock->is_404       = true;
		Statify_Frontend::track_visit();
		$mock->is_404    = false;
		$mock->is_robots = true;
		Statify_Frontend::track_visit();
		$mock->is_robots = false;
		$mock->is_user_logged_in = true;
		Statify_Frontend::track_visit();
		$mock->is_user_logged_in = false;
		$mock->is_preview = true;
		Statify_Frontend::track_visit();
		$mock->is_preview = false;
		$mock->is_search = true;
		Statify_Frontend::track_visit();
		$mock->is_search = false;
	}

	/**
	 * Test the "statify__skip_tracking" hook to exclude tracking, even if included regularily.
	 *
	 * @return void
	 */
	public function test_skip_tracking_hook_negative() {
		global $wpdb;

		$capture   = null;
		$insert_id = 0;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andSet( 'insert_id', ++ $insert_id )
			->with(
				'statify',
				Mockery::on(
					function ( $arg ) use ( &$capture ) {
						$capture = $arg;

						return true;
					}
				)
			);

		// Initialize query with yet excluded user agent.
		$_SERVER['REQUEST_URI'] = self::TARGET_1;
		$_SERVER['HTTP_REFERER'] = self::REFERRER;
		$_SERVER['HTTP_USER_AGENT'] = self::UA_BLOCKED;

		$result = Statify_Frontend::track_visit();
		$this->assertFalse( $result );

		// No tracking.
		$wpdb->shouldNotHaveReceived( 'insert' );

		// Filter should have fired.
		$this->assertEquals( 1, Filters\applied( 'statify__skip_tracking' ), 'Filter hook did not fire' );

		// Now filter returns FALSE, following checks should be skipped and user tracked.
		Filters\expectApplied( 'statify__skip_tracking' )->once()->andReturn( false );

		Statify_Frontend::track_visit();
		$this->assertEquals( 2, Filters\applied( 'statify__skip_tracking' ), 'Filter hook did not fire' );
		$wpdb->shouldHaveReceived( 'insert' );
	}

	/**
	 * Test the "statify__skip_tracking" hook to include tracking, even if excluded regularily.
	 *
	 * @return void
	 */
	public function test_skip_tracking_hook_positive() {
		global $wpdb;

		// Set valid user agent, this time an entry should be generated.
		$capture   = null;
		$insert_id = 0;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andSet( 'insert_id', ++ $insert_id )
			->with(
				'statify',
				Mockery::on(
					function ( $arg ) use ( &$capture ) {
						$capture = $arg;

						return true;
					}
				)
			);

		// Initialize valid query.
		$_SERVER['REQUEST_URI'] = self::TARGET_1;
		$_SERVER['HTTP_REFERER'] = self::REFERRER;
		$_SERVER['HTTP_USER_AGENT'] = self::UA_VALID;

		// Should be tracked successfully.
		$result = Statify_Frontend::track_visit();
		$this->assertFalse( $result );
		$this->assertEquals( 1, Filters\applied( 'statify__skip_tracking' ), 'Filter hook did not fire' );
		$wpdb->shouldHaveReceived( 'insert' );

		// And check the other way, filter returns TRUE (skip).
		Filters\expectApplied( 'statify__skip_tracking' )->once()->andReturn( true );

		Statify_Frontend::track_visit();
		$this->assertEquals( 2, Filters\applied( 'statify__skip_tracking' ), 'Filter hook did not fire' );
		// Test fails, if 'insert' has been called a second time.
	}
}

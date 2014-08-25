<?php

/**
 * @group option
 */
class Tests_Option_Transient extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
		// make sure the schedule is clear
		_set_cron_array(array());
	}

	function tearDown() {
		parent::tearDown();
		// make sure the schedule is clear
		_set_cron_array(array());
	}

	function test_the_basics() {
		$key = rand_str();
		$value = rand_str();
		$value2 = rand_str();

		$this->assertFalse( get_soft_transient( 'doesnotexist' ) );
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertFalse( set_soft_transient( $key, $value ) );
		$this->assertTrue( set_soft_transient( $key, $value2 ) );
		$this->assertEquals( $value2, get_soft_transient( $key ) );
		$this->assertTrue( delete_soft_transient( $key ) );
		$this->assertFalse( get_soft_transient( $key ) );
		$this->assertFalse( delete_soft_transient( $key ) );
	}

	function test_serialized_data() {
		$key = rand_str();
		$value = array( 'foo' => true, 'bar' => true );

		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		$value = (object) $value;
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( delete_soft_transient( $key ) );
	}

	function test_transient_data_with_timeout() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Not testable in MS: wpmu_create_blog() defines WP_INSTALLING.' );
		}

		$key = rand_str();
		$value = rand_str();

		$this->assertTrue( set_soft_transient( $key, $value, 100, 'test_soft_transient_1' ) );

		// Update the timeout to a second in the past and watch the transient be invalidated.
		$stored_value = get_option( '_transient_' . $key );
		$this->assertFalse( empty( $stored_value['expiration'] ) );
		$stored_value['expiration'] = time() - 1;
		update_option( '_transient_' . $key, $stored_value );

		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'test_soft_transient_1', array( $key ) ) > 0 );
	}

	function test_transient_add_timeout() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Not testable in MS: wpmu_create_blog() defines WP_INSTALLING.' );
		}

		$key = rand_str();
		$value = rand_str();
		$value2 = rand_str();
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		$this->assertTrue( empty( $stored_value['expiration'] ) );

		// Add timeout to existing timeout-less transient.
		$this->assertTrue( set_soft_transient( $key, $value2, 1, 'test_soft_transient_2' ) );
		$stored_value = get_option( '_transient_' . $key );
		$this->assertFalse( empty( $stored_value['expiration'] ) );
		$stored_value['expiration'] = time() - 1;
		update_option( '_transient_' . $key, $stored_value );

		$this->assertEquals( $value2, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'test_soft_transient_2', array( $key ) ) > 0 );
	}
}

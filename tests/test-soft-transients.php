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

	public function test_soft_transient_default_actions() {
		$key = rand_str();
		$value = rand_str();

		$this->assertTrue( set_soft_transient( $key, $value, 100 ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		// Get the actual stored value of the transient and expire it
		$stored_value = get_transient( $key );
		$this->assertTrue( array_key_exists( 'action', $stored_value ) );
		$this->assertEquals( null, $stored_value['action'] );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		// Ensure that when the expired transient is accessed, deletion is
		// scheduled with the default action
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'transient_refresh_' . $key, array( $key ) ) > 0 );
		$this->assertTrue( delete_soft_transient( $key ) );
		$this->assertFalse( wp_next_scheduled( 'transient_refresh_' . $key, array( $key ) ) );
	}

	public function test_soft_transient_custom_actions() {
		$key = rand_str();
		$value = rand_str();

		// Create the transient with a custom action
		$this->assertTrue( set_soft_transient( $key, $value, 100, 'test_soft_transient_0' ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		// Get the actual stored value of the transient and expire it
		$stored_value = get_transient( $key );
		$this->assertFalse( empty( $stored_value['action'] ) );
		$this->assertEquals( 'test_soft_transient_0', $stored_value['action'] );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		// Ensure that when the expired transient is accessed, deletion is
		// scheduled with the custom action
		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'test_soft_transient_0', array( $key ) ) > 0 );
		$this->assertTrue( delete_soft_transient( $key, 'test_soft_transient_0' ) );
		$this->assertFalse( wp_next_scheduled( 'test_soft_transient_0', array( $key ) ) );
	}

	function test_soft_transient_data_with_timeout() {
		$key = rand_str();
		$value = rand_str();

		$this->assertTrue( set_soft_transient( $key, $value, 100, 'test_soft_transient_1' ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		// Update the timeout to a second in the past and watch the transient be invalidated.
		$stored_value = get_transient( $key );
		$this->assertFalse( empty( $stored_value['expiration'] ) );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		$this->assertEquals( $value, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'test_soft_transient_1', array( $key ) ) > 0 );
	}

	function test_soft_transient_add_timeout() {
		$key = rand_str();
		$value = rand_str();
		$value2 = rand_str();
		$this->assertTrue( set_soft_transient( $key, $value ) );
		$this->assertEquals( $value, get_soft_transient( $key ) );

		$this->assertTrue( empty( $stored_value['expiration'] ) );

		// Add timeout to existing timeout-less transient.
		$this->assertTrue( set_soft_transient( $key, $value2, 1, 'test_soft_transient_2' ) );
		$stored_value = get_transient( $key );
		$this->assertFalse( empty( $stored_value['expiration'] ) );
		$stored_value['expiration'] = time() - 1;
		set_transient( $key, $stored_value );

		$this->assertEquals( $value2, get_soft_transient( $key ) );
		$this->assertTrue( wp_next_scheduled( 'test_soft_transient_2', array( $key ) ) > 0 );
	}
}

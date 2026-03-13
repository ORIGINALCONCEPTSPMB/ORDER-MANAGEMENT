<?php
/**
 * PHPUnit tests for order CRUD operations.
 *
 * These tests use WordPress's built-in WP_UnitTestCase and expect the
 * plugin to be loaded. Run with:
 *
 *   phpunit --bootstrap=tests/bootstrap.php tests/test-orders.php
 *
 * @package ProcessFlow_Manager
 */

// Ensure plugin constants exist in standalone test runs.
if ( ! defined( 'PROCESSFLOW_PLUGIN_DIR' ) ) {
	define( 'PROCESSFLOW_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-database.php';
require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-order-manager.php';

/**
 * Class Test_Orders
 *
 * Basic order CRUD test suite.
 */
class Test_Orders extends WP_UnitTestCase {

	/** @var ProcessFlow_Database */
	private $db;

	/** @var ProcessFlow_Order_Manager */
	private $order_manager;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->db            = new ProcessFlow_Database();
		$this->order_manager = new ProcessFlow_Order_Manager( $this->db );

		// Ensure tables exist.
		ProcessFlow_Activator::activate();
	}

	// ------------------------------------------------------------------ //
	// Validation                                                           //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * A missing customer_name should return a WP_Error.
	 */
	public function test_create_order_requires_customer_name() {
		$result = $this->order_manager->create_order( array(
			'customer_name' => '',
			'whatsapp'      => '+27821234567',
		) );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * A missing WhatsApp number should return a WP_Error.
	 */
	public function test_create_order_requires_whatsapp() {
		$result = $this->order_manager->create_order( array(
			'customer_name' => 'Test User',
			'whatsapp'      => '',
		) );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * An invalid WhatsApp format (no country code) should return a WP_Error.
	 */
	public function test_create_order_validates_whatsapp_format() {
		$result = $this->order_manager->create_order( array(
			'customer_name' => 'Test User',
			'whatsapp'      => '0821234567',   // no leading +
		) );
		$this->assertWPError( $result );
	}

	// ------------------------------------------------------------------ //
	// CRUD happy paths                                                     //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * A valid order should be persisted and return an integer ID.
	 */
	public function test_create_valid_order_returns_id() {
		$id = $this->order_manager->create_order( array(
			'customer_name' => 'Jane Doe',
			'business_name' => 'Acme Corp',
			'whatsapp'      => '+27821234567',
			'job_details'   => 'Business cards x 500',
		) );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		return $id;
	}

	/**
	 * @test
	 * @depends test_create_valid_order_returns_id
	 */
	public function test_get_order_returns_correct_data( int $order_id ) {
		$order = $this->db->get_order( $order_id );
		$this->assertNotNull( $order );
		$this->assertEquals( 'Jane Doe', $order->customer_name );
		$this->assertEquals( 'Acme Corp', $order->business_name );
		$this->assertEquals( '+27821234567', $order->whatsapp );
		// QR hash should have been populated.
		$this->assertNotEmpty( $order->qr_code_hash );
	}

	/**
	 * @test
	 * @depends test_create_valid_order_returns_id
	 */
	public function test_update_order( int $order_id ) {
		$result = $this->order_manager->update_order( $order_id, array(
			'customer_name' => 'Jane Smith',
			'business_name' => 'Acme Corp',
			'whatsapp'      => '+27821234567',
			'job_details'   => 'Flyers x 1000',
		) );
		$this->assertTrue( $result );
		$order = $this->db->get_order( $order_id );
		$this->assertEquals( 'Jane Smith', $order->customer_name );
		$this->assertEquals( 'Flyers x 1000', $order->job_details );
	}

	/**
	 * @test
	 * @depends test_create_valid_order_returns_id
	 */
	public function test_delete_order( int $order_id ) {
		$result = $this->order_manager->delete_order( $order_id );
		$this->assertTrue( $result );
		$order = $this->db->get_order( $order_id );
		$this->assertNull( $order );
	}

	/**
	 * @test
	 * Deleting a non-existent order should return a WP_Error.
	 */
	public function test_delete_nonexistent_order_returns_error() {
		$result = $this->order_manager->delete_order( 999999 );
		$this->assertWPError( $result );
	}

	// ------------------------------------------------------------------ //
	// get_orders filtering                                                 //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * get_orders should return a total count and items array.
	 */
	public function test_get_orders_structure() {
		// Create two orders.
		$this->order_manager->create_order( array(
			'customer_name' => 'Alpha Customer',
			'whatsapp'      => '+27821111111',
		) );
		$this->order_manager->create_order( array(
			'customer_name' => 'Beta Customer',
			'whatsapp'      => '+27822222222',
		) );

		$result = $this->db->get_orders();
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThanOrEqual( 2, $result['total'] );
	}

	/**
	 * @test
	 * Search filter should restrict results.
	 */
	public function test_get_orders_search_filter() {
		$this->order_manager->create_order( array(
			'customer_name' => 'Unique SearchName XYZ',
			'whatsapp'      => '+27823333333',
		) );

		$result = $this->db->get_orders( array( 'search' => 'Unique SearchName XYZ' ) );
		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 'Unique SearchName XYZ', $result['items'][0]->customer_name );
	}

	// ------------------------------------------------------------------ //
	// Stage history                                                        //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * Advancing a stage should add a history record.
	 */
	public function test_advance_stage_creates_history() {
		$id = $this->order_manager->create_order( array(
			'customer_name' => 'History Test',
			'whatsapp'      => '+27824444444',
		) );
		$this->assertIsInt( $id );

		// Advance if there are at least 2 stages.
		$stages = $this->db->get_stages();
		if ( count( $stages ) < 2 ) {
			$this->markTestSkipped( 'Need at least 2 stages to test advance.' );
		}

		$result = $this->order_manager->advance_stage( $id );
		$this->assertNotWPError( $result );

		$history = $this->db->get_stage_history( $id );
		$this->assertGreaterThanOrEqual( 2, count( $history ) );
	}

	// ------------------------------------------------------------------ //
	// Sanitize / Validate helpers                                          //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * sanitize_order_data should strip dangerous characters.
	 */
	public function test_sanitize_order_data_strips_html() {
		$raw = array(
			'customer_name' => '<script>alert(1)</script>',
			'business_name' => '<b>Bold</b>',
			'whatsapp'      => '+27825556666',
			'job_details'   => 'Safe text',
		);
		$clean = $this->order_manager->sanitize_order_data( $raw );
		$this->assertStringNotContainsString( '<script>', $clean['customer_name'] );
		$this->assertStringNotContainsString( '<b>', $clean['business_name'] );
	}

	/**
	 * @test
	 * validate_order_data returns true for valid data.
	 */
	public function test_validate_order_data_passes_valid_data() {
		$data = array(
			'customer_name' => 'Valid Name',
			'whatsapp'      => '+27821234567',
		);
		$result = $this->order_manager->validate_order_data( $data );
		$this->assertTrue( $result );
	}
}

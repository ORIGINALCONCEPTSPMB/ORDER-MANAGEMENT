<?php
/**
 * PHPUnit tests for the QR Engine.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'PROCESSFLOW_PLUGIN_DIR' ) ) {
	define( 'PROCESSFLOW_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-database.php';
require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-order-manager.php';
require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-qr-engine.php';
require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-whatsapp.php';

/**
 * Class Test_QR
 */
class Test_QR extends WP_UnitTestCase {

	/** @var ProcessFlow_Database */
	private $db;

	/** @var ProcessFlow_QR_Engine */
	private $qr_engine;

	/** @var ProcessFlow_Order_Manager */
	private $order_manager;

	public function setUp(): void {
		parent::setUp();
		$this->db            = new ProcessFlow_Database();
		$this->qr_engine     = new ProcessFlow_QR_Engine( $this->db );
		$this->order_manager = new ProcessFlow_Order_Manager( $this->db );
		ProcessFlow_Activator::activate();
	}

	// ------------------------------------------------------------------ //
	// Scan URL                                                             //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * get_scan_url should always include the order_id and hash params.
	 * When a QR scan page exists the URL points to that page; when none exists
	 * it falls back to the legacy ?processflow_scan=1 endpoint.
	 */
	public function test_get_scan_url_contains_params() {
		$url = $this->qr_engine->get_scan_url( 42, 'abc123hash' );
		$this->assertStringContainsString( 'order_id=42', $url );
		$this->assertStringContainsString( 'hash=', $url );
	}

	/**
	 * @test
	 * When no QR scan page exists, get_scan_url falls back to the legacy
	 * ?processflow_scan=1 endpoint on the home URL.
	 */
	public function test_get_scan_url_falls_back_without_page() {
		// Temporarily clear the QR scan page option.
		$saved = get_option( 'processflow_qr_scan_page_id' );
		delete_option( 'processflow_qr_scan_page_id' );

		$url = $this->qr_engine->get_scan_url( 42, 'abc123hash' );
		$this->assertStringContainsString( 'processflow_scan=1', $url );
		$this->assertStringContainsString( 'order_id=42', $url );

		// Restore.
		if ( $saved ) {
			update_option( 'processflow_qr_scan_page_id', $saved );
		}
	}

	// ------------------------------------------------------------------ //
	// QR image URL                                                         //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * get_qr_image_url should return Google Charts API URL for valid order.
	 */
	public function test_get_qr_image_url_for_valid_order() {
		$order_id = $this->order_manager->create_order( array(
			'customer_name' => 'QR Test User',
			'whatsapp'      => '+27821234567',
		) );
		$this->assertIsInt( $order_id );

		$url = $this->qr_engine->get_qr_image_url( $order_id );
		$this->assertNotWPError( $url );
		$this->assertStringContainsString( 'chart.googleapis.com', $url );
		$this->assertStringContainsString( 'cht=qr', $url );
	}

	/**
	 * @test
	 * get_qr_image_url should return WP_Error for a non-existent order.
	 */
	public function test_get_qr_image_url_returns_error_for_missing_order() {
		$result = $this->qr_engine->get_qr_image_url( 999999 );
		$this->assertWPError( $result );
	}

	// ------------------------------------------------------------------ //
	// process_scan                                                         //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * process_scan with a wrong hash should return WP_Error.
	 */
	public function test_process_scan_with_wrong_hash_returns_error() {
		$order_id = $this->order_manager->create_order( array(
			'customer_name' => 'Scan Test',
			'whatsapp'      => '+27821234567',
		) );

		$result = $this->qr_engine->process_scan( $order_id, 'wrong_hash_value' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_hash', $result->get_error_code() );
	}

	/**
	 * @test
	 * process_scan with the correct hash should return a wa.me URL.
	 */
	public function test_process_scan_with_correct_hash_returns_whatsapp_url() {
		$stages = $this->db->get_stages();
		if ( count( $stages ) < 2 ) {
			$this->markTestSkipped( 'Needs at least 2 stages.' );
		}

		$order_id = $this->order_manager->create_order( array(
			'customer_name' => 'Scan Happy Test',
			'whatsapp'      => '+27821234567',
		) );

		$order  = $this->db->get_order( $order_id );
		$result = $this->qr_engine->process_scan( $order_id, $order->qr_code_hash );

		$this->assertNotWPError( $result );
		$this->assertStringContainsString( 'wa.me', $result );
	}

	// ------------------------------------------------------------------ //
	// PDF / HTML labels                                                    //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * generate_pdf_labels should return a valid HTML string.
	 */
	public function test_generate_pdf_labels_returns_html() {
		$id1 = $this->order_manager->create_order( array(
			'customer_name' => 'Label One',
			'whatsapp'      => '+27821111111',
		) );
		$id2 = $this->order_manager->create_order( array(
			'customer_name' => 'Label Two',
			'whatsapp'      => '+27822222222',
		) );

		$html = $this->qr_engine->generate_pdf_labels( array( $id1, $id2 ) );
		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( 'pf-label', $html );
		$this->assertStringContainsString( "Order #{$id1}", $html );
		$this->assertStringContainsString( "Order #{$id2}", $html );
	}

	/**
	 * @test
	 * generate_pdf_labels with no valid orders should return HTML with empty body.
	 */
	public function test_generate_pdf_labels_skips_invalid_order_ids() {
		$html = $this->qr_engine->generate_pdf_labels( array( 999997, 999998 ) );
		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringNotContainsString( 'pf-label__qr', $html );
	}

	// ------------------------------------------------------------------ //
	// get_scan_page_data                                                   //
	// ------------------------------------------------------------------ //

	/**
	 * @test
	 * get_scan_page_data with a wrong hash should return WP_Error.
	 */
	public function test_get_scan_page_data_with_wrong_hash_returns_error() {
		$order_id = $this->order_manager->create_order( array(
			'customer_name' => 'Page Data Test',
			'whatsapp'      => '+27821234567',
		) );

		$result = $this->qr_engine->get_scan_page_data( $order_id, 'bad_hash' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_hash', $result->get_error_code() );
	}

	/**
	 * @test
	 * get_scan_page_data with a missing order should return WP_Error.
	 */
	public function test_get_scan_page_data_with_missing_order_returns_error() {
		$result = $this->qr_engine->get_scan_page_data( 999999, 'anyhash' );
		$this->assertWPError( $result );
		$this->assertEquals( 'not_found', $result->get_error_code() );
	}

	/**
	 * @test
	 * get_scan_page_data with a valid order and correct hash returns array
	 * with the expected keys.
	 */
	public function test_get_scan_page_data_returns_expected_data() {
		$order_id = $this->order_manager->create_order( array(
			'customer_name' => 'Page Data Happy',
			'whatsapp'      => '+27821234567',
		) );
		$this->assertIsInt( $order_id );

		$order = $this->db->get_order( $order_id );
		$data  = $this->qr_engine->get_scan_page_data( $order_id, $order->qr_code_hash );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'order', $data );
		$this->assertArrayHasKey( 'stage', $data );
		$this->assertArrayHasKey( 'stages', $data );
		$this->assertArrayHasKey( 'history', $data );
		$this->assertArrayHasKey( 'wa_url', $data );
		$this->assertEquals( $order_id, (int) $data['order']->id );
		$this->assertStringContainsString( 'wa.me', $data['wa_url'] );
	}
}

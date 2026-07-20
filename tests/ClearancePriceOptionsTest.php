<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class ClearancePriceOptionsTest extends TestCase {

	private Clearance_Price_Options $options;

	protected function setUp(): void {
		cp_test_reset();
		$this->options = new Clearance_Price_Options();
	}

	private function enable( array $options = array() ): void {
		$GLOBALS['cp_test_options'] = array_merge( array( 'clearance_price_cb' => '1' ), $options );
	}

	private function at( string $datetime, string $timezone = 'UTC' ): void {
		$GLOBALS['cp_test_timezone'] = $timezone;
		$GLOBALS['cp_test_now']      = new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
	}

	public function test_window_inactive_when_master_switch_off(): void {
		$this->assertFalse( $this->options->is_clearance_window_active() );
	}

	public function test_window_active_with_no_dates(): void {
		$this->enable();
		$this->assertTrue( $this->options->is_clearance_window_active() );
	}

	public function test_window_inactive_before_from_date(): void {
		$this->enable( array( 'clearance_price_from_date_cb' => '2026-07-16' ) );
		$this->at( '2026-07-15 12:00:00' );
		$this->assertFalse( $this->options->is_clearance_window_active() );
	}

	public function test_window_active_from_start_of_from_date(): void {
		$this->enable( array( 'clearance_price_from_date_cb' => '2026-07-15' ) );
		$this->at( '2026-07-15 00:00:00' );
		$this->assertTrue( $this->options->is_clearance_window_active() );
	}

	public function test_window_includes_whole_to_date(): void {
		$this->enable( array( 'clearance_price_to_date_cb' => '2026-07-15' ) );
		$this->at( '2026-07-15 23:00:00' );
		$this->assertTrue( $this->options->is_clearance_window_active() );
	}

	public function test_window_inactive_after_to_date(): void {
		$this->enable( array( 'clearance_price_to_date_cb' => '2026-07-14' ) );
		$this->at( '2026-07-15 00:00:01' );
		$this->assertFalse( $this->options->is_clearance_window_active() );
	}

	public function test_window_uses_site_timezone(): void {
		$this->enable( array( 'clearance_price_to_date_cb' => '2026-07-15' ) );

		// 11:00 UTC on the 15th is still the 15th in Auckland (23:00) — active.
		$this->at( '2026-07-15 11:00:00', 'Pacific/Auckland' );
		$this->assertTrue( $this->options->is_clearance_window_active() );

		// 13:00 UTC on the 15th is already the 16th in Auckland (01:00) — over.
		$this->at( '2026-07-15 13:00:00', 'Pacific/Auckland' );
		$this->assertFalse( $this->options->is_clearance_window_active() );
	}

	public function test_open_ended_dates(): void {
		$this->enable( array( 'clearance_price_from_date_cb' => '2020-01-01' ) );
		$this->at( '2026-07-15 12:00:00' );
		$this->assertTrue( $this->options->is_clearance_window_active(), 'Empty "to" date should mean no end.' );

		$this->enable( array( 'clearance_price_to_date_cb' => '2030-01-01' ) );
		$this->assertTrue( $this->options->is_clearance_window_active(), 'Empty "from" date should mean active since forever.' );
	}

	public function test_get_clearance_price_returns_valid_price(): void {
		$GLOBALS['cp_test_post_meta'][10]['_clearance_price'] = '12.50';
		$this->assertSame( '12.50', $this->options->get_clearance_price( 10 ) );
	}

	/**
	 * @dataProvider invalid_price_provider
	 */
	public function test_get_clearance_price_rejects_invalid_values( $stored ): void {
		$GLOBALS['cp_test_post_meta'][10]['_clearance_price'] = $stored;
		$this->assertSame( '', $this->options->get_clearance_price( 10 ) );
	}

	public function invalid_price_provider(): array {
		return array(
			'empty string'   => array( '' ),
			'non numeric'    => array( 'abc' ),
			'script payload' => array( '<script>alert(1)</script>' ),
			'negative'       => array( '-5' ),
			'zero'           => array( '0' ),
			'array'          => array( array( '5' ) ),
		);
	}

	public function test_toggles_require_master_switch(): void {
		$GLOBALS['cp_test_options'] = array(
			'clearance_price_simple_cb'        => '1',
			'clearance_price_variable_cb'      => '1',
			'clearance_price_show_original_cb' => '1',
		);
		$this->assertFalse( $this->options->simple_enabled() );
		$this->assertFalse( $this->options->variable_enabled() );
		$this->assertFalse( $this->options->show_original_price() );

		$this->enable(
			array(
				'clearance_price_simple_cb'        => '1',
				'clearance_price_variable_cb'      => '1',
				'clearance_price_show_original_cb' => '1',
			)
		);
		$this->assertTrue( $this->options->simple_enabled() );
		$this->assertTrue( $this->options->variable_enabled() );
		$this->assertTrue( $this->options->show_original_price() );
	}
}

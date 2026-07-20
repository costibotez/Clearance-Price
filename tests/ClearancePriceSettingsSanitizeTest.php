<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class ClearancePriceSettingsSanitizeTest extends TestCase {

	private Clearance_Price_Settings $settings;

	protected function setUp(): void {
		cp_test_reset();
		$this->settings = new Clearance_Price_Settings();
	}

	/**
	 * @dataProvider checkbox_provider
	 */
	public function test_sanitize_checkbox( $raw, int $expected ): void {
		$this->assertSame( $expected, $this->settings->sanitize_checkbox( $raw ) );
	}

	public function checkbox_provider(): array {
		return array(
			'checked'      => array( '1', 1 ),
			'unchecked'    => array( null, 0 ),
			'empty string' => array( '', 0 ),
			'zero string'  => array( '0', 0 ),
			'truthy junk'  => array( 'yes', 1 ),
		);
	}

	/**
	 * @dataProvider date_provider
	 */
	public function test_sanitize_date( $raw, string $expected ): void {
		$this->assertSame( $expected, $this->settings->sanitize_date( $raw ) );
	}

	public function date_provider(): array {
		return array(
			'valid date'       => array( '2026-01-31', '2026-01-31' ),
			'empty'            => array( '', '' ),
			'whitespace'       => array( '   ', '' ),
			'null'             => array( null, '' ),
			'array'            => array( array( '2026-01-31' ), '' ),
			'impossible date'  => array( '2026-02-30', '' ),
			'wrong format'     => array( '31/01/2026', '' ),
			'xss payload'      => array( '"><script>alert(1)</script>', '' ),
			'date with suffix' => array( '2026-01-31 extra', '' ),
		);
	}
}

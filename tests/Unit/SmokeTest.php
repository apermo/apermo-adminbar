<?php
namespace ApermoAdminBar\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
	public function test_plugin_file_is_loadable(): void {
		Monkey\Functions\stubs( array( '__', 'esc_html__', 'add_action', 'add_filter' ) );
		$this->assertFileExists( __DIR__ . '/../../apermo-adminbar.php' );
	}
}

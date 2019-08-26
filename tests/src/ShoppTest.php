<?php

namespace Pronamic\WordPress\Pay\Extensions\Shopp;

use PHPUnit_Framework_TestCase;

class ShoppTest extends PHPUnit_Framework_TestCase {
	/**
	 * Test class.
	 */
	public function test_class() {
		$this->assertTrue( class_exists( __NAMESPACE__ . '\Shopp' ) );
	}
}

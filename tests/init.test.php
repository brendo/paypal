<?php

	require_once __DIR__ . '/../lib/bootstrap.php';

	Class InitTest extends \PHPUnit_Framework_TestCase {

		public function testisTesting() {
			$this->assertTrue(PayPal::isTesting());
			Symphony::Configuration()->set('gateway-mode', 'sandbox', extension_Paypal::$config_handle);
			$this->assertTrue(PayPal::isTesting());
			Symphony::Configuration()->set('gateway-mode', 'live', extension_Paypal::$config_handle);
			$this->assertFalse(PayPal::isTesting());
		}

		/**
		 * @depends testisTesting
		 */
		public function testgetBaseEndpoint() {
			Symphony::Configuration()->set('gateway-mode', 'sandbox', extension_Paypal::$config_handle);
			$this->assertEquals(PayPal::getBaseEndpoint(), 'https://api.sandbox.paypal.com');
			Symphony::Configuration()->set('gateway-mode', 'live', extension_Paypal::$config_handle);
			$this->assertEquals(PayPal::getBaseEndpoint(), 'https://api.paypal.com');
		}

	}

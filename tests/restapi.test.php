<?php

	require_once __DIR__ . '/../lib/bootstrap.php';

	Class ExpressCheckoutTest extends \PHPUnit_Framework_TestCase {

		public function cartDetailsProvider() {
			return array(
				array(
					array(
						array(
							'sku' => '123',
							'name' => 'Red Hat',
							'quantity' => 1,
							'amount' => '75.00'
						)
					)
				)
			);
		}

		public function redirectsProvider() {
			return array(
				array(
					'paypal.com/?good', 'paypal.com/?cancel'
				)
			);
		}

		public function cartProvider() {
			return array(
				array(
					'75.00', 'Test Cart', array(
						'http://paypal.com/?good',
						'http://paypal.com/?cancel'
					), array(
						array(
							'sku' => '123',
							'name' => 'Red Hat',
							'quantity' => 1,
							'amount' => '75.00'
						)
					)
				)
			);
		}

		public function setup() {
			Symphony::Configuration()->set('gateway-mode', 'sandbox', extension_Paypal::$config_handle);
			Symphony::Configuration()->set('currency', 'AUD', extension_Paypal::$config_handle);
		}

		public function testgetAPIConfig() {
			$config = PayPal::getAPIConfig();

			$this->assertCount(4, $config);
			$this->assertArrayHasKey('mode', $config);
			//$this->assertArrayHasKey('log.logEnabled', $config);
			//$this->assertArrayHasKey('log.FileName', $config);
			//$this->assertArrayHasKey('log.LogLevel', $config);
		}

		/**
		 * @depends testgetAPIConfig
		 */
		public function testcreateContext() {
			$context = PayPal::createContext();
			$this->assertInstanceOf('PayPal\Rest\ApiContext', $context);
			$this->assertNotNull($context->getrequestId());

			$context = PayPal::createContext('ABC123');
			$this->assertInstanceOf('PayPal\Rest\ApiContext', $context);
			$this->assertEquals($context->getrequestId(), 'ABC123');
		}

		/**
		 * @dataProvider cartDetailsProvider
		 */
		public function testcreateTransactionItem($item_details) {
			$item = PayPal::createTransactionItem($item_details['sku'], $item_details['name'], $item_details['quantity'], $item_details['amount']);

			$this->assertInstanceOf('PayPal\Api\Item', $item);
			$this->assertEquals($item->getSku(), $item_details['sku']);
			$this->assertEquals($item->getName(), $item_details['name']);
			$this->assertEquals($item->getQuantity(), $item_details['quantity']);
			$this->assertEquals($item->getPrice(), $item_details['amount']);
			$this->assertEquals($item->getCurrency(), PayPal::getConfigValue('currency'));
		}

		/**
		 * @depends testcreateTransactionItem
		 * @dataProvider cartDetailsProvider
		 */
		public function testcreateItemList($items) {
			$itemlist = PayPal::createItemList($items);

			$this->assertInstanceOf('PayPal\Api\ItemList', $itemlist);
		}

		/**
		 * @dataProvider redirectsProvider
		 */
		public function testcreateRedirects($success, $cancel) {
			$redirects = PayPal::createRedirects($success, $cancel);

			$this->assertInstanceOf('PayPal\Api\RedirectUrls', $redirects);
		}

		/**
		 * @dataProvider cartProvider
		 * @depends testcreateRedirects
		 * @depends testcreateItemList
		 */
		public function testprepareCheckout($amount, $description, $redirects, $items) {
			$redirects = PayPal::createRedirects($redirects[0], $redirects[1]);
			$items = PayPal::createItemList($items);

			$payment = PayPal::prepareCheckout($amount, $description, $redirects, $items);

			$this->assertInstanceOf('PayPal\Api\Payment', $payment);
		}

		/**
		 * @dataProvider cartProvider
		 * @depends testprepareCheckout
		 */
		public function testreturnApprovalUrl($amount, $description, $redirects, $items) {
			$redirects = PayPal::createRedirects($redirects[0], $redirects[1]);
			$items = PayPal::createItemList($items);

			$payment = PayPal::prepareCheckout($amount, $description, $redirects, $items);
			$url = PayPal::returnApprovalUrl($payment);

			$this->assertInternalType('string', $url);
		}
	}


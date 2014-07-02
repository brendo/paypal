<?php

	require_once __DIR__ . '/bootstrap.php';

	use PayPal\Rest\ApiContext;
	use PayPal\Auth\OAuthTokenCredential;
	use PayPal\Api\Address;
	use PayPal\Api\Amount;
	use PayPal\Api\Details;
	use PayPal\Api\Payer;
	use PayPal\Api\Payment;
	use PayPal\Api\PaymentExecution;
	use PayPal\Api\FundingInstrument;
	use PayPal\Api\RedirectUrls;
	use PayPal\Api\Transaction;
	use PayPal\Api\Item;
	use PayPal\Api\ItemList;

	class PayPal {

		/**
		 * Last error message(s)
		 * @var array
		 */
		protected $_errors = array();

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * Whether or not PayPal is in test mode.
		 *
		 * @return type
		 */
		public static function isTesting() {
			return extension_PayPal::isTesting();
		}

		/**
		 * Returns configuration values from Symphony's config
		 * file by a given `$key`.
		 * 
		 * @param string $key
		 * @return mixed
		 */
		public static function getConfigValue($key) {
			return extension_PayPal::getSetting($key);
		}

		/**
		 * API PayPal endpoint
		 * @return string
		 */
		public static function getBaseEndpoint() {
			return (PayPal::isTesting())
				? 'https://api.sandbox.paypal.com'
				: 'https://api.paypal.com';
		}

		/**
		 * Find out type of payment frequency for PayPal.
		 *
		 * @param string $type
		 *	 Type of payment: Weekly, Fortnightly, Monthly
		 *	 and Yearly
		 * @return array
		 */
		public static function getFrequency($type) {
			$data = array();
			switch (strtolower($type)) {
				// Daily
				case "daily":
					$data['BILLINGPERIOD'] = "Day";
					$data['BILLINGFREQUENCY'] = 1;
					$data['ADDTIME'] = "+1 Day";
					break;

				// Weekly
				case "weekly":
					$data['BILLINGPERIOD'] = "Week";
					$data['BILLINGFREQUENCY'] = 1;
					$data['ADDTIME'] = "+1 Week";
					break;

				// Fortnightly
				case "fortnightly":
					$data['BILLINGPERIOD'] = "Week";
					$data['BILLINGFREQUENCY'] = 2;
					$data['ADDTIME'] = "+2 Week2";
					break;

				// Monthly
				case "monthly":
					$data['BILLINGPERIOD'] = "Month";
					$data['BILLINGFREQUENCY'] = 1;
					$data['ADDTIME'] = "+1 Month";
					break;

				// Yearly
				case "yearly":
					$data['BILLINGPERIOD'] = "Year";
					$data['BILLINGFREQUENCY'] = 1;
					$data['ADDTIME'] = "+1 Year";
					break;
			}

			return $data;
		}

		/**
		 * Log data coming from the Paypal into MANIFEST/logs/paypal/
		 *
		 * @param Exception $exception
		 */
		public static function log(Exception $exception, $request = null) {
			// Prefer Symphony Log
			if(null != Symphony::Log()) {
				return Symphony::Log()->pushExceptionToLog($exception, true, true, true);
			}

			// Otherwise log error to PayPal log
			$log_date = date('Ymd') . ".txt"; // Format "YEAR MONTH DAY.txt"
			return General::writeFile(LOGS . "/paypal/" . $log_date, (string)$exception, 'a+');
		}

		public static function addToEventXML(XMLElement $event_xml, $ex = null) {
			if(is_null($ex)) return null;

			$event_xml->setAttribute('result', 'error');

			// Pull the data out of the exception
			if(method_exists($ex, 'getData')) {
				$error = json_decode($ex->getData());
				$attributes = array(
					'type' => $error->name,
					'debug-id' => $error->debug_id,
					'message' => $ex->getMessage()
				);
				if(isset($error->details)) {
					$attributes['details'] = $error->details[0]->issue;
				}

				// Add to XML;
				$event_xml->appendChild(
					new XMLElement('paypal', $error->message, $attributes)
				);
			}
			else {
				// Add to XML;
				$event_xml->appendChild(
					new XMLElement('paypal', $ex->getMessage())
				);
			}

			return $event_xml;
		}

		/**
		 * Builds a base API interface based off the configuration
		 * values of Symphony
		 *
		 * @return array
		 */
		public static function getConfig() {
			$config = array (
				'mode' =>  self::getConfigValue('gateway-mode'),
				'client-id' => self::getConfigValue('api-client-id'),
				'secret' => self::getConfigValue('api-secret'),
				'currency' => self::getConfigValue('currency')
			);

			return $config;
		}

		/**
		 * Returns an associative array of PayPal configuration for
		 * the underlying SDK to use
		 *
		 * @return array
		 */
		public static function getAPIConfig() {
			$api_config = array(
				'mode' => self::getConfigValue('gateway-mode'),
				'log.LogEnabled' => true,
				'log.FileName' => LOGS . '/paypal/main',
				'log.LogLevel' => 'FINE',
			);

			return $api_config;
		}

		/** 
		 * Handles creating a valid OAuth session to communicate with PayPal
		 * and return an APIContext, ready to interact directly with
		 *
		 * @param string $request_id (optional)
		 * @return APIContext
		 */
		public static function createContext($request_id = null) {
			$config = self::getConfig();

			$context = new APIContext(new OAuthTokenCredential($config['client-id'], $config['secret'], array(
				'mode' => $config['sandbox']
			)), $request_id);
			$context->setConfig(self::getAPIConfig());

			return $context;
		}

		/**
		 * Given an array of assocative arrays which contain `name`,
		 * `quantity` and `price` keys, this function will create an ItemsList
		 * to be added to the Transaction
		 * 
		 * @param array $items
		 * @return ItemList
		 */
		public static function createItemList(array $items) {
			// ### Items
			// Add items to the transaction
			$itemlist = new ItemList();
			foreach($items as &$cart_item) {
				$cart_item = self::createTransactionItem($cart_item['sku'], $cart_item['name'], $cart_item['quantity'], $cart_item['amount']);
			}
			$itemlist->setItems($items);

			return $itemlist;
		}

		/**
		 * Given an associative array of information, this function will
		 * return an Item that can be added to the PayPal Transaction
		 *
		 * @param string $name
		 * @param string $quantity
		 * @param string $price
		 * @return Item
		 */
		public static function createTransactionItem($sku = null, $name = null, $quantity = null, $price = null) {
			$item = new Item();
			$item->setCurrency(self::getConfigValue('currency'));

			if(!is_null($sku)) $item->setSku($sku);
			if(!is_null($name)) $item->setName($name);
			if(!is_null($quantity)) $item->setQuantity($quantity);
			if(!is_null($price)) $item->setPrice(number_format((float)$price, 2, '.', ''));

			return $item;
		}

		/**
		 * Given two URL's, one for a successful transaction, one for a cancelled
		 * transaction, this function will create a RedirectUrls instance
		 *
		 * @param string $success_url
		 * @param string $cancel_url
		 * @return RedirectUrls
		 */
		public static function createRedirects($success_url, $cancel_url) {
			// ### Redirect urls
			// Set the urls that the buyer must be redirected to after 
			// payment approval/ cancellation.
			$redirectUrls = new RedirectUrls();
			$redirectUrls->setReturn_url($success_url);
			$redirectUrls->setCancel_url($cancel_url);

			return $redirectUrls;
		}

		/**
		 * Given a Payment response, return the redirect URL for a customer
		 * to complete their transaction
		 *
		 * @param Payment $payment
		 * @return string|boolean
		 */
		public static function returnApprovalUrl(Payment $payment = null) {
			if(($payment instanceof Payment) === false) return false;

			// ### Redirect buyer to paypal
			// Retrieve buyer approval url from the `payment` object.
			foreach($payment->getLinks() as $link) {
				if($link->getRel() == 'approval_url') {
					$redirectUrl = $link->getHref();

					return $redirectUrl;
				}
			}

			return false;
		}
	
	/*-------------------------------------------------------------------------
		Payments:
	-------------------------------------------------------------------------*/

		/**
		 * This function will prepare a PayPal checkout, returning the Payment response
		 * for the customer to complete their transaction. It should be used in conjunction
		 * with `returnApprovalUrl` and `completeCheckout`
		 *
		 * @see completeCheckout
		 * @param string $checkout_amount
		 * @param string $taxes_amount
		 * @param string $checkout_description (optional)
		 * @param RedirectUrls $redirects
		 * @param ItemList $items
		 * @return Payment
		 */
		public static function prepareCheckout($checkout_amount, $taxes_amount, $checkout_description = null, RedirectUrls $redirects, ItemList $items) {
			$payer = new Payer();
			$payer->setPayment_method("paypal");

			// ### Amount
			// Let's you specify a payment amount.
			$checkout_amount = number_format((float)$checkout_amount, 2, '.', '');
			$taxes_amount = number_format((float)$taxes_amount, 2, '.', '');
			$total = number_format($checkout_amount + $taxes_amount, 2, '.', '');

			$amount_details = new Details();
			$amount_details->setTax($taxes_amount);
			$amount_details->setSubtotal($checkout_amount);

			$amount = new Amount();
			$amount->setCurrency(self::getConfigValue('currency'));
			$amount->setTotal($total);
			$amount->setDetails($amount_details);

			// ### Transaction
			// A transaction defines the contract of a
			// payment - what is the payment for and who
			// is fulfilling it. Transaction is created with
			// a `Payee` and `Amount` types
			$transaction = new Transaction();
			$transaction->setAmount($amount);
			if(!is_null($checkout_description)) {
				$transaction->setDescription($checkout_description);
			}
			$transaction->setItemList($items);

			// ### Payment
			// A Payment Resource; create one using
			// the above types and intent as 'sale'
			$payment = new Payment();
			$payment->setIntent("sale");
			$payment->setPayer($payer);
			$payment->setRedirect_urls($redirects);
			$payment->setTransactions(array($transaction));

			// ### Create Payment
			// Create a payment by posting to the APIService
			// using a valid apiContext.
			// (See bootstrap.php for more on `ApiContext`)
			// The return object contains the status and the
			// url to which the buyer must be redirected to
			// for payment approval
			$apiContext = self::createContext();
			$payment->create($apiContext);

			return $payment;
		}

		/**
		 * Given a `$payment_id` and `$payer_id`, this function
		 * will complete the PayPal transaction that has already been
		 * approved and return the Payment response
		 *
		 * @param string $payment_id
		 * @param string $payer_id
		 * @return Payment
		 */
		public function completeCheckout($payment_id, $payer_id) {
			$apiContext = self::createContext();

			$payment = Payment::get($payment_id, $apiContext);
			$execution = new PaymentExecution();
			$execution->setPayerId($payer_id);
			$response = $payment->execute($execution, $apiContext);

			return $response;
		}

	}
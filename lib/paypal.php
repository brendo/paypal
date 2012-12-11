<?php

	class Paypal {

		/**
		* Last error message(s)
		* @var array
		*/
		protected $_errors = array();

		/**
		* PayPal API Version
		* @var string
		*/
		protected $_version = '74.0';

		/**
		* Whether or not PayPal is in test mode.
		*
		* @return type
		*/
		public static function isTesting() {
			return Symphony::Configuration()->get('gateway-mode', 'paypal') == 'sandbox';
		}

		/**
		* API PayPal endpoint
		* Live - https://api-3t.paypal.com/nvp
		* Sandbox - https://api-3t.sandbox.paypal.com/nvp
		* @return string
		*/
		public static function getPayPalUrl(){
			return (Paypal::isTesting())
				? 'https://api-3t.sandbox.paypal.com/nvp'
				: 'https://api-3t.paypal.com/nvp';
		}

		/**
		* Return the correct url for validating the IPN accordingly with
		* the environment (live/sandbox)
		*
		* @return string
		*/
		public static function validateIpnUrl() {
			return (Paypal::isTesting())
				? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
				: 'https://www.paypal.com/cgi-bin/webscr';
		}

		/**
		* Make API request
		*
		* @param string $method
		*	 string API method to request
		* @param array $params
		 * Additional request parameters
		* @return array / boolean Response array / boolean false on failure
		*/
		public function request($method,$params = array()) {

			$this->_errors = array();
			if (empty($method)) { //Check if API method is not empty
				$this -> _errors = array('API method is missing');
				return false;
			}

			/**
			* API Credentials (Live / Sandbox)
			*
			* @var array
			*/
			$credentials = array(
				'USER'		 => Symphony::Configuration()->get('api-username', 'paypal'),
				'PWD'		 => Symphony::Configuration()->get('api-password', 'paypal'),
				'SIGNATURE'	 => Symphony::Configuration()->get('api-signature', 'paypal'),
			);

			// Our request parameters
			$requestParams = array(
				'METHOD' => $method,
				'VERSION' => $this->_version
			) + $credentials;

			// Building NVP string
			$request = http_build_query($requestParams + $params);

			// Settings
			$curlOptions = array (
				CURLOPT_URL => $this->getPayPalUrl(),
				CURLOPT_VERBOSE => 1,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_CAINFO => dirname(__FILE__) . '/cacert.pem', // CA cert file
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => $request
			);

			$ch = curl_init();
			curl_setopt_array($ch,$curlOptions);

			//Sending our request - $response will hold the API response
			$response = curl_exec($ch);

			//Checking for cURL errors
			if (curl_errno($ch)) {
				$this -> _errors = curl_error($ch);
				curl_close($ch);
				return false;

			}
			// Handle errors
			else {
				curl_close($ch);
				$responseArray = array();
				parse_str($response,$responseArray); // Break the NVP string to an array
				return $responseArray;
			}
		}

		/**
		* Validates IPN from Paypal.
		*
		* @param type $post
		* @see https://www.x.com/developers/PayPal/documentation-tools/code-sample/216623
		*/
		public function validateIPN($raw_post_data) {
			$raw_post_array = explode('&', $raw_post_data);
			$myPost = array();
			foreach ($raw_post_array as $keyval) {
				$keyval = explode ('=', $keyval);
				if (count($keyval) == 2)
					$myPost[$keyval[0]] = urldecode($keyval[1]);
			}

			// read the post from PayPal system and add 'cmd'
			$req = 'cmd=_notify-validate';
			if(function_exists('get_magic_quotes_gpc')) {
				$get_magic_quotes_exists = true;
			}
			foreach ($myPost as $key => $value) {
				if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
					$value = urlencode(stripslashes($value));
				}
				else {
					$value = urlencode($value);
				}
				$req .= "&$key=$value";
			}

			// STEP 2: Post IPN data back to paypal to validate
			$ch = curl_init($this->validateIpnUrl());
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
			curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');

			if(!($res = curl_exec($ch))) {
				error_log("Got " . curl_error($ch) . " when processing IPN data");
				curl_close($ch);
				exit;
			}
			curl_close($ch);

			// STEP 3: Inspect IPN validation result and act accordingly
			if (strcmp ($res, "VERIFIED") == 0) {
				return true;
			}
			else if (strcmp ($res, "INVALID") == 0) {
				// log for manual investigation
				return false;
			}
		}

		/**
		 * Find out type of payment frequency for PayPal.
		 *
		 * @param int $type
		 *	 Type of payment: *1 => Weekly, 2 => Fortnightly, 3 => Monthly
		 *	 and 4 => Yearly
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

				// Montly
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
		 * Log data coming from the Paypal IPN into MANIFEST/logs/paypal/
		 *
		 * @param type $data
		 */
		public function logPaypalIPN($data) {
			$log_date = "ipn_" . date('Y') . date('m') . date('d') . ".txt"; // Format "ipn_YEAR MONTH DAY.txt"
			$logIPN = MANIFEST . "/logs/paypal/" . $log_date;
			$fh = fopen($logIPN, 'a+') or die("can't open file");
			$stringData = print_r($data, true) . "\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
	}
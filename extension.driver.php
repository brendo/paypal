<?php

	require_once EXTENSIONS . '/paypal/lib/paypal.php';

	Class extension_PayPal extends Extension {

		public static $config_handle = 'paypal';

		public function getSubscribedDelegates(){
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'Save',
					'callback'	=> 'savePreferences'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function getSetting($key) {
			return Symphony::Configuration()->get($key, self::$config_handle);
		}

		public static function isTesting() {
			return self::getSetting('gateway-mode') !== 'live';
		}

	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		public function getPreferencesData() {
			$data = array(
				'api-client-id' => '',
				'api-secret' => '',
				'gateway-mode' => 'sandbox',
				'currency' => ''
			);

			foreach ($data as $key => &$value) {
				$value = $this->getSetting($key);
			}

			return $data;
		}

		/**
		 * Allow the user to add their Paypal keys.
		 *
		 * @uses AddCustomPreferenceFieldsets
		 */
		public function appendPreferences($context) {
			$data = $this->getPreferencesData();

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Paypal Details')));

			$this->buildPreferences($fieldset, array(
				array(
					'label' => 'API Client ID',
					'name' => 'api-client-id',
					'value' => $data['api-client-id'],
					'type' => 'text'
				),
				array(
					'label' => 'API Secret',
					'name' => 'api-secret',
					'value' => $data['api-secret'],
					'type' => 'text'
				),
				array(
					'label' => 'Currency Code (3 characters)',
					'name' => 'currency',
					'value' => $data['currency'],
					'type' => 'text'
				)
			));

			$context['wrapper']->appendChild($fieldset);
		}


		public function buildPreferences($fieldset, $data) {
			$row = null;

			foreach ($data as $index => $item) {
				if ($index % 2 == 0) {
					if ($row) $fieldset->appendChild($row);

					$row = new XMLElement('div');
					$row->setAttribute('class', 'group');
				}

				$label = Widget::Label(__($item['label']));
				$name = 'settings[' . self::$config_handle . '][' . $item['name'] . ']';

				$input = Widget::Input($name, $item['value'], $item['type']);

				$label->appendChild($input);
				$row->appendChild($label);
			}

			// Build the Gateway Mode
			$label = new XMLElement('label', __('Gateway Mode'));
			$options = array(
				array('sandbox', $this->isTesting() , __('Sandbox')),
				array('live',  !$this->isTesting(), __('Live'))
			);

			$label->appendChild(Widget::Select('settings[paypal][gateway-mode]', $options));
			$row->appendChild($label);

			$fieldset->appendChild($row);
		}

		/**
		 * Saves the Paypal to the configuration
		 *
		 * @uses savePreferences
		 */
		public function savePreferences(array &$context){
			$settings = $context['settings'];

			// Active Section
			Symphony::Configuration()->set('api-username', $settings['paypal']['api-client-id'], 'paypal');
			Symphony::Configuration()->set('api-password', $settings['paypal']['api-secret'], 'paypal');
			Symphony::Configuration()->set('gateway-mode', $settings['paypal']['gateway-mode'], 'paypal');
			Symphony::Configuration()->set('currency', $settings['paypal']['currency'], 'paypal');

			Symphony::Configuration()->write();
		}

	}

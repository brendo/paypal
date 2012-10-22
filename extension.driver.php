<?php
	Class extension_Paypal extends Extension {

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

		public function getSetting($key) {
			return Symphony::Configuration()->get($key, self::$config_handle);
		}


	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		public function getPreferencesData() {
			$data = array(
				'api-username' => '',
				'api-password' => '',
				'api-signature' => '',
				'gateway-mode' => 'sandbox'
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
					'label' => 'API username',
					'name' => 'api-username',
					'value' => $data['api-username'],
					'type' => 'text'
				),
				array(
					'label' => 'API password',
					'name' => 'api-password',
					'value' => $data['api-password'],
					'type' => 'password'
				),
				array(
					'label' => 'API signature',
					'name' => 'api-signature',
					'value' => $data['api-signature'],
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

		public static function isTesting() {
			return Symphony::Configuration()->get('gateway-mode', 'paypal') == 'sandbox';
		}		

		/**
		 * Saves the Paypal to the configuration
		 *
		 * @uses savePreferences
		 */
		public function savePreferences(array &$context){
			$settings = $context['settings'];

			// Active Section
			Symphony::Configuration()->set('production-customer-id', $settings['paypal']['api-username'], 'paypal');
			Symphony::Configuration()->set('production-merchant-id', $settings['paypal']['api-password'], 'paypal');
			Symphony::Configuration()->set('production-merchant-password', $settings['paypal']['api-signature'], 'paypal');
			Symphony::Configuration()->set('gateway-mode', $settings['paypal']['gateway-mode'], 'paypal');

			Administration::instance()->saveConfig();
		}

	}

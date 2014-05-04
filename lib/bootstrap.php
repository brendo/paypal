<?php

	// Defines some constants
	$clean_path = rtrim(dirname(__FILE__), '/\\');
	$clean_path = preg_split('/extensions/i', $clean_path);
	$clean_path = rtrim($clean_path[0], '/\\');
	define('DOCROOT', $clean_path);

	if(isset($_SERVER['HTTP_HOST'])) {
		$clean_url = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : NULL;
		$clean_url = dirname(rtrim($_SERVER['PHP_SELF'], $clean_url));
		$clean_url = rtrim($_SERVER['HTTP_HOST'] . $clean_url, '/\\');
		$clean_url = preg_split('/extensions/i', $clean_url);
		$clean_url = rtrim($clean_url[0], '/\\');
	}
	else {
		$clean_url = 'http://localhost/';
	}
	define('DOMAIN', $clean_url);

	// Bring in the bundle
	require_once DOCROOT . '/symphony/lib/boot/bundle.php';

	if(!class_exists('Administration') and !class_exists('Frontend')) {
		require_once CORE . '/class.administration.php';
		\Administration::instance();
	}

	// Specific files
	require_once __DIR__ . '/paypal.php';
	require_once __DIR__ . '/../extension.driver.php';

	// Autoloader
	require_once __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../../../vendor/autoload.php';
	require_once __DIR__ . '/../vendor/autoload.php';
<?php

if (! defined('CUSTOM_123TEST_AUTHOR')) {
	define('CUSTOM_123TEST_AUTHOR', 'ZEF');
	define('CUSTOM_123TEST_AUTHOR_URL', 'https://www.zigt.nl');
	define('CUSTOM_123TEST_DESC', '123test custom functionality');
	define('CUSTOM_123TEST_DOCS_URL', '');
	define('CUSTOM_123TEST_NAME', 'Custom 123test');
	define('CUSTOM_123TEST_VER', '1.0');
}

return array(
	'author' => CUSTOM_123TEST_AUTHOR,
	'author_url' => CUSTOM_123TEST_AUTHOR_URL,
	'description' => CUSTOM_123TEST_DESC,
	'docs_url' => CUSTOM_123TEST_DOCS_URL,
	'name' => CUSTOM_123TEST_NAME,
	'namespace' => 'Zigt\123test',
	'version' => CUSTOM_123TEST_VER
);
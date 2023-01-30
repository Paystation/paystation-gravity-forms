<?php
/*
Plugin Name: Gravity Forms Paystation (3 party hosted)
Plugin URI: https://wordpress.org/plugins/gravity-forms-paystation-3-party-hosted/
Description: Integrates Gravity Forms with the Paystation 3 party payment gateway allowing end users to purchase goods and services, or make donations, via Gravity Forms.
Version: 1.5.5
Author: Paystation Limited
Author URI: https://www2.paystation.co.nz/
License: GPL2
*/
/*  Copyright 2014  Paystation Limited  (email : support@paystation.co.nz)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!defined('GFPAYSTATION_PLUGIN_ROOT')) {
	define('GFPAYSTATION_PLUGIN_ROOT', dirname(__FILE__) . '/');
	define('GFPAYSTATION_PLUGIN_NAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
	define('GFPAYSTATION_PLUGIN_OPTIONS', 'gfpaystation_plugin');

	// ****** REMEMBER TO UPDATE THIS TOO *******
	define('GFPAYSTATION_PLUGIN_VERSION', '1.5.5');

	// Custom post types
	define('GFPAYSTATION_TYPE_FEED', 'gfpaystation_feed');

	// End point for the Paystation API
	define('GFPAYSTATION_API_URL', 'https://www.paystation.co.nz/direct/paystation.dll');

	// End point for return to website - the redirect after the payment.
	define('GFPAYSTATION_RETURN', 'gfpaystation_return');

	// End point for the postback from paystation to this site with the confirmation of the payment.
	define('GFPAYSTATION_POSTBACK', 'gfpaystation_postback');

	// Name used as cURL user agent
	define('GFPAYSTATION_CURL_USER_AGENT', 'Gravity Forms Paystation (3 party hosted)');
}

/**
 * autoload classes as/when needed
 *
 * @param string $class_name name of class to attempt to load
 */
function gfpaystation_autoload($class_name) {
	static $classMap = array(
		'GFPaystationAdmin' => 'class.GFPaystationAdmin.php',
		'GFPaystationFeed' => 'class.GFPaystationFeed.php',
		'GFPaystationFeedAdmin' => 'class.GFPaystationFeedAdmin.php',
		'GFPaystationFormData' => 'class.GFPaystationFormData.php',
		'GFPaystationOptionsAdmin' => 'class.GFPaystationOptionsAdmin.php',
		'GFPaystationPayment' => 'class.GFPaystationPayment.php',
		'GFPaystationPlugin' => 'class.GFPaystationPlugin.php',
		'GFPaystationReturnResult' => 'class.GFPaystationReturnResult.php',
		'GFPaystationPostbackResult' => 'class.GFPaystationPostbackResult.php',
	);

	if (isset($classMap[$class_name])) {
		require GFPAYSTATION_PLUGIN_ROOT . $classMap[$class_name];
	}
}

// register a class (static) method for autoloading required classes
spl_autoload_register('gfpaystation_autoload');

// instantiate the plug-in
GFPaystationPlugin::getInstance();

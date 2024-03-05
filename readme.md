# Gravity Forms Paystation 1.5.5 (latest version)

Integrates Gravity Forms with the Paystation 3 party hosted payment gateway, enabling end-users to purchase goods and services seamlessly via Gravity Forms.

## Description

Gravity Forms Paystation (3 party hosted) adds a 3 party hosted credit card payment gateway for Paystation to the Gravity Forms plugin.

With this plugin you can use Gravity Forms 2.8 to create various types of payment forms...

* Product forms
* Online Booking forms
* Donation forms
* Service payment forms

NOTE: this plugin requires [Gravity Forms 2.8 or latest version, check here](https://www.gravityforms.com/); so you need to have installed and activated Gravity Forms.
and also **previous versions of Gravity forms won't be recommended with this plugin as you can expect the compatibility issues.**

## Requirements:

* The [Gravity Forms](http://www.gravityforms.com/) plugin, any licence (Personal, Business, Developer).
* [Install](https://wordpress.org/plugins/gravity-forms-paystation-3-party-hosted/) the Gravity forms Paystation (3 party hosted plugin). 
* After installing activate your account with [Paystation](https://www2.paystation.co.nz/).

## Installation steps:

1. Install the [Gravity Forms](http://www.gravityforms.com/) plugin.
2. Activate the Gravity forms plugin, and set settings as desired (refer to gravity forms documentation if needed).
3. Upload the Gravity Forms Paystation (3 party hosted) plugin to your /wp-content/plugins/ directory
4. Activate Gravity Forms Paystation (3 party hosted) via Plugins menu in WordPress.

### Create a gravity form:

1. To create a gravity form, go to the admin dashboard, see the gravity form plugin which you have installed, and click add new form.
2. Create your form according to your requirements, please make sure that you include the form field from the "Payment Details" tab to ensure that the plugin is working correctly. 
3. Select your form from the dropdown at the top of the page and click save.
4. If you want custom field mappings to Paystation data, you can map your form fields to Paystation transaction fields (Merchant Reference, Customer details, Order details); be aware
   a form field can only be mapped to one Paystation transaction field at a time.

## Using Gravity Forms Paystation:

Before you can use the plugin to make payments, you need to create an account with Paystation.
To do this please see visit the [Paystation](https://www2.paystation.co.nz/) website.

Then you will need to enter some information in the plugin settings

1. Select 'Plugins' from the WordPress Admin menu.
2. Select the Gravity Forms plugin, the 3 party setting will be under the gravity forms plugin.
3. And then choose the Paystation (3 party hosted).
4. Enter your Paystation Id in the Paystation Id field, Gateway ID in the gateway Id field. 
5. Enter a string of letters and numbers for the Security Hash (8 to 20 characters is fine). 
6. Then choose 'No' in the test mode field. 
7. Save the settings by clicking the 'Save Settings' button. 
8. And then under the same setting click on setup Paystation feed, it will redirect to the feeds page.
9. On the feeds settings page, select thye form name from the dropdown which yoy have created 
10. Email [info@paystation.co.nz](mailto:info@paystation.co.nz) letting us know the following: that you are using the Paystation Gravity forms plugin, the url to your wordpress site, and what your Security Hash is.

## Testing dev environment:

Tested successfully with the latest WordPress 6.4 version, PHP 8.2 version, Gravity forms 2.8 version
Please note: This plugion is compatbile with the latest version as mentioned above, any downgrade versions will occure an issue and this might break your website.

## Frequently Asked Questions

### What is Paystation (3 party hosted)?

Paystation (3 party hosted) is a Credit Card payment gateway. Paystation is one of New Zealand's leading online payments solution providers.

### Will this plugin work without installing Gravity Forms?

No. This plugin adds a Paystation 3rd Party Hosted gateway to Gravity Forms so that you can do online payments with your forms.
You must purchase and install a copy of the [Gravity Forms](http://www.gravityforms.com/) plugin.

### What Gravity Forms license do I need?

Any Gravity Forms license will do; you can use this plugin with the Personal, Business or Developer licenses.

### Where will the customer be directed after they complete their payment with the Paystation payment screens?

Standard Gravity Forms submission logic applies. The customer will either be shown your chosen confirmation message, an error message if the payment failed,
directed to a nominated page on your website, or sent to a custom URL. It depends on what you have specified for the Confirmation settings of the Gravity Form.

### Where do I find the Paystation transaction number?

Successful transaction details including the Paystation transaction number are shown in the Info box when you view the details of a form entry in the WordPress admin.
Details of the payment also appear in the Paystation online Admin which you as Paystation customer have access to.

### How do I change my currency type?

Use your Gravity Forms Settings page to select the currency type to pass to Paystation. Please ensure your currency type is one of the ones supported by Paystation.

### Where can I find dummy Credit Card details for testing purposes?

[Visit the Test Card Number page](https://www2.paystation.co.nz/developers/test-cards/)

### Can I use this plugin on any shared-hosting environment?

The plugin will run in shared hosting environments, but requires PHP 5 with the following module enabled (talk to your host).
This is typically available because is is enabled by default in PHP 5, but may be disabled on some shared hosts.

### Can I use this plugin with the older versions of WordPress or PHP or Gravity forms ?

Absoultely, NO you cannot use any of the older versions with this otherwise expect an unexpected behavior.

* SimpleXML
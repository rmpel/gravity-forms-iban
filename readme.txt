=== Gravity Forms IBAN ===
Contributors: rmpel, admium-webdevelopment, slimndap
Tags: iban, sepa
Requires at least: 4.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add an IBAN input mask and IBAN validation to your Gravity Form.

== Description ==

This plugin adds an new IBAN mask to the built-in input masks of Gravity Forms.
Add this mask to any text field to enforce valid IBAN account numbers in your form.

This plugin uses the [php-iban](https://github.com/globalcitizen/php-iban) library to validate IBAN account numbers.

== Screenshots ==

1. The IBAN mask in the Gravity Forms editor.
2. An IBAN input field in the front end.

== Changelog ==

= 1.1 =
* Compatibility with Gravity Forms 3.0: the IBAN mask is now registered as a regular standard mask instead of relying on the deprecated `gform_input_mask_script` filter (removed in Gravity Forms 3.0). Forms saved with version 1.0 are migrated on the fly.
* Validation now uses the per-field `gform_field_validation` filter instead of `gform_validation`.
* The validation message now reads "Please enter a valid IBAN." and is filterable via `gform_iban_validation_message`.
* Updated the bundled php-iban library to the current upstream version (116 country registry, PHP 8 compatible).

= 1.0 =
* First public version of the plugin.

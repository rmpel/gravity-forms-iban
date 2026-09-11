<?php
/**
 * Plugin Name: Gravity Forms IBAN
 * Plugin URI: https://wordpress.org/plugins/gravity-forms-iban/
 * Description: Adds an IBAN mask and IBAN validation to Gravity Forms.
 * Author: Remon Pel, Admium and Jeroen Schmit, Slim & Dapper
 * Version: 1.1.1
 * Author URI: remonpel.nl
 * Text Domain: gravity-forms-iban
 * Domain Path: /languages
 *
 * @package Gravity_Forms_IBAN
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms field properties are camelCase.

defined( 'ABSPATH' ) || exit;

/**
 * The IBAN input mask in Gravity Forms input mask syntax:
 * 2 letters, 2 digits, then up to 32 optional alphanumeric characters in groups of 4.
 *
 * Registered as a regular standard mask so Gravity Forms applies it with its own
 * mask engine; works on both the jquery.maskedinput engine (GF 2.x) and the
 * data-mask engine (GF 3.x).
 */
define( 'GFORM_IBAN_MASK', 'aa99 ?**** **** **** **** **** **** **** ****' );

/**
 * Mask value stored by version 1.0 of this plugin. It relied on the
 * gform_input_mask_script filter (removed in Gravity Forms 3.0) to translate it
 * into the real mask. Forms saved with 1.0 still carry it.
 */
define( 'GFORM_IBAN_LEGACY_MASK_VALUE', 'iban' );

/**
 * Loads the plugin translations bundled in the /languages directory.
 *
 * @since 1.1.1
 * @return void
 */
function gform_iban_load_textdomain() {
	load_plugin_textdomain( 'gravity-forms-iban', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'gform_iban_load_textdomain' );

/**
 * Adds the IBAN mask to the built-in input masks that are displayed in the Text Field input mask setting.
 *
 * @since 1.0
 * @param array $masks Current list of masks to be filtered.
 * @return array The list of masks, including the IBAN mask.
 */
function gform_iban_add_mask( $masks ) {
	$masks['IBAN'] = GFORM_IBAN_MASK;
	return $masks;
}
add_filter( 'gform_input_masks', 'gform_iban_add_mask' );

/**
 * Whether a field is an IBAN-masked field, saved by either this or a previous version of the plugin.
 *
 * @since 1.1
 * @param GF_Field $field The field to inspect.
 * @return bool
 */
function gform_iban_is_iban_field( $field ) {
	return ! empty( $field->inputMask )
		&& in_array( $field->inputMaskValue, array( GFORM_IBAN_MASK, GFORM_IBAN_LEGACY_MASK_VALUE ), true );
}

/**
 * The validation message shown for an invalid IBAN.
 *
 * @since 1.1
 * @param GF_Field $field The field being validated.
 * @return string
 */
function gform_iban_get_validation_message( $field ) {
	/**
	 * Filters the validation message shown for an invalid IBAN.
	 *
	 * @since 1.1
	 * @param string   $message The validation message.
	 * @param GF_Field $field   The field being validated.
	 */
	return apply_filters(
		'gform_iban_validation_message',
		__( 'Please enter a valid IBAN.', 'gravity-forms-iban' ),
		$field
	);
}

/**
 * Rewrites the legacy 'iban' mask value (stored by plugin version 1.0) to the real
 * mask pattern, so Gravity Forms' own mask handling covers the field without the
 * gform_input_mask_script filter that was removed in Gravity Forms 3.0. Editing
 * and re-saving a form in the form editor persists the migrated value.
 *
 * @since 1.1
 * @param array $form The current Form Object.
 * @return array The Form Object with migrated IBAN mask values.
 */
function gform_iban_migrate_legacy_mask( $form ) {
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $form;
	}
	foreach ( $form['fields'] as $field ) {
		if ( ! empty( $field->inputMask ) && GFORM_IBAN_LEGACY_MASK_VALUE === $field->inputMaskValue ) {
			$field->inputMaskValue = GFORM_IBAN_MASK;
		}
	}
	return $form;
}
add_filter( 'gform_pre_render', 'gform_iban_migrate_legacy_mask' );
add_filter( 'gform_pre_validation', 'gform_iban_migrate_legacy_mask' );
add_filter( 'gform_pre_submission_filter', 'gform_iban_migrate_legacy_mask' );
add_filter( 'gform_admin_pre_render', 'gform_iban_migrate_legacy_mask' );

/**
 * Gives IBAN fields without a configured placeholder a readable example, instead
 * of the raw mask pattern Gravity Forms 3.x would otherwise display.
 *
 * Runs after gform_iban_migrate_legacy_mask and only on the front end, so the
 * form editor's placeholder setting stays untouched.
 *
 * @since 1.1
 * @param array $form The current Form Object.
 * @return array The Form Object.
 */
function gform_iban_set_placeholder( $form ) {
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $form;
	}
	foreach ( $form['fields'] as $field ) {
		if ( gform_iban_is_iban_field( $field ) && empty( $field->placeholder ) ) {
			/**
			 * Filters the placeholder shown on IBAN fields that have none configured.
			 *
			 * @since 1.1
			 * @param string   $placeholder The example placeholder.
			 * @param GF_Field $field       The field being rendered.
			 */
			$field->placeholder = apply_filters(
				'gform_iban_placeholder',
				/* translators: Example IBAN shown as input placeholder; localize to an example IBAN of your country. */
				__( 'NL00 BANK 0123 4567 89', 'gravity-forms-iban' ),
				$field
			);
		}
	}
	return $form;
}
add_filter( 'gform_pre_render', 'gform_iban_set_placeholder', 11 );

/**
 * Prepares IBAN fields for validation:
 *
 * Normalizes a submitted valid IBAN to the spaced human format, so machine
 * format input ("NL91ABNA0417164300") passes the server-side mask check that
 * Gravity Forms 3.x performs, and entries store a consistent format.
 *
 * @since 1.1
 * @param array $form The current Form Object.
 * @return array The Form Object.
 */
function gform_iban_prepare_validation( $form ) {
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $form;
	}
	foreach ( $form['fields'] as $field ) {
		if ( ! gform_iban_is_iban_field( $field ) ) {
			continue;
		}

		$input_name = "input_{$field->id}";
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Gravity Forms owns the submission; the value is verified by verify_iban() below.
		$value = isset( $_POST[ $input_name ] ) ? wp_unslash( $_POST[ $input_name ] ) : '';
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			continue;
		}

		require_once __DIR__ . '/lib/php-iban.php';

		$machine = iban_to_machine_format( $value );
		if ( verify_iban( $machine, true ) ) {
			$_POST[ $input_name ] = trim( iban_to_human_format( $machine ) );
		}
	}
	return $form;
}
add_filter( 'gform_pre_validation', 'gform_iban_prepare_validation', 11 );

/**
 * Validates the submitted value of fields with an IBAN mask.
 *
 * @since 1.1
 * @param array    $result The validation result ('is_valid' and 'message').
 * @param mixed    $value  The submitted field value.
 * @param array    $form   The current Form Object.
 * @param GF_Field $field  The field being validated.
 * @return array The new validation result.
 */
function gform_iban_validate_field( $result, $value, $form, $field ) {
	if ( ! gform_iban_is_iban_field( $field ) ) {
		return $result;
	}

	// An empty value is left to the field's own required-check.
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return $result;
	}

	require_once __DIR__ . '/lib/php-iban.php';

	// Also replaces the "Required format: aa99 ..." message of the mask check
	// Gravity Forms 3.x performs itself.
	if ( ! verify_iban( $value ) ) {
		$result['is_valid'] = false;
		$result['message']  = gform_iban_get_validation_message( $field );
	}

	return $result;
}
add_filter( 'gform_field_validation', 'gform_iban_validate_field', 10, 4 );

<?php

// ============================================================================================================================================================
/**
 * Class for managing form data, mostly loading it.
 */
// ============================================================================================================================================================
class GFPaystationFormData {
	public $amount = 0;
	public $total = 0;
	public $formID = 0;

	// field mappings to GF form
	public $MerchantReference; // merchant reference
	public $CustomerDetails; // Optional 255 char string.
	public $OrderDetails; // Optional 255 char string.
	public $PaystationOverrideId; // Optional for overriding the paystation id in the main settings.

	private $isLastPageFlag = false;
	private $isCcHiddenFlag = false;
	private $hasPurchaseFieldsFlag = false;

	/**
	 * initialise instance
	 * @param array $form
	 * @param GFPaystationFeed $feed
	 */
	public function __construct(&$form, $feed) {
		// check for last page
		$current_page = GFFormDisplay::get_source_page($form['id']);
		$target_page = GFFormDisplay::get_target_page($form, $current_page, rgpost('gform_field_values'));
		$this->isLastPageFlag = ($target_page == 0);

		// load the form data
		$this->formID = $form['id'];
		$this->loadForm($form, $feed);
	}

	/**
	 * load the form data we care about from the form array
	 * @param array $form
	 * @param GFPaystationFeed $feed
	 */
	private function loadForm(&$form, $feed) {
		// pick up feed mappings, set special mappings (form ID, title)
		$inverseMap = $feed->getGfFieldMap();

		if (isset($inverseMap['title'])) {
			$this->{$inverseMap['title']} = $form['title'];
		}
		if (isset($inverseMap['form'])) {
			$this->{$inverseMap['form']} = $form['id'];
		}

		// iterate over fields to collect data
		foreach ($form['fields'] as &$field) {
			$fieldName = empty($field['adminLabel']) ? $field['label'] : $field['adminLabel'];
			$id = (string) $field['id'];

			switch (GFFormsModel::get_input_type($field)) {
				case 'total':
					$this->total = GFCommon::to_number(rgpost("input_{$id}"));
					$this->hasPurchaseFieldsFlag = true;
					break;

				default:
					// check for product field
					if (GFCommon::is_product_field($field['type']) || $field['type'] == 'donation') {
						$this->amount += self::getProductPrice($form, $field);
						$this->hasPurchaseFieldsFlag = true;
					}
					break;
			}

			// check for feed mapping
			if (isset($field['inputs']) && is_array($field['inputs'])) {
				// compound field, see if want whole field
				if (isset($inverseMap[$id])) {
					// want whole field, concatenate values
					$values = array();
					foreach ($field['inputs'] as $input) {
						$subID = strtr($input['id'], '.', '_');
						$values[] = trim(rgpost("input_{$subID}"));
					}
					$this->{$inverseMap[$id]} = implode(' ', array_filter($values, 'strlen'));
				}
				else {
					// see if want any part-field
					foreach ($field['inputs'] as $input) {
						$key = (string) $input['id'];
						if (isset($inverseMap[$key])) {
							$subID = strtr($input['id'], '.', '_');
							$this->{$inverseMap[$key]} = rgpost("input_{$subID}");
						}
					}
				}
			}
			else {
				// simple field, just take value
				if (isset($inverseMap[$id])) {
					$this->{$inverseMap[$id]} = rgpost("input_{$id}");
				}
			}
		}

		// if form didn't pass the total, pick it up from calculated amount
		if ($this->total == 0) {
			$this->total = $this->amount;
		}
	}

	/**
	 * extract the price from a product field, and multiply it by the quantity
	 * @return float
	 */
	private static function getProductPrice($form, $field) {
		$price = $qty = 0;
		$isProduct = false;
		$id = $field['id'];

		if (!GFFormsModel::is_field_hidden($form, $field, array())) {
			$lead_value = rgpost("input_{$id}");

			$qty_field = GFCommon::get_product_fields_by_type($form, array('quantity'), $id);
			$qty = sizeof($qty_field) > 0 ? rgpost("input_{$qty_field[0]['id']}") : 1;

			switch ($field["inputType"]) {
				case 'singleproduct':
					$price = GFCommon::to_number(rgpost("input_{$id}_2"));
					$qty = GFCommon::to_number(rgpost("input_{$id}_3"));
					$isProduct = true;
					break;

				case 'hiddenproduct':
					$price = GFCommon::to_number($field["basePrice"]);
					$isProduct = true;
					break;

				case 'donation':
				case 'price':
					$price = GFCommon::to_number($lead_value);
					$isProduct = true;
					break;

				default:
					// handle drop-down lists
					if (!empty($lead_value)) {
						list($name, $price) = rgexplode('|', $lead_value, 2);
						$isProduct = true;
					}
					break;
			}

			// pick up extra costs from any options
			if ($isProduct) {
				$options = GFCommon::get_product_fields_by_type($form, array('option'), $id);
				foreach ($options as $option) {
					if (!GFFormsModel::is_field_hidden($form, $option, array())) {
						$option_value = rgpost("input_{$option['id']}");

						if (is_array(rgar($option, 'inputs'))) {
							foreach ($option['inputs'] as $input) {
								$input_value = rgpost('input_' . str_replace('.', '_', $input['id']));
								$option_info = GFCommon::get_option_info($input_value, $option, true);
								if (!empty($option_info)) {
									$price += GFCommon::to_number(rgar($option_info, 'price'));
								}
							}
						}
						elseif (!empty($option_value)) {
							$option_info = GFCommon::get_option_info($option_value, $option, true);
							$price += GFCommon::to_number(rgar($option_info, 'price'));
						}
					}
				}
			}

			// Convert $price and $qty to integers before multiplying
			$price = intval($price);
			$qty = intval($qty);
			$price *= $qty;
		}

		return $price;
	}

	/**
	 * check whether we're on the last page of the form
	 * @return boolean
	 */
	public function isLastPage() {
		return $this->isLastPageFlag;
	}

	/**
	 * check whether CC field is hidden (which indicates that payment is being made another way)
	 * @return boolean
	 */
	public function isCcHidden() {
		return $this->isCcHiddenFlag;
	}

	/**
	 * check whether form has any product fields (because CC needs something to bill against)
	 * @return boolean
	 */
	public function hasPurchaseFields() {
		return $this->hasPurchaseFieldsFlag;
	}
}

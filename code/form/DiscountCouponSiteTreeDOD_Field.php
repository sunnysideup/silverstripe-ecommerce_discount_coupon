<?php


class DiscountCouponSiteTreeDOD_Field extends TreeMultiselectField {

	/**
	 *
	 * TO DO: explain how this works or what it does.
	 */
	public function saveInto(DataObjectInterface $record) {
		if($this->value !== 'unchanged') {
			$items = array();

			$fieldName = $this->name;

			if($this->value) {
				$items = preg_split("/ *, */", trim($this->value));
			}

			// Allows you to modify the items on your object before save
			$funcName = "onChange$fieldName";
			if($record->hasMethod($funcName)){
				$result = $record->$funcName($items);
				if(!$result){
					return;
				}
			}
			if ($fieldName && ($record->has_many($fieldName) || $record->many_many($fieldName))) {
				// Set related records
				$record->$fieldName()->setByIDList($items);
			}
			else {
				$record->$fieldName = implode(',', $items);
			}
		}
	}
}

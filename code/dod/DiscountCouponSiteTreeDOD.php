<?php


class DiscountCouponSiteTreeDOD extends DataObjectDecorator {

	function extraStatics() {
		return array(
			'db' => array(
				'PageIDs' => 'Text'
			)
		);
	}

	function updateCMSFields(FieldSet &$fields) {
		$fields->addFieldToTab('Root.Permissions', new DiscountCouponSiteTreeDOD_Field('PageIDs', 'Product Groups, Products (If nothing is selected, the discount will apply to the all site. The discount will also apply to all variations of a product.)', 'SiteTree'));
	}

	function canBeDiscounted(SiteTree $page) {
		if($this->owner->PageIDs) {
			$pageIDs = explode(',', $this->owner->PageIDs);
			while($page && $page->exists()) {
				if(array_search($page->ID, $pageIDs) !== false) {
					return true;
				}
				$page = $page->Parent();
			}
			return false;
		}
		return true;
	}
}

class DiscountCouponSiteTreeDOD_Product extends DataObjectDecorator {

	function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier) {
		$coupon = $modifier->DiscountCouponOption();
		return ! $coupon->canBeDiscounted($this->owner);
	}
}

class DiscountCouponSiteTreeDOD_ProductVariation extends DataObjectDecorator {

	function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier) {
		$coupon = $modifier->DiscountCouponOption();
		return ! $coupon->canBeDiscounted($this->owner->Product());
	}
}

class DiscountCouponSiteTreeDOD_Field extends TreeMultiselectField {

	function saveInto(DataObject $record) {
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
			} else {
				$record->$fieldName = implode(',', $items);
			}
		}
	}
}
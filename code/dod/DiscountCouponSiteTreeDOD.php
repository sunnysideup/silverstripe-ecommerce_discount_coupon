<?php
/**
 * @author Romain@sunnysideup .co dot nz
 *
 */

class DiscountCouponSiteTreeDOD extends DataObjectDecorator {

	function extraStatics() {
		return array(
			'db' => array(
				'PageIDs' => 'Text(700)'
			)
		);
	}

	function updateCMSFields(FieldSet &$fields) {
		$label = _t(
			"SELECTPRODUCTSANDCATEGORIES",
			"Select Product Categories and/or Products (if nothing is selected, the discount coupon will apply to all buyables)."
		);
		$field = new DiscountCouponSiteTreeDOD_Field(
			$name = "PageIDs",
			$title = $label,
			$sourceObject = "SiteTree",
			$keyField = "ID",
			$labelField = "MenuTitle"
		);
		$filter = create_function('$obj', 'return ( ( $obj InstanceOf ProductGroup || $obj InstanceOf Product) && ($obj->ParentID != '.$this->owner->ID.'));');
		$field->setFilterFunction($filter);
		$fields->addFieldToTab('Root.AppliesTo', $field);
	}

	/**
	 * normally returns TRUE, but returns FALSE when it, or its parent is in the list.
	 * todo: add products in other product categories
	 * @return Boolean
	 */
	function canBeDiscounted(SiteTree $page) {
		if($this->owner->PageIDs) {
			$allowedPageIDs = explode(',', $this->owner->PageIDs);
			$checkPages = new DataObjectSet(array($page));
			$alreadyCheckedPageIDs = array();
			while($checkPages->Count()) {
				$page = $checkPages->First();
				if(array_search($page->ID, $allowedPageIDs) !== false) {
					return true;
				}
				$alreadyCheckedPageIDs[] = $page->ID;
				$checkPages = $checkPages->toArray('ID');
				unset($checkPages[$page->ID]);
				$checkPages = new DataObjectSet($checkPages);

				// Parents list update

				$parents = new DataObjectSet();
				if($page->hasMethod('AllParentGroups')) {
					$parents = $page->AllParentGroups();
				}
				$parent = $page->Parent();
				if($parent && $parent->exists()) {
					$parents->insertFirst($parent);
				}

				foreach($parents as $parent) {
					if(array_search($parent->ID, $alreadyCheckedPageIDs) === false) {
						$checkPages->push($parent);
					}
				}
				$checkPages->removeDuplicates();
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

	/**
	 *
	 * TO DO: explain how this works or what it does.
	 */
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
			}
			else {
				$record->$fieldName = implode(',', $items);
			}
		}
	}
}

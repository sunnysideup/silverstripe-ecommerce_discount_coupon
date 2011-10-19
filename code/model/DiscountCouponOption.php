<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class DiscountCouponOption extends DataObject {

	static $db = array(
		"Title" => "Varchar(25)",
		"Code" => "Varchar(25)",
		"StartDate" => "Date",
		"EndDate" => "Date",
		"DiscountAbsolute" => "Currency",
		"DiscountPercentage" => "Decimal(4,2)",
		"MinimumOrderSubTotalValue" => "Currency",
		"NumberOfTimesCouponCanBeUsed" => "Int"
	);


	function populateDefaults() {
		parent::populateDefaults();
		$this->Code = $this->createRandomCode();
	}

	public static $casting = array(
		"UseCount" => "Int",
		"IsValid" => "Boolean"
	);

	public static $searchable_fields = array(
		"Code" => "PartialMatchFilter",
	);

	public static $field_labels = array(
		"Title" => "Name (for internal use only)",
		"DiscountAbsolute" => "Discount as absolute reduction of total - if any (e.g. 10 = -$10.00)",
		"DiscountPercentage" => "Discount as percentage of total - if any (e.g. 10 = -10%)",
		"UseCount" => "number of times the code has been used"
	);

	public static $summary_fields = array(
		"Title",
		"Code",
		"StartDate",
		"EndDate"
	);


	public static $singular_name = "Discount Coupon";
		function i18n_single_name() { return _t("ModifierExample.MODIFIEREXAMPLE", "Discount Coupon");}

	public static $plural_name = "Discount Coupons";
		function i18n_plural_name() { return _t("ModifierExample.MODIFIEREXAMPLES", "Discount Coupons");}


	public static $default_sort = "EndDate DESC, StartDate DESC";

	function UseCount() {return $this->getUseCount();}
	function getUseCount() {
		$objects = DataObject::get("DiscountCouponModifier", "\"DiscountCouponOptionID\" = ".$this->ID);
		if($objects) {
			return $objects->count();
		}
		return 0;
	}

	function IsValid() {return $this->getIsValid();}
	function getIsValid() {
		if($this->getUseCount() < $this->NumberOfTimesCouponCanBeUsed) {
			$startDate = strtotime($this->StartDate);
			$endDate = strtotime($this->EndDate);
			$today = strtotime("today");
			$yesterday = strtotime("yesterday");
			if($startDate < $today && $endDate > $yesterday) {
				return true;
			}
		}
		return false;
	}


	function canDelete($member = null) {
		return $this->canEdit($member);
	}

	function canEdit($member = null) {
		if($this->UseCount()) {
			return false;
		}
		return true;
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();
		return $fields;
	}

	function onBeforeWrite() {
		if(!$this->Code) {
			$this->Code = $this->createRandomCode();
		}
		$this->Code = eregi_replace("[^[:alnum:]]", " ", $this->Code );
		$this->Code = trim(eregi_replace(" +", "", $this->Code));
		parent::onBeforeWrite();
	}

	protected function createRandomCode($length = 5, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890'){
		$chars_length = (strlen($chars) - 1);
		$string = $chars{rand(0, $chars_length)};
		for ($i = 1; $i < $length; $i = strlen($string)){
			$r = $chars{rand(0, $chars_length)};
			if ($r != $string{$i - 1}) {
				$string .=  $r;
			}
		}
		return $string;
	}

}


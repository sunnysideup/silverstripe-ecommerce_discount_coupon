<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class DiscountCouponOption extends DataObject {

	static $db = array(
		"Code" => "Varchar(25)",
		"StartDate" => "Date",
		"EndDate" => "Date",
		"DiscountAbsolute" => "Currency",
		"DiscountPercentage" => "Decimal(4,2)",
		"CanOnlyBeUsedOnce" => "Boolean"
	);

	public static $casting = array(
		"UseCount" => "Int",
		"IsValid" => "Boolean"
	);

	public static $searchable_fields = array(
		"Code"
	);

	public static $field_labels = array(
		"DiscountAbsolute" => "Discount as absolute reduction for code - if any (e.g. $10.00)",
		"DiscountPercentage" => "Discount as percentage of total - if any (e.g. 10%)",
		"UseCount" => "number of times the code has been used"
	);

	public static $summary_fields = array(
		"Code",
		"StartDate",
		"EndDate"
	);

	protected static $coupons_can_only_be_used_once = "";
		static function set_coupons_can_only_be_used_once($b) {self::$coupons_can_only_be_used_once = $b;}
		static function get_coupons_can_only_be_used_once() {return self::$coupons_can_only_be_used_once;}

	public static $singular_name = "Discount Coupon";

	public static $plural_name = "Discount Coupon";

	public static $default_sort = "EndDate DESC, StartDate DESC";

	function UseCount() {
		$objects = DataObject::get("DiscountCouponModifier", "\"DiscountCouponOptionID\" = ".$this->ID);
		if($objects) {
			return $objects->count();
		}
		return 0;
	}

	function IsValid() {
		if($this->UseCount() > 0 && $this->CanOnlyBeUsedOnce) {
			return false;
		}
		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$today = strtotime("today");
		$yesterday = strtotime("yesterday");
		if($startDate < $today && $endDate > $yesterday) {
			return true;
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
		$this->Code = eregi_replace("[^[:alnum:]]", " ", $this->Code );
		$this->Code = trim(eregi_replace(" +", "", $this->Code));
		parent::onBeforeWrite();
	}

}


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
		"MaximumDiscount" => "Currency",
		"DiscountAbsolute" => "Currency",
		"DiscountPercentage" => "Decimal(4,2)",
		"MinimumOrderSubTotalValue" => "Currency",
		"NumberOfTimesCouponCanBeUsed" => "Int"
	);



	/**
	 * standard SS variable
	 *
	 */
	public static $casting = array(
		"UseCount" => "Int",
		"IsValid" => "Boolean",
		"IsValidNice" => "Boolean"
	);


	/**
	 * standard SS variable
	 *
	 */
	public static $searchable_fields = array(
		"Code" => "PartialMatchFilter",
	);

	/**
	 * standard SS variable
	 *
	 */
	public static $field_labels = array(
		"Title" => "Name (for internal use only)",
		"MaximumDiscount" => "Maximum deduction (set to zero to ignore)",
		"DiscountAbsolute" => "Discount as absolute reduction of total - if any (e.g. 10 = -$10.00)",
		"DiscountPercentage" => "Discount as percentage of total - if any (e.g. 10 = -10%)",
		"UseCount" => "number of times this coupon has been used",
		"IsValidNice" => "coupon is currently valid"
	);

	/**
	 * standard SS variable
	 *
	 */
	public static $summary_fields = array(
		"Title",
		"Code",
		"StartDate",
		"EndDate"
	);


	/**
	 * standard SS variable
	 *
	 */
	public static $singular_name = "Discount Coupon";
		function i18n_single_name() { return _t("DiscountCouponOption.DISCOUNTCOUPON", "Discount Coupon");}


	/**
	 * standard SS variable
	 *
	 */
	public static $plural_name = "Discount Coupons";
		function i18n_plural_name() { return _t("DiscountCouponOption.DISCOUNTCOUPONS", "Discount Coupons");}


	/**
	 * standard SS variable
	 *
	 */
	public static $default_sort = "EndDate DESC, StartDate DESC";


	/**
	 * standard SS method
	 *
	 */
	function populateDefaults() {
		parent::populateDefaults();
		$this->Code = $this->createRandomCode();
	}

	/**
	 * casted variable
	 * returns the number of times this coupon has been used.
	 * Some of the used coupons are not submitted yet, but it should still
	 * work on first come first served basis.
	 *
	 * @return Int
	 */
	function UseCount() {return $this->getUseCount();}
	function getUseCount() {
		$objects = DataObject::get("DiscountCouponModifier", "\"DiscountCouponOptionID\" = ".$this->ID);
		if($objects) {
			return $objects->count();
		}
		return 0;
	}

	/**
	 * casted variable telling us if the discount coupon is valid.
	 *
	 * @return Bool
	 */
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

	/**
	 * casted variable telling us if the discount coupon is valid - formatted nicely...
	 *
	 * @return String
	 */
	function IsValidNice() {return $this->getIsValidNice();}
	function getIsValidNice() {
		return $this->IsValid() ? "yes" : "no";
	}

	/**
	 * standard SS method
	 *
	 */
	function canDelete($member = null) {
		return $this->canEdit($member);
	}

	/**
	 * standard SS method
	 *
	 */
	function canEdit($member = null) {
		if($this->UseCount()) {
			return false;
		}
		return true;
	}

	/**
	 * standard SS method
	 *
	 */
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Main", new ReadonlyField("IsValidNice", self::$field_labels["IsValidNice"]));
		$fields->addFieldToTab("Root.Main", new ReadonlyField("UseCount", self::$field_labels["UseCount"]));
		return $fields;
	}

	/**
	 * standard SS method
	 *
	 */
	function validate(){
		$validator = new ValidationResult();
		if(isset($_REQUST["StartDate"])) {
			$this->StartDate == date("Y-m-d", strtotime($_REQUST["StartDate"]));
		}
		if(isset($_REQUST["EndDate"])) {
			$this->EndDate == date("Y-m-d", strtotime($_REQUST["EndDate"]));
		}
		if(strtotime($this->StartDate) < strtotime("1 jan 1971") ) {
			$validator->error(_t('DiscountCouponOption.NOSTARTDATE', "COULD NOT SAVE: Please enter a start date."));
		}
		if(strtotime($this->EndDate) < strtotime("1 jan 1971") ) {
			$validator->error(_t('DiscountCouponOption.NOENDDATE', "COULD NOT SAVE: Please enter an end date."));
		}
		if(strtotime($this->EndDate) < strtotime($this->StartDate)) {
			$validator->error(_t('DiscountCouponOption.ENDDATETOOEARLY', "COULD NOT SAVE: The end date should be after the start date."));
		}
		return $validator;
	}


	/**
	 * standard SS method
	 *
	 */
	function onBeforeWrite() {
		if(!$this->Code) {
			$this->Code = $this->createRandomCode();
		}
		$this->Code = eregi_replace("[^[:alnum:]]", " ", $this->Code );
		$this->Code = trim(eregi_replace(" +", "", $this->Code));
		parent::onBeforeWrite();
	}

	/**
	 * returns a random string.
	 * @param Int $length - number of characters
	 * @param Int $chars - input characters
	 * @return string
	 */
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


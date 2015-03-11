<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *@author romain [at] sunnysideup.co.nz
 *
 **/

class DiscountCouponOption extends DataObject {

	private static $db = array(
		'Title' => 'Varchar(25)',
		'Code' => 'Varchar(25)',
		'StartDate' => 'Date',
		'EndDate' => 'Date',
		'MaximumDiscount' => 'Currency',
		'DiscountAbsolute' => 'Currency',
		'DiscountPercentage' => 'Decimal(4,2)',
		'MinimumOrderSubTotalValue' => 'Currency',
		'NumberOfTimesCouponCanBeUsed' => 'Int'
	);


	private static $many_many = array(
		'Products' => 'Product'
	);

	/**
	 * standard SS variable
	 *
	 */
	private static $casting = array(
		"UseCount" => "Int",
		"IsValid" => "Boolean",
		"IsValidNice" => "Varchar"
	);


	/**
	 * standard SS variable
	 *
	 */
	private static $searchable_fields = array(
		"Code" => "PartialMatchFilter",
	);

	/**
	 * standard SS variable
	 *
	 */
	private static $field_labels = array(
		'StartDate' => 'First date the coupon can be used',
		'EndDate' => 'Last day the coupon can be used',
		"Title" => "Name (for internal use only)",
		"MaximumDiscount" => "Maximum deduction (set to zero to ignore)",
		"DiscountAbsolute" => "Discount as absolute reduction of total - if any (e.g. 10 = -$10.00)",
		"DiscountPercentage" => "Discount as percentage of total - if any (e.g. 10 = -10%)",
		"NumberOfTimesCouponCanBeUsed" => "Number of times the coupon can be used, set to zero to disallow usage",
		"UseCount" => "Number of times this coupon has been used",
		"IsValidNice" => "coupon is currently valid",
		"Products" => "only applies to the following products (optional)"
	);

	/**
	 * standard SS variable
	 *
	 */
	private static $summary_fields = array(
		"Title",
		"Code",
		"StartDate",
		"EndDate"
	);


	/**
	 * standard SS variable
	 *
	 */
	private static $singular_name = "Discount Coupon";
		function i18n_singular_name() { return _t("DiscountCouponOption.DISCOUNTCOUPON", "Discount Coupon");}

	/**
	 * standard SS variable
	 *
	 */
	private static $plural_name = "Discount Coupons";
		function i18n_plural_name() { return _t("DiscountCouponOption.DISCOUNTCOUPONS", "Discount Coupons");}

	/**
	 * standard SS variable
	 *
	 */
	private static $default_sort = "EndDate DESC, StartDate DESC";

	/**
	 *
	 * @var Boolean
	 */
	protected $isNew = false;

	/**
	 * standard SS method
	 *
	 */
	function populateDefaults() {
		parent::populateDefaults();
		$this->Code = $this->createRandomCode();
		$this->isNew = true;
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
		return DiscountCouponModifier::get()->filter(array("DiscountCouponOptionID" => $this->ID))->count();
	}

	/**
	 * casted variable telling us if the discount coupon is valid.
	 *
	 * @return Bool
	 */
	function IsValid() {return $this->getIsValid();}
	function getIsValid() {
		if(! $this->NumberOfTimesCouponCanBeUsed || $this->getUseCount() < $this->NumberOfTimesCouponCanBeUsed) {
			$startDate = strtotime($this->StartDate);
			$endDate = strtotime($this->EndDate);
			$today = strtotime("today");
			$yesterday = strtotime("yesterday");
			if($startDate <= $today && $endDate > $yesterday) {
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
	 * @param Member | NULL
	 * @return Boolean
	 */
	public function canCreate($member = null){
		if(Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {return true;}
		return parent::canCreate($member);
	}

	/**
	 * standard SS method
	 * @param Member | NULL
	 * @return Boolean
	 */
	public function canView($member = null){
		if(Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {return true;}
		return parent::canCreate($member);
	}

	/**
	 * standard SS method
	 * @param Member | NULL
	 * @return Boolean
	 */
	public function canEdit($member = null){
		if(Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {return true;}
		return parent::canEdit($member);
	}

	/**
	 * standard SS method
	 * @param Member | NULL
	 * @return Boolean
	 */
	function canDelete($member = null) {
		if($this->UseCount()) {
			return false;
		}
		if(Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {return true;}
		return parent::canDelete($member);
	}

	/**
	 * standard SS method
	 *
	 */
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Main", new ReadonlyField("UseCount", self::$field_labels["UseCount"]));
		$fields->addFieldToTab("Root.Main", new ReadonlyField("IsValidNice", self::$field_labels["IsValidNice"]));
		if($gridField = $fields->dataFieldByName("Products")) {
			$gridField->getConfig()
				->removeComponentsByType("GridFieldEditButton")
				->removeComponentsByType("GridFieldDeleteAction")
				->removeComponentsByType("GridFieldAddNewButton")
				->addComponent(new GridFieldAddNewButtonOriginalPage())
				->addComponent(new GridFieldEditButtonOriginalPage());
		}
		return $fields;
	}

	/**
	 * standard SS method
	 * THIS ONLY WORKS FOR CREATED OBJECTS
	 */

	public function validate(){
		$validator = parent::validate();
		if(!$this->isNew) {
			if($this->thereAreCouponsWithTheSameCode()) {
				$validator->error(_t('DiscountCouponOption.CODEALREADYEXISTS', "This code already exists - please use another code."));
			}
		}
		return $validator;
		/*
		$validator = parent::validate();
		if($this->thereAreCouponsWithTheSameCode()) {
			$validator->error(_t('DiscountCouponOption.CODEALREADYEXISTS', "This code already exists - please use another code."));
		}
		if(isset($_REQUEST["StartDate"])) {
			$this->StartDate = date("Y-m-d", strtotime($_REQUEST["StartDate"]));
		}
		if(isset($_REQUEST["EndDate"])) {
			$this->EndDate = date("Y-m-d", strtotime($_REQUEST["EndDate"]));
		}
		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$minDate = strtotime("1 jan 1980");
		if($startDate < $minDate ) {
			$validator->error(_t('DiscountCouponOption.NOSTARTDATE', "Please enter a start date. "));
		}
		if($endDate < $minDate ) {
			$validator->error(_t('DiscountCouponOption.NOENDDATE', "Please enter an end date. "));
		}
		if($endDate < $startDate) {
			$validator->error(_t('DiscountCouponOption.ENDDATETOOEARLY', "The end date should be after the start date. "));
		}
		return $validator;
		*/
	}

	/**
	 * Checks if there are coupons with the same code as the current one
	 * @return Boolean
	 */
	protected function thereAreCouponsWithTheSameCode(){
		return DiscountCouponOption::get()->exclude(array("ID" => $this->ID))->filter(array("Code" => $this->Code))->count() ? true : false;
	}


	/**
	 * standard SS method
	 *
	 */
	function onBeforeWrite() {
		parent::onBeforeWrite();
		if(!$this->Code) {
			$this->Code = $this->createRandomCode();
		}
		$this->Code = preg_replace('/[^a-z0-9]/i', " ", $this->Code );
		$this->Code = trim(preg_replace('/\s+/', "", $this->Code));
		$i = 1;
		while($this->thereAreCouponsWithTheSameCode() && $i < 100) {
			$i++;
			$this->Code = $this->Code."".$i;
		}
		if(!$this->Name) {
			$this->Name = $this->Code;
		}
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


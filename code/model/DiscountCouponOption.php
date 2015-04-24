<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/

class DiscountCouponOption extends DataObject {

	private static $db = array(
		'ApplyEvenWithoutCode' => 'Boolean(1)',
		'ApplyPercentageToApplicableProducts' => 'Boolean(1)',
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
		'Products' => 'Product',
		'ProductGroups' => 'ProductGroup'
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
		'StartDate' => 'Start Date',
		'EndDate' => 'Last Day',
		"Title" => "Name",
		"MaximumDiscount" => "Maximum deduction",
		"DiscountAbsolute" => "Discount as absolute reduction of total",
		"DiscountPercentage" => "Discount as percentage of total / matching products",
		"ApplyPercentageToApplicableProducts" => "Applicable products only",
		"NumberOfTimesCouponCanBeUsed" => "Availability count",
		"UseCount" => "Count of usage thus far",
		"IsValidNice" => "Current validity",
		"ApplyEvenWithoutCode" => "Automatically applied",
		"Products" => "Applicable products",
		"ProductGroups" => "Applicable Categories",
	);

	/**
	 * standard SS variable
	 *
	 */
	private static $field_labels_right = array(
		'Code' => 'What the user enters',
		'StartDate' => 'First date the coupon can be used',
		'EndDate' => 'Last day the coupon can be used',
		"Title" => "for internal use only",
		"MaximumDiscount" => "set to zero to ignore",
		"DiscountAbsolute" => "e.g. 0.1 = -$10.00, set to zero to ignore ",
		"DiscountPercentage" => "e.g. 10 = -10%, set to zero to ignore",
		"MinimumOrderSubTotalValue" => "minimum sub-total of total order to make coupon applicable",
		"ApplyPercentageToApplicableProducts" => "rather than applying it to the order, the discount is directly applied to selected products (you must select products).",
		"NumberOfTimesCouponCanBeUsed" => "set to zero to disallow usage, set to 99999 to allow unlimited usage",
		"UseCount" => "number of times this coupon has been used",
		"IsValidNice" => "coupon is currently valid",
		"ApplyEvenWithoutCode" => "automatically applied - the user does not have to enter the coupon at all",
		"Products" => "This is the final list of products to which the coupon applies - if no products are selected, it applies to all products",
		"ProductGroups" => "Adding product categories helps you to select a large number of products at once.",
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
		if($this->ID) {
			return DiscountCouponModifier::get()->filter(array("DiscountCouponOptionID" => $this->ID))->count();
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
		//we go through all the options that would make it invalid...
		if(! $this->NumberOfTimesCouponCanBeUsed) {
			return false;
		}
		if($this->getUseCount() > $this->NumberOfTimesCouponCanBeUsed) {
			return false;
		}
		$now = strtotime("now");
		$startDate = strtotime($this->StartDate);
		if($now < $startDate) {
			return false;
		}
		//include the end date itself.
		if($this->EndDate) {
			$endDate = strtotime($this->EndDate)+(60*60*24);
			if($now > $endDate) {
				return false;
			}
		}
		return true;
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
		$fieldLabels = $this->Config()->get("field_labels_right");
		foreach($fields->dataFields() as $field) {
			$name = $field->getName();
			if(isset($fieldLabels[$name])) {
				$field->setDescription($fieldLabels[$name]);
			}
		}
		if($this->ApplyEvenWithoutCode) {
			$fields->removeFieldsFromTab(
				"Root.Main",
				array(
					'Code',
					'MaximumDiscount',
					'MinimumOrderSubTotalValue'
				)
			);
		}
		if($this->ApplyPercentageToApplicableProducts) {
			$fields->removeFieldsFromTab(
				"Root.Main",
				array(
					'DiscountAbsolute'
				)
			);
		}
		$fields->addFieldToTab("Root.Main", new ReadonlyField("UseCount", self::$field_labels["UseCount"]));
		$fields->addFieldToTab("Root.Main", new ReadonlyField("IsValidNice", self::$field_labels["IsValidNice"]));
		if($gridField1 = $fields->dataFieldByName("Products")) {
			$gridField1->setConfig(GridFieldEditOriginalPageConfig::create());
		}
		if($gridField2 = $fields->dataFieldByName("ProductGroups")) {
			$gridField2->setConfig(GridFieldEditOriginalPageConfig::create());
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
			if(isset($_REQUEST["StartDate"])) {
				$this->StartDate = date("Y-m-d", strtotime($_REQUEST["StartDate"]));
			}
                        if(isset($_REQUEST["EndDate"])) {
                                $this->EndDate = date("Y-m-d", strtotime($_REQUEST["EndDate"]));
                        }
			if(strtotime($this->StartDate) < strtotime("-12 years") ) {
				$validator->error(_t('DiscountCouponOption.NOSTARTDATE', "Please enter a start date"));
			}
			if(strtotime($this->EndDate) < strtotime("-12 years") ) {
				$validator->error(_t('DiscountCouponOption.NOENDDATE', "Please enter an end date"));
			}
			if(strtotime($this->EndDate) < strtotime($this->StartDate)) {
				$validator->error(_t('DiscountCouponOption.ENDDATETOOEARLY', "The end date should be after the start date"));
			}
			if($this->DiscountPercentage < 0 || $this->DiscountPercentage > 99.999) {
				$validator->error(_t('DiscountCouponOption.DISCOUNTOUTOFBOUNDS', "The discount percentage should be between 0 and 99.999."));
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
			$this->Code = $this->Code."_".$i;
		}
		if(strlen(trim($this->Title)) < 1) {
			$this->Title = $this->Code;
		}
		if($this->ApplyEvenWithoutCode) {
			$this->Code = "";
			$this->MaximumDiscount = 0;
			$this->MinimumOrderSubTotalValue = 0;
		}
		if($this->ApplyPercentageToApplicableProducts) {
			$this->DiscountAbsolute = 0;
		}
	}

	protected $_productsCalculated = false;
	/**
	 * standard SS method
	 *
	 */
	function onAfterWrite() {
		$productsArray = array(0 => 0);
		parent::onAfterWrite();
		if(!$this->_productsCalculated) {
			$this->_productsCalculated = true;
			$productGroups = $this->ProductGroups();
			foreach($productGroups as $productGroup) {
				$productsShowable = $productGroup->currentInitialProducts(null, "default");
				if($productsShowable && $productsShowable->count()) {
					$productsShowable = $productsShowable->exclude("ID", $productsArray);
					if($productsShowable && $productsShowable->count()) {
						$productsShowableArray = $productsShowable->map("ID", "ID")->toArray();
						$productsArray += $productsShowableArray;
					}
				}
			}
			$this->Products()->addMany($productsArray);
			$this->write();
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


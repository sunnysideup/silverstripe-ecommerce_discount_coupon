<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_delivery
 * @description: Shipping calculation scheme based on SimpleShippingModifier.
 * It lets you set fixed shipping costs, or a fixed
 * cost for each region you're delivering to.
 */

class DiscountCouponModifier extends OrderModifier {

// ######################################## *** model defining static variables (e.g. $db, $has_one)

	public static $db = array(
		'DebugString' => 'HTMLText',
		'SubTotalAmount' => 'Currency',
		'CouponCodeEntered' => 'Varchar(25)'
	);

	public static $has_one = array(
		"DiscountCouponOption" => "DiscountCouponOption"
	);

	//TODO: use running total as point where you add the discount
	protected static $include_modifiers_in_subtotal = false;
		public static function set_include_modifiers_in_subtotal($b) {self::$include_modifiers_in_subtotal = $b;}
		public static function get_include_modifiers_in_subtotal() {return self::$include_modifiers_in_subtotal;}

// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)


	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName("DebugString");
		$fields->removeByName("SubTotalAmount");
		$fields->removeByName("OrderCoupon");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("SubTotalAmountShown", "sub-total amount", $this->SubTotalAmount));
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("DebugStringShown", "debug string", $this->DebugString));
		return $fields;
	}

	public static $singular_name = "Discount Coupon Entry";
		function i18n_singular_name() { return _t("ModifierExample.MODIFIEREXAMPLE", "Discount Coupon Entry");}

	public static $plural_name = "Discount Coupon Entries";
		function i18n_plural_name() { return _t("ModifierExample.MODIFIEREXAMPLES", "Discount Coupon Entries");}


// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)

	protected $actualDeductions = null;

	protected $debugMessage = "";


// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions
	/**
	 * updates all database fields
	 *
	 * @param Bool $force - run it, even if it has run already
	 */
	public function runUpdate($force = false) {
		if(!$this->IsRemoved()) {
			$this->checkField("SubTotalAmount");
			$this->checkField("CouponCodeEntered");
			$this->checkField("DiscountCouponOptionID");
		}
		parent::runUpdate($force);
	}



// ######################################## *** form functions (e. g. showform and getform)


	public function showForm() {
		return $this->Order()->Items();
	}

	function getModifierForm($optionalController = null, $optionalValidator = null) {
		$fields = new FieldSet(
			$this->headingField(),
			$this->descriptionField(),
			new TextField('DiscountCouponCode',_t("DiscountCouponModifier.COUPON", 'Coupon', $this->CouponCodeEntered))
		);
		$actions = new FieldSet(
			new FormAction('submit', _t("DiscountCouponModifier.APPLY", 'Apply Coupon'))
		);
		$form = new DiscountCouponModifier_Form($optionalController, 'DiscountCouponModifier', $fields, $actions, $optionalValidator);
		$fields->fieldByName("DiscountCouponCode")->setValue($this->CouponCodeEntered);
		return $form;
	}

	/**
	 *@param String $code - code that has been entered
	 *@return Int - only returns a positive value (ID of Discount Coupom) if the coupon entered is valid
	 **/


	public function updateCouponCodeEntered($code) {
		$this->CouponCodeEntered = $code;
		$discountCoupon = DataObject::get_one("DiscountCouponOption", "\"Code\" = '".$code."'");
		if($discountCoupon && $discountCoupon->IsValid()) {
			$this->DiscountCouponOptionID = $discountCoupon->ID;
		}
		else {
			$this->DiscountCouponOptionID = 0;
		}
		$this->write();
		return $this->DiscountCouponOptionID;
	}

	public function setCoupon($discountCoupon) {
		$this->DiscountCouponOptionID = $discountCoupon->ID;
		$this->write();
	}


	public function setCouponByID($discountCouponID) {
		$this->DiscountCouponOptionID = $discountCouponID;
		$this->write();
	}



// ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES

	/**
	*@return boolean
	**/
	public function ShowInTable() {
		return true;
	}

	/**
	*@return boolean
	**/
	public function CanRemove() {
		return false;
	}


	/**
	*@return float
	**/
	public function CartValue() {return $this->getCartValue();}
	public function getCartValue() {
		return $this->TableValue;
	}


// ######################################## ***  inner calculations.... USES CALCULATED VALUES


	/**
	*@return DiscountCouponOption
	**/
	protected function myDiscountCouponOption() {
		if($id = $this->LiveDiscountCouponOptionID()){
			return DataObject::get_by_id("DiscountCouponOption", $id);
		}
	}


// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES


	/**
	*@return int
	**/
	protected function LiveName() {
		$code = $this->LiveCouponCodeEntered();
		$coupon = $this->myDiscountCouponOption();
		if($coupon) {
			return _t("DiscountCouponModifier.COUPON", "Coupon '").$code._t("DiscountCouponModifier.APPLIED", "' applied.");
		}
		elseif($code) {
			return  _t("DiscountCouponModifier.COUPON", "Coupon '").$code._t("DiscountCouponModifier.COULDNOTBEAPPLIED", "' could not be applied.");
		}
		return _t("DiscountCouponModifier.NOCOUPONENTERED", "No Coupon Entered").$code;
	}

	/**
	*@return float
	**/
	protected function LiveSubTotalAmount() {
		$order = $this->Order();
		$subTotal = $order->SubTotal();
		if(self::$include_modifiers_in_subtotal) {
			$subTotal += $order->ModifiersSubTotal(array(get_class($this)));
		}
		return $subTotal;
	}

	/**
	*@return float
	**/
	protected function LiveCalculatedTotal() {
		$this->actualDeductions = 0;
		$this->DebugString = "";
		$subTotal = $this->LiveSubTotalAmount();
		if($coupon = $this->myDiscountCouponOption()) {
			if($coupon->MinimumOrderSubTotalValue > 0 && $subTotal < $coupon->MinimumOrderSubTotalValue) {
				$this->actualDeductions = 0;
				$this->DebugString .= "<hr />sub-total is too low to offer any discount: ".$this->actualDeductions;
			}
			else {
				if($coupon->DiscountAbsolute > 0) {
					$this->actualDeductions += $coupon->DiscountAbsolute;
					$this->DebugString .= "<hr />using absolutes for coupon discount: ".$this->actualDeductions;
				}
				if($coupon->DiscountPercentage > 0) {
					$this->actualDeductions += ($coupon->DiscountPercentage / 100) * $subTotal;
					$this->DebugString .= "<hr />using percentages for coupon discount: ".$this->actualDeductions;
				}
			}
			if($coupon->MaximumDiscount > 0) {
				if($this->actualDeductions > $coupon->MaximumDiscount) {
					$this->DebugString .= "<hr />actual deductions (".$this->actualDeductions.") are greater than maximum discount (".$coupon->MaximumDiscount."): ";
					$this->actualDeductions = $coupon->MaximumDiscount;
				}
			}
		}
		if($subTotal < $this->actualDeductions) {
			$this->actualDeductions = $subTotal;
		}
		$this->DebugString .= "<hr />final score: ".$this->actualDeductions;
		if(isset($_GET["debug"])) {
			print_r($this->DebugString);
		}
		$this->actualDeductions = -1 * $this->actualDeductions;
		return $this->actualDeductions;
	}

	/**
	*@return float
	**/
	public function LiveTableValue() {
		return $this->LiveCalculatedTotal();
	}


	/**
	*@return String
	**/
	protected function LiveDebugString() {
		return $this->DebugString;
	}

	/**
	*@return String
	**/
	protected function LiveCouponCodeEntered() {
		return $this->CouponCodeEntered;
	}

	/**
	*@return int
	**/
	protected function LiveDiscountCouponOptionID() {
		return $this->DiscountCouponOptionID;
	}


// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

	/**
	*@return Boolean
	**/
	public function IsDeductable() {
		return true;
	}

// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)


// ######################################## *** AJAX related functions
	/**
	* some modifiers can be hidden after an ajax update (e.g. if someone enters a discount coupon and it does not exist).
	* There might be instances where ShowInTable (the starting point) is TRUE and HideInAjaxUpdate return false.
	*@return Boolean
	**/
	public function HideInAjaxUpdate() {
		//we check if the parent wants to hide it...
		//we need to do this first in case it is being removed.
		if(parent::HideInAjaxUpdate()) {
			return true;
		}
		// we do NOT hide it if values have been entered
		if($this->CouponCodeEntered) {
			return false;
		}
		return true;
	}

// ######################################## *** debug functions


}

class DiscountCouponModifier_Form extends OrderModifierForm {

	function __construct($optionalController = null, $name,FieldSet $fields, FieldSet $actions,$optionalValidator = null) {
		parent::__construct($optionalController, $name, $fields, $actions, $optionalValidator);
		Requirements::themedCSS("DiscountCouponModifier");
		Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
		Requirements::javascript(THIRDPARTY_DIR."/jquery-form/jquery.form.js");
		Requirements::javascript("ecommerce_discount_coupon/javascript/DiscountCouponModifier.js");
	}

	public function submit($data, $form) {
		if(isset($data['DiscountCouponCode'])) {
			$order = ShoppingCart::current_order();
			if($order) {
				if($modifiers = $order->Modifiers("DiscountCouponModifier")) {
					foreach($modifiers as $modifier) {
						$outcome = $modifier->updateCouponCodeEntered(Convert::raw2sql($data["DiscountCouponCode"]));
					}
					if($outcome) {
						return ShoppingCart::singleton()->setMessageAndReturn(_t("DiscountCouponModifier.APPLIED", "Coupon applied"),"good");
					}
					else {
						return ShoppingCart::singleton()->setMessageAndReturn(_t("DiscountCouponModifier.NOTFOUND", "Coupon could not be found"),"bad");
					}
				}
			}
		}
		return ShoppingCart::singleton()->setMessageAndReturn(_t("DiscountCouponModifier.NOTAPPLIED", "Coupon could not be found.", "bad"));
	}
}




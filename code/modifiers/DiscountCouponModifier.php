<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @author Romain [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_delivery
 * @description: Shipping calculation scheme based on SimpleShippingModifier.
 * It lets you set fixed shipping costs, or a fixed
 * cost for each region you're delivering to.
 */

class DiscountCouponModifier extends OrderModifier {

// ######################################## *** model defining static variables (e.g. $db, $has_one)

	/**
	 * standard SS Variable
	 * @var Array
	 */
	public static $db = array(
		'DebugString' => 'HTMLText',
		'SubTotalAmount' => 'Currency',
		'CouponCodeEntered' => 'Varchar(25)'
	);


	/**
	 * standard SS Variable
	 * @var Array
	 */
	public static $has_one = array(
		"DiscountCouponOption" => "DiscountCouponOption"
	);

	/**
	 * Should the discount be worked out over the the sub-total or
	 * the Total Total?
	 * @var Boolean
	 */
	protected static $include_modifiers_in_subtotal = false;
		public static function set_include_modifiers_in_subtotal($b) {self::$include_modifiers_in_subtotal = $b;}
		public static function get_include_modifiers_in_subtotal() {return self::$include_modifiers_in_subtotal;}

	/**
	 * If this method is present in the Buyable, the related order item will be excluded
	 * @var Boolean
	 */
	protected static $exclude_buyable_method = 'ExcludeInDiscountCalculation';
		static function set_exclude_buyable_method($s) {self::$exclude_buyable_method = $s;}
		static function get_exclude_buyable_method() {return self::$exclude_buyable_method;}

	/**
	 * Standard SS Variable
	 * @var String
	 */
	public static $singular_name = "Discount Coupon Entry";
		function i18n_singular_name() { return _t("ModifierExample.MODIFIEREXAMPLE", "Discount Coupon Entry");}

	/**
	 * Standard SS Variable
	 * @var String
	 */
	public static $plural_name = "Discount Coupon Entries";
		function i18n_plural_name() { return _t("ModifierExample.MODIFIEREXAMPLES", "Discount Coupon Entries");}



// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)


	/**
	 * Standard SS Method
	 * @return FieldSet
	 */
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName("DebugString");
		$fields->removeByName("SubTotalAmount");
		$fields->removeByName("OrderCoupon");
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("SubTotalAmountShown", "sub-total amount", $this->SubTotalAmount));
		$fields->addFieldToTab("Root.Debug", new ReadonlyField("DebugStringShown", "debug string", $this->DebugString));
		return $fields;
	}

// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)

	/**
	 * Used in calculations to work out how much we need.
	 * @var Double | Null
	 */
	protected $actualDeductions = null;

	/**
	 * Used for debugging
	 * @var String
	 */
	protected $debugMessage = "";


// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions
	/**
	 * updates all database fields
	 *
	 * @param Bool $force - run it, even if it has run already
	 */
	public function runUpdate($force = false) {
		if (isset($_GET['debug_profile'])) Profiler::mark('DiscountCouponModifier::runUpdate');
		if(!$this->IsRemoved()) {
			$this->checkField("SubTotalAmount");
			$this->checkField("CouponCodeEntered");
			$this->checkField("DiscountCouponOptionID");
		}
		if (isset($_GET['debug_profile'])) Profiler::unmark('DiscountCouponModifier::runUpdate');
		parent::runUpdate($force);
	}



// ######################################## *** form functions (e. g. showform and getform)

	/**
	 * Show the form?
	 * We always show it when there are items in the cart.
	 * @return Boolean
	 */
	public function ShowForm() {
		return $this->Order()->Items();
	}


	/**
	 * @param Controller $optionalController
	 * @param Validator $optionalValidator
	 * @return DiscountCouponModifier_Form
	 */
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
		$discountCoupon = DataObject::get_one('DiscountCouponOption', "\"Code\" = '$code'");
		if($discountCoupon && $discountCoupon->IsValid()) {
			$this->DiscountCouponOptionID = $discountCoupon->ID;
			$this->CouponCodeEntered = $code;
			$result = array(_t('DiscountCouponModifier.APPLIED', 'Coupon applied'), 'good');
		}
		else if($code) {
			$result = array(_t('DiscountCouponModifier.NOTFOUND', 'Coupon could not be found'), 'bad');
		}
		else if($this->CouponCodeEntered && $this->DiscountCouponOptionID) {
			$this->DiscountCouponOptionID = 0;
			$this->CouponCodeEntered = $code;
			$result = array(_t('DiscountCouponModifier.REMOVED', 'Coupon removed'), 'good');
		}
		else {
			//to do: do we need to remove it again?
			$result = array(_t('DiscountCouponModifier.NOTFOUND', 'Coupon could not be found'), 'bad');
		}
		$this->write();
		return $result;
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
		if($this->DiscountCouponOptionID) {
			return true;
		}
		elseif($this->Order()->IsSubmitted()) {
			return false;
		}
		else {
			//we hide it with ajax if needed
			return true;
		}
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
		return _t("DiscountCouponModifier.NOCOUPONENTERED", "No (valid) coupon entered").$code;
	}

	private static $subtotal = 0;
	/**
	*@return float
	**/
	protected function LiveSubTotalAmount() {
		if(!self::$subtotal) {
			$order = $this->Order();
			$items = $order->Items();
			$subTotal = $order->SubTotal();
			$function = self::$exclude_buyable_method;
			if($items) {
				foreach($items as $item) {
					$buyable = $item->Buyable();
					if($buyable && $buyable->hasMethod($function) && $buyable->$function($this)) {
						$subTotal -= $item->Total();
					}
				}
			}
			if(self::$include_modifiers_in_subtotal) {
				$subTotal += $order->ModifiersSubTotal(array(get_class($this)));
			}
			self::$subtotal = $subTotal;
		}
		return self::$subtotal;
	}

	private static $calculated_total = 0;
	/**
	*@return float
	**/
	protected function LiveCalculatedTotal() {
		if(!self::$calculated_total) {
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
			self::$calculated_total = $this->actualDeductions;
		}
		return self::$calculated_total;
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
	* @return Boolean
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
				$modifiers = $order->Modifiers('DiscountCouponModifier');
				$modifier = $modifiers->First();
				if($modifier) {
					list($message, $type) = $modifier->updateCouponCodeEntered(Convert::raw2sql($data['DiscountCouponCode']));
					return ShoppingCart::singleton()->setMessageAndReturn($message, $type);
				}
			}
		}
		return ShoppingCart::singleton()->setMessageAndReturn(_t("DiscountCouponModifier.NOTAPPLIED", "Coupon could not be found.", "bad"));
	}
}




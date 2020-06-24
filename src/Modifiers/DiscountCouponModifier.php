<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Modifiers;









use ProductVaration;
use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDOD;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\Validator;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
use Sunnysideup\Ecommerce\Model\OrderModifier;



/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @author Romain [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_delivery
 * @description: Shipping calculation scheme based on SimpleShippingModifier.
 * It lets you set fixed shipping costs, or a fixed
 * cost for each region you're delivering to.
 */
class DiscountCouponModifier extends OrderModifier
{
    // ######################################## *** model defining static variables (e.g. $db, $has_one)

    /**
     * standard SS Variable
     * @var Array
     */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $db (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $db (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'DiscountCouponModifier';

    private static $db = array(
        'DebugString' => 'HTMLText',
        'SubTotalAmount' => 'Currency',
        'CouponCodeEntered' => 'Varchar(25)'
    );

    private static $defauls = [
        'Type' => 'Discount',
    ];

    /**
     * standard SS Variable
     * @var Array
     */
    private static $has_one = array(
        "DiscountCouponOption" => DiscountCouponOption::class
    );

    /**
     * Should the discount be worked out over the the sub-total or
     * the Total Total?
     * @var Boolean
     */
    private static $include_modifiers_in_subtotal = false;

    /**
     * If this method is present in the Buyable, the related order item will be excluded
     * @var Boolean
     */
    private static $exclude_buyable_method = 'ExcludeInDiscountCalculation';

    /**
     * Standard SS Variable
     * @var String
     */
    private static $singular_name = "Discount Coupon Entry";

    public function i18n_singular_name()
    {
        return _t("DiscountCouponModifier.SINGULAR_NAME", "Discount Coupon Entry");
    }

    /**
     * Standard SS Variable
     * @var String
     */
    private static $plural_name = "Discount Coupon Entries";

    public function i18n_plural_name()
    {
        return _t("DiscountCouponModifier.PLURAL_NAME", "Discount Coupon Entries");
    }

    // ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)

    /**
     * Standard SS Method
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("DebugString");
        $fields->removeByName("SubTotalAmount");
        $fields->removeByName("OrderCoupon");
        $fields->addFieldToTab(
                "Root.Debug",
            new ReadonlyField(
                "SubTotalAmountShown",
                    _t("DiscountCouponModifier.SUB_TOTAL_AMOUNT", "sub-total amount"),
                    $this->SubTotalAmount
                )
        );
        $fields->addFieldToTab(
                "Root.Debug",
            new ReadonlyField(
                "DebugStringShown",
                    _t("DiscountCouponModifier.DEBUG_STRING", "debug string"),
                    $this->DebugString
                )
        );
        return $fields;
    }

    // ######################################## *** other (non) static variables (e.g. private static $special_name_for_something, protected $order)

    /**
     * Used in calculations to work out how much we need.
     * @var Double | Null
     */
    protected $_actualDeductions = null;

    // ######################################## *** CRUD functions (e.g. canEdit)
    // ######################################## *** init and update functions
    /**
     * updates all database fields
     *
     * @param Bool $force - run it, even if it has run already
     */
    public function runUpdate($force = false)
    {
        if (!$this->IsRemoved()) {
            $this->checkField("SubTotalAmount");
            $this->checkField("CouponCodeEntered");
            $this->checkField("DiscountCouponOptionID");
        }
        parent::runUpdate($force);
    }

    // ######################################## *** form functions (e. g. showform and getform)

    /**
     * Show the form?
     * We always show it when there are items in the cart.
     * @return Boolean
     */
    public function ShowForm()
    {
        $items = $this->Order()->Items();
        if ($items) {
            //-- START HACK
            return true;
            //-- END HACK
            if (singleton(DiscountCouponOption::class)->hasExtension(DiscountCouponSiteTreeDOD::class)) {
                foreach ($items as $item) {
                    //here we need to add foreach valid coupon
                    //for each item->Buyable
                    //check if the coupon
                    //can be applied to the buyable
                }
            } else {
                return DiscountCouponOption::get()->exclude(array("NumberOfTimesCouponCanBeUsed" => 0))->count();
            }
        } else {
            return false;
        }
    }

    /**
     * @param Controller $optionalController
     * @param Validator $optionalValidator
     * @return DiscountCouponModifier_Form
     */
    public function getModifierForm(Controller $optionalController = null, Validator $optionalValidator = null)
    {
        $fields = new FieldList(
            $this->headingField(),
            $this->descriptionField(),
            new TextField(
                'DiscountCouponCode',
                _t("DiscountCouponModifier.COUPON", 'Coupon'),
                $this->LiveCouponCodeEntered()
            )
        );
        $actions = new FieldList(
            new FormAction(
                'submit',
                _t("DiscountCouponModifier.APPLY", 'Apply Coupon')
            )
        );
        $form = new DiscountCouponModifier_Form(
            $optionalController,
            DiscountCouponModifier::class,
            $fields,
            $actions,
            $optionalValidator
        );
        $fields->fieldByName("DiscountCouponCode")->setValue($this->CouponCodeEntered);
        return $form;
    }

    /**
     * @param String $code - code that has been entered
     *
     * @return array
     * */
    public function updateCouponCodeEntered($code)
    {
        //set to new value ....
        $this->CouponCodeEntered = $code;
        $coupon = DiscountCouponOption::get()
            ->filter(array("Code" => $code))->first();
        //apply valid discount coupong
        if ($coupon) {
            if ($coupon->IsValid() && $this->isValidAdditional($coupon)) {
                $this->setCoupon($coupon);
                $messages = array(_t('DiscountCouponModifier.APPLIED', 'Coupon applied'), 'good');
            } else {
                $messages = array(_t('DiscountCouponModifier.NOT_VALID', 'Coupon is no longer available'), 'bad');
                $this->DiscountCouponOptionID = 0;
            }
        } elseif ($code) {
            $messages = array(_t('DiscountCouponModifier.NOTFOUND', 'Coupon could not be found'), 'bad');
            if ($this->DiscountCouponOptionID) {
                $this->DiscountCouponOptionID = 0;
                $messages = array(_t('DiscountCouponModifier.REMOVED', 'Existing coupon removed'), 'good');
            }
        } else {
            //to do: do we need to remove it again?
            $messages = array(_t('DiscountCouponModifier.NOT_ENTERED', 'No coupon was entered'), 'bad');
        }
        $this->write();

        return $messages;
    }

    /**
     * @param DiscountCouponOption $coupon
     */
    public function setCoupon($coupon)
    {
        $this->DiscountCouponOptionID = $coupon->ID;
        $this->write();
    }

    /**
     * @param int $couponID
     */
    public function setCouponByID($couponID)
    {
        $this->DiscountCouponOptionID = $couponID;
        $this->write();
    }

    // ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES

    /**
     * @see self::HideInAjaxUpdate
     *
     * @return boolean
     */
    public function ShowInTable()
    {
        if ($this->DiscountCouponOptionID) {
            return true;
        } elseif ($this->Order()->IsSubmitted()) {
            return false;
        } else {
            //we hide it with ajax if needed
            return true;
        }
    }

    /**
     * @return boolean
     */
    public function CanRemove()
    {
        return false;
    }

    /**
     * @return float
     */
    public function CartValue()
    {
        return $this->getCartValue();
    }

    public function getCartValue()
    {
        return $this->TableValue;
    }

    // ######################################## ***  inner calculations.... USES CALCULATED VALUES

    /**
     * Checks for extensions to make sure it is valid...
     * @param DiscountCouponOption $coupon
     * @return bool returns true if the coupon is valid
     */
    protected function isValidAdditional($coupon)
    {
        $exclusions = $this->extend('checkForExclusions', $coupon);
        if (is_array($exclusions) && count($exclusions)) {
            foreach ($exclusions as $exclusion) {
                if ($exclusion === true) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * returns the discount coupon, if any ...
     *
     * @return DiscountCouponOption | null
     */
    protected function myDiscountCouponOption()
    {
        $coupon = null;
        if ($id = $this->LiveDiscountCouponOptionID()) {
            $coupon = DiscountCouponOption::get()->byID($id);
            if ($coupon) {
                if ($coupon->ApplyPercentageToApplicableProducts) {
                    $arrayOfOrderItemsToWhichThisCouponApplies = $this->applicableProductsArray($coupon);
                    if (count($arrayOfOrderItemsToWhichThisCouponApplies)) {
                        return $coupon;
                    }
                } else {
                    return $coupon;
                }
            }
        }
        return null;
    }

    private static $_applicable_products_array = null;

    /**
     * returns an Array of OrderItem IDs
     * to which the coupon applies
     * @param DiscountCouponOption
     * @return Array
     */
    protected function applicableProductsArray($coupon)
    {
        if (self::$_applicable_products_array === null) {
            self::$_applicable_products_array = [];
            $finalArray = [];
            $order = $this->Order();
            if ($order) {
                $items = $order->Items();
                if ($items && $items->count()) {
                    //get a list of all the products in the cart
                    $arrayOfProductsInOrder = [];
                    foreach ($items as $item) {
                        $buyable = $item->Buyable();
                        if ($buyable instanceof ProductVaration) {
                            $buyable = $buyable->Product();
                        }
                        $arrayOfProductsInOrder[$item->ID] = $buyable->ID;
                    }
                    //if no products / product groups are specified then
                    //it applies
                    //get a list of all the products to which the coupon applies
                    $productsArray = $coupon->Products()->map("ID", "ID")->toArray();
                    if (count($productsArray)) {
                        $matches = array_intersect($productsArray, $arrayOfProductsInOrder);
                        foreach ($matches as $buyableID) {
                            foreach ($arrayOfProductsInOrder as $itemID => $innerBuyableID) {
                                if ($buyableID == $innerBuyableID) {
                                    $finalArray[$itemID] = $itemID;
                                }
                            }
                        }
                    } else {
                        foreach ($arrayOfProductsInOrder as $itemID => $buyableID) {
                            $finalArray[$itemID] = $itemID;
                        }
                    }
                }
            }
            self::$_applicable_products_array = $finalArray;
        }
        return self::$_applicable_products_array;
    }

    // ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES

    /**
     * @return int
     * */
    protected function LiveName()
    {
        $code = $this->LiveCouponCodeEntered();
        $coupon = $this->myDiscountCouponOption();
        if ($coupon) {
            return _t("DiscountCouponModifier.COUPON", "Coupon") . " '" . $code . "' " . _t("DiscountCouponModifier.APPLIED", "applied.");
        } elseif ($code) {
            return _t("DiscountCouponModifier.COUPON", "Coupon") . " '" . $code . "' " . _t("DiscountCouponModifier.COULDNOTBEAPPLIED", "could not be applied.");
        }
        return _t("DiscountCouponModifier.NOCOUPONENTERED", "No (valid) coupon entered") . $code;
    }

    private static $subtotal = 0;

    /**
     * @return float
     * */
    protected function LiveSubTotalAmount()
    {
        if (!self::$subtotal) {
            $order = $this->Order();
            $items = $order->Items();
            $coupon = $this->myDiscountCouponOption();
            if ($coupon && $coupon->ApplyPercentageToApplicableProducts) {
                $array = $this->applicableProductsArray($coupon);
                $subTotal = 0;
                if (count($array)) {
                    if ($items) {
                        $itemCount = 0;
                        foreach ($items as $item) {
                            if (in_array($item->ID, $array)) {
                                $subTotal += $item->Total();
                            }
                        }
                    }
                }
            } else {
                $subTotal = $order->SubTotal();
                $function = $this->Config()->get("exclude_buyable_method");
                if ($items) {
                    foreach ($items as $item) {
                        $buyable = $item->Buyable();
                        if ($buyable && $buyable->hasMethod($function) && $buyable->$function($this)) {
                            $subTotal -= $item->Total();
                        }
                    }
                }
                if ($this->Config()->get("include_modifiers_in_subtotal")) {
                    $subTotal += $order->ModifiersSubTotal(array(get_class($this)));
                }
            }

            self::$subtotal = $subTotal;
        }
        return self::$subtotal;
    }

    protected $_calculatedTotal = null;

    /**
     * @return float
     * */
    protected function LiveCalculatedTotal()
    {
        if ($this->_calculatedTotal === null) {
            $this->_calculatedTotal = 0;
            $this->_actualDeductions = 0;
            $this->DebugString = "";
            $subTotal = $this->LiveSubTotalAmount();
            $coupon = $this->myDiscountCouponOption();
            if ($coupon && $this->isValidAdditional($coupon)) {
                if ($coupon->MinimumOrderSubTotalValue > 0 && $subTotal < $coupon->MinimumOrderSubTotalValue) {
                    $this->_actualDeductions = 0;
                    $this->DebugString .= "<hr />sub-total is too low to offer any discount: " . $this->_actualDeductions;
                } else {
                    if ($coupon->DiscountAbsolute > 0) {
                        $this->_actualDeductions += $coupon->DiscountAbsolute;
                        $this->DebugString .= "<hr />using absolutes for coupon discount: " . $this->_actualDeductions;
                    }
                    if ($coupon->DiscountPercentage > 0) {
                        $this->_actualDeductions += ($coupon->DiscountPercentage / 100) * $subTotal;
                        $this->DebugString .= "<hr />using percentages for coupon discount: " . $this->_actualDeductions;
                    }
                }
                if ($coupon->MaximumDiscount > 0) {
                    if ($this->_actualDeductions > $coupon->MaximumDiscount) {
                        $this->DebugString .= "<hr />actual deductions (" . $this->_actualDeductions . ") are greater than maximum discount (" . $coupon->MaximumDiscount . "): ";
                        $this->_actualDeductions = $coupon->MaximumDiscount;
                    }
                }
            }
            if ($subTotal < $this->_actualDeductions) {
                $this->_actualDeductions = $subTotal;
            }
            $this->DebugString .= "<hr />final score: " . $this->_actualDeductions;
            $this->_actualDeductions = -1 * $this->_actualDeductions;

            $this->_calculatedTotal = $this->_actualDeductions;
        }
        return $this->_calculatedTotal;
    }

    /**
     * @return float
     * */
    public function LiveTableValue()
    {
        return $this->LiveCalculatedTotal();
    }

    /**
     * @return String
     * */
    protected function LiveDebugString()
    {
        return $this->DebugString;
    }

    /**
     * @return String
     * */
    protected function LiveCouponCodeEntered()
    {
        return $this->CouponCodeEntered;
    }

    /**
     * @return int
     * */
    protected function LiveDiscountCouponOptionID()
    {
        return $this->DiscountCouponOptionID;
    }

    protected function LiveType()
    {
        return 'Discount';
    }

    // ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

    /**
     * @return Boolean
     * */
    public function IsDeductable()
    {
        return true;
    }

    // ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)
    // ######################################## *** AJAX related functions
    /**
     * some modifiers can be hidden after an ajax update (e.g. if someone enters a discount coupon and it does not exist).
     * There might be instances where ShowInTable (the starting point) is TRUE and HideInAjaxUpdate return false.
     * @return Boolean
     * */
    public function HideInAjaxUpdate()
    {
        //we check if the parent wants to hide it...
        //we need to do this first in case it is being removed.
        if (parent::HideInAjaxUpdate()) {
            return true;
        }
        // we do NOT hide it if values have been entered
        if ($this->CouponCodeEntered) {
            return false;
        }
        return true;
    }

    // ######################################## *** debug functions
}


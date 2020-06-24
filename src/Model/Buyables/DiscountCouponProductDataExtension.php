<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;







use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridField;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DataExtension;





/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class DiscountCouponProductDataExtension extends DataExtension
{


    /**
     * stadard SS declaration
     * @var Array
     */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $belongs_many_many = (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $belongs_many_many = (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'DiscountCouponProductDataExtension';

    private static $belongs_many_many = array(
        "ApplicableDiscountCoupons" => DiscountCouponOption::class
    );


    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Discount',
            GridField::create(
                'ApplicableDiscountCoupons',
                'Discount Coupons',
                $this->owner->ApplicableDiscountCoupons(),
                GridFieldConfig_RelationEditor::create()
            )
        );
        return $fields;
    }

    protected static $buyable_to_be_excluded_from_discounts = [];

    public static function add_buyable_to_be_excluded($buyableOrBuyableID)
    {
        if(is_object($buyable)) {
            $id = $buyable->ID;
        } elseif(intval($buyable)) {
            $id = intval($buyable);
        }

        self::$buyable_to_be_excluded_from_discounts[$id] = $id;
    }

    public function setCanBeNotDiscounted()
    {
        self::$buyable_to_be_excluded_from_discounts[$this->owner->ID] = $this->owner->ID;

        return $this;
    }

    public function getCanBeDiscounted()
    {
        return isset(self::$buyable_to_be_excluded_from_discounts[$this->owner->ID]) ? false : true;
    }

    /**
     * @param float $price
     *
     * @return float | null
     */
    public function updateCalculatedPrice($price = null)
    {
        if($this->getCanBeDiscounted()) {
            $hasDiscount = false;
            $coupons = $this->owner->DirectlyApplicableDiscountCoupons();
            if ($coupons && $coupons->count()) {
                $discountPercentage = 0;
                $discountAbsolute = 0;
                foreach ($coupons as $coupon) {
                    if ($coupon->isValid()) {
                        $hasDiscount = true;
                        if ($coupon->DiscountPercentage > $discountPercentage) {
                            $discountPercentage = $coupon->DiscountPercentage;
                        }
                        if ($coupon->DiscountAbsolute > $discountAbsolute) {
                            $discountAbsolute = $coupon->DiscountAbsolute;
                        }
                    }
                }
                if ($hasDiscount) {
                    $priceWithPercentageDiscount = $price - ($price * ($discountPercentage / 100));
                    $priceWithAbsoluteDiscount = $price - $discountAbsolute;
                    if ($priceWithPercentageDiscount < $priceWithAbsoluteDiscount) {
                        return $priceWithPercentageDiscount;
                    } else {
                        return $priceWithAbsoluteDiscount;
                    }
                }
            }
        }
    }

    public function DirectlyApplicableDiscountCoupons()
    {
        return $this->owner->ApplicableDiscountCoupons()
            ->filter(array("ApplyPercentageToApplicableProducts" => 1, "ApplyEvenWithoutCode" => 1));
    }

    private $discountCouponAmount = null;

    public function DiscountCouponAmount()
    {
        if ($this->discountCouponAmount === null) {
            $this->discountCouponAmount = 0;
            $amount = floatval($this->owner->Price) - floatval($this->owner->CalculatedPrice());
            if($amount > 1) {
                $this->discountCouponAmount = $amount;
            }
        }
        return EcommerceCurrency::get_money_object_from_order_currency($this->discountCouponAmount);
    }

    /**
     *
     * @return SS_Date
     */
    public function DiscountsAvailableUntil()
    {
        $coupons = $this->DirectlyApplicableDiscountCoupons();
        $next = strtotime('+100 years');
        if ($coupons && $coupons->count()) {
            $discount = 0;
            foreach ($coupons as $coupon) {
                if ($coupon->isValid()) {
                    if ($coupon->EndDate && $coupon->DiscountAbsolute > $discount) {
                        $discount = $coupon->DiscountAbsolute;
                        $maxDate = strtotime($coupon->EndDate);
                        if ($maxDate < $next) {
                            $next = $maxDate;
                        }
                    }
                }
            }
        }
        if ($next) {
            return DBField::create_field(DBDate::class, $next);
        }
    }
}


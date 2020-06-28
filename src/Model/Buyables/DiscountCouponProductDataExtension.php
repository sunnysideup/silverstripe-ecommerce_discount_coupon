<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;

class DiscountCouponProductDataExtension extends DataExtension
{
    protected static $buyable_to_be_excluded_from_discounts = [];

    /**
     * stadard SS declaration
     * @var array
     */
    private static $belongs_many_many = [
        'ApplicableDiscountCoupons' => DiscountCouponOption::class,
    ];

    private $discountCouponAmount = null;

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

    public static function add_buyable_to_be_excluded($buyableOrBuyableID)
    {
        $id = 0;
        
        if (is_object($buyableOrBuyableID)) {
            $id = $buyableOrBuyableID->ID;
        } elseif (intval($buyableOrBuyableID)) {
            $id = intval($buyableOrBuyableID);
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
        if ($this->getCanBeDiscounted()) {
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
                    }
                    return $priceWithAbsoluteDiscount;
                }
            }
        }
    }

    public function DirectlyApplicableDiscountCoupons()
    {
        return $this->owner->ApplicableDiscountCoupons()
            ->filter(['ApplyPercentageToApplicableProducts' => 1, 'ApplyEvenWithoutCode' => 1]);
    }

    public function DiscountCouponAmount()
    {
        if ($this->discountCouponAmount === null) {
            $this->discountCouponAmount = 0;
            $amount = floatval($this->owner->Price) - floatval($this->owner->CalculatedPrice());
            if ($amount > 1) {
                $this->discountCouponAmount = $amount;
            }
        }
        return EcommerceCurrency::get_money_object_from_order_currency($this->discountCouponAmount);
    }

    /**
     * @return \SilverStripe\ORM\FieldType\DBDate
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

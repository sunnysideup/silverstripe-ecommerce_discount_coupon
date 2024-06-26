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

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponProductDataExtension
 *
 * @property \Sunnysideup\Ecommerce\Pages\Product|\Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponProductDataExtension $owner
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption[] ApplicableDiscountCoupons()
 */
class DiscountCouponProductDataExtension extends DataExtension
{
    protected static $buyable_to_be_excluded_from_discounts = [];

    /**
     * stadard SS declaration.
     *
     * @var array
     */
    private static $belongs_many_many = [
        'ApplicableDiscountCoupons' => DiscountCouponOption::class,
    ];

    private $discountCouponAmount;

    /**
     * Update Fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Price',
            [
                GridField::create(
                    'ApplicableDiscountCoupons',
                    'Discount Coupons',
                    $this->getOwner()->ApplicableDiscountCoupons(),
                    GridFieldConfig_RelationEditor::create()
                )
            ]
        );
    }

    public static function add_buyable_to_be_excluded($buyableOrBuyableID)
    {
        $id = 0;

        if (is_object($buyableOrBuyableID)) {
            $id = $buyableOrBuyableID->ID;
        } elseif ((int) $buyableOrBuyableID) {
            $id = (int) $buyableOrBuyableID;
        }

        self::$buyable_to_be_excluded_from_discounts[$id] = $id;
    }

    public function setCanBeNotDiscounted()
    {
        self::$buyable_to_be_excluded_from_discounts[$this->getOwner()->ID] = $this->getOwner()->ID;

        return $this;
    }

    public function getCanBeDiscounted()
    {
        return ! isset(self::$buyable_to_be_excluded_from_discounts[$this->getOwner()->ID]);
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function updateCalculatedPrice(?float $price = null)
    {
        $owner = $this->getOwner();
        if ($this->getCanBeDiscounted()) {
            $hasDiscount = false;
            $coupons = $owner->ValidCoupons();
            if ($coupons && $coupons->exists()) {
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
                if ($hasDiscount && $price) {
                    $priceWithPercentageDiscount = $price - ($price * ($discountPercentage / 100));
                    $priceWithAbsoluteDiscount = $price - $discountAbsolute;
                    if ($priceWithPercentageDiscount < $priceWithAbsoluteDiscount) {
                        return $priceWithPercentageDiscount;
                    }

                    return $priceWithAbsoluteDiscount;
                }
            }
        }

        return null;
    }

    public function DirectlyApplicableDiscountCoupons()
    {
        return $this->getOwner()->ApplicableDiscountCoupons()
            ->filter(['ApplyPercentageToApplicableProducts' => 1, 'ApplyEvenWithoutCode' => 1])
        ;
    }

    public function DiscountCouponAmount()
    {
        if (null === $this->discountCouponAmount) {
            $this->discountCouponAmount = 0;
            $amount = floatval($this->getOwner()->Price) - floatval($this->getOwner()->CalculatedPrice());
            if ($amount > 1) {
                $this->discountCouponAmount = $amount;
            }
        }

        return EcommerceCurrency::get_money_object_from_order_currency($this->discountCouponAmount);
    }

    protected static $valid_coupons_cache = [];

    public function ValidCoupons()
    {
        $owner = $this->getOwner();
        if (isset(self::$valid_coupons_cache[$owner->ID])) {
            return self::$valid_coupons_cache[$owner->ID];
        }
        $validCoupons = $owner->DirectlyApplicableDiscountCoupons();
        if ($validCoupons->exists()) {
            foreach($validCoupons as $coupon) {
                if (! $coupon->isValid()) {
                    $validCoupons = $validCoupons->remove($coupon);
                }
            }
        }
        self::$valid_coupons_cache[$owner->ID] = $validCoupons;

        return $validCoupons;
    }

    protected static $discount_availble_until_cache = [];

    /**
     * @return null|\SilverStripe\ORM\FieldType\DBDate
     */
    public function DiscountsAvailableUntil(): ?DBDate
    {
        $owner = $this->getOwner();
        if(isset(self::$discount_availble_until_cache[$owner->ID])) {
            return self::$discount_availble_until_cache[$owner->ID];
        }
        $coupons = $this->ValidCoupons();
        $maxDateSet = false;
        $obj = null;
        $next = null;
        if ($coupons && $coupons->exists()) {
            $discount = 0;
            foreach ($coupons as $coupon) {
                if ($coupon->EndDate && $coupon->DiscountAbsolute > $discount) {
                    $discount = $coupon->DiscountAbsolute;
                    $maxDate = strtotime((string) $coupon->EndDate);
                    if ($next === null || $maxDate < $next) {
                        $next = $maxDate;
                        $maxDateSet = true;
                    }
                }
            }
        }
        if($next && $maxDateSet) {
            // do nothing
        } else {
            $next = strtotime('now +7 days');
        }
        if ($next) {
            /** @var DBDate $obj */
            $obj = DBDate::create_field(DBDate::class, $next);
        }
        self::$discount_availble_until_cache[$owner->ID] = $obj;

        return $obj;
    }
}

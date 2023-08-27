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
        if ($this->getCanBeDiscounted()) {
            $hasDiscount = false;
            $coupons = $this->getOwner()->DirectlyApplicableDiscountCoupons();
            if ($coupons->exists()) {
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

    /**
     * @return null|\SilverStripe\ORM\FieldType\DBDate
     */
    public function DiscountsAvailableUntil()
    {
        $coupons = $this->DirectlyApplicableDiscountCoupons();
        $next = strtotime('+100 years');
        $obj = null;
        if ($coupons->exists()) {
            $discount = 0;
            foreach ($coupons as $coupon) {
                if ($coupon->isValid()) {
                    if ($coupon->EndDate && $coupon->DiscountAbsolute > $discount) {
                        $discount = $coupon->DiscountAbsolute;
                        $maxDate = strtotime((string) $coupon->EndDate);
                        if ($maxDate < $next) {
                            $next = $maxDate;
                        }
                    }
                }
            }
        }
        if ($next) {
            /** @var DBDate $obj */
            $obj = DBField::create_field(DBDate::class, $next);
        }

        return $obj;
    }
}

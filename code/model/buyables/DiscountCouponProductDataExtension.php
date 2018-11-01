<?php


class DiscountCouponProductDataExtension extends DataExtension
{


    /**
     * stadard SS declaration
     * @var Array
     */
    private static $belongs_many_many = array(
        "ApplicableDiscountCoupons" => "DiscountCouponOption"
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

    /**
     * @param float $price
     *
     * @return float | null
     */
    public function updateCalculatedPrice($price = null)
    {
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
            return DBField::create_field('Date', $next);
        }
    }
}

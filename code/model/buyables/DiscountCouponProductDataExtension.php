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
     * @param float $price
     *
     * @return float | null
     */
    public function updateCalculatedPrice($price)
    {
        $hasDiscount = false;
        $coupons = $this->owner->DirectlyApplicableDiscountCoupons();
        if ($coupons && $coupons->count()) {
            $discountPercentage = 0;
            foreach ($coupons as $coupon) {
                if ($coupon->isValid()) {
                    $hasDiscount = true;
                    if ($coupon->DiscountPercentage > $discountPercentage) {
                        $discountPercentage = $coupon->DiscountPercentage;
                    }
                }
            }
            if ($hasDiscount) {
                return $price - ($price * ($discountPercentage / 100));
            }
        }
    }

    public function DirectlyApplicableDiscountCoupons()
    {
        return $this->owner->ApplicableDiscountCoupons()
            ->filter(array("ApplyPercentageToApplicableProducts" => 1, "ApplyEvenWithoutCode" => 1));
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
                if ($coupon->EndDate) {
                    $maxDate = strtotime($coupon->EndDate);
                    if ($maxDate < $next) {
                        $next = $maxDate;
                    }
                }
            }
        }
        if ($next) {
            return DBField::create_field('Date', $next);
        }
    }
}

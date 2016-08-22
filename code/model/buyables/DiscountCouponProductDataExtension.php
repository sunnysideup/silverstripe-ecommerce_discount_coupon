<?php


class DiscountCouponProductDataExtension extends DataExtension {


    /**
     * stadard SS declaration
     * @var Array
     */
    private static $belongs_many_many = array (
        "ApplicableDiscountCoupons" => "DiscountCouponOption"
    );


    /**
     * @param float $price
     *
     * @return float | null
     */
    function updateCalculatedPrice($price) {
        $coupons = $this->DirectlyApplicableDiscountCoupons();
        if($coupons && $coupons->count()) {
            $discount = 0;
            foreach($coupons as $coupon) {
                if($coupon->isValid()) {
                    if($coupon->DiscountPercentage > $discount) {
                        $discount = $coupon->DiscountPercentage;
                    }
                }
            }
            return $price - ($price * ($discount / 100));
        }
    }

    function DirectlyApplicableDiscountCoupons(){
        return $this->owner->ApplicableDiscountCoupons()
            ->filter(array("ApplyPercentageToApplicableProducts" => 1, "ApplyEvenWithoutCode" => 1));
    }

    /**
     *
     * @return SS_Date
     */
    function DiscountsAvailableUntil()
    {
        $coupons = $this->DirectlyApplicableDiscountCoupons();
        $next = strtotime('+100 years');
        if($coupons && $coupons->count()) {
            $discount = 0;
            foreach($coupons as $coupon) {
                if($coupon->EndDate) {
                    $maxDate = strtotime($coupon->EndDate);
                    if($maxDate < $next) {
                        $next = $maxDate;
                    }
                }
            }
        }
        if($next) {
            return DBField::create_field('Date', $next);
        }
    }

}

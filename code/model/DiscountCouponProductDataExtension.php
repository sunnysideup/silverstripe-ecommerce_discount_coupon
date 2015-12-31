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
        $coupons = $this->DirectlyApplicableDiscountCoupons();
        if ($coupons && $coupons->count()) {
            $discount = 0;
            foreach ($coupons as $coupon) {
                if ($coupon->isValid()) {
                    if ($coupon->DiscountPercentage > $discount) {
                        $discount = $coupon->DiscountPercentage;
                    }
                }
            }
            return $price - ($price * ($discount / 100));
        }
    }

    public function DirectlyApplicableDiscountCoupons()
    {
        return $this->owner->ApplicableDiscountCoupons()
            ->filter(array("ApplyPercentageToApplicableProducts" => 1, "ApplyEvenWithoutCode" => 1));
    }
}

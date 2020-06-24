<?php

class DiscountCouponSiteTreeDOD_Product extends DataExtension
{
    public function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier)
    {
        $coupon = $modifier->DiscountCouponOption();
        return ! $coupon->canBeDiscounted($this->owner);
    }
}


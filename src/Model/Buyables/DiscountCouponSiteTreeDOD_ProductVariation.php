<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use DataExtension;
use DiscountCouponModifier;




/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class DiscountCouponSiteTreeDOD_ProductVariation extends DataExtension
{

    /**
     *
     * @param DiscountCouponModifier $modifier
     *
     * @return boolean
     *
     */
    public function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier)
    {
        $coupon = $modifier->DiscountCouponOption();
        return ! $coupon->canBeDiscounted($this->owner->Product());
    }
}


<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\ORM\DataExtension;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;

class DiscountCouponSiteTreeDODProductVariation extends DataExtension
{
    /**
     * @return bool
     */
    public function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier)
    {
        $coupon = $modifier->DiscountCouponOption();

        return ! $coupon->canBeDiscounted($this->getOwner()->Product());
    }
}

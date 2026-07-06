<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\Core\Extension;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDODProduct
 *
 * @property DiscountCouponSiteTreeDODProduct $owner
 */
class DiscountCouponSiteTreeDODProduct extends Extension
{
    public function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier): bool
    {
        $coupon = $modifier->DiscountCouponOption();

        return ! $coupon->canBeDiscounted($this->getOwner());
    }
}

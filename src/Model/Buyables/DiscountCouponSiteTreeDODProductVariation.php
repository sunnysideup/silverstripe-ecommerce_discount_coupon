<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\Core\Extension;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDODProductVariation
 *
 * @property DiscountCouponSiteTreeDODProductVariation $owner
 */
class DiscountCouponSiteTreeDODProductVariation extends Extension
{
    /**
     * @return bool
     */
    public function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier): bool
    {
        $coupon = $modifier->DiscountCouponOption();

        return ! $coupon->canBeDiscounted($this->getOwner()->Product());
    }
}

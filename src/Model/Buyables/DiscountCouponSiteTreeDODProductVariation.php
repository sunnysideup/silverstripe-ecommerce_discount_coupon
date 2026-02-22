<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\ORM\DataExtension;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDODProductVariation
 *
 * @property \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDODProductVariation $owner
 */
class DiscountCouponSiteTreeDODProductVariation extends DataExtension
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

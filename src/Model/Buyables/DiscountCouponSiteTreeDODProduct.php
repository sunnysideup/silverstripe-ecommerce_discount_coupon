<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\ORM\DataExtension;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDODProduct
 *
 * @property \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDODProduct $owner
 */
class DiscountCouponSiteTreeDODProduct extends DataExtension
{
    public function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier): bool
    {
        $coupon = $modifier->DiscountCouponOption();

        return ! $coupon->canBeDiscounted($this->owner);
    }
}

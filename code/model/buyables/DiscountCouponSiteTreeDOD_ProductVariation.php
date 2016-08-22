<?php


class DiscountCouponSiteTreeDOD_ProductVariation extends DataExtension {

	/**
	 *
	 * @param DiscountCouponModifier $modifier
	 *
	 * @return boolean
	 *
	 */
	function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier) {
		$coupon = $modifier->DiscountCouponOption();
		return ! $coupon->canBeDiscounted($this->owner->Product());
	}

}

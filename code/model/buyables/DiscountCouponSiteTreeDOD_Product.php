<?php

class DiscountCouponSiteTreeDOD_Product extends DataExtension {

	function ExcludeInDiscountCalculation(DiscountCouponModifier $modifier) {
		$coupon = $modifier->DiscountCouponOption();
		return ! $coupon->canBeDiscounted($this->owner);
	}

}

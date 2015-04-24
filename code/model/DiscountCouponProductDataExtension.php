<?php


class DiscountCouponProductDataExtension extends DataExtension {


	/**
	 * stadard SS declaration
	 * @var Array
	 */
	private static $belongs_many_many = array (
		"ApplicableDiscountCoupons" => "DiscountCouponOption"
	);


	/**
	 * How do we display the price excluding GST?
	 * @return STRING / INT
	 */
	function updateCalculatedPrice($price) {
		$coupons = $this->DirectlyApplicableDiscountCoupons();
		foreach($coupons as $coupon) {
			if($coupon->isValid()) {
				return $price - ($price * ($coupon->DiscountPercentage / 100));
			}
		}
	}

	function DirectlyApplicableDiscountCoupons(){
		return $this->owner->ApplicableDiscountCoupons()
			->filter(array("ApplyPercentageToApplicableProducts" => 1, "ApplyEvenWithoutCode" => 1));
	}

}

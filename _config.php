<?php


/**
 * developed by www.sunnysideup.co.nz
 * author: Nicolaas - modules [at] sunnysideup.co.nz
**/
Director::addRules(50, array(
	DiscountCouponModifier_AjaxController::get_url_segment().'//$Action/$ID/$OtherID' => 'DiscountCouponModifier_AjaxController'
));


//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START ecommerce_discount_coupon MODULE ----------------===================
//NOTE: add http://svn.gpmd.net/svn/open/multiselectfield/tags/0.2/ for nicer interface
//DiscountCoupon::set_form_header("Delivery Option (REQUIRED)");
//ProductsAndGroupsModelAdmin::add_managed_model("DiscountCouponOptions");
//===================---------------- END ecommerce_discount_coupon MODULE ----------------===================


//TO DO:
// make countdown (number of times used, and number of times it is allowed to be used)

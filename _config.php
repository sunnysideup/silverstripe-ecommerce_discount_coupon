<?php


/**
 * developed by www.sunnysideup.co.nz
 * author: Nicolaas - modules [at] sunnysideup.co.nz
**/

//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START ecommerce_discount_coupon MODULE ----------------===================
/**
 * ADD TO ECOMMERCE.YAML:
Order:
    modifiers: [
        ...
        DiscountCouponModifier
    ]
StoreAdmin:
    managed_models: [
        ...
        DiscountCouponOption
    ]
*/
// OPTIONAL
//Object::add_extension('DiscountCouponOption', 'DiscountCouponSiteTreeDOD');
//Object::add_extension('Product', 'DiscountCouponSiteTreeDODProduct');
//Object::add_extension('ProductVariation', 'DiscountCouponSiteTreeDODProductVariation');
//DiscountCouponModifier_Form::set_custom_javascript_files(null | false | array("myjavascript.js"));
//===================---------------- END ecommerce_discount_coupon MODULE ----------------===================

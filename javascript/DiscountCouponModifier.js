(function($){
	$(document).ready(
		function() {
			DiscountCoupon.init();
		}
	);
})(jQuery);


var DiscountCoupon = {

	formID: "#DiscountCouponModifier_Form_DiscountCouponModifier",

	fieldID: "#DiscountCouponCode input",

	loadingClass: "loading",

	actionsClass: ".Actions",

	generalActionsClass: ".checkoutStepPrevNextHolder",


	availableCountries: new Array(),

	init: function() {
		var options = {
			beforeSubmit:  DiscountCoupon.showRequest,  // pre-submit callback
			success: DiscountCoupon.showResponse,  // post-submit callback
			dataType: "json"
		};
		jQuery(DiscountCoupon.formID).ajaxForm(options);
		jQuery(DiscountCoupon.formID + " " + DiscountCoupon.actionsClass).hide();
		jQuery(DiscountCoupon.fieldID).change(
			function() {
				jQuery(DiscountCoupon.formID).submit();
			}
		);
	},

	// pre-submit callback
	showRequest: function (formData, jqForm, options) {
		jQuery(DiscountCoupon.generalActionsClass).hide();
		jQuery(DiscountCoupon.formID).addClass(DiscountCoupon.loadingClass);
		return true;
	},

	// post-submit callback
	showResponse: function (responseText, statusText)  {
		//redo quantity boxes
		//jQuery(DiscountCoupon.updatedDivID).css("height", "auto");
		jQuery(DiscountCoupon.formID).removeClass(DiscountCoupon.loadingClass);
		jQuery(DiscountCoupon.generalActionsClass).show();
		EcomCart.setChanges(responseText);
	}



}


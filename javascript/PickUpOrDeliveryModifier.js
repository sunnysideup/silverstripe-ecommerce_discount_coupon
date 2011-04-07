(function($){
	$(document).ready(
		function() {
			DiscountCoupon.init();
		}
	);
})(jQuery);


var	DiscountCoupon = {

	formID: "DiscountCoupon_Form_ModifierForm",

	DropdownIDappendix: "_DiscountCouponCode",

	loadingClass: "loading",

	actionsClass: "Actions",

	countryDropdownSelector: "select.ajaxCountryField",

	notSelectedText: "-- not selected --",

	availableCountries: new Array(),

	init: function() {
		var options = {
			beforeSubmit:  DiscountCoupon.showRequest,  // pre-submit callback
			success: DiscountCoupon.showResponse,  // post-submit callback
			dataType: "json"
		};
		jQuery('#' + DiscountCoupon.formID).ajaxForm(options);
		jQuery("#" + DiscountCoupon.formID + " ." + DiscountCoupon.actionsClass).hide();
		DiscountCoupon.updateCountryList();
		jQuery("#" + DiscountCoupon.formID+ DiscountCoupon.DropdownIDappendix).change(
			function() {
				DiscountCoupon.updateCountryList();
				jQuery("#" + DiscountCoupon.formID).submit();
			}
		);
	},

	// pre-submit callback
	showRequest: function (formData, jqForm, options) {
		jQuery("#" + DiscountCoupon.formID).addClass(DiscountCoupon.loadingClass);
		return true;
	},

	// post-submit callback
	showResponse: function (responseText, statusText)  {
		//redo quantity boxes
		//jQuery("#" + DiscountCoupon.updatedDivID).css("height", "auto");
		jQuery("#" + DiscountCoupon.formID).removeClass(DiscountCoupon.loadingClass);
		Cart.setChanges(responseText);
	},

	addAvailableCountriesItem: function(index, countriesArray) {
		DiscountCoupon.availableCountries[index] = countriesArray;
	},

	updateCountryList: function() {
		var currentIndex = jQuery("#" + DiscountCoupon.formID+ DiscountCoupon.DropdownIDappendix).val();
		var currentCountryValue = jQuery(DiscountCoupon.countryDropdownSelector).val();
		var acceptableOptions = DiscountCoupon.availableCountries[currentIndex];
		if(acceptableOptions ==undefined) {
			acceptableOptions = new Array();
		}
		if(acceptableOptions.length < 1) {
			jQuery(DiscountCoupon.countryDropdownSelector + " option").show();
		}
		else {
			jQuery(DiscountCoupon.countryDropdownSelector + " option").hide();
		}
		var hasValidValue = false;

		for(i=0;i<acceptableOptions.length;i++) {
			jQuery(DiscountCoupon.countryDropdownSelector + " option[value='" + acceptableOptions[i] + "']").show();
			if(currentCountryValue == acceptableOptions[i]) {
				hasValidValue = true;
			}
		}
		if(acceptableOptions.length == 1) {
			jQuery(DiscountCoupon.countryDropdownSelector).val(acceptableOptions[0]);
			hasValidValue = true;
		}
		if(hasValidValue) {
			jQuery(DiscountCoupon.countryDropdownSelector + " option.nothingSelected").hide();
		}
		else {
			if(acceptableOptions.length > 1) {
				if(jQuery(DiscountCoupon.countryDropdownSelector + " option.nothingSelected").length < 1) {
					jQuery(DiscountCoupon.countryDropdownSelector).prepend('<option class="nothingSelected" value="-">'+DiscountCoupon.notSelectedText+'</option>');
				}
				else {
					jQuery(DiscountCoupon.countryDropdownSelector + " option.nothingSelected").show();
				}
				jQuery(DiscountCoupon.countryDropdownSelector).val("-");
			}
		}
		jQuery(DiscountCoupon.countryDropdownSelector).change();
	}


}


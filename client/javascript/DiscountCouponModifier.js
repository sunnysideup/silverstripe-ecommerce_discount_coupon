import EcomCart from './../../../ecommerce/client/javascript/EcomCart'

if (
  document.getElementById('DiscountCouponModifierForm_DiscountCouponModifier')
) {
  const DiscountCoupon = {
    formID: '#DiscountCouponModifierForm_DiscountCouponModifier',

    fieldID:
      "#DiscountCouponModifierForm_DiscountCouponModifier input[name='DiscountCouponCode']",

    loadingClass: 'loading',

    actionsClass: '.Actions',

    generalActionsClass: '.checkoutStepPrevNextHolder',

    delayForAutoSubmit: 1000,

    availableCountries: new Array(),

    init: function () {
      var options = {
        beforeSubmit: DiscountCoupon.showRequest, // pre-submit callback
        success: DiscountCoupon.showResponse, // post-submit callback
        dataType: 'json'
      }
      jQuery(DiscountCoupon.formID).ajaxForm(options)
      jQuery(DiscountCoupon.formID + ' ' + DiscountCoupon.actionsClass).hide()
      jQuery(this.fieldID).on('change', function () {
        jQuery(DiscountCoupon.formID).trigger('submit')
      })
    },

    // pre-submit callback
    showRequest: function (formData, jqForm, options) {
      jQuery(DiscountCoupon.generalActionsClass).hide()
      jQuery(DiscountCoupon.formID).addClass(DiscountCoupon.loadingClass)
      return true
    },

    // post-submit callback
    showResponse: function (responseText, statusText) {
      //redo quantity boxes
      //jQuery(DiscountCoupon.updatedDivID).css("height", "auto");
      jQuery(DiscountCoupon.formID).removeClass(DiscountCoupon.loadingClass)
      jQuery(DiscountCoupon.generalActionsClass).show()
      EcomCart.setChanges(responseText)
    }
  }

  jQuery(() => {
    DiscountCoupon.init()
  })
}

(function($){
    $(document).ready(
        function() {
            DiscountCoupon.init();
        }
    );
})(jQuery);


var DiscountCoupon = {

    formID: "#DiscountCouponModifierForm_DiscountCouponModifier",

    fieldID: "#DiscountCouponModifierForm_DiscountCouponModifier input[name='DiscountCouponCode']",

    loadingClass: "loading",

    actionsClass: ".Actions",

    generalActionsClass: ".checkoutStepPrevNextHolder",

    delayForAutoSubmit: 1000,

    availableCountries: new Array(),

    EcomCart: {},

    init: function() {
        if (typeof window.EcomCart === "undefined") {
            //webpack route
            // var EcomCart = require("./EcomCartWebPack");
            DiscountCoupon.EcomCart = EcomCart.EcomCart;
        } else {
            //alternative route
            DiscountCoupon.EcomCart = window.EcomCart;
        }
        var options = {
            beforeSubmit:  DiscountCoupon.showRequest,  // pre-submit callback
            success: DiscountCoupon.showResponse,  // post-submit callback
            dataType: "json"
        };
        jQuery(DiscountCoupon.formID).ajaxForm(options);
        jQuery(DiscountCoupon.formID + " " + DiscountCoupon.actionsClass).hide();
        jQuery(this.fieldID).handleKeyboardChange(this.delayForAutoSubmit).change(
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
        DiscountCoupon.EcomCart.setChanges(responseText);
    }



}




jQuery.fn.handleKeyboardChange = function(nDelay)
{
    // Utility function to test if a keyboard event should be ignored
    function shouldIgnore(event)
    {
        var mapIgnoredKeys = {
             9:true, // Tab
            16:true, 17:true, 18:true, // Shift, Alt, Ctrl
            37:true, 38:true, 39:true, 40:true, // Arrows
            91:true, 92:true, 93:true // Windows keys
        };
        return mapIgnoredKeys[event.which];
    }

    // Utility function to fire OUR change event if the value was actually changed
    function fireChange($element)
    {
        if( $element.val() != jQuery.data($element[0], "valueLast") )
        {
            jQuery.data($element[0], "valueLast", $element.val())
            $element.trigger("change");
        }
    }

    // The currently running timeout,
    // will be accessed with closures
    var timeout = 0;

    // Utility function to cancel a previously set timeout
    function clearPreviousTimeout()
    {
        if( timeout )
        {
            clearTimeout(timeout);
        }
    }

    return this
    .keydown(function(event)
    {
        if( shouldIgnore(event) ) return;
        // User pressed a key, stop the timeout for now
        clearPreviousTimeout();
        return null;
    })
    .keyup(function(event)
    {
        if( shouldIgnore(event) ) return;
        // Start a timeout to fire our event after some time of inactivity
        // Eventually cancel a previously running timeout
        clearPreviousTimeout();
        var $self = jQuery(this);
        timeout = setTimeout(function(){ fireChange($self) }, nDelay);
    })
    .change(function()
    {
        // Fire a change
        // Use our function instead of just firing the event
        // Because we want to check if value really changed since
        // our previous event.
        // This is for when the browser fires the change event
        // though we already fired the event because of the timeout
        fireChange(jQuery(this));
    })
    ;
}

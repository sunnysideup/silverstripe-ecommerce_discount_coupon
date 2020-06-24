<?php

class DiscountCouponModifier_Form extends OrderModifierForm
{

    /**
     * @var Array
     *
     */
    private static $custom_javascript_files = array(
        "ecommerce_discount_coupon/javascript/DiscountCouponModifier.js"
    );

    public static function get_custom_javascript_files()
    {
        $jsFiles = $this->Config()->get("custom_javascript_files");
        if (is_array($jsFiles) && count($jsFiles)) {
            return $jsFiles;
        }
        return null;
    }

    public function __construct($optionalController = null, $name, FieldList $fields, FieldList $actions, $optionalValidator = null)
    {
        parent::__construct($optionalController, $name, $fields, $actions, $optionalValidator);
        Requirements::themedCSS("DiscountCouponModifier", "ecommerce_discount_coupon");
        Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");
        Requirements::javascript(THIRDPARTY_DIR . "/jquery-form/jquery.form.js");
        //Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
        //Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
        if ($jsRequirements = $this->Config()->get("custom_javascript_files")) {
            foreach ($jsRequirements as $js) {
                Requirements::javascript($js);
            }
        }
    }

    public function submit(array $data, Form $form, $message = "Order updated", $status = "good")
    {
        if (isset($data['DiscountCouponCode'])) {
            $order = ShoppingCart::current_order();
            if ($order) {
                $modifiers = $order->Modifiers('DiscountCouponModifier');
                $modifier = $modifiers->First();
                if ($modifier) {
                    list($message, $type) = $modifier->updateCouponCodeEntered(Convert::raw2sql($data['DiscountCouponCode']));
                    $form->addErrorMessage("DiscountCouponCode", $message, $type);
                    return ShoppingCart::singleton()->setMessageAndReturn($message, $type);
                }
            }
        }
        return ShoppingCart::singleton()->setMessageAndReturn(_t("DiscountCouponModifier.NOTAPPLIED", "Coupon could not be found.", "bad"));
    }
}

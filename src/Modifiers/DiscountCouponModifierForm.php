<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Modifiers;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\View\Requirements;
use Sunnysideup\Ecommerce\Api\ShoppingCart;
use Sunnysideup\Ecommerce\Forms\OrderModifierForm;

class DiscountCouponModifierForm extends OrderModifierForm
{
    /**
     * @var array
     */
    private static $custom_javascript_files = [
        'sunnysideup/ecommerce_discount_coupon: client/javascript/DiscountCouponModifier.js',
    ];

    public function __construct($optionalController = null, $name, FieldList $fields, FieldList $actions, $optionalValidator = null)
    {
        parent::__construct($optionalController, $name, $fields, $actions, $optionalValidator);
        Requirements::javascript('silverstripe/admin: thirdparty/jquery/jquery.js');
        Requirements::javascript('silverstripe/admin: thirdparty/jquery-form/jquery.form.js');
        //Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
        //Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
        if ($jsRequirements = $this->Config()->get('custom_javascript_files')) {
            foreach ($jsRequirements as $js) {
                Requirements::javascript($js);
            }
        }
        /**
         * ### @@@@ START REPLACEMENT @@@@ ###
         * WHY doesnt this work?
         */
        //Requirements::themedCSS('client/css/DiscountCouponModifier');
    }

    public static function get_custom_javascript_files()
    {
        $jsFiles = $this->Config()->get('custom_javascript_files');
        if (is_array($jsFiles) && count($jsFiles)) {
            return $jsFiles;
        }
        return null;
    }

    public function submit(array $data, Form $form, $message = 'Order updated', $status = 'good')
    {
        if (isset($data['DiscountCouponCode'])) {
            $order = ShoppingCart::current_order();
            if ($order) {
                $modifiers = $order->Modifiers(DiscountCouponModifier::class);
                $modifier = $modifiers->First();
                if ($modifier) {
                    list($message, $type) = $modifier->updateCouponCodeEntered(Convert::raw2sql($data['DiscountCouponCode']));
                    $form->sessionMessage($message, $type);
                    return ShoppingCart::singleton()->setMessageAndReturn($message, $type);
                }
            }
        }
        return ShoppingCart::singleton()->setMessageAndReturn(_t('DiscountCouponModifier.NOTAPPLIED', 'Coupon could not be found.', 'bad'));
    }
}

<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Modifiers;

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

    public function __construct($optionalController, string $name, FieldList $fields, FieldList $actions, $optionalValidator = null)
    {
        parent::__construct($optionalController, $name, $fields, $actions, $optionalValidator);
        Requirements::javascript('https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js');
        // Requirements::javascript('silverstripe/admin: thirdparty/jquery-form/jquery.form.js');
        Requirements::block('silverstripe/admin: thirdparty/jquery-form/jquery.form.js');
        //Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
        $jsRequirements = $this->Config()->get('custom_javascript_files');
        if ($jsRequirements) {
            foreach ($jsRequirements as $js) {
                Requirements::javascript($js);
            }
        }
        Requirements::themedCSS('client/css/DiscountCouponModifier');
    }

    public function submit(array $data, Form $form, $message = 'Order updated', $status = 'good')
    {
        // the code must be a single value (not e.g. an array)
        if (isset($data['DiscountCouponCode']) && is_string($data['DiscountCouponCode'])) {
            $order = ShoppingCart::current_order();
            if ($order) {
                $modifiers = $order->Modifiers(DiscountCouponModifier::class);
                $modifier = $modifiers->First();
                if ($modifier) {
                    $result = $modifier->updateCouponCodeEntered(mb_substr(trim($data['DiscountCouponCode']), 0, 25));
                    $message = $result['message'] ?? '';
                    $type = $result['type'] ?? 'bad';
                    $form->sessionMessage($message, $type);

                    return ShoppingCart::singleton()->setMessageAndReturn($message, $type);
                }
            }
        }

        return ShoppingCart::singleton()->setMessageAndReturn(_t('DiscountCouponModifier.NOTAPPLIED', 'Coupon could not be found.', 'bad'));
    }
}

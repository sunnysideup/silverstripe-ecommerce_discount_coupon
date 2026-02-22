<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Modifiers;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validator;
use Sunnysideup\Ecommerce\Model\OrderModifier;
use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;

/**
 * @property string $DebugString
 * @property string $CouponCodeEntered
 * @property int $DiscountCouponOptionID
 * @method DiscountCouponOption DiscountCouponOption()
 */
class DiscountCouponModifier extends OrderModifier
{
    /**
     * Used in calculations to work out how much we need.
     */
    protected ?float $actualDeductions = null;

    protected ?float $calculatedTotal = null;

    /**
     * Cache of applicable order item IDs keyed by coupon ID.
     *
     * @var array<int, array<int, int>>
     */
    protected array $applicableProductsByCouponId = [];

    /**
     * Cache of subtotals keyed by coupon ID.
     *
     * @var array<int, float>
     */
    protected array $subTotalsByCouponId = [];

    private static $table_name = 'DiscountCouponModifier';

    private static $db = [
        'DebugString' => 'HTMLText',
        'CouponCodeEntered' => 'Varchar(25)',
    ];

    private static $defaults = [
        'Type' => 'Discount',
    ];

    private static $has_one = [
        'DiscountCouponOption' => DiscountCouponOption::class,
    ];

    private static $many_many = [
        'OtherApplicableDiscountCouponOptions' => DiscountCouponOption::class,
    ];

    /**
     * Should the discount be worked out over the sub-total or
     * the total total?
     */
    private static bool $include_modifiers_in_subtotal = false;

    /**
     * If this method is present in the Buyable, the related order item will be excluded.
     */
    private static string $exclude_buyable_method = 'ExcludeInDiscountCalculation';

    private static $singular_name = 'Discount Coupon Entry';

    private static $plural_name = 'Discount Coupon Entries';

    public function i18n_singular_name(): string
    {
        return _t('DiscountCouponModifier.SINGULAR_NAME', 'Discount Coupon Entry');
    }

    public function i18n_plural_name(): string
    {
        return _t('DiscountCouponModifier.PLURAL_NAME', 'Discount Coupon Entries');
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('OrderCoupon');
        $fields->removeByName('OtherApplicableDiscountCouponOptions');

        if ((bool) $this->config()->get('debug')) {
            $fields->addFieldToTab(
                'Root.Debug',
                new ReadonlyField(
                    'DebugStringShown',
                    _t('DiscountCouponModifier.DEBUG_STRING', 'debug string'),
                    $this->DebugString
                )
            );
        } else {
            $fields->removeByName('DebugString');
        }

        return $fields;
    }

    /**
     * Updates all database fields.
     */
    public function runUpdate($recalculate = false)
    {
        if (! $this->IsRemoved()) {
            $this->checkField('OtherApplicableDiscountCouponOptions', $recalculate);
            $this->checkField('DiscountCouponOptionID', $recalculate);
            $this->checkField('CouponCodeEntered', $recalculate);
        }

        parent::runUpdate($recalculate);
    }

    /**
     * Show the form?
     * We always show it when there are items in the cart.
     */
    public function ShowForm(): bool
    {
        $order = $this->getOrderCached();

        return $order !== null && (bool) $order->getTotalItems();
    }

    /**
     * @return DiscountCouponModifierForm
     */
    public function getModifierForm(?Controller $optionalController = null, ?Validator $optionalValidator = null)
    {
        $fields = new FieldList(
            $this->headingField(),
            $this->descriptionField(),
            new TextField(
                'DiscountCouponCode',
                _t('DiscountCouponModifier.COUPON', 'Coupon'),
                $this->LiveCouponCodeEntered()
            )
        );

        $actions = new FieldList(
            new FormAction(
                'submit',
                _t('DiscountCouponModifier.APPLY', 'Apply Coupon')
            )
        );

        $form = new DiscountCouponModifierForm(
            $optionalController,
            'DiscountCouponModifier',
            $fields,
            $actions,
            $optionalValidator
        );

        $couponField = $fields->fieldByName('DiscountCouponCode');
        if ($couponField) {
            $couponField->setValue($this->CouponCodeEntered);
        }

        return $form;
    }

    /**
     * @return array{message:string,type:string}
     */
    public function updateCouponCodeEntered(string $code): array
    {
        $code = trim($code);
        $this->CouponCodeEntered = $code;

        /** @var ?DiscountCouponOption $coupon */
        $coupon = DiscountCouponOption::get()
            ->filter(['Code' => $code])
            ->first();

        if ($coupon !== null) {
            if ($coupon->IsValid() && $this->isValidAdditional($coupon)) {
                $this->setCoupon($coupon);

                return [
                    'message' => _t('DiscountCouponModifier.APPLIED', 'Coupon applied'),
                    'type' => 'good',
                ];
            }

            $this->DiscountCouponOptionID = 0;
            $this->write();

            return [
                'message' => _t('DiscountCouponModifier.NOT_VALID', 'Coupon is no longer available'),
                'type' => 'bad',
            ];
        }

        if ($code === '') {
            $this->write();

            return [
                'message' => _t('DiscountCouponModifier.NOT_ENTERED', 'No coupon code was entered'),
                'type' => 'bad',
            ];
        }

        if ((int) $this->DiscountCouponOptionID > 0) {
            $this->DiscountCouponOptionID = 0;
            $this->write();

            return [
                'message' => _t('DiscountCouponModifier.REMOVED', 'Existing coupon removed'),
                'type' => 'good',
            ];
        }

        $this->write();

        return [
            'message' => _t('DiscountCouponModifier.NOTFOUND', 'Coupon could not be found'),
            'type' => 'bad',
        ];
    }

    public function setCoupon(DiscountCouponOption $coupon): static
    {
        return $this->setCouponByID((int) $coupon->ID);
    }

    public function setCouponByID(int $couponId): static
    {
        $this->DiscountCouponOptionID = $couponId;
        $this->write();

        return $this;
    }

    /**
     * @see self::HideInAjaxUpdate
     */
    public function ShowInTable(): bool
    {
        $order = $this->getOrderCached();
        if ($order) {
            return ! $order->IsSubmitted();
        }

        return false;
    }

    public function CanRemove(): bool
    {
        return false;
    }

    public function CartValue(): float
    {
        return $this->getCartValue();
    }

    public function getCartValue(): float
    {
        return (float) $this->TableValue;
    }

    public function IsDeductable(): bool
    {
        return true;
    }

    /**
     * Some modifiers can be hidden after an ajax update.
     */
    public function HideInAjaxUpdate(): bool
    {
        if (parent::HideInAjaxUpdate()) {
            return true;
        }

        $mustShow = (
            (string) $this->CouponCodeEntered !== ''
            || (int) $this->DiscountCouponOptionID > 0
            || $this->OtherApplicableDiscountCouponOptions()->exists()
        );

        return ! $mustShow;
    }

    protected function LiveTableValue(): float
    {
        return $this->LiveCalculatedTotal();
    }

    /**
     * Checks for extensions to make sure it is valid.
     */
    protected function isValidAdditional(DiscountCouponOption $coupon): bool
    {
        $exclusions = $this->extend('checkForExclusions', $coupon);

        if (is_array($exclusions) && count($exclusions)) {
            foreach ($exclusions as $exclusion) {
                if ($exclusion === true) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns the coupons that are applicable to the order item.
     *
     * @return array<int, DiscountCouponOption>
     */
    protected function myDiscountCouponOptions(): array
    {
        $array = [];

        foreach ($this->AllApplicableCoupons() as $id) {
            if (! $id) {
                continue;
            }

            /** @var ?DiscountCouponOption $coupon */
            $coupon = DiscountCouponOption::get()->byID((int) $id);
            if (! $coupon) {
                continue;
            }

            if (! $coupon->IsValid() || ! $this->isValidAdditional($coupon)) {
                continue;
            }

            if ($coupon->ApplyPercentageToApplicableProducts) {
                $arrayOfOrderItemsToWhichThisCouponApplies = $this->applicableProductsArray($coupon);
                if (count($arrayOfOrderItemsToWhichThisCouponApplies) > 0) {
                    $array[] = $coupon;
                }

                continue;
            }

            $array[] = $coupon;
        }

        return $array;
    }

    /**
     * @return array<int, int|string>
     */
    protected function AllApplicableCoupons(): array
    {
        return array_values(
            array_unique(
                array_merge(
                    [(int) $this->LiveDiscountCouponOptionID()],
                    $this->LiveOtherApplicableDiscountCouponOptions()
                )
            )
        );
    }

    /**
     * Returns an array of OrderItem IDs to which the coupon applies.
     *
     * @return array<int, int>
     */
    protected function applicableProductsArray(DiscountCouponOption $coupon): array
    {
        $couponId = (int) $coupon->ID;

        if (isset($this->applicableProductsByCouponId[$couponId])) {
            return $this->applicableProductsByCouponId[$couponId];
        }

        $finalArray = [];
        $order = $this->getOrderCached();

        if ($order) {
            $items = $order->Items();
            if ($items->exists()) {
                $arrayOfProductsInOrder = $order->ProductIds();

                $productsArray = $coupon->Products()->columnUnique();

                if (count($productsArray) > 0) {
                    $matches = array_intersect($productsArray, $arrayOfProductsInOrder);
                    foreach ($matches as $buyableId) {
                        foreach ($arrayOfProductsInOrder as $itemId => $innerBuyableId) {
                            if ((string) $buyableId === (string) $innerBuyableId) {
                                $finalArray[(int) $itemId] = (int) $itemId;
                            }
                        }
                    }
                } else {
                    foreach (array_keys($arrayOfProductsInOrder) as $itemId) {
                        $finalArray[(int) $itemId] = (int) $itemId;
                    }
                }
            }
        }

        $this->applicableProductsByCouponId[$couponId] = $finalArray;

        return $finalArray;
    }

    protected function LiveName(): string
    {
        $code = trim((string) $this->LiveCouponCodeEntered());
        $coupons = $this->myDiscountCouponOptions();
        $messages = [];

        if (count($coupons) > 0) {
            foreach ($coupons as $coupon) {
                if ($coupon) {
                    $messages[] =
                        _t('DiscountCouponModifier.COUPON', 'Coupon')
                        . ' \''
                        . $code
                        . '\' '
                        . _t('DiscountCouponModifier.APPLIED', 'applied.');
                }
            }

            return implode('<br />', $messages);
        }

        if ($code !== '') {
            return
                _t('DiscountCouponModifier.COUPON', 'Coupon')
                . ' \''
                . $code
                . '\' '
                . _t('DiscountCouponModifier.COULDNOTBEAPPLIED', 'could not be applied.');
        }

        return _t('DiscountCouponModifier.NOCOUPONENTERED', 'No (valid) coupon entered');
    }

    /**
     * This refers to the subtotal amount from the order to which the amount is applied.
     *
     * @return array<int, float>
     */
    protected function LiveSubTotalAmountsInner(): array
    {
        if ($this->subTotalsByCouponId !== []) {
            return $this->subTotalsByCouponId;
        }

        $order = $this->getOrderCached();
        if (! $order) {
            return [];
        }

        $items = $order->Items();
        $coupons = $this->myDiscountCouponOptions();

        foreach ($coupons as $coupon) {
            if (! $coupon) {
                continue;
            }

            $subTotal = 0.0;

            if ($coupon->ApplyPercentageToApplicableProducts) {
                $applicableItemIds = $this->applicableProductsArray($coupon);

                if (count($applicableItemIds) > 0 && $items) {
                    foreach ($items as $item) {
                        if (in_array((int) $item->ID, $applicableItemIds, true)) {
                            $subTotal += (float) $item->Total();
                        }
                    }
                }
            } else {
                $subTotal = (float) $order->SubTotal();
                $function = (string) $this->config()->get('exclude_buyable_method');

                if ($items) {
                    foreach ($items as $item) {
                        $buyable = $item->getBuyableCached();
                        if ($buyable && $buyable->hasMethod($function) && $buyable->{$function}($this)) {
                            $subTotal -= (float) $item->Total();
                        }
                    }
                }

                if ((bool) $this->config()->get('include_modifiers_in_subtotal')) {
                    $subTotal += (float) $order->ModifiersSubTotal([static::class]);
                }
            }

            $this->subTotalsByCouponId[(int) $coupon->ID] = max(0.0, $subTotal);
        }

        return $this->subTotalsByCouponId;
    }

    protected function LiveCalculatedTotal(): float
    {
        if ($this->calculatedTotal !== null) {
            return $this->calculatedTotal;
        }

        $this->calculatedTotal = 0.0;
        $this->actualDeductions = 0.0;

        $this->recordDebug('starting score: ' . $this->actualDeductions, true);

        $order = $this->getOrderCached();
        if (! $order) {
            return 0.0;
        }

        $subTotals = $this->LiveSubTotalAmountsInner();
        $orderSubTotal = (float) $order->SubTotal();

        foreach ($subTotals as $couponId => $subTotal) {
            $perCouponDeductions = 0.0;

            /** @var ?DiscountCouponOption $coupon */
            $coupon = DiscountCouponOption::get()->byID((int) $couponId);
            if (! $coupon) {
                continue;
            }

            if ($coupon->MinimumOrderSubTotalValue > 0 && $orderSubTotal < (float) $coupon->MinimumOrderSubTotalValue) {
                $this->recordDebug('Order sub-total is too low to offer any discount');
            } else {
                if ($coupon->DiscountAbsolute > 0) {
                    $perCouponDeductions += (float) $coupon->DiscountAbsolute;
                    $this->recordDebug('using absolutes for coupon discount: ' . $perCouponDeductions);
                }

                if ($coupon->DiscountPercentage > 0) {
                    $perCouponDeductions += (((float) $coupon->DiscountPercentage) / 100) * (float) $subTotal;
                    $this->recordDebug('using percentages for coupon discount: ' . $perCouponDeductions);
                }
            }

            if ($coupon->MaximumDiscount > 0 && $perCouponDeductions > (float) $coupon->MaximumDiscount) {
                $this->recordDebug(
                    'actual deductions (' . $perCouponDeductions . ') are greater than maximum discount (' . $coupon->MaximumDiscount . '): '
                );
                $perCouponDeductions = (float) $coupon->MaximumDiscount;
            }

            $this->actualDeductions += $perCouponDeductions;
        }

        // Cannot discount below zero (using order subtotal as safety cap).
        if ($orderSubTotal < $this->actualDeductions) {
            $this->actualDeductions = $orderSubTotal;
            $this->recordDebug('Below zero: ' . $this->actualDeductions);
        }

        $this->recordDebug('final score: ' . $this->actualDeductions);

        $this->actualDeductions = -1.0 * $this->actualDeductions;
        $this->calculatedTotal = $this->actualDeductions;

        return $this->calculatedTotal;
    }

    protected function LiveDebugString(): string
    {
        return (string) $this->DebugString;
    }

    protected function LiveCouponCodeEntered(): string
    {
        return (string) $this->CouponCodeEntered;
    }

    protected function LiveDiscountCouponOptionID(): int
    {
        return (int) $this->DiscountCouponOptionID;
    }

    /**
     * @return array<int, int>
     */
    protected function LiveOtherApplicableDiscountCouponOptions(): array
    {
        $order = $this->getOrderCached();
        $newData = [];

        if (! $order) {
            return $newData;
        }

        $items = $order->Items();
        if (! $items->exists()) {
            return $newData;
        }

        $productsInOrder = $order->ProductIds();

        foreach ($items as $item) {
            $buyable = $item->getBuyableCached();
            if (! $buyable || ! $buyable->hasMethod('ConditionallyApplicableDiscountCoupons')) {
                continue;
            }

            $coupons = $buyable->ConditionallyApplicableDiscountCoupons();
            if (! $coupons || ! $coupons->exists()) {
                continue;
            }

            foreach ($coupons as $coupon) {
                if ((int) $coupon->ID === (int) $this->DiscountCouponOptionID) {
                    continue;
                }

                $mustExists = $coupon->AnotherProductInOrderMustBeInProducts()->columnUnique();
                if (count(array_intersect($mustExists, $productsInOrder)) > 0) {
                    $newData[(int) $coupon->ID] = (int) $coupon->ID;
                }
            }
        }

        // Preserving original behaviour (side effect), but ideally move this write out of Live* methods.
        $this->OtherApplicableDiscountCouponOptions()->setByIDList($newData);

        return $newData;
    }

    protected function LiveType(): string
    {
        return 'Discount';
    }

    protected function recordDebug(string $message, bool $reset = false): void
    {
        if ((bool) $this->config()->get('debug')) {
            if ($reset) {
                $this->DebugString = '';
            }

            $this->DebugString .= '<hr />' . $message;

            return;
        }

        $this->DebugString = null;
    }
}

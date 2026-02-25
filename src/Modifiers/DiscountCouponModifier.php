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
    protected ?float $actualDeductions = null;

    protected ?float $calculatedTotal = null;

    /**
     * @var array<int, array<int, int>>
     */
    protected array $applicableProductsByCouponId = [];

    /**
     * @var array<int, float>
     */
    protected array $subTotalsByCouponId = [];

    /**
     * @var array<int, ?float>
     */
    protected array $maximumDeductionCapsByCouponId = [];

    private static $table_name = 'DiscountCouponModifier';

    private static array $db = [
        'DebugString' => 'HTMLText',
        'CouponCodeEntered' => 'Varchar(25)',
    ];

    private static array $defaults = [
        'Type' => 'Discount',
    ];

    private static array $has_one = [
        'DiscountCouponOption' => DiscountCouponOption::class,
    ];

    private static array $many_many = [
        'OtherApplicableDiscountCouponOptions' => DiscountCouponOption::class,
    ];

    private static bool $include_modifiers_in_subtotal = false;

    private static string $exclude_buyable_method = 'ExcludeInDiscountCalculation';

    private static string $singular_name = 'Discount Coupon Entry';

    private static string $plural_name = 'Discount Coupon Entries';

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

    public function runUpdate($recalculate = false)
    {
        if (! $this->IsRemoved()) {
            $this->checkField('OtherApplicableDiscountCouponOptions', $recalculate);
            $this->checkField('DiscountCouponOptionID', $recalculate);
            $this->checkField('CouponCodeEntered', $recalculate);
        }

        parent::runUpdate($recalculate);
    }

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
            new FormAction('submit', _t('DiscountCouponModifier.APPLY', 'Apply Coupon'))
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
        $coupon = DiscountCouponOption::get()->filter(['Code' => $code])->first();

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
                $targets = $this->applicableProductsArray($coupon);
                if (count($targets) > 0) {
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
     * Identifies exactly which products in the order are "targets" for the discount.
     *
     * Returns map: [OrderItemID => ProductID]
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
                /** @var array<int, int> $productIdsByItemId */
                $productIdsByItemId = $order->ProductIds(true);

                $explicitTargets = array_map(
                    static fn($id): int => (int) $id,
                    $coupon->Products()->columnUnique()
                );

                $conditionalTargetIds = [];
                foreach ($items as $item) {
                    $buyable = $item->getBuyableCached();
                    if ($buyable && $buyable->hasMethod('ConditionallyApplicableDiscountCoupons')) {
                        $coupons = $buyable->ConditionallyApplicableDiscountCoupons();
                        if ($coupons && $coupons->exists() && $coupons->find('ID', $couponId)) {
                            $conditionalTargetIds[] = (int) ($productIdsByItemId[(int) $item->ID] ?? 0);
                        }
                    }
                }

                $combinedTargetProducts = array_values(array_unique(array_merge($explicitTargets, $conditionalTargetIds)));
                $combinedTargetProducts = array_values(array_filter($combinedTargetProducts));

                if (count($combinedTargetProducts) > 0) {
                    foreach ($productIdsByItemId as $itemId => $productId) {
                        if (in_array((int) $productId, $combinedTargetProducts, true)) {
                            $finalArray[(int) $itemId] = (int) $productId;
                        }
                    }
                } else {
                    $finalArray = $productIdsByItemId;
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
                $messages[] =
                    _t('DiscountCouponModifier.COUPON', 'Coupon')
                    . ' '
                    . (string) $coupon->Title
                    . ' '
                    . _t('DiscountCouponModifier.APPLIED', 'applied.');
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
            $subTotal = 0.0;

            if ($coupon->ApplyPercentageToApplicableProducts) {
                $applicableItemIds = $this->applicableProductsArray($coupon);

                if ($applicableItemIds !== [] && $items) {
                    $subTotal = $this->subTotalForApplicableProductsWithRatio($coupon, $items, $applicableItemIds);
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
                    $applicableQty = $this->applicableQuantityForCoupon($coupon);

                    if ($applicableQty > 0) {
                        $addedAbsolute = ((float) $coupon->DiscountAbsolute) * $applicableQty;
                        $perCouponDeductions += $addedAbsolute;
                        $this->recordDebug('using absolutes for coupon discount: ' . $addedAbsolute . ' (Qty: ' . $applicableQty . ')');
                    } else {
                        $perCouponDeductions += (float) $coupon->DiscountAbsolute;
                    }
                }

                if ($coupon->DiscountPercentage > 0) {
                    $addedPercentage = (((float) $coupon->DiscountPercentage) / 100) * (float) $subTotal;
                    $perCouponDeductions += $addedPercentage;
                    $this->recordDebug('using percentages for coupon discount: ' . $addedPercentage);
                }
            }

            if ($coupon->MaximumDiscount > 0 && $perCouponDeductions > (float) $coupon->MaximumDiscount) {
                $this->recordDebug(
                    'actual deductions (' . $perCouponDeductions . ') are greater than maximum discount (' . $coupon->MaximumDiscount . ')'
                );
                $perCouponDeductions = (float) $coupon->MaximumDiscount;
            }

            $ratioCap = $this->maximumDeductionCapForCoupon($coupon);
            if ($ratioCap !== null && $perCouponDeductions > $ratioCap) {
                $this->recordDebug(
                    'actual deductions (' . $perCouponDeductions . ') are greater than ratio-based cap (' . $ratioCap . ')'
                );
                $perCouponDeductions = $ratioCap;
            }

            $this->actualDeductions += $perCouponDeductions;
        }

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

                $mustExists = $coupon->OtherProductInOrderProducts()->columnUnique();
                if (count(array_intersect($mustExists, $productsInOrder)) > 0) {
                    $newData[(int) $coupon->ID] = (int) $coupon->ID;
                }
            }
        }

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

    /**
     * Builds a list of applicable items and sorts by UNIT price DESC so that, when discount quantity is limited,
     * we apply discount to the most expensive items first (max customer benefit).
     *
     * @param iterable<mixed> $items
     * @param array<int, int> $applicableItemIds Map of OrderItemID => ProductID
     * @param array<int, int> $excludeProductIds ProductIDs that must NOT be discounted (e.g. prerequisites)
     *
     * @return array<int, array{item:mixed,itemId:int,qty:int,lineTotal:float,unitPrice:float}>
     */
    protected function getApplicableItemsSortedByUnitPriceDesc(iterable $items, array $applicableItemIds, array $excludeProductIds): array
    {
        $rows = [];

        foreach ($items as $item) {
            $itemId = (int) $item->ID;

            if (! isset($applicableItemIds[$itemId])) {
                continue;
            }

            $productId = (int) ($applicableItemIds[$itemId] ?? 0);
            if ($productId > 0 && in_array($productId, $excludeProductIds, true)) {
                continue;
            }

            $qty = max(0, (int) $item->Quantity);
            if ($qty <= 0) {
                continue;
            }

            $lineTotal = (float) $item->Total();
            $unitPrice = $lineTotal / $qty;

            $rows[] = [
                'item' => $item,
                'itemId' => $itemId,
                'qty' => $qty,
                'lineTotal' => $lineTotal,
                'unitPrice' => $unitPrice,
            ];
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                return $b['unitPrice'] <=> $a['unitPrice'];
            }
        );

        return $rows;
    }

    /**
     * Calculates the total quantity of applicable products, respecting combination ratios.
     * If discount quantity is limited, it allocates discounted units to the most expensive eligible units first.
     */
    protected function applicableQuantityForCoupon(DiscountCouponOption $coupon): int
    {
        $order = $this->getOrderCached();
        if (! $order) {
            return 0;
        }

        $items = $order->Items();
        if (! $items || ! $items->exists()) {
            return 0;
        }

        $applicableItemIds = $this->applicableProductsArray($coupon);
        if ($applicableItemIds === []) {
            return 0;
        }

        $ratio = (int) $coupon->ProductCombinationRatio;
        $mustHaveProductIds = array_map(
            static fn($id): int => (int) $id,
            $coupon->OtherProductInOrderProducts()->columnUnique()
        );

        $excludePrerequisites = count($coupon->Products()->columnUnique()) === 0;
        $excludeProductIds = $excludePrerequisites ? $mustHaveProductIds : [];

        // Unlimited or no prereq => all eligible quantities
        if ($ratio <= 0 || $mustHaveProductIds === []) {
            $qty = 0;

            foreach ($items as $item) {
                $itemId = (int) $item->ID;
                if (! isset($applicableItemIds[$itemId])) {
                    continue;
                }

                $productId = (int) ($applicableItemIds[$itemId] ?? 0);
                if ($productId > 0 && in_array($productId, $excludeProductIds, true)) {
                    continue;
                }

                $qty += max(0, (int) $item->Quantity);
            }

            return $qty;
        }

        /** @var array<int, int> $productIdsByItemId */
        $productIdsByItemId = $order->ProductIds(true);

        $requiredProductQty = $this->quantityForProductIdsInItems($items, $mustHaveProductIds, $productIdsByItemId);
        $remainingDiscountedQty = $requiredProductQty * $ratio;

        if ($remainingDiscountedQty <= 0) {
            return 0;
        }

        $eligibleRows = $this->getApplicableItemsSortedByUnitPriceDesc($items, $applicableItemIds, $excludeProductIds);

        $discountedQty = 0;

        foreach ($eligibleRows as $row) {
            if ($remainingDiscountedQty <= 0) {
                break;
            }

            $qtyToDiscount = min((int) $row['qty'], $remainingDiscountedQty);
            $discountedQty += $qtyToDiscount;
            $remainingDiscountedQty -= $qtyToDiscount;
        }

        return $discountedQty;
    }

    /**
     * Subtotal for percentage discount: if discount quantity is limited, we discount the most expensive eligible units first.
     *
     * @param iterable<mixed> $items
     * @param array<int, int> $applicableItemIds Map of OrderItemID => ProductID
     */
    protected function subTotalForApplicableProductsWithRatio(DiscountCouponOption $coupon, iterable $items, array $applicableItemIds): float
    {
        if ($applicableItemIds === []) {
            return 0.0;
        }

        $ratio = (int) $coupon->ProductCombinationRatio;
        $mustHaveProductIds = array_map(
            static fn($id): int => (int) $id,
            $coupon->OtherProductInOrderProducts()->columnUnique()
        );

        $excludePrerequisites = count($coupon->Products()->columnUnique()) === 0;
        $excludeProductIds = $excludePrerequisites ? $mustHaveProductIds : [];

        // Unlimited or no prereq => sum all eligible totals
        if ($ratio <= 0 || $mustHaveProductIds === []) {
            $subTotal = 0.0;

            foreach ($items as $item) {
                $itemId = (int) $item->ID;
                if (! isset($applicableItemIds[$itemId])) {
                    continue;
                }

                $productId = (int) ($applicableItemIds[$itemId] ?? 0);
                if ($productId > 0 && in_array($productId, $excludeProductIds, true)) {
                    continue;
                }

                $subTotal += (float) $item->Total();
            }

            return max(0.0, $subTotal);
        }

        $order = $this->getOrderCached();
        if (! $order) {
            return 0.0;
        }

        /** @var array<int, int> $productIdsByItemId */
        $productIdsByItemId = $order->ProductIds(true);

        $requiredProductQty = $this->quantityForProductIdsInItems($items, $mustHaveProductIds, $productIdsByItemId);
        $remainingDiscountedQty = $requiredProductQty * $ratio;

        if ($remainingDiscountedQty <= 0) {
            return 0.0;
        }

        $eligibleRows = $this->getApplicableItemsSortedByUnitPriceDesc($items, $applicableItemIds, $excludeProductIds);

        $subTotal = 0.0;

        foreach ($eligibleRows as $row) {
            if ($remainingDiscountedQty <= 0) {
                break;
            }

            $qty = (int) $row['qty'];
            $lineTotal = (float) $row['lineTotal'];

            $qtyToDiscount = min($qty, $remainingDiscountedQty);

            $subTotal += $lineTotal * ($qtyToDiscount / $qty);
            $remainingDiscountedQty -= $qtyToDiscount;
        }

        return max(0.0, $subTotal);
    }

    /**
     * @param iterable<mixed> $items
     * @param array<int, int|string> $productIds
     * @param array<int, int> $productIdsByItemId
     */
    protected function quantityForProductIdsInItems(iterable $items, array $productIds, array $productIdsByItemId): int
    {
        $totalQty = 0;
        $productIdsInt = array_map(static fn($id): int => (int) $id, $productIds);

        foreach ($items as $item) {
            $itemId = (int) $item->ID;
            $productId = (int) ($productIdsByItemId[$itemId] ?? 0);

            if ($productId > 0 && in_array($productId, $productIdsInt, true)) {
                $totalQty += max(0, (int) $item->Quantity);
            }
        }

        return $totalQty;
    }

    protected function maximumDeductionCapForCoupon(DiscountCouponOption $coupon): ?float
    {
        $couponId = (int) $coupon->ID;

        if (array_key_exists($couponId, $this->maximumDeductionCapsByCouponId)) {
            return $this->maximumDeductionCapsByCouponId[$couponId];
        }

        $ratio = (int) $coupon->ProductCombinationRatio;
        if ($ratio <= 0) {
            $this->maximumDeductionCapsByCouponId[$couponId] = null;

            return null;
        }

        $mustHaveProductIds = $coupon->OtherProductInOrderProducts()->columnUnique();
        if (count($mustHaveProductIds) === 0) {
            $this->maximumDeductionCapsByCouponId[$couponId] = null;

            return null;
        }

        $order = $this->getOrderCached();
        if (! $order) {
            return 0.0;
        }

        $items = $order->Items();
        if (! $items || ! $items->exists()) {
            return 0.0;
        }

        $applicableItemIds = $this->applicableProductsArray($coupon);
        if ($applicableItemIds === []) {
            return 0.0;
        }

        $cap = $this->subTotalForApplicableProductsWithRatio($coupon, $items, $applicableItemIds);
        $this->maximumDeductionCapsByCouponId[$couponId] = max(0.0, $cap);

        return $this->maximumDeductionCapsByCouponId[$couponId];
    }
}

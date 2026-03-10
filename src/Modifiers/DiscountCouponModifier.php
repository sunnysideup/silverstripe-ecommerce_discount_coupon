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
use Sunnysideup\Ecommerce\Pages\Product;
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
    protected array $applicableProductsByCouponId = [];
    protected array $subTotalsByCouponId = [];
    protected array $maximumDeductionCapsByCouponId = [];
    protected array $discountedProductIds = [];

    /**
     * Cached pre-computed per-item data. Built once by buildItemRows().
     *
     * Each entry: {
     *   item, itemId, qty, lineTotal, unitPrice,
     *   productId, isExcluded, applicableCouponIds
     * }
     *
     * @var array<int, array{
     *   item: mixed,
     *   itemId: int,
     *   qty: int,
     *   lineTotal: float,
     *   unitPrice: float,
     *   productId: int,
     *   isExcluded: bool,
     *   applicableCouponIds: int[]
     * }>|null
     */
    protected ?array $itemRows = null;

    private static $table_name = 'DiscountCouponModifier';

    private static array $db = [
        'DebugString'       => 'HTMLText',
        'CouponCodeEntered' => 'Varchar(25)',
    ];

    private static array $defaults = ['Type' => 'Discount'];

    private static array $has_one = [
        'DiscountCouponOption' => DiscountCouponOption::class,
    ];

    private static array $many_many = [
        'OtherApplicableDiscountCouponOptions' => DiscountCouponOption::class,
        'DiscountedProducts'                   => Product::class,
    ];

    private static bool $include_modifiers_in_subtotal = false;
    private static string $exclude_buyable_method = 'ExcludeInDiscountCalculation';
    private static string $singular_name = 'Discount Coupon Entry';
    private static string $plural_name = 'Discount Coupon Entries';

    // -------------------------------------------------------------------------
    // i18n
    // -------------------------------------------------------------------------

    public function i18n_singular_name(): string
    {
        return _t('DiscountCouponModifier.SINGULAR_NAME', 'Discount Coupon Entry');
    }

    public function i18n_plural_name(): string
    {
        return _t('DiscountCouponModifier.PLURAL_NAME', 'Discount Coupon Entries');
    }

    // -------------------------------------------------------------------------
    // CMS
    // -------------------------------------------------------------------------

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['OrderCoupon', 'OtherApplicableDiscountCouponOptions']);

        if ((bool) $this->config()->get('debug')) {
            $fields->addFieldToTab('Root.Debug', new ReadonlyField(
                'DebugStringShown',
                _t('DiscountCouponModifier.DEBUG_STRING', 'debug string'),
                $this->DebugString
            ));
        } else {
            $fields->removeByName('DebugString');
        }

        return $fields;
    }

    // -------------------------------------------------------------------------
    // Core modifier API
    // -------------------------------------------------------------------------

    public function runUpdate($recalculate = false)
    {
        if (!$this->IsRemoved()) {
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

    public function getModifierForm(?Controller $optionalController = null, ?Validator $optionalValidator = null): DiscountCouponModifierForm
    {
        $fields = new FieldList(
            $this->headingField(),
            $this->descriptionField(),
            new TextField('DiscountCouponCode', _t('DiscountCouponModifier.COUPON', 'Coupon'), $this->LiveCouponCodeEntered())
        );

        $actions = new FieldList(
            new FormAction('submit', _t('DiscountCouponModifier.APPLY', 'Apply Coupon'))
        );

        $form = new DiscountCouponModifierForm($optionalController, 'DiscountCouponModifier', $fields, $actions, $optionalValidator);

        $couponField = $fields->fieldByName('DiscountCouponCode');
        if ($couponField) {
            $couponField->setValue($this->CouponCodeEntered);
        }

        return $form;
    }

    /** @return array{message:string,type:string} */
    public function updateCouponCodeEntered(string $code): array
    {
        $code = trim($code);
        $this->CouponCodeEntered = $code;

        $coupon = DiscountCouponOption::get()->filter(['Code' => $code])->first();

        if ($coupon !== null) {
            if ($coupon->IsValid() && $this->isValidAdditional($coupon)) {
                $this->setCoupon($coupon);
                return ['message' => _t('DiscountCouponModifier.APPLIED', 'Coupon applied'), 'type' => 'good'];
            }

            $this->DiscountCouponOptionID = 0;
            $this->write();
            return ['message' => _t('DiscountCouponModifier.NOT_VALID', 'Coupon is no longer available'), 'type' => 'bad'];
        }

        if ($code === '') {
            $this->write();
            return ['message' => _t('DiscountCouponModifier.NOT_ENTERED', 'No coupon code was entered'), 'type' => 'bad'];
        }

        if ((int) $this->DiscountCouponOptionID > 0) {
            $this->DiscountCouponOptionID = 0;
            $this->write();
            return ['message' => _t('DiscountCouponModifier.REMOVED', 'Existing coupon removed'), 'type' => 'good'];
        }

        $this->write();
        return ['message' => _t('DiscountCouponModifier.NOTFOUND', 'Coupon could not be found'), 'type' => 'bad'];
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
        return $order ? !$order->IsSubmitted() : false;
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

        $mustShow = (string) $this->CouponCodeEntered !== ''
            || (int) $this->DiscountCouponOptionID > 0
            || $this->OtherApplicableDiscountCouponOptions()->exists();

        return !$mustShow;
    }

    // -------------------------------------------------------------------------
    // Item rows — the single place we iterate order items
    // -------------------------------------------------------------------------

    /**
     * Build (once) a flat array of per-item data consumed by every downstream
     * method.  This replaces the eight separate foreach ($items as $item) loops
     * that previously scattered identical retrieval logic across the class.
     *
     * @return array<int, array{
     *   item: mixed,
     *   itemId: int,
     *   qty: int,
     *   lineTotal: float,
     *   unitPrice: float,
     *   productId: int,
     *   isExcluded: bool,
     *   applicableCouponIds: int[]
     * }>
     */
    protected function buildItemRows(): array
    {
        if ($this->itemRows !== null) {
            return $this->itemRows;
        }

        $order = $this->getOrderCached();

        if (!$order) {
            return $this->itemRows = [];
        }

        $items = $order->Items();

        if (!$items || !$items->exists()) {
            return $this->itemRows = [];
        }

        $productIdsByItemId = $order->ProductIds(true);
        $excludeMethod      = (string) $this->config()->get('exclude_buyable_method');

        $this->itemRows = [];

        foreach ($items as $item) {
            $itemId    = (int) $item->ID;
            $qty       = max(0, (int) $item->Quantity);
            $lineTotal = (float) $item->Total();
            $productId = (int) ($productIdsByItemId[$itemId] ?? 0);
            $buyable   = $item->getBuyableCached();

            $isExcluded = $buyable
                && $buyable->hasMethod($excludeMethod)
                && $buyable->{$excludeMethod}($this);

            // Collect coupon IDs declared directly on the buyable
            $applicableCouponIds = [];
            if ($buyable && $buyable->hasMethod('CurrentApplicableDiscountCoupons')) {
                $coupons = $buyable->CurrentApplicableDiscountCoupons();
                if ($coupons && $coupons->exists()) {
                    foreach ($coupons as $c) {
                        $applicableCouponIds[] = (int) $c->ID;
                    }
                }
            }

            $this->itemRows[$itemId] = [
                'item'               => $item,
                'itemId'             => $itemId,
                'qty'                => $qty,
                'lineTotal'          => $lineTotal,
                'unitPrice'          => $qty > 0 ? $lineTotal / $qty : 0.0,
                'productId'          => $productId,
                'isExcluded'         => $isExcluded,
                'applicableCouponIds' => $applicableCouponIds,
            ];
        }

        return $this->itemRows;
    }

    /**
     * Subset of buildItemRows() that belong to a given coupon's applicable
     * product set, sorted descending by unit price (used for ratio logic).
     *
     * @param array<int, int> $applicableItemIds  itemId → productId
     * @param array<int, int> $excludeProductIds
     * @return array<int, array{item:mixed,itemId:int,qty:int,lineTotal:float,unitPrice:float,...}>
     */
    protected function applicableItemRowsSorted(array $applicableItemIds, array $excludeProductIds): array
    {
        $rows = [];

        foreach ($this->buildItemRows() as $itemId => $row) {
            if (!isset($applicableItemIds[$itemId])) {
                continue;
            }

            $productId = $row['productId'];
            if ($productId > 0 && in_array($productId, $excludeProductIds, true)) {
                continue;
            }

            if ($row['qty'] <= 0) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn($a, $b) => $b['unitPrice'] <=> $a['unitPrice']);

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Coupon resolution
    // -------------------------------------------------------------------------

    protected function isValidAdditional(DiscountCouponOption $coupon): bool
    {
        $exclusions = $this->extend('checkForExclusions', $coupon);

        if (is_array($exclusions)) {
            foreach ($exclusions as $exclusion) {
                if ($exclusion === true) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<int, DiscountCouponOption> */
    protected function myDiscountCouponOptions(): array
    {
        $array = [];

        foreach ($this->AllApplicableCoupons() as $id) {
            if (!$id) {
                continue;
            }

            $coupon = DiscountCouponOption::get()->byID((int) $id);
            if (!$coupon || !$coupon->IsValid() || !$this->isValidAdditional($coupon)) {
                continue;
            }

            if ($coupon->ApplyPercentageToApplicableProducts) {
                if (count($this->applicableProductsArray($coupon)) > 0) {
                    $array[] = $coupon;
                }
                continue;
            }

            $array[] = $coupon;
        }

        return $array;
    }

    /** @return array<int, int|string> */
    protected function AllApplicableCoupons(): array
    {
        return array_values(array_unique(array_merge(
            [(int) $this->LiveDiscountCouponOptionID()],
            $this->LiveOtherApplicableDiscountCouponOptions()
        )));
    }

    /** @return array<int, int> */
    protected function applicableProductsArray(DiscountCouponOption $coupon): array
    {
        $couponId = (int) $coupon->ID;

        if (isset($this->applicableProductsByCouponId[$couponId])) {
            return $this->applicableProductsByCouponId[$couponId];
        }

        $order = $this->getOrderCached();

        if (!$order) {
            return $this->applicableProductsByCouponId[$couponId] = [];
        }

        $rows = $this->buildItemRows();

        if ($rows === []) {
            return $this->applicableProductsByCouponId[$couponId] = [];
        }

        $explicitTargets = array_map(
            static fn($id): int => (int) $id,
            $coupon->Products()->columnUnique()
        );

        // Products declared applicable via the buyable itself
        $conditionalTargetIds = [];
        foreach ($rows as $row) {
            if (in_array($couponId, $row['applicableCouponIds'], true)) {
                $conditionalTargetIds[] = $row['productId'];
            }
        }

        $combinedTargets = array_values(array_filter(array_unique(array_merge($explicitTargets, $conditionalTargetIds))));

        $finalArray = [];
        foreach ($rows as $itemId => $row) {
            if ($combinedTargets === [] || in_array($row['productId'], $combinedTargets, true)) {
                $finalArray[$itemId] = $row['productId'];
            }
        }

        return $this->applicableProductsByCouponId[$couponId] = $finalArray;
    }

    // -------------------------------------------------------------------------
    // Live field calculations
    // -------------------------------------------------------------------------

    protected function LiveTableValue(): float
    {
        return $this->LiveCalculatedTotal();
    }

    protected function LiveName(): string
    {
        $code    = trim((string) $this->LiveCouponCodeEntered());
        $coupons = $this->myDiscountCouponOptions();

        if (count($coupons) > 0) {
            $messages = array_map(
                fn($c) => _t('DiscountCouponModifier.COUPON', 'Coupon') . ' ' . $c->Title . ' ' . _t('DiscountCouponModifier.APPLIED', 'applied.'),
                $coupons
            );
            return implode('<br />', $messages);
        }

        if ($code !== '') {
            return _t('DiscountCouponModifier.COUPON', 'Coupon') . " '$code' " . _t('DiscountCouponModifier.COULDNOTBEAPPLIED', 'could not be applied.');
        }

        return _t('DiscountCouponModifier.NOCOUPONENTERED', 'No (valid) coupon entered');
    }

    /** @return array<int, float> */
    protected function LiveSubTotalAmountsInner(): array
    {
        if ($this->subTotalsByCouponId !== []) {
            return $this->subTotalsByCouponId;
        }

        $order = $this->getOrderCached();
        if (!$order) {
            return [];
        }

        $rows = $this->buildItemRows();

        foreach ($this->myDiscountCouponOptions() as $coupon) {
            if ($coupon->ApplyPercentageToApplicableProducts) {
                $applicableItemIds = $this->applicableProductsArray($coupon);
                $subTotal          = $applicableItemIds !== []
                    ? $this->subTotalForApplicableProductsWithRatio($coupon, $applicableItemIds)
                    : 0.0;
            } else {
                $subTotal = (float) $order->SubTotal();

                foreach ($rows as $row) {
                    if ($row['isExcluded']) {
                        $subTotal -= $row['lineTotal'];
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

        $this->calculatedTotal  = 0.0;
        $this->actualDeductions = 0.0;
        $this->discountedProductIds = [];
        $this->recordDebug('starting score: ' . $this->actualDeductions, true);

        $order = $this->getOrderCached();
        if (!$order) {
            return 0.0;
        }

        $orderSubTotal = (float) $order->SubTotal();

        foreach ($this->LiveSubTotalAmountsInner() as $couponId => $subTotal) {
            $coupon = DiscountCouponOption::get()->byID((int) $couponId);
            if (!$coupon) {
                continue;
            }

            $perCouponDeductions = 0.0;

            if (
                !$coupon->ApplyPercentageToApplicableProducts
                && $coupon->MinimumOrderSubTotalValue > 0
                && $orderSubTotal < (float) $coupon->MinimumOrderSubTotalValue
            ) {
                $this->recordDebug('Order sub-total is too low to offer any discount');
            } else {
                if ($coupon->DiscountAbsolute > 0) {
                    $applicableQty = $this->applicableQuantityForCoupon($coupon);
                    if ($applicableQty > 0) {
                        $addedAbsolute = (float) $coupon->DiscountAbsolute * $applicableQty;
                        $perCouponDeductions += $addedAbsolute;
                        $this->recordDebug('using absolutes for coupon discount: ' . $addedAbsolute . ' (Qty: ' . $applicableQty . ')');
                    } else {
                        $perCouponDeductions += (float) $coupon->DiscountAbsolute;
                    }
                }

                if ($coupon->DiscountPercentage > 0) {
                    $addedPercentage = ((float) $coupon->DiscountPercentage / 100) * $subTotal;
                    $perCouponDeductions += $addedPercentage;
                    $this->recordDebug('using percentages for coupon discount: ' . $addedPercentage);
                }
            }

            if ($coupon->MaximumDiscount > 0 && $perCouponDeductions > (float) $coupon->MaximumDiscount) {
                $this->recordDebug('actual deductions (' . $perCouponDeductions . ') are greater than maximum discount (' . $coupon->MaximumDiscount . ')');
                $perCouponDeductions = (float) $coupon->MaximumDiscount;
            }

            $ratioCap = $this->maximumDeductionCapForCoupon($coupon);
            if ($ratioCap !== null && $perCouponDeductions > $ratioCap) {
                $this->recordDebug('actual deductions (' . $perCouponDeductions . ') are greater than ratio-based cap (' . $ratioCap . ')');
                $perCouponDeductions = $ratioCap;
            }

            // Only record products as discounted after all caps have been applied,
            // so DiscountedProducts reflects what was actually discounted.
            if ($perCouponDeductions > 0.0) {
                $this->collectDiscountedProductIds($coupon);
            }

            $this->actualDeductions += $perCouponDeductions;
        }

        if ($orderSubTotal < $this->actualDeductions) {
            $this->actualDeductions = $orderSubTotal;
            $this->recordDebug('Below zero: ' . $this->actualDeductions);
        }

        $this->recordDebug('final score: ' . $this->actualDeductions);
        $this->actualDeductions  *= -1.0;
        $this->calculatedTotal    = $this->actualDeductions;
        $this->persistDiscountedProducts();

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

    protected function LiveType(): string
    {
        return 'Discount';
    }

    /** @return array<int, int> */
    protected function LiveOtherApplicableDiscountCouponOptions(): array
    {
        $order = $this->getOrderCached();
        $newData = [];

        if (!$order) {
            return $newData;
        }

        $rows = $this->buildItemRows();

        if ($rows === []) {
            return $newData;
        }

        $productsInOrder = $order->ProductIds();

        foreach ($rows as $row) {
            foreach ($row['applicableCouponIds'] as $couponId) {
                if ($couponId === (int) $this->DiscountCouponOptionID) {
                    continue;
                }

                $coupon = DiscountCouponOption::get()->byID($couponId);
                if (!$coupon) {
                    continue;
                }

                $mustExists = $coupon->OtherProductInOrderProducts()->columnUnique();
                if (count(array_intersect($mustExists, $productsInOrder)) > 0) {
                    $newData[$couponId] = $couponId;
                }
            }
        }

        $this->OtherApplicableDiscountCouponOptions()->setByIDList($newData);
        return $newData;
    }

    // -------------------------------------------------------------------------
    // Discount product tracking
    // -------------------------------------------------------------------------

    protected function collectDiscountedProductIds(DiscountCouponOption $coupon): void
    {
        $rows = $this->buildItemRows();

        if ($coupon->ApplyPercentageToApplicableProducts) {
            foreach ($this->applicableProductsArray($coupon) as $productId) {
                if (($productId = (int) $productId) > 0) {
                    $this->discountedProductIds[$productId] = $productId;
                }
            }
            return;
        }

        foreach ($rows as $row) {
            if ($row['isExcluded']) {
                continue;
            }

            if ($row['productId'] > 0) {
                $this->discountedProductIds[$row['productId']] = $row['productId'];
            }
        }
    }

    protected function persistDiscountedProducts(): void
    {
        if ($this->isInDB()) {
            $this->DiscountedProducts()->setByIDList($this->discountedProductIds);
        }
    }

    // -------------------------------------------------------------------------
    // Ratio / quantity helpers — now consume applicableItemRowsSorted()
    // -------------------------------------------------------------------------

    protected function applicableQuantityForCoupon(DiscountCouponOption $coupon): int
    {
        $order = $this->getOrderCached();
        if (!$order) {
            return 0;
        }

        $applicableItemIds = $this->applicableProductsArray($coupon);
        if ($applicableItemIds === []) {
            return 0;
        }

        $ratio               = (int) $coupon->ProductCombinationRatio;
        $mustHaveProductIds  = array_map(static fn($id): int => (int) $id, $coupon->OtherProductInOrderProducts()->columnUnique());
        $excludeProductIds   = count($coupon->Products()->columnUnique()) === 0 ? $mustHaveProductIds : [];

        if ($ratio <= 0 || $mustHaveProductIds === []) {
            $qty = 0;
            foreach ($this->buildItemRows() as $itemId => $row) {
                if (!isset($applicableItemIds[$itemId])) {
                    continue;
                }
                if ($row['productId'] > 0 && in_array($row['productId'], $excludeProductIds, true)) {
                    continue;
                }
                $qty += $row['qty'];
            }
            return $qty;
        }

        $remainingDiscountedQty = $this->quantityForProductIds($mustHaveProductIds) * $ratio;
        if ($remainingDiscountedQty <= 0) {
            return 0;
        }

        $discountedQty = 0;
        foreach ($this->applicableItemRowsSorted($applicableItemIds, $excludeProductIds) as $row) {
            if ($remainingDiscountedQty <= 0) {
                break;
            }
            $qtyToDiscount = min($row['qty'], $remainingDiscountedQty);
            $discountedQty         += $qtyToDiscount;
            $remainingDiscountedQty -= $qtyToDiscount;
        }

        return $discountedQty;
    }

    /**
     * @param array<int, int> $applicableItemIds  itemId → productId
     */
    protected function subTotalForApplicableProductsWithRatio(DiscountCouponOption $coupon, array $applicableItemIds): float
    {
        if ($applicableItemIds === []) {
            return 0.0;
        }

        $ratio              = (int) $coupon->ProductCombinationRatio;
        $mustHaveProductIds = array_map(static fn($id): int => (int) $id, $coupon->OtherProductInOrderProducts()->columnUnique());
        $excludeProductIds  = count($coupon->Products()->columnUnique()) === 0 ? $mustHaveProductIds : [];

        if ($ratio <= 0 || $mustHaveProductIds === []) {
            $subTotal = 0.0;
            foreach ($this->buildItemRows() as $itemId => $row) {
                if (!isset($applicableItemIds[$itemId])) {
                    continue;
                }
                if ($row['productId'] > 0 && in_array($row['productId'], $excludeProductIds, true)) {
                    continue;
                }
                $subTotal += (float) $row['item']->TotalForDiscount();
            }
            return max(0.0, $subTotal);
        }

        $remainingDiscountedQty = $this->quantityForProductIds($mustHaveProductIds) * $ratio;
        if ($remainingDiscountedQty <= 0) {
            return 0.0;
        }

        $subTotal = 0.0;
        foreach ($this->applicableItemRowsSorted($applicableItemIds, $excludeProductIds) as $row) {
            if ($remainingDiscountedQty <= 0) {
                break;
            }
            $qtyToDiscount = min($row['qty'], $remainingDiscountedQty);
            $subTotal              += $row['lineTotal'] * ($qtyToDiscount / $row['qty']);
            $remainingDiscountedQty -= $qtyToDiscount;
        }

        return max(0.0, $subTotal);
    }

    /**
     * Total quantity across all items whose product ID appears in $productIds.
     * Replaces quantityForProductIdsInItems() — the items and productIdsByItemId
     * arguments are no longer needed since buildItemRows() holds both.
     *
     * @param array<int, int|string> $productIds
     */
    protected function quantityForProductIds(array $productIds): int
    {
        $totalQty      = 0;
        $productIdsInt = array_map(static fn($id): int => (int) $id, $productIds);

        foreach ($this->buildItemRows() as $row) {
            if ($row['productId'] > 0 && in_array($row['productId'], $productIdsInt, true)) {
                $totalQty += $row['qty'];
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

        $ratio              = (int) $coupon->ProductCombinationRatio;
        $mustHaveProductIds = $coupon->OtherProductInOrderProducts()->columnUnique();

        if ($ratio <= 0 || count($mustHaveProductIds) === 0) {
            return $this->maximumDeductionCapsByCouponId[$couponId] = null;
        }

        $applicableItemIds = $this->applicableProductsArray($coupon);
        if ($applicableItemIds === []) {
            return $this->maximumDeductionCapsByCouponId[$couponId] = 0.0;
        }

        return $this->maximumDeductionCapsByCouponId[$couponId] = max(
            0.0,
            $this->subTotalForApplicableProductsWithRatio($coupon, $applicableItemIds)
        );
    }

    // -------------------------------------------------------------------------
    // Debug
    // -------------------------------------------------------------------------

    protected function recordDebug(string $message, bool $reset = false): void
    {
        if (!(bool) $this->config()->get('debug')) {
            $this->DebugString = null;
            return;
        }

        if ($reset) {
            $this->DebugString = '';
        }

        $this->DebugString .= '<hr />' . $message;
    }
}

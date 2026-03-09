<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBMoney;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;

/**
 * @property \Sunnysideup\Ecommerce\Pages\Product|DiscountCouponProductDataExtension $owner
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption[] ApplicableDiscountCoupons()
 */
class DiscountCouponProductDataExtension extends DataExtension
{
    private static float $min_discount_amount = 0.1;
    private static string $default_end_date_description = 'now +7 days';

    protected static array $buyableToBeExcludedFromDiscounts = [];
    protected static array $discountCouponAmount = [];
    protected static array $couponPriceArrayCache = [];
    protected static array $couponCombos = [];
    protected static array $couponCombosMustHave = [];
    protected static array $discountAvailableUntilCache = [];

    private static $belongs_many_many = [
        'ApplicableDiscountCoupons' => DiscountCouponOption::class . '.Products',
        'MustHavesCoupons' => DiscountCouponOption::class . '.OtherProductInOrderProducts',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldsToTab('Root.Price', [
            GridField::create('ApplicableDiscountCoupons', 'Discount Coupons', $this->getOwner()->ApplicableDiscountCoupons(), GridFieldConfig_RelationEditor::create()),
            GridField::create('MustHavesCoupons', 'Must Have Coupons', $this->getOwner()->MustHavesCoupons(), GridFieldConfig_RelationEditor::create()),
        ]);
    }

    public static function add_buyable_to_be_excluded(object|int|string $buyableOrBuyableId): void
    {
        $id = is_object($buyableOrBuyableId) ? (int) ($buyableOrBuyableId->ID ?? 0) : (int) $buyableOrBuyableId;
        if ($id > 0) {
            self::$buyableToBeExcludedFromDiscounts[$id] = $id;
        }
    }

    public function setCanBeNotDiscounted(): static
    {
        $id = (int) $this->getOwner()->ID;
        if ($id > 0) {
            self::$buyableToBeExcludedFromDiscounts[$id] = $id;
        }
        return $this;
    }

    public function getCanBeDiscounted(): bool
    {
        return !array_key_exists((int) $this->getOwner()->ID, self::$buyableToBeExcludedFromDiscounts);
    }

    public function updateCalculatedPrice(?float $price = null): ?float
    {
        if (!$this->getCanBeDiscounted()) {
            return null;
        }
        $prices = $this->applicableCouponsAndPrice($price ?? (float) $this->getOwner()->Price);
        return !empty($prices) ? (float) $prices[0]['Price'] : null;
    }



    public function DiscountCouponAmount(): DBMoney
    {
        $owner = $this->getOwner();
        $id = (int) $owner->ID;

        if (!array_key_exists($id, self::$discountCouponAmount)) {
            $amount = (float) $owner->Price - (float) $owner->CalculatedPrice();
            $clean = $amount > (float) $owner->config()->get('min_discount_amount') ? $amount : 0.0;
            self::$discountCouponAmount[$id] = EcommerceCurrency::get_money_object_from_order_currency($clean);
        }

        return self::$discountCouponAmount[$id];
    }

    public function DiscountsAvailableUntil(): ?DBDate
    {
        $owner = $this->getOwner();
        $id = (int) $owner->ID;
        if ($id <= 0) return null;

        if (!array_key_exists($id, self::$discountAvailableUntilCache)) {
            self::$discountAvailableUntilCache[$id] = null;
            $coupons = $this->applicableCouponsAndPrice((float) $owner->Price);

            if ($coupons) {
                $coupon = $coupons[0]['Coupon'] ?? null;
                $timestamp = ($coupon && !empty($coupon->EndDate))
                    ? strtotime((string) $coupon->EndDate)
                    : strtotime((string) $owner->config()->get('default_end_date_description'));

                if ($timestamp !== false) {
                    self::$discountAvailableUntilCache[$id] = DBDate::create_field(DBDate::class, date('Y-m-d', $timestamp));
                }
            }
        }

        return self::$discountAvailableUntilCache[$id];
    }

    /**
     * all (potentiallly) applicable coupons - including those that require a combination of products
     *
     * @return DataList
     */
    public function CurrentApplicableDiscountCoupons(): DataList
    {
        return $this->filteredCoupons('ApplicableDiscountCoupons');
    }

    /**
     * All coupons that require a combo with another product
     *
     * @return DataList|null
     */
    public function ComboCoupons(): ?DataList
    {
        return $this->cachedCouponList('couponCombos', 'comboApplicableDiscountCoupons');
    }


    public function ComboCouponsInverse(): ?DataList
    {
        return $this->cachedCouponList('couponCombosMustHave', 'comboApplicableDiscountCouponsInverse');
    }


    public function DirectlyApplicableDiscountCoupons(): DataList
    {
        return $this->CurrentApplicableDiscountCoupons()->filter(['RequiresProductCombinationInOrder' => 0]);
    }

    protected function comboApplicableDiscountCoupons(): DataList
    {
        return $this->CurrentApplicableDiscountCoupons()->filter(['RequiresProductCombinationInOrder' => 1]);
    }


    protected function comboApplicableDiscountCouponsInverse(): DataList
    {
        return $this->filteredCoupons('MustHavesCoupons');
    }

    protected function filteredCoupons(string $relation): DataList
    {
        $date = date('Y-m-d');
        return $this->getOwner()->{$relation}()->filter([
            'StartDate:LessThanOrEqual' => $date,
            'EndDate:GreaterThanOrEqual' => $date,
            'ApplyEvenWithoutCode' => 1,
        ]);
    }
    private function cachedCouponList(string $cacheKey, string $method): ?DataList
    {
        $id = (int) $this->getOwner()->ID;
        if ($id <= 0) return null;

        $cache = &self::$$cacheKey;
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = $this->{$method}();
        }

        return $cache[$id];
    }


    protected function applicableCouponsAndPrice(?float $price): ?array
    {
        $owner = $this->getOwner();
        $id = (int) $owner->ID;
        if ($id <= 0) return null;

        if (!array_key_exists($id, self::$couponPriceArrayCache)) {
            self::$couponPriceArrayCache[$id] = null;
            $effectivePrice = $price ?? (float) $owner->Price;
            $coupons = $owner->DirectlyApplicableDiscountCoupons();

            if ($coupons?->exists()) {
                $minAmount = (float) $owner->config()->get('min_discount_amount');
                $result = [];

                foreach ($coupons as $coupon) {
                    if (!$coupon->IsValid()) continue;

                    $candidates = array_filter([
                        $effectivePrice - ($effectivePrice * ((float) $coupon->DiscountPercentage / 100)),
                        $effectivePrice - (float) $coupon->DiscountAbsolute,
                        (float) $coupon->DiscountPrice,
                    ], fn(float $v) => $v > $minAmount);

                    $best = !empty($candidates) ? min($candidates) : null;

                    if ($best !== null && $best < $effectivePrice && $best > 0) {
                        $result[] = ['Price' => $best, 'Coupon' => $coupon];
                    }
                }

                if (!empty($result)) {
                    usort($result, fn($a, $b) => $a['Price'] <=> $b['Price']);
                    self::$couponPriceArrayCache[$id] = $result;
                }
            }
        }

        return self::$couponPriceArrayCache[$id];
    }
}

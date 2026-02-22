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

    /**
     * @var array<int, int>
     */
    protected static array $buyableToBeExcludedFromDiscounts = [];

    /**
     * @var array<int, DBMoney|null>
     */
    protected static array $discountCouponAmount = [];

    /**
     * @var array<int, array<int, array{Price: float, Coupon: DiscountCouponOption}>|null>
     */
    protected static array $couponPriceArrayCache = [];

    /**
     * @var array<int, DBDate|null>
     */
    protected static array $discountAvailableUntilCache = [];

    /**
     * Standard SS declaration.
     *
     * @var array<string, string>
     */
    private static $belongs_many_many = [
        'ApplicableDiscountCoupons' => DiscountCouponOption::class,
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldsToTab(
            'Root.Price',
            [
                GridField::create(
                    'ApplicableDiscountCoupons',
                    'Discount Coupons',
                    $this->getOwner()->ApplicableDiscountCoupons(),
                    GridFieldConfig_RelationEditor::create()
                ),
            ]
        );
    }

    /**
     * @param object|int|string $buyableOrBuyableId
     */
    public static function add_buyable_to_be_excluded($buyableOrBuyableId): void
    {
        $id = 0;

        if (is_object($buyableOrBuyableId) && isset($buyableOrBuyableId->ID)) {
            $id = (int) $buyableOrBuyableId->ID;
        } elseif ((int) $buyableOrBuyableId > 0) {
            $id = (int) $buyableOrBuyableId;
        }

        if ($id > 0) {
            self::$buyableToBeExcludedFromDiscounts[$id] = $id;
        }
    }

    public function setCanBeNotDiscounted(): static
    {
        $ownerId = (int) $this->getOwner()->ID;
        if ($ownerId > 0) {
            self::$buyableToBeExcludedFromDiscounts[$ownerId] = $ownerId;
        }

        return $this;
    }

    public function getCanBeDiscounted(): bool
    {
        return ! isset(self::$buyableToBeExcludedFromDiscounts[(int) $this->getOwner()->ID]);
    }

    public function updateCalculatedPrice(?float $price = null): ?float
    {
        if (! $this->getCanBeDiscounted()) {
            return null;
        }

        $effectivePrice = $price ?? (float) $this->getOwner()->Price;
        $prices = $this->applicableCouponsAndPrice($effectivePrice);

        if (! empty($prices)) {
            return (float) $prices[0]['Price'];
        }

        return null;
    }

    /**
     * @return array<int, array{Price: float, Coupon: DiscountCouponOption}>|null
     */
    public function applicableCouponsAndPrice(?float $price): ?array
    {
        $owner = $this->getOwner();
        $ownerId = (int) $owner->ID;
        $effectivePrice = $price ?? (float) $owner->Price;

        if ($ownerId <= 0) {
            return null;
        }

        if (! array_key_exists($ownerId, self::$couponPriceArrayCache)) {
            self::$couponPriceArrayCache[$ownerId] = null;

            $coupons = $owner->DirectlyApplicableDiscountCoupons();
            if ($coupons && $coupons->exists()) {
                $couponPriceArray = [];

                foreach ($coupons as $coupon) {
                    if (! $coupon->IsValid()) {
                        continue;
                    }

                    $discountPercentage = max(0.0, (float) $coupon->DiscountPercentage);
                    $discountAbsolute = max(0.0, (float) $coupon->DiscountAbsolute);
                    $discountPrice = max(0.0, (float) $coupon->DiscountPrice);

                    $priceWithPercentageDiscount = $effectivePrice - ($effectivePrice * ($discountPercentage / 100));
                    $priceWithAbsoluteDiscount = $effectivePrice - $discountAbsolute;

                    $filteredArray = array_filter(
                        [
                            $priceWithPercentageDiscount,
                            $priceWithAbsoluteDiscount,
                            $discountPrice,
                        ],
                        function (float $value) use ($owner): bool {
                            return $value > (float) $owner->config()->get('min_discount_amount');
                        }
                    );

                    $bestPrice = ! empty($filteredArray) ? (float) min($filteredArray) : null;

                    if ($bestPrice !== null && $bestPrice < $effectivePrice && $bestPrice > 0) {
                        $couponPriceArray[] = [
                            'Price' => $bestPrice,
                            'Coupon' => $coupon,
                        ];
                    }
                }

                if (! empty($couponPriceArray)) {
                    usort(
                        $couponPriceArray,
                        function (array $a, array $b): int {
                            return ((float) $a['Price']) <=> ((float) $b['Price']);
                        }
                    );

                    self::$couponPriceArrayCache[$ownerId] = $couponPriceArray;
                }
            }
        }

        return self::$couponPriceArrayCache[$ownerId];
    }

    public function CurrentApplicableDiscountCoupons(): DataList
    {
        $date = date('Y-m-d');

        return $this->getOwner()->ApplicableDiscountCoupons()
            ->filter([
                'StartDate:LessThanOrEqual' => $date,
                'EndDate:GreaterThanOrEqual' => $date,
                'ApplyEvenWithoutCode' => 1,
            ]);
    }

    public function DirectlyApplicableDiscountCoupons(): DataList
    {
        return $this->CurrentApplicableDiscountCoupons()
            ->filter([
                'RequiresProductCombinationInOrder' => 0,
            ]);
    }

    public function ConditionallyApplicableDiscountCoupons(): DataList
    {
        return $this->CurrentApplicableDiscountCoupons()
            ->filter([
                'RequiresProductCombinationInOrder' => 1,
            ]);
    }

    public function DiscountCouponAmount(): DBMoney
    {
        $owner = $this->getOwner();
        $ownerId = (int) $owner->ID;

        if (! array_key_exists($ownerId, self::$discountCouponAmount)) {
            self::$discountCouponAmount[$ownerId] = null;

            $amount = (float) $owner->Price - (float) $owner->CalculatedPrice();
            $amountClean = 0.0;

            if ($amount > (float) $owner->config()->get('min_discount_amount')) {
                $amountClean = $amount;
            }

            self::$discountCouponAmount[$ownerId] = EcommerceCurrency::get_money_object_from_order_currency($amountClean);
        }

        /** @var DBMoney $money */
        $money = self::$discountCouponAmount[$ownerId];

        return $money;
    }

    public function DiscountsAvailableUntil(): ?DBDate
    {
        $owner = $this->getOwner();
        $ownerId = (int) $owner->ID;

        if ($ownerId <= 0) {
            return null;
        }

        if (! array_key_exists($ownerId, self::$discountAvailableUntilCache)) {
            self::$discountAvailableUntilCache[$ownerId] = null;

            $availableCoupons = $this->applicableCouponsAndPrice((float) $owner->Price);

            if ($availableCoupons) {
                $coupon = $availableCoupons[0]['Coupon'] ?? null;
                $next = null;

                if ($coupon && ! empty($coupon->EndDate)) {
                    $parsed = strtotime((string) $coupon->EndDate);
                    if ($parsed !== false) {
                        $next = $parsed;
                    }
                }

                if ($next === null) {
                    $fallback = strtotime((string) $owner->config()->get('default_end_date_description'));
                    if ($fallback !== false) {
                        $next = $fallback;
                    }
                }

                if ($next !== null) {
                    /** @var DBDate $obj */
                    $obj = DBDate::create_field(DBDate::class, date('Y-m-d', $next));
                    self::$discountAvailableUntilCache[$ownerId] = $obj;
                }
            }
        }

        return self::$discountAvailableUntilCache[$ownerId];
    }
}

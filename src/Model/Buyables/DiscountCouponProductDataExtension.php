<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponProductDataExtension
 *
 * @property \Sunnysideup\Ecommerce\Pages\Product|\Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponProductDataExtension $owner
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption[] ApplicableDiscountCoupons()
 */
class DiscountCouponProductDataExtension extends DataExtension
{
    private static $min_discount_amount = 0.1;
    private static $default_end_date_description = 'now +7 days';
    protected static array $buyableToBeExcludedFromDiscounts = [];
    protected static array $discountCoupontAmount = [];

    protected static array $couponPriceArrayCache = [];

    protected static array $discountAvailbleUntilCache = [];

    /**
     * stadard SS declaration.
     *
     * @var array
     */
    private static $belongs_many_many = [
        'ApplicableDiscountCoupons' => DiscountCouponOption::class,
    ];


    /**
     * Update Fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Price',
            [
                GridField::create(
                    'ApplicableDiscountCoupons',
                    'Discount Coupons',
                    $this->getOwner()->ApplicableDiscountCoupons(),
                    GridFieldConfig_RelationEditor::create()
                )
            ]
        );
    }

    public static function add_buyable_to_be_excluded($buyableOrBuyableID): void
    {
        $id = 0;

        if (is_object($buyableOrBuyableID)) {
            $id = $buyableOrBuyableID->ID;
        } elseif ((int) $buyableOrBuyableID) {
            $id = (int) $buyableOrBuyableID;
        }

        self::$buyableToBeExcludedFromDiscounts[$id] = $id;
    }

    public function setCanBeNotDiscounted(): static
    {
        self::$buyableToBeExcludedFromDiscounts[$this->getOwner()->ID] = $this->getOwner()->ID;

        return $this;
    }

    public function getCanBeDiscounted()
    {
        return ! isset(self::$buyableToBeExcludedFromDiscounts[$this->getOwner()->ID]);
    }

    /**
     * @param float $price
     *
     * @return null|float
     */
    public function updateCalculatedPrice(?float $price = null): ?float
    {
        if ($this->getCanBeDiscounted()) {
            $prices = $this->applicableCouponsAndPrice($price);
            if (!empty($prices)) {
                return $prices[0]['Price'];
            }
        }

        return null;
    }

    public function applicableCouponsAndPrice(?float $price): ?array
    {
        $owner = $this->getOwner();
        if (! isset(self::$couponPriceArrayCache[$owner->ID])) {
            self::$couponPriceArrayCache[$owner->ID] = null;
            $coupons = $owner->DirectlyApplicableDiscountCoupons();
            if ($coupons && $coupons->exists()) {
                $couponPriceArray = [];
                foreach ($coupons as $coupon) {
                    if ($coupon->isValid()) {
                        $discountPercentage = 0;
                        $discountAbsolute = 0;
                        $discountPrice = 0;
                        if ($coupon->DiscountPrice > $discountPrice) {
                            $discountPrice = $coupon->DiscountPrice;
                        }
                        if ($coupon->DiscountPercentage > $discountPercentage) {
                            $discountPercentage = $coupon->DiscountPercentage;
                        }
                        if ($coupon->DiscountAbsolute > $discountAbsolute) {
                            $discountAbsolute = $coupon->DiscountAbsolute;
                        }
                        $priceWithPercentageDiscount = $price - ($price * ($discountPercentage / 100));
                        $priceWithAbsoluteDiscount = $price - $discountAbsolute;
                        // Filter values greater than $owner->config()->get('min_discount_amount')
                        $filteredArray = array_filter(
                            [
                                $priceWithPercentageDiscount,
                                $priceWithAbsoluteDiscount,
                                $discountPrice
                            ],
                            function (float $value) use ($owner): bool {
                                return $value > $owner->config()->get('min_discount_amount');
                            }
                        );

                        // Get the minimum value from the filtered array
                        $bestPrice = !empty($filteredArray) ? min($filteredArray) : null;
                        if ($bestPrice < $price && $bestPrice > 0) {
                            $couponPriceArray[] = [
                                'Price' => $bestPrice,
                                'Coupon' => $coupon,
                            ];
                        }
                    }
                }
            }
            if (!empty($couponPriceArray)) {
                usort($couponPriceArray, function (array $a, array $b): int {
                    return $a['Price'] <=> $b['Price'];
                });
                self::$couponPriceArrayCache[$owner->ID] = $couponPriceArray;
            }
        }
        return self::$couponPriceArrayCache[$owner->ID];
    }

    public function DirectlyApplicableDiscountCoupons()
    {
        $date = date('Y-m-d');
        return $this->getOwner()->ApplicableDiscountCoupons()
            ->filter([
                'ApplyPercentageToApplicableProducts' => 1,
                'ApplyEvenWithoutCode' => 1,
                'StartDate:LessThanOrEqual' => $date,
                'EndDate:GreaterThanOrEqual' => $date
            ]);
    }

    public function DiscountCouponAmount()
    {
        $owner = $this->getOwner();
        if (! isset(self::$discountCoupontAmount[$owner->ID])) {
            self::$discountCoupontAmount[$owner->ID] = null;
            $amount = floatval($this->getOwner()->Price) - floatval($owner->CalculatedPrice());
            if ($amount > $owner->config()->get('min_discount_amount')) {
                self::$discountCoupontAmount[$owner->ID] = $amount;
            }
            self::$discountCoupontAmount[$owner->ID] = EcommerceCurrency::get_money_object_from_order_currency($amount);
        }
        return self::$discountCoupontAmount[$owner->ID];
    }



    /**
     * @return null|\SilverStripe\ORM\FieldType\DBDate
     */
    public function DiscountsAvailableUntil(): ?DBDate
    {
        $owner = $this->getOwner();
        // kickstart cache... using proper price.
        if (!isset(self::$couponPriceArrayCache)) {
            $this->getOwner()->CalculatedPrice();
        }
        if (!isset(self::$discountAvailbleUntilCache[$owner->ID])) {
            self::$discountAvailbleUntilCache[$owner->ID] = null;
            $availableCoupons = $this->applicableCouponsAndPrice($this->getOwner()->Price);
            if ($availableCoupons) {
                $coupon = $availableCoupons[0]['Coupon'] ?? null;
                if ($coupon) {
                    $next = strtotime($coupon->EndDate);
                }
                if (! $next) {
                    $next = strtotime($owner->config()->get('default_end_date_description'));
                }
                if ($next) {
                    /** @var DBDate $obj */
                    self::$discountAvailbleUntilCache[$owner->ID] = DBDate::create_field(DBDate::class, $next);
                }
            }
        }
        return self::$discountAvailbleUntilCache[$owner->ID];
    }
}

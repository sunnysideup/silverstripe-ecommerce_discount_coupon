<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use Sunnysideup\CmsEditLinkField\Api\CMSEditLinkAPI;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForCustomLists;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProductGroups;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;
use Sunnysideup\Ecommerce\Model\Extensions\EcommerceRole;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;
use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList;
use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductLists;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
use Sunnysideup\EcommerceDiscountCoupon\Search\DiscountCouponFilterForDate;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption
 *
 * @property bool $ImportedFromAPI
 * @property bool $ApplyPercentageToApplicableProducts
 * @property bool $ApplyEvenWithoutCode
 * @property string $Title
 * @property string $Code
 * @property int $NumberOfTimesCouponCanBeUsed
 * @property string $StartDate
 * @property string $EndDate
 * @property float $MaximumDiscount
 * @property float $DiscountAbsolute
 * @property float $DiscountPercentage
 * @property float $MinimumOrderSubTotalValue
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\Product[] Products()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\ProductGroup[] ProductGroups()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\ProductGroup[] ProductGroupsMustAlsoBePresentIn()
 */
class DiscountCouponOption extends DataObject
{
    /**
     * @var bool
     */
    protected bool $isNew = false;

    protected bool $_productsCalculated = false;

    /**
     * standard SS Variable.
     *
     * @var string
     */
    private static $table_name = 'DiscountCouponOption';

    private static array $db = [
        'ApplyEvenWithoutCode' => 'Boolean(1)',
        'ApplyPercentageToApplicableProducts' => 'Boolean(1)',
        'RequiresProductCombinationInOrder' => 'Boolean(1)',
        'ProductCombinationRatio' => 'Int',
        'Title' => 'Varchar(255)',
        'Code' => 'Varchar(32)',
        'NumberOfTimesCouponCanBeUsed' => 'Int',
        'StartDate' => 'Date',
        'EndDate' => 'Date',
        'MinimumOrderSubTotalValue' => 'Currency',
        'MaximumDiscount' => 'Currency',
        'DiscountAbsolute' => 'Currency',
        'DiscountPrice' => 'Currency',
        'DiscountPercentage' => 'Decimal(4,2)',
        'ComboDiscountedProductDescription' => 'Varchar(255)',
        'ComboMustHaveProductDescription' => 'Varchar(255)',
        // or or and query selection main selection
        'AndQueryProductGroupSelection' => 'Boolean(0)',
        'AndQueryCustomProductListSelection' => 'Boolean(0)',
        // or or and query selection for must also be present in
        'LimitProductListThroughCrossReferencing' => 'Boolean(0)',
        'AndQueryMustAlsoBePresentInProductGroupSelection' => 'Boolean(0)',
        'AndQueryMustAlsoBePresentInCustomProductListSelection' => 'Boolean(0)',
        // other product in Order in selection
        'AndQueryOtherProductInOrderProductGroupSelection' => 'Boolean(0)',
        'AndQueryOtherProductInOrderCustomProductListSelection' => 'Boolean(0)',
    ];

    private static array $many_many = [
        'Products' => Product::class,
        'ProductGroups' => ProductGroup::class,
        'CustomProductLists' => CustomProductList::class,
        // also present in ...
        'ProductGroupsMustAlsoBePresentIn' => ProductGroup::class,
        'CustomProductListsMustAlsoBePresentIn' => CustomProductList::class,
        // other product in Order in ...
        'OtherProductInOrderProducts' => Product::class,
        'OtherProductInOrderProductGroups' => ProductGroup::class,
        'OtherProductInOrderCustomProductLists' => CustomProductList::class,
    ];

    /**
     * standard SS variable.
     */
    private static array $indexes = [
        'Title' => true,
        'Code' => true,
        'StartDate' => true,
        'EndDate' => true,
    ];


    /**
     * standard SS variable.
     */
    private static array $casting = [
        'UseCount' => 'Int',
        'IsValid' => 'Boolean',
        'IsValidNice' => 'Varchar',
    ];

    /**
     * standard SS variable.
     */
    private static array $searchable_fields = [
        'StartDate' => [
            'filter' => DiscountCouponFilterForDate::class,
        ],
        'Title' => 'PartialMatchFilter',
        'Code' => 'PartialMatchFilter',
        'ApplyPercentageToApplicableProducts' => 'ExactMatchFilter',
        'RequiresProductCombinationInOrder' => 'ExactMatchFilter',
        'ApplyEvenWithoutCode' => 'ExactMatchFilter',
        'DiscountAbsolute' => 'ExactMatchFilter',
        'DiscountPercentage' => 'ExactMatchFilter',
        'DiscountPrice' => 'ExactMatchFilter',
    ];

    private static array $cascade_deletes = [
        // 'Products', => we want to keep the products, just remove the relation.
        // 'ProductGroups', => we want to keep the products, just remove the relation.
        // 'CustomProductLists', => we want to keep the products, just remove the relation.
        // 'ProductGroupsMustAlsoBePresentIn', => we want to keep the products, just remove the relation.
        // 'CustomProductListsMustAlsoBePresentIn', => we want to keep the products, just remove the relation.
        // 'OtherProductInOrderProducts', => we want to keep the products, just remove the relation.
        // 'OtherProductInOrderProductGroups', => we want to keep the products, just remove the relation.
        // 'OtherProductInOrderCustomProductLists', => we want to keep the products, just remove the relation.
    ];


    /**
     * standard SS variable.
     */
    private static array $field_labels = [
        'StartDate' => 'Start Date',
        'EndDate' => 'Last Day',
        'Title' => 'Name',
        'MaximumDiscount' => 'Maximum deduction - per product (i.e. if you buy four products, you can have four times the minimum discount)',
        'DiscountAbsolute' => 'Absolute Discount',
        'DiscountPercentage' => 'Percentage Discount',
        'ApplyPercentageToApplicableProducts' => 'Applicable products only',
        'RequiresProductCombinationInOrder' => 'Only applies if a combination of products is in the order',
        'NumberOfTimesCouponCanBeUsed' => 'Availability count',
        'UseCount' => 'Count of usage thus far',
        'IsValidNice' => 'Current validity',
        'ApplyEvenWithoutCode' => 'Automatically applied',
        'Products' => 'Applicable products',
        'ProductGroups' => 'Applicable Categories',
        'CustomProductLists' => 'Applicable Custom Product Lists',
        // also in ...
        'ProductGroupsMustAlsoBePresentIn' => 'Products must also be listed in this list of product groups ... ',
        'CustomProductListsMustAlsoBePresentIn' => 'Products must also be listed in this list of custom product lists ... ',
        // another product in Order in ...
        'ProductCombinationRatio' => 'Ratio between the discounted and required product.',
        'OtherProductInOrderProducts' => 'Other Products in the Order must be in this list of products ... ',
        'OtherProductInOrderProductGroups' => 'Other Products in the Order must be listed in this list of product groups ... ',
        'OtherProductInOrderCustomProductLists' => 'Other Products in the Order must be listed in this list of custom product lists ... ',
        'AndQueryProductGroupSelection' => 'Products must be listed in ALL selected product groups (rather than ANY of the selected product groups)',
        'AndQueryCustomProductListSelection' => 'Products must be listed in ALL selected custom product lists (rather than ANY of the selected custom product lists)',
        'AndQueryOtherProductInOrderCustomProductListSelection' => 'Other Products in the Order must be listed in ALL selected custom product lists (rather than ANY of the selected custom product lists)',
        'AndQueryOtherProductInOrderProductGroupSelection' => 'Other Products in the Order must be listed in ALL selected product groups (rather than ANY of the selected product groups)',
    ];

    /**
     * standard SS variable.
     */
    private static array $field_labels_right = [
        'ApplyEvenWithoutCode' => 'Discount is automatically applied: the user does not have to enter the coupon at all. ',
        'ApplyPercentageToApplicableProducts' => 'Rather than applying it to the order, the discount is directly applied to selected products (you must select products).',
        'RequiresProductCombinationInOrder' => 'E.g. Customer much have a product from category A and a product from category B in the order to get the discount.   ',
        'Title' => 'The name of the coupon is for internal use only.  This name is not exposed to the customer but can be used to find a particular coupon.',
        'Code' => 'The code that the customer enters to get their discount.',
        'StartDate' => 'First date the coupon can be used.',
        'EndDate' => 'Last day the coupon can be used.',
        'MaximumDiscount' => 'This is the total amount of discount that can ever be applied - no matter what. Set to zero to ignore.',
        'DiscountPrice' => 'New (discounted) price of the product. Set to zero to ignore.',
        'DiscountAbsolute' => 'Absolute reduction. For example, 10 = -$10.00 off. Set this value to zero to ignore.',
        'DiscountPercentage' => 'Percentage Discount.  For example, 10 = -10% discount Set this value to zero to ignore.',
        'MinimumOrderSubTotalValue' => 'Minimum sub-total of total order to make coupon applicable. For example, order must be at least $100 before the customer gets a discount. This only applies if the discount is for the whole order (i.e. not for specific products). Set this value to zero to ignore.',
        'NumberOfTimesCouponCanBeUsed' => 'Set to zero to disallow usage, set to 999,999 to allow unlimited usage.',
        'UseCount' => 'number of times this coupon has been used',
        'IsValidNice' => 'coupon is currently valid',
        // product selection
        'Products' => "This is the final list of products to which the coupon applies. To edit this list directly, please remove all selections below.",
        'ProductGroups' => 'Adding product categories helps you to select a large number of products at once. Please select categories above.  The products in each category selected will be added to the list.',
        'CustomProductLists' => 'Adding custom lists helps you to select a large number of products at once. Please select custom lists above.  The products in each list selected will be added to the list.',
        // cross reference selection
        'LimitProductListThroughCrossReferencing' => 'Tick this box to cross-reference the product list: in order for a product to be discounted it must be in the lists below as well.',
        'ProductGroupsMustAlsoBePresentIn' => 'Select cross-reference listing products (listed in both categories) - e.g. products that are in the Large Items category and Expensive Items category will have a discount.',
        'CustomProductListsMustAlsoBePresentIn' => 'Select cross-reference listing custom product lists - e.g. products that are in the Large Items category and Expensive Items category will have a discount.',
        // another product in Order in ...
        'ProductCombinationRatio' => 'For example, if the ratio is 2, then for every 2 products in the Discounted Products list, there must be 1 product in the "Other Products in Order" list. If the ratio is 1, then for every 1 product in the "Products" list, there must be 1 product in the "Other Products in Order" list. If the ratio is 0, then the ratio is unlimited. ',
        'OtherProductInOrderProducts' => 'Other Products in the Order must be in this list of products. To edit this list directly, please remove all product groups and custom list selections in the \'Other Products in Order\' tab.',
        'OtherProductInOrderProductGroups' => 'Other Products in the Order must be listed in this list of product groups. ',
        'OtherProductInOrderCustomProductLists' => 'Other Products in the Order must be listed in this list of custom product lists. ',
    ];

    /**
     * standard SS variable.
     */
    private static array $summary_fields = [
        'Title' => 'Name',
        'Code' => 'Code',
        'StartDate.Full' => 'From',
        'EndDate.Full' => 'Until',
        'IsValidNice' => 'Current',
    ];

    /**
     * standard SS variable.
     */
    private static array $defaults = [
        'NumberOfTimesCouponCanBeUsed' => '999999',
        'ProductCombinationRatio' => '1',
    ];

    /**
     * standard SS variable.
     */
    private static string $singular_name = 'Discount Coupon';

    /**
     * standard SS variable.
     */
    private static string $plural_name = 'Discount Coupons';

    /**
     *  default number of days that a coupon will be valid for
     *  used to set value of EndDate in getCMSFields
     *  set to -1 to disable.
     *
     *  @var int
     */
    private static int $default_valid_length_in_days = 7;
    /**
     * standard SS variable.
     */
    private static array $default_sort = [
        'EndDate' => 'DESC',
        'StartDate' => 'DESC',
        'ID' => 'ASC',
    ];

    public function scaffoldSearchFields($_params = null)
    {
        $fields = parent::scaffoldSearchFields($_params);
        $fields->push(
            DropdownField::create(
                'StartDate',
                _t('DiscountCouponOption.FUTURE_CURRENT_OR_PAST', 'Available ...'),
                [
                    '' => _t('DiscountCouponOption.ANY_TIME', ' -- Any Time -- '),
                    'future' => _t('DiscountCouponOption.FUTURE', 'In Future'),
                    'current' => _t('DiscountCouponOption.CURRENT', 'Now'),
                    'past' => _t('DiscountCouponOption.PAST', 'No longer available'),
                ]
            )
        );

        return $fields;
    }

    public function i18n_singular_name(): string
    {
        return _t('DiscountCouponOption.SINGULAR_NAME', 'Discount Coupon');
    }

    public function i18n_plural_name(): string
    {
        return _t('DiscountCouponOption.PLURAL_NAME', 'Discount Coupons');
    }

    /**
     * standard SS method.
     */
    public function populateDefaults(): static
    {
        $this->Code = $this->createRandomCode();
        $this->isNew = true;

        return parent::populateDefaults();
    }

    /**
     * casted variable
     * returns the number of times this coupon has been used.
     * Some of the used coupons are not submitted yet, but it should still
     * work on first come first served basis.
     */
    public function UseCount(): int
    {
        return $this->getUseCount();
    }

    public function getUseCount(): int
    {
        if ($this->ID) {
            return DiscountCouponModifier::get()->filter(['DiscountCouponOptionID' => $this->ID])->count();
        }

        return 0;
    }

    /**
     * casted variable telling us if the discount coupon is valid.
     *
     * @return bool
     */
    public function IsValid(): bool
    {
        return $this->getIsValid();
    }

    public function getIsValid()
    {
        //we go through all the options that would make it invalid...
        if (! $this->NumberOfTimesCouponCanBeUsed) {
            return false;
        }
        if ($this->getUseCount() > $this->NumberOfTimesCouponCanBeUsed) {
            return false;
        }
        $now = strtotime('now');
        $startDate = strtotime((string) $this->StartDate);
        if ($now < $startDate) {
            return false;
        }
        //include the end date itself.
        if ($this->EndDate) {
            $endDate = strtotime((string) $this->EndDate) + (60 * 60 * 24);
            if ($now > $endDate) {
                return false;
            }
        }
        $additionalChecks = $this->extend('checkForAdditionalValidity');
        if (is_array($additionalChecks) && count($additionalChecks)) {
            foreach ($additionalChecks as $additionalCheck) {
                if (!($additionalCheck || $additionalCheck === null)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * casted variable telling us if the discount coupon is valid - formatted nicely...
     *
     * @return string
     */
    public function IsValidNice(): string
    {
        return $this->getIsValidNice();
    }

    public function getIsValidNice(): string
    {
        return $this->IsValid() ? 'yes' : 'no';
    }

    /**
     * standard SS method.
     *
     * @param null|mixed $member
     * @param mixed      $context
     *
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }

        return parent::canCreate($member);
    }

    /**
     * standard SS method.
     *
     * @param null|mixed $member
     * @param mixed      $context
     *
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }

        return parent::canView($member);
    }

    /**
     * standard SS method.
     *
     * @param null|mixed $member
     * @param mixed      $context
     *
     * @return bool
     */
    public function canEdit($member = null, $context = [])
    {
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * standard SS method.
     *
     * @param null|mixed $member
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        if ($this->UseCount()) {
            return false;
        }
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return true;
        }

        return parent::canDelete($member);
    }

    /**
     * standard SS method.
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fieldLabels = $this->Config()->get('field_labels_right');
        foreach ($fields->dataFields() as $field) {
            $name = $field->getName();
            if (isset($fieldLabels[$name])) {
                $field->setDescription($fieldLabels[$name]);
            }
        }
        if ($this->ApplyEvenWithoutCode) {
            $fields->removeFieldsFromTab(
                'Root.Main',
                [
                    'Code',
                ]
            );
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                new ReadonlyField('UseCount', self::$field_labels['UseCount']),
                new ReadonlyField('IsValidNice', self::$field_labels['IsValidNice'])
            ]
        );
        if ($this->ApplyPercentageToApplicableProducts) {
            $fields->insertAfter(
                'Main',
                new Tab('DiscountedProducts', 'Discounted Products'),
            );
            $fields->removeByName('MinimumOrderSubTotalValue');

            $gridField1 = $fields->dataFieldByName('Products');
            if ($gridField1) {
                $fields->removeFieldFromTab('Root', 'Products');
                if ($this->ProductsAddedThroughLists()) {
                    $gridField1->setConfig(GridFieldBasicPageRelationConfigNoAddExisting::create());
                    $gridField1->setReadonly(true);
                } else {
                    $gridField1->setConfig(GridFieldConfigForProducts::create());
                }
                $fields->addFieldsToTab(
                    'Root.DiscountedProducts',
                    [
                        // $fields->dataFieldByName('AndQueryProductGroupSelection'),
                        $gridField1
                    ]
                );
            } else {
                die('no gridfield for products');
            }

            $gridField2 = $fields->dataFieldByName('ProductGroups');
            if ($gridField2) {
                $fields->removeFieldFromTab('Root', 'ProductGroups');
                $gridField2->setConfig(GridFieldConfigForProductGroups::create());
                $fields->addFieldsToTab(
                    'Root.DiscountedProducts',
                    [
                        HeaderField::create(
                            'Product Groups Header',
                            _t('DiscountCouponOption.PRODUCT_GROUPS', 'Select Using Product Groups'),
                            1
                        ),
                        $fields->dataFieldByName('AndQueryProductGroupSelection'),
                        $gridField2,
                    ]
                );
            }
            $gridField3 = $fields->dataFieldByName('CustomProductLists');
            if ($gridField3) {
                $fields->removeFieldFromTab('Root', 'CustomProductLists');
                $gridField3->setConfig(GridFieldConfigForCustomLists::create());
                $fields->addFieldsToTab(
                    'Root.DiscountedProducts',
                    [
                        HeaderField::create(
                            'Custom Product Lists Header',
                            _t('DiscountCouponOption.CUSTOM_PRODUCT_LISTS', 'Select Using Custom Product Lists'),
                            1
                        ),
                        $fields->dataFieldByName('AndQueryCustomProductListSelection'),
                        $gridField3
                    ]
                );
            }
            if ($this->ProductsAddedThroughLists()) {
                $fields->addFieldsToTab(
                    'Root.LimitDiscountedProducts',
                    [
                        $fields->dataFieldByName('LimitProductListThroughCrossReferencing'),
                    ]
                );
                if ($this->LimitProductListThroughCrossReferencing) {
                    $gridField4 = $fields->dataFieldByName('ProductGroupsMustAlsoBePresentIn');
                    if ($gridField4) {
                        $fields->removeFieldFromTab('Root', 'ProductGroupsMustAlsoBePresentIn');
                        $gridField4->setConfig(GridFieldConfigForProductGroups::create());
                        $fields->addFieldsToTab(
                            'Root.LimitDiscountedProducts',
                            [
                                HeaderField::create(
                                    'Limit Product Selection by Product Groups Header',
                                    _t('DiscountCouponOption.LIMIT_PRODUCT_SELECTION_BY_GROUPS', 'Limit Product Selection by Product Groups'),
                                    1
                                ),
                                $fields->dataFieldByName('AndQueryMustAlsoBePresentInProductGroupSelection'),
                                $gridField4
                            ]
                        );
                    }


                    $gridField5 = $fields->dataFieldByName('CustomProductListsMustAlsoBePresentIn');
                    if ($gridField5) {
                        $fields->removeFieldFromTab('Root', 'CustomProductListsMustAlsoBePresentIn');
                        $gridField5->setConfig(GridFieldConfigForCustomLists::create());
                        $fields->addFieldsToTab(
                            'Root.LimitDiscountedProducts',
                            [
                                HeaderField::create(
                                    'Limit Product Selection by Custom Product Lists Header',
                                    _t('DiscountCouponOption.LIMIT_PRODUCT_SELECTION_BY_CUSTOM_PRODUCT_LISTS', 'Limit Product Selection by Custom Product Lists'),
                                    1
                                ),
                                $fields->dataFieldByName('AndQueryMustAlsoBePresentInCustomProductListSelection'),
                                $gridField5
                            ]
                        );
                    }
                } else {
                    $fields->removeByName('AndQueryMustAlsoBePresentInProductGroupSelection');
                    $fields->removeByName('AndQueryMustAlsoBePresentInCustomProductListSelection');
                    $fields->removeByName('ProductGroupsMustAlsoBePresentIn');
                    $fields->removeByName('CustomProductListsMustAlsoBePresentIn');
                }
            } else {
                $fields->removeByName('AndQueryMustAlsoBePresentInProductGroupSelection');
                $fields->removeByName('AndQueryMustAlsoBePresentInCustomProductListSelection');
                $fields->removeByName('LimitProductListThroughCrossReferencing');
                $fields->removeByName('ProductGroupsMustAlsoBePresentIn');
                $fields->removeByName('CustomProductListsMustAlsoBePresentIn');
            }
            if ($this->RequiresProductCombinationInOrder) {
                $fields->addFieldsToTab(
                    'Root.DiscountedProducts',
                    [
                        TextField::create(
                            'ComboDiscountedProductDescription',
                            _t('DiscountCouponOption.COMBO_DISCOUNTED_PRODUCT_DESCRIPTION', 'Description of discounted product for combination.'),
                        ),
                    ],
                    'Products'
                );
                $fields->addFieldsToTab(
                    'Root.OrderMustAlsoHave',
                    [
                        TextField::create(
                            'ComboMustHaveProductDescription',
                            _t('DiscountCouponOption.COMBO_MUST_HAVE_PRODUCT_DESCRIPTION', 'Description of must-have product for combination.'),
                        ),
                    ],
                );
                $fields->addFieldsToTab('Root.OrderMustAlsoHave', [
                    $fields->dataFieldByName('RequiresProductCombinationInOrder'),
                    new DropdownField(
                        'ProductCombinationRatio',
                        $this->config()->get('field_labels')['ProductCombinationRatio'],
                        [
                            0 => 'unlimited',
                            1 => '1:1',
                            2 => '2:1',
                            3 => '3:1',
                            4 => '4:1',
                            5 => '5:1',
                        ]
                    )
                ]);
                $gridField6 = $fields->dataFieldByName('OtherProductInOrderProducts');
                if ($gridField6) {
                    $fields->removeFieldFromTab('Root', 'OtherProductInOrderProducts');
                    if ($this->OtherProductsAddedThroughLists()) {
                        $gridField6->setConfig(GridFieldBasicPageRelationConfigNoAddExisting::create());
                    } else {
                        $gridField6->setConfig(GridFieldConfigForProducts::create());
                    }
                    $fields->addFieldToTab('Root.OrderMustAlsoHave', $gridField6);
                }
                $gridField7 = $fields->dataFieldByName('OtherProductInOrderProductGroups');
                if ($gridField7) {
                    $fields->removeFieldFromTab('Root', 'OtherProductInOrderProductGroups');
                    $gridField7->setConfig(GridFieldConfigForProductGroups::create());
                    $fields->addFieldsToTab(
                        'Root.OrderMustAlsoHave',
                        [
                            $fields->dataFieldByName('AndQueryOtherProductInOrderProductGroupSelection'),
                            $gridField7
                        ]
                    );
                }

                $gridField8 = $fields->dataFieldByName('OtherProductInOrderCustomProductLists');
                if ($gridField8) {
                    $fields->removeFieldFromTab('Root', 'OtherProductInOrderCustomProductLists');
                    $fields->addFieldsToTab(
                        'Root.OrderMustAlsoHave',
                        [
                            $fields->dataFieldByName('AndQueryOtherProductInOrderCustomProductListSelection'),
                            $gridField8
                        ]
                    );
                }
            } else {
                $fields->addFieldsToTab('Root.OrderMustAlsoHave', [
                    $fields->dataFieldByName('RequiresProductCombinationInOrder'),
                ]);
                $fields->removeByName('ProductCombinationRatio');
                $fields->removeByName('ProductCombinationRatio');
                $fields->removeByName('OtherProductInOrderProducts');
                $fields->removeByName('OtherProductInOrderProductGroups');
                $fields->removeByName('OtherProductInOrderCustomProductLists');
                $fields->removeByName('AndQueryOtherProductInOrderProductGroupSelection');
                $fields->removeByName('AndQueryOtherProductInOrderCustomProductListSelection');
            }
            if ($this->exists()) {
                $fields->insertBefore(
                    'DiscountedProducts',
                    new Tab('Price', 'Price'),
                );
            }
        } else {
            $fields->removeByName('Products');
            $fields->removeByName('ProductGroups');
            $fields->removeByName('CustomProductLists');
            $fields->removeByName('AndQueryProductGroupSelection');
            $fields->removeByName('AndQueryCustomProductListSelection');

            $fields->removeByName('LimitProductListThroughCrossReferencing');
            $fields->removeByName('ProductGroupsMustAlsoBePresentIn');
            $fields->removeByName('CustomProductListsMustAlsoBePresentIn');
            $fields->removeByName('AndQueryMustAlsoBePresentInProductGroupSelection');
            $fields->removeByName('AndQueryMustAlsoBePresentInCustomProductListSelection');

            $fields->removeByName('RequiresProductCombinationInOrder');
            $fields->removeByName('ProductCombinationRatio');
            $fields->removeByName('OtherProductInOrderProducts');
            $fields->removeByName('OtherProductInOrderProductGroups');
            $fields->removeByName('OtherProductInOrderCustomProductLists');
            $fields->removeByName('AndQueryOtherProductInOrderProductGroupSelection');
            $fields->removeByName('AndQueryOtherProductInOrderCustomProductListSelection');
            $fields->removeFieldFromTab('Root.Main', 'ApplyEvenWithoutCode');
        }

        // $fields->removeFieldFromTab('Root', 'Products');
        // $fields->removeFieldFromTab('Root', 'ProductGroups');
        // $fields->removeFieldFromTab('Root', 'CustomProductLists');
        // $fields->removeFieldFromTab('Root', 'ProductGroupsMustAlsoBePresentIn');
        // $fields->removeFieldFromTab('Root', 'CustomProductListsMustAlsoBePresentIn');
        // $fields->removeFieldFromTab('Root', 'OtherProductInOrderProducts');
        // $fields->removeFieldFromTab('Root', 'OtherProductInOrderProductGroups');
        // $fields->removeFieldFromTab('Root', 'OtherProductInOrderCustomProductLists');


        $fields->addFieldsToTab(
            'Root.Price',
            [
                $fields->dataFieldByName('DiscountPrice')->setTitle('Discounted Price'),
                $fields->dataFieldByName('DiscountAbsolute')->setTitle('Amount Deducted in '. EcommerceCurrency::default_currency_code()),
                $fields->dataFieldByName('DiscountPercentage')->setTitle('Percentage Deducted'),
                $fields->dataFieldByName('MaximumDiscount'),
            ]
        );
        return $fields;
    }

    protected function ProductsAddedThroughLists(): bool
    {
        return $this->ProductGroups()->exists()
            || $this->CustomProductLists()->exists()
            || $this->OtherProductsAddedThroughLists();
    }

    protected function OtherProductsAddedThroughLists(): bool
    {
        return
            $this->OtherProductInOrderProductGroups()->exists() ||
            $this->OtherProductInOrderCustomProductLists()->exists();
    }

    /**
     * standard SS method
     * THIS ONLY WORKS FOR CREATED OBJECTS.
     */
    public function validate()
    {
        $validator = parent::validate();
        if (! $this->isNew) {
            if ($this->thereAreCouponsWithTheSameCode()) {
                $validator->addError(_t('DiscountCouponOption.CODEALREADYEXISTS', 'This code already exists - please use another code.'));
            }
            if (strtotime((string) $this->StartDate) < strtotime('-12 years')) {
                $validator->addFieldError(
                    'StartDate',
                    _t('DiscountCouponOption.NOSTARTDATE', 'Please enter a start date')
                );
            }
            if (strtotime((string) $this->EndDate) < strtotime('-12 years')) {
                $validator->addFieldError(
                    'EndDate',
                    _t('DiscountCouponOption.NOENDDATE', 'Please enter an end date')
                );
            }
            if (strtotime((string) $this->EndDate) < strtotime((string) $this->StartDate)) {
                $validator->addError(_t('DiscountCouponOption.ENDDATETOOEARLY', 'The end date should be after the start date'));
            }
            if ($this->DiscountPercentage < 0 || $this->DiscountPercentage > 99.999) {
                $validator->addFieldError(
                    'DiscountPercentage',
                    _t('DiscountCouponOption.DISCOUNTOUTOFBOUNDS', 'The discount percentage should be between 0 and 99.999.')
                );
            }
        }
        if (null === $this->NumberOfTimesCouponCanBeUsed || '' === $this->NumberOfTimesCouponCanBeUsed) {
            $validator->addFieldError(
                'NumberOfTimesCouponCanBeUsed',
                _t('DiscountCouponOption.SET_TIMES_AVAILABLE', 'Set the number of times the coupon is available (0 = not available ... 999,999 = almost unlimited availability)')
            );
        }

        return $validator;
    }

    /**
     * standard SS method.
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->Code) {
            $this->Code = $this->createRandomCode();
        }
        $this->Code = preg_replace('#[^a-z0-9]#i', ' ', (string) $this->Code);
        $this->Code = trim(preg_replace('#\s+#', '', (string) $this->Code));

        $i = 1;
        while ($this->thereAreCouponsWithTheSameCode() && $i < 100) {
            ++$i;
            $this->Code .= '_' . $i;
        }
        if (strlen(trim((string) $this->Title)) < 1) {
            $this->Title = $this->Code;
        }
        if ($this->ApplyPercentageToApplicableProducts) {
            //we have removed this!
            //$this->DiscountAbsolute = 0;
        } else {
            $this->ApplyEvenWithoutCode = 0;
        }
        if (! $this->StartDate) {
            $this->StartDate = date('Y-m-d');
        }

        if (! $this->EndDate) {
            $validLength = $this->config()->get('default_valid_length_in_days');
            $this->EndDate = date('Y-m-d', strtotime(date('Y-m-d') . $validLength . 'days'));
        }
        $this->LastEdited = date('Y-m-d H:i:s');
    }

    /**
     * standard SS method.
     */
    protected function onAfterWrite()
    {
        $productsArray = [];
        parent::onAfterWrite();
        if (! $this->_productsCalculated) {
            $this->_productsCalculated = true;


            $productGroups = $this->ProductGroups();
            if ($productGroups->exists()) {

                /** @var ProductGroup $productGroup */
                foreach ($productGroups as $productGroup) {
                    $productsShowable = $productGroup->getProducts();
                    if ($productsShowable->exists()) {
                        if ($this->AndQueryProductGroupSelection) {
                            $productsArray = array_intersect($productsArray, $productsShowable->columnUnique() ?? []);
                        } else {
                            $productsArray += array_merge($productsArray, $productsShowable->columnUnique() ?? []);
                        }
                    }
                }
            }

            $customLists = $this->CustomProductLists();
            if ($customLists->exists()) {

                /** @var CustomProductList $customProductList */
                foreach ($customLists as $customProductList) {
                    $productsShowable = $customProductList->Products();
                    if ($productsShowable->exists()) {
                        if ($this->AndQueryCustomProductListSelection) {
                            $productsArray = array_intersect($productsArray, $productsShowable->columnUnique() ?? []);
                        } else {
                            $productsArray += array_merge($productsArray, $productsShowable->columnUnique() ?? []);
                        }
                    }
                }
            }
            if (empty($productsArray)) {
                $productsArray = $this->Products()->columnUnique() ?? [];
            }
            $mustAlsoBePresentInProductsArray = [];
            $isLimited = false;
            if ($this->LimitProductListThroughCrossReferencing) {
                // calculated additional rules for products to be included in the discount coupon
                $mustAlsoBePresentInGroups = $this->ProductGroupsMustAlsoBePresentIn();
                /** @var ProductGroup $mustAlsoBePresentInGroup */
                foreach ($mustAlsoBePresentInGroups as $mustAlsoBePresentInGroup) {
                    $isLimited = true;
                    $mustAlsoBePresentInProducts = $mustAlsoBePresentInGroup->getProducts();
                    if ($mustAlsoBePresentInProducts->exists()) {
                        if ($this->AndQueryMustAlsoBePresentInProductGroupSelection) {
                            $mustAlsoBePresentInProductsArray = array_intersect($mustAlsoBePresentInProductsArray, $mustAlsoBePresentInProducts->columnUnique() ?? []);
                        } else {
                            $mustAlsoBePresentInProductsArray = array_merge($mustAlsoBePresentInProductsArray, $mustAlsoBePresentInProducts->columnUnique() ?? []);
                        }
                    }
                }
                $mustAlsoBePresentInCustomProductLists = $this->CustomProductListsMustAlsoBePresentIn();
                /** @var CustomProductList $mustAlsoBePresentInCustomProductList */
                foreach ($mustAlsoBePresentInCustomProductLists as $mustAlsoBePresentInCustomProductList) {
                    $isLimited = true;
                    $mustAlsoBePresentInProducts = $mustAlsoBePresentInCustomProductList->Products();
                    if ($mustAlsoBePresentInProducts->exists()) {
                        if ($this->AndQueryMustAlsoBePresentInCustomProductListSelection) {
                            $mustAlsoBePresentInProductsArray = array_intersect($mustAlsoBePresentInProductsArray, $mustAlsoBePresentInProducts->columnUnique() ?? []);
                        } else {
                            $mustAlsoBePresentInProductsArray += array_merge($mustAlsoBePresentInProductsArray, $mustAlsoBePresentInProducts->columnUnique() ?? []);
                        }
                    }
                }
            }
            if ($isLimited) {
                $mustAlsoBePresentInProductsArray = array_unique($mustAlsoBePresentInProductsArray);
                $productsArray = array_intersect($mustAlsoBePresentInProductsArray, $productsArray);
                if (empty($productsArray)) {
                    $productsArray = [-1 => -1];
                }
            }

            // put it all together - leading to a final list of products that are applicable for the discount coupon
            if (!empty($productsArray)) {
                $this->Products()->setByIDList($productsArray);
            }

            $otherProductsArray = [];
            $productGroups = $this->OtherProductInOrderProductGroups();
            if ($productGroups->exists()) {

                /** @var ProductGroup $productGroup */
                foreach ($productGroups as $productGroup) {
                    $otherProductsRequired = $productGroup->getProducts();
                    if ($otherProductsRequired->exists()) {
                        if ($this->AndQueryOtherProductInOrderProductGroupSelection) {
                            $otherProductsArray = array_intersect($otherProductsArray, $otherProductsRequired->columnUnique() ?? []);
                        } else {
                            $otherProductsArray += array_merge($otherProductsArray, $otherProductsRequired->columnUnique() ?? []);
                        }
                    }
                }
            }

            $customLists = $this->OtherProductInOrderCustomProductLists();
            if ($customLists->exists()) {

                /** @var CustomProductList $customProductList */
                foreach ($customLists as $customProductList) {
                    $otherProductsRequired = $customProductList->Products();
                    if ($otherProductsRequired->exists()) {
                        if ($this->AndQueryOtherProductInOrderCustomProductListSelection) {
                            $otherProductsArray = array_intersect($otherProductsArray, $otherProductsRequired->columnUnique() ?? []);
                        } else {
                            $otherProductsArray += array_merge($otherProductsArray, $otherProductsRequired->columnUnique() ?? []);
                        }
                    }
                }
            }
            if (! empty($otherProductsArray)) {
                $otherProductsArray = array_unique($otherProductsArray);
                $this->OtherProductInOrderProducts()->setByIDList($otherProductsArray);
            }

            $this->write();
        }
    }


    /**
     * Checks if there are coupons with the same code as the current one.
     */
    protected function thereAreCouponsWithTheSameCode(): bool
    {
        return (bool) DiscountCouponOption::get()->exclude(['ID' => $this->ID])->filter(['Code' => $this->Code])->exists();
    }

    /**
     * returns a random string.
     *
     * @param int    $length - number of characters
     * @param string $chars  - input characters
     */
    protected function createRandomCode($length = 5, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890'): string
    {
        $chars_length = strlen((string) $chars) - 1;
        $string = $chars[rand(0, $chars_length)];
        for ($i = 1; $i < $length; $i = strlen((string) $string)) {
            $r = $chars[rand(0, $chars_length)];
            if ($r !== $string[$i - 1]) {
                $string .= $r;
            }
        }

        return $string;
    }
    public function CMSEditLink($action = null): string
    {
        return CMSEditLinkAPI::find_edit_link_for_object($this, $action);
    }
}

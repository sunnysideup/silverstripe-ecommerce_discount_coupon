<?php

declare(strict_types=1);

namespace Sunnysideup\EcommerceDiscountCoupon\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\GridFieldArchiveAction;
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
use Sunnysideup\EcommerceDiscountCoupon\Pages\DiscountCouponOptionPage;
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
        'ComboDiscountedProductListDescription' => 'Varchar(255)',
        'ComboOtherProductInOrderDescription' => 'Varchar(255)',
        'ComboOtherProductInOrderListDescription' => 'Varchar(255)',
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
        'CodeIndex' => array(
            'type' => 'unique',
            'columns' => ['Code'],
        ),
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
        'ComboDiscountedProductDescriptionLink' => 'Varchar',
        'ComboOtherProductInOrderDescriptionLink' => 'Varchar',
    ];

    public function getComboDiscountedProductDescriptionLink(): string
    {
        $page = $this->myDisplayPage();
        if ($page) {
            $page->setCustomList($this);
            return $page->Link();
        }
        return '';
    }

    public function getComboOtherProductInOrderDescriptionLink(): string
    {
        $page = $this->myDisplayPage();
        if ($page) {
            $page->setCustomList($this);
            $page->setIsOtherProductsInOrder(true);
            return $page->Link();
        }
        return '';
    }

    protected function myDisplayPage()
    {
        return DiscountCouponOptionPage::get()->first();
    }

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
     *
     * Short, plain-language TITLES only.
     * All longer explanations live in $field_labels_right (used as field descriptions).
     */
    private static array $field_labels = [
        // basics
        'Title' => 'Name (PUBLIC! - e.g. "10% off Red Accessories")',
        'Code' => 'Code',
        'StartDate' => 'Start Date',
        'EndDate' => 'Last Day',
        'NumberOfTimesCouponCanBeUsed' => 'Times Available',
        'UseCount' => 'Times Used',
        'IsValidNice' => 'Currently Valid',
        // behaviour
        'ApplyEvenWithoutCode' => 'Apply Automatically',
        'ApplyPercentageToApplicableProducts' => 'Discount Specific Products',
        'RequiresProductCombinationInOrder' => 'Require Product Combination',
        'ProductCombinationRatio' => 'Product Ratio',
        // price
        'DiscountPrice' => 'Fixed Price',
        'DiscountAbsolute' => 'Amount Off',
        'DiscountPercentage' => 'Percentage Off',
        'MaximumDiscount' => 'Maximum Deduction',
        'MinimumOrderSubTotalValue' => 'Minimum Order Value',
        // discounted product selection
        'Products' => 'Applicable Products',
        'ProductGroups' => 'Applicable Categories',
        'CustomProductLists' => 'Applicable Custom Lists',
        'AndQueryProductGroupSelection' => 'Match ALL Categories',
        'AndQueryCustomProductListSelection' => 'Match ALL Custom Lists',
        // cross-referencing ("must also be present in ...")
        'LimitProductListThroughCrossReferencing' => 'Cross-reference Products',
        'ProductGroupsMustAlsoBePresentIn' => 'Must Also Be In Categories',
        'CustomProductListsMustAlsoBePresentIn' => 'Must Also Be In Custom Lists',
        'AndQueryMustAlsoBePresentInProductGroupSelection' => 'Match ALL Categories',
        'AndQueryMustAlsoBePresentInCustomProductListSelection' => 'Match ALL Custom Lists',
        // required "other products in the order"
        'OtherProductInOrderProducts' => 'Required Products',
        'OtherProductInOrderProductGroups' => 'Required Categories',
        'OtherProductInOrderCustomProductLists' => 'Required Custom Lists',
        'AndQueryOtherProductInOrderProductGroupSelection' => 'Match ALL Categories',
        'AndQueryOtherProductInOrderCustomProductListSelection' => 'Match ALL Custom Lists',
        // combo public descriptions
        'ComboDiscountedProductDescription' => 'Discounted Product Text',
        'ComboOtherProductInOrderDescription' => 'Required Product Text',
        'ComboDiscountedProductListDescription' => 'Heading for Discounted Product List',
        'ComboOtherProductInOrderListDescription' => 'Heading for Required Product List',
    ];

    /**
     * standard SS variable.
     *
     * Longer, helpful DESCRIPTIONS shown under each field (see getCMSFields()).
     * These add extra information - they never simply repeat the title above.
     */
    private static array $field_labels_right = [
        // basics
        'Title' => 'A clear, concise name. This may also be shown to customers.',
        'Code' => 'The code the customer enters at checkout to claim the discount.',
        'StartDate' => 'The first day the coupon can be used.',
        'EndDate' => 'The last day the coupon can be used (this day is included).',
        'NumberOfTimesCouponCanBeUsed' => 'How many times this coupon may be used in total. Set to 0 to disable it, or to 999,999 for effectively unlimited use.',
        'UseCount' => 'How many times this coupon has been used so far.',
        'IsValidNice' => 'Whether the coupon can be used right now, based on its dates and remaining uses.',
        // behaviour
        'ApplyEvenWithoutCode' => 'Apply the discount automatically, without the customer having to enter a code.',
        'ApplyPercentageToApplicableProducts' => 'Apply the discount straight to selected products instead of to the order total. You must select products below.',
        'RequiresProductCombinationInOrder' => 'Only apply the discount when a required combination of products is in the order (e.g. a product from category A together with a product from category B).',
        'ProductCombinationRatio' => 'The ratio of discounted products to required products. For example, 2:1 means one required product is needed for every two discounted products. Choose "unlimited" to ignore the ratio.',
        // price
        'DiscountPrice' => 'Override the product price with this fixed amount. Set to zero to ignore.',
        'DiscountAbsolute' => 'A flat amount taken off. For example, 10 = 10.00 off. Set to zero to ignore.',
        'DiscountPercentage' => 'A percentage taken off. For example, 10 = 10% off. Must be between 0 and 99.999. Set to zero to ignore.',
        'MaximumDiscount' => 'The largest deduction that can ever be applied per product, no matter what. Set to zero for no limit.',
        'MinimumOrderSubTotalValue' => 'The order sub-total required before the coupon applies (e.g. the order must reach 100 first). Only used for whole-order discounts. Set to zero to ignore.',
        // discounted product selection
        'Products' => 'The final list of products the coupon applies to. To edit this list directly, first remove all category and custom-list selections below.',
        'ProductGroups' => 'Add whole categories at once - every product in each selected category is added to the list.',
        'CustomProductLists' => 'Add whole custom lists at once - every product in each selected list is added to the list.',
        'AndQueryProductGroupSelection' => 'When ticked, a product must appear in ALL selected categories. When unticked, appearing in ANY one is enough.',
        'AndQueryCustomProductListSelection' => 'When ticked, a product must appear in ALL selected custom lists. When unticked, appearing in ANY one is enough.',
        // cross-referencing
        'LimitProductListThroughCrossReferencing' => 'Only discount a product if it also appears in the cross-reference lists below.',
        'ProductGroupsMustAlsoBePresentIn' => 'A discounted product must also appear in these categories (e.g. it is in both "Large Items" and "Expensive Items").',
        'CustomProductListsMustAlsoBePresentIn' => 'A discounted product must also appear in these custom lists.',
        'AndQueryMustAlsoBePresentInProductGroupSelection' => 'When ticked, the product must also appear in ALL selected categories. When unticked, appearing in ANY one is enough.',
        'AndQueryMustAlsoBePresentInCustomProductListSelection' => 'When ticked, the product must also appear in ALL selected custom lists. When unticked, appearing in ANY one is enough.',
        // required "other products in the order"
        'OtherProductInOrderProducts' => 'Other products that must also be in the order for the discount to apply. To edit this list directly, first remove all category and custom-list selections in this tab.',
        'OtherProductInOrderProductGroups' => 'Choose the required products via whole categories.',
        'OtherProductInOrderCustomProductLists' => 'Choose the required products via whole custom lists.',
        'AndQueryOtherProductInOrderProductGroupSelection' => 'When ticked, a required product must appear in ALL selected categories. When unticked, appearing in ANY one is enough.',
        'AndQueryOtherProductInOrderCustomProductListSelection' => 'When ticked, a required product must appear in ALL selected custom lists. When unticked, appearing in ANY one is enough.',
        // combo public descriptions
        'ComboDiscountedProductDescription' => 'Publicly shown text describing the discounted product in the combination (e.g. "Buy this accessory with a large item and get 10% off the accessory").',
        'ComboOtherProductInOrderDescription' => 'Publicly shown text describing the required product in the combination (e.g. "Buy this large item to get 10% off the accessory").',
        'ComboDiscountedProductListDescription' => 'This shows above the list of discounted products in the combination. For example, "Discounted Accessories".',
        'ComboOtherProductInOrderListDescription' => 'This shows above the list of required products in the combination. For example, "Required Large Items that offer discounted accessories if purchased together.".',
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
                new ReadonlyField('UseCount', $this->fieldLabel('UseCount')),
                new ReadonlyField('IsValidNice', $this->fieldLabel('IsValidNice')),
            ]
        );
        if ($this->ApplyPercentageToApplicableProducts) {
            $fields->removeByName('MinimumOrderSubTotalValue');
            $fields->insertAfter(
                'Main',
                new Tab('DiscountedProducts', 'Discounted Products'),
            );

            $gridField1 = $fields->dataFieldByName('Products');
            if ($gridField1) {
                $fields->removeFieldFromTab('Root', 'Products');
                if ($this->ProductsAddedThroughLists()) {
                    $gridField1->setConfig(
                        GridFieldBasicPageRelationConfigNoAddExisting::create()
                            ->removeComponentsByType(GridFieldEditButton::class)
                    );
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
            }

            $gridField2 = $fields->dataFieldByName('ProductGroups');
            if ($gridField2) {
                $fields->removeFieldFromTab('Root', 'ProductGroups');
                $gridField2->setConfig(
                    GridFieldConfigForProductGroups::create()
                        ->removeComponentsByType(GridFieldArchiveAction::class)
                        ->removeComponentsByType(GridFieldEditButton::class)
                );
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
                        $gridField4->setConfig(
                            GridFieldConfigForProductGroups::create()
                                ->removeComponentsByType(GridFieldArchiveAction::class)
                                ->removeComponentsByType(GridFieldEditButton::class)
                        );
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
                        $gridField5->setConfig(
                            GridFieldConfigForCustomLists::create()
                                ->removeComponentsByType(GridFieldArchiveAction::class)
                                ->removeComponentsByType(GridFieldEditButton::class)
                        );
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
                            $this->fieldLabel('ComboDiscountedProductDescription'),
                        ),
                        TextField::create(
                            'ComboDiscountedProductListDescription',
                            $this->fieldLabel('ComboDiscountedProductListDescription'),
                        ),

                        ReadonlyField::create(
                            'ComboDiscountedProductDescriptionLinkNice',
                            'View List of Discounted Products',
                            DBHTMLText::create()->setValue(
                                '<a href="' . $this->getComboDiscountedProductDescriptionLink() . '" target="_blank">' . $this->getComboDiscountedProductDescriptionLink() . '</a>'
                            )
                        ),
                    ],
                    'Products'
                );
                $fields->addFieldsToTab('Root.OrderMustAlsoHave', [
                    $fields->dataFieldByName('RequiresProductCombinationInOrder'),
                    TextField::create(
                        'ComboOtherProductInOrderDescription',
                        $this->fieldLabel('ComboOtherProductInOrderDescription'),
                    ),
                    TextField::create(
                        'ComboOtherProductInOrderListDescription',
                        $this->fieldLabel('ComboOtherProductInOrderListDescription'),
                    ),

                    ReadonlyField::create(
                        'ComboOtherProductInOrderDescriptionLinkNice',
                        'View list of Products that must also be present in the order',
                        DBHTMLText::create()->setValue(
                            '<a href="' . $this->getComboOtherProductInOrderDescriptionLink() . '" target="_blank">' . $this->getComboOtherProductInOrderDescriptionLink() . '</a>'
                        )
                    ),
                    new DropdownField(
                        'ProductCombinationRatio',
                        $this->fieldLabel('ProductCombinationRatio'),
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
                            HeaderField::create(
                                'Product Category Header OTHER PRODUCTS IN ORDER',
                                _t('DiscountCouponOption.SELECT_USING_HEADER', 'Select Using Categories'),
                                1
                            ),
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
                            HeaderField::create(
                                'Custom Product Lists Header OTHER PRODUCTS IN ORDER',
                                _t('DiscountCouponOption.CUSTOM_PRODUCT_LISTS', 'Select Using Custom Product Lists'),
                                1
                            ),
                            $fields->dataFieldByName('AndQueryOtherProductInOrderCustomProductListSelection'),
                            CheckboxSetField::create(
                                'OtherProductInOrderCustomProductLists',
                                $this->fieldLabel('OtherProductInOrderCustomProductLists'),
                                CustomProductList::get()->map('ID', 'Title')->toArray()
                            )
                        ]
                    );
                }
            } else {
                $fields->addFieldsToTab('Root.OrderMustAlsoHave', [
                    $fields->dataFieldByName('RequiresProductCombinationInOrder'),
                ]);
                $fields->removeByName('ProductCombinationRatio');
                $fields->removeByName('OtherProductInOrderProducts');
                $fields->removeByName('OtherProductInOrderProductGroups');
                $fields->removeByName('OtherProductInOrderCustomProductLists');
                $fields->removeByName('AndQueryOtherProductInOrderProductGroupSelection');
                $fields->removeByName('AndQueryOtherProductInOrderCustomProductListSelection');
                $fields->removeByName('ComboDiscountedProductListDescription');
                $fields->removeByName('ComboOtherProductInOrderListDescription');
                $fields->removeByName('ComboDiscountedProductDescription');
                $fields->removeByName('ComboOtherProductInOrderDescription');
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
            $fields->removeByName('ComboDiscountedProductListDescription');
            $fields->removeByName('ComboDiscountedProductDescription');
            $fields->removeByName('ComboOtherProductInOrderDescription');
            $fields->removeByName('ComboOtherProductInOrderListDescription');
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


        // Titles come from $field_labels (the standard scaffolding), so we only
        // set the currency-based one here where a value is derived at runtime.
        $fields->addFieldsToTab(
            'Root.Price',
            [
                $fields->dataFieldByName('DiscountPrice'),
                $fields->dataFieldByName('DiscountAbsolute'),
                $fields->dataFieldByName('DiscountPercentage'),
                $fields->dataFieldByName('MaximumDiscount'),
            ]
        );

        // Apply the "right-hand" descriptions from $field_labels_right to every
        // field. Done last so it also reaches fields rebuilt above (combo/ratio).
        $fieldDescriptions = (array) $this->Config()->get('field_labels_right');
        foreach ($fields->dataFields() as $field) {
            $name = $field->getName();
            if (isset($fieldDescriptions[$name])) {
                $field->setDescription($fieldDescriptions[$name]);
            }
        }

        // The absolute-discount description references the shop's currency,
        // so it is set dynamically after the static descriptions above.
        $absoluteField = $fields->dataFieldByName('DiscountAbsolute');
        if ($absoluteField) {
            $currencyCode = EcommerceCurrency::default_currency_code();
            $absoluteField->setDescription(
                'A flat amount taken off, in ' . $currencyCode
                . '. For example, 10 = ' . $currencyCode . ' 10.00 off. Set to zero to ignore.'
            );
        }

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

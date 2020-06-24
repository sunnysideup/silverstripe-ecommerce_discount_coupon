<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model;











use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;
use SilverStripe\Forms\DropdownField;
use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
use SilverStripe\Core\Config\Config;
use Sunnysideup\Ecommerce\Model\Extensions\EcommerceRole;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfig;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;



/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 **/
class DiscountCouponOption extends DataObject
{
    private static $db = array(
        'ApplyPercentageToApplicableProducts' => 'Boolean',
        'ApplyEvenWithoutCode' => 'Boolean',
        'Title' => 'Varchar(255)',
        'Code' => 'Varchar(32)',
        'NumberOfTimesCouponCanBeUsed' => 'Int',
        'StartDate' => 'Date',
        'EndDate' => 'Date',
        'MaximumDiscount' => 'Currency',
        'DiscountAbsolute' => 'Currency',
        'DiscountPercentage' => 'Decimal(4,2)',
        'MinimumOrderSubTotalValue' => 'Currency'
    );

    private static $many_many = array(
        'Products' => Product::class,
        'ProductGroups' => ProductGroup::class,
        'ProductGroupsMustAlsoBePresentIn' => ProductGroup::class
    );

    /**
     * standard SS variable
     *
     */
    private static $indexes = array(
        "Title" => true,
        "Code" => true,
        "StartDate" => true,
        "EndDate" => true
    );
    /**
     * standard SS variable
     *
     */
    private static $casting = array(
        "UseCount" => "Int",
        "IsValid" => "Boolean",
        "IsValidNice" => "Varchar"
    );


    /**
     * standard SS variable
     *
     */
    private static $searchable_fields = array(
        'StartDate' => array(
            'filter' => 'DiscountCouponFilterForDate',
        ),
        'Title' => 'PartialMatchFilter',
        "Code" => "PartialMatchFilter",
        'ApplyPercentageToApplicableProducts' => 'ExactMatchFilter',
        'ApplyEvenWithoutCode' => 'ExactMatchFilter',
        'DiscountAbsolute' => 'ExactMatchFilter',
        'DiscountPercentage' => 'ExactMatchFilter'
    );

    public function scaffoldSearchFields($_params = null)
    {
        $fields = parent::scaffoldSearchFields($_params);
        $fields->push(
            DropdownField::create(
                'StartDate',
                _t('DiscountCouponOption.FUTURE_CURRENT_OR_PAST', 'Available ...'),
                array(
                    '' => _t('DiscountCouponOption.ANY_TIME', ' -- Any Time -- '),
                    'future' => _t('DiscountCouponOption.FUTURE', 'In Future'),
                    'current' => _t('DiscountCouponOption.CURRENT', 'Now'),
                    'past' => _t('DiscountCouponOption.PAST', 'No longer available')
                )
            )
        );

        return $fields;
    }

    /**
     * standard SS variable
     *
     */
    private static $field_labels = array(
        'StartDate' => 'Start Date',
        'EndDate' => 'Last Day',
        "Title" => "Name",
        "MaximumDiscount" => "Maximum deduction",
        "DiscountAbsolute" => "Absolute Discount",
        "DiscountPercentage" => "Percentage Discount",
        "ApplyPercentageToApplicableProducts" => "Applicable products only",
        "NumberOfTimesCouponCanBeUsed" => "Availability count",
        "UseCount" => "Count of usage thus far",
        "IsValidNice" => "Current validity",
        "ApplyEvenWithoutCode" => "Automatically applied",
        "Products" => "Applicable products",
        "ProductGroups" => "Applicable Categories",
        "ProductGroupsMustAlsoBePresentIn" => "Products must also be listed in ... ",
    );

    /**
     * standard SS variable
     *
     */
    private static $field_labels_right = array(
        "ApplyEvenWithoutCode" => "Discount is automatically applied: the user does not have to enter the coupon at all. ",
        "ApplyPercentageToApplicableProducts" => "Rather than applying it to the order, the discount is directly applied to selected products (you must select products).",
        "Title" => "The name of the coupon is for internal use only.  This name is not exposed to the customer but can be used to find a particular coupon.",
        'Code' => 'The code that the customer enters to get their discount.',
        'StartDate' => 'First date the coupon can be used.',
        'EndDate' => 'Last day the coupon can be used.',
        "MaximumDiscount" => "This is the total amount of discount that can ever be applied - no matter waht. Set to zero to ignore.",
        "DiscountAbsolute" => "Absolute reduction. For example, 10 = -$10.00 off. Set this value to zero to ignore.",
        "DiscountPercentage" => "Percentage Discount.  For example, 10 = -10% discount Set this value to zero to ignore.",
        "MinimumOrderSubTotalValue" => "Minimum sub-total of total order to make coupon applicable. For example, order must be at least $100 before the customer gets a discount.",
        "NumberOfTimesCouponCanBeUsed" => "Set to zero to disallow usage, set to 999,999 to allow unlimited usage.",
        "UseCount" => "number of times this coupon has been used",
        "IsValidNice" => "coupon is currently valid",
        "Products" => "This is the final list of products to which the coupon applies. To edit this list directly, please remove all product groups selections in the 'Add Products Using Categories' tab.",
        "ProductGroups" => "Adding product categories helps you to select a large number of products at once. Please select categories above.  The products in each category selected will be added to the list.",
        "ProductGroupsMustAlsoBePresentIn" => "Select cross-reference listing products (listed in both categories) - e.g. products that are in the Large Items category and Expensive Items category will have a discount.",
    );

    /**
     * standard SS variable
     *
     */
    private static $summary_fields = array(
        "Title" => "Name",
        "Code" => 'Code',
        "StartDate.Full" => 'From',
        "EndDate.Full" => 'Until',
        'IsValidNice' => 'Current'
    );

    /**
     * standard SS variable
     *
     */
    private static $defaults = array(
        "NumberOfTimesCouponCanBeUsed" => "999999"
    );

    /**
     * standard SS variable
     *
     */
    private static $singular_name = "Discount Coupon";
    public function i18n_singular_name()
    {
        return _t("DiscountCouponOption.SINGULAR_NAME", "Discount Coupon");
    }

    /**
     * standard SS variable
     *
     */
    private static $plural_name = "Discount Coupons";
    public function i18n_plural_name()
    {
        return _t("DiscountCouponOption.PLURAL_NAME", "Discount Coupons");
    }

    /**
     * standard SS variable
     *
     */
    private static $default_sort = [
        'EndDate' =>  'DESC',
        'StartDate' => 'DESC',
        'ID' =>  'ASC'
    ];

    /**
     *
     * @var Boolean
     */
    protected $isNew = false;

    /**
     * standard SS method
     *
     */
    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->Code = $this->createRandomCode();
        $this->isNew = true;
    }

    /**
     * casted variable
     * returns the number of times this coupon has been used.
     * Some of the used coupons are not submitted yet, but it should still
     * work on first come first served basis.
     *
     * @return Int
     */
    public function UseCount()
    {
        return $this->getUseCount();
    }
    public function getUseCount()
    {
        if ($this->ID) {
            return DiscountCouponModifier::get()->filter(array("DiscountCouponOptionID" => $this->ID))->count();
        }
        return 0;
    }

    /**
     * casted variable telling us if the discount coupon is valid.
     *
     * @return Bool
     */
    public function IsValid()
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
        $now = strtotime("now");
        $startDate = strtotime($this->StartDate);
        if ($now < $startDate) {
            return false;
        }
        //include the end date itself.
        if ($this->EndDate) {
            $endDate = strtotime($this->EndDate)+(60*60*24);
            if ($now > $endDate) {
                return false;
            }
        }
        $additionalChecks = $this->extend('checkForAdditionalValidity');
        if (is_array($additionalChecks) && count($additionalChecks)) {
            foreach ($additionalChecks as $additionalCheck) {
                if (! $additionalCheck) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * casted variable telling us if the discount coupon is valid - formatted nicely...
     *
     * @return String
     */
    public function IsValidNice()
    {
        return $this->getIsValidNice();
    }
    public function getIsValidNice()
    {
        return $this->IsValid() ? "yes" : "no";
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canCreate($member = null, $context = [])
    {
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
            return true;
        }
        return parent::canCreate($member);
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canView($member = null, $context = [])
    {
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
            return true;
        }
        return parent::canView($member);
    }

    /**
     * standard SS method
     * @param Member | NULL
     * @return Boolean
     */
    public function canEdit($member = null, $context = [])
    {
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
            return true;
        }
        return parent::canEdit($member);
    }

    /**
     * standard SS method
     *
     * @param Member | NULL
     *
     * @return Boolean
     */
    public function canDelete($member = null, $context = [])
    {
        if ($this->UseCount()) {
            return false;
        }
        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
            return true;
        }
        return parent::canDelete($member);
    }

    /**
     * standard SS method
     *
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fieldLabels = $this->Config()->get("field_labels_right");
        foreach ($fields->dataFields() as $field) {
            $name = $field->getName();
            if (isset($fieldLabels[$name])) {
                $field->setDescription($fieldLabels[$name]);
            }
        }
        if ($this->ApplyEvenWithoutCode) {
            $fields->removeFieldsFromTab(
                "Root.Main",
                array(
                    'Code',
                    'MaximumDiscount',
                    'MinimumOrderSubTotalValue'
                )
            );
        }
        $fields->addFieldToTab("Root.Main", new ReadonlyField("UseCount", self::$field_labels["UseCount"]));
        $fields->addFieldToTab("Root.Main", new ReadonlyField("IsValidNice", self::$field_labels["IsValidNice"]));
        if ($gridField1 = $fields->dataFieldByName("Products")) {
            if ($this->ProductGroups()->count() || $this->ProductGroupsMustAlsoBePresentIn()->count()) {
                $gridField1->setConfig(GridFieldBasicPageRelationConfigNoAddExisting::create());
            } else {
                $gridField1->setConfig(GridFieldBasicPageRelationConfig::create());
            }
            $fields->addFieldToTab("Root.AddProductsDirectly", $gridField1);
        }
        if ($gridField2 = $fields->dataFieldByName("ProductGroups")) {
            $gridField2->setConfig(GridFieldBasicPageRelationConfig::create());
            $fields->addFieldToTab("Root.AddProductsUsingCategories", $gridField2);
        }

        if ($gridField3 = $fields->dataFieldByName("ProductGroupsMustAlsoBePresentIn")) {
            $gridField3->setConfig(GridFieldBasicPageRelationConfig::create());
            $fields->addFieldToTab("Root.AddProductsUsingCategories", $gridField3);
        }
        $fields->removeFieldFromTab("Root", "Products");
        $fields->removeFieldFromTab("Root", "ProductGroups");
        $fields->removeFieldFromTab("Root", "ProductGroupsMustAlsoBePresentIn");
        if (! $this->ApplyPercentageToApplicableProducts) {
            $fields->removeFieldFromTab("Root.Main", "ApplyEvenWithoutCode");
        }
        return $fields;
    }

    /**
     * standard SS method
     * THIS ONLY WORKS FOR CREATED OBJECTS
     */

    public function validate()
    {
        $validator = parent::validate();
        if (!$this->isNew) {
            if ($this->thereAreCouponsWithTheSameCode()) {
                $validator->error(_t('DiscountCouponOption.CODEALREADYEXISTS', "This code already exists - please use another code."));
            }
            if (isset($_REQUEST["StartDate"])) {
                $this->StartDate = date("Y-m-d", strtotime($_REQUEST["StartDate"]));
            }
            if (isset($_REQUEST["EndDate"])) {
                $this->EndDate = date("Y-m-d", strtotime($_REQUEST["EndDate"]));
            }
            if (strtotime($this->StartDate) < strtotime("-12 years")) {
                $validator->error(_t('DiscountCouponOption.NOSTARTDATE', "Please enter a start date"));
            }
            if (strtotime($this->EndDate) < strtotime("-12 years")) {
                $validator->error(_t('DiscountCouponOption.NOENDDATE', "Please enter an end date"));
            }
            if (strtotime($this->EndDate) < strtotime($this->StartDate)) {
                $validator->error(_t('DiscountCouponOption.ENDDATETOOEARLY', "The end date should be after the start date"));
            }
            if ($this->DiscountPercentage < 0 || $this->DiscountPercentage > 99.999) {
                $validator->error(_t('DiscountCouponOption.DISCOUNTOUTOFBOUNDS', "The discount percentage should be between 0 and 99.999."));
            }
        }
        if ($this->NumberOfTimesCouponCanBeUsed === null || $this->NumberOfTimesCouponCanBeUsed === '') {
            $validator->error(_t('DiscountCouponOption.SET_TIMES_AVAILABLE', "Set the number of times the coupon is available (0 = not available ... 999,999 = almost unlimited availability)"));
        }
        return $validator;
    }

    /**
     * Checks if there are coupons with the same code as the current one
     * @return Boolean
     */
    protected function thereAreCouponsWithTheSameCode()
    {
        return DiscountCouponOption::get()->exclude(array("ID" => $this->ID))->filter(array("Code" => $this->Code))->count() ? true : false;
    }


    /**
     * standard SS method
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->Code) {
            $this->Code = $this->createRandomCode();
        }
        $this->Code = preg_replace('/[^a-z0-9]/i', " ", $this->Code);
        $this->Code = trim(preg_replace('/\s+/', "", $this->Code));
        $i = 1;
        while ($this->thereAreCouponsWithTheSameCode() && $i < 100) {
            $i++;
            $this->Code = $this->Code."_".$i;
        }
        if (strlen(trim($this->Title)) < 1) {
            $this->Title = $this->Code;
        }
        if ($this->ApplyEvenWithoutCode) {
            $this->MaximumDiscount = 0;
            $this->MinimumOrderSubTotalValue = 0;
        }
        if ($this->ApplyPercentageToApplicableProducts) {
            //we have removed this!
            //$this->DiscountAbsolute = 0;
        } else {
            $this->ApplyEvenWithoutCode = 0;
        }
    }

    protected $_productsCalculated = false;
    /**
     * standard SS method
     *
     */
    public function onAfterWrite()
    {
        $productsArray = array(0 => 0);
        $mustAlsoBePresentInProductsArray = array(0 => 0);
        parent::onAfterWrite();
        if (!$this->_productsCalculated && $this->ProductGroups()->count()) {
            $this->_productsCalculated = true;
            $productGroups = $this->ProductGroups();
            $productsShowable = Product::get()->filter(array("ID" => -1));
            foreach ($productGroups as $productGroup) {
                $productsShowable = $productGroup->currentInitialProducts(null, "default");
                if ($productsShowable && $productsShowable->count()) {
                    $productsArray += $productsShowable->map("ID", "ID")->toArray();
                }
            }
            $mustAlsoBePresentInGroups = $this->ProductGroupsMustAlsoBePresentIn();
            foreach ($mustAlsoBePresentInGroups as $mustAlsoBePresentInGroup) {
                $mustAlsoBePresentInProducts = $mustAlsoBePresentInGroup->currentInitialProducts(null, "default");
                if ($mustAlsoBePresentInProducts && $mustAlsoBePresentInProducts->count()) {
                    $mustAlsoBePresentInProductsArray += $mustAlsoBePresentInProducts->map("ID", "ID")->toArray();
                }
            }
            if (count($mustAlsoBePresentInProductsArray) > 1) {
                $productsArray = array_intersect_key($mustAlsoBePresentInProductsArray, $productsArray);
            }
            $this->Products()->removeAll();
            $this->Products()->addMany($productsArray);
            $this->write();
        }
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        -
        DB::query("DELETE FROM \"DiscountCouponOption_Products\" WHERE \"DiscountCouponOptionID\" = ".$this->ID);
    }

    /**
     * returns a random string.
     * @param Int $length - number of characters
     * @param Int $chars - input characters
     * @return string
     */
    protected function createRandomCode($length = 5, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
    {
        $chars_length = (strlen($chars) - 1);
        $string = $chars{rand(0, $chars_length)};
        for ($i = 1; $i < $length; $i = strlen($string)) {
            $r = $chars{rand(0, $chars_length)};
            if ($r != $string{$i - 1}) {
                $string .=  $r;
            }
        }
        return $string;
    }
}


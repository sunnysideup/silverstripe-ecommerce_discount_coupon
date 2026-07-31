<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Pages;

use Sunnysideup\Ecommerce\Pages\ProductGroup;

/**
 * Class \Sunnysideup\EcommerceDiscountCoupon\Pages\DiscountCouponOptionPage
 *
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 * @mixin \Sunnysideup\AutomatedContentManagement\Extensions\DataObjectExtensionForLLM
 * @mixin \Sunnysideup\SimpleTemplateCaching\Extensions\DataObjectExtension
 * @mixin \Sunnysideup\YesNoAnyFilter\FixBooleanSearchAsExtension
 */
class DiscountCouponOptionPage extends ProductGroup
{
    private static $table_name = 'DiscountCouponOptionPage';

    private static $description = 'This page provides a holder to show discounted products.';
    private static $icon = 'sunnysideup/ecommerce_discount_coupon:client/images/DiscountCouponOptionPage-file.svg';
    private static $singular_name = 'Discount Coupon Option Page';

    private static $plural_name = 'Discount Coupon Option Pages';

    public function i18n_singular_name()
    {
        return _t('DiscountCouponOptionPage.SINGULARNAME', 'Discount Coupon Option Page');
    }

    public function i18n_plural_name()
    {
        return _t('DiscountCouponOptionPage.PLURALNAME', 'Discount Coupon Option Pages');
    }
    private static $allowed_children = 'none';

    private static $default_child = null;

    private static $can_be_root = true;

    private static $defaults = [
        'ShowInMenus' => false,
        'ShowInSearch' => false,
    ];

    public function canCreate($member = null, $context = [])
    {
        if (DiscountCouponOptionPage::get()->exists()) {
            return false;
        }
        return $this->canEdit($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        return $fields;
    }

    /**
     * @var int
     */
    private static $maximum_number_of_products_to_list_for_search = 1000;

    /**
     * @var string
     */
    private static $best_match_key = 'bestmatch';


    public function getMyLevelOfProductsToShow(?int $defauult = 99): int
    {
        return 2;
    }

    protected $customList = null;

    public function setCustomList($customList): void
    {
        $this->customList = $customList;
    }

    protected $isOtherProductsInOrder = false;

    public function setIsOtherProductsInOrder(bool $isOtherProductsInOrder): void
    {
        $this->isOtherProductsInOrder = $isOtherProductsInOrder;
    }



    public function Link($action = null): string
    {
        return $this->getLink($action);
    }

    public function getLink($action = null): string
    {
        if ($action) {
            return parent::Link($action);
        } elseif ($this->customList) {
            if ($this->isOtherProductsInOrder) {
                $action = 'otherproductsinorder';
            } else {
                $action = 'show';
            }
            return parent::Link($action . '/' . $this->customList->Code . '/' . $this->customList->ID);
        }
        return parent::Link();
    }
}

<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;
use Sunnysideup\EcommerceDiscountCoupon\Form\DiscountCouponSiteTreeDOD_Field;

class DiscountCouponSiteTreeDOD extends DataExtension
{
    private static $db = [
        'PageIDs' => 'Text',
    ];

    /**
     * update the CMS Fields
     *
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $label = _t(
            'DiscountCouponSiteTreeDOD.SELECT_PRODUCTS_AND_SERVICES',
            'Select Product Categories and/or Products (if nothing is selected, the discount coupon will apply to all buyables).'
        );
        $field = new DiscountCouponSiteTreeDOD_Field(
            $name = 'PageIDs',
            $title = $label,
            $sourceObject = SiteTree::class,
            $keyField = 'ID',
            $labelField = 'MenuTitle'
        );
        // $filter = function ($o) use ($obj)  {
        //     return (($obj instanceof ProductGroup || $obj instanceof Product) && ($obj->ParentID != ' . $this->owner->ID . '));
        // };
        // $field->setFilterFunction($filter);
        $fields->addFieldToTab('Root.AppliesTo', $field);
    }

    /**
     * normally returns TRUE, but returns FALSE when it, or its parent is in the list.
     * todo: add products in other product categories
     *
     * @param SiteTree $page
     *
     * @return boolean
     */
    public function canBeDiscounted(SiteTree $page)
    {
        if ($this->owner->PageIDs) {
            $allowedPageIDs = explode(',', $this->owner->PageIDs);
            $checkPages = ArrayList::create([$page]);
            $alreadyCheckedPageIDs = [];
            while ($checkPages->Count()) {
                $page = $checkPages->First();
                if (array_search($page->ID, $allowedPageIDs, true) !== false) {
                    return true;
                }
                $alreadyCheckedPageIDs[] = $page->ID;
                $checkPages->remove($page);

                // Parents list update
                if ($page->hasMethod('AllParentGroups')) {
                    $parents = ArrayList::create($page->AllParentGroups()->toArray());
                } else {
                    $parents = ArrayList::create();
                }

                $parent = $page->Parent();
                if ($parent && $parent->exists()) {
                    $parents->unshift($parent);
                }

                foreach ($parents as $parent) {
                    if (array_search($parent->ID, $alreadyCheckedPageIDs, true) === false) {
                        $checkPages->push($parent);
                    }
                }
                $checkPages->removeDuplicates();
            }
            return false;
        }
        return true;
    }
}

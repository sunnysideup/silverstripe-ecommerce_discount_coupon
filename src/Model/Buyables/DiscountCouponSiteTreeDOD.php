<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;
use Sunnysideup\EcommerceDiscountCoupon\Form\DiscountCouponSiteTreeDODField;

class DiscountCouponSiteTreeDOD extends DataExtension
{
    private static $db = [
        'PageIDs' => 'Text',
    ];

    /**
     * update the CMS Fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $label = _t(
            'DiscountCouponSiteTreeDOD.SELECT_PRODUCTS_AND_SERVICES',
            'Select Product Categories and/or Products (if nothing is selected, the discount coupon will apply to all buyables).'
        );
        $field = new DiscountCouponSiteTreeDODField(
            $name = 'PageIDs',
            $title = $label,
            $sourceObject = SiteTree::class,
            $keyField = 'ID',
            $labelField = 'MenuTitle'
        );
        // $filter = function ($o) use ($obj)  {
        //     return (($obj instanceof ProductGroup || $obj instanceof Product) && ($obj->ParentID != ' . $this->getOwner()->ID . '));
        // };
        // $field->setFilterFunction($filter);
        $fields->addFieldToTab('Root.AppliesTo', $field);
    }

    /**
     * normally returns TRUE, but returns FALSE when it, or its parent is in the list.
     * todo: add products in other product categories.
     *
     * @return bool
     */
    public function canBeDiscounted(SiteTree $page)
    {
        if ($this->getOwner()->PageIDs) {
            $allowedPageIDs = explode(',', $this->getOwner()->PageIDs);
            $checkPages = ArrayList::create([$page]);
            $alreadyCheckedPageIDs = [];
            while ($checkPages->exists()) {
                $page = $checkPages->First();
                if (in_array($page->ID, $allowedPageIDs, true)) {
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
                $parent = $page->hasMethod('ParentGroup') ? $page->ParentGroup() : $page->getParent();
                if ($parent && $parent->exists()) {
                    $parents->unshift($parent);
                }

                foreach ($parents as $parent) {
                    if (! in_array($parent->ID, $alreadyCheckedPageIDs, true)) {
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

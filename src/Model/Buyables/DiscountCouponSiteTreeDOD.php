<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

use DataExtension;
use FieldList;
use DiscountCouponSiteTreeDOD_Field;
use SiteTree;
use ArrayList;


/**
 *
 */


/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class DiscountCouponSiteTreeDOD extends DataExtension
{

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $db (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $db (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'DiscountCouponSiteTreeDOD';

    private static $db = array(
        'PageIDs' => 'Text(700)'
    );

    /**
     * update the CMS Fields
     *
     * @param FieldList $fields
     *
     */
    public function updateCMSFields(FieldList $fields)
    {
        $label = _t(
            "DiscountCouponSiteTreeDOD.SELECT_PRODUCTS_AND_SERVICES",
            'Select Product Categories and/or Products (if nothing is selected, the discount coupon will apply to all buyables).'
        );
        $field = new DiscountCouponSiteTreeDOD_Field(
            $name = "PageIDs",
            $title = $label,
            $sourceObject = "SiteTree",
            $keyField = "ID",
            $labelField = "MenuTitle"
        );
        $filter = create_function('$obj', 'return ( ( $obj InstanceOf ProductGroup || $obj InstanceOf Product) && ($obj->ParentID != '.$this->owner->ID.'));');
        $field->setFilterFunction($filter);
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
                if (array_search($page->ID, $allowedPageIDs) !== false) {
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
                    if (array_search($parent->ID, $alreadyCheckedPageIDs) === false) {
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


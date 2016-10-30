<?php

/**
 *
 */

class DiscountCouponSiteTreeDOD extends DataExtension {

    private static $db = array(
        'PageIDs' => 'Text(700)'
    );

    /**
     * update the CMS Fields
     *
     * @param FieldList $fields
     *
     */
    public function updateCMSFields(FieldList $fields) {
        $label = _t(
            "SELECTPRODUCTSANDCATEGORIES",
            _t("DiscountCouponSiteTreeDOD.SELECT_PRODUCTS_AND_SERVICES",
            "
                Select Product Categories and/or Products (if nothing is selected,
                the discount coupon will apply to all buyables).
            "
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
    function canBeDiscounted(SiteTree $page) {
        if($this->owner->PageIDs) {
            $allowedPageIDs = explode(',', $this->owner->PageIDs);
            $checkPages = new ArrayList(array($page));
            $alreadyCheckedPageIDs = array();
            while($checkPages->Count()) {
                $page = $checkPages->First();
                if(array_search($page->ID, $allowedPageIDs) !== false) {
                    return true;
                }
                $alreadyCheckedPageIDs[] = $page->ID;
                $checkPages->remove($page);

                // Parents list update
                if($page->hasMethod('AllParentGroups')) {
                    $parents = new ArrayList($page->AllParentGroups()->toArray());
                } else {
                    $parents = new ArrayList();
                }

                $parent = $page->Parent();
                if($parent && $parent->exists()) {
                    $parents->unshift($parent);
                }

                foreach($parents as $parent) {
                    if(array_search($parent->ID, $alreadyCheckedPageIDs) === false) {
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

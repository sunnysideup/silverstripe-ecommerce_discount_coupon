<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Form;

use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;

class DiscountCouponSiteTreeDODField extends TreeMultiselectField
{
    /**
     * @todoexplain how this works or what it does.
     * @param DataObject
     */
    public function saveInto(DataObjectInterface $record)
    {
        if ('unchanged' !== $this->value) {
            $items = [];

            // linting....
            /** @var DataObject */
            $myRecord = $record;

            $fieldName = $this->name;

            if ($this->value) {
                $items = preg_split('# *, *#', trim((string) $this->value));
            }

            // Allows you to modify the items on your object before save
            $funcName = "onChange{$fieldName}";
            if ($myRecord->hasMethod($funcName)) {
                $result = $record->{$funcName}($items);
                if (! $result) {
                    return;
                }
            }
            $schema = $myRecord->getSchema();
            if ($fieldName && ($schema->hasManyComponent($record->ClassName, $fieldName) || $schema->manyManyComponent($record->ClassName, $fieldName))) {
                // Set related records
                $record->{$fieldName}()->setByIDList($items);
            } else {
                $record->{$fieldName} = implode(',', $items);
            }
        }
    }
}

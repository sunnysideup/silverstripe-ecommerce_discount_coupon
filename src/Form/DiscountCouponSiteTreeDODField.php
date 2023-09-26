<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Form;

use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\DataObjectInterface;

class DiscountCouponSiteTreeDODField extends TreeMultiselectField
{
    /**
     * @todoexplain how this works or what it does.
     */
    public function saveInto(DataObjectInterface $record)
    {
        if ('unchanged' !== $this->value) {
            $items = [];

            $fieldName = $this->name;

            if ($this->value) {
                $items = preg_split('# *, *#', trim((string) $this->value));
            }

            // Allows you to modify the items on your object before save
            $funcName = "onChange{$fieldName}";
            if ($record->hasMethod($funcName)) {
                $result = $record->{$funcName}($items);
                if (! $result) {
                    return;
                }
            }
            if ($fieldName && ($record->getSchema()->hasManyComponent($record->ClassName, $fieldName) || $record->getSchema()->manyManyComponent($record->ClassName, $fieldName))) {
                // Set related records
                $record->{$fieldName}()->setByIDList($items);
            } else {
                $record->{$fieldName} = implode(',', $items);
            }
        }
    }
}

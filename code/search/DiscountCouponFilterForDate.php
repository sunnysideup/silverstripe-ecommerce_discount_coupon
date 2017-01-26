<?php
 // Future one
 //0----------F--|-------|----------------3000
 // Current One
 //0------------|--C-----|----------------3000
 // Past One
 //0------------|-------|---P-------------3000

class DiscountCouponFilterForDate extends ExactMatchFilter
{

    /**
     *@return SQLQuery
     **/
    public function apply(DataQuery $query)
    {
        $value = $this->getValue();
        $date = time();
        $filterString = '';
        switch ($value) {
            case 'future':
                $filterString = 'UNIX_TIMESTAMP("StartDate") > '.$date;
                break;
            case 'current':
                $filterString = 'UNIX_TIMESTAMP("StartDate") <=  '.$date.' AND UNIX_TIMESTAMP("EndDate") >= '.$date;
                break;
            case 'past':
                $filterString = 'UNIX_TIMESTAMP("EndDate") <  '.$date;
                break;
        }
        if($filterString) {
            $query = $query->where($filterString);
        }
        return $query;
    }

}

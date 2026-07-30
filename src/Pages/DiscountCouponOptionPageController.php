<?php

namespace Sunnysideup\EcommerceDiscountCoupon\Pages;

use SilverStripe\Core\Config\Config;
use Sunnysideup\EcommerceCustomProductLists\Pages\CustomListPageController;
use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;

/**
 * Class \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPageController
 *
 * @property \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPage $dataRecord
 * @method \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPage data()
 * @mixin \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPage
 */
class DiscountCouponOptionPageController extends CustomListPageController
{
    public function getSearchFilterHeader(): string
    {
        return _t('Ecommerce.SEARCH_THIS_LIST', 'Search this list');
    }

    private static $allowed_actions = [
        'show',
        'otherproductsinorder',
    ];

    protected bool $isOtherProductsInOrder = false;

    public function show()
    {
        return $this->index();
    }

    public function otherproductsinorder()
    {
        $this->isOtherProductsInOrder = true;
        $this->data()->setIsOtherProductsInOrder(true);
        return $this->index();
    }

    public function IsOtherProductsInOrder(): bool
    {
        return $this->isOtherProductsInOrder;
    }

    public function ResetPreferencesLink($action = null): string
    {
        return (string) $this->data()?->Link();
    }


    protected function setCustomList($customList)
    {
        if ($customList) {
            $this->customList = $customList;
        } else {
            $code = (string) $this->getRequest()->param('ID');
            $id = (int) $this->getRequest()->param('OtherID');
            if ($id && $code) {
                $this->customList = DiscountCouponOption::get()->filter(['Code' => $code, 'ID' => $id, 'RequiresProductCombinationInOrder' => true])->first();

                if ($this->customList) {
                    if (!$this->customList->getIsValid()) {
                        $this->customList = null;
                        return $this->httpError(404, _t('DiscountCouponOptionPageController.CUSTOMLISTNOTFOUND', 'Discount coupon option is not valid right now.'));
                    }
                }
            }
            if (!$this->customList) {
                return $this->httpError(404, _t('DiscountCouponOptionPageController.CUSTOMLISTNOTFOUND', 'Discount coupon option not found.'));
            }
            $this->data()->setCustomList($this->customList);
        }
    }


    protected function getFilterForFinalProductList($extraFilter = null, $alternativeSort = null): array
    {
        $filter = ['ID' => -1];
        if ($this->customList) {
            if ($this->isOtherProductsInOrder) {
                $filter = ['InternalItemID' => $this->customList->OtherProductInOrderProducts()->column('InternalItemID')];
            } else {
                $filter = ['InternalItemID' => $this->customList->Products()->column('InternalItemID')];
            }
        }
        return $filter;
    }

    public function getTitle()
    {
        if ($this->customList) {
            if (! $this->customTitle) {
                $this->customTitle = $this->customList->Title;
            }
            return $this->customTitle;
        }
        return parent::getTitle();
    }

    protected ?string $customListNote = null;

    public function getListNote() : string
    {
        if ($this->customList) {
            if (! $this->customListNote) {
                if ($this->IsOtherProductsInOrder()) {
                    $this->customListNote = $this->customList->ComboOtherProductInOrderListDescription ?: $this->customList->ComboOtherProductInOrderDescription;
                } else {
                    $this->customListNote = $this->customList->ComboDiscountedProductListDescription ?: $this->customList->ComboDiscountedProductDescription;
                }
            }
            return $this->customListNote;
        }
        return '';
    }
}

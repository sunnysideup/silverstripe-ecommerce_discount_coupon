# Upgrade Guide: Moving to Silverstripe 6

This document outlines the necessary changes to upgrade your project to be compatible with Silverstripe CMS 6 and the latest version of `sunnysideup/ecommerce_discount_coupon`.

## ⚠️ BREAKING CHANGE: Project Dependencies

Your project's `composer.json` file must be updated to require the new major versions of the core dependencies.

-   **`sunnysideup/ecommerce`**: Update constraint to `^33.0`
-   **`silverstripe/recipe-cms`**: Update constraint to `^6.0`

```json
"require": {
    "sunnysideup/ecommerce": "^33.0",
    "silverstripe/recipe-cms": "^6.0"
}
```

## ⚠️ BREAKING CHANGE: Configuration

### Database Build Class

The deprecated `SilverStripe\ORM\DatabaseAdmin` class has been removed. Update your YAML configuration files to use `SilverStripe\Dev\DbBuild`.

**Before:**
```yaml
SilverStripe\ORM\DatabaseAdmin:
  # ...
```

**After:**
```yaml
SilverStripe\Dev\DbBuild:
  # ...
```

## ⚠️ BREAKING CHANGE: API Updates

Several classes and methods have been updated to align with Silverstripe 6 conventions.

### PHP `Override` Attribute

The `@override` annotation has been replaced with the native PHP 8 `#[Override]` attribute. You must update your extended methods in the following classes:

-   `Sunnysideup\EcommerceDiscountCoupon\Form\DiscountCouponSiteTreeDODField`
-   `Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption`
-   `Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier`
-   `Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifierForm`
-   `Sunnysideup\EcommerceDiscountCoupon\Search\DiscountCouponFilterForDate`

**Example:**
```php
// Before
/**
 * @override
 */
public function myMethod() {}

// After
#[Override]
public function myMethod() {}
```

### Class Namespace Imports

The location of several core classes has changed. Update your `use` statements accordingly.

-   `SilverStripe\ORM\ArrayList` is now `SilverStripe\Model\List\ArrayList`.
-   `SilverStripe\Forms\Validator` is now `SilverStripe\Forms\Validation\Validator`.

### Method Name Changes

-   In `DiscountCouponOption.php`, the method `fields->dataFields()` has been renamed to `fields->getDataFields()`. Update any instances where you loop over form fields.

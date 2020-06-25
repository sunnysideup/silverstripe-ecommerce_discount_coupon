
ecommerce discount coupon
================================================================================

This module adds the "discount coupon" functionality
to e-commerce. This means that at the checkout page
the customer will be asked to enter a discount
coupon number.  On entering a correct number they will
receive a discount.

Developers
-----------------------------------------------
Nicolaas [at] sunnysideup.co.nz
Jeremy [at] burnbright.co.nz



Documentation
-----------------------------------------------
Please contact author for more details.

Any bug reports and/or feature requests will be
looked at

We are also very happy to provide personalised support
for this module in exchange for a small donation.


Requirements
-----------------------------------------------
see composer.json


Project Home
-----------------------------------------------
See http://code.google.com/p/silverstripe-ecommerce

Demo
-----------------------------------------------
See http://www.silverstripe-ecommerce.com


Installation Instructions
-----------------------------------------------

1. Find out how to add modules to SS and add module as per usual.

2. Review configs and add entries to app/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).

3. Make sure that you add the `DiscountCouponModifier` as a Order Modifier in the 
yml configs: 

```yml
    Order:
      modifiers:
        - DiscountCouponModifier
```



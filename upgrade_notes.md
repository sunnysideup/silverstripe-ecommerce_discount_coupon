2020-06-25 10:06

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/ecommerce_discount_coupon
php /var/www/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/upgrades/ecommerce_discount_coupon/ecommerce_discount_coupon  --root-dir=/var/www/upgrades/ecommerce_discount_coupon --write -vvv
Writing changes for 11 files
Running upgrades on "/var/www/upgrades/ecommerce_discount_coupon/ecommerce_discount_coupon"
[2020-06-25 10:06:55] Applying RenameClasses to EcommerceDiscountCouponTest.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to EcommerceDiscountCouponTest.php...
[2020-06-25 10:06:55] Applying UpdateConfigClasses to config.yml...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponSiteTreeDOD_Field.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponSiteTreeDOD_Field.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponModifier_Form.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponModifier_Form.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponModifier.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponModifier.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponFilterForDate.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponFilterForDate.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponProductDataExtension.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponProductDataExtension.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponSiteTreeDOD_ProductVariation.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponSiteTreeDOD_ProductVariation.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponSiteTreeDOD.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponSiteTreeDOD.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponSiteTreeDOD_Product.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponSiteTreeDOD_Product.php...
[2020-06-25 10:06:55] Applying RenameClasses to DiscountCouponOption.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to DiscountCouponOption.php...
[2020-06-25 10:06:55] Applying RenameClasses to _config.php...
[2020-06-25 10:06:55] Applying ClassToTraitRule to _config.php...
modified:	tests/EcommerceDiscountCouponTest.php
@@ -1,4 +1,6 @@
 <?php
+
+use SilverStripe\Dev\SapphireTest;

 class EcommerceDiscountCouponTest extends SapphireTest
 {

modified:	_config/config.yml
@@ -6,23 +6,19 @@
     - 'cms/*'
     - 'ecommerce/*'
 ---
-StoreAdmin:
+Sunnysideup\Ecommerce\Cms\StoreAdmin:
   managed_models:
-    - DiscountCouponOption
-
-OrderModifierFormController:
+    - Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption
+Sunnysideup\Ecommerce\Control\OrderModifierFormController:
   allowed_actions:
-    - DiscountCouponModifier
-
-Product:
+    - Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier
+Sunnysideup\Ecommerce\Pages\Product:
   extensions:
-    - DiscountCouponProductDataExtension
-
+    - Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponProductDataExtension
 ---
 Only:
   classexists: 'DataObjectSorterDOD'
 ---
-
 PickUpOrDeliveryModifierOptions:
   extensions:
     - DataObjectSorterDOD

modified:	src/Form/DiscountCouponSiteTreeDOD_Field.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Form;

-use TreeMultiselectField;
-use DataObjectInterface;
+
+
+use SilverStripe\ORM\DataObjectInterface;
+use SilverStripe\Forms\TreeMultiselectField;
+




modified:	src/Modifiers/DiscountCouponModifier_Form.php
@@ -2,12 +2,20 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Modifiers;

-use OrderModifierForm;
-use FieldList;
-use Requirements;
-use Form;
-use ShoppingCart;
-use Convert;
+
+
+
+
+
+
+use SilverStripe\Forms\FieldList;
+use SilverStripe\View\Requirements;
+use SilverStripe\Forms\Form;
+use Sunnysideup\Ecommerce\Api\ShoppingCart;
+use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
+use SilverStripe\Core\Convert;
+use Sunnysideup\Ecommerce\Forms\OrderModifierForm;
+


 class DiscountCouponModifier_Form extends OrderModifierForm
@@ -59,7 +67,7 @@
         if (isset($data['DiscountCouponCode'])) {
             $order = ShoppingCart::current_order();
             if ($order) {
-                $modifiers = $order->Modifiers('DiscountCouponModifier');
+                $modifiers = $order->Modifiers(DiscountCouponModifier::class);
                 $modifier = $modifiers->First();
                 if ($modifier) {
                     list($message, $type) = $modifier->updateCouponCodeEntered(Convert::raw2sql($data['DiscountCouponCode']));

modified:	src/Modifiers/DiscountCouponModifier.php
@@ -2,15 +2,26 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Modifiers;

-use OrderModifier;
-use ReadonlyField;
-use DiscountCouponOption;
-use Controller;
-use Validator;
-use FieldList;
-use TextField;
-use FormAction;
+
+
+
+
+
+
+
+
 use ProductVaration;
+use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;
+use SilverStripe\Forms\ReadonlyField;
+use Sunnysideup\EcommerceDiscountCoupon\Model\Buyables\DiscountCouponSiteTreeDOD;
+use SilverStripe\Control\Controller;
+use SilverStripe\Forms\Validator;
+use SilverStripe\Forms\TextField;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\FormAction;
+use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
+use Sunnysideup\Ecommerce\Model\OrderModifier;
+


 /**
@@ -59,7 +70,7 @@
      * @var Array
      */
     private static $has_one = array(
-        "DiscountCouponOption" => "DiscountCouponOption"
+        "DiscountCouponOption" => DiscountCouponOption::class
     );

     /**
@@ -167,7 +178,7 @@
             //-- START HACK
             return true;
             //-- END HACK
-            if (singleton('DiscountCouponOption')->hasExtension('DiscountCouponSiteTreeDOD')) {
+            if (singleton(DiscountCouponOption::class)->hasExtension(DiscountCouponSiteTreeDOD::class)) {
                 foreach ($items as $item) {
                     //here we need to add foreach valid coupon
                     //for each item->Buyable
@@ -206,7 +217,7 @@
         );
         $form = new DiscountCouponModifier_Form(
             $optionalController,
-            'DiscountCouponModifier',
+            DiscountCouponModifier::class,
             $fields,
             $actions,
             $optionalValidator

modified:	src/Search/DiscountCouponFilterForDate.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Search;

-use ExactMatchFilter;
-use DataQuery;
+
+
+use SilverStripe\ORM\DataQuery;
+use SilverStripe\ORM\Filters\ExactMatchFilter;
+

  // Future one
  //0----------F--|-------|----------------3000

modified:	src/Model/Buyables/DiscountCouponProductDataExtension.php
@@ -2,12 +2,21 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

-use DataExtension;
-use FieldList;
-use GridField;
-use GridFieldConfig_RelationEditor;
-use EcommerceCurrency;
-use DBField;
+
+
+
+
+
+
+use Sunnysideup\EcommerceDiscountCoupon\Model\DiscountCouponOption;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
+use SilverStripe\Forms\GridField\GridField;
+use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;
+use SilverStripe\ORM\FieldType\DBDate;
+use SilverStripe\ORM\FieldType\DBField;
+use SilverStripe\ORM\DataExtension;
+



@@ -43,7 +52,7 @@
     private static $table_name = 'DiscountCouponProductDataExtension';

     private static $belongs_many_many = array(
-        "ApplicableDiscountCoupons" => "DiscountCouponOption"
+        "ApplicableDiscountCoupons" => DiscountCouponOption::class
     );


@@ -170,7 +179,7 @@
             }
         }
         if ($next) {
-            return DBField::create_field('Date', $next);
+            return DBField::create_field(DBDate::class, $next);
         }
     }
 }

modified:	src/Model/Buyables/DiscountCouponSiteTreeDOD_ProductVariation.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

-use DataExtension;
-use DiscountCouponModifier;
+
+
+use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
+use SilverStripe\ORM\DataExtension;
+




modified:	src/Model/Buyables/DiscountCouponSiteTreeDOD.php
@@ -2,11 +2,17 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

-use DataExtension;
-use FieldList;
-use DiscountCouponSiteTreeDOD_Field;
-use SiteTree;
-use ArrayList;
+
+
+
+
+
+use SilverStripe\Forms\FieldList;
+use SilverStripe\CMS\Model\SiteTree;
+use Sunnysideup\EcommerceDiscountCoupon\Form\DiscountCouponSiteTreeDOD_Field;
+use SilverStripe\ORM\ArrayList;
+use SilverStripe\ORM\DataExtension;
+


 /**
@@ -57,7 +63,7 @@
         $field = new DiscountCouponSiteTreeDOD_Field(
             $name = "PageIDs",
             $title = $label,
-            $sourceObject = "SiteTree",
+            $sourceObject = SiteTree::class,
             $keyField = "ID",
             $labelField = "MenuTitle"
         );

modified:	src/Model/Buyables/DiscountCouponSiteTreeDOD_Product.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Model\Buyables;

-use DataExtension;
-use DiscountCouponModifier;
+
+
+use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
+use SilverStripe\ORM\DataExtension;
+




modified:	src/Model/DiscountCouponOption.php
@@ -2,16 +2,29 @@

 namespace Sunnysideup\EcommerceDiscountCoupon\Model;

-use DataObject;
-use DropdownField;
-use DiscountCouponModifier;
-use Permission;
-use Config;
-use ReadonlyField;
-use GridFieldBasicPageRelationConfigNoAddExisting;
-use GridFieldBasicPageRelationConfig;
-use Product;
-use DB;
+
+
+
+
+
+
+
+
+
+
+use Sunnysideup\Ecommerce\Pages\Product;
+use Sunnysideup\Ecommerce\Pages\ProductGroup;
+use SilverStripe\Forms\DropdownField;
+use Sunnysideup\EcommerceDiscountCoupon\Modifiers\DiscountCouponModifier;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\Ecommerce\Model\Extensions\EcommerceRole;
+use SilverStripe\Security\Permission;
+use SilverStripe\Forms\ReadonlyField;
+use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
+use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfig;
+use SilverStripe\ORM\DB;
+use SilverStripe\ORM\DataObject;
+


 /**
@@ -35,9 +48,9 @@
     );

     private static $many_many = array(
-        'Products' => 'Product',
-        'ProductGroups' => 'ProductGroup',
-        'ProductGroupsMustAlsoBePresentIn' => 'ProductGroup'
+        'Products' => Product::class,
+        'ProductGroups' => ProductGroup::class,
+        'ProductGroupsMustAlsoBePresentIn' => ProductGroup::class
     );

     /**
@@ -289,7 +302,7 @@
      */
     public function canCreate($member = null, $context = [])
     {
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canCreate($member);
@@ -302,7 +315,7 @@
      */
     public function canView($member = null, $context = [])
     {
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canView($member);
@@ -315,7 +328,7 @@
      */
     public function canEdit($member = null, $context = [])
     {
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canEdit($member);
@@ -333,7 +346,7 @@
         if ($this->UseCount()) {
             return false;
         }
-        if (Permission::checkMember($member, Config::inst()->get("EcommerceRole", "admin_permission_code"))) {
+        if (Permission::checkMember($member, Config::inst()->get(EcommerceRole::class, "admin_permission_code"))) {
             return true;
         }
         return parent::canDelete($member);

Writing changes for 11 files
✔✔✔
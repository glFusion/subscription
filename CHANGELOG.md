# Changelog for the Subscription plugin for glFusion

## 0.2.2
Release TBD
- Change at-registration to use trial days if set
- Implement class autoloader
- Add optional return_url config item
- Implement namespace
- Require glFusion 1.7+, lgLib 1.0.8+, Paypal 0.6.0+

## 0.2.1
Released 2016-11-16
- Fix ajax toggle for product enabled status
- Update admin subscription list view
- Match sql to current schema
- Make sure sql errors are exposed
- Fix upgrade sql for new permission fields

## 0.2.0
- Change from glFusion permissions matrix to simple group ID for access to products.
- Switch from XML to JSON for AJAX function
- Remove profile plugin update functions
- Add autotag [subscription:buynow product_id]
- Removed name property from products, use user-supplied item_id instead

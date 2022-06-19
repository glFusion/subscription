# Changelog for the Subscription plugin for glFusion

## 1.2.0
Release TBD
- Require glFusion 2.0.0+

## 1.1.1
Release 2022-01-02
- Enable notifications to referring subscriber when a referral is processed.
- Implement `plugin_iteminfo_subscription()` for Shop plugin 1.3.0+

## 1.1.0
Release 2021-04-20
- Drop views column from plans table.
- Fix plan ratings, enable rating resets from admin list.
- Refactor class properties and use accessor functions.
- Deprecate admin group, use subscription.admin permission.
- Add referrer bonus functionality. Requires Shop plugin v1.3.0+. (David Tong)

## 1.0.0
Release 2020-02-18
- Fix link to subscriptions admin list.
- Change from `Product` to `Plan`.
- Implement Menu class to show menus and messages.
- Deprecate non-uikit templates and styles.
- Fix getting user ID from order object during handlePurchase().
- Forward plan taxable status to Shop plugin when purchasing.

## 0.2.3
Release 2019-08-04
- Enable web services to allow use of `PLG_invokeService()`
- Change from Paypal to Shop integration
- Enable product ratings

## 0.2.2
Release 2018-11-04
- Add development update function
- Change at-registration to use trial days if set
- Implement class autoloader
- Add optional `return_url` config item
- Implement namespace
- Require glFusion 1.7+, lgLib 1.0.7+, Paypal 0.6.0+

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
- Add autotag `[subscription:buynow product_id]`
- Removed name property from products, use user-supplied item id instead

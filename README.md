# Subscription plugin for glFusion
Works with the Paypal plugin to allow you to sell subscriptions to content on
your site.

## Implementation
1. Install the lgLib, Paypal and Subcription plugins. Configure the Paypal
plugin with your Paypal information. 
1. Create a group that will be able to access the "premium" content.
1. Set the group on premium content items to this new group. Set access for
members and anonymous to "None".
1. Create a subscription item and set the "Subscription Group" to this new
group.

When the payment from Paypal is processed, the buyer will be added to the
premium group. When a subscription expires, the subscriber is removed from
that group.

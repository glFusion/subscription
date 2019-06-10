# Subscription plugin for glFusion
Works with the Shop plugin to allow you to sell subscriptions to content on
your site.

## Implementation
1. Install the lgLib, Shop and Subcription plugins. Configure the Shop
plugin with your Shop information. 
1. Create a group that will be able to access the "premium" content.
1. Set the group on premium content items to this new group. Set access for
members and anonymous to "None".
1. Create a subscription item and set the "Subscription Group" to this new
group.

When the payment from Shop is processed, the buyer will be added to the
premium group. When a subscription expires, the subscriber is removed from
that group.

### Subscribe at Registration
You can configure a subscription product so that new users are automatically subscribed when they register.
Check the "At Registration" checkbox and set a number of days for a trial subscription.
When the user registers, they will be subscribed for that number of days.
If the "Trial Days" field is empty, they will be subscribed for the normal subscription term.

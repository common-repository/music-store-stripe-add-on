=== Music Store - Stripe Add On ===
Contributors: codepeople
Tags:music store,payment gateway,stripe,credit card,debit card
Donate link: https://musicstore.dwbooster.com/add-ons/stripe
Requires at least: 4.4
Requires PHP: 7.4
Tested up to: 6.7
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrates the Stripe payment gateway with the **[Music Store](https://wordpress.org/plugins/music-store/)** for accepting payments with credit and debit cards.

== Description ==

The plugin allows to integrate the Stripe payment gateway with the Music Store for accepting payments with credit and debit cards.

The "Stripe Payment Gateway" section appears in the store's settings page. It can be enabled as alternative to other payment gateways like PayPal.

The payments processed by the plugin are SCA ready (Strong Customer Authentication), compatible with the new Payment services (PSD 2) - Directive (EU)

The add-on settings are:

*	Activate the Stripe payment gateway checkbox.
*	Label, the text that appears in the list of payment gateways, selectable by the buyers.
*	Payment mode?, to select between the test (sandbox) mode, or the production one.
*	Publishable key, the publishable Stripe key.
*	Secret key, the secret Stripe key.
*	Language. Select the language to use with the payment popup, if the "auto" option is selected, the language to use would dependen on the user's language.
*	Ask for billing address? would include the fields for entering the billing data.
* 	Subtitle for payment panel, the text to display at top of payment pop-up.
*	URL of logo image, the URL to the image to display at top of payment pop-up.

== Screenshots ==

1. The settings section in the store's settings page.
2. Stripe checkout modal.

== Changelog ==

= 1.2.3 =

* Modifies the loading language process to ensure WP6.7 compatibility.

= 1.2.2 =

* Modifies the Stripe integration to accept all payment methods active in the Stripe account.

= 1.2.1 =

* Fixes some conflicts with the latest Stripe API version.

= 1.2.0 =

* Upgrades the Stripe integration library.

= 1.1.0 =

* Fixes deprecated notices in the latest PHP version.

= 1.0.16 =

* Improves the plugin code and security.

= 1.0.15 =

* Supports the new coupons attributes.

= 1.0.14 =

* Updates the payment gateway integration.

= 1.0.13 =

* Improved support for world currencies.

= 1.0.12 =

* Fixes some warnings in the Stripe library.

= 1.0.11 =

* Fixes an issue with the pay what you want option.

= 1.0.10 =
= 1.0.9 =

* Modifies the capture of the buyer information.

= 1.0.8 =

* Prevents conflicts with other plugins that include Stripe library.

= 1.0.7 =

* Removes duplicated attributes.
* Fixes a warning message.

= 1.0.6 =

* Modifies the integration with the Music Store.

= 1.0.5 =

* Fixes a conflict with the latest update of the Music Store plugin.

= 1.0.4 =

* Includes the compatibility of new features in the Music Store plugin.

= 1.0.3 =

* Fixes an encoding issue in some ampersand symbols on generated URLs.

= 1.0.2 =

* Payments are SCA ready (Strong Customer Authentication), compatible with the new Payment services (PSD 2) - Directive (EU)

= 1.0.1 =

* Improves the plugin's security.

= 1.0.0 =

* First version released.
=== VR Single Property ===
Contributors: vrsp
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Single-property vacation rental system with Stripe Checkout, dynamic pricing, iCal sync, SMS housekeeping, and external check-in automation.

== Description ==

VR Single Property transforms WordPress into a full-featured vacation rental stack purpose-built for a single property owner.

* Airbnb-style booking block/shortcode with quote + Stripe Checkout.
* Dynamic pricing uplift by repeat views with Do-Not-Track respect.
* Stripe deposit/balance flows with webhook verification and off-session charges.
* iCal import/export every 15 minutes with activity logging.
* voip.ms SMS automation (checkout reminders, issue routing, check-in app notifications).
* REST webhook pushes to external check-in software.
* Business rules editor, coupons, taxes, and fees in the admin.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress “Plugins” screen.
3. Visit **VR Rental → Settings** to configure rates, Stripe keys, iCal URLs, SMS numbers, and rules.
4. Add the `[vrsp_listing]` shortcode or block to display the listing.

== Frequently Asked Questions ==

= Does the plugin support multiple properties? =
No. It is intentionally designed around a single flagship property with deep automation.

= How do balance payments work? =
If arrival is 7+ days away the guest pays a 50% deposit at checkout. The balance is auto-charged 7 days prior to arrival using the saved payment method (Stripe off-session).

= Can I disable dynamic pricing uplift? =
Set every tier uplift to `0` in **Settings → Dynamic Pricing** or place the opt-out cookie via `document.cookie = "vrsp_price_optout=1;path=/;max-age=31536000"`.

== Screenshots ==
1. Admin dashboard with live booking stats.
2. Listing block displaying quote + Stripe booking button.
3. Dynamic pricing & coupon settings.
4. SMS housekeeping log entries.

== Changelog ==

= 0.1.0 =
* Initial release with payments, pricing, iCal, SMS, and check-in integration.

== Upgrade Notice ==

= 0.1.0 =
First public release.

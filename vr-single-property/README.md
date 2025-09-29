# VR Single Property

A production-ready WordPress plugin that delivers an Airbnb-style, single-property vacation rental experience with Stripe payments, iCal sync, dynamic pricing, SMS housekeeping automation, and an external check-in integration.

## Features

- **Custom Post Types** for the primary rental, bookings, and operational logs.
- **Stripe Checkout** for deposits and balances with webhook verification and off-session balance collection.
- **Dynamic pricing engine** with repeat-view uplift tiers and coupon support.
- **iCal import/export** with WP-Cron scheduling and availability logs.
- **voip.ms SMS automation** for housekeeping workflows (outbound checkout notices and inbound replies).
- **External check-in integration** via REST webhook with retry queue.
- **Gutenberg block & shortcode** that outputs a fully interactive listing (gallery, quote, booking form) using Stripe Checkout.
- **Business rule editor** for check-in/out times, deposits, and cancellation window.
- **Sample email/receipt templates** plus a seed child theme.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- A Stripe account with API keys and webhooks configured
- voip.ms SMS API credentials (DID + API password)

## Installation

1. Clone this repository into `wp-content/plugins/vr-single-property`.
2. Activate the **VR Single Property** plugin from the WordPress Plugins screen.
3. (Optional) Copy the `theme-seed` folder into `wp-content/themes/jordan-view-retreat-child` and activate the child theme.

## Configuration

Navigate to **VR Rental → Settings** and configure:

- Base nightly rate, tax rate, and fees.
- Stripe keys (test and/or live), webhook secret, and Stripe Tax toggle.
- iCal import feeds and export token (share the export URL with channels).
- SMS credentials, housekeeper, and owner phone numbers.
- Dynamic pricing tiers and coupons.
- Business rules (check-in/out, deposit %, cancellation window).

## Stripe Setup

1. Create a webhook endpoint pointing to `https://your-site.com/wp-json/vr/v1/stripe/webhook`.
2. Subscribe to `checkout.session.completed`, `payment_intent.succeeded`, and `payment_intent.payment_failed` events.
3. Paste the signing secret into the plugin settings.
4. Ensure “automatic tax” is enabled in Stripe if you toggle **Stripe Tax**.

## iCal Sync

- Import URLs sync every 15 minutes.
- Export calendar: `https://your-site.com/vrsp-calendar/{token}.ics`.
- Logs are available under **VR Rental → Logs**.

## SMS Workflow

- Daily cron checks for departures and texts the housekeeper.
- Reply `1` to trigger the check-in app webhook.
- Reply `2` to flag an issue and notify the owner number.

## REST API

Endpoints are namespaced under `/wp-json/vr/v1/` and include:

- `POST /quote` – calculate pricing and deposits.
- `POST /booking` – create a booking + Stripe Checkout session.
- `GET /availability` – retrieve booked date ranges.
- `POST /stripe/webhook` – Stripe event listener.
- `POST /sms/webhook` – voip.ms inbound messages.

## Development

- Run PHPCS with the WordPress ruleset for linting.
- JavaScript assets are vanilla ES5 and do not require a build step.
- All PHP namespaces live under `VRSP\*`.

## Uninstall

The uninstall script removes plugin options, imported events, bookings, and logs.

## License

GPL-2.0-or-later

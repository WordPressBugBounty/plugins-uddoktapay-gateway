=== UddoktaPay ===
Contributors: uddoktapay
Tags: uddoktapay, bdpaymentgateway, bkash, nagad, paypal
Requires at least: 6.0
Tested up to: 7.0
Stable Tag: 2.7.0
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept Bangladeshi and international payments in WooCommerce through UddoktaPay.

== Description ==

UddoktaPay lets your WooCommerce store accept payments through your personal Bangladeshi accounts (bKash, Nagad, Rocket, Upay and more) as well as international methods (PayPal, Stripe, Paddle and others), with automation.

The plugin ships two gateways:

* **UddoktaPay** — local Bangladeshi methods, charged in BDT.
* **UddoktaPay International** — global methods, charged in USD.

Every payment is confirmed with a server-to-server verification against the UddoktaPay API before the order is marked paid, so order status is driven by the real payment result — not by the browser redirect or a raw webhook call.

== Features ==

* Local (BDT) and International (USD) gateways.
* Classic checkout and WooCommerce Block (Gutenberg) checkout.
* High-Performance Order Storage (HPOS) compatible.
* Automatic currency conversion with a configurable exchange rate.
* Per product-type order status — set separate statuses for physical, virtual, downloadable and backordered products after payment.
* Payment amount verification against the order total; mismatches are held for manual review.
* Server-to-server payment verification; only verified COMPLETED or PENDING results change the order.
* Secure webhook — per-order token plus API-key signature; manual or forged callbacks are rejected.
* Transaction ID and sender number columns on the WooCommerce order list.
* Transaction details shown on the order edit screen.
* Pending payment redirect URL.
* Optional debug logging.

== Supported Gateways ==

* bKash
* Rocket
* Nagad
* Upay
* Cellfin
* Tap
* Ok Wallet
* SSLCommerz
* Paypal
* Stripe
* Paddle
* Perfect Money
* Cryptomus
* Binance Personal (C2C)
* etc
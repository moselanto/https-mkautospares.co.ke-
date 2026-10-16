=== MKauto Desk ===
Contributors: pimofy
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPLv2 or later

The back office for MK Auto Spares, Kirinyaga Road, Nairobi.

== Description ==

One black and red desk under wp-admin > MKauto Desk:

* Overview - today's money, what needs attention, packing list.
* Point of sale - every sale is a real WooCommerce order. Receipts and A4 invoices, WhatsApp and e-mail sending, M-Pesa / cash / card.
* Sourcing - when a part is not in stock, buy it from a neighbour shop and still make the sale. The till records the supplier, our cost, the selling price and whether the supplier is paid. Sourcing shows what we owe each neighbour, profit and margin on sourced parts, and settles a supplier in one click.
* WhatsApp orders - whole-cart "Order on WhatsApp" from the cart page plus the theme's single-part leads (vehicle and chassis number included), one list with status, replies and hand-over to the till.
* Payment plans - deposit plus weekly instalments on bigger items, statements and reminders.
* Garage & service - bookings with vehicle, registration number and mileage, mechanic, week view.
* Reminders, follow-ups, marketing, social catalogue, team permissions with an activity log, encrypted customer contacts, branded Excel reports.

== Installation ==

1. Upload the mk-desk folder (or zip) under Plugins > Add New > Upload and activate. WooCommerce must be active.
2. Open MKauto Desk > Settings: set M-Pesa details, invoice logo and mechanics.
3. Add shop managers under Users > Add New (role Shop manager), then set their limits under MKauto Desk > Team.

== Changelog ==

= 1.4.0 =
* Payments: a payment code (M-Pesa code) already used on a till sale, plan payment or WhatsApp order is refused everywhere; M-Pesa codes must be 10 letters/digits. Blocked attempts go to the Team log.
* Bookings: one open booking per phone number per day on the website (staff get a warning); past or made-up times refused; dates limited to 60 days ahead; slots that have passed today shown as unavailable; a lock stops two people taking the last place at the same moment. Staff can no longer save an open booking in the past.
* Till: price locked in Source this item and on catalogue lines for staff without price permission; closing the dialog clears it; selling price required; confirmations for below-cost, KSh 0 items and M-Pesa sales without a code.
* Team log: bookings (created, edited, rescheduled, status, deleted, website), plan creation, plan payments and cancellations, and WhatsApp order status now logged under the right areas.
* Removed leftover wig wording from templates, placeholders and the plans table.

= 1.3.1 =
* Garage & service terms and conditions: customers must tick to agree before booking (checked in the browser and on the server). Terms open in a pop-up from the form and are listed on the page. Editable in Desk > Settings (one term per line, Heading: text). Agreement and terms version are recorded in the booking history, the WhatsApp message and the shop e-mail.

= 1.3.0 =
* Garage & Service page redesigned form-first: the booking form now sits at the top beside a short headline (and straight under it on phones), in three short steps. Services became compact cards that preselect the service and jump to the form; steps, contact details and trust points moved into a slim column; a sticky "Book a service" button appears on phones when the form is off screen.

= 1.2.2 =
* Point of sale: redesigned the custom item / sourced part dialog - labelled fields, KSh price boxes, a proper on/off switch, an Owe / Paid segmented control, a live price-cost-profit summary, and Cancel / Add to sale buttons. Fixes the stretched checkbox and radio buttons.

= 1.2.1 =
* Fix: the Team & activity page loaded without the Desk stylesheet, so tabs, filters, summary boxes and tables showed as plain text. It now uses the same black and red design as every other Desk page.

= 1.2.0 =
* New public Garage & Service page (/garage-service/, shortcode [mkd_garage]) created automatically and added to the main menu: hero, eight service cards, how it works, booking form (vehicle, reg. no., mileage, service, garage or mobile mechanic, live time slots, contact) and FAQ. Each booking is saved to MKauto Desk > Garage & service as Awaiting confirmation, the shop is e-mailed, and the customer is sent to WhatsApp with the booking written out.
* Shop address now comes from the theme Customizer.

= 1.1.0 =
* Point of sale: "+ Sourced part" button beside the search box, and a "Sourced" tag on every product tile to sell a catalogue part bought from a neighbour (supplier, our cost, paid or owed). Sourced catalogue parts do not reduce our own stock and appear in Sourcing with their profit.

= 1.0.0 =
* First release, built on the NURA Desk feature set and adapted for spare parts: neighbour sourcing and supplier ledger, garage and service bookings with vehicle details, theme WhatsApp leads in the Desk, MK red and black design.

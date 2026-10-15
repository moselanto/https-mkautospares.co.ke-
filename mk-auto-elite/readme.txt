=== MK Auto Elite ===
Contributors: mkautospares
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 2.20.3
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Premium automotive WooCommerce theme with an intelligent vehicle-fitment finder,
predictive search, and WhatsApp ordering. Built for the Kenyan and wider African
market with global performance, accessibility and SEO standards.

== Description ==

MK Auto Elite helps customers find the right spare part in under 10 seconds. It
ships with:

* A cascading "Find Parts for Your Vehicle" finder (Make > Model > Year > Engine > Fuel > Transmission)
* Predictive AJAX search across product name, SKU, OEM and vehicle
* WhatsApp ordering + "Request price" flow for unpriced products
* HPOS-compatible WooCommerce integration
* A performance-first, Core Web Vitals-oriented front end
* Full Customizer-based Theme Options (contact, WhatsApp, brand accent, map)
* Guided setup wizard and recommended-plugin installer

== Installation ==

1. Upload the theme zip via Appearance > Themes > Add New > Upload Theme.
2. Activate MK Auto Elite.
3. When prompted, install and activate WooCommerce.
4. Open the "MK Auto Elite" setup wizard in the admin menu and follow the steps.
5. Set your contact details and WhatsApp number in Appearance > Customize > MK Auto Elite.

== Changelog ==

= 2.20.3 =
* New: WhatsApp order form. Every Order on WhatsApp / Ask price button opens a checkout-style form (quantity, vehicle and chassis, delivery method with KSh 500 fee and free delivery from KSh 10,000, payment choice, live total) and sends a formatted order with reference MK-YYMMDD-XXXX to WhatsApp. Requests are saved under WooCommerce > WhatsApp Orders.
* New: redesigned WhatsApp chat widget with business-hours status, teaser, quick topics, product context and AI hand-off. Replaces the old floating WhatsApp button.

= 2.19.7 =
* Fix: WordPress 6.7 "translation loading for the mk-auto-elite domain was triggered too early" notice. Vehicle finder and fitment tool labels are now translated on demand instead of while the theme loads.

= 2.19.6 =
* SEO: /brand/<slug>/ (WooCommerce Brands) now 301-redirects to the matching /brands/<slug>/ Make hub; part-brand archives without a Make are noindexed; bare /brands/ and /brand/ redirect to the home page make section; Make hubs requested in the Rank Math sitemap.

= 2.19.5 =
* Products: optional Condition field (New genuine/OEM, New aftermarket, Used/ex-Japan, Refurbished) on the product edit screen; unset products are detected from the name (used, ex-Japan, second-hand).
* Products: visible "Condition:" line under the price when known; used and refurbished parts no longer show the "Genuine & quality-checked" badge.
* SEO: Offer itemCondition now matches the Product condition (Rank Math defaulted offers to NewCondition). Return-policy link in schema now finds the /return-refund/ page.

= 2.19.4 =
* SEO: vehicle make and model archives now show a keyword-led H1 ("Toyota Spare Parts in Kenya", "Toyota Fielder Spare Parts") instead of just the term name.

= 2.19.3 =
* SEO: product category titles no longer double the word "Parts" when the category name already ends in "Parts" (e.g. "Ex-Japan Spare Parts in Kenya").

= 2.19.2 =
* Fix: 2.19.1 shipped stray backslashes before "!" in Seo/Schema.php, a PHP parse error that took the whole site down (HTTP 500). Removed.

= 2.19.1 =
* SEO: Product brand in structured data is no longer the vehicle make. If Rank Math's brand matches the product's vehicle make (mk_make / _mk_make), it is replaced with the real part brand (product_brand, pa_brand or _mk_brand) or removed when none is recorded.
* SEO: Offer seller now references the single site Organization by @id instead of a nested duplicate Organization.

= 2.19.0 =
* SEO: structured data no longer duplicates Rank Math. Schema.php now enriches Rank Math's Organization (AutoPartsStore, full address, hours, geo, map, socials) and Product (correct part brand instead of vehicle make, OEM number as MPN, condition, compatible vehicles, category, shipping and 7-day return policy) and prints only what Rank Math does not (FAQ, Knowledge Centre Article, fallback graph).
* SEO: price-on-request products no longer output an Offer without a price.
* SEO: new Seo\Meta module - keyword-led titles and descriptions for shop, product categories, vehicle make/model archives, Knowledge Centre and guides when no Rank Math override is set; empty /fitment/ and /locations/ archives noindexed; default share image when a page has none.
* Customizer: new "SEO & Local Business" section (short brand, street, city, county, schema hours, latitude/longitude, return days, default share image).

= 2.18.0 =
* Current live release. Staff sign-in screen and login hardening now ship in the separate MK Auto Core plugin (mk-auto-core/).

= 1.0.0 =
* Phase 3 foundation: OOP core, design system, header/footer, homepage,
  vehicle finder shell, WooCommerce wrappers, WhatsApp ordering, AJAX search,
  setup wizard, plugin installer.

== Credits ==

* Fonts: Inter (SIL OFL 1.1) and Sora (SIL OFL 1.1) - self-hosted, add WOFF2 files under /assets/fonts.
* Logo: MK Auto Spares.

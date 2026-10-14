=== MK Auto Elite ===
Contributors: mkautospares
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 2.19.0
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

=== MK Auto Elite ===
Contributors: mkautospares
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 2.29.0
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
* WhatsApp order form with live total, saved leads and a business-hours chat widget
* Lipa na M-Pesa (Paybill) payment method for classic and block checkout
* Shop filters (part type, car make, price, stock) and vehicle-aware related products
* Mobile tab bar and slide-in navigation drawer
* Designed Contact page with WhatsApp enquiry form, and an SEO Knowledge Centre with FAQ schema
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

= 2.29.0 =
* Knowledge Centre: 16 new guides targeting high-demand Kenyan searches (hybrid battery prices, spare parts prices, Kirinyaga Road, ordering online with M-Pesa, Fielder/Axio, Demio, Honda Fit, Probox, check engine light, overheating, CVT, AC, NTSA inspection, ex-Japan engines, spark plugs, fuel consumption). 31 guides in total.
* Knowledge Centre: new topics "Model Guides" and "Prices & Costs".
* SEO: FAQPage schema generated from each guide's FAQ section; "Related guides" internal links on every guide; styled price tables.

= 2.28.0 =
* Security: rate limits on bookings, chat and WhatsApp orders use Security::client_ip() (fake CF-Connecting-IP headers no longer bypass them).
* Security: Gemini API key read from GEMINI_API_KEY / MKAE_GEMINI_API_KEY first and sent in the x-goog-api-key header, not the URL.
* Security: per-IP ceiling on public lookups (search, fitment, terms).
* Security: http to https redirect on the front end; HSTS, X-Permitted-Cross-Domain-Policies and Cross-Origin-Opener-Policy headers.

= 2.27.1 =
* Fix: self-healing scheduler. WP-Cron is disabled on the server and loopback requests are blocked, which left cart reminders and WooCommerce's scheduled actions (unpaid-order cancelling, cleanup, Action Scheduler migration) stuck since 25 July. A backup runner now runs anything overdue after a page has been sent to the visitor, at most once every 5 minutes, with a lock.
* New: health panel on WooCommerce > Cart Reminders showing last and next reminder run, WP-Cron status and overdue scheduled actions, with a "Run reminders and queue now" button.

= 2.27.0 =
* New: abandoned cart and browse reminders. Logged-in shoppers' viewed products and carts are remembered; guests are included only when they tick "Email me a reminder of my cart" at checkout. An hourly job sends branded emails: cart reminder after 1 hour and 24 hours, viewed-parts reminder after 24 hours (at most one browse email every 3 days), each with the parts, price, similar parts for the same vehicle, a red action button and a WhatsApp button. Stops automatically when the shopper orders.
* New: WooCommerce > Cart Reminders list (shopper, parts, phone, status, last activity) with a one-tap "Send on WhatsApp" button that opens a ready-written follow-up message to the shopper.
* Privacy: one-click unsubscribe link and List-Unsubscribe header in every email; opt-out tick box in My Account > Account details; staff/admin activity is never tracked.

= 2.26.0 =
* Responsive pass for every screen size: layout grids, maps, tables and media can no longer push pages wider than the screen (fixes right-edge overflow on Knowledge Centre, Contact, About and product pages).
* 10"-11" laptops (1024-1279px): tighter header and menu, single-column hero below 1100px, two-column contact cards, single-column contact form, 3-column footer.
* 13"-14" laptops (1280px+): standard two-column layouts.
* 15"-16" laptops (1440px+): wider container (1360px).
* 17" / full-HD (1680px+): 1480px container, 5 products per row, 4 guides per row, slightly larger text.
* 27" / QHD (2200px+): 1640px container, 6 products per row, readable line lengths.
* Short laptop screens: smaller hero and lower sticky offsets.

= 2.25.2 =
* Mobile home hero: the scrollable make-button row (added in 2.24.1) was widening the whole hero, pushing the headline and the finder card off the right edge. The hero column and the row are now locked to the screen width.

= 2.25.1 =
* Mobile: vehicle finder no longer spills off the right edge - grid columns can shrink, so the scrollable make-chip row, selects and saved-car bar stay inside the card and centred.
* Vehicle finder: browser cache of year/engine/fuel/gearbox lists renewed (old cached counts were still showing catalogue-wide numbers) and keyed to the catalogue version.
* Vehicle finder on the shop/results page now restores the full chosen vehicle (make, model, year...) from the URL, so the finder, button count and results agree.
* "Your car" bar hidden on a make page for a different make, and on results pages, to avoid two vehicles showing at once.
* Make pages: "Popular models" now lists that make's own models with the most parts, instead of an alphabetical slice of all models.

= 2.25.0 =
* About Us page: photo hero, trust strip (genuine and OEM, fitment checked, M-Pesa, countrywide delivery, 7-day returns), workshop photo with caption, "Car spare parts delivered across Kenya" section with towns served, keyword-led SEO title and meta description for this page (overrides Rank Math defaults here only), descriptive image alt text.

= 2.24.1 =
* Mobile: the vehicle finder's popular-make row and the shop's part-type chip rows now show that they scroll - fade at the cut-off edge, round arrow buttons, a red scroll progress bar, snap-to-chip scrolling and a one-time gentle nudge the first time the row is seen on a phone (skipped for reduced-motion users).

= 2.24.0 =
* New About Us page template (page-about-us.php, used automatically for /about-us/): breadcrumb, keyword-led hero ("Genuine car spare parts in Kenya"), live parts and makes stats, Who we are story, four pillars, Genuine / OEM / Aftermarket / Ex-Japan explainer, category and make links, How we work steps, visit-the-shop panel, 7-question FAQ and call to action.
* About page structured data: AboutPage linked to the site Organization, BreadcrumbList and FAQPage.

= 2.23.1 =
* Vehicle finder: year, engine, fuel and gearbox counts now include only products matching every earlier choice, so "Show N parts" matches the results page.
* Make and model pages (/brands/..., /models/...) use the hub template again: intro, vehicle finder with the make preselected, filters and model links (WooCommerce's template loader had replaced it).

= 2.23.0 =
* Vehicle finder rebuilt: Make, Model and Year up front with engine, fuel and gearbox under "More options"; quick-pick buttons for the 8 most-stocked makes; live "Show N parts" count; saved "Your car" bar with one-tap Shop parts; make preselected on make pages; empty fields left out of the search URL.
* Vehicle finder speed: makes and models now come from one cached map (two database queries, cached 12 hours, cleared when products or make/model terms change, stored in the browser), so the first two steps fill instantly; other steps are cached per session.
* Vehicle finder accessibility: real labels, focus states, loading spinner, required-make prompt, GA4 vehicle_finder event.

= 2.22.1 =
* WhatsApp order form: removed the Bank transfer option (no bank details published); customers choose M-Pesa or Cash on delivery, matching checkout.
* WhatsApp order form: confirmation screen is business-hours aware (Mon-Sat 8am-6pm, Nairobi time) - "open now, reply within minutes" or "closed, we reply first thing".

= 2.22.0 =
* Knowledge Centre (/knowledge/) redesigned: breadcrumb, hero with live-filter guide search, stats, topic chips (mkae_guide_topic), featured latest guide, card grid with topic tag, reading time and icon covers for guides without images, styled pagination.
* Knowledge Centre SEO content (page 1): "Common warning signs" grid linking to part categories, Kenyan-roads maintenance schedule table, Genuine vs OEM vs aftermarket vs ex-Japan explainer, parts-by-make links, 8-question FAQ, WhatsApp / Find parts call to action.
* Knowledge Centre structured data: CollectionPage with ItemList of guides, BreadcrumbList and FAQPage.
* WhatsApp order message now includes the M-Pesa Paybill line when M-Pesa is chosen.

= 2.21.0 =
* New Contact page template (page-contact.php, used automatically for the /contact/ page): hero with live open/closed status (Nairobi time), WhatsApp / Call / Email / Visit cards, map with Get directions, shop and service-centre details, How ordering works steps, help links.
* Contact enquiry form that opens WhatsApp with a formatted message: topic chips, name, validated Kenyan phone, vehicle make/model/year, chassis/VIN, order number for order and return enquiries, message, reference MK-C-YYMMDD-XXXX; email fallback; honeypot and fill-time spam check; GA4 generate_lead and Facebook Contact events.
* Removes the empty "M-Pesa:" line that the old page text showed.

= 2.20.4 =
* Product page: WooCommerce's floated 48% summary and gallery widths overridden; the theme grid now controls the two columns.
* Product page: full product title (no clipping), black price, brand-red Add to cart with matching quantity box, full-width Order on WhatsApp, 4:3 gallery frame, square thumbnails, sticky gallery on desktop, empty fit-check result hidden.

= 2.20.3 =
* Product cards: titles clamp to exactly two full lines (the link clamps, the heading reserves space) - fixes titles being cut by the price row.

= 2.20.2 =
* Hero vehicle finder "Find Parts" button no longer clipped; booking steps wrap on small screens. (Card-title fix superseded by 2.20.3.)

= 2.20.1 =
* Cart: only one empty-cart message, and it no longer appears on a cart that has items.
* Category filter bar shows sibling categories as part-type chips when a category has no sub-categories.

= 2.20.0 =
* Checkout: new "M-Pesa (Paybill 529914)" payment method for classic and block checkout. Orders go On hold; thank-you page and customer email show Paybill steps with the exact amount and a WhatsApp confirmation button. Settings under WooCommerce > Settings > Payments.
* Checkout: phone number required (classic and block); Terms page found by slug when not set in WooCommerce; footer Privacy link uses the WordPress privacy page.
* Cart: sticky bar with subtotal and Proceed to checkout; empty cart shows Find parts for my car, Continue shopping and Ask on WhatsApp.
* Mini cart: prices no longer show "&nbsp;".
* Shop, category, search and make/model pages: part-type chips and a Filter results panel (car make, price range, in stock only) with removable active-filter pills.
* Related products prefer the same vehicle model, then make, within the same part category.
* Price-on-request products show a quote checklist (vehicle, chassis, photo of old part, reply hours).
* My Account: benefits panel (guest checkout reassurance, order tracking) and restyled login/register forms.
* Book a Service: 3-step "request > we confirm > visit" explainer and request note; visible honeypot label removed.
* New 404 page: "We couldn't find that page" with Shop parts, Find by car, WhatsApp and popular categories.
* Guides show updated date, reading time and author line.
* Footer emoji icons replaced with SVG icons; duplicate "M-Pesa:" label removed; duplicate home-page newsletter removed; tablet header tidied; horizontal overflow clipped.

= 2.19.9 =
* Mobile navigation: labelled "Menu" button in the header; bottom tab bar Home, Shop, Search, Cart (with count), Menu with active-page highlight; Search tab jumps to the header search.
* New slide-in mobile drawer: logo and Close, sign-in / account strip, quick links (Home, All parts, Find by car, My orders), expandable categories with icons and counts, popular car makes, Explore links, help card with WhatsApp, Call, hours, location and M-Pesa. Swipe or Esc to close, focus trap, scroll lock, GA4 mobile_nav events.

= 2.19.8 =
* New: WhatsApp order form. Every Order on WhatsApp / Ask price button opens a form (quantity, vehicle and chassis, delivery with KSh 500 fee and free delivery from KSh 10,000, payment choice, live total) and sends a formatted order with reference MK-YYMMDD-XXXX to WhatsApp. Requests are saved under WooCommerce > WhatsApp Orders.
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

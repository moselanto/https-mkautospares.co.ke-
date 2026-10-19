# MK Auto Spares - SEO, AEO, GEO and Schema Strategy (Oct 2026)

Owner brief: `MK AUTO SEO AEO GEO AND SCHEMA.docx` (19 Oct 2026). Goal: make
mkautospares.co.ke one of the most visible, trusted car spare-parts sources in
Kenya for Google, Bing and AI assistants. This file is the plan of record;
`docs/HANDOVER-2026-10.md` is the work log.

Verified facts are marked **[verified]** with the date observed. Everything
else is a hypothesis or a plan. No search volumes or rankings are invented.

## 1. Baseline audit (19 Oct 2026)

| Area | Finding | Status |
| --- | --- | --- |
| Stack | WordPress + WooCommerce, theme MK Auto Elite 2.29.0, Rank Math (system of record), Cloudflare in front | [verified] |
| Catalogue | 4,300+ products. Homepage category counts: Suspension 1,515, Engine 760, Brakes 459, Electrical 357, Transmission 249, Cooling 242, Accessories 235, Lighting 201, Body 163, Fuel 155, Exterior 96, Steering 37 | [verified] |
| Homepage title | "Car Spare Parts Kenya \| Genuine & Ex-Japan \| MK Auto Spares" - good, keep | [verified] |
| Homepage ranking | mkautospares.co.ke appears in web results for "car spare parts Nairobi Kirinyaga Road" and "Toyota spare parts Kenya online" (Exa web search) | [verified, not a Google position] |
| Category pages | Have keyword titles ("Suspension Parts in Kenya") and helpful intros, but H1 is just "Suspension" and there was no FAQ / price information | [verified] |
| Homepage FAQ | Only 3 questions; none answered "where to buy in Nairobi", chassis number, genuine vs ex-Japan, payment, returns | [verified] -> fixed 2.30.0 |
| robots.txt | Theme-served file **blocked GPTBot, ClaudeBot, CCBot and meta-externalagent** - directly against the goal of being recommended by AI | [verified in code] -> fixed 2.30.0 |
| Sitemap line | robots.txt pointed at `/wp-sitemap.xml` although Rank Math owns `/sitemap_index.xml` | [verified in code] -> fixed 2.30.0 |
| Cloudflare | Non-browser requests (curl from a data centre) get a "Just a moment" managed challenge on every URL including robots.txt and sitemaps. Googlebot is normally allowed as a verified bot, but AI crawlers and SEO tools may be challenged | [verified] -> **action for owner, see section 7** |
| Faceted URLs | ?mk_make, ?min_price, ?stock, ?orderby filtered shop views were indexable | [verified in code] -> noindex,follow in 2.30.0 |
| Search results | /?s= pages indexable | -> noindex,follow in 2.30.0 |
| Schema | Already good (2.19.x): de-duplicated with Rank Math, AutoPartsStore, part brand (never the vehicle make), MPN, condition, isAccessoryOrSparePartFor, shipping, 7-day returns, no Offer on price-on-request. Missing: contactPoint, description, knowsAbout, OfferCatalog, GTIN, PropertyValue specs, ItemList on archives, FAQ on archives | -> added 2.30.0 |
| llms.txt | None | -> added 2.30.0 |
| Knowledge Centre | 31 guides (2.29.0) incl. chassis number, genuine vs aftermarket vs ex-Japan, prices, Kirinyaga Road | [verified in repo] |

Not measured yet (needs owner access): Search Console coverage and queries,
GA4 organic conversions, Core Web Vitals field data, Rich Results Test on live
URLs (blocked to us by Cloudflare). See section 7.

## 2. Competitor evidence (web search, 19 Oct 2026)

| Competitor | Observed | Strength | Opportunity for MK |
| --- | --- | --- | --- |
| SasaParts (sasaparts.com) | "Kenya's Largest Auto Parts Marketplace", claims 120,000+ parts, search by part number / engine code / brand, same-day Nairobi | Part-number and engine-code search, strong positioning copy | MK already searches SKU/OEM; say so prominently (done in FAQ) and add engine-code examples to category intros |
| Amex Auto Parts (amexautoparts.com) | "Automotive Parts Megastore in Kenya", priced listings with model + chassis code in names | Very specific product names | MK names are similar; add price-range answers per category/make (done) |
| Toyota Kenya / CFAO (toyotakenya.ke, cfaomobility.co.ke) | Official genuine parts pages | Authority on "genuine Toyota parts" | Do not compete on "official"; win "Toyota spare parts price Kenya", model + part queries |
| Partspoa, 888 Auto Spares, E-spareparts | Toyota/Japanese parts with prices, part numbers | Price + part-number pages | MK has 4,300 priced items; product titles now "... Price in Kenya" where length allows |
| Kirinyaga Road shops (KM Auto Spares, Smallwood, Carmath, Kabugua, SamTech, JSAM, Gitonga) | Many rank for "Kirinyaga Road" with thin shop pages | Local signals | MK has the strongest catalogue + guides; keep Google Business Profile and NAP consistent |
| Nanak, Simco, Taisho, Motorshop (named in brief) | Not surfaced in these two searches; check again in Search Console "Queries" and manual Google searches | - | Record in section 6 when checked |

## 3. Keyword architecture (one primary intent per URL)

| Intent cluster | Example seeds (from brief) | Target URL type |
| --- | --- | --- |
| Core commercial | car spare parts Kenya / Nairobi, auto spare parts Kenya, online car spare parts Kenya, genuine / OEM / ex-Japan car parts Kenya | Homepage (title already matches) |
| Shop / buy online | buy car spare parts online Kenya | /shop/ (Meta: "Buy Car Spare Parts Online in Kenya") |
| Vehicle make | Toyota / Nissan / Subaru / Mazda / Mitsubishi / BMW / Mercedes-Benz / Land Rover / Range Rover / VW / Isuzu spare parts Kenya | /brands/<make>/ hubs (H1 "X Spare Parts in Kenya", data FAQ added) |
| Vehicle model | Prado / X-Trail / Fielder / Forester parts | /mk_model/ hubs (data FAQ added) |
| Part category | suspension, brakes, engine, electrical & sensors, gearbox, cooling, steering, lighting, body parts Kenya / Nairobi | /product-category/<cat>/ (title "X Parts in Kenya", intro, data FAQ added) |
| Part + vehicle + price | Toyota Prado shock absorber price Kenya, Fielder brake pads price | Product pages ("... Price in Kenya" title when <=60 chars, price + stock + OEM description) |
| Problem / how-to | how to identify the correct spare part, chassis number lookup, genuine vs aftermarket | Knowledge Centre guides + homepage FAQ |
| Local | car spare parts near Kirinyaga Road Nairobi | Homepage FAQ #1, Kirinyaga Road guide, Contact page, Google Business Profile |

Rules: no mass location pages; landing generator keeps creating drafts only;
make hubs (/brands/) are canonical, /brand/ redirects (2.19.6).

## 4. What 2.30.0 implements

- `inc/src/Seo/Answers.php` (new): homepage FAQ source (10 answers built from Customizer + policy facts), data-driven archive FAQs (live part count, real price range, top categories/makes), product title/description fallbacks, noindex,follow for faceted + search URLs, `/llms.txt`.
- `inc/src/Seo/Schema.php`: FAQPage mirrors homepage + archive FAQs; business gains contactPoint (English/Swahili), description, knowsAbout, hasOfferCatalog; products gain GTIN (only valid 8/12/13/14 digits) and additionalProperty (OEM number, engine, vehicle years); ItemList of products on clean first-page archives.
- `inc/src/Security.php`: robots.txt now allows AI search/assistant crawlers (GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, PerplexityBot, Google-Extended, CCBot ...), still blocks Bytespider and PetalBot; points at Rank Math's sitemap.
- `front-page.php`, `template-parts/hub.php`: render the new FAQs.
- `inc/src/Customizer.php`: `delivery_fee` (500) and `free_delivery_from` (10000) settings so FAQ figures follow the Shipping page.

Never fabricated: prices, stock, ratings, reviews, GTINs, fitment. All figures
are read from WooCommerce at request time and cached 12 h.

## 5. 30 / 60 / 90-day roadmap

**Days 0-30 (owner + next agent)**
1. Upload theme 2.30.0; purge Cloudflare and any page cache.
2. Cloudflare: Security > Bots - make sure "Block AI bots / AI Scrapers and Crawlers" is OFF and AI Crawl Control allows GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, PerplexityBot, Google-Extended, Bingbot. Check there is no physical robots.txt in the web root (it would override the theme one).
3. Rank Math: Titles & Meta > Products - leave product title/description templates empty or default (theme fallbacks apply only when no per-product value is typed). Turn off Rank Math FAQ schema blocks if any page duplicates the homepage FAQ.
4. Search Console + Bing Webmaster: submit /sitemap_index.xml; inspect homepage, /product-category/suspension/, /brands/toyota/, one product; Rich Results Test + validator.schema.org on the same four URLs.
5. Google Business Profile: same name, address, phone, hours as the site; categories "Auto parts store"; add website, products, photos; ask happy customers for reviews (never write them).
6. Fill `_mk_oem`, `_mk_make`, `_mk_model`, years and `_mk_condition` on the top 200 sellers (pilot batch).

**Days 31-60**
7. Pilot product copy: 50 top products get a 60-120 word unique description (fit, position, OEM numbers, condition, what's in the box) from verified data; measure CTR in Search Console vs control.
8. Category intros for Brakes, Engine, Electrical, Cooling, Transmission, Lighting, Body matching the Suspension pattern; H1 "X Parts" on categories.
9. Model hubs for the top 15 Kenyan models (Fielder/Axio, Prado, X-Trail, Forester, Probox, Demio, Note, Vitz, Hilux, Land Cruiser, Harrier, Premio, Wish, Outlander, CX-5) with real intro text.
10. Citations: list MK on Kenyan directories that are genuinely used (Google, Bing Places, Apple Business Connect, Yelp KE, Kenya business directories); same NAP everywhere.

**Days 61-90**
11. Expand product copy pilot in batches of 200 if CTR/conversions improved.
12. New guides where Search Console shows impressions without a good page.
13. Re-run AI answer tests (section 6) and record citations.

## 6. AI / answer-engine test log

Run monthly; record date, engine, query, whether MK was mentioned/cited.

| Date | Engine | Query | MK cited? |
| --- | --- | --- | --- |
| 19 Oct 2026 | Web search (Exa) | car spare parts Nairobi Kirinyaga Road online shop | Yes (homepage, result 2 of 10) |
| 19 Oct 2026 | Web search (Exa) | Toyota spare parts Kenya online | Yes (homepage, result 9 of 10) |
| - | ChatGPT search | where to buy genuine car spare parts in Nairobi | to test after Cloudflare change |
| - | Perplexity | Toyota Prado shock absorber price Kenya | to test |
| - | Google AI Overview | car spare parts with countrywide delivery Kenya | to test |

## 7. Measurement

Track in GA4 (Analytics module already sends events): organic product views,
product search, WhatsApp clicks, calls, add to cart, begin checkout, purchase.
Baseline these the week 2.30.0 goes live; compare at 30/60/90 days. Separate
verified numbers from projections in every report.

# Handover - SEO / GEO / AEO programme (Oct 2026)

Read `AGENTS.md`, then `docs/SEO-STRATEGY-2026-10.md`, before continuing.

## Current state (19 Oct 2026, 23:15 EAT)

- 2.31.0 is LIVE (verified 19 Oct 2026 23:20 EAT: /llms.txt, make-hub and category FAQs render). **2.31.1 committed, not uploaded** (category label fix).
- Still stale on live: homepage shows the old 3-question FAQ (page cache) and /robots.txt shows the OLD rules blocking GPTBot/ClaudeBot (Cloudflare cache or a physical robots.txt in the web root).
- New module `inc/src/Seo/Answers.php`; edits to `Seo/Schema.php`,
  `Security.php` (robots.txt), `Customizer.php`, `Theme.php`,
  `front-page.php`, `template-parts/hub.php`.
- All changed PHP passed `php -l` (PHP 8.3) and the `\!` grep.

## Deploy

1. Zip `mk-auto-elite/` and upload via Appearance > Themes > Add New > Upload (replace current).
2. Purge Cloudflare cache and any WP cache plugin. Check `?nocache=1` variants.
3. Check: `/llms.txt` returns plain text; `/robots.txt` no longer blocks GPTBot/ClaudeBot and lists `sitemap_index.xml` (if it still shows the old rules, a physical robots.txt exists in the web root - edit or remove it); homepage shows 10 FAQs; `/product-category/suspension/` and `/brands/toyota/` show "Questions about these parts" under the grid on page 1 only; `/shop/?orderby=price` has `noindex, follow`.
4. Validate homepage, a category, a make hub and a product in Rich Results Test and validator.schema.org (no duplicate Organization / Product / FAQPage).
5. Customizer > MK Auto Elite > SEO & Local Business: confirm Delivery fee 500 and Free delivery from 10000 match the Shipping page; fill shop latitude/longitude.

## Rollback

Re-upload the 2.29.0 zip (git tag/commit before this one). No database changes
were made except the option `mkae_answers_ns` and `mkae_ans_*` transients,
which are harmless and expire in 12 h.

## Open items (owner)

- Cloudflare bot settings (AI crawlers currently challenged for non-browser clients).
- Search Console / Bing / GA4 access for baseline numbers.
- Google Business Profile consistency and reviews.
- Competitors named in the brief checked 20 Oct 2026 (see strategy section 2). Directory citations and listicle outreach are the next off-site actions.

## Log

- 20 Oct 2026 - 2.33.0 (committed, not uploaded): live price lists on hubs and categories, product Quick answers, IndexNow, entity sameAs / hasMap / paymentAccepted, richer llms.txt. New files `inc/src/Seo/PriceList.php`, `Seo/QuickAnswers.php`, `Seo/IndexNow.php`; edits to `Seo/Answers.php` (llms.txt), `Seo/Schema.php`, `Customizer.php`, `Theme.php`, `assets/css/main.css` + `main.min.css`, version files. `php -l` could NOT run (no PHP in the sandbox and no package access); files were checked by a bracket/quote balance script, the `\\!` grep and by eye. **Run `php -l` on the server before or right after upload.** Deploy: zip `mk-auto-elite/`, upload, purge Cloudflare; then check `/brands/toyota/` and `/product-category/suspension/` show the dated price table above the FAQ, a product shows Quick answers under the tabs, `/llms.txt` has the two new sections, and in Customizer > SEO & Local Business fill Google Business Profile URL (and LinkedIn under Footer & Social if one exists). IndexNow: if Rank Math > Instant Indexing is on, the theme stays silent (intended); otherwise open `/<key>.txt` (key in option `mkae_indexnow_key`) and confirm Bing Webmaster > IndexNow shows submissions after editing a product. Rollback: re-upload 2.32.1. DB items added: options `mkae_indexnow_key`, `mkae_indexnow_queue`, `mkae_indexnow_sent`, `mkae_indexnow_last`, transients `mkae_pl_*`, cron event `mkae_indexnow_flush` - all harmless if left.

- 20 Oct 2026 - MKauto Desk 1.7.1: subscribe pop-up is marked seen when it opens (no repeat on reload). Competitor check + AI/answer visibility tests logged in the strategy file. Meta-description import: product pages load; title on SZ7195 unchanged as intended; meta description tag could not be read by our fetch tool - owner to confirm via view-source.

- 20 Oct 2026 - 2.32.1: CDN cache headers on robots.txt / llms.txt. Verified: /robots.txt?nocache returns the new rules (so no physical file exists); the plain URL is a stale Cloudflare copy - purge it by URL. Product pilot (50) imported; checked product SZ72104 (Germax Discovery 4 hose): description, part details, short description render correctly.

- 19 Oct 2026 - 2.32.0 (committed, not uploaded): empty product image alt text falls back to the product name. Audit via the public Store API found every product description uses one boilerplate template ("The X is a quality new auto spare part ... your trusted supplier ... Order today via WhatsApp"), and catalogue images have alt="". Product copy rewrite needs a WooCommerce export CSV.

- 19 Oct 2026 - 2.31.1: natural category labels ("Brake Parts in Kenya", not "Brakes Parts").

- 19 Oct 2026 - 2.31.0: data-driven fallback intros on category/make/model archives with no description; Offer shippingRate (KES 500, free from 10,000) and delivery times in schema; AI crawlers named explicitly as allowed in robots.txt.

- 19 Oct 2026 - 2.30.0: AEO homepage FAQ (10), data-driven archive FAQs with live price ranges, product title/description fallbacks, facet + search noindex, llms.txt, AI crawlers allowed, Rank Math sitemap in robots.txt, schema enrichment (contactPoint, description, knowsAbout, OfferCatalog, GTIN, additionalProperty, ItemList).

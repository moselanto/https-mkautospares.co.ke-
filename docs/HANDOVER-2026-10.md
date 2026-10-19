# Handover - SEO / GEO / AEO programme (Oct 2026)

Read `AGENTS.md`, then `docs/SEO-STRATEGY-2026-10.md`, before continuing.

## Current state (19 Oct 2026, 23:15 EAT)

- Theme **2.31.0 committed, NOT uploaded yet** (includes 2.30.0). Live is 2.29.0.
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
- Competitors named in the brief (Nanak, Simco, Taisho, Motorshop) still to be checked manually in Google.

## Log

- 19 Oct 2026 - 2.31.0: data-driven fallback intros on category/make/model archives with no description; Offer shippingRate (KES 500, free from 10,000) and delivery times in schema; AI crawlers named explicitly as allowed in robots.txt.

- 19 Oct 2026 - 2.30.0: AEO homepage FAQ (10), data-driven archive FAQs with live price ranges, product title/description fallbacks, facet + search noindex, llms.txt, AI crawlers allowed, Rank Math sitemap in robots.txt, schema enrichment (contactPoint, description, knowsAbout, OfferCatalog, GTIN, additionalProperty, ItemList).

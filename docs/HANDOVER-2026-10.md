# Handover - SEO / GEO / AEO programme (Oct 2026)

Read `AGENTS.md`, then `docs/SEO-STRATEGY-2026-10.md`, before continuing.

## Current state (20 Oct 2026, 12:45 EAT) - programme phase 1 closed

**Live and verified on 20 Oct 2026** (web fetch of /brands/toyota/, /models/x-trail/, a product page, /, /robots.txt, /llms.txt):
- Theme **MK Auto Elite 2.33.0 + live-edit fixes** (commits 7c22201, f951941) uploaded by the owner. Dated price tables + 3 answers on make/model/category hubs, product "Quick answers" (price, fitment, OEM no., delivery), 10-question homepage FAQ, llms.txt with "How to cite us" and "Popular part searches", robots.txt allowing AI crawlers. No PHP errors seen.
- Live-edited in wp-admin theme editor and mirrored in repo (f951941): footer back-to-top `#`, `id="primary"` skip target in front-page.php, alt on WaOrder placeholder, Chat/Module.php.
- Homepage meta description (Rank Math) 154 chars; alt text added to 14 homepage images.
- 7 "price on request" products (IDs 30819, 30974, 31028, 31622, 31646, 30810, 30834): regular/sale price cleared. Search Console "Validate fix" started 20 Oct; 584 archive-schema items also under validation.
- **Caching:** server is **Apache + PHP-FPM 8.2** (cPanel/CloudLinux shared host, path /home/pcnlblkq/public_html, Imunify). LiteSpeed Cache 7.9.1 was active but useless on Apache -> **deactivated (not deleted)**. **WP Super Cache 3.1.4** installed: Simple mode, no cache for logged-in users or GET params, compression, 304, cache rebuild, clear on publish, 3600 s timeout, rejected URIs /cart/ /checkout/ /my-account/ /wp-json/ /wc-api/ ?add-to-cart=, rejected cookies woocommerce_items_in_cart, wp_woocommerce_session. Built-in cache test passed. External TTFB not yet re-measured (was ~1.5-2.0 s). OPcache disabled; DISABLE_WP_CRON on (check cPanel cron runs wp-cron.php); directory listing enabled on server.
- MKauto Desk **1.7.1** (pop-up seen-cookie fix, commit 19cffb5): owner upload not confirmed.

**Google / Bing (account mkautospareske@gmail.com):**
- Search Console: sitemap_index.xml Success, 4,404 discovered.
- Bing Webmaster: site imported, sitemap resubmitted. Rank Math Instant Indexing active (theme IndexNow pings auto-skip).
- **Bing Places:** GBP imported + verified 20 Oct, pending publish 7-12 days, weekly GBP sync on. Manage: https://www.bing.com/forbusiness/singleEntity?bizid=2d8ae32d-55a8-44c7-89ce-24227074493c
- GA4 G-HZ2W9TN2TH (property 547083524); key events phone_click, whatsapp_click, purchase. GTM-KP7NJH4K.
- GBP "MK Autospares Kenya": description (pending review) and in-store collection service mention "Kirinyaga Road, Nairobi (Glogon, behind Shell petrol station)". Structured address stays "Kirinyaga Road, Nairobi" (Google rejects landmark line without moving the pin - do not move it). One Update post submitted (pending). **Products not created** (Google requires a photo; browser upload of chat attachments fails). **Storefront photo not uploaded** for the same reason - owner adding from phone.
- **Confirmed address (owner, 20 Oct): Kirinyaga Road (Glogon, behind Shell).** Ignore the "Lower Kirinyaga Road, MK Building" wording on the sign.

**Client report:** "MK Auto Spares - Website and Search Work Report SEO, GEO & AEO.docx" (doc no. PMF-MKAS-RPT-2026-01, Pimofy Digital format) saved in the owner's Town library; sections 05 (starting figures) and 06 (next steps) are the baseline for the next review.

### Next agent: start here
1. Re-measure TTFB/PageSpeed on /, a hub and a product (cache should now hit). Re-run Rank Math SEO Analyzer (baseline 83/100).
2. After owner enables OPcache in cPanel, re-check Site Health.
3. Check Search Console validation of the 7 price errors and 584 archive items; Bing Places publish (~27 Oct-1 Nov).
4. GBP: create Products (Suspension, Engine, Brakes, Electrical, Toyota, Nissan -> category/brand URLs) once the owner provides photos; confirm storefront photo.
5. Citations with identical NAP: Yellow Pages Kenya, KenyaBizList, Apple Business Connect. Listicle outreach (victormatara.com, nyongesasande.com).
6. Late Nov 2026: compare the 50 rewritten pilot products vs the rest in Search Console; if positive, rewrite next 200 best sellers (needs WooCommerce export CSV). Re-run the visibility tests in SEO-STRATEGY section "AI answer tests" (MK not shown for Prado shock / Forester suspension queries).
7. Owner items: Customizer > SEO & Local Business GBP link (sameAs), Jetpack reconnect, host to disable directory listing and hide error log, Mashujaa Day hours.

Note: no PHP lint available in the agent sandbox on 20 Oct; 2.33.0 PHP was eye-checked only, then verified live with no errors.

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

- 20 Oct 2026 (midday) - 2.33.0 verified live. WP Super Cache replaced inactive-on-Apache LiteSpeed Cache. Bing Places imported. GBP description/service/post updated with confirmed address. Rank Math fixes (meta description, 14 alt texts, back-to-top/skip link). 7 price-on-request products cleared and GSC validation started. Client report PMF-MKAS-RPT-2026-01 produced.

- 20 Oct 2026 - 2.33.0 (committed, not uploaded): live price lists on hubs and categories, product Quick answers, IndexNow, entity sameAs / hasMap / paymentAccepted, richer llms.txt. New files `inc/src/Seo/PriceList.php`, `Seo/QuickAnswers.php`, `Seo/IndexNow.php`; edits to `Seo/Answers.php` (llms.txt), `Seo/Schema.php`, `Customizer.php`, `Theme.php`, `assets/css/main.css` + `main.min.css`, version files. `php -l` could NOT run (no PHP in the sandbox and no package access); files were checked by a bracket/quote balance script, the `\\!` grep and by eye. **Run `php -l` on the server before or right after upload.** Deploy: zip `mk-auto-elite/`, upload, purge Cloudflare; then check `/brands/toyota/` and `/product-category/suspension/` show the dated price table above the FAQ, a product shows Quick answers under the tabs, `/llms.txt` has the two new sections, and in Customizer > SEO & Local Business fill Google Business Profile URL (and LinkedIn under Footer & Social if one exists). IndexNow: if Rank Math > Instant Indexing is on, the theme stays silent (intended); otherwise open `/<key>.txt` (key in option `mkae_indexnow_key`) and confirm Bing Webmaster > IndexNow shows submissions after editing a product. Rollback: re-upload 2.32.1. DB items added: options `mkae_indexnow_key`, `mkae_indexnow_queue`, `mkae_indexnow_sent`, `mkae_indexnow_last`, transients `mkae_pl_*`, cron event `mkae_indexnow_flush` - all harmless if left.

- 20 Oct 2026 - MKauto Desk 1.7.1: subscribe pop-up is marked seen when it opens (no repeat on reload). Competitor check + AI/answer visibility tests logged in the strategy file. Meta-description import: product pages load; title on SZ7195 unchanged as intended; meta description tag could not be read by our fetch tool - owner to confirm via view-source.

- 20 Oct 2026 - 2.32.1: CDN cache headers on robots.txt / llms.txt. Verified: /robots.txt?nocache returns the new rules (so no physical file exists); the plain URL is a stale Cloudflare copy - purge it by URL. Product pilot (50) imported; checked product SZ72104 (Germax Discovery 4 hose): description, part details, short description render correctly.

- 19 Oct 2026 - 2.32.0 (committed, not uploaded): empty product image alt text falls back to the product name. Audit via the public Store API found every product description uses one boilerplate template ("The X is a quality new auto spare part ... your trusted supplier ... Order today via WhatsApp"), and catalogue images have alt="". Product copy rewrite needs a WooCommerce export CSV.

- 19 Oct 2026 - 2.31.1: natural category labels ("Brake Parts in Kenya", not "Brakes Parts").

- 19 Oct 2026 - 2.31.0: data-driven fallback intros on category/make/model archives with no description; Offer shippingRate (KES 500, free from 10,000) and delivery times in schema; AI crawlers named explicitly as allowed in robots.txt.

- 19 Oct 2026 - 2.30.0: AEO homepage FAQ (10), data-driven archive FAQs with live price ranges, product title/description fallbacks, facet + search noindex, llms.txt, AI crawlers allowed, Rank Math sitemap in robots.txt, schema enrichment (contactPoint, description, knowsAbout, OfferCatalog, GTIN, additionalProperty, ItemList).

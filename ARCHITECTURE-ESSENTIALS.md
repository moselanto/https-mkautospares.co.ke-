# Architecture essentials

One-page summary. Full detail in [ARCHITECTURE.md](ARCHITECTURE.md).

## Packages

| Package | Type | Version | Owns |
| --- | --- | :---: | --- |
| `mk-auto-elite/` | Theme (active) | 2.19.0 | Storefront, design system, fitment engine and tools, WooCommerce integration, Knowledge Centre, locations, fitment landing pages, booking, chat, schema, analytics, front-end hardening, setup wizard |
| `mk-auto-core/` | Plugin | 1.0.0 | Branded staff sign-in screen, login guard |
| `mk-desk/` | Plugin | 1.6.1 | MKauto Desk back office: POS with neighbour sourcing and supplier ledger, WhatsApp cart orders (plus theme leads), payment plans, garage and service bookings, reminders, follow-ups, marketing, social feeds, team permissions and activity log, encrypted contacts, Excel reports |

## Boot

- Theme: `functions.php` > PHP 8.1 guard > constants > `inc/autoload.php` (PSR-4 `MKAutoElite\` > `inc/src/`) > `Theme::instance()` creates every module. Admin-only modules: `Admin\PluginActivation`, `Admin\Tools`, `Admin\SetupWizard`.
- Plugin: `mk-desk/mk-desk.php` > on `plugins_loaded` (20, WooCommerce required) requires `includes/class-mkd-*.php` > `MKD_WA_Order`, `MKD_Desk` (registers every Desk module), `MKD_Followup`.
- Plugin: `mk-auto-core.php` > requires `includes/` > on `plugins_loaded` creates `MKAC_Login_Brand` and `MKAC_Login_Guard`.

## Data

| Data | Where |
| --- | --- |
| Settings | Theme mods `mkae_*` (Customizer), read via `Customizer::get()` / `MKAC_Brand::get()` |
| Products, orders | WooCommerce (HPOS compatible) |
| Vehicle fitment | Taxonomies `mk_make`, `mk_model`, `mk_year`, `mk_engine`, `mk_fuel`, `mk_transmission`; product meta `_mk_*` synced into them |
| Knowledge Centre | CPT `mkae_guide` (`/knowledge/`), taxonomy `mkae_guide_topic` |
| Locations | CPT location (`/spare-parts/...`) |
| Fitment landing pages | CPT `mkae_landing` (`/fitment/...`), meta `_mkae_l_*` |
| Bookings | CPT `mkae_booking`, meta `_mkae_*` |
| Desk records | Tables `{prefix}mkd_lmm_plans`, `mkd_lmm_payments`, `mkd_appointments` (with vehicle, plate, mileage), `mkd_sourcing` (neighbour-sourced lines, supplier owed/paid), marketing and activity tables; CPT `mkd_wa_order` (cart orders); POS sales are WooCommerce orders (`created_via` `mkd_pos`) |
| Saved vehicle | Cookie `mkae_vehicle` |
| Caches, rate limits, lockouts | Transients (`mkae_*`, `mkac_lg_*`) |

## Request surfaces

- AJAX (`admin-ajax.php`, nonce `mkae_nonce`): `mkae_get_terms`, `mkae_search_suggest`, `mkae_vehicle_parts`, `mkae_check_fit`, `mkae_submit_booking`, `mkae_chat`.
- Admin posts: setup wizard tools and `mkae_generate_landings` (capability + nonce).
- External: Google Gemini (server-side), WhatsApp links, GA4 / Meta Pixel (only when IDs set), Google Maps embed.

## SEO

Rank Math (live, outside this repo) owns titles, meta, canonicals, sitemaps and base schema. `Seo\Schema` enriches Rank Math's entities and prints only missing types (FAQ, Article); `Seo\Meta` supplies title/description fallbacks and noindexes empty fitment/location archives. One entity of each type per page.

## Security layers

1. Theme `Security`: XML-RPC off, author/REST-user enumeration blocked, head clean-up, generic login errors, front-end headers, comments only on products, robots.txt.
2. Theme `Ajax\Handlers`: nonces, sanitisation, honeypot + timing + rate limits on bookings, chat throttle.
3. Plugin `MKAC_Login_Guard`: lockout, bot traps, registration/reset limits, password policy, staff session expiry, login/admin headers, app passwords for admins.

## Release

Commit to `main` > bump versions + changelog + README > upload changed folder to hosting > clear cache > check versions > smoke test on a phone.

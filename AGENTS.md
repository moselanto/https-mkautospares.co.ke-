# AGENTS.md

Instructions for AI coding agents and new contributors working in this
repository. Read this before writing any code here.

Start with [`ARCHITECTURE-ESSENTIALS.md`](ARCHITECTURE-ESSENTIALS.md) to find the
right file, [`ARCHITECTURE.md`](ARCHITECTURE.md) before changing how something
works, and [`SECURITY-AUDIT.md`](SECURITY-AUDIT.md) before touching forms,
AJAX, authentication, headers or anything holding customer data.

---

## 1. What this repository is

The first-party WordPress code for **MK Auto Spares** (<https://mkautospares.co.ke/>),
a car, truck and motorcycle spare-parts shop on Kirinyaga Road, Nairobi, selling
online through WooCommerce with WhatsApp and M-Pesa ordering.

Two packages:

```
mk-auto-elite/   Theme   - storefront, fitment engine, WooCommerce, content, SEO
mk-auto-core/    Plugin  - staff sign-in screen and login guard
mk-desk/         Plugin  - MKauto Desk back office (POS, sourcing, orders, plans, garage)
```

WordPress core, WooCommerce, third-party plugins, uploads and the database are
**not** in this repository. Do not add them.

## 2. The rules that are not negotiable

1. **Commit directly to `main`.** No feature branches, no pull requests.
2. **Every change keeps every related file current in the same commit:** the
   version bump (theme: `style.css` header and `MK_AUTO_ELITE_VERSION` in
   `functions.php`; plugin: header and `MKAC_VERSION` in `mk-auto-core.php`;
   desk: header and `MKD_VERSION` in `mk-desk/mk-desk.php`),
   the package `readme.txt` changelog, the README badges, Security line and
   Release history table, and any doc in this list whose subject changed.
3. **Never hardcode editable content.** Phone, WhatsApp, email, address, hours,
   M-Pesa instruction, accent colour and IDs live in Customizer theme mods
   `mkae_*`. Read them with `MKAutoElite\Customizer::get()` in the theme and
   `MKAC_Brand::get()` in the plugin. (Known exception: the Paybill numbers in
   `Woo\WooCommerce::checkout_paybill_notice()` - see section 5.)
4. **Sanitise on input, escape on output. Every time.**
5. **Every AJAX endpoint checks the `mkae_nonce` nonce** and sanitises every
   field. Public write endpoints also need a honeypot and a per-IP rate limit
   (copy `submit_booking`).
6. **Never weaken a security control to make something work.** Read
   `SECURITY-AUDIT.md` first, then solve it another way or raise it explicitly.
7. **Never put an API key in the browser.** The Gemini key stays server-side;
   the chat is proxied through `Ajax\Handlers::chat()`.
8. **The sign-in screen changes presentation only.** Do not rename login
   fields, remove nonces or change redirects in `MKAC_Login_Brand`.

## 3. Coding standards

- WordPress Coding Standards for PHP. Tabs, not spaces.
- PHP 8.1+ and WordPress 6.4+ are the floor (declared in both package headers).
- Every PHP file has a docblock with `@package` (`MK_Auto_Elite` or
  `MK_Auto_Core`) and an `ABSPATH` guard.
- Theme classes live in the `MKAutoElite\` namespace under `inc/src/` and are
  autoloaded (PSR-4). A new module is a new class, instantiated in
  `Theme::__construct()`. Constructors only register hooks.
- Prefixes: theme `mkae_` (hooks, options, AJAX actions, CPTs), fitment
  taxonomies `mk_`, product meta `_mk_`, booking/landing meta `_mkae_`;
  plugin `mkac_` / `MKAC_`.
- Text domains: `mk-auto-elite` and `mk-auto-core`.
- Use `wp_safe_redirect()`, never `wp_redirect()`.
- Comment the **why**, not the what.

## 4. Where to make a change

| Task | File |
| --- | --- |
| Contact details, brand, Customizer setting | `mk-auto-elite/inc/src/Customizer.php` (add to `$defaults` and the field list) |
| Theme supports, menus, image sizes, `[mkae_*]` shortcodes | `inc/src/Setup.php` |
| CSS / JS loading, preloads, critical CSS | `inc/src/Assets.php` |
| Styles | `assets/css/main.css` then regenerate `main.min.css` |
| Scripts | `assets/js/*.js` then regenerate the matching `.min.js` |
| Fitment taxonomies, saved vehicle, finder UI | `inc/src/Vehicle/Finder.php`, `assets/js/vehicle-finder.js` |
| Parts Finder, Symptom wizard, fit checker | `inc/src/Fitment/Tools.php`, `assets/js/fitment-tools.js` |
| Any AJAX endpoint | `inc/src/Ajax/Handlers.php` |
| WooCommerce behaviour, checkout, WhatsApp button, price on request | `inc/src/Woo/WooCommerce.php` |
| Knowledge Centre and Locations CPTs | `inc/src/Content.php` |
| Fitment landing pages | `inc/src/Landing.php`, `single-mkae_landing.php` |
| Service bookings | `inc/src/Booking/Module.php`, `assets/js/booking.js` |
| AI chat | `inc/src/Chat/Module.php` (UI), `Ajax/Handlers.php` (model call) |
| Structured data | `inc/src/Seo/Schema.php` - enrich Rank Math's entities via `rank_math/json_ld`; never print a second Organization, WebSite, Product or BreadcrumbList |
| Title / description / robots fallbacks | `inc/src/Seo/Meta.php` - respect any Rank Math override set on the page or term |
| GA4 / Meta Pixel | `inc/src/Analytics.php` |
| Front-end hardening, robots.txt | `inc/src/Security.php` |
| Setup wizard, starter pages / articles / menus | `inc/src/Admin/*.php`, `inc/content/` |
| Sign-in screen look | `mk-auto-core/includes/class-mkac-login-brand.php`, `assets/css/login.css` |
| MKauto Desk shell, menu, settings, exports | `mk-desk/includes/class-mkd-desk.php` |
| Till, invoices, neighbour-sourced lines | `mk-desk/includes/class-mkd-desk-pos.php`, `class-mkd-desk-source.php`, `assets/js/mk-desk.js` |
| Garage and service bookings | `mk-desk/includes/class-mkd-desk-appts.php` |
| Lockout, password policy, sessions | `mk-auto-core/includes/class-mkac-login-guard.php` |

`functions.php` stays thin: environment guard, constants, autoloader, boot.

## 5. Gotchas that will catch you out

- **PHP escaping bug.** Writing PHP through Python or shell heredocs has turned
  `!` into `\!` more than once and broke the live soundcreationsltd.com site
  (parse error). Before committing or zipping any PHP run
  `grep -rn '\\!' <changed files>` and fix every hit.
- **There is no `php -l` in the agent sandbox.** Re-read every changed PHP file
  by eye; a fatal error takes the store down.
- **Minified assets are committed.** Edit the source file and regenerate the
  `.min` file, or the change will not ship.
- **Paybill numbers are duplicated.** `mkae_mpesa` in the Customizer drives the
  site copy, but the checkout notice in `Woo\WooCommerce` hard-codes 529914 /
  4268. If the Paybill changes, change both (and fix the debt if you can).
- **The theme genericises every login error** (`Security::generic_login_error`).
  MK Auto Core re-applies only its own lockout message at priority 99. Keep it
  that way: do not reveal whether a username exists.
- **Fitment terms are cached in transients.** After bulk product imports run
  *Fitment sync* in the setup wizard; the cache namespace is bumped there.
- **Landing generator creates drafts.** Up to 120 per run, never published
  automatically - leave it that way so thin pages never go live.
- **`robots.txt` is theme-served** only when no physical file exists in the web
  root.

## 6. Verifying your work

No test suite and no CI exist yet (see `SECURITY-AUDIT.md`). Verify manually:

- `grep -rn '\\!'` on changed PHP; read the diff.
- Load the homepage, a product, the shop and the Knowledge Centre on mobile and desktop.
- Run the vehicle finder end to end and the fit checker on a product.
- Submit a booking (success, and with a required field empty).
- Sign in at `/wp-admin` in a private window; check the lost-password screen too.
- After any CSS/JS change confirm the version moved and the new file is served.

## 7. Commits

- Imperative subject under ~70 characters.
- Body explains why.
- Never commit credentials, API keys, database dumps, uploads or `wp-config.php`.

# Security audit - mkautospares.co.ke

| | |
| --- | --- |
| **Scope** | `mk-auto-elite` theme 2.28.0, `mk-auto-core` plugin 1.1.0, `mk-desk` plugin 1.6.3 |
| **Date** | 17 Oct 2026 (supersedes the 14 Oct 2026 review) |
| **Method** | Manual review of every PHP file and front-end script, plus automated searches for raw SQL, unescaped output, dangerous functions, uploads, redirects, remote calls, REST routes and hard-coded secrets. All PHP passes `php -l`; all Desk JavaScript passes a parse check |
| **Out of scope** | WordPress core, WooCommerce, third-party plugins, hosting, DNS, Cloudflare and server configuration |

## 1. Summary

No critical or high-severity vulnerability was found in the current code. Every
staff action checks both the user's capability and a security token (nonce),
database queries with user input are prepared, output is escaped, there is no
`eval`, `unserialize`, shell call or open redirect, and no API key is committed.

This review closed the remaining medium and low gaps: rate limits that a fake
proxy header could bypass, an API key that travelled in a URL, a public REST
route with no rate limit and open CORS, missing HTTPS redirect and front-end
HSTS, no idle timeout for staff sessions, and Desk screens that browsers could
cache. The payment safeguards added in Desk 1.4.0 to 1.6.2 are recorded here too.

## 2. Requested controls - status

| Control | Status | Where |
| --- | --- | --- |
| **Rate limiting** | In place on every public write form and lookup; login, registration and password reset limited | `MKAC_Login_Guard`, `Ajax\Handlers::guard()`, `MKD_Desk::throttled()`, each public form |
| **Input sanitisation** | Every field sanitised by type (`sanitize_text_field`, `sanitize_email`, `absint`, allow-lists, length caps); output escaped at the point of output | All packages |
| **Password hashing** | Handled by WordPress core (bcrypt on WP 6.8+, phpass before). The code never stores, logs or e-mails a password. 10-character policy, not the username or e-mail | `MKAC_Login_Guard` |
| **Environment variables, no hard-coded keys** | WhatsApp token, Gemini key and data-encryption key read from the server environment or `wp-config.php` constants. No secrets in the repository | `MKD_WhatsApp`, `Security::secret()`, `MKD_Vault` |
| **CORS** | WordPress reflects any Origin on REST responses; the Desk's `mkd/v1` routes now send no CORS permission to other sites | `MKD_Desk_Appts::cors()` |
| **SQL / NoSQL injection** | All queries with input use `$wpdb->prepare()` or WooCommerce APIs. Remaining interpolation is fixed table names only. There is no NoSQL store | All packages |
| **Session expiry** | Staff: 8 h (3 days with Remember me) plus a new 60-minute idle logout. Customers: WordPress defaults | `MKAC_Login_Guard::session_length()`, `idle_logout()` |
| **HTTPS everywhere** | New 301 redirect from http to https on the front end when the site address is https; HSTS on front end, login and admin | `Security::force_https()`, both header hooks |
| **Security headers** | nosniff, X-Frame-Options SAMEORIGIN, Referrer-Policy, Permissions-Policy, X-Permitted-Cross-Domain-Policies, Cross-Origin-Opener-Policy (front end), HSTS on HTTPS; `no-store` on Desk screens and invoice links | `Security`, `MKAC_Login_Guard`, `MKD_Desk::no_store()` |
| **Regular dependency updates** | The code has no Composer or npm dependencies and bundles no third-party JavaScript library. Risk sits in WordPress, WooCommerce and plugins - see section 6 | Hosting routine |

## 3. Findings and fixes in this review

| # | Severity | Finding | Fix |
| --- | --- | --- | --- |
| S1 | Medium | Theme WhatsApp-order rate limit trusted the `CF-Connecting-IP` header from anyone, so a bot could send a fake value and never be limited | Uses `Security::client_ip()`: `REMOTE_ADDR`, or the proxy header only when `MKAC_TRUSTED_PROXY_HEADER` is defined |
| S2 | Medium | Booking, chat and Desk public-form limits used `REMOTE_ADDR` directly, so behind Cloudflare every visitor shared one limit | All use the trusted-proxy helper (`Security::client_ip()`, `MKD_Desk::ip()`) |
| S3 | Medium | Gemini API key only stored in the database and sent in the request URL (visible in proxy / server logs) | Read from `GEMINI_API_KEY` / `MKAE_GEMINI_API_KEY` first; sent in the `x-goog-api-key` header |
| S4 | Low | Public REST route `mkd/v1/slots` had no rate limit | 120 requests per 5 minutes per IP; `date` argument declared and sanitised |
| S5 | Low | WordPress reflected any Origin with CORS headers on Desk REST routes | CORS headers removed for other origins on `mkd/v1` |
| S6 | Low | Public theme lookups (search, fitment, terms) could be scraped without limit | 300 requests per 5 minutes per IP in the shared AJAX guard |
| S7 | Low | No HTTP-to-HTTPS redirect or HSTS on front-end pages | `force_https()` redirect and HSTS added |
| S8 | Low | Staff sessions had no idle timeout; an unattended counter PC stayed logged in for 8 hours | 60-minute idle logout (filter `mkac_staff_idle`); the background heartbeat does not count |
| S9 | Low | Desk screens with customer data could be kept in browser or proxy caches | `Cache-Control: no-store` on all Desk screens |
| S10 | Info | Missing `Permissions-Policy` / `X-Permitted-Cross-Domain-Policies` on login and admin | Added |

### Business-logic safeguards (Desk 1.4.0 to 1.6.2)

| Risk | Control |
| --- | --- |
| Same M-Pesa / transaction code used twice | One code, one use across till sales, invoices, plans, WhatsApp and website orders; spaces, dashes and capitals ignored; database lock against simultaneous saves; duplicate website payments put on hold |
| Staff changing prices or over-discounting | Price lock without permission; discount ceiling per role; every attempt logged |
| Marking an invoice paid without payment | "Fulfilled" only on paid website orders; no re-marking paid; payment method recorded |
| Deleting evidence of a sale | Sales are voided, not deleted: reason required, owner-only by default, stock restored, logged |
| Double charging | A WhatsApp order cannot be rung up twice; garage jobs warn before a second invoice |
| Double booking | One open booking per phone per day; database lock on the last slot; past / invented times refused |
| Customer data at rest | Phone and e-mail in the marketing table encrypted with AES-256-GCM (`MKD_Vault`); blind index for search |
| Invoice links | Public links require the WooCommerce order key (`hash_equals`), are `noindex` and not cached |

## 4. Vulnerability classes checked

| Class | Result |
| --- | --- |
| SQL injection | No user input reaches SQL without `prepare()` |
| XSS | Output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`); JSON-LD encoded with `JSON_HEX_TAG`; no raw superglobal echoed |
| CSRF | Every admin form and AJAX action verifies a nonce; public forms on cached pages use honeypot, fill-time and rate limits instead |
| Broken authentication | Lockout, bot traps, password policy, generic login errors, session and idle expiry, application passwords for admins only |
| Insecure file upload | No visitor upload. The only upload writes images bundled with the theme, admin only (capability + nonce) |
| Unauthorised access | Desk pages and actions require `manage_woocommerce`; sensitive actions (void, refunds, deletes, settings, encryption) require the owner or an explicit permission |
| Session hijacking | WordPress HttpOnly auth cookies, Secure on HTTPS; HSTS; short staff sessions; idle logout |
| API vulnerabilities | REST route read-only, rate-limited, no CORS for other sites; AJAX nonce-checked and rate-limited; keys server-side only |
| Data exposure | User REST endpoint hidden from guests; author enumeration blocked; Desk screens `no-store`; exports nonce + capability checked; personal data encrypted at rest |

## 5. Still open (by design or needs hosting)

| # | Item | Recommendation |
| --- | --- | --- |
| O1 | No Content-Security-Policy | Needs an inventory of GA4, Meta Pixel, Maps, Gemini and payment origins; start in report-only mode |
| O2 | Paybill numbers hard-coded in the checkout notice | Integrity issue, not security; move to the Customizer |
| O3 | No automated tests or CI | Add `php -l` and PHPCS (WordPress ruleset) on every push |
| O4 | Two-factor login for administrators | Install a 2FA plugin compatible with the branded sign-in |

## 6. Hosting configuration checklist

```php
// wp-config.php
define( 'FORCE_SSL_ADMIN', true );
define( 'DISALLOW_FILE_EDIT', true );
define( 'MKAC_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' ); // only if behind Cloudflare
define( 'MKD_DATA_KEY', '...64 random hex characters...' );     // Desk encryption key
define( 'WHATSAPP_TOKEN', '...' );                               // or set as an environment variable
define( 'WHATSAPP_PHONE_NUMBER_ID', '...' );
define( 'GEMINI_API_KEY', '...' );
```

- [ ] Valid SSL certificate; site and home URLs start with https
- [ ] Unique strong passwords; remove unused administrator accounts; one login per staff member
- [ ] Daily off-site backups of database and uploads; test a restore
- [ ] Update WordPress, WooCommerce, plugins and PHP monthly (security releases at once); remove unused plugins and themes
- [ ] Keep `wp-config.php` and backups out of the web root and out of Git

## 7. Release checklist

- [ ] `php -l` on every changed PHP file; parse-check changed JavaScript
- [ ] New AJAX / admin actions: capability + nonce; every field sanitised
- [ ] New public write endpoints: honeypot + rate limit via the trusted-IP helper
- [ ] Every output escaped where it is printed
- [ ] No keys, passwords or customer data committed
- [ ] This document updated when a control changes

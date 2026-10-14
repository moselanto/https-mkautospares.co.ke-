# Security audit - mkautospares.co.ke

| | |
| --- | --- |
| **Scope** | `mk-auto-elite` theme 2.18.0, `mk-auto-core` plugin 1.0.0 |
| **Date** | 14 Oct 2026 |
| **Method** | Manual code review of every PHP file and front-end script in this repository |
| **Out of scope** | WordPress core, WooCommerce, third-party plugins, hosting and server configuration |

## 1. Summary

The theme was already in reasonable shape: AJAX endpoints use a shared nonce
and sanitise input, the booking form has a honeypot, timing check and per-IP
rate limit, the Gemini key is never sent to the browser, and the front end
blocks XML-RPC and user enumeration. The biggest gap was the staff login,
which had no brute-force protection, no password policy, no session limit and
no security headers on `wp-login.php` / `wp-admin`. MK Auto Core 1.0.0 closes it.

## 2. Controls in place

### Theme (`MKAutoElite\Security`)

| Control | Detail |
| --- | --- |
| XML-RPC | `xmlrpc_enabled` false, pingback methods removed, direct `xmlrpc.php` requests get 403 |
| User enumeration | `?author=N` and author archives 301 home for guests; users removed from sitemap; `/wp/v2/users` hidden from guests |
| Information leakage | Generator, RSD, WLW, shortlink, REST and oEmbed discovery links removed |
| Login errors | One generic message; never reveals whether a username exists |
| Headers (front end) | `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` |
| Comment spam | Comments only on products (reviews); pings off |
| robots.txt | AI/scraper bots blocked; cart, checkout, account and faceted URLs disallowed |

### Theme (`Ajax\Handlers`, admin tools)

| Control | Detail |
| --- | --- |
| Nonces | Every AJAX action checks `mkae_nonce`; admin tools check capability + action nonce |
| Input | `sanitize_text_field`, `sanitize_email`, `absint`, taxonomy allow-list |
| Booking abuse | Honeypot `mkae_hp`, minimum fill time, 8 submissions per IP per hour |
| Chat abuse / cost | Per-IP throttle per 5 minutes; key stored server-side; model filterable |
| Landing generator | Creates drafts only, capped at 120 per run |
| Analytics | Nothing loads until an ID is entered |

### Plugin (`MKAC_Login_Guard`) - new in 1.0.0

| Control | Detail |
| --- | --- |
| Brute-force lockout | 5 failures per IP in 15 min, or 10 per username in 1 hour, lock for 30 min. Filters `mkac_login_ip_max`, `mkac_login_user_max` |
| Lockout notice | Shown even though the theme genericises errors, only when the request was refused for a lockout |
| Bot traps | Honeypot `mkac_hp` + minimum fill time on wp-login, register, lost password and WooCommerce account forms |
| Rate limits | 3 registrations and 5 password resets per IP per hour |
| Password policy | 10+ characters, not the username or email, on reset, profile and My Account |
| Staff sessions | `edit_posts` and up: 8 h, or 3 days with Remember me. Customers unchanged |
| Headers (login/admin) | nosniff, SAMEORIGIN, Referrer-Policy, HSTS on HTTPS |
| Application passwords | Administrators only |
| Sign-in screen | Presentation only: no field, nonce or redirect changes; outputs escaped |

## 3. Findings

| # | Severity | Finding | Status |
| --- | --- | --- | --- |
| F1 | High | No brute-force protection on staff login | **Fixed** - MK Auto Core lockout |
| F2 | Medium | No bot protection on login / registration / reset | **Fixed** - honeypot + timing |
| F3 | Medium | No minimum password length beyond WordPress's strength meter | **Fixed** - 10-character policy |
| F4 | Medium | Long-lived staff sessions (14 days) | **Fixed** - 8 h / 3 days |
| F5 | Low | No security headers on login and admin screens | **Fixed** |
| F6 | Low | Application passwords available to every role | **Fixed** - admins only |
| F7 | Medium | Rate limits key on `REMOTE_ADDR`; behind Cloudflare every visitor shares an IP | **Mitigated** - define `MKAC_TRUSTED_PROXY_HEADER`. Theme booking/chat limits still use `REMOTE_ADDR` - open |
| F8 | Low | No HSTS on front-end responses from the theme | Open - set at host / Cloudflare or add to `Security::security_headers()` |
| F9 | Low | No Content-Security-Policy | Open - needs an inventory of GA4, Pixel, Maps and Gemini origins first |
| F10 | Low | Paybill numbers hard-coded in the checkout notice | Open - integrity rather than security; move to Customizer |
| F11 | Info | No automated tests, lint or CI | Open |

## 4. Configuration checklist (hosting)

- [ ] HTTPS everywhere; `define( 'FORCE_SSL_ADMIN', true );`
- [ ] Behind Cloudflare: `define( 'MKAC_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );`
- [ ] `define( 'DISALLOW_FILE_EDIT', true );`
- [ ] Unique, strong passwords for every admin; remove unused admin accounts
- [ ] 2FA plugin for administrators (compatible with the branded sign-in)
- [ ] Daily off-site backups of database and uploads
- [ ] WordPress, WooCommerce and plugins kept updated

## 5. Maintenance checklist (every release)

- [ ] `grep -rn '\\!'` on changed PHP (escaping bug that broke a live site before)
- [ ] Every new AJAX action checks `mkae_nonce` and sanitises input
- [ ] Every new public write endpoint has honeypot + rate limit
- [ ] Every output escaped at the point of output
- [ ] No keys or secrets committed
- [ ] Sign in at `/wp-admin` in a private window after deploying
- [ ] This document updated if a control changed

# Product requirements - mkautospares.co.ke

## 1. Summary

MK Auto Spares sells car, truck and motorcycle spare parts from Kirinyaga Road,
Nairobi, with countrywide delivery. The website must let a customer find a part
that fits their vehicle quickly, trust that it is the right part, and order in
the way Kenyans prefer: WhatsApp, phone or M-Pesa.

## 2. Goals

| Goal | Measure |
| --- | --- |
| Find the right part fast | Vehicle to matching parts in under 10 seconds on a phone |
| Reduce wrong-part returns | Fit checker and compatibility tab on every product |
| Convert on mobile | One-tap WhatsApp order and Paybill steps at checkout |
| Rank for Kenyan part searches | Knowledge Centre, location and fitment pages with schema |
| Run the shop without a developer | All contact, brand and tracking values editable in the Customizer |
| Professional, safe staff access | Branded sign-in screen and login hardening |

## 3. Users

- **Drivers / owners** - know their car, not always the part name.
- **Mechanics and garages** - know the part or OEM number, want price and stock fast.
- **Store staff** - manage products, fitment data, orders, bookings and guides.

## 4. Requirements and status

| # | Requirement | Status |
| --- | --- | --- |
| R1 | Vehicle finder: Make > Model > Year > Engine > Fuel > Transmission, only valid combinations | Done |
| R2 | Predictive search by name, SKU, OEM, vehicle | Done |
| R3 | Product fit checker and compatibility tab | Done |
| R4 | Parts Finder and Symptom > Part wizard | Done |
| R5 | WhatsApp ordering and "Request price" for unpriced items | Done |
| R6 | Kenyan checkout: phone required, no postcode, M-Pesa Paybill steps | Done |
| R7 | Knowledge Centre with starter guides | Done (15 guides) |
| R8 | Location pages and fitment landing pages (drafts, human-reviewed) | Done |
| R9 | Service booking with email to shop and customer | Done |
| R10 | AI chat (Gemini) with WhatsApp hand-off; key server-side | Done |
| R11 | JSON-LD for Store, Product, Breadcrumb, FAQ, WebSite search | Done |
| R12 | GA4 and Meta Pixel with store events, off by default | Done |
| R13 | Setup wizard: plugins, fitment sync, pages, articles, menus | Done |
| R14 | Front-end hardening and robots.txt | Done |
| R15 | Branded staff sign-in screen in MK colours with logo, WhatsApp help, secure note | Done - MK Auto Core 1.0.0 |
| R16 | Login lockout, bot traps, password policy, staff session expiry | Done - MK Auto Core 1.0.0 |
| R17 | Documentation set (README, AGENTS, architecture, PRD, security audit) | Done |
| R18 | One schema entity per type per page (no Rank Math duplicates); automotive Product data (part brand, MPN, condition, compatible vehicles, shipping, returns) | Done - theme 2.19.0, verify live |
| R19 | Meaningful titles/descriptions on shop, categories, vehicle archives and guides; thin archives noindexed | Done - theme 2.19.0, verify live |
| R20 | Product categories in the XML sitemap | Open - Rank Math setting |

## 5. Non-functional requirements

- PHP 8.1+, WordPress 6.4+, WooCommerce 8.0+ (HPOS compatible).
- Mobile first; Core Web Vitals-oriented loading.
- Accessible: labelled forms, visible focus, reduced-motion respected.
- No secrets in the browser; every AJAX call nonce-checked.
- Every editable business value in the Customizer.

## 6. Next

| Priority | Item |
| --- | --- |
| High | Move CPTs, fitment taxonomies, booking, chat and schema from the theme into MK Auto Core so a re-theme cannot drop data features |
| High | Read the checkout Paybill numbers from the Customizer instead of hard-coding them |
| Medium | M-Pesa STK push (Daraja) or a Kenyan gateway for card + M-Pesa at checkout |
| Medium | Off-site listings: Google Business Profile, Bing Places, Apple Business Connect, Kenyan directories |
| Medium | Publish fitment landing pages in waves for top models (Toyota, Nissan, Subaru) |
| Low | Basic CI: `php -l` + PHPCS (WordPress ruleset) |

## 7. Out of scope / rejected

- Page builders - heavy and fragile; templates are hand-built.
- Bundling WooCommerce or third-party plugins in this repo.
- Exposing the Gemini key client-side.

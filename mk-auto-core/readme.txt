=== MK Auto Core ===
Contributors: pimofydigital
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site-owned features for MK Auto Spares (mkautospares.co.ke) that must survive a theme change.

== Description ==

* Branded staff sign-in screen at /wp-admin: split layout with a dark brand panel, the MK logo, "Welcome back" heading, secure-connection note and WhatsApp help link. Contact details come from Appearance > Customize > MK Auto Elite.
* Login guard: lockout after repeated failures (per IP and per username), bot traps on login / registration / lost password, registration and reset rate limits, 10-character minimum passwords, 8-hour staff sessions (3 days with Remember me), security headers on login and admin, application passwords for administrators only.

== Installation ==

1. Upload the mk-auto-core folder to wp-content/plugins/ (or upload mk-auto-core.zip under Plugins > Add New > Upload Plugin).
2. Activate MK Auto Core.
3. Open /wp-admin in a private window to see the new sign-in screen.
4. Behind Cloudflare? Add define( 'MKAC_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' ); to wp-config.php.

== Changelog ==

= 1.0.0 =
* First release: branded sign-in screen and login guard.

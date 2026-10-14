# Self-hosted fonts

Drop these two variable WOFF2 files into this folder for fully self-hosted,
performance-optimised typography:

- `inter-variable.woff2` - Inter (SIL Open Font License 1.1)
- `sora-variable.woff2` - Sora (SIL Open Font License 1.1)

The theme's `assets/css/main.css` already declares `@font-face` rules pointing
here, and `Assets::resource_hints()` preloads them automatically when present.

If the files are absent, the theme falls back gracefully to a high-quality
system font stack (system-ui / -apple-system / Segoe UI / Roboto), so nothing
breaks - you just get the platform default until you add the WOFF2 files.

Where to get them:
- Inter: https://github.com/rsms/inter (export/subset the variable WOFF2)
- Sora: https://fonts.google.com/specimen/Sora (self-host the variable WOFF2)

Tip: subset to Latin + Latin-Extended and only the weights you use (400-700)
to keep each file small for best Core Web Vitals.

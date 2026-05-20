# Sego Lily Routine Quiz

Sego Lily's skincare routine quiz for segolilyskincare.com. Self-contained WordPress plugin — no external dependencies.

Forked from the [LP Quiz Suite](https://github.com/louievillaverde/lp-quiz-suite) canonical quiz reference. Branded for Sego Lily, customized with Holly's product line + voice.

## What this plugin does

- 5-question quiz: name, skin concern, product count, frustration, email
- Renders inline 2-product recommendation on the same page after submission
- Syncs lead to Mautic with tags (`quiz-completed`, `retail-quiz-lead`, plus matching skin concern + frustration tags)
- Auto-creates `/your-routine` page on activation with the quiz embedded
- "Build Your Sego Lily Routine" heading + Holly sign-off on results
- Self-updates from this repo's GitHub releases

## Install

1. Download `sego-lily-routine-quiz.zip` from the [latest release](https://github.com/louievillaverde/sego-lily-routine-quiz/releases/latest)
2. WP Admin → Plugins → Add New → Upload Plugin → upload the zip → Activate
3. Settings → Sego Lily Routine Quiz → confirm Mautic credentials (auto-detected from `sego-lily-wholesale` plugin if installed, otherwise enter manually)
4. `/your-routine` is automatically created and published

## Smoke test after activation

Incognito → `segolilyskincare.com/your-routine` → walk the 5 questions with a test email → verify:
- Inline 2-product result renders with Holly sign-off
- "Shop →" links point at real WooCommerce product pages
- Mautic admin shows new contact with tags: `quiz-completed`, `retail-quiz-lead`, matching skin concern + frustration

## Release process

```sh
# 1. Bump version in sego-lily-routine-quiz.php (Plugin header + SLRQ_VERSION constant)
git push origin main
git tag -a vX.Y.Z -m "vX.Y.Z: summary"
git push origin vX.Y.Z

# 2. Build the zip
bin/build-release.sh

# 3. Create the GitHub release with zip attached
gh release create vX.Y.Z dist/sego-lily-routine-quiz.zip --title "vX.Y.Z" --notes "..."
```

The plugin's self-updater hits `/releases/latest` every 12 hours and pushes updates through WP admin → Plugins → Update.

## Relationship to LP Quiz Suite

[LP Quiz Suite](https://github.com/louievillaverde/lp-quiz-suite) is Lead Piranha's canonical quiz reference, holding the spec + implementations across platforms (WordPress today; Framer / coded sites / Shopify as those clients come online). This plugin is Sego Lily's WordPress fork — fully self-contained, customized for Holly's line, with its own release cycle independent of the master.

When future Lead Piranha clients need a quiz on a different platform (Framer, coded site, Shopify, etc.), their implementation forks from the relevant platform reference in lp-quiz-suite, not from this plugin.

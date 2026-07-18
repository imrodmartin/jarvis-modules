# Jarvis modules

The two custom glue modules for the [Jarvis](https://github.com/imrodmartin/jarvis)
Drupal theme, packaged for Composer. Installed as one directory; Drupal's
extension discovery finds both nested modules:

- **jarvis_blocks** — the Jarvis block content types (hero, card, image,
  text, video) and their field config, mapped onto the theme's SDCs.
- **jarvis_canvas** — Drupal Canvas integration: Basic/Full HTML text formats
  on rich-text component props, the "Create with Canvas" CTA on view-mode
  forms (D11.4 route fix), auto-created content templates per content type,
  and the idempotent Canvas component-folder organiser (runs on cache
  rebuild).

Don't require this directly — it comes in with
[imrodmartin/jarvis-recipe](https://github.com/imrodmartin/jarvis-recipe),
which sets up the whole site.

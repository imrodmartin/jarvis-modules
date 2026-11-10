# Jarvis modules

The glue module for the [Jarvis](https://github.com/imrodmartin/jarvis) Drupal
theme, packaged for Composer. Installed as one directory; Drupal's extension
discovery finds the nested module:

- **jarvis_canvas** — Drupal Canvas integration: Basic/Full HTML text formats
  on rich-text component props, the Remote video media picker on the video
  components, the "Create with Canvas" CTA on view-mode forms (D11.4 route
  fix), auto-created content templates per content type, and the idempotent
  Canvas component-folder organiser (runs on cache rebuild). It also provides
  the two Twig filters the theme's templates call: `jarvis_image_style` and
  `jarvis_overlay_alpha`.

  (`jarvis_blocks` was retired — nothing rendered its block types on a Canvas
  site.)

**This is required, not optional.** A theme cannot register a Twig extension,
so `hero`, `card`, the column shell and `image` all depend on filters that live
here; Twig fails to compile them when the module is absent. `drupal/jarvis`
requires `^2.2` of this package (theme 4.0 and later; 2.2 carries the conversion of content saved under 3.x) and declares the module in `jarvis.info.yml`.
It also arrives with
[imrodmartin/jarvis-recipe](https://github.com/imrodmartin/jarvis-recipe),
which sets up the whole site.

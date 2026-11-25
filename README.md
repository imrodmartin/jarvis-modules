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

## Interim fix: canvas_field_component on Canvas 1.13+

`JarvisCanvasHooks::configSchemaInfoAlter()` works around a
`canvas_field_component` 1.0.2 bug: on Canvas 1.13+ it appends `field_display`
beside the `source` Choice constraint's `choices` key instead of inside it, and
every `canvas.component.*` entity then fails validation with *"Cannot use
positional argument after named argument during unpacking"* (recipe apply,
config import, the Canvas cron health check). The hook runs after
`canvas_field_component` and moves the value inside `choices`. It is a no-op on
Canvas 1.12 and older, and once upstream is fixed.

**On every upgrade, check whether it can be removed.** Reported upstream by
Andrii in the
[canvas_field_component issue queue](https://git.drupalcode.org/project/canvas_field_component/-/issues).
When a `canvas_field_component` release contains the fix (its
`src/Hook/ConfigSchemaHooks.php` handles `choices`), delete the method and its
`ReorderHook` / `OrderAfter` imports. The full check is in the upgrade runbook,
`docs/upgrade-jarvis.html` in
[jarvis-bootstrap-recipe](https://github.com/imrodmartin/jarvis-bootstrap-recipe).

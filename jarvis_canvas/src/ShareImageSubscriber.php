<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\AdminContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives every page a share image: hero banner, else first body image, else logo.
 *
 * Facebook, X and LinkedIn take the preview image from og:image in the page
 * head, not from the share link. Jarvis sites emitted none, so the networks
 * guessed and usually picked the logo.
 *
 * Works on the finished HTML rather than on stored component trees, so one
 * rule covers Canvas pages, content templates, Layout Builder nodes and Views
 * alike. The hero sits at the top of the page body (the hero region or the top
 * of <main>), so "hero banner, else first body image" is simply the first
 * usable image below the site header, background or <img>.
 *
 * An og:image already in the head (an author's own Metatag value) is kept.
 *
 * ponytail: regex over the response HTML. A page with a decorative background
 * section above its real photo picks the decoration; add a data attribute
 * opt-out on the component if that ever happens.
 */
final class ShareImageSubscriber implements EventSubscriberInterface {

  /**
   * Facebook's minimum; smaller images are skipped when the width is known.
   */
  private const MIN_WIDTH = 200;

  public function __construct(private readonly AdminContext $adminContext) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !$response instanceof HtmlResponse || $response->getStatusCode() !== 200
      || !$request->isMethodSafe() || $this->adminContext->isAdminRoute()) {
      return;
    }
    $html = (string) $response->getContent();
    $head_end = stripos($html, '</head>');
    if ($head_end === FALSE || preg_match('/<meta[^>]+property="og:image"/i', substr($html, 0, $head_end))) {
      return;
    }

    $origin = $request->getSchemeAndHttpHost();
    $image = $this->pageImage($html, $head_end) ?? $this->logo();
    if (!$image) {
      return;
    }
    $image = $this->absolute($image, $origin, $request->getScheme());
    $esc = htmlspecialchars($image, ENT_QUOTES);
    $tags = '<meta property="og:image" content="' . $esc . '" />' . "\n";
    if (!preg_match('/<meta[^>]+name="twitter:card"/i', substr($html, 0, $head_end))) {
      // X shows no image at all without a card type.
      $tags .= '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }
    if (!preg_match('/<meta[^>]+name="twitter:image"/i', substr($html, 0, $head_end))) {
      $tags .= '<meta name="twitter:image" content="' . $esc . '" />' . "\n";
    }
    $response->setContent(substr_replace($html, $tags, $head_end, 0));
  }

  /**
   * First usable image in the page body, in document order.
   *
   * The window opens at the first full-width region page.html.twig prints
   * under the site header (page title, hero, highlighted) or at <main>,
   * whichever comes first: a banner placed in the hero region sits outside
   * <main>. It closes at </main>, so the footer never counts.
   */
  private function pageImage(string $html, int $head_end): ?string {
    $main = stripos($html, '<main', $head_end);
    $end = $main === FALSE ? FALSE : stripos($html, '</main>', $main);
    // Without <main>, the header logo would win; better no page image.
    if ($main === FALSE || $end === FALSE) {
      return NULL;
    }
    $start = $main;
    foreach (['jarvis-page-title', 'jarvis-region--full jarvis-hero', 'jarvis-highlighted'] as $region) {
      $at = strpos($html, $region, $head_end);
      if ($at !== FALSE && $at < $start) {
        $start = $at;
      }
    }
    $body = substr($html, $start, $end - $start);
    preg_match_all(
      '/background-image:\s*url\(\s*[\'"]?([^\'")]+)|<img\b[^>]*>/i',
      $body,
      $matches,
      PREG_SET_ORDER,
    );
    foreach ($matches as $m) {
      if (!empty($m[1])) {
        $src = $m[1];
      }
      else {
        if (!preg_match('/\ssrc="([^"]+)"/i', $m[0], $s)) {
          continue;
        }
        if (preg_match('/\swidth="(\d+)"/i', $m[0], $w) && (int) $w[1] < self::MIN_WIDTH) {
          continue;
        }
        $src = $s[1];
      }
      $src = $this->original(html_entity_decode($src, ENT_QUOTES | ENT_HTML5));
      // The networks render neither SVG nor inline data.
      if ($src && !str_starts_with($src, 'data:') && !preg_match('/\.svg$/i', $src)) {
        return $src;
      }
    }
    return NULL;
  }

  /**
   * The uploaded file behind a Canvas/image-style URL.
   *
   * Canvas appends ?alternateWidths=..., and image styles serve
   * /styles/NAME/public/path.jpg.avif?itok=... — AVIF, which LinkedIn and
   * Facebook do not reliably accept. The original upload is a JPEG or PNG.
   */
  private function original(string $src): string {
    $src = strtok($src, '?') ?: '';
    if (preg_match('#^(.*)/styles/[^/]+/(?:public|private)/(.+)$#', $src, $m)) {
      $src = $m[1] . '/' . preg_replace('/(\.[a-z0-9]+)\.(?:avif|webp)$/i', '$1', $m[2]);
    }
    return $src;
  }

  private function logo(): ?string {
    return theme_get_setting('logo.url') ?: NULL;
  }

  private function absolute(string $src, string $origin, string $scheme): string {
    if (str_starts_with($src, '//')) {
      return $scheme . ':' . $src;
    }
    if (str_starts_with($src, '/')) {
      return $origin . $src;
    }
    return preg_match('#^https?://#i', $src) ? $src : $origin . '/' . ltrim($src, '/');
  }

}

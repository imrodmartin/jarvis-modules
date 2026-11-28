<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;

/**
 * Site alerts: coloured bars at the very top of chosen pages.
 *
 * Alerts are `alert` nodes (shipped by the jarvis and jarvis-existing
 * recipes). They print in page_top, which core's html.html.twig renders
 * outside the Canvas page variant, so the bar appears on Canvas pages, nodes,
 * Views and Layout Builder pages alike. A block would not: Canvas ignores
 * block placement outside the footer.
 *
 * Scheduler switches alerts on and off by publishing and unpublishing them at
 * cron; each save invalidates node_list:alert, which every page carries.
 *
 * Inert on sites without the alert type, so a jarvis-modules update is safe
 * anywhere.
 */
final class AlertHooks {

  use StringTranslationTrait;

  private const STYLES = ['red', 'yellow', 'green', 'blue'];

  private const PICKERS = [
    'canvas_page' => 'field_alert_canvas_pages',
    'node' => 'field_alert_nodes',
  ];

  #[Hook('page_top')]
  public function pageTop(array &$page_top): void {
    // Carried even when nothing matches, or a cached page would never learn
    // that an alert was published.
    $page_top['jarvis_alerts'] = [
      '#cache' => [
        'tags' => ['node_list:alert'],
        'contexts' => ['url.path', 'url.path.is_front'],
      ],
    ];
    $route = (string) \Drupal::routeMatch()->getRouteName();
    if (str_starts_with($route, 'canvas.') || \Drupal::service('router.admin_context')->isAdminRoute()
      || !FieldConfig::loadByName('node', 'alert', 'field_alert_show_on')) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'alert')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    if (!$ids) {
      return;
    }
    $current = self::currentEntity();
    $is_front = \Drupal::service('path.matcher')->isFrontPage();

    foreach ($storage->loadMultiple($ids) as $alert) {
      if (!self::matches($alert, $current, $is_front)) {
        continue;
      }
      $style = $alert->get('field_alert_style')->value;
      $page_top['jarvis_alerts'][$alert->id()] = [
        '#type' => 'inline_template',
        '#template' => '<aside class="jarvis-alert jarvis-alert--{{ style }}" aria-label="{{ label }}"><div class="container">{{ message }}</div></aside>',
        '#context' => [
          'style' => in_array($style, self::STYLES, TRUE) ? $style : 'red',
          'label' => $this->t('Site alert'),
          'message' => $alert->get('field_body')->view(['label' => 'hidden', 'type' => 'text_default']),
        ],
      ];
      $page_top['jarvis_alerts']['#attached']['library'] = ['jarvis_canvas/alerts'];
    }
  }

  /**
   * Whether an alert belongs on the current page.
   *
   * "Selected pages" with nothing selected matches nowhere, never everywhere.
   */
  public static function matches(NodeInterface $alert, ?EntityInterface $current, bool $is_front): bool {
    $mode = $alert->get('field_alert_show_on')->value ?: 'all';
    if ($mode === 'all') {
      return TRUE;
    }
    if ($mode === 'front') {
      return $is_front;
    }
    $field = $current ? (self::PICKERS[$current->getEntityTypeId()] ?? NULL) : NULL;
    if (!$field || !$alert->hasField($field)) {
      return FALSE;
    }
    foreach ($alert->get($field) as $item) {
      if ((string) $item->target_id === (string) $current->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The Canvas page or node this request shows, if it is one.
   */
  private static function currentEntity(): ?EntityInterface {
    $match = \Drupal::routeMatch();
    foreach (array_keys(self::PICKERS) as $type) {
      if ($match->getRouteName() === "entity.$type.canonical") {
        $entity = $match->getParameter($type);
        return $entity instanceof EntityInterface ? $entity : NULL;
      }
    }
    return NULL;
  }

  /**
   * Page pickers only show for "Selected pages", which then needs one.
   */
  #[Hook('form_node_alert_form_alter')]
  #[Hook('form_node_alert_edit_form_alter')]
  public function alertFormAlter(array &$form, FormStateInterface $form_state): void {
    foreach (self::PICKERS as $field) {
      if (isset($form[$field])) {
        $form[$field]['#states']['visible'][':input[name="field_alert_show_on"]'] = ['value' => 'selected'];
      }
    }
    $form['#validate'][] = [self::class, 'validateSelection'];
  }

  public static function validateSelection(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getValue(['field_alert_show_on', 0, 'value']) ?? '') !== 'selected') {
      return;
    }
    foreach (self::PICKERS as $field) {
      // The widget's value also holds its "Add another" button label.
      foreach ((array) $form_state->getValue($field) as $item) {
        if (is_array($item) && !empty($item['target_id'])) {
          return;
        }
      }
    }
    $form_state->setErrorByName('field_alert_show_on', t('Choose at least one Canvas page or content item, or pick another "Show on" option.'));
  }

}

<?php

namespace Drupal\mrmilu_readable_url\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

class ReadableUrlForm extends FormBase {

  public function getFormId() {
    return 'mrmilu_readable_url';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
   $form['#tree'] = TRUE;
   $form['container'] = [
     '#type' => 'details',
     '#title' => $this->t('Url\'s'),
     '#open' => TRUE,
     '#prefix' => '<div id="urls-fieldset-wrapper">',
     '#suffix' => '</div>',
   ];

    $numUrls = $form_state->get('num_urls');
    if ($numUrls === NULL) {
      $stateUrls = \Drupal::state()->get('mrmilu_readable_url_num_urls');
      $numUrls = $stateUrls != null ? $stateUrls : 1;
      $form_state->set('num_urls', $numUrls);
    }

    $nodeValues = \Drupal::state()->get('mrmilu_readable_url_node');
    $filterValues = \Drupal::state()->get('mrmilu_readable_url_filter');
    for ($i = 0; $i < $numUrls; $i++) {
      $form['container']['url'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Url') . ' ' . $i,
        '#open' => TRUE,
        '#prefix' => '<div id="filters-fieldset-wrapper-' . $i . '">',
        '#suffix' => '</div>',
      ];

      $form['container']['url'][$i]['mrmilu_readable_url_node'] = [
        '#title' => t('Node'),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_handler' => 'default',
        '#default_value' => isset($nodeValues[$i]) ? Node::load($nodeValues[$i]) : NULL
      ];

      $numFilters = $form_state->get(['num_filters', $i]);
      if ($numFilters === NULL) {
        $stateFilters = \Drupal::state()->get('mrmilu_readable_url_num_filters');
        $numFilters = $stateFilters[$i] ?? 1;
        $form_state->set(['num_filters', $i], $numFilters);
      }

      for ($j = 0; $j < $numFilters; $j++) {
        $form['container']['url'][$i]['mrmilu_readable_url_filter'][$j] = [
          '#type' => 'textarea',
          '#title' => t('Filter') . ' ' . $j,
          '#description' => t('Field key|Field value'),
          '#default_value' => $filterValues[$i][$j] ?? NULL,
        ];
      }

      // Filters actions
      $form['container']['url'][$i]['actions'] = [
        '#type' => 'actions',
      ];
      $form['container']['url'][$i]['actions']['add_filter'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add filter'),
        '#name' => 'add_filter_' . $i,
        '#submit' => ['::addOneFilters'],
        '#attributes' => [
          'current_url' => $i,
        ],
        '#ajax' => [
          'callback' => '::addmoreFiltersCallback',
          'wrapper' => 'filters-fieldset-wrapper-' . $i,
        ],
      ];
      if ($numFilters > 1) {
        $form['container']['url'][$i]['actions']['remove_filter'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove filter'),
          '#name' => 'remove_filter_' . $i,
          '#submit' => ['::removeFiltersCallback'],
          '#attributes' => [
            'current_url' => $i,
          ],
          '#ajax' => [
            'callback' => '::addmoreFiltersCallback',
            'wrapper' => 'filters-fieldset-wrapper-' . $i,
          ],
        ];
      }
    }

    // Url actions
    $form['container']['actions'] = [
      '#type' => 'actions',
    ];
    $form['container']['actions']['add_url'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add url'),
      '#submit' => ['::addOneUrls'],
      '#ajax' => [
        'callback' => '::addmoreUrlsCallback',
        'wrapper' => 'urls-fieldset-wrapper',
      ],
    ];
    if ($numUrls > 1) {
      $form['container']['actions']['remove_url'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove url'),
        '#submit' => ['::removeUrlsCallback'],
        '#ajax' => [
          'callback' => '::addmoreUrlsCallback',
          'wrapper' => 'urls-fieldset-wrapper',
        ],
      ];
    }

    // Filters actions
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue(['container', 'url']);
    $nodeValues = [];
    $filterValues = [];
    $filterIndex = [];

    foreach ($values as $i => $value) {
      $nodeValues[$i] = $value['mrmilu_readable_url_node'];
      $filterValues[$i] = [];
      foreach ($value['mrmilu_readable_url_filter'] as $j => $filter) {
        $filterValues[$i][$j] = $filter;
      }
      $filterIndex[$i] = $j + 1;
    }

    \Drupal::state()->set('mrmilu_readable_url_node', $nodeValues);
    \Drupal::state()->set('mrmilu_readable_url_filter', $filterValues);

    // Auxiliar values to init form with indexes
    $numUrls = $form_state->get('num_urls');
    \Drupal::state()->set('mrmilu_readable_url_num_urls', $numUrls);
    \Drupal::state()->set('mrmilu_readable_url_num_filters', $filterIndex);

    \Drupal::messenger()->addMessage(t('Saved url\'s'));
  }

  /**
   * addMoreFunctions
   */
  public function addmoreUrlsCallback(array &$form, FormStateInterface $form_state) {
    return $form['container'];
  }

  public function addmoreFiltersCallback(array &$form, FormStateInterface $form_state) {
    $currentUrl = $this->getCurrentUrl($form_state);
    return $form['container']['url'][$currentUrl];
  }

  /**
   * addOne functions
   */
  public function addOneUrls(array &$form, FormStateInterface $form_state) {
    $numUrls = $form_state->get('num_urls');
    $addButton = $numUrls + 1;
    $form_state->set('num_urls', $addButton);
    $form_state->setRebuild();
  }

  public function addOneFilters(array &$form, FormStateInterface $form_state) {
    $currentUrl = $this->getCurrentUrl($form_state);
    $numFilters = $form_state->get(['num_filters', $currentUrl]);
    $addButton = $numFilters + 1;
    $form_state->set(['num_filters', $currentUrl], $addButton);
    $form_state->setRebuild();
  }

  /**
   * removeFunctions
   */
  public function removeUrlsCallback(array &$form, FormStateInterface $form_state) {
    $numUrls = $form_state->get('num_urls');
    if ($numUrls > 1) {
      $removeButton = $numUrls - 1;
      $form_state->set('num_urls', $removeButton);
    }
    $form_state->setRebuild();
  }

  public function removeFiltersCallback(array &$form, FormStateInterface $form_state) {
    $currentUrl = $this->getCurrentUrl($form_state);
    $numFilters = $form_state->get(['num_filters', $currentUrl]);
    if ($numFilters > 1) {
      $removeButton = $numFilters - 1;
      $form_state->set(['num_filters', $currentUrl], $removeButton);
    }
    $form_state->setRebuild();
  }

  private function getCurrentUrl(FormStateInterface $form_state) {
    $clickedElement = $form_state->getTriggeringElement();
    $currentUrl = $clickedElement['#attributes']['current_url'];
    return $currentUrl;
  }
}

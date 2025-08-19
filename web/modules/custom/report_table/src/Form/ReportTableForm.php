<?php

namespace Drupal\report_table\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ReportTableForm extends FormBase {

  public function getFormId() {
    return 'report_table_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_year = date('Y');

    $months = [
      'Jan', 'Feb', 'Mar', 'Q1',
      'Apr', 'May', 'Jun', 'Q2',
      'Jul', 'Aug', 'Sep', 'Q3',
      'Oct', 'Nov', 'Dec', 'Q4',
      'YTD',
    ];

    $header = array_merge(['Year'], $months);

    $form['#attached']['library'][] = 'report_table/report_table.styles';
    $form['#attributes']['class'][] = 'report-table-wrapper';

    // Add year button
    $form['add_year'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Year'),
      '#submit' => ['::addYearSubmit'],
      '#limit_validation_errors' => [],
    ];

    // Table
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#attributes' => ['class' => ['report-table']],
    ];

    $years = $form_state->get('years');
    if ($years === NULL) {
      $years = [$current_year];
      $form_state->set('years', $years);
    }

    foreach ($years as $year) {
      $form['table'][$year]['year'] = [
        '#markup' => $year,
      ];

      foreach ($months as $month) {
        $cell = [
          '#type' => 'number',
          '#default_value' => '',
          '#min' => 0,
          '#attributes' => ['class' => []],
        ];

        if (in_array($month, ['Q1', 'Q2', 'Q3', 'Q4', 'YTD'])) {
          $cell['#attributes']['class'][] = 'quarter-cell';
        }

        $form['table'][$year][$month] = $cell;
      }
    }

    // Submit button (renamed and updated)
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['report-table-submit']],
    ];

    return $form;
  }

  /**
   * Validate form input
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $valid = TRUE;
    $values = $form_state->getValue('table');

    foreach ($values as $year => $months) {
      foreach ($months as $month => $value) {
        if ($month === 'year') {
          continue;
        }
        // Приклад: значення має бути числом і не від’ємне
        if (!is_numeric($value) || $value < 0) {
          $valid = FALSE;
          $form_state->setErrorByName("table][$year][$month", $this->t('Value must be a non-negative number.'));
        }
      }
    }

    // Збереження флагу валідації
    $form_state->set('is_valid', $valid);
  }

  /**
   * Submit form
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('is_valid')) {
      \Drupal::messenger()->addMessage($this->t('Valid'));
    }
    else {
      \Drupal::messenger()->addMessage($this->t('Invalid'), 'error');
    }
  }

  /**
   * Add year button handler
   */
  public function addYearSubmit(array &$form, FormStateInterface $form_state) {
    $years = $form_state->get('years');
    if (empty($years)) {
      $years = [date('Y')];
    }
    $last_year = end($years);
    $years[] = $last_year - 1;
    $form_state->set('years', $years);
    $form_state->setRebuild(TRUE);
  }
}


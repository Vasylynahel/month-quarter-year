<?php

namespace Drupal\report_table\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ReportTableForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'report_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_year = date('Y');
    
    
    
    // Правильний порядок колонок.
    $months = [
      'Jan', 'Feb', 'Mar', 'Q1',
      'Apr', 'May', 'Jun', 'Q2',
      'Jul', 'Aug', 'Sep', 'Q3',
      'Oct', 'Nov', 'Dec', 'Q4',
      'YTD',
    ];

    $header = array_merge(['Year'], $months);

    // Підключення CSS бібліотеки
    $form['#attached']['library'][] = 'report_table/report_table.styles';
    $form['#attributes']['class'][] = 'report-table-wrapper';
    
    $form['add_year'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Year'),
      '#submit' => ['::addYearSubmit'],
      '#limit_validation_errors' => [],
    ];
    
    // Таблиця
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#attributes' => ['class' => ['report-table']],
    ];

    // Рядок поточного року
    $form['table'][$current_year]['year'] = [
      '#markup' => $current_year,
    ];

    // Клітинки
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

      $form['table'][$current_year][$month] = $cell;
    }
    
    $years = $form_state->get('years');
    if ($years === NULL){
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
          'attributes' => ['class' => []],
        ];
        
        if (in_array($month, ['Q1','Q2','Q3','Q4','Q5','YTD',])) {
          $cell['#attributes']['class'][]='quarter-cell';
        }
        
        $form['table'][$year][$month] = $cell;
        }
      }

    // Кнопка
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['report-table-submit']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('table');
    \Drupal::messenger()->addMessage('Data saved: ' . print_r($values, TRUE));
  }
  
  public function addYearSubmit(array &$form, FormStateInterface $form_state) {
    $years = $form_state->get('years');
    if (empty($years)) {
      $years = [date('Y')];
    }
    $last_year = end($years);
    $years[] = $last_year - 1;  // додаємо наступний рік
    $form_state->set('years', $years);

    $form_state->setRebuild(TRUE);
  }
}


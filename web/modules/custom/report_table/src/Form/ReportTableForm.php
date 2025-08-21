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

    // 1️⃣ Отримання / ініціалізація таблиць та років
    $tables = $form_state->get('tables');
    if ($tables === NULL) {
      $tables = [[date('Y')]]; // одна таблиця з поточним роком
      $form_state->set('tables', $tables);
    }

    // 2️⃣ Кнопки "Add Table" і "Add Year" над усіма таблицями
    $form['actions_top'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['actions-top']],
    ];

    $form['actions_top']['add_table'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Table'),
      '#submit' => ['::addTableSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['actions_top']['add_year'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Year'),
      '#submit' => ['::addYearSubmit'],
      '#limit_validation_errors' => [],
    ];

    // 3️⃣ Генерація всіх таблиць
    foreach ($tables as $index => $years) {
      $form['tables'][$index]['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#attributes' => ['class' => ['report-table', 'report-table-wrapper']],
      ];

      foreach ($years as $year) {
        $form['tables'][$index]['table'][$year]['year'] = [
          '#markup' => $year,
        ];

        foreach ($months as $month) {
          $cell = [
            '#type' => 'number',
            '#default_value' => '',
            '#min' => 0,
            '#attributes' => ['class' => []],
            '#parents' => ['tables', $index, 'table', $year, $month],
          ];

          if (in_array($month, ['Q1', 'Q2', 'Q3', 'Q4', 'YTD'])) {
            $cell['#attributes']['class'][] = 'quarter-cell';
          }

          $form['tables'][$index]['table'][$year][$month] = $cell;
        }
      }
    }

    // 4️⃣ Кнопка Submit
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['report-table-submit']],
    ];

    return $form;
  }

  /**
   * ✅ Валідація форми: всі значення повинні бути числами >= 0
   */
public function validateForm(array &$form, FormStateInterface $form_state) {
  $valid = TRUE;
  $tables = $form_state->getValue('tables');

  // Список місяців у правильному порядку
  $months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];

  if (!is_array($tables)) {
    $form_state->setErrorByName('tables', $this->t('No table data found.'));
    return;
  }

  $global_period = NULL; // тут зберігаємо спільний період для всіх таблиць

  foreach ($tables as $table_index => $table) {
    foreach ($table['table'] as $year => $row) {
      if ($year === 'year') {
        continue;
      }

      // 1️⃣ Визначаємо заповнені місяці
      $filled = [];
      foreach ($months as $month) {
        $val = trim((string) ($row[$month] ?? ''));
        if ($val !== '' && is_numeric($val) && $val >= 0) {
          $filled[] = $month;
        }
      }

      if (empty($filled)) {
        continue; // рік пустий — пропускаємо
      }

      $first = array_search(reset($filled), $months);
      $last  = array_search(end($filled), $months);

      // 2️⃣ Перевірка на «дірки» в одному році
      for ($i = $first; $i <= $last; $i++) {
        $m = $months[$i];
        $val = trim((string) ($row[$m] ?? ''));
        if ($val === '' || !is_numeric($val) || $val < 0) {
          $form_state->setErrorByName(
            "tables][$table_index][table][$year][$m",
            $this->t("Missing value for @month in continuous period.", ['@month' => $m])
          );
          $valid = FALSE;
        }
      }

      // 3️⃣ Перевірка, що всі таблиці мають однаковий період
      $period = [$first, $last];
      if ($global_period === NULL) {
        $global_period = $period;
      } else {
        if ($global_period !== $period) {
          $form_state->setErrorByName(
            "tables][$table_index][table][$year",
            $this->t("All tables must have the same period (from @first to @last).", [
              '@first' => $months[$global_period[0]],
              '@last' => $months[$global_period[1]],
            ])
          );
          $valid = FALSE;
        }
      }
    }
  }

  $form_state->set('is_valid', $valid);
}


  /**
   * ✅ Submit обробка
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('is_valid')) {
      \Drupal::messenger()->addMessage($this->t('Valid'));
    } else {
      \Drupal::messenger()->addMessage($this->t('Invalid'), 'error');
    }
  }

  /**
   * ➕ Додати таблицю
   */
  public function addTableSubmit(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->get('tables') ?? [];
    $tables[] = [date('Y')];
    $form_state->set('tables', $tables);
    $form_state->setRebuild(TRUE);
  }

  /**
   * ➕ Додати рік до всіх таблиць
   */
  public function addYearSubmit(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->get('tables') ?? [];

    foreach ($tables as &$years) {
      $last_year = end($years);
      $years[] = $last_year - 1;
    }

    $form_state->set('tables', $tables);
    $form_state->setRebuild(TRUE);
  }
}


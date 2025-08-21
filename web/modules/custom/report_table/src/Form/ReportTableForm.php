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

    $tables = $form_state->get('tables');
    if ($tables === NULL) {
      $tables = [[date('Y')]];
      $form_state->set('tables', $tables);
    }

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
            // ВАЖЛИВО: правильні parents, щоб значення реально лежали у tables[index][table][year][month]
            '#parents' => ['tables', $index, 'table', $year, $month],
          ];

          if (in_array($month, ['Q1', 'Q2', 'Q3', 'Q4', 'YTD'], true)) {
            $cell['#attributes']['class'][] = 'quarter-cell';
          }

          $form['tables'][$index]['table'][$year][$month] = $cell;
        }
      }
    }

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

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->getValue('tables') ?? [];
    if (empty($tables)) {
      return;
    }

    $periods = []; // для перевірки однакового періоду у всіх таблицях

    foreach ($tables as $index => $tableWrapper) {
      // --- FIX: беремо саме масив з даними рядків/місяців ---
      $table = isset($tableWrapper['table']) && is_array($tableWrapper['table'])
        ? $tableWrapper['table']
        : (is_array($tableWrapper) ? $tableWrapper : []);

      $anchor = "tables][$index][table"; // куди вішати помилку

      $filledMonths = $this->getFilledMonths($table);

      if (empty($filledMonths)) {
        $form_state->setErrorByName($anchor, $this->t('Таблиця @num не містить жодного заповненого місяця.', ['@num' => $index + 1]));
        // навіть якщо порожня, далі не валідовуємо її
        continue;
      }

      // 1) Розриви по місяцях (у т.ч. на межі років)
      $missingMonths = $this->getMissingMonths($filledMonths);
      if (!empty($missingMonths)) {
        $form_state->setErrorByName(
          $anchor,
          $this->t('У таблиці @num є розриви по місяцях. Пропущено: @list', [
            '@num' => $index + 1,
            '@list' => $this->formatMonthList($missingMonths, 12),
          ])
        );
      }

      // 2) Розриви по роках (якщо років більше одного)
      $missingYears = $this->getMissingYears($filledMonths);
      if (!empty($missingYears)) {
      $form_state->setErrorByName(
          $anchor,
          $this->t('У таблиці @num є розриви по роках. Пропущені роки: @years', [
            '@num'   => $index + 1,
            '@years' => implode(', ', $missingYears),
          ])
        );
      }

      // для перевірки «однаковий період»
      $periods[] = [
        'start' => $filledMonths[0],
        'end'   => $filledMonths[count($filledMonths) - 1],
      ];
    }

    // 3) Усі таблиці повинні мати ОДНАКОВИЙ період (той самий min і max)
    if (!empty($periods)) {
      $firstStart = $periods[0]['start'];
      $firstEnd   = $periods[0]['end'];

      foreach ($periods as $i => $p) {
        if ($p['start'] !== $firstStart || $p['end'] !== $firstEnd) {
          $form_state->setErrorByName(
            "tables][$i][table",
            $this->t('Усі таблиці мають бути за однаковий період: @s — @e. Таблиця @num має @cs — @ce.', [
              '@s'   => $this->humanMonth($firstStart),
              '@e'   => $this->humanMonth($firstEnd),
              '@num' => $i + 1,
              '@cs'  => $this->humanMonth($p['start']),
              '@ce'  => $this->humanMonth($p['end']),
            ])
          );
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addMessage($this->t('Форма успішно надіслана.'));
  }

  public function addTableSubmit(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->get('tables') ?? [];
    $tables[] = [date('Y')];
    $form_state->set('tables', $tables);
    $form_state->setRebuild(TRUE);
  }

  public function addYearSubmit(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->get('tables') ?? [];

    foreach ($tables as &$years) {
      $last_year = end($years);
      $years[] = $last_year - 1;
      // повертаємо внутрішній покажчик масиву, щоб уникнути побічних ефектів
      reset($years);
    }
    unset($years);

    $form_state->set('tables', $tables);
    $form_state->setRebuild(TRUE);
  }

  // ----------------- Допоміжні методи (фікс + діагностика) -----------------

  private function getFilledMonths(array $table): array {
    // Очікується $table[YYYY][Mon] => value
    $monthMap = [
      'Jan' => 1, 'Feb' => 2, 'Mar' => 3,
      'Apr' => 4, 'May' => 5, 'Jun' => 6,
      'Jul' => 7, 'Aug' => 8, 'Sep' => 9,
      'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
    ];

    $filled = [];

    foreach ($table as $yearStr => $months) {
      if (!is_array($months)) {
        continue;
      }
      $year = (int) $yearStr;
      if ($year <= 0) {
        continue; // захист від помилкових ключів типу 'table'
      }

      foreach ($months as $monthName => $value) {
        if (!isset($monthMap[$monthName])) {
          continue; // пропускаємо Q1..Q4, YTD тощо
        }
        // 0 і '0' вважаються заповненими
        if ($value !== '' && $value !== NULL) {
          $monthNum = $monthMap[$monthName];
          $filled[] = sprintf('%04d-%02d', $year, $monthNum);
        }
      }
    }

    sort($filled, SORT_STRING);
    return $filled;
  }

  private function generateMonthRange(string $startMonth, string $endMonth): array {
    $range = [];
    $current = new \DateTime($startMonth . '-01');
    $end = new \DateTime($endMonth . '-01');

    while ($current <= $end) {
      $range[] = $current->format('Y-m');
      $current->modify('+1 month');
    }

    return $range;
  }

  private function getMissingMonths(array $filledMonths): array {
    if (empty($filledMonths)) {
      return [];
    }
    $expected = $this->generateMonthRange($filledMonths[0], $filledMonths[count($filledMonths) - 1]);
    // які саме місяці відсутні
    return array_values(array_diff($expected, $filledMonths));
  }

  private function getMissingYears(array $filledMonths): array {
    if (empty($filledMonths)) {
      return [];
    }
    $years = [];
    foreach ($filledMonths as $ym) {
      $years[(int) substr($ym, 0, 4)] = true;
    }
    $years = array_keys($years);
    sort($years, SORT_NUMERIC);
    if (count($years) <= 1) {
      return [];
    }

    $missing = [];
    $minYear = $years[0];
    $maxYear = $years[count($years) - 1];
    for ($y = $minYear; $y <= $maxYear; $y++) {
      if (!in_array($y, $years, true)) {
        $missing[] = (string) $y;
      }
    }
    return $missing;
  }

  private function humanMonth(string $ym): string {
    // 'YYYY-MM' -> 'ММ.YYYY' (коротко і ясно)
    $y = substr($ym, 0, 4);
    $m = substr($ym, 5, 2);
    return $m . '.' . $y;
  }

  private function formatMonthList(array $ymList, int $limit = 12): string {
    $pretty = array_map([$this, 'humanMonth'], $ymList);
    if (count($pretty) > $limit) {
      $head = array_slice($pretty, 0, $limit);
      return implode(', ', $head) . $this->t(' та ін.');
    }
    return implode(', ', $pretty);
  }

}

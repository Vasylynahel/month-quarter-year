<?php

namespace Drupal\report_table\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ReportTableForm extends FormBase {

  public function getFormId() {
    return 'report_table_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $months = [
      'Jan', 'Feb', 'Mar', 'Q1',
      'Apr', 'May', 'Jun', 'Q2',
      'Jul', 'Aug', 'Sep', 'Q3',
      'Oct', 'Nov', 'Dec', 'Q4',
      'YTD',
    ];
    $quarterKeys = ['Q1','Q2','Q3','Q4'];
    $monthKeys   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    $header = array_merge(['Year'], $months);

    $form['#attached']['library'][] = 'report_table/report_table.styles';

    // ініціалізація структури таблиць у стані форми
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

    // Отримуємо значення з форми або ініціалізуємо пусті
    $values = $form_state->getValue('tables', []);
    
    foreach ($tables as $index => $years) {
      // обгортаємо кожну таблицю власним wrapper для AJAX-перемальовки
      $form['tables'][$index]['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#attributes' => ['class' => ['report-table', 'report-table-wrapper']],
        '#prefix' => '<div id="table-wrapper-' . $index . '">',
        '#suffix' => '</div>',
      ];

      foreach ($years as $year) {
        $form['tables'][$index]['table'][$year]['year'] = [
          '#markup' => $year,
        ];

        // Отримуємо поточні значення для цього року
        $yearValues = $values[$index]['table'][$year] ?? [];

        foreach ($months as $month) {
          // Визначаємо значення за замовчуванням
          $default_value = $yearValues[$month] ?? '';

          // Квартальні клітинки: нередаговані number (readonly), крок 0.05
          if (in_array($month, $quarterKeys, true)) {
            // Завжди перераховуємо квартали для року
            $this->computeQuartersForYear($yearValues);
            $values[$index]['table'][$year] = $yearValues; // зберігаємо стан

            $default_value = $yearValues[$month] ?? NULL;

            $cell = [
              '#type' => 'number',
              '#default_value' => ($default_value === NULL) ? '' : $default_value,
              '#step' => 0.05,
              '#min' => 0,
              '#attributes' => [
                'class' => ['quarter-cell'],
                'readonly' => 'readonly',
              ],
              '#parents' => ['tables', $index, 'table', $year, $month],
            ];
          }


          // Місячні клітинки: редаговані number + AJAX перерахунок кварталу
          elseif (in_array($month, $monthKeys, true)) {
            $cell = [
              '#type' => 'number',
              '#default_value' => $default_value,
              '#min' => 0,
              '#step' => 0.01,
              '#attributes' => ['class' => []],
              '#parents' => ['tables', $index, 'table', $year, $month],
              '#ajax' => [
                'callback' => '::recalculateQuarterAjax',
                'wrapper' => "table-wrapper-$index",
                'event' => 'change',
              ],
            ];
          }
          // YTD залишаємо як звичайний number (не розраховуємо тут)
          else {
            $cell = [
              '#type' => 'number',
              '#default_value' => $default_value,
              '#min' => 0,
              '#step' => 0.01,
              '#attributes' => ['class' => ['ytd-cell']],
              '#parents' => ['tables', $index, 'table', $year, $month],
            ];
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

  /**
   * AJAX: перерахунок кварталів після зміни будь-якого місяця в таблиці.
   */
   public function recalculateQuarterAjax(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'] ?? [];
    // Очікуємо структуру: ['tables', tIndex, 'table', year, month]
    if (count($parents) < 5) {
      return $form;
    }
    $tIndex = $parents[1];
    $yearKey = $parents[3];

    // Беремо поточні значення
    $tables = $form_state->getValue('tables') ?? [];

    if (!isset($tables[$tIndex]['table'][$yearKey]) || !is_array($tables[$tIndex]['table'][$yearKey])) {
      return $form['tables'][$tIndex]['table'];
    }

    // Перерахунок кварталів лише для конкретного року в цій таблиці
    $this->computeQuartersForYear($tables[$tIndex]['table'][$yearKey]);

    // Оновлюємо form_state (щоб значення збереглися і в подальшому сабміті)
    $form_state->setValue('tables', $tables);

    // Підставляємо значення у рендер-дерево для відображення (Q1..Q4)
      foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $val = $tables[$tIndex]['table'][$yearKey][$q] ?? '';
        $form['tables'][$tIndex]['table'][$yearKey][$q]['#value'] = ($val === NULL) ? '' : $val;
        $form['tables'][$tIndex]['table'][$yearKey][$q]['#default_value'] = ($val === NULL) ? '' : $val;
      }


    // Повертаємо саме ту таблицю, що має wrapper "table-wrapper-{$tIndex}"
    return $form['tables'][$tIndex]['table'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->getValue('tables');

    $allPeriods = [];

    foreach ($tables as $index => $tableWrapper) {
      $table = isset($tableWrapper['table']) && is_array($tableWrapper['table'])
        ? $tableWrapper['table']
        : (is_array($tableWrapper) ? $tableWrapper : []);

      $anchor = "tables][$index][table";

      $filledMonths = $this->getFilledMonths($table);

      if (empty($filledMonths)) {
        $form_state->setErrorByName($anchor, $this->t('Таблиця @num не містить жодного заповненого місяця.', ['@num' => $index + 1]));
        continue;
      }

      // Розриви по місяцях (у т.ч. на межі років)
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

      // Розриви по роках (якщо років > 1)
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

      $allPeriods[] = [
        'start' => $filledMonths[0],
        'end'   => $filledMonths[count($filledMonths) - 1],
      ];
    }

    // Всі таблиці мають бути за однаковий період
    if (!empty($allPeriods)) {
      $firstStart = $allPeriods[0]['start'];
      $firstEnd   = $allPeriods[0]['end'];

      foreach ($allPeriods as $i => $p) {
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
    // Додатково гарантуємо коректність кварталів при сабміті
    $tables = $form_state->getValue('tables') ?? [];
    foreach ($tables as $tIndex => &$tableWrapper) {
      if (!isset($tableWrapper['table']) || !is_array($tableWrapper['table'])) {
        continue;
      }
      foreach ($tableWrapper['table'] as $year => &$months) {
        $this->computeQuartersForYear($months);
      }
    }
    unset($months);

    $form_state->setValue('tables', $tables);

    \Drupal::messenger()->addMessage($this->t('Форма успішно надіслана. Квартальні значення розраховані.'));
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
      reset($years);
    }
    unset($years);

    $form_state->set('tables', $tables);
    $form_state->setRebuild(TRUE);
  }

  // ----------------- Допоміжні методи (валідації) -----------------

  private function getFilledMonths(array $table): array {
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
        continue;
      }

      foreach ($months as $monthName => $value) {
        if (!isset($monthMap[$monthName])) {
          continue; // пропускаємо Q1..Q4, YTD
        }
        // Перевіряємо, чи значення не пусте (включаючи 0)
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

  // ----------------- Допоміжні методи (квартали) -----------------

  /**
   * Обчислює Q1..Q4 для заданого року (масив місяців/кварталів).
   * Формула: ((M1 + M2 + M3) + 1) / 3
   * Порожні місяці = 0; якщо всі три = 0 → квартал = NULL.
   * Округлення до 0.05.
   */
  private function computeQuartersForYear(array &$months): void {
    $map = [
      'Q1' => ['Jan','Feb','Mar'],
      'Q2' => ['Apr','May','Jun'],
      'Q3' => ['Jul','Aug','Sep'],
      'Q4' => ['Oct','Nov','Dec'],
    ];

    foreach ($map as $q => $mList) {
      $sum = 0;
      $allEmpty = true;
      
      foreach ($mList as $m) {
        $v = $months[$m] ?? '';
        // Перевіряємо, чи місяць заповнений (включаючи 0)
        if ($v !== '' && $v !== NULL) {
          $sum += (float) $v;
          $allEmpty = false;
        }
      }

      if ($allEmpty) {
        $months[$q] = NULL; // всі місяці пусті - квартал NULL
        continue;
      }

      $raw = ($sum + 1) / 3;
      $months[$q] = $this->roundToStep($raw, 0.05); // округлення до 0.05
    }
  }

  /**
   * Стабільне округлення до кроку (0.05 => множник 20).
   */
  private function roundToStep(float $value, float $step): float {
    $factor = (int) round(1 / $step);
    return round($value * $factor) / $factor;
  }

}

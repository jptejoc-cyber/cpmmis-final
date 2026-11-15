<?php
declare(strict_types=1);

/**
 * CPMMIS Project Gantt Chart Generator
 *
 * Reads the 7-phase SDLC schedule from docs/cpmmis-project-schedule-table.md
 * (embedded below so the chart is reproducible) and renders a standalone,
 * publication-ready SVG.
 *
 * Usage:
 *   php docs/generate_gantt_chart.php [output.svg]
 */

$phases = [
    [
        'num' => 1,
        'name' => 'Plan',
        'color' => '#0ea5e9',
        'stroke' => '#0369a1',
        'start' => '2026-03-02',
        'end' => '2026-03-17',
        'tasks' => [
            ['Document Analysis', 'All', '2026-03-03', '2026-03-05', 100],
            ['Data Gathering', 'All', '2026-03-06', '2026-03-08', 100],
            ['Process Analysis', 'All', '2026-03-09', '2026-03-10', 100],
            ['Conduct of RRL', 'Janealyn Perez', '2026-03-11', '2026-03-13', 100],
            ['Study Objectives', 'Trisha Angeli Antoniano', '2026-03-14', '2026-03-14', 100],
            ['Scope & Delimitation', 'Janealyn Perez', '2026-03-15', '2026-03-15', 100],
            ['Gantt Preparation', 'Trisha Angeli Antoniano', '2026-03-16', '2026-03-16', 100],
            ['Feasibility Study', 'Trisha Angeli Antoniano', '2026-03-17', '2026-03-17', 100],
        ],
    ],
    [
        'num' => 2,
        'name' => 'Design',
        'color' => '#7c3aed',
        'stroke' => '#5b21b6',
        'start' => '2026-03-18',
        'end' => '2026-03-28',
        'tasks' => [
            ['SDLC Model', 'Kartein Alicarte', '2026-03-19', '2026-03-20', 100],
            ['System Architecture', 'Kartein Alicarte', '2026-03-21', '2026-03-22', 100],
            ['ERD Design', 'Kartein Alicarte', '2026-03-23', '2026-03-24', 100],
            ['DFD Modeling', 'Kartein Alicarte', '2026-03-25', '2026-03-26', 100],
            ['UI Design', 'Kartein Alicarte', '2026-03-27', '2026-03-28', 100],
        ],
    ],
    [
        'num' => 3,
        'name' => 'Develop',
        'color' => '#2563eb',
        'stroke' => '#1d4ed8',
        'start' => '2026-03-29',
        'end' => '2026-04-24',
        'tasks' => [
            ['Front-End Development', 'Andrew Estrella', '2026-03-30', '2026-04-04', 100],
            ['Back-End Development', 'Andrew Estrella', '2026-04-05', '2026-04-12', 100],
            ['Module Coding', 'Andrew Estrella', '2026-04-13', '2026-04-22', 100],
            ['Code Refinement', 'All', '2026-04-23', '2026-04-24', 100],
        ],
    ],
    [
        'num' => 4,
        'name' => 'Test',
        'color' => '#d97706',
        'stroke' => '#b45309',
        'start' => '2026-04-25',
        'end' => '2026-05-25',
        'tasks' => [
            ['Unit & Integration Testing', 'Kartein Alicarte', '2026-04-25', '2026-05-05', 100],
            ['System & Analytics Validation', 'Andrew Estrella', '2026-05-06', '2026-05-16', 100],
            ['User Acceptance Testing', 'All', '2026-05-17', '2026-05-25', 100],
        ],
    ],
    [
        'num' => 5,
        'name' => 'Deploy',
        'color' => '#059669',
        'stroke' => '#047857',
        'start' => '2026-05-26',
        'end' => '2026-07-02',
        'tasks' => [
            ['System Deployment & Hosting', 'Andrew Estrella', '2026-05-26', '2026-06-08', 100],
            ['User Manual & Training', 'Janealyn Perez & Trisha Angeli Antoniano', '2026-06-09', '2026-06-23', 100],
            ['Initial Release', 'All', '2026-06-24', '2026-07-02', 100],
        ],
    ],
    [
        'num' => 6,
        'name' => 'Review',
        'color' => '#db2777',
        'stroke' => '#be185d',
        'start' => '2026-07-03',
        'end' => '2026-08-20',
        'tasks' => [
            ['Pilot Test Feedback', 'All', '2026-07-03', '2026-07-30', 100],
            ['Pre-Launch Bug Fixes', 'Andrew Estrella', '2026-08-01', '2026-08-30', 100],
        ],
    ],
    [
        'num' => 7,
        'name' => 'Launch',
        'color' => '#dc2626',
        'stroke' => '#b91c1c',
        'start' => '2026-08-21',
        'end' => '2026-11-27',
        'tasks' => [
            ['System Go-Live', 'All', '2026-08-21', '2026-09-18', 100],
            ['Final Defense & Documentation', 'All', '2026-09-19', '2026-11-27', 0],
        ],
    ],
];

$projectStart = new DateTimeImmutable('2026-03-02');
$projectEnd = new DateTimeImmutable('2026-11-27');
$dataDate = new DateTimeImmutable('2026-11-14');

// Summary bars span the phase window and all of its activities.
foreach ($phases as &$phase) {
    $phase['visual_start'] = $phase['start'];
    $phase['visual_end'] = $phase['end'];
    $phase['progress'] = 100;
    foreach ($phase['tasks'] as $task) {
        if ($task[2] < $phase['visual_start']) {
            $phase['visual_start'] = $task[2];
        }
        if ($task[3] > $phase['visual_end']) {
            $phase['visual_end'] = $task[3];
        }
        if ($task[4] < 100) {
            $phase['progress'] = 0;
        }
    }
}
unset($phase);

$totalDays = (int) $projectStart->diff($projectEnd)->days + 1;

/* ----------------------------------------------------------------------------
 * Canvas and column geometry
 * ------------------------------------------------------------------------- */
$canvasW = 1760;
$canvasH = 1160;
$frameX = 20;
$frameY = 20;
$frameW = 1720;
$frameH = 1120;

$contentLeft = 48;
$activityRight = 328;
$assigneeRight = 468;
$progressRight = 528;
$timelineX = 528;
$timelineW = 1160;
$timelineRight = $timelineX + $timelineW;
$scale = $timelineW / $totalDays;

$headerTop = 112;
$headerH = 40;
$rowsTop = $headerTop + $headerH;
$rowH = 27;

$rowCount = 0;
foreach ($phases as $phase) {
    $rowCount += 1 + count($phase['tasks']);
}
$rowsBottom = $rowsTop + ($rowCount * $rowH);

/* ----------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

$dayX = static function (string $date) use ($projectStart, $timelineX, $scale): float {
    $days = (int) $projectStart->diff(new DateTimeImmutable($date))->days;
    return $timelineX + ($days * $scale);
};

$barW = static function (string $start, string $end) use ($scale): float {
    $days = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
    return max(5.0, $days * $scale);
};

$assigneeLines = static function (string $assignee): array {
    if ($assignee === 'Janealyn Perez & Trisha Angeli Antoniano') {
        return ['Janealyn Perez &', 'Trisha A. Antoniano'];
    }
    return [$assignee];
};

$todayX = $dayX($dataDate->format('Y-m-d'));

/* ----------------------------------------------------------------------------
 * Month bands
 * ------------------------------------------------------------------------- */
$months = [];
$cursor = $projectStart;
while ($cursor <= $projectEnd) {
    $monthStart = $cursor;
    $monthEnd = $cursor->modify('last day of this month');
    if ($monthEnd > $projectEnd) {
        $monthEnd = $projectEnd;
    }
    $months[] = [
        'label' => $monthStart->format('F'),
        'start' => $monthStart->format('Y-m-d'),
        'end' => $monthEnd->format('Y-m-d'),
    ];
    $cursor = $monthStart->modify('first day of next month');
}

/* ----------------------------------------------------------------------------
 * SVG document
 * ------------------------------------------------------------------------- */
$svg = [];
$svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
$svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $canvasW . '" height="' . $canvasH . '" viewBox="0 0 ' . $canvasW . ' ' . $canvasH . '" role="img" aria-labelledby="ganttTitle ganttDesc">';
$svg[] = '<title id="ganttTitle">CPMMIS Project Development Gantt Chart</title>';
$svg[] = '<desc id="ganttDesc">Seven-phase SDLC schedule for the Capstone Project Management and Monitoring Information System, March 2 to November 27, 2026.</desc>';
$svg[] = <<<SVG
<defs>
  <filter id="cardShadow" x="-5%" y="-5%" width="110%" height="112%">
    <feDropShadow dx="0" dy="6" stdDeviation="12" flood-color="#0f172a" flood-opacity="0.10" />
  </filter>
  <pattern id="hatch" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
    <rect width="7" height="7" fill="#fee2e2" />
    <line x1="0" y1="0" x2="0" y2="7" stroke="#ef4444" stroke-width="2.2" />
  </pattern>
  <style>
    .title { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 21px; font-weight: 700; fill: #0f172a; letter-spacing: -0.2px; }
    .subtitle { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 10.8px; font-weight: 500; fill: #64748b; }
    .legend { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.8px; font-weight: 600; fill: #475569; }
    .th { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.8px; font-weight: 700; fill: #64748b; letter-spacing: 0.9px; }
    .month { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 10.4px; font-weight: 700; fill: #334155; }
    .phase-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 11.4px; font-weight: 800; fill: #0f172a; letter-spacing: 0.35px; }
    .phase-date { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.4px; font-weight: 600; fill: #64748b; }
    .task { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 10.7px; font-weight: 500; fill: #1e293b; }
    .task-num { font-weight: 700; fill: #94a3b8; }
    .assignee { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.5px; font-weight: 500; fill: #475569; }
    .pct { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.4px; font-weight: 700; fill: #047857; }
    .pct-open { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.8px; font-weight: 700; fill: #b91c1c; }
    .inprogress { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.5px; font-weight: 700; fill: #991b1b; }
    .today-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.6px; font-weight: 700; fill: #ffffff; letter-spacing: 0.5px; }
    .note { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.6px; font-weight: 500; fill: #64748b; }
    .source { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8px; font-weight: 400; fill: #94a3b8; }
  </style>
</defs>
<rect width="{$canvasW}" height="{$canvasH}" fill="#eef2f7" />
<rect x="{$frameX}" y="{$frameY}" width="{$frameW}" height="{$frameH}" rx="14" fill="#ffffff" stroke="#dbe3ec" stroke-width="1.2" filter="url(#cardShadow)" />
SVG;

// Title and subtitle.
$svg[] = '<text x="' . $contentLeft . '" y="54" class="title">CPMMIS — Project Development Gantt Chart</text>';
$svg[] = '<text x="' . $contentLeft . '" y="78" class="subtitle">Capstone Project Management &amp; Monitoring Information System  ·  7-phase SDLC  ·  March 2 – November 27, 2026  ·  Data date: November 14, 2026</text>';

// Legend.
$legendY = 68;
$svg[] = '<rect x="1118" y="' . ($legendY - 5.5) . '" width="14" height="11" rx="2" fill="#2563eb" />';
$svg[] = '<text x="1138" y="' . ($legendY + 3) . '" class="legend">Complete</text>';
$svg[] = '<rect x="1228" y="' . ($legendY - 5.5) . '" width="14" height="11" rx="2" fill="url(#hatch)" stroke="#dc2626" stroke-width="0.9" />';
$svg[] = '<text x="1248" y="' . ($legendY + 3) . '" class="legend">In progress</text>';
$svg[] = '<rect x="1348" y="' . ($legendY - 4.5) . '" width="14" height="9" rx="2" fill="#334155" />';
$svg[] = '<text x="1368" y="' . ($legendY + 3) . '" class="legend">Phase summary</text>';
$svg[] = '<line x1="1502" y1="' . $legendY . '" x2="1524" y2="' . $legendY . '" stroke="#dc2626" stroke-width="1.4" stroke-dasharray="5 4" opacity="0.85" />';
$svg[] = '<text x="1530" y="' . ($legendY + 3) . '" class="legend">Today (Nov 14)</text>';

// Table header.
$svg[] = '<rect x="' . $timelineX . '" y="' . $headerTop . '" width="' . $timelineW . '" height="' . $headerH . '" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1" />';
$svg[] = '<text x="60" y="133" class="th">ACTIVITY / PHASE</text>';
$svg[] = '<text x="336" y="133" class="th">ASSIGNED TO</text>';
$svg[] = '<text x="498" y="133" class="th" text-anchor="middle">PROGRESS</text>';

// Month labels and separators.
foreach ($months as $index => $month) {
    $x1 = $dayX($month['start']);
    $x2 = $dayX($month['end']) + $scale;
    $center = ($x1 + $x2) / 2;
    $svg[] = '<text x="' . round($center, 2) . '" y="135" class="month" text-anchor="middle">' . $h($month['label']) . '</text>';
    if ($index > 0) {
        $svg[] = '<line x1="' . round($x1, 2) . '" y1="' . $headerTop . '" x2="' . round($x1, 2) . '" y2="' . $rowsBottom . '" stroke="#e2e8f0" stroke-width="1" />';
    }
}

// Weekly ticks at the bottom of the header.
$tick = $projectStart;
while ($tick <= $projectEnd) {
    if ((int) $tick->format('N') === 1) {
        $x = $dayX($tick->format('Y-m-d'));
        $svg[] = '<line x1="' . round($x, 2) . '" y1="' . ($headerTop + $headerH - 6) . '" x2="' . round($x, 2) . '" y2="' . ($headerTop + $headerH - 1) . '" stroke="#cbd5e1" stroke-width="1" />';
    }
    $tick = $tick->modify('+1 day');
}

// Column rules and header baseline.
$svg[] = '<line x1="' . $activityRight . '" y1="' . $headerTop . '" x2="' . $activityRight . '" y2="' . $rowsBottom . '" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = '<line x1="' . $assigneeRight . '" y1="' . $headerTop . '" x2="' . $assigneeRight . '" y2="' . $rowsBottom . '" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = '<line x1="' . $progressRight . '" y1="' . $headerTop . '" x2="' . $progressRight . '" y2="' . $rowsBottom . '" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = '<line x1="' . $timelineRight . '" y1="' . $headerTop . '" x2="' . $timelineRight . '" y2="' . $rowsBottom . '" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = '<line x1="' . $contentLeft . '" y1="' . $rowsTop . '" x2="' . $timelineRight . '" y2="' . $rowsTop . '" stroke="#94a3b8" stroke-width="1.1" />';

// Rows.
$y = $rowsTop;
foreach ($phases as $phase) {
    $centerY = $y + ($rowH / 2);
    $phaseBarX = $dayX($phase['visual_start']);
    $phaseBarW = $barW($phase['visual_start'], $phase['visual_end']);

    // Phase row band.
    $svg[] = '<rect x="' . $contentLeft . '" y="' . $y . '" width="' . ($timelineRight - $contentLeft) . '" height="' . $rowH . '" fill="' . $phase['color'] . '" fill-opacity="0.09" />';
    $svg[] = '<rect x="' . $contentLeft . '" y="' . $y . '" width="3" height="' . $rowH . '" fill="' . $phase['color'] . '" />';
    $svg[] = '<text x="60" y="' . round($centerY, 2) . '" class="phase-label" dominant-baseline="central">' . $phase['num'] . '. ' . $h(strtoupper($phase['name'])) . '</text>';
    $svg[] = '<text x="' . ($assigneeRight - 8) . '" y="' . round($centerY, 2) . '" class="phase-date" text-anchor="end" dominant-baseline="central">'
        . $h((new DateTimeImmutable($phase['visual_start']))->format('M j') . ' – ' . (new DateTimeImmutable($phase['visual_end']))->format('M j'))
        . '</text>';
    if ($phase['progress'] === 100) {
        $svg[] = '<text x="498" y="' . round($centerY, 2) . '" class="pct" text-anchor="middle" dominant-baseline="central">100%</text>';
    } else {
        $svg[] = '<text x="498" y="' . round($centerY, 2) . '" class="pct-open" text-anchor="middle" dominant-baseline="central">In progress</text>';
    }

    // Summary bar with small end caps.
    $barY = $y + 9;
    $svg[] = '<rect x="' . round($phaseBarX, 2) . '" y="' . $barY . '" width="' . round($phaseBarW, 2) . '" height="9" rx="2" fill="#334155" />';
    $svg[] = '<rect x="' . round($phaseBarX, 2) . '" y="' . ($barY + 9) . '" width="2" height="4" fill="#334155" />';
    $svg[] = '<rect x="' . round($phaseBarX + $phaseBarW - 2, 2) . '" y="' . ($barY + 9) . '" width="2" height="4" fill="#334155" />';

    $svg[] = '<line x1="' . $contentLeft . '" y1="' . ($y + $rowH) . '" x2="' . $timelineRight . '" y2="' . ($y + $rowH) . '" stroke="#e2e8f0" stroke-width="1" />';
    $y += $rowH;

    foreach ($phase['tasks'] as $index => $task) {
        [$name, $assignee, $start, $end, $progress] = $task;
        $centerY = $y + ($rowH / 2);
        $taskX = $dayX($start);
        $taskW = $barW($start, $end);

        $svg[] = '<text x="60" y="' . round($centerY, 2) . '" class="task" dominant-baseline="central">'
            . '<tspan class="task-num">' . $phase['num'] . '.' . ($index + 1) . '</tspan>  ' . $h($name) . '</text>';

        $lines = $assigneeLines($assignee);
        if (count($lines) === 1) {
            $svg[] = '<text x="336" y="' . round($centerY, 2) . '" class="assignee" dominant-baseline="central">' . $h($lines[0]) . '</text>';
        } else {
            $svg[] = '<text x="336" y="' . round($centerY - 4.6, 2) . '" class="assignee" dominant-baseline="central">' . $h($lines[0]) . '</text>';
            $svg[] = '<text x="336" y="' . round($centerY + 5.2, 2) . '" class="assignee" dominant-baseline="central">' . $h($lines[1]) . '</text>';
        }

        if ($progress === 100) {
            $svg[] = '<text x="498" y="' . round($centerY, 2) . '" class="pct" text-anchor="middle" dominant-baseline="central">100%</text>';
        } else {
            $svg[] = '<text x="498" y="' . round($centerY, 2) . '" class="pct-open" text-anchor="middle" dominant-baseline="central">In progress</text>';
        }

        if ($progress === 100) {
            $svg[] = '<rect x="' . round($taskX, 2) . '" y="' . ($y + 7) . '" width="' . round($taskW, 2) . '" height="13" rx="3.5" fill="' . $phase['color'] . '" stroke="' . $phase['stroke'] . '" stroke-width="0.8" />';
        } else {
            $svg[] = '<rect x="' . round($taskX, 2) . '" y="' . ($y + 7) . '" width="' . round($taskW, 2) . '" height="13" rx="3.5" fill="url(#hatch)" stroke="' . $phase['stroke'] . '" stroke-width="1.3" stroke-dasharray="4 2" />';
            $labelX = $taskX + ($taskW / 2);
            $svg[] = '<text x="' . round($labelX, 2) . '" y="' . round($centerY, 2) . '" class="inprogress" text-anchor="middle" dominant-baseline="central">In progress</text>';
        }

        $svg[] = '<line x1="' . $contentLeft . '" y1="' . ($y + $rowH) . '" x2="' . $timelineRight . '" y2="' . ($y + $rowH) . '" stroke="#f1f5f9" stroke-width="1" />';
        $y += $rowH;
    }
}

// Today marker.
$svg[] = '<line x1="' . round($todayX, 2) . '" y1="' . $rowsTop . '" x2="' . round($todayX, 2) . '" y2="' . $rowsBottom . '" stroke="#dc2626" stroke-width="1.35" stroke-dasharray="5 4" opacity="0.85" />';
$svg[] = '<rect x="' . round($todayX - 45, 2) . '" y="1073" width="90" height="16" rx="8" fill="#dc2626" />';
$svg[] = '<text x="' . round($todayX, 2) . '" y="1081.4" class="today-label" text-anchor="middle" dominant-baseline="central">TODAY · NOV 14</text>';

// Footer.
$svg[] = '<line x1="' . $contentLeft . '" y1="1093" x2="' . $timelineRight . '" y2="1093" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = '<text x="' . $contentLeft . '" y="1110" class="note">All activities are 100% complete except 7.2 Final Defense &amp; Documentation, which is in progress on the data date.</text>';
$svg[] = '<text x="' . $contentLeft . '" y="1126" class="note">Activity colors follow their phase; phase bars summarize each SDLC phase and activity bars show planned start and end dates.</text>';
$svg[] = '<text x="' . $timelineRight . '" y="1110" class="source" text-anchor="end">Source: CPMMIS complete 7-phase SDLC activity schedule</text>';
$svg[] = '<text x="' . $timelineRight . '" y="1126" class="source" text-anchor="end">Bars reflect planned dates; actual dates and percent complete per source table</text>';

$svg[] = '</svg>';

$outputPath = $argv[1] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'cpmmis-gantt-chart.svg');
file_put_contents($outputPath, implode("\n", $svg) . "\n");
echo "Wrote {$outputPath}\n";
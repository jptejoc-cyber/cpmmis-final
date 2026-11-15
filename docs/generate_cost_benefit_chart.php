<?php
declare(strict_types=1);

/**
 * CPMMIS Cost-Benefit Analysis — One-Page Visual Summary
 *
 * Data source: docs/cpmmis-cost-benefit-analysis.md (8-month base case).
 * Produces a standalone, publication-ready SVG.
 *
 * Usage:
 *   php docs/generate_cost_benefit_chart.php [output.svg]
 */

/* ----------------------------------------------------------------------------
 * Source data (base case from the CBA document)
 * ------------------------------------------------------------------------- */
$developmentCost = 376100;
$personnelCost = 276000;
$nonPersonnelCost = 100100;
$operatingCost = 75240;
$year1Total = $developmentCost + $operatingCost; // 451,340
$annualBenefit = 351000;

$benefits = [
    ['Adviser time recovered', 144000],
    ['Group leader time recovered', 80000],
    ['Administrative time recovered', 60000],
    ['Printing and photocopy avoided', 42000],
    ['Lost / reworked documents', 10000],
    ['Faster archive retrieval', 7500],
    ['Meeting coordination', 7500],
];

$cumulative = [-376100, -100340, 175420, 451180, 726940, 1002700];

$payback = 1 + (100340 / 275760);          // 1.3639 years
$threeYearRoi = 0.750;
$threeYearBcr = 1.75;
$fiveYearRoi = 1.333;
$fiveYearBcr = 2.33;

$sensitivity = [
    ['Conservative (-25%)', 263250, '2.00 yrs', '1.31 : 1'],
    ['Base case', 351000, '1.36 yrs', '1.75 : 1'],
    ['Optimistic (+20%)', 421200, '1.09 yrs', '2.10 : 1'],
];

// Schedule-aligned 9-month variant (from the CBA document's schedule note).
$schedDevelopment = 421050;
$schedYear1Total = $schedDevelopment + $operatingCost; // 496,290
$schedYear1Net = $annualBenefit - $schedYear1Total;    // -145,290
$schedPayback = 1 + (145290 / 275760);                 // 1.5269
$schedBcr3 = 1053000 / ($schedDevelopment + 150480);   // 1.842
$schedBcr5 = 1755000 / ($schedDevelopment + 300960);   // 2.431

$dataDate = 'November 14, 2026';

/* ----------------------------------------------------------------------------
 * Canvas
 * ------------------------------------------------------------------------- */
$canvasW = 1760;
$canvasH = 1160;
$frameX = 20;
$frameY = 20;
$frameW = 1720;
$frameH = 1120;

$contentLeft = 48;
$contentRight = 1712;
$contentW = $contentRight - $contentLeft;

/* ----------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
$peso = static fn(float $n): string => '₱' . number_format($n, 0);
$compact = static function (float $n): string {
    if (abs($n) >= 1000000) {
        return '₱' . number_format($n / 1000000, 2) . 'M';
    }
    if (abs($n) >= 1000) {
        return '₱' . number_format($n / 1000, 0) . 'k';
    }
    return '₱' . number_format($n, 0);
};
$text = static function (float $x, float $y, string $class, string $value, string $anchor = '', string $extra = '') use ($h): string {
    $anchorAttr = $anchor !== '' ? ' text-anchor="' . $anchor . '"' : '';
    $extraAttr = $extra !== '' ? ' ' . $extra : '';
    return '<text x="' . round($x, 2) . '" y="' . round($y, 2) . '" class="' . $class . '"' . $anchorAttr . $extraAttr . '>' . $h($value) . '</text>';
};

/* ----------------------------------------------------------------------------
 * SVG
 * ------------------------------------------------------------------------- */
$svg = [];
$svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
$svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $canvasW . '" height="' . $canvasH . '" viewBox="0 0 ' . $canvasW . ' ' . $canvasH . '" role="img" aria-labelledby="cbaTitle cbaDesc">';
$svg[] = '<title id="cbaTitle">CPMMIS Cost-Benefit Analysis at a Glance</title>';
$svg[] = '<desc id="cbaDesc">One-page cost-benefit summary for the Capstone Project Management and Monitoring Information System, including development and operating costs, annual benefits, cumulative net benefit, payback period, and sensitivity analysis.</desc>';
$svg[] = <<<SVG
<defs>
  <filter id="cardShadow" x="-5%" y="-5%" width="110%" height="112%">
    <feDropShadow dx="0" dy="6" stdDeviation="12" flood-color="#0f172a" flood-opacity="0.10" />
  </filter>
  <filter id="panelShadow" x="-3%" y="-3%" width="106%" height="108%">
    <feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#0f172a" flood-opacity="0.05" />
  </filter>
  <linearGradient id="pnlGradient" x1="0" y1="290" x2="0" y2="545" gradientUnits="userSpaceOnUse">
    <stop offset="0%" stop-color="#10b981" stop-opacity="0.20" />
    <stop offset="73.3%" stop-color="#10b981" stop-opacity="0.10" />
    <stop offset="73.3%" stop-color="#ef4444" stop-opacity="0.10" />
    <stop offset="100%" stop-color="#ef4444" stop-opacity="0.20" />
  </linearGradient>
  <style>
    .title { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 21px; font-weight: 700; fill: #0f172a; letter-spacing: -0.2px; }
    .subtitle { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 10.6px; font-weight: 500; fill: #64748b; }
    .badge { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.4px; font-weight: 700; fill: #047857; letter-spacing: 0.5px; }
    .kpi-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.8px; font-weight: 700; fill: #64748b; letter-spacing: 0.8px; }
    .kpi-value { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 23px; font-weight: 700; fill: #0f172a; letter-spacing: -0.4px; }
    .kpi-sub { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.9px; font-weight: 500; fill: #64748b; }
    .panel-title { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 12.2px; font-weight: 700; fill: #0f172a; letter-spacing: -0.1px; }
    .panel-sub { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 500; fill: #64748b; }
    .bar-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 9.4px; font-weight: 700; fill: #334155; }
    .bar-text { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 10px; font-weight: 700; fill: #ffffff; }
    .bar-text-sub { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 600; fill: #ffffff; }
    .bar-text-dark { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 9px; font-weight: 700; fill: #1e3a8a; }
    .bar-text-dark-sub { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.8px; font-weight: 600; fill: #1e40af; }
    .bar-text-on-green { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 9.4px; font-weight: 700; fill: #064e3b; }
    .legend { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8px; font-weight: 600; fill: #475569; }
    .note { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 500; fill: #64748b; }
    .chart-axis { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8px; font-weight: 500; fill: #94a3b8; }
    .chart-year { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.4px; font-weight: 600; fill: #64748b; }
    .chart-value { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.4px; font-weight: 700; fill: #047857; }
    .chart-value-neg { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 700; fill: #b91c1c; }
    .be-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.6px; font-weight: 700; fill: #b45309; }
    .benefit-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 500; fill: #334155; }
    .benefit-value { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 700; fill: #0f172a; }
    .benefit-total { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 10px; font-weight: 700; fill: #047857; }
    .tile-label { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.7px; font-weight: 700; fill: #64748b; letter-spacing: 0.6px; }
    .tile-value { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 17px; font-weight: 700; fill: #0f172a; }
    .table-head { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.6px; font-weight: 700; fill: #94a3b8; letter-spacing: 0.5px; }
    .table-text { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.5px; font-weight: 500; fill: #334155; }
    .table-text-bold { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.5px; font-weight: 700; fill: #0f172a; }
    .callout-title { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 9.4px; font-weight: 700; fill: #0f172a; }
    .callout-text { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 8.2px; font-weight: 500; fill: #475569; }
    .footer { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; font-size: 7.8px; font-weight: 400; fill: #94a3b8; }
  </style>
</defs>
<rect width="{$canvasW}" height="{$canvasH}" fill="#eef2f7" />
<rect x="{$frameX}" y="{$frameY}" width="{$frameW}" height="{$frameH}" rx="14" fill="#ffffff" stroke="#dbe3ec" stroke-width="1.2" filter="url(#cardShadow)" />
SVG;

// Header.
$svg[] = $text($contentLeft, 54, 'title', 'CPMMIS — Cost-Benefit Analysis at a Glance');
$svg[] = $text($contentLeft, 78, 'subtitle', 'Capstone Project Management & Monitoring Information System  ·  8-month base case (32 weeks)  ·  Philippine pesos at 2026 price levels');
$svg[] = '<rect x="1516" y="40" width="196" height="28" rx="14" fill="#ecfdf5" stroke="#a7f3d0" stroke-width="1" />';
$svg[] = $text(1614, 58, 'badge', '3-YEAR BCR  1.75 : 1', 'middle');

/* ----------------------------------------------------------------------------
 * KPI cards
 * ------------------------------------------------------------------------- */
$kpis = [
    ['DEVELOPMENT COST', $peso($developmentCost), 'one-time · 1,640 person-hours', '#2563eb'],
    ['YEAR 1 OPERATING', $peso($operatingCost), $peso(6270) . ' / month', '#7c3aed'],
    ['YEAR 1 TOTAL COST', $peso($year1Total), 'development + operations', '#0f172a'],
    ['ANNUAL BENEFIT', $peso($annualBenefit), 'time, printing, archive', '#059669'],
    ['PAYBACK PERIOD', number_format($payback, 2) . ' years', '≈16 months · full cost', '#d97706'],
];

$kpiGap = 12;
$kpiW = ($contentW - (4 * $kpiGap)) / 5;
$kpiY = 105;
$kpiH = 88;
foreach ($kpis as $index => $kpi) {
    $x = $contentLeft + ($index * ($kpiW + $kpiGap));
    $svg[] = '<rect x="' . round($x, 2) . '" y="' . $kpiY . '" width="' . round($kpiW, 2) . '" height="' . $kpiH . '" rx="10" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />';
    $svg[] = '<rect x="' . round($x, 2) . '" y="' . $kpiY . '" width="' . round($kpiW, 2) . '" height="3" rx="1.5" fill="' . $kpi[3] . '" />';
    $svg[] = $text($x + 16, $kpiY + 25, 'kpi-label', $kpi[0]);
    $svg[] = $text($x + 16, $kpiY + 54, 'kpi-value', $kpi[1]);
    $svg[] = $text($x + 16, $kpiY + 74, 'kpi-sub', $kpi[2]);
}

/* ----------------------------------------------------------------------------
 * Panel 1 — Cost breakdown
 * ------------------------------------------------------------------------- */
$panel1X = 48;
$panel1Y = 215;
$panel1W = 800;
$panel1H = 380;
$svg[] = '<rect x="' . $panel1X . '" y="' . $panel1Y . '" width="' . $panel1W . '" height="' . $panel1H . '" rx="12" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="url(#panelShadow)" />';
$svg[] = $text(68, 245, 'panel-title', 'Cost breakdown');
$svg[] = $text(68, 262, 'panel-sub', 'Development and Year 1 operations');

$barX = 68;
$barW = 720;
$devY = 302;
$svg[] = $text(68, 292, 'bar-label', 'Development cost · ' . $peso($developmentCost));
$personnelW = $barW * ($personnelCost / $developmentCost);
$nonPersonnelW = $barW - $personnelW;
$svg[] = '<rect x="' . $barX . '" y="' . $devY . '" width="' . round($personnelW, 2) . '" height="34" rx="5" fill="#2563eb" />';
$svg[] = '<rect x="' . round($barX + $personnelW, 2) . '" y="' . $devY . '" width="' . round($nonPersonnelW, 2) . '" height="34" rx="5" fill="#93c5fd" />';
$svg[] = $text($barX + ($personnelW / 2), $devY + 14, 'bar-text', 'Personnel', 'middle');
$svg[] = $text($barX + ($personnelW / 2), $devY + 27, 'bar-text-sub', $peso($personnelCost) . '  ·  73.4%', 'middle');
$svg[] = $text($barX + $personnelW + ($nonPersonnelW / 2), $devY + 14, 'bar-text-dark', 'Non-personnel', 'middle');
$svg[] = $text($barX + $personnelW + ($nonPersonnelW / 2), $devY + 27, 'bar-text-dark-sub', $peso($nonPersonnelCost) . '  ·  26.6%', 'middle');

$year1Y = 376;
$svg[] = $text(68, 366, 'bar-label', 'Year 1 total cost · ' . $peso($year1Total));
$devShareW = $barW * ($developmentCost / $year1Total);
$opsShareW = $barW - $devShareW;
$svg[] = '<rect x="' . $barX . '" y="' . $year1Y . '" width="' . round($devShareW, 2) . '" height="34" rx="5" fill="#0f172a" />';
$svg[] = '<rect x="' . round($barX + $devShareW, 2) . '" y="' . $year1Y . '" width="' . round($opsShareW, 2) . '" height="34" rx="5" fill="#10b981" />';
$svg[] = $text($barX + ($devShareW / 2), $year1Y + 21, 'bar-text', 'Development  ' . $peso($developmentCost), 'middle');
$svg[] = $text($barX + $devShareW + ($opsShareW / 2), $year1Y + 14, 'bar-text-on-green', 'Operations', 'middle');
$svg[] = $text($barX + $devShareW + ($opsShareW / 2), $year1Y + 27, 'bar-text-on-green', $peso($operatingCost), 'middle');

// Legend.
$legendY = 437;
$legendItems = [
    [68, '#2563eb', 'Personnel 73.4%'],
    [238, '#93c5fd', 'Non-personnel 26.6%'],
    [438, '#0f172a', 'Development 83.3%'],
    [618, '#10b981', 'Operations 16.7%'],
];
foreach ($legendItems as [$lx, $color, $label]) {
    $svg[] = '<circle cx="' . $lx . '" cy="' . ($legendY - 2.5) . '" r="4" fill="' . $color . '" />';
    $svg[] = $text($lx + 10, $legendY, 'legend', $label);
}

// Notes.
$svg[] = '<circle cx="72" cy="470" r="2" fill="#94a3b8" />';
$svg[] = $text(82, 473, 'note', 'Personnel: 4 team members × 8 months · 1,640 person-hours.');
$svg[] = '<circle cx="72" cy="488" r="2" fill="#94a3b8" />';
$svg[] = $text(82, 491, 'note', 'Non-personnel: equipment depreciation, Internet, hosting, printing, and 10% contingency.');
$svg[] = '<circle cx="72" cy="506" r="2" fill="#94a3b8" />';
$svg[] = $text(82, 509, 'note', 'Software licenses: ₱0 — open-source PHP, MariaDB, Laragon, Git, Tailwind, and Chart.js.');
$svg[] = '<rect x="68" y="532" width="720" height="42" rx="8" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = $text(84, 549, 'kpi-label', 'EFFECTIVE COST');
$svg[] = $text(84, 566, 'bar-label', '₱47,013 per month  ·  ₱229 per person-hour');
$svg[] = $text(788, 566, 'note', '1,640 hours', 'end');

/* ----------------------------------------------------------------------------
 * Panel 2 — Cumulative net benefit chart
 * ------------------------------------------------------------------------- */
$panel2X = 880;
$panel2Y = 215;
$panel2W = 832;
$panel2H = 380;
$svg[] = '<rect x="' . $panel2X . '" y="' . $panel2Y . '" width="' . $panel2W . '" height="' . $panel2H . '" rx="12" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="url(#panelShadow)" />';
$svg[] = $text(900, 245, 'panel-title', 'Cumulative net benefit');
$svg[] = $text(900, 262, 'panel-sub', 'Full economic cost · 5-year view · break-even in year 1.36');

$chartX = 930;
$chartY = 290;
$chartW = 730;
$chartH = 255;
$chartMin = -400000;
$chartMax = 1100000;

$valueY = static function (float $value) use ($chartY, $chartH, $chartMin, $chartMax): float {
    return $chartY + (($chartMax - $value) / ($chartMax - $chartMin)) * $chartH;
};
$yearX = static function (float $year) use ($chartX, $chartW): float {
    return $chartX + (($year / 5) * $chartW);
};
$zeroY = $valueY(0);

// Gridlines and y-axis labels.
$ticks = [-400000, -200000, 0, 200000, 400000, 600000, 800000, 1000000];
foreach ($ticks as $tick) {
    $ty = $valueY((float) $tick);
    $svg[] = '<line x1="' . $chartX . '" y1="' . round($ty, 2) . '" x2="' . ($chartX + $chartW) . '" y2="' . round($ty, 2) . '" stroke="#eef2f7" stroke-width="1" />';
    $label = $tick === 0 ? '₱0' : (abs($tick) >= 1000000
        ? ($tick < 0 ? '−₱1.0M' : '₱1.0M')
        : ($tick < 0 ? '−₱' . number_format(abs($tick) / 1000, 0) . 'k' : '₱' . number_format($tick / 1000, 0) . 'k'));
    $svg[] = $text($chartX - 10, $ty + 2.6, 'chart-axis', $label, 'end');
}

// Zero line.
$svg[] = '<line x1="' . $chartX . '" y1="' . round($zeroY, 2) . '" x2="' . ($chartX + $chartW) . '" y2="' . round($zeroY, 2) . '" stroke="#94a3b8" stroke-width="1.1" stroke-dasharray="4 3" />';

// Area and line.
$points = [];
foreach ($cumulative as $year => $value) {
    $points[] = [$yearX((float) $year), $valueY((float) $value)];
}
$area = 'M ' . $chartX . ' ' . round($zeroY, 2);
foreach ($points as [$px, $py]) {
    $area .= ' L ' . round($px, 2) . ' ' . round($py, 2);
}
$area .= ' L ' . ($chartX + $chartW) . ' ' . round($zeroY, 2) . ' Z';
$svg[] = '<path d="' . $area . '" fill="url(#pnlGradient)" />';

$line = [];
foreach ($points as [$px, $py]) {
    $line[] = round($px, 2) . ',' . round($py, 2);
}
$svg[] = '<polyline points="' . implode(' ', $line) . '" fill="none" stroke="#0f172a" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" />';

// Year points and labels.
foreach ($points as $year => [$px, $py]) {
    $dotFill = $year === 0 ? '#ffffff' : '#ffffff';
    $svg[] = '<circle cx="' . round($px, 2) . '" cy="' . round($py, 2) . '" r="4" fill="' . $dotFill . '" stroke="#0f172a" stroke-width="1.8" />';
}
$svg[] = $text($chartX + 8, $valueY(-376100) - 12, 'chart-value-neg', '−₱376,100', '', '');
$svg[] = $text($yearX(1) + 8, $valueY(-100340) + 16, 'chart-value-neg', '−₱100,340');
$svg[] = $text($yearX(3) - 8, $valueY(451180) - 12, 'chart-value', '₱451,180', 'end');
$svg[] = $text($yearX(5) - 6, $valueY(1002700) - 12, 'chart-value', '₱1.00M', 'end');

// X-axis labels.
foreach ([0, 1, 2, 3, 4, 5] as $year) {
    $svg[] = $text($yearX((float) $year), 562, 'chart-year', 'Year ' . $year, 'middle');
}

// Break-even marker.
$beYear = $payback;
$beX = $yearX($beYear);
$svg[] = '<line x1="' . round($beX, 2) . '" y1="430" x2="' . round($beX, 2) . '" y2="' . round($zeroY, 2) . '" stroke="#f59e0b" stroke-width="1.2" stroke-dasharray="3 3" />';
$svg[] = '<circle cx="' . round($beX, 2) . '" cy="' . round($zeroY, 2) . '" r="5" fill="#f59e0b" stroke="#ffffff" stroke-width="2" />';
$svg[] = '<rect x="' . round($beX - 62, 2) . '" y="410" width="124" height="20" rx="10" fill="#fffbeb" stroke="#f59e0b" stroke-width="1" />';
$svg[] = $text($beX, 423, 'be-label', 'Break-even · 1.36 yrs', 'middle');

// Chart caption.
$svg[] = $text(900, 578, 'note', 'Cumulative net benefit = annual benefit − operating cost − development cost.');

/* ----------------------------------------------------------------------------
 * Panel 3 — Annual benefits
 * ------------------------------------------------------------------------- */
$panel3X = 48;
$panel3Y = 615;
$panel3W = 800;
$panel3H = 330;
$svg[] = '<rect x="' . $panel3X . '" y="' . $panel3Y . '" width="' . $panel3W . '" height="' . $panel3H . '" rx="12" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="url(#panelShadow)" />';
$svg[] = $text(68, 645, 'panel-title', 'Annual benefits · ' . $peso($annualBenefit));
$svg[] = $text(68, 662, 'panel-sub', 'Quantified recurring value at 100 capstone groups');

$benefitMax = 144000;
$benefitBarX = 270;
$benefitBarMaxW = 430;
$benefitRowStart = 692;
$benefitRowH = 29;
foreach ($benefits as $index => [$label, $value]) {
    $rowY = $benefitRowStart + ($index * $benefitRowH);
    $bw = max(4, $benefitBarMaxW * ($value / $benefitMax));
    $svg[] = $text(68, $rowY, 'benefit-label', $label);
    $svg[] = '<rect x="' . $benefitBarX . '" y="' . ($rowY - 10) . '" width="' . round($bw, 2) . '" height="13" rx="3" fill="#059669" opacity="' . (0.98 - ($index * 0.07)) . '" />';
    $svg[] = $text(828, $rowY, 'benefit-value', $peso($value), 'end');
}

$svg[] = '<line x1="68" y1="890" x2="828" y2="890" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = $text(68, 916, 'bar-label', 'Total annual benefit', '', '');
$svg[] = $text(828, 916, 'benefit-total', $peso($annualBenefit), 'end');
$svg[] = $text(68, 936, 'note', 'Sources: recovered staff and student time, avoided printing, faster retrieval, fewer lost documents.');

/* ----------------------------------------------------------------------------
 * Panel 4 — Investment indicators and sensitivity
 * ------------------------------------------------------------------------- */
$panel4X = 880;
$panel4Y = 615;
$panel4W = 832;
$panel4H = 330;
$svg[] = '<rect x="' . $panel4X . '" y="' . $panel4Y . '" width="' . $panel4W . '" height="' . $panel4H . '" rx="12" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="url(#panelShadow)" />';
$svg[] = $text(900, 645, 'panel-title', 'Investment indicators');
$svg[] = $text(900, 662, 'panel-sub', 'Full economic cost · sensitivity of payback and 3-year BCR');

$tiles = [
    ['3-YEAR ROI', '75.0%', '#2563eb'],
    ['3-YEAR BCR', '1.75 : 1', '#059669'],
    ['5-YEAR ROI', '133.3%', '#7c3aed'],
    ['5-YEAR BCR', '2.33 : 1', '#0d9488'],
];
$tileW = 396;
$tileH = 58;
foreach ($tiles as $index => $tile) {
    $col = $index % 2;
    $row = intdiv($index, 2);
    $tx = 900 + ($col * ($tileW + 12));
    $ty = 678 + ($row * ($tileH + 8));
    $svg[] = '<rect x="' . $tx . '" y="' . $ty . '" width="' . $tileW . '" height="' . $tileH . '" rx="8" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />';
    $svg[] = '<rect x="' . $tx . '" y="' . $ty . '" width="3" height="' . $tileH . '" rx="1.5" fill="' . $tile[2] . '" />';
    $svg[] = $text($tx + 16, $ty + 20, 'tile-label', $tile[0]);
    $svg[] = $text($tx + 16, $ty + 45, 'tile-value', $tile[1]);
}

// Sensitivity table.
$svg[] = $text(900, 825, 'bar-label', 'Sensitivity analysis');
$tableHeadY = 844;
$svg[] = $text(900, $tableHeadY, 'table-head', 'SCENARIO');
$svg[] = $text(1200, $tableHeadY, 'table-head', 'ANNUAL BENEFIT', 'end');
$svg[] = $text(1450, $tableHeadY, 'table-head', 'PAYBACK', 'end');
$svg[] = $text(1692, $tableHeadY, 'table-head', '3-YEAR BCR', 'end');

$rowY = 866;
foreach ($sensitivity as $index => [$scenario, $benefit, $pb, $bcr]) {
    if ($index === 1) {
        $svg[] = '<rect x="892" y="' . ($rowY - 14) . '" width="800" height="24" rx="4" fill="#eff6ff" />';
    }
    $class = $index === 1 ? 'table-text-bold' : 'table-text';
    $svg[] = $text(900, $rowY, $class, $scenario);
    $svg[] = $text(1200, $rowY, $class, $peso($benefit), 'end');
    $svg[] = $text(1450, $rowY, $class, $pb, 'end');
    $svg[] = $text(1692, $rowY, $class, $bcr, 'end');
    $rowY += 24;
}

/* ----------------------------------------------------------------------------
 * Callouts
 * ------------------------------------------------------------------------- */
$calloutY = 965;
$calloutH = 100;

// Cash basis.
$svg[] = '<rect x="48" y="' . $calloutY . '" width="820" height="' . $calloutH . '" rx="10" fill="#ecfdf5" stroke="#a7f3d0" stroke-width="1" />';
$svg[] = $text(68, 990, 'callout-title', 'Cash basis — student labor donated');
$svg[] = $text(68, 1012, 'callout-text', 'Development cash outlay ' . $peso(100100) . '  ·  Year 1 cash cost ' . $peso(175340) . '  ·  Year 1 net cash benefit ' . $peso(175660));
$svg[] = $text(68, 1032, 'callout-text', 'Cash payback ≈ 4 months of operation; this is the institution\'s actual cash view.');
$svg[] = $text(68, 1050, 'note', 'Full economic cost remains the correct basis for comparing CPMMIS with a commercially developed system.');

// 9-month schedule.
$svg[] = '<rect x="880" y="' . $calloutY . '" width="832" height="' . $calloutH . '" rx="10" fill="#fffbeb" stroke="#fde68a" stroke-width="1" />';
$svg[] = $text(900, 990, 'callout-title', 'Schedule-aligned view — 9-month Gantt (March–November 2026)');
$svg[] = $text(900, 1012, 'callout-text', 'Development ' . $peso($schedDevelopment) . '  ·  Year 1 total ' . $peso($schedYear1Total) . '  ·  Year 1 net ' . '−' . $peso(abs($schedYear1Net)));
$svg[] = $text(900, 1032, 'callout-text', 'Payback ≈ ' . number_format($schedPayback, 2) . ' years  ·  3-year BCR ≈ ' . number_format($schedBcr3, 2) . ' : 1  ·  5-year BCR ≈ ' . number_format($schedBcr5, 2) . ' : 1');
$svg[] = $text(900, 1050, 'note', 'Use this variant if the manuscript follows the 7-phase Gantt schedule instead of the 32-week base case.');

/* ----------------------------------------------------------------------------
 * Footer
 * ------------------------------------------------------------------------- */
$svg[] = '<line x1="48" y1="1084" x2="1712" y2="1084" stroke="#e2e8f0" stroke-width="1" />';
$svg[] = $text(48, 1102, 'footer', 'Planning estimates in Philippine pesos at 2026 price levels. Benefits assume 100 capstone groups, 12 advisers, and 2 admin staff.');
$svg[] = $text(1712, 1102, 'footer', 'Source: docs/cpmmis-cost-benefit-analysis.md', 'end');
$svg[] = $text(48, 1118, 'footer', 'Software licenses: ₱0 · open-source stack (PHP, MariaDB, Laragon, Git, GitHub Free, Tailwind, Chart.js).');
$svg[] = $text(1712, 1118, 'footer', 'Data date: ' . $dataDate, 'end');

$svg[] = '</svg>';

$outputPath = $argv[1] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'cpmmis-cost-benefit-chart.svg');
file_put_contents($outputPath, implode("\n", $svg) . "\n");
echo "Wrote {$outputPath}\n";
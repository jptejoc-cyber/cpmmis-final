<?php
/**
 * CPMMIS Decomposed DFD Level 1 Diagram Generator (Processes 1.0 - 7.0)
 * Generates standalone, crisp, publication-grade Gane & Sarson SVG diagrams.
 *
 * Matching the exact visual decomposition style requested:
 * - Central rounded Process box with ID header compartment
 * - External Entities on sides (Rectangles)
 * - Open-ended Gane & Sarson Data Stores (Numbered ID + Name)
 * - Clear orthogonal data flows with descriptive labels
 */

function generate_svg_header($width, $height, $process_num, $process_name, $subtitle = "Level 1 Decomposition · Gane & Sarson Notation") {
    $w_inner = $width - 30;
    $h_inner = $height - 30;
    return <<<SVG
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}" width="{$width}" height="{$height}">
  <defs>
    <!-- Sharp crisp arrow markers -->
    <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#1e293b" />
    </marker>
    <marker id="arrow-store" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#0284c7" />
    </marker>
    <filter id="shadow" x="-4%" y="-4%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity="0.08" />
    </filter>
  </defs>

  <style>
    .bg { fill: #f8fafc; }
    .card-bg { fill: #ffffff; }
    .title-main { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 16px; font-weight: 800; fill: #0f172a; letter-spacing: -0.2px; }
    .title-sub { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 11px; font-weight: 500; fill: #64748b; }
    .badge-pill { fill: #e0f2fe; stroke: #0284c7; stroke-width: 1; rx: 4px; }
    .badge-text { font-family: 'Segoe UI', sans-serif; font-size: 10px; font-weight: 700; fill: #0369a1; text-anchor: middle; }

    /* External Entity Styles */
    .entity-box { fill: #ffffff; stroke: #1e293b; stroke-width: 1.8; filter: url(#shadow); }
    .entity-title { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 13px; font-weight: 700; fill: #0f172a; text-anchor: middle; }
    .entity-type { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 9.5px; font-weight: 600; fill: #64748b; text-anchor: middle; letter-spacing: 0.3px; }

    /* Process Bubble Styles */
    .process-box { fill: #ffffff; stroke: #0f172a; stroke-width: 2.2; rx: 12px; ry: 12px; filter: url(#shadow); }
    .process-header-bg { fill: #f1f5f9; rx: 12px; ry: 12px; }
    .process-divider { stroke: #0f172a; stroke-width: 2; }
    .process-num { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 15px; font-weight: 800; fill: #0f172a; text-anchor: middle; }
    .process-title { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 13.5px; font-weight: 700; fill: #0f172a; text-anchor: middle; }
    .process-subtext { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 10px; font-weight: 500; fill: #475569; text-anchor: middle; }

    /* Gane & Sarson Data Store Styles */
    .store-bg { fill: #ffffff; }
    .store-id-bg { fill: #f1f5f9; stroke: #1e293b; stroke-width: 1.8; }
    .store-border-line { stroke: #1e293b; stroke-width: 1.8; stroke-linecap: square; }
    .store-id { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 11.5px; font-weight: 800; fill: #0f172a; text-anchor: middle; }
    .store-name { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 11px; font-weight: 700; fill: #1e293b; }
    .store-table { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 9px; font-weight: 500; fill: #64748b; }

    /* Flow Lines & Labels */
    .flow-line { stroke: #1e293b; stroke-width: 1.5; fill: none; marker-end: url(#arrow); stroke-linejoin: round; stroke-linecap: round; }
    .flow-store { stroke: #0284c7; stroke-width: 1.5; fill: none; marker-end: url(#arrow-store); stroke-linejoin: round; stroke-linecap: round; }
    .flow-bg { fill: #ffffff; opacity: 0.92; }
    .flow-label { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 10px; font-weight: 600; fill: #0f172a; }
    .flow-id { font-weight: 800; fill: #2563eb; font-size: 9.5px; }
    .flow-label-store { font-family: 'Segoe UI', -apple-system, system-ui, Roboto, sans-serif; font-size: 9.5px; font-weight: 600; fill: #0369a1; }
  </style>

  <!-- Background Canvas -->
  <rect width="100%" height="100%" class="bg" />

  <!-- Outer Frame -->
  <rect x="15" y="15" width="{$w_inner}" height="{$h_inner}" rx="8" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.2" />

  <!-- Header Banner -->
  <g transform="translate(35, 42)">
    <rect x="-8" y="-18" width="58" height="22" class="badge-pill" />
    <text x="21" y="-3" class="badge-text">DFD 1.0</text>
    <text x="65" y="-2" class="title-main">Process {$process_num} — {$process_name}</text>
    <text x="65" y="14" class="title-sub">Capstone Project Management &amp; Monitoring Information System (CPMMIS) · {$subtitle}</text>
  </g>
SVG;
}

function svg_footer() {
    return "</svg>\n";
}

/**
 * Draws the central Gane & Sarson Process
 */
function draw_process($id, $title, $subtext, $x, $y, $w = 270, $h = 95) {
    $header_h = 30;
    $cx = $x + ($w / 2);
    $y_div = $y + $header_h;
    $x_end = $x + $w;
    $y_num = $y + 20;
    $y_title = $y + 53;
    $y_sub = $y + 72;
    return <<<SVG
  <!-- ==================== PROCESS {$id} ==================== -->
  <g id="process-{$id}">
    <!-- Process Box Outer -->
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" class="process-box" />
    <!-- Top ID Compartment -->
    <line x1="{$x}" y1="{$y_div}" x2="{$x_end}" y2="{$y_div}" class="process-divider" />
    <text x="{$cx}" y="{$y_num}" class="process-num">{$id}</text>
    <!-- Bottom Name Compartment -->
    <text x="{$cx}" y="{$y_title}" class="process-title">{$title}</text>
    <text x="{$cx}" y="{$y_sub}" class="process-subtext">{$subtext}</text>
  </g>
SVG;
}

/**
 * Draws External Entity (Square/Rectangle)
 */
function draw_entity($id, $name, $type, $x, $y, $w = 170, $h = 75) {
    $cx = $x + ($w / 2);
    $y_title = $y + 34;
    $y_type = $y + 52;
    return <<<SVG
  <!-- Entity {$id}: {$name} -->
  <g id="entity-{$id}">
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" class="entity-box" />
    <text x="{$cx}" y="{$y_title}" class="entity-title">{$name}</text>
    <text x="{$cx}" y="{$y_type}" class="entity-type">{$type}</text>
  </g>
SVG;
}

/**
 * Draws Gane & Sarson Data Store (Left closed ID box, right open-ended lines)
 */
function draw_store($id, $name, $table, $x, $y, $w = 210, $h = 42) {
    $id_w = 46;
    $x_open = $x + $w;
    $x_mid = $x + ($id_w / 2);
    $y_mid = $y + ($h / 2) + 4;
    $x_div = $x + $id_w;
    $x_text = $x + $id_w + 10;
    $y_t1 = $y + 19;
    $y_t2 = $y + 33;
    $y_bot = $y + $h;
    return <<<SVG
  <!-- Data Store {$id}: {$name} -->
  <g id="store-{$id}">
    <rect x="{$x}" y="{$y}" width="{$w}" height="{$h}" class="store-bg" />
    <!-- ID compartment -->
    <rect x="{$x}" y="{$y}" width="{$id_w}" height="{$h}" class="store-id-bg" />
    <!-- Top & Bottom Open-ended lines -->
    <line x1="{$x_div}" y1="{$y}" x2="{$x_open}" y2="{$y}" class="store-border-line" />
    <line x1="{$x_div}" y1="{$y_bot}" x2="{$x_open}" y2="{$y_bot}" class="store-border-line" />
    <!-- Labels -->
    <text x="{$x_mid}" y="{$y_mid}" class="store-id">{$id}</text>
    <text x="{$x_text}" y="{$y_t1}" class="store-name">{$name}</text>
    <text x="{$x_text}" y="{$y_t2}" class="store-table">{$table}</text>
  </g>
SVG;
}

/**
 * Draws Flow Arrow with background box to prevent line interference
 */
function draw_flow($points, $flow_id, $label, $label_x, $label_y, $align = "left", $is_store = false) {
    $cls = $is_store ? "flow-store" : "flow-line";
    $lbl_cls = $is_store ? "flow-label-store" : "flow-label";
    $anchor = ($align === "center") ? "middle" : (($align === "right") ? "end" : "start");

    // Path string
    $d = "M " . implode(" L ", $points);

    // Approximate label width for clean background pill
    $len = strlen($flow_id ? ($flow_id . ": " . $label) : $label);
    $box_w = ($len * 6.0) + 10;
    $box_h = 16;
    $box_x = ($align === "center") ? ($label_x - ($box_w / 2)) : (($align === "right") ? ($label_x - $box_w) : ($label_x - 3));
    $box_y = $label_y - 12;

    $id_span = $flow_id ? "<tspan class=\"flow-id\">{$flow_id}: </tspan>" : "";

    return <<<SVG
  <path d="{$d}" class="{$cls}" />
  <rect x="{$box_x}" y="{$box_y}" width="{$box_w}" height="{$box_h}" class="flow-bg" rx="2" />
  <text x="{$label_x}" y="{$label_y}" class="{$lbl_cls}" text-anchor="{$anchor}">{$id_span}{$label}</text>
SVG;
}


// =========================================================================
// PROCESS 1.0: ACCOUNT & ACCESS MANAGEMENT
// =========================================================================
function build_process_1() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "1.0", "Account & Access Management");

    // Center Process
    $svg .= draw_process("1.0", "Account & Access Management", "Subprocesses 1.1 — 1.5", 385, 290, 270, 95);

    // External Entities
    $svg .= draw_entity("EE3", "Administrator", "External Actor", 45, 140, 175, 75);
    $svg .= draw_entity("EE1", "Research Group", "External Actor", 45, 430, 175, 75);
    $svg .= draw_entity("EE2", "Adviser", "External Actor", 820, 140, 175, 75);
    $svg .= draw_entity("EE4", "Email Service", "Gmail SMTP Relay", 820, 430, 175, 75);

    // Data Stores
    $svg .= draw_store("DS1", "User Accounts", "group, adviser, admin", 260, 90, 220, 42);
    $svg .= draw_store("DS10", "Login Logs", "login_logs audit", 560, 90, 210, 42);
    $svg .= draw_store("DS12", "Password Reset OTPs", "password_resets", 260, 560, 220, 42);
    $svg .= draw_store("DS13", "Rate Limits", "login_rate_limits", 560, 560, 210, 42);

    // External Flows
    // EE3 Administrator <-> 1.0
    $svg .= draw_flow(["220,165", "340,165", "340,310", "385,310"], "F5", "Approve/Decline Group, Reset Password", 228, 160, "left");
    $svg .= draw_flow(["385,330", "310,330", "310,195", "220,195"], "F6", "Pending Group Alert, Action Confirmation", 228, 209, "left");

    // EE1 Research Group <-> 1.0
    $svg .= draw_flow(["220,450", "330,450", "330,355", "385,355"], "F1", "Registration Data, Login, OTP Request", 228, 444, "left");
    $svg .= draw_flow(["385,375", "310,375", "310,480", "220,480"], "F2", "Pending Status, Auth Token, Reset Link", 228, 495, "left");

    // EE2 Adviser <-> 1.0
    $svg .= draw_flow(["820,165", "700,165", "700,310", "655,310"], "F3", "Adviser Credentials, OTP, Password Update", 812, 160, "right");
    $svg .= draw_flow(["655,330", "730,330", "730,195", "820,195"], "F4", "Auth Confirmation, Password Reset Status", 812, 209, "right");

    // EE4 Email Service <-> 1.0
    $svg .= draw_flow(["655,355", "730,355", "730,450", "820,450"], "F7", "Recipient Email & 6-Digit OTP Code", 812, 444, "right");
    $svg .= draw_flow(["820,480", "700,480", "700,375", "655,375"], "F8", "SMTP Delivery Result (Success/Fail)", 812, 495, "right");

    // Store Flows
    // DS1 <-> 1.0
    $svg .= draw_flow(["370,132", "370,290"], "", "Account Query & Credentials", 240, 240, "left", true);
    $svg .= draw_flow(["420,290", "420,132"], "", "Store Account / Hash / Status", 425, 240, "left", true);

    // 1.0 -> DS10
    $svg .= draw_flow(["610,290", "610,132"], "", "Record Login Audit (IP, Agent, Time)", 615, 240, "left", true);

    // 1.0 <-> DS12
    $svg .= draw_flow(["420,385", "420,560"], "", "Store / Burn OTP bcrypt Hash", 265, 475, "left", true);

    // 1.0 <-> DS13
    $svg .= draw_flow(["610,385", "610,560"], "", "Check & Increment Rate Limit Counters", 615, 475, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// PROCESS 2.0: ADVISING & GROUP MATCHING
// =========================================================================
function build_process_2() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "2.0", "Advising & Group Matching");

    // Center Process
    $svg .= draw_process("2.0", "Advising & Group Matching", "Subprocesses 2.1 — 2.3", 385, 290, 270, 95);

    // External Entities
    $svg .= draw_entity("EE1", "Research Group", "External Actor", 45, 290, 175, 75);
    $svg .= draw_entity("EE2", "Adviser", "External Actor", 820, 290, 175, 75);

    // Data Stores
    $svg .= draw_store("DS1", "User Accounts", "group, adviser records", 240, 90, 230, 42);
    $svg .= draw_store("DS2", "Adviser Requests", "adviser_requests queue", 570, 90, 230, 42);
    $svg .= draw_store("DS14", "Document Vault", "uploads/letters/ PDFs", 240, 560, 230, 42);
    $svg .= draw_store("DS7", "Notifications", "in-app notifications", 570, 560, 230, 42);

    // External Flows (Group <-> 2.0)
    $svg .= draw_flow(["220,310", "385,310"], "F9", "Adviser Selection & PDF Request Letter", 230, 303, "left");
    $svg .= draw_flow(["385,345", "220,345"], "F10", "Request Status, Assigned Adviser & Reason", 230, 360, "left");

    // External Flows (Adviser <-> 2.0)
    $svg .= draw_flow(["820,310", "655,310"], "F11", "Accept / Decline Decision & Reason", 810, 303, "right");
    $svg .= draw_flow(["655,345", "820,345"], "F12", "Incoming Request Details & Advisee Roster", 810, 360, "right");

    // Store Flows
    // DS1 <-> 2.0 (Check adviser slot / update group.adviser_id)
    $svg .= draw_flow(["350,132", "350,290"], "", "Verify Group Eligibility & Existing Adviser", 145, 220, "left", true);
    $svg .= draw_flow(["410,290", "410,132"], "", "Assign adviser_id upon Acceptance", 415, 220, "left", true);

    // DS2 <-> 2.0 (Store request, update status, auto-decline rivals)
    $svg .= draw_flow(["630,290", "630,132"], "", "Insert / Update Request Status (accepted/declined)", 635, 220, "left", true);

    // 2.0 -> DS14 (Store PDF letter)
    $svg .= draw_flow(["410,385", "410,560"], "", "Save Request Letter PDF (letter_<id>.pdf)", 195, 480, "left", true);

    // 2.0 -> DS7 (Notifications to Adviser)
    $svg .= draw_flow(["630,385", "630,560"], "", "Notify Adviser of Incoming Advisership Request", 635, 480, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// PROCESS 3.0: TASK & SUBMISSION MANAGEMENT
// =========================================================================
function build_process_3() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "3.0", "Task & Submission Management");

    // Center Process
    $svg .= draw_process("3.0", "Task & Submission Management", "Subprocesses 3.1 — 3.7", 385, 290, 270, 95);

    // External Entities
    $svg .= draw_entity("EE1", "Research Group", "External Actor", 45, 290, 175, 75);
    $svg .= draw_entity("EE2", "Adviser", "External Actor", 820, 290, 175, 75);

    // Data Stores
    $svg .= draw_store("DS3", "Tasks & Deadlines", "adviser_tasks & history", 240, 90, 230, 42);
    $svg .= draw_store("DS4", "Submissions", "group_submissions records", 570, 90, 230, 42);
    $svg .= draw_store("DS14", "Document Vault", "uploads/tasks/ & submissions/", 240, 560, 230, 42);
    $svg .= draw_store("DS7", "Notifications", "task & review alerts", 570, 560, 230, 42);

    // Flows between Adviser and 3.0
    $svg .= draw_flow(["820,310", "655,310"], "F13", "Task Criteria, Deadlines & Review Decisions", 810, 303, "right");
    $svg .= draw_flow(["655,345", "820,345"], "F14", "Submission Queue, Task Status & Advisee Health", 810, 360, "right");

    // Flows between Research Group and 3.0
    $svg .= draw_flow(["220,310", "385,310"], "F15", "Chapter Submission PDF & Unsubmit Requests", 230, 303, "left");
    $svg .= draw_flow(["385,345", "220,345"], "F16", "Task Deadlines, Review Notes & Health Status", 230, 360, "left");

    // Store Flows
    // DS3 <-> 3.0
    $svg .= draw_flow(["350,290", "350,132"], "", "Create Task, Edit Deadline & Log History", 145, 220, "left", true);
    $svg .= draw_flow(["410,132", "410,290"], "", "Query Task Deadlines & Verification", 415, 220, "left", true);

    // DS4 <-> 3.0
    $svg .= draw_flow(["630,290", "630,132"], "", "Store Submission & Update Review Status", 635, 220, "left", true);

    // 3.0 <-> DS14
    $svg .= draw_flow(["410,385", "410,560"], "", "Store / Retrieve Task Attachments & Chapter PDFs", 160, 480, "left", true);

    // 3.0 -> DS7
    $svg .= draw_flow(["630,385", "630,560"], "", "Post Task Notifications & Review Feedback Alerts", 635, 480, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// PROCESS 4.0: MEETING MANAGEMENT
// =========================================================================
function build_process_4() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "4.0", "Meeting Management");

    // Center Process
    $svg .= draw_process("4.0", "Meeting Management", "Subprocesses 4.1 — 4.3", 385, 290, 270, 95);

    // External Entities
    $svg .= draw_entity("EE1", "Research Group", "External Actor", 45, 290, 175, 75);
    $svg .= draw_entity("EE2", "Adviser", "External Actor", 820, 290, 175, 75);

    // Data Stores
    $svg .= draw_store("DS5", "Meeting Requests", "meeting_requests records", 240, 90, 230, 42);
    $svg .= draw_store("DS7", "Notifications", "consultation alerts & reminders", 570, 90, 230, 42);
    $svg .= draw_store("DS1", "User Accounts", "verify group-adviser link", 240, 560, 230, 42);
    $svg .= draw_store("DS11", "Activity Logs", "meeting audit actions", 570, 560, 230, 42);

    // External Flows
    // EE1 Research Group <-> 4.0
    $svg .= draw_flow(["220,310", "385,310"], "F17", "Consultation Request (Date, Time, Agenda)", 230, 303, "left");
    $svg .= draw_flow(["385,345", "220,345"], "F18", "Decision Notice, Remarks & Reschedule Offer", 230, 360, "left");

    // EE2 Adviser <-> 4.0
    $svg .= draw_flow(["820,310", "655,310"], "F19", "Accept / Decline Decision & Suggested Time", 810, 303, "right");
    $svg .= draw_flow(["655,345", "820,345"], "F20", "Incoming Consultation Alert & 24h Reminder", 810, 360, "right");

    // Store Flows
    // DS5 <-> 4.0
    $svg .= draw_flow(["350,290", "350,132"], "", "Store Consultation Request & Decision Status", 145, 220, "left", true);
    $svg .= draw_flow(["410,132", "410,290"], "", "Check 24h Upcoming Meetings for Reminders", 415, 220, "left", true);

    // 4.0 -> DS7
    $svg .= draw_flow(["630,290", "630,132"], "", "Insert Meeting Alerts & Reminder Notifications", 635, 220, "left", true);

    // 4.0 -> DS1
    $svg .= draw_flow(["410,385", "410,560"], "", "Validate Group's Active Adviser Assignment", 175, 480, "left", true);

    // 4.0 -> DS11
    $svg .= draw_flow(["630,385", "630,560"], "", "Log Meeting Requests, Decisions & Reschedules", 635, 480, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// PROCESS 5.0: COMMUNICATION & NOTIFICATIONS
// =========================================================================
function build_process_5() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "5.0", "Communication & Notifications");

    // Center Process
    $svg .= draw_process("5.0", "Communication & Notifications", "Subprocesses 5.1 — 5.5", 385, 290, 270, 95);

    // External Entities
    $svg .= draw_entity("EE1", "Research Group", "External Actor", 45, 290, 175, 75);
    $svg .= draw_entity("EE3", "Administrator", "External Actor", 820, 140, 175, 75);
    $svg .= draw_entity("EE2", "Adviser", "External Actor", 820, 440, 175, 75);

    // Data Stores
    $svg .= draw_store("DS6", "Chat Messages", "messages threads", 240, 90, 230, 42);
    $svg .= draw_store("DS8", "Admin Letters", "admin_letters metadata", 570, 90, 230, 42);
    $svg .= draw_store("DS7", "Notifications", "in-app notifications", 240, 560, 230, 42);
    $svg .= draw_store("DS14", "Document Vault", "uploads/letters/ PDFs", 570, 560, 230, 42);

    // External Flows
    // EE1 Research Group <-> 5.0
    $svg .= draw_flow(["220,310", "385,310"], "F21", "Send Chat Message & Mark-Read Request", 230, 303, "left");
    $svg .= draw_flow(["385,345", "220,345"], "F22", "Chat Feed, In-App Alerts & Letter Download", 230, 360, "left");

    // EE3 Administrator <-> 5.0
    $svg .= draw_flow(["820,165", "690,165", "690,310", "655,310"], "F25", "Post Admin Letter PDF & Broadcast Chat", 810, 160, "right");
    $svg .= draw_flow(["655,330", "720,330", "720,195", "820,195"], "F26", "Delivery Status & Inbound Chat Messages", 810, 209, "right");

    // EE2 Adviser <-> 5.0
    $svg .= draw_flow(["820,465", "690,465", "690,360", "655,360"], "F23", "Send Advisee Chat & Notification Ack", 810, 460, "right");
    $svg .= draw_flow(["655,375", "720,375", "720,495", "820,495"], "F24", "Chat Feed, Reminders & Letter Upload Alerts", 810, 509, "right");

    // Store Flows
    // DS6 <-> 5.0
    $svg .= draw_flow(["350,290", "350,132"], "", "Store One-to-One Chat Messages", 190, 220, "left", true);
    $svg .= draw_flow(["410,132", "410,290"], "", "Fetch Unread Counts & Message History", 415, 220, "left", true);

    // DS8 <-> 5.0
    $svg .= draw_flow(["630,290", "630,132"], "", "Insert Admin Letter Records & Recipient Rows", 635, 220, "left", true);

    // DS7 <-> 5.0
    $svg .= draw_flow(["350,385", "350,560"], "", "Query User Notifications & Update Read Flags", 130, 480, "left", true);
    $svg .= draw_flow(["410,560", "410,385"], "", "Insert System Alerts & Reminders", 415, 480, "left", true);

    // 5.0 <-> DS14
    $svg .= draw_flow(["630,385", "630,560"], "", "Save Uploaded Letter PDF & Serve Downloads", 635, 480, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// PROCESS 6.0: ARCHIVE & REPORTING
// =========================================================================
function build_process_6() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "6.0", "Archive & Reporting");

    // Center Process
    $svg .= draw_process("6.0", "Archive & Reporting", "Subprocesses 6.1 — 6.5", 385, 290, 270, 95);

    // External Entities
    $svg .= draw_entity("EE1", "Research Group", "External Actor", 45, 140, 175, 75);
    $svg .= draw_entity("EE2", "Adviser", "External Actor", 45, 440, 175, 75);
    $svg .= draw_entity("EE3", "Administrator", "External Actor", 820, 290, 175, 75);

    // Data Stores
    $svg .= draw_store("DS9", "Capstone Archive", "capstone_archive catalog", 240, 90, 230, 42);
    $svg .= draw_store("DS14", "Document Vault", "uploads/archive/ ZIPs & PDFs", 570, 90, 230, 42);
    $svg .= draw_store("DS4", "Submissions", "verify chapters 1-5 approved", 240, 560, 230, 42);
    $svg .= draw_store("DS11", "Activity Logs", "audit logs & reports", 570, 560, 230, 42);

    // External Flows
    // EE1 Research Group <-> 6.0
    $svg .= draw_flow(["220,165", "350,165", "350,310", "385,310"], "F27", "Archive Search Filters & Download Request", 228, 160, "left");
    $svg .= draw_flow(["385,330", "320,330", "320,195", "220,195"], "F28", "Search Catalog Results & Project PDF/ZIP", 228, 209, "left");

    // EE2 Adviser <-> 6.0
    $svg .= draw_flow(["220,465", "350,465", "350,360", "385,360"], "F29", "Archive Query & Prior Capstone Request", 228, 460, "left");
    $svg .= draw_flow(["385,375", "320,375", "320,495", "220,495"], "F30", "Archive Search Results & Document Files", 228, 509, "left");

    // EE3 Administrator <-> 6.0
    $svg .= draw_flow(["820,310", "655,310"], "F31", "Archive Upload, Auto-Archive & Report Filters", 810, 303, "right");
    $svg .= draw_flow(["655,345", "820,345"], "F32", "KPIs, Workload, Trend Analytics & CSV Exports", 810, 360, "right");

    // Store Flows
    // DS9 <-> 6.0
    $svg .= draw_flow(["350,290", "350,132"], "", "Store New Archive Metadata & Index Records", 130, 220, "left", true);
    $svg .= draw_flow(["410,132", "410,290"], "", "Search Projects by Title, Year & Category", 415, 220, "left", true);

    // 6.0 <-> DS14
    $svg .= draw_flow(["630,290", "630,132"], "", "Compile Final Project ZIP & Serve Archives", 635, 220, "left", true);

    // 6.0 <-> DS4
    $svg .= draw_flow(["410,560", "410,385"], "", "Verify All 5 Chapters Approved for Auto-Archive", 130, 480, "left", true);

    // 6.0 <-> DS11
    $svg .= draw_flow(["630,560", "630,385"], "", "Query Audit Logs for Activity Reports & KPIs", 635, 480, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// PROCESS 7.0: ADMINISTRATION & MAINTENANCE
// =========================================================================
function build_process_7() {
    $w = 1040;
    $h = 700;
    $svg = generate_svg_header($w, $h, "7.0", "Administration & Maintenance");

    // Center Process
    $svg .= draw_process("7.0", "Administration & Maintenance", "Subprocesses 7.1 — 7.4", 385, 290, 270, 95);

    // External Entity (Single Admin Actor on Left)
    $svg .= draw_entity("EE3", "Administrator", "External Actor", 45, 290, 175, 75);

    // Right-hand Side System Entity or Global Tables Store
    $svg .= draw_store("DS_ALL", "System Database", "all 14 tables (SQL snapshot)", 790, 290, 220, 75);

    // Data Stores (Top & Bottom)
    $svg .= draw_store("DS1", "User Accounts", "manage advisers & groups", 240, 90, 230, 42);
    $svg .= draw_store("DS10", "Login Logs", "truncate / audit purge", 570, 90, 230, 42);
    $svg .= draw_store("DS11", "Activity Logs", "truncate / audit purge", 240, 560, 230, 42);
    $svg .= draw_store("DS14", "Document Vault", "orphan cleanup & backup", 570, 560, 230, 42);

    // External Flows (Admin <-> 7.0)
    $svg .= draw_flow(["220,310", "385,310"], "F33", "Account Governance, DB Backup & Purge Commands", 230, 303, "left");
    $svg .= draw_flow(["385,345", "220,345"], "F34", "Governance Confirmation, SQL/ZIP Backup File", 230, 360, "left");

    // Store Flows
    // DS1 <-> 7.0 (Add Adviser, Ban Group, Reset Credentials)
    $svg .= draw_flow(["350,290", "350,132"], "", "Add Adviser / Ban Group / Set Passwords", 145, 220, "left", true);

    // 7.0 -> DS10 (Purge login logs)
    $svg .= draw_flow(["630,290", "630,132"], "", "Purge / Truncate Login Audit Logs", 635, 220, "left", true);

    // 7.0 -> DS11 (Purge & write purge log)
    $svg .= draw_flow(["410,385", "410,560"], "", "Purge Activity Logs & Write Maintenance Audit", 155, 480, "left", true);

    // 7.0 <-> DS14 (Backup vault files)
    $svg .= draw_flow(["630,385", "630,560"], "", "Full ZIP Archive File Extraction & Cleanup", 635, 480, "left", true);

    // 7.0 <-> DS_ALL (Stream SQL Backup & Optimize)
    $svg .= draw_flow(["655,310", "790,310"], "", "OPTIMIZE TABLE & Stream SQL Dump", 665, 303, "left", true);
    $svg .= draw_flow(["790,345", "655,345"], "", "Table Stats & Raw Table Data Stream", 665, 360, "left", true);

    $svg .= svg_footer();
    return $svg;
}


// =========================================================================
// RUN GENERATION FOR ALL 7 PROCESSES
// =========================================================================
$generators = [
    'cpmmis-dfd-process-1.0.svg' => 'build_process_1',
    'cpmmis-dfd-process-2.0.svg' => 'build_process_2',
    'cpmmis-dfd-process-3.0.svg' => 'build_process_3',
    'cpmmis-dfd-process-4.0.svg' => 'build_process_4',
    'cpmmis-dfd-process-5.0.svg' => 'build_process_5',
    'cpmmis-dfd-process-6.0.svg' => 'build_process_6',
    'cpmmis-dfd-process-7.0.svg' => 'build_process_7',
];

$output_dir = __DIR__;
foreach ($generators as $filename => $func) {
    $content = call_user_func($func);
    $path = $output_dir . '/' . $filename;
    file_put_contents($path, $content);
    echo "Generated: {$filename} (" . strlen($content) . " bytes)\n";
}

echo "All 7 Decomposed Level 1 DFD SVGs generated successfully!\n";

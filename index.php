<?php
include('backend/session_init.php');

include(__DIR__ . '/backend/db_config.php');

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    header("Location: " . $role . "/" . $role . "_dashboard.php");
    exit();
}

$csrf_token = generate_csrf_token();

$total_groups = 0;
$total_advisers = 0;
$total_projects = 0;

$result = $conn->query("SELECT COUNT(*) AS cnt FROM `group` WHERE status IS NULL OR status NOT IN ('inactive','archived')");
if ($result) { $total_groups = (int)$result->fetch_assoc()['cnt']; }

$result = $conn->query("SELECT COUNT(*) AS cnt FROM `adviser`");
if ($result) { $total_advisers = (int)$result->fetch_assoc()['cnt']; }

$result = $conn->query("SELECT COUNT(*) AS cnt FROM `capstone_archive`");
if ($result) { $total_projects = (int)$result->fetch_assoc()['cnt']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPMMIS | Carlos Hilado Memorial State University</title>
    <link rel="icon" type="image/svg+xml" href="chmsu.png">
    <meta name="color-scheme" content="light">
    <style>html{background:#F0F2F5;color-scheme:light}</style>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --chmsu-blue: #1877F2;
            --chmsu-blue-soft: #3b82f6;
            --chmsu-navy: #1668d1;
            --bg-deep: #F0F2F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-deep);
            color: #1c1e21;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ─── Ambient Glow (matches dashboard) ─── */
        .ambient-glow {
            position: fixed;
            top: -10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(24, 119, 242, 0.07) 0%, transparent 70%);
            z-index: -1;
            filter: blur(80px);
            pointer-events: none;
        }
        .ambient-glow-left {
            position: fixed;
            bottom: -15%;
            left: -8%;
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, rgba(24, 119, 242, 0.05) 0%, transparent 70%);
            z-index: -1;
            filter: blur(80px);
            pointer-events: none;
        }

        /* ─── Content Layer ─── */
        .content-layer {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ─── Top Nav ─── */
        .top-nav {
            padding: 1.5rem 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .top-nav .logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .top-nav .logo-icon {
            width: 46px;
            height: 46px;
            background: #ffffff;
            border: 1px solid #dadde1;
            border-radius: 14px;
            padding: 7px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        }
        .top-nav .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .top-nav .logo-text {
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: -0.03em;
            color: #1c1e21;
        }
        .top-nav .logo-text span {
            color: var(--chmsu-blue);
        }
        .top-nav .nav-tag {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #1877F2;
            background: rgba(24, 119, 242, 0.08);
            border: 1px solid rgba(24, 119, 242, 0.15);
            padding: 0.4rem 0.9rem;
            border-radius: 100px;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ─── Hero Section ─── */
        .hero-section {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            padding: 0 3rem 3rem;
            max-width: 1320px;
            margin: 0 auto;
            width: 100%;
            gap: 4rem;
        }

        /* ─── Left: Brand ─── */
        .hero-brand {
            display: flex;
            flex-direction: column;
            gap: 1.4rem;
        }
        .hero-brand .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(24, 119, 242, 0.08);
            border: 1px solid rgba(24, 119, 242, 0.15);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            width: fit-content;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--chmsu-blue);
        }
        .hero-brand .badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--chmsu-blue);
            box-shadow: 0 0 12px var(--chmsu-blue);
            animation: pulse 2s ease-in-out infinite;
        }
        .hero-brand h1 {
            font-size: clamp(2.4rem, 4.6vw, 3.9rem);
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
            color: #1c1e21;
        }
        .hero-brand h1 .highlight {
            background: linear-gradient(135deg, #1877F2 0%, #3b82f6 60%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-brand p.sub {
            font-size: 0.98rem;
            color: #65676b;
            line-height: 1.65;
            max-width: 480px;
            font-weight: 400;
        }
        .hero-brand .quote {
            font-size: 0.88rem;
            color: #1877F2;
            font-weight: 600;
            font-style: italic;
            border-left: 3px solid rgba(24, 119, 242, 0.5);
            padding-left: 1rem;
            background: rgba(24, 119, 242, 0.04);
            border-radius: 0 0.5rem 0.5rem 0;
            padding: 0.6rem 1rem;
            max-width: 480px;
        }
        .hero-brand .stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        .hero-brand .stats .stat-card {
            background: #ffffff;
            border: 1px solid #dadde1;
            border-radius: 1rem;
            padding: 1rem 1.4rem;
            min-width: 130px;
            text-align: left;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .hero-brand .stats .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.1);
            border-color: rgba(24, 119, 242, 0.4);
        }
        .hero-brand .stats .stat-card .num {
            font-size: 1.45rem;
            font-weight: 800;
            color: #1877F2;
        }
        .hero-brand .stats .stat-card .label {
            font-size: 0.65rem;
            color: #65676b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            margin-top: 0.15rem;
        }

        /* ─── Right: Login Card ─── */
        .login-card {
            background: #ffffff;
            border: 1px solid #dadde1;
            border-radius: 2rem;
            padding: 2.75rem;
            box-shadow:
                0 1px 2px rgba(0, 0, 0, 0.05),
                0 30px 60px rgba(24, 119, 242, 0.08);
            max-width: 440px;
            width: 100%;
            margin-left: auto;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-4px);
            box-shadow:
                0 1px 2px rgba(0, 0, 0, 0.05),
                0 40px 90px rgba(24, 119, 242, 0.12);
        }
        .login-card .card-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .login-card .card-brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }
        .login-card .card-header {
            margin-bottom: 1.75rem;
            text-align: center;
        }
        .login-card .card-header h2 {
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #1c1e21;
        }
        .login-card .card-header p {
            font-size: 0.8rem;
            color: #65676b;
            margin-top: 0.25rem;
            font-weight: 400;
        }
        .input-group {
            position: relative;
            margin-bottom: 1.1rem;
        }
        .input-group .input-wrap {
            display: flex;
            align-items: center;
            background: #F0F2F5;
            border: 1px solid #dadde1;
            border-radius: 0.9rem;
            padding: 0 1.15rem;
            transition: all 0.25s ease;
        }
        .input-group .input-wrap:focus-within {
            border-color: rgba(24, 119, 242, 0.55);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.1);
        }
        .input-group .input-wrap i,
        .input-group .input-wrap svg {
            color: #9aa0a6;
            font-size: 0.9rem;
            margin-right: 0.75rem;
            transition: color 0.25s;
            width: 16px;
            height: 16px;
            min-width: 16px;
            text-align: center;
        }
        .input-group .input-wrap:focus-within i,
        .input-group .input-wrap:focus-within svg {
            color: var(--chmsu-blue);
        }
        .input-group .input-wrap input {
            width: 100%;
            padding: 0.85rem 0;
            background: transparent;
            border: none;
            outline: none;
            color: #1c1e21;
            font-size: 0.86rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 500;
        }
        .input-group .input-wrap input::placeholder {
            color: #9aa0a6;
            font-weight: 400;
        }
        .input-group .input-wrap .toggle-vis {
            background: none;
            border: none;
            color: #9aa0a6;
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.25s;
            font-size: 0.85rem;
        }
        .input-group .input-wrap .toggle-vis:hover {
            color: #65676b;
        }
        .forgot-link {
            text-align: right;
            margin-top: -0.25rem;
            margin-bottom: 1.25rem;
        }
        .forgot-link button {
            background: none;
            border: none;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1877F2;
            cursor: pointer;
            transition: color 0.25s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .forgot-link button:hover {
            color: var(--chmsu-navy);
        }
        .btn-primary {
            width: 100%;
            padding: 0.95rem;
            border: none;
            border-radius: 0.9rem;
            background: linear-gradient(135deg, #1877F2 0%, #3b82f6 100%);
            color: #fff;
            font-weight: 800;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(24, 119, 242, 0.25);
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.15) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .btn-primary:hover::before {
            opacity: 1;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(24, 119, 242, 0.3);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #ffffff;
            border: 1px solid rgba(24, 119, 242, 0.35);
            color: var(--chmsu-blue);
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.8rem 1.5rem;
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-secondary:hover {
            border-color: var(--chmsu-blue);
            background: rgba(24, 119, 242, 0.06);
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.4rem 0;
        }
        .divider .line {
            flex: 1;
            height: 1px;
            background: #dadde1;
        }
        .divider span {
            font-size: 0.62rem;
            color: #9aa0a6;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* ─── Toast ─── */
        #toast {
            position: fixed;
            top: 1.5rem;
            left: 50%;
            transform: translateX(-50%) translateY(-30px);
            z-index: 9999;
            pointer-events: none;
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        #toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* ─── Modal ─── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border: 1px solid #dadde1;
            border-radius: 1.75rem;
            padding: 2.5rem;
            max-width: 470px;
            width: 100%;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.18);
        }
        .modal-close {
            position: absolute;
            top: 1.1rem;
            right: 1.1rem;
            background: none;
            border: none;
            color: #9aa0a6;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.25s;
        }
        .modal-close:hover { color: #1c1e21; }
        .modal-box h3 {
            font-size: 1.25rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.25rem;
            letter-spacing: -0.02em;
            color: #1c1e21;
        }
        .modal-box .modal-desc {
            font-size: 0.8rem;
            color: #65676b;
            text-align: center;
            margin-bottom: 1.4rem;
        }
        .modal-box .input-wrap {
            display: flex;
            align-items: center;
            background: #F0F2F5;
            border: 1px solid #dadde1;
            border-radius: 0.75rem;
            padding: 0 1rem;
            transition: all 0.25s ease;
            margin-bottom: 0.75rem;
        }
        .modal-box .input-wrap:focus-within {
            border-color: rgba(24, 119, 242, 0.55);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.1);
        }
        .modal-box .input-wrap i,
        .modal-box .input-wrap svg {
            color: #9aa0a6;
            font-size: 0.8rem;
            margin-right: 0.75rem;
            width: 14px;
            height: 14px;
            min-width: 14px;
            text-align: center;
        }
        .modal-box .input-wrap:focus-within i,
        .modal-box .input-wrap:focus-within svg {
            color: var(--chmsu-blue);
        }
        .modal-box .input-wrap input,
        .modal-box .input-wrap textarea {
            width: 100%;
            padding: 0.8rem 0;
            background: transparent;
            border: none;
            outline: none;
            color: #1c1e21;
            font-size: 0.82rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 500;
        }
        .modal-box .input-wrap input::placeholder,
        .modal-box .input-wrap textarea::placeholder {
            color: #9aa0a6;
        }
        .modal-box .input-wrap textarea {
            resize: none;
            min-height: 60px;
        }
        .modal-hint {
            text-align: left;
            font-size: 11px;
            margin: 0 0 0.75rem;
            color: #9aa0a6;
            line-height: 1.4;
        }
        .modal-cooldown {
            text-align: center;
            font-size: 11px;
            margin: 8px 0 0;
            color: #dc2626;
            font-weight: 700;
        }
        .modal-inline {
            text-align: center;
            font-size: 11px;
            margin: 10px 0 0;
            color: #9aa0a6;
        }
        .modal-inline button {
            background: none;
            border: none;
            color: #1877F2;
            font-weight: 700;
            font-size: 11px;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .otp-input {
            text-align: center;
            letter-spacing: 0.5em;
            font-weight: 700;
        }

        /* ─── Footer ─── */
        .footer {
            text-align: center;
            padding: 1.5rem 3rem;
            font-size: 0.65rem;
            color: #9aa0a6;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        /* ─── Scrollbar ─── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 3px; }

        /* ─── Responsive ─── */
        @media (max-width: 968px) {
            .top-nav { padding: 1rem 1.5rem; }
            .hero-section {
                grid-template-columns: 1fr;
                padding: 0 1.5rem 2rem;
                gap: 2.5rem;
            }
            .hero-brand { text-align: center; align-items: center; }
            .hero-brand .badge { align-self: center; }
            .hero-brand p.sub, .hero-brand .quote { max-width: 100%; text-align: left; }
            .hero-brand .stats { justify-content: center; }
            .login-card {
                margin: 0 auto;
                padding: 2rem;
                max-width: 100%;
            }
            .footer { padding: 1rem 1.5rem; }
        }
    </style>
</head>
<body>

    <!-- Ambient Background -->
    <div class="ambient-glow"></div>
    <div class="ambient-glow-left"></div>

    <!-- Toast -->
    <div id="toast">
        <div id="toast-bg" style="background:rgba(15,23,42,0.92);backdrop-filter:blur(20px);border:1px solid rgba(24,119,242,0.25);padding:0.75rem 1.5rem;border-radius:100px;display:flex;align-items:center;gap:0.75rem;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
            <div id="toast-icon-bg" style="width:32px;height:32px;background:rgba(24,119,242,0.8);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <svg id="toast-icon" viewBox="0 0 512 512" width="12" height="12"><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"/></svg>
            </div>
            <p id="toast-msg" style="color:#fff;font-weight:700;font-size:10px;letter-spacing:0.15em;text-transform:uppercase;white-space:nowrap;margin:0;"></p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content-layer">

        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="logo-wrap">
                <div class="logo-icon">
                    <img src="chmsu.png" alt="CHMSU">
                </div>
                <div class="logo-text">CP<span>MMIS</span></div>
            </div>
            <div class="nav-tag">CHMSU-FT</div>
        </nav>

        <!-- Hero + Login -->
        <section class="hero-section">
            <!-- Left: Branding -->
            <div class="hero-brand">
                <div class="badge">
                    <span class="dot"></span>
                    CHMSU-FT Information System
                </div>
                <h1>
                    Capstone Project<br>
                    <span class="highlight">Monitoring &amp; Management</span><br>
                    <span class="highlight">System</span>
                </h1>
                <p class="sub">
                    A centralized platform for managing, tracking, and evaluating capstone research projects — empowering students, advisers, and administrators through digital transformation.
                </p>
                <div class="quote">
                    "Excellence in Research through Information Systems"
                </div>
                <div class="stats">
                    <div class="stat-card">
                        <div class="num" id="statGroups"><?php echo $total_groups; ?></div>
                        <div class="label">Active Groups</div>
                    </div>
                    <div class="stat-card">
                        <div class="num" id="statAdvisers"><?php echo $total_advisers; ?></div>
                        <div class="label">Advisers</div>
                    </div>
                    <div class="stat-card">
                        <div class="num" id="statProjects"><?php echo $total_projects; ?></div>
                        <div class="label">Projects</div>
                    </div>
                </div>
            </div>

            <!-- Right: Login Card -->
            <div class="login-card">
                <div class="card-brand">
                    <img src="chmsu.png" alt="CHMSU">
                </div>
                <div class="card-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to access your dashboard</p>
                </div>

                <form id="loginForm" method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="input-group">
                        <div class="input-wrap">
                            <svg viewBox="0 0 448 512"><path fill="currentColor" d="M224 256c70.7 0 128-57.3 128-128S294.7 0 224 0 96 57.3 96 128s57.3 128 128 128zm89.6 32h-16.7c-22.2 10.2-46.9 16-72.9 16s-50.6-5.8-72.9-16h-16.7C60.2 288 0 348.2 0 422.4V464c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48v-41.6c0-74.2-60.2-134.4-134.4-134.4z"/></svg>
                            <input type="text" name="username" required placeholder="User ID or Handle">
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-wrap">
                            <svg viewBox="0 0 448 512"><path fill="currentColor" d="M400 224h-24v-72C376 68.2 307.8 0 224 0S72 68.2 72 152v72H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V272c0-26.5-21.5-48-48-48zm-104 0H152v-72c0-39.7 32.3-72 72-72s72 32.3 72 72v72z"/></svg>
                            <input type="password" id="login_pass" name="password" required minlength="8" placeholder="Password">
                            <button type="button" class="toggle-vis" data-toggle-vis="login_pass|eye_login">
                                <svg id="eye_login" viewBox="0 0 640 512"><path fill="currentColor" d="M320 400c-75.85 0-146.5-41.11-201.6-108.7C47.37 290.4 16.85 244.1 4.57 216.1c-1.183-26.6 7.522-49.49 22.75-65.1C40.37 135.7 100.2 96 176 96c38.6 0 73.8 13.8 101 37l13 10.6c7.5 6.2 18.2 6.2 25.7 0l13-10.6C363.2 109.8 398.4 96 437 96c75.8 0 135.6 39.7 149.7 55c15.2 15.6 24.3 38.5 22.75 65.1C601.2 236.4 570.6 282.7 521.6 291.3C466.5 358.9 395.8 400 320 400zM49.7 216.1C61.4 235.4 85.7 268 125.4 298.6 177.1 339.4 247 368 320 368s142.9-28.6 194.6-69.4c39.7-30.6 64-63.2 75.7-82.5C596.2 206.4 598.5 182.7 592 160c-7.7-27.5-30.4-60.6-84.8-87.4C477.7 46.8 418.9 32 352 32c-66.9 0-125.7 14.8-155.2 40.6C142.4 99.4 119.7 132.5 112 160c-6.5 22.7-4.2 46.4 7.7 56.1zM320 288c-35.3 0-64-28.7-64-64s28.7-64 64-64 64 28.7 64 64-28.7 64-64 64zm0-96c-17.6 0-32 14.4-32 32s14.4 32 32 32 32-14.4 32-32-14.4-32-32-32z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="forgot-link">
                        <button type="button" data-toggle-modal="forgot-modal">Forgot Password?</button>
                    </div>

                    <button type="submit" id="loginBtn" class="btn-primary">
                        Sign In
                    </button>

                    <div class="divider">
                        <span class="line"></span>
                        <span>New here?</span>
                        <span class="line"></span>
                    </div>

                    <button type="button" data-toggle-modal="signup-modal" class="btn-secondary" style="width:100%;">Register Your Group</button>
                </form>
            </div>
        </section>

        <div class="footer">
            &copy; 2026 Carlos Hilado Memorial State University — Fortune Towne &mdash; CPMMIS. All rights reserved.
        </div>
    </div>

    <!-- Sign Up Modal -->
    <div id="signup-modal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" data-toggle-modal="signup-modal"><svg viewBox="0 0 384 512" width="14" height="14"><path fill="currentColor" d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3l105.4 105.3c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256l105.3-105.4z"/></svg></button>
            <h3>Group Registration</h3>
            <p class="modal-desc">Register your capstone group to get started.</p>

            <form id="registerForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="input-wrap" style="margin-bottom:0.75rem;">
                    <svg viewBox="0 0 640 512" width="14" height="14"><path fill="currentColor" d="M96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm448 0c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm32 32h-64c-17.6 0-33.5 7.1-45.1 18.6 40.3 22.1 68.9 62 75.1 109.4h66c17.7 0 32-14.3 32-32v-32c0-35.3-28.7-64-64-64zm-256 0c61.9 0 112-50.1 112-112S381.9 0 320 0 208 50.1 208 112s50.1 112 112 112zm76.8 32h-8.3c-20.8 10-43.9 16-68.5 16s-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48v-28.8c0-63.6-51.6-115.2-115.2-115.2zm-299.2-96H48c-26.5 0-48 21.5-48 48v32c0 26.5 21.5 48 48 48h67.2C110.2 376.9 96 351.4 96 323.2c0-84.9 68.8-153.6 153.6-153.6 42.5 0 81.5 17.3 109.4 45.1C374.1 221.3 367 224 359.4 224H352c-35.3 0-64 28.7-64 64s28.7 64 64 64h6.9c12.8 32.4 37.1 58.8 68.2 72.9V432c0-17.7-14.3-32-32-32H192v-64c0-35.3-28.7-64-64-64H64c-35.3 0-64 28.7-64 64v32c0 35.3 28.7 64 64 64h64v64c0 17.7 14.3 32 32 32h288c17.7 0 32-14.3 32-32v-28.8c0-63.6-51.6-115.2-115.2-115.2H352c-53 0-96-43-96-96z"/></svg>
                    <input type="text" name="group_name" required maxlength="50" placeholder="Unique Group ID">
                </div>
                <div class="input-wrap" style="margin-bottom:0.75rem;">
                    <svg viewBox="0 0 448 512" width="14" height="14"><path fill="currentColor" d="M96 48H48C21.5 48 0 69.5 0 96v64c0 26.5 21.5 48 48 48h48v272c0 26.5 21.5 48 48 48h256c26.5 0 48-21.5 48-48V208h48c26.5 0 48-21.5 48-48V96c0-26.5-21.5-48-48-48h-48V48c0-26.5-21.5-48-48-48H144c-26.5 0-48 21.5-48 48zm352 128h-48V96H96v80h304zm0 144H96V480h256V320zm-176-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16s16-7.2 16-16v-32c0-8.8-7.2-16-16-16zm-96 0c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16s16-7.2 16-16v-32c0-8.8-7.2-16-16-16zm192 0c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16s16-7.2 16-16v-32c0-8.8-7.2-16-16-16z"/></svg>
                    <textarea name="project_title" required maxlength="255" placeholder="Project Title" style="text-transform:uppercase;"></textarea>
                </div>
                <div class="input-wrap" style="margin-bottom:0.75rem;">
                    <svg viewBox="0 0 448 512" width="14" height="14"><path fill="currentColor" d="M224 256c70.7 0 128-57.3 128-128S294.7 0 224 0 96 57.3 96 128s57.3 128 128 128zm89.6 32h-16.7c-22.2 10.2-46.9 16-72.9 16s-50.6-5.8-72.9-16h-16.7C60.2 288 0 348.2 0 422.4V464c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48v-41.6c0-74.2-60.2-134.4-134.4-134.4z"/></svg>
                    <input type="text" name="leader_name" required maxlength="255" placeholder="Leader Complete Name">
                </div>
                <div class="input-wrap" style="margin-bottom:0.75rem;">
                    <svg viewBox="0 0 448 512" width="14" height="14"><path fill="currentColor" d="M48 80c0-8.8 7.2-16 16-16h352c8.8 0 16 7.2 16 16v352c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V80zm336-48L236.8 198.8c-6.7 4.5-15.2 4.5-21.8 0L64 32V48h320V32zM48 416V128l192 144L432 128v288c0 17.7-14.3 32-32 32H80c-17.7 0-32-14.3-32-32z"/></svg>
                    <input type="email" name="leader_email" required maxlength="255" placeholder="Email Address">
                </div>
                <div class="input-wrap" style="margin-bottom:0.75rem;">
                    <svg viewBox="0 0 448 512" width="14" height="14"><path fill="currentColor" d="M400 224h-24v-72C376 68.2 307.8 0 224 0S72 68.2 72 152v72H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V272c0-26.5-21.5-48-48-48zm-104 0H152v-72c0-39.7 32.3-72 72-72s72 32.3 72 72v72z"/></svg>
                    <input type="password" id="reg_pass" name="password" required minlength="8" maxlength="72" pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,}" title="At least 8 characters with an uppercase letter, a lowercase letter, and a number" placeholder="Group Access Password" style="flex:1;">
                    <button type="button" class="toggle-vis" data-toggle-vis="reg_pass|eye_reg" style="background:none;border:none;color:#9aa0a6;cursor:pointer;padding:0.5rem;">
                        <svg id="eye_reg" viewBox="0 0 640 512" width="14" height="14"><path fill="currentColor" d="M320 400c-75.85 0-146.5-41.11-201.6-108.7C47.37 290.4 16.85 244.1 4.57 216.1c-1.183-26.6 7.522-49.49 22.75-65.1C40.37 135.7 100.2 96 176 96c38.6 0 73.8 13.8 101 37l13 10.6c7.5 6.2 18.2 6.2 25.7 0l13-10.6C363.2 109.8 398.4 96 437 96c75.8 0 135.6 39.7 149.7 55c15.2 15.6 24.3 38.5 22.75 65.1C601.2 236.4 570.6 282.7 521.6 291.3C466.5 358.9 395.8 400 320 400zM49.7 216.1C61.4 235.4 85.7 268 125.4 298.6 177.1 339.4 247 368 320 368s142.9-28.6 194.6-69.4c39.7-30.6 64-63.2 75.7-82.5C596.2 206.4 598.5 182.7 592 160c-7.7-27.5-30.4-60.6-84.8-87.4C477.7 46.8 418.9 32 352 32c-66.9 0-125.7 14.8-155.2 40.6C142.4 99.4 119.7 132.5 112 160c-6.5 22.7-4.2 46.4 7.7 56.1zM320 288c-35.3 0-64-28.7-64-64s28.7-64 64-64 64 28.7 64 64-28.7 64-64 64zm0-96c-17.6 0-32 14.4-32 32s14.4 32 32 32 32-14.4 32-32-14.4-32-32-32z"/></svg>
                    </button>
                </div>
                <p class="modal-hint">At least 8 characters with an uppercase letter, a lowercase letter, and a number.</p>

                <button type="submit" id="regBtn" class="btn-primary" style="margin-top:0.5rem;">Register Account</button>
            </form>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgot-modal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" data-toggle-modal="forgot-modal"><svg viewBox="0 0 384 512" width="14" height="14"><path fill="currentColor" d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3l105.4 105.3c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256l105.3-105.4z"/></svg></button>
            <h3>Password Recovery</h3>
            <p id="forgot-desc" class="modal-desc">Enter your registered email address to receive an OTP.</p>

            <form id="forgotForm" autocomplete="off" onsubmit="event.preventDefault(); resetPasswordFinal();">
                <!-- Step 1: Email -->
                <div id="step-1">
                    <div class="input-wrap" style="margin-bottom:0.75rem;">
                        <svg viewBox="0 0 448 512" width="14" height="14"><path fill="currentColor" d="M48 80c0-8.8 7.2-16 16-16h352c8.8 0 16 7.2 16 16v352c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V80zm336-48L236.8 198.8c-6.7 4.5-15.2 4.5-21.8 0L64 32V48h320V32zM48 416V128l192 144L432 128v288c0 17.7-14.3 32-32 32H80c-17.7 0-32-14.3-32-32z"/></svg>
                        <input type="email" id="forgot_email" name="email" required placeholder="Registered Email Address">
                    </div>
                    <button type="button" id="sendOtpBtn" class="btn-primary" style="margin-top:0.25rem;">Send Recovery Code</button>
                </div>

                <!-- Step 2: OTP -->
                <div id="step-2" style="display:none;">
                    <div class="input-wrap" style="margin-bottom:0.75rem;">
                        <svg viewBox="0 0 512 512" width="14" height="14"><path fill="currentColor" d="M336 192h48c26.5 0 48 21.5 48 48v192c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V240c0-26.5 21.5-48 48-48h48c0-53 43-96 96-96s96 43 96 96zm-48 0h48c0-26.5-21.5-48-48-48s-48 21.5-48 48zM384 240c0-53-43-96-96-96s-96 43-96 96h48c0-26.5 21.5-48 48-48s48 21.5 48 48h48zM256 320a32 32 0 1 0 0-64 32 32 0 1 0 0 64z"/></svg>
                        <input type="text" id="forgot_otp" name="otp" placeholder="6-Digit OTP Code" class="otp-input" style="flex:1;">
                    </div>
                    <button type="button" id="verifyOtpBtn" class="btn-primary" style="margin-top:0.25rem;">Verify Code</button>
                    <p class="modal-inline">
                        <span id="resend-wrap">Resend available in <span id="resend-seconds" style="font-weight:700;color:#1c1e21;">60</span>s</span>
                        <button type="button" id="resendOtpBtn" style="display:none;">Resend Code</button>
                    </p>
                    <p id="otp-cooldown" class="modal-cooldown" style="display:none;">
                        Too many attempts. Please wait <span id="otp-cooldown-seconds">60</span>s before trying again.
                    </p>
                </div>

                <!-- Step 3: New Password -->
                <div id="step-3" style="display:none;">
                    <div class="input-wrap" style="margin-bottom:0.75rem;">
                        <svg viewBox="0 0 448 512" width="14" height="14"><path fill="currentColor" d="M400 224h-24v-72C376 68.2 307.8 0 224 0S72 68.2 72 152v72H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V272c0-26.5-21.5-48-48-48zm-104 0H152v-72c0-39.7 32.3-72 72-72s72 32.3 72 72v72z"/></svg>
                        <input type="password" id="forgot_new_pass" name="new_password" placeholder="New Password" style="flex:1;">
                        <button type="button" class="toggle-vis" data-toggle-vis="forgot_new_pass|eye_forgot1" style="background:none;border:none;color:#9aa0a6;cursor:pointer;padding:0.5rem;">
                            <svg id="eye_forgot1" viewBox="0 0 640 512" width="14" height="14"><path fill="currentColor" d="M320 400c-75.85 0-146.5-41.11-201.6-108.7C47.37 290.4 16.85 244.1 4.57 216.1c-1.183-26.6 7.522-49.49 22.75-65.1C40.37 135.7 100.2 96 176 96c38.6 0 73.8 13.8 101 37l13 10.6c7.5 6.2 18.2 6.2 25.7 0l13-10.6C363.2 109.8 398.4 96 437 96c75.8 0 135.6 39.7 149.7 55c15.2 15.6 24.3 38.5 22.75 65.1C601.2 236.4 570.6 282.7 521.6 291.3C466.5 358.9 395.8 400 320 400zM49.7 216.1C61.4 235.4 85.7 268 125.4 298.6 177.1 339.4 247 368 320 368s142.9-28.6 194.6-69.4c39.7-30.6 64-63.2 75.7-82.5C596.2 206.4 598.5 182.7 592 160c-7.7-27.5-30.4-60.6-84.8-87.4C477.7 46.8 418.9 32 352 32c-66.9 0-125.7 14.8-155.2 40.6C142.4 99.4 119.7 132.5 112 160c-6.5 22.7-4.2 46.4 7.7 56.1zM320 288c-35.3 0-64-28.7-64-64s28.7-64 64-64 64 28.7 64 64-28.7 64-64 64zm0-96c-17.6 0-32 14.4-32 32s14.4 32 32 32 32-14.4 32-32-14.4-32-32-32z"/></svg>
                        </button>
                    </div>
                    <div class="input-wrap" style="margin-bottom:0.75rem;">
                        <svg viewBox="0 0 512 512" width="14" height="14"><path fill="currentColor" d="M256 48C141.1 48 48 141.1 48 256s93.1 208 208 208 208-93.1 208-208S370.9 48 256 48zm-21.5 303.5l-56.6-56.6-14.1 14.1 70.7 70.7 70.7-70.7-14.1-14.1-56.6 56.6V224h-20v227.5z"/></svg>
                        <input type="password" id="forgot_conf_pass" name="conf_password" placeholder="Confirm New Password" style="flex:1;">
                        <button type="button" class="toggle-vis" data-toggle-vis="forgot_conf_pass|eye_forgot2" style="background:none;border:none;color:#9aa0a6;cursor:pointer;padding:0.5rem;">
                            <svg id="eye_forgot2" viewBox="0 0 640 512" width="14" height="14"><path fill="currentColor" d="M320 400c-75.85 0-146.5-41.11-201.6-108.7C47.37 290.4 16.85 244.1 4.57 216.1c-1.183-26.6 7.522-49.49 22.75-65.1C40.37 135.7 100.2 96 176 96c38.6 0 73.8 13.8 101 37l13 10.6c7.5 6.2 18.2 6.2 25.7 0l13-10.6C363.2 109.8 398.4 96 437 96c75.8 0 135.6 39.7 149.7 55c15.2 15.6 24.3 38.5 22.75 65.1C601.2 236.4 570.6 282.7 521.6 291.3C466.5 358.9 395.8 400 320 400zM49.7 216.1C61.4 235.4 85.7 268 125.4 298.6 177.1 339.4 247 368 320 368s142.9-28.6 194.6-69.4c39.7-30.6 64-63.2 75.7-82.5C596.2 206.4 598.5 182.7 592 160c-7.7-27.5-30.4-60.6-84.8-87.4C477.7 46.8 418.9 32 352 32c-66.9 0-125.7 14.8-155.2 40.6C142.4 99.4 119.7 132.5 112 160c-6.5 22.7-4.2 46.4 7.7 56.1zM320 288c-35.3 0-64-28.7-64-64s28.7-64 64-64 64 28.7 64 64-28.7 64-64 64zm0-96c-17.6 0-32 14.4-32 32s14.4 32 32 32 32-14.4 32-32-14.4-32-32-32z"/></svg>
                        </button>
                    </div>
                    <button type="submit" id="resetPassBtn" class="btn-primary" style="margin-top:0.25rem;">Save New Password</button>
                </div>
            </form>
        </div>
    </div>

    <script id="page-config" type="application/json">{"csrf_token": "<?php echo $csrf_token; ?>", "total_groups": <?php echo (int)$total_groups; ?>, "total_advisers": <?php echo (int)$total_advisers; ?>, "total_projects": <?php echo (int)$total_projects; ?>}</script>
    <script src="js/index.js"></script>
</body>
</html>
<?php include "../layout/topbar.php"?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>About Us — NCC</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --gold: #B8963E;
            --gold-light: #D4AF68;
            --gold-pale: #F5EDD8;
            --deep: #1A1A2E;
            --navy: #16213E;
            --maroon: #7B1C1C;
            --maroon-mid: #9B2929;
            --cream: #FAF7F2;
            --line: rgba(184,150,62,0.25);
            --line-light: rgba(184,150,62,0.15);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--deep);
            color: #fff;
            overflow-x: hidden;
        }

        /* ── HERO ─────────────────────────────────────────────────────── */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: var(--navy);
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background: url('../img/school.png') center/cover no-repeat;
            opacity: 0.10;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(26,26,46,0.97) 0%, rgba(22,33,62,0.88) 60%, rgba(123,28,28,0.18) 100%);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -100px; right: -80px;
            width: 420px; height: 800px;
            background: linear-gradient(160deg, rgba(184,150,62,0.08) 0%, transparent 70%);
            transform: rotate(12deg);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding: 7rem 0 5rem;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--gold-light);
            border: 1px solid var(--line);
            border-radius: 2px;
            padding: 0.35rem 1rem;
            margin-bottom: 1.75rem;
            animation: fadeUp 0.6s ease both;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.4rem, 6vw, 4.8rem);
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            animation: fadeUp 0.6s ease both 0.1s;
        }

        .hero-title .gold-word { color: var(--gold-light); font-style: italic; }

        .hero-desc {
            font-size: 1rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.8;
            max-width: 520px;
            margin-bottom: 2.5rem;
            font-weight: 300;
            animation: fadeUp 0.6s ease both 0.2s;
        }

        .hero-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            animation: fadeUp 0.6s ease both 0.3s;
        }

        .hero-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--line);
            border-radius: 50px;
            padding: 0.5rem 1.1rem;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.6);
        }

        .hero-pill i { color: var(--gold); }

        .scroll-cue {
            position: absolute;
            bottom: 2.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.65rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            animation: bounce 2s ease-in-out infinite;
            z-index: 2;
        }

        @keyframes bounce {
            0%,100% { transform: translateX(-50%) translateY(0); }
            50%      { transform: translateX(-50%) translateY(8px); }
        }

        .hero-seal-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeUp 0.6s ease both 0.15s;
        }

        .hero-seal {
            width: 220px;
            height: 220px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            padding: 10px;
            box-shadow: 0 0 80px rgba(184,150,62,0.2), 0 0 160px rgba(184,150,62,0.08);
            animation: sealGlow 3s ease-in-out infinite alternate;
        }

        @keyframes sealGlow {
            from { box-shadow: 0 0 30px rgba(184,150,62,0.15); }
            to   { box-shadow: 0 0 80px rgba(184,150,62,0.35), 0 0 160px rgba(184,150,62,0.12); }
        }

        .hero-seal-ring {
            position: absolute;
            width: 270px; height: 270px;
            border-radius: 50%;
            border: 1px dashed rgba(184,150,62,0.2);
            animation: rotateSlow 30s linear infinite;
        }

        @keyframes rotateSlow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* ── DIVIDER ──────────────────────────────────────────────────── */
        .gold-divider {
            height: 2px;
            background: linear-gradient(to right, transparent, var(--gold), transparent);
            margin: 0;
        }

        /* ── SECTION WRAPPER ──────────────────────────────────────────── */
        section { padding: 5rem 0; }

        .section-label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--gold-light);
            margin-bottom: 0.75rem;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.6rem, 3.5vw, 2.8rem);
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
            margin-bottom: 1rem;
        }

        .section-sub {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.4);
            line-height: 1.8;
            max-width: 540px;
        }

        /* ── STORY SECTION ────────────────────────────────────────────── */
        .story-section { background: var(--deep); }

        .story-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px;
            padding: 2.5rem;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: border-color 0.3s, transform 0.3s;
        }

        .story-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(to bottom, var(--gold), var(--maroon));
        }

        .story-card:hover {
            border-color: rgba(184,150,62,0.25);
            transform: translateY(-4px);
        }

        .story-card-icon {
            width: 48px; height: 48px;
            background: rgba(184,150,62,0.1);
            border: 1px solid var(--line);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--gold);
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
        }

        .story-card h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 0.75rem;
        }

        .story-card p {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.45);
            line-height: 1.8;
        }

        /* ── VISION / MISSION ─────────────────────────────────────────── */
        .vm-section {
            background: var(--navy);
            position: relative;
            overflow: hidden;
        }

        .vm-section::before {
            content: '';
            position: absolute;
            top: -100px; right: -150px;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(184,150,62,0.06) 0%, transparent 70%);
        }

        .vm-block {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 3rem 2rem;
            height: 100%;
            position: relative;
            transition: transform 0.3s;
        }

        .vm-block:hover { transform: translateY(-4px); }

        .vm-block .vm-number {
            font-family: 'Playfair Display', serif;
            font-size: 5rem;
            font-weight: 700;
            line-height: 1;
            color: rgba(184,150,62,0.1);
            position: absolute;
            top: 1.5rem; right: 2rem;
        }

        .vm-block h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--gold-light);
            margin-bottom: 1.25rem;
        }

        .vm-block p {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.55);
            line-height: 1.9;
        }

        /* ── CORE VALUES ──────────────────────────────────────────────── */
        .values-section { background: var(--deep); }

        .value-item {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            transition: background 0.3s, border-color 0.3s;
        }

        .value-item:hover {
            background: rgba(184,150,62,0.05);
            border-color: var(--line);
        }

        .value-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: rgba(184,150,62,0.3);
            line-height: 1;
            flex-shrink: 0;
            min-width: 2rem;
        }

        .value-text h5 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 0.35rem;
        }

        .value-text p {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.4);
            line-height: 1.7;
        }

        /* ── STATS BAND ───────────────────────────────────────────────── */
        .stats-band {
            background: linear-gradient(135deg, var(--maroon) 0%, #5c1111 100%);
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .stats-band::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('../img/school.png') center/cover no-repeat;
            opacity: 0.06;
        }

        .stat-item { text-align: center; }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            color: var(--gold-light);
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: rgba(255,255,255,0.55);
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* ── PROGRAMS ─────────────────────────────────────────────────── */
        .programs-section { background: var(--navy); }

        .program-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px;
            padding: 2rem 1.5rem;
            height: 100%;
            transition: border-color 0.3s, transform 0.3s, background 0.3s;
            position: relative;
            overflow: hidden;
        }

        .program-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(to right, var(--gold), var(--maroon-mid));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s;
        }

        .program-card:hover {
            border-color: var(--line);
            background: rgba(184,150,62,0.04);
            transform: translateY(-4px);
        }

        .program-card:hover::after { transform: scaleX(1); }

        .program-icon {
            font-size: 2rem;
            color: var(--gold);
            margin-bottom: 1rem;
        }

        .program-card h5 {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            color: #fff;
            margin-bottom: 0.5rem;
        }

        .program-card p {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.4);
            line-height: 1.7;
        }

        /* ── LOCATION ─────────────────────────────────────────────────── */
        .location-section { background: var(--deep); }

        .location-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 2rem;
        }

        .location-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .location-item:last-child { border-bottom: none; padding-bottom: 0; }
        .location-item:first-child { padding-top: 0; }

        .location-item .icon-wrap {
            width: 40px; height: 40px;
            background: rgba(184,150,62,0.1);
            border: 1px solid var(--line);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--gold);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .location-item h6 {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: 0.25rem;
        }

        .location-item p {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
        }

        /* map frame */
        .map-frame {
            width: 100%;
            height: 380px;
            border-radius: 12px;
            border: 1px solid var(--line);
            overflow: hidden;
            position: relative;
            background: var(--navy);
        }

        .map-frame iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* ── CTA ──────────────────────────────────────────────────────── */
        .cta-section {
            background: linear-gradient(135deg, var(--navy) 0%, var(--deep) 100%);
            padding: 6rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(184,150,62,0.07) 0%, transparent 70%);
        }

        .cta-section .section-title { margin-bottom: 1rem; }

        .btn-cta-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2.2rem;
            background: linear-gradient(135deg, var(--maroon-mid), var(--maroon));
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 0.4rem;
        }

        .btn-cta-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(123,28,28,0.35);
            color: #fff;
        }

        .btn-cta-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.88rem 2.2rem;
            background: transparent;
            color: var(--gold-light);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            border: 1px solid var(--line);
            border-radius: 6px;
            text-decoration: none;
            transition: border-color 0.2s, background 0.2s, transform 0.2s;
            margin: 0.4rem;
        }

        .btn-cta-outline:hover {
            border-color: var(--gold);
            background: rgba(184,150,62,0.08);
            color: var(--gold-light);
            transform: translateY(-2px);
        }

        /* ── FOOTER STRIP ─────────────────────────────────────────────── */
        .about-footer {
            background: var(--deep);
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 1.5rem 1rem;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.2);
            letter-spacing: 0.08em;
        }

        .page-top-offset { padding-top: 72px; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fade-up { animation: fadeUp 0.6s ease both; }

        .gold-rule { width: 50px; height: 2px; background: linear-gradient(to right, transparent, var(--gold), transparent); margin: 1.5rem 0; }

        /* ── MOBILE OVERRIDES ─────────────────────────────────────────── */
        @media (max-width: 991.98px) {
            .hero-content { padding: 5rem 0 4rem; }

            .hero-seal-wrap { margin-top: 2.5rem; }
            .hero-seal { width: 160px; height: 160px; }
            .hero-seal-ring { width: 210px; height: 210px; }

            .vm-block { padding: 2rem 1.5rem; }
            .vm-block .vm-number { font-size: 3.5rem; }
        }

        @media (max-width: 767.98px) {
            section { padding: 3.5rem 0; }

            .hero { min-height: auto; }
            .hero-content { padding: 4rem 0 3rem; }
            .hero-title { font-size: 2rem; }
            .hero-desc { font-size: 0.9rem; }
            .hero-pill { font-size: 0.72rem; padding: 0.4rem 0.85rem; }

            .hero-seal { width: 130px; height: 130px; }
            .hero-seal-ring { width: 178px; height: 178px; }

            .scroll-cue { display: none; }

            .stats-band { padding: 2.5rem 0; }
            .stat-num { font-size: 2rem; }

            .story-card { padding: 1.75rem 1.5rem; }
            .vm-block { padding: 1.75rem 1.25rem; }
            .vm-block .vm-number { font-size: 2.8rem; top: 1rem; right: 1.25rem; }
            .vm-block h3 { font-size: 1.2rem; }

            .program-card { padding: 1.5rem 1.25rem; }
            .program-icon { font-size: 1.6rem; }

            .location-card { padding: 1.5rem 1.25rem; }
            .map-frame { height: 280px; margin-top: 1.5rem; }

            .cta-section { padding: 4rem 0; }
            .btn-cta-primary,
            .btn-cta-outline {
                width: 100%;
                justify-content: center;
                margin: 0.3rem 0;
            }

            .about-footer { font-size: 0.7rem; letter-spacing: 0.04em; }
        }

        @media (max-width: 480px) {
            .hero-title { font-size: 1.75rem; }
            .section-title { font-size: 1.5rem; }
        }
    </style>
</head>

<body class="page-top-offset">

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>

    <div class="container hero-content ps-3 mt-0">
        <div class="row align-items-center g-4">

            <div class="col-lg-7 order-2 order-lg-1">
                
                <h1 class="hero-title">
                    Northeastern Cebu<br>
                    <span class="gold-word">Colleges, Inc.</span>
                </h1>
                <p class="hero-desc">
                    A premier educational institution in Danao City, Cebu dedicated to cultivating excellence, integrity, and lifelong service since our founding. We shape the leaders and professionals of tomorrow.
                </p>
                <div class="hero-pills">
                    <div class="hero-pill"><i class="bi bi-geo-alt-fill"></i> Danao City, Cebu</div>
                    <div class="hero-pill"><i class="bi bi-patch-check-fill"></i> CHED Recognized</div>
                    <div class="hero-pill"><i class="bi bi-mortarboard-fill"></i> Quality Education</div>
                    <div class="hero-pill"><i class="bi bi-people-fill"></i> Community-Centered</div>
                </div>
            </div>

            <div class="col-lg-5 order-1 order-lg-2">
                <div class="hero-seal-wrap" style="position:relative;">
                    <div class="hero-seal-ring"></div>
                    <img src="../img/logo.png" class="hero-seal" alt="NCC Seal">
                </div>
            </div>

        </div>
    </div>

    <div class="scroll-cue">
        <span>Scroll</span>
        <i class="bi bi-chevron-down"></i>
    </div>
</section>

<div class="gold-divider"></div>

<!-- STORY / HISTORY -->
<section class="story-section">
    <div class="container">
        <div class="row align-items-end mb-4 mb-md-5">
            <div class="col-lg-6 mb-3 mb-lg-0">
                <p class="section-label"><i class="bi bi-book me-1"></i> Our Story</p>
                <h2 class="section-title">A Legacy of Academic<br>Excellence</h2>
                <div class="gold-rule"></div>
            </div>
            <div class="col-lg-6">
                <p class="section-sub">
                    Northeastern Cebu Colleges, Inc. was established with a clear mission: to provide accessible, high-quality tertiary education to the communities of Danao City and its neighboring municipalities in northern Cebu. Through decades of dedicated service, NCC has grown into a trusted institution that has produced thousands of professionals serving locally and abroad.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="story-card">
                    <div class="story-card-icon"><i class="bi bi-star-fill"></i></div>
                    <h4>Founded with Purpose</h4>
                    <p>NCC was established to bridge the educational gap in northern Cebu, providing the communities of Danao City and surrounding areas access to reputable college-level education close to home.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="story-card">
                    <div class="story-card-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <h4>Decades of Growth</h4>
                    <p>From its humble beginnings, NCC has steadily expanded its academic programs and facilities, continuously improving to meet the evolving demands of industry and the aspirations of its students.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="story-card">
                    <div class="story-card-icon"><i class="bi bi-globe2"></i></div>
                    <h4>Community Impact</h4>
                    <p>Our graduates serve in key roles across education, business, healthcare, public administration, and abroad — a testament to NCC's enduring contribution to Cebuano society and beyond.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATS BAND -->
<div class="stats-band">
    <div class="container" style="position:relative;z-index:2;">
        <div class="row align-items-center text-center g-4">
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-num">25+</div>
                    <div class="stat-label">Years of Service</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-num">10K+</div>
                    <div class="stat-label">Alumni Worldwide</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-num">5</div>
                    <div class="stat-label">Degree Programs</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-num">100%</div>
                    <div class="stat-label">Committed Faculty</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VISION / MISSION -->
<section class="vm-section">
    <div class="container">
        <div class="text-center mb-4 mb-md-5">
            <p class="section-label"><i class="bi bi-eye me-1"></i> Our Direction</p>
            <h2 class="section-title">Vision &amp; Mission</h2>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="vm-block">
                    <div class="vm-number">01</div>
                    <h3><i class="bi bi-eye me-2" style="font-size:1rem;"></i>Vision</h3>
                    <p>
                   Northeastern Cebu Colleges is seen as a prestigious institution of higher learning catering to the educational and training needs of young people, turning out competent graduates in the different fields, answering to the professional manpower needs, producing leaders and contributing to the social and economic development of the country and most especially generating globally-competitive world citizens.</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="vm-block">
                    <div class="vm-number">02</div>
                    <h3><i class="bi bi-bullseye me-2" style="font-size:1rem;"></i>Mission</h3>
                    <p>
                    Northeastern Cebu Colleges is committed to render service by way of providing opportunities to the young people in this part of Cebu for college education that is relevant, quality-oriented, accessible and affordable. Its mission is to contribute to holistic human development with respect to the intellectual, physical, moral, spiritual, social, cultural and the professional aspect of the person. The mission is anchored on love, wisdom and sense of service to humanity.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CORE VALUES -->
<section class="values-section">
    <div class="container">
        <div class="row align-items-end mb-4 mb-md-5">
            <div class="col-lg-6 mb-3 mb-lg-0">
                <p class="section-label"><i class="bi bi-shield-check me-1"></i> What We Stand For</p>
                <h2 class="section-title">Core Values</h2>
                <div class="gold-rule"></div>
            </div>
            <div class="col-lg-6">
                <p class="section-sub">The principles that define our culture and guide every decision, interaction, and aspiration within our institution.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-lg-4">
                <div class="value-item">
                    <div class="value-num">01</div>
                    <div class="value-text">
                        <h5>Excellence</h5>
                        <p>We hold ourselves to the highest academic and professional standards in everything we do.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="value-item">
                    <div class="value-num">02</div>
                    <div class="value-text">
                        <h5>Integrity</h5>
                        <p>Honesty and ethical conduct are the foundation of our community and our graduates.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="value-item">
                    <div class="value-num">03</div>
                    <div class="value-text">
                        <h5>Service</h5>
                        <p>We cultivate a spirit of service to God, to community, and to the nation.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="value-item">
                    <div class="value-num">04</div>
                    <div class="value-text">
                        <h5>Relevance</h5>
                        <p>Our programs are continuously aligned with the needs of industry and the demands of a changing world.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="value-item">
                    <div class="value-num">05</div>
                    <div class="value-text">
                        <h5>Discipline</h5>
                        <p>We foster responsibility, punctuality, and commitment in our students and staff alike.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="value-item">
                    <div class="value-num">06</div>
                    <div class="value-text">
                        <h5>Innovation</h5>
                        <p>We embrace progress and continuously seek better ways to deliver education and serve our stakeholders.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PROGRAMS -->
<section class="programs-section">
    <div class="container">
        <div class="text-center mb-4 mb-md-5">
            <p class="section-label"><i class="bi bi-mortarboard me-1"></i> Academic Offerings</p>
            <h2 class="section-title">Degree Programs</h2>
            <p class="section-sub mx-auto text-center" style="max-width:480px;">
                NCC offers programs designed to equip students with the knowledge and skills needed for professional success.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-sm-6 col-lg-4">
                <div class="program-card">
                    <div class="program-icon"><i class="bi bi-briefcase"></i></div>
                    <h5>Business Administration</h5>
                    <p>Bachelor of Science in Business Administration (BSBA)</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="program-card">
                    <div class="program-icon"><i class="bi bi-laptop"></i></div>
                    <h5>Information Technology</h5>
                    <p>Associate in Information Technology (ACT) & Bachelor of Science in Information Technology (BSIT)</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="program-card">
                    <div class="program-icon"><i class="bi bi-compass"></i></div>
                    <h5>Tourism Management</h5>
                    <p>Bachelor of Science in Tourism Management (BSTM)</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-6">
                <div class="program-card">
                    <div class="program-icon"><i class="bi bi-book"></i></div>
                    <h5>Education</h5>
                    <p>Bachelor of Elementary Education (BEED) &amp; Bachelor of Secondary Education (BSED)</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-6">
                <div class="program-card">
                    <div class="program-icon"><i class="bi bi-cup-hot"></i></div>
                    <h5>Hospitality Management</h5>
                    <p>Bachelor of Science in Hospitality Management (BSHM)</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- LOCATION & CONTACT -->
<section class="location-section">
    <div class="container">
        <div class="row align-items-end mb-4 mb-md-5">
            <div class="col-lg-6 mb-3 mb-lg-0">
                <p class="section-label"><i class="bi bi-geo me-1"></i> Find Us</p>
                <h2 class="section-title">Location &amp; Contact</h2>
                <div class="gold-rule"></div>
            </div>
            <div class="col-lg-6">
                <p class="section-sub">We are conveniently located in the heart of Danao City, accessible from surrounding municipalities of northern Cebu.</p>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <div class="location-card">
                    <div class="location-item">
                        <div class="icon-wrap"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <h6>Address</h6>
                            <p>P.G. Almendras St., Danao City,<br>Cebu, Philippines 6004</p>
                        </div>
                    </div>
                    <div class="location-item">
                        <div class="icon-wrap"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <h6>Telephone</h6>
                            <p>0922 828 2445</p>
                        </div>
                    </div>
                    <div class="location-item">
                        <div class="icon-wrap"><i class="bi bi-envelope-fill"></i></div>
                        <div>
                            <h6>Email</h6>
                            <p>ncc.collegedepartment@gmail.com</p>
                        </div>
                    </div>
                    <div class="location-item">
                        <div class="icon-wrap"><i class="bi bi-clock-fill"></i></div>
                        <div>
                            <h6>Office Hours</h6>
                            <p>Monday – Friday<br>7:30 AM – 5:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="map-frame">
                    <!-- Northeastern Cebu Colleges exact location: 10.5236994, 124.0252692 -->
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3924.244871560585!2d124.02269!3d10.52370!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a9b08f544d4d5f%3A0x371cec404011124c!2sNortheastern%20Cebu%20Colleges!5e0!3m2!1sen!2sph!4v1713520000000!5m2!1sen!2sph"
                        allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container" style="position:relative;z-index:2;">
        <p class="section-label justify-content-center d-flex"><i class="bi bi-door-open me-1"></i> Ready to Join Us?</p>
        <h2 class="section-title mx-auto" style="max-width:540px;">
            Start Your Journey at NCC
        </h2>
        <p class="section-sub mx-auto text-center mb-4" style="max-width:440px;">
            Apply for admission through our secure online portal. Begin your academic story with Northeastern Cebu Colleges, Inc.
        </p>
        <div class="d-flex flex-column flex-sm-row justify-content-center align-items-center gap-2">
            <a href="../pages/register.php" class="btn-cta-primary">
                <i class="bi bi-pencil-square"></i> Apply Now
            </a>
            <a href="../pages/log_in.php" class="btn-cta-outline">
                <i class="bi bi-box-arrow-in-right"></i> Log In to Portal
            </a>
        </div>
    </div>
</section>

<!-- FOOTER STRIP -->
<footer class="about-footer">
    &copy; <?php echo date('Y'); ?> Northeastern Cebu Colleges, Inc. &nbsp;&middot;&nbsp; P.G. Almendras St., Danao City, Cebu &nbsp;&middot;&nbsp; All Rights Reserved
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.story-card, .vm-block, .value-item, .program-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });
</script>

</body>
</html>
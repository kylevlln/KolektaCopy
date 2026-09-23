<?php
/**
 * Kolekta — public landing page (formal introduction).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

page_guest_only();

$heading = new DateTimeImmutable();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kolekta — Barangay Waste Collection Dispatch</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,480;0,9..144,560;1,9..144,400;1,9..144,480&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%231E3B2C'/%3E%3Cpath d='M10 20c3 3 9 3 12 0s3-8 0-11' stroke='%23F5F0E4' stroke-width='2.4' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/kolekta.css">
</head>
<body class="landing">

<header class="site-header">
  <?= brand_word() ?>
  <nav class="site-nav">
    <a href="#programme">The Programme</a>
    <a href="#residents">For Residents</a>
    <a href="#administration">For Administration</a>
  </nav>
  <div class="site-actions">
    <a class="btn btn--ghost" href="login.php">Sign in</a>
    <a class="btn btn--primary" href="register.php">Create an account</a>
  </div>
</header>

<main>

  <section class="hero">
    <div class="hero-deco hero-deco--2"></div>
    <div class="hero-deco"></div>
    <div class="hero-inner">
      <div class="hero-copy">
        <p class="eyebrow">Barangay Solid Waste Management</p>
        <h1 class="hero-title">Collection, <em>with certainty.</em></h1>
        <p class="hero-lead">
          Kolekta tells you exactly when the truck arrives in your purok — and gives
          the barangay a clear record when a pickup is missed.
        </p>
        <div class="hero-cta">
          <a class="btn btn--primary" href="login.php">Sign in to your barangay
            <span><?= icon('arrowRight') ?></span>
          </a>
          <a class="btn btn--ghost" href="register.php">Register your household</a>
        </div>
      </div>

      <div class="hero-art">
        <div class="dispatch-card">
          <div class="dc-top">
            <span class="dc-zone">Zone P1 · Purok 1</span>
            <span class="dc-status"><i class="pulse"></i> Underway</span>
          </div>
          <div class="dc-title">
            <div class="dc-waste">Biodegradable pickup</div>
            <h3>The collection truck has been dispatched.</h3>
            <p>Bring out your segregated waste within the collection window.</p>
          </div>
          <div>
            <div class="dc-progress"><span></span></div>
            <div class="dc-meta" style="margin-top:12px">
              <span>Window 05:00 – 07:00</span>
              <span><?= $heading->format('F j') ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--paper" id="programme">
    <div class="section-inner">
      <div class="section-head reveal">
        <p class="eyebrow">01 · The Programme</p>
        <h2>A formal record of every collection.</h2>
        <p class="lede">
          Kolekta supports the barangay in meeting the requirements of
          <strong>Republic Act 9003</strong>, the Ecological Solid Waste Management Act
          of 2000, and the local ordinance on segregation at source. It is a
          coordination tool for the <strong>Solid Waste Management Committee</strong> and
          the households it serves.
        </p>
        <div class="stat-line">
          <div class="s"><b>Segregation</b><span>at the household</span></div>
          <div class="s"><b>Per-Purok</b><span>collection routing</span></div>
          <div class="s"><b>Accountable</b><span>missed-pickup follow-up</span></div>
        </div>
      </div>

      <div class="principles reveal">
        <div class="principle">
          <span class="no">No. 01</span>
          <div class="rule"></div>
          <h3>Segregation at source</h3>
          <p>Waste is separated into biodegradable, non-biodegradable, and recyclable
             streams at the household, as required by the barangay ordinance.</p>
        </div>
        <div class="principle">
          <span class="no">No. 02</span>
          <div class="rule"></div>
          <h3>Public, fixed schedules</h3>
          <p>Every purok follows a set weekly calendar. Residents put waste out only
             on their assigned days, never overnight.</p>
        </div>
        <div class="principle">
          <span class="no">No. 03</span>
          <div class="rule"></div>
          <h3>Follow-through, tracked</h3>
          <p>When a household is skipped, a report is filed, logged, and resolved by
             the barangay — not left to social media.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="residents">
    <div class="section-inner">
      <div class="section-head reveal">
        <p class="eyebrow">02 · For Residents</p>
        <h2>No more waiting by the roadside.</h2>
        <p>Registering your household takes one minute. You choose your purok once and
           Kolekta handles the rest quietly in the background.</p>
      </div>
      <div class="cards-grid reveal">
        <div class="card">
          <div class="card-icon"><?= icon('truck') ?></div>
          <h3 class="c-title">Dispatch alerts</h3>
          <p class="c-body">The barangay signals when the truck is en route to your
             purok. You bring your waste out at the right moment — not hours early.</p>
          <span class="c-note">Pushed when dispatched</span>
        </div>
        <div class="card">
          <div class="card-icon"><?= icon('calendar') ?></div>
          <h3 class="c-title">Collection calendar</h3>
          <p class="c-body">A clear weekly calendar shows which waste type is collected
             on which day — biodegradable, non-biodegradable, or recyclable.</p>
          <span class="c-note">Always available</span>
        </div>
        <div class="card">
          <div class="card-icon"><?= icon('camera') ?></div>
          <h3 class="c-title">Missed-pickup report</h3>
          <p class="c-body">Skipped by the truck? Report it in three steps with a photo.
             The barangay receives it with your zone and address attached.</p>
          <span class="c-note">Pending to resolved</span>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--paper" id="administration">
    <div class="section-inner">
      <div class="section-head reveal">
        <p class="eyebrow">03 · For Administration</p>
        <h2>One dashboard for the whole collection day.</h2>
        <p>Built for the Solid Waste Management Officer and the barangay hall —
           pointed, practical, and free of noise.</p>
      </div>
      <div class="cards-grid reveal">
        <div class="card card--dark">
          <div class="card-icon"><?= icon('send') ?></div>
          <h3 class="c-title">Zone dispatch trigger</h3>
          <p class="c-body">A button per purok. Tap it when the truck leaves, and every
             registered household in that zone is alerted immediately.</p>
          <span class="c-note">One tap per zone</span>
        </div>
        <div class="card card--dark">
          <div class="card-icon"><?= icon('megaphone') ?></div>
          <h3 class="c-title">Schedule manager</h3>
          <p class="c-body">Update collection days or announce a cancellation — truck
             maintenance, weather, holidays — and the notice reaches the right puroks.</p>
          <span class="c-note">Notices &amp; cancellations</span>
        </div>
        <div class="card card--dark">
          <div class="card-icon"><?= icon('inbox') ?></div>
          <h3 class="c-title">Missed-pickup triage</h3>
          <p class="c-body">Reports arrive grouped by street and zone. Review, flag for a
             secondary truck, and mark resolved — with a full paper trail.</p>
          <span class="c-note">Tickets, not complaints</span>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="section-inner">
      <div class="split reveal">
        <div>
          <p class="eyebrow">Two roles, one system</p>
          <h3>Households</h3>
          <p>Residents create their own account, register their purok, and use the
             calendar, alerts, and missed-pickup reporting.</p>
        </div>
        <div>
          <p class="eyebrow">Staff accounts</p>
          <h3>Barangay administration</h3>
          <p>The Solid Waste Management Office staff use shared accounts to run
             dispatches, publish schedules, and manage the triage board.</p>
        </div>
      </div>
    </div>
  </section>

</main>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <span class="wordmark on-dark">
        <span class="wordmark--mark"><?= brand_mark() ?></span>
        <span class="wordmark--word">Kolekta</span>
      </span>
      <p>A barangay-level waste collection dispatch notification and missed-pickup
         reporting system, developed in support of segregation at source.</p>
    </div>
    <div class="footer-col">
      <b>Navigate</b>
      <ul>
        <li><a href="#programme">The Programme</a></li>
        <li><a href="#residents">For Residents</a></li>
        <li><a href="#administration">For Administration</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <b>Access</b>
      <ul>
        <li><a href="login.php">Sign in</a></li>
        <li><a href="register.php">Register a household</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <b>Legal</b>
      <ul>
        <li><a href="legal/privacy.php">Privacy Policy</a></li>
        <li><a href="legal/cookies.php">Cookie Policy</a></li>
        <li><a href="legal/terms.php">Terms of Service</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span><?= BARANGAY_NAME ?> · Solid Waste Management Office</span>
    <span>In support of R.A. 9003 · <?= date('Y') ?></span>
  </div>
</footer>

<?= cookie_notice() ?>
<script src="assets/js/app.js"></script>
<script src="assets/js/legal.js"></script>

</body>
</html>
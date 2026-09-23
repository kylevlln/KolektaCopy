<?php
/**
 * Kolekta — resident dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$user = page_guard('resident');

$zones = db()->query(
    'SELECT id, name, code FROM zones WHERE is_active = 1 ORDER BY id'
)->fetchAll();

$wasteTypes = db()->query(
    'SELECT id, code, name, is_active, sort_order FROM waste_types WHERE is_active = 1 ORDER BY sort_order, id'
)->fetchAll();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$nameParts = explode(' ', trim($user['name']));
$firstName = $nameParts[0] ?? '';
if ($firstName === '') {
    $firstName = 'neighbor';
}
$initials = mb_strtoupper(mb_substr($nameParts[0] ?? '', 0, 1));
if (count($nameParts) > 1) {
    $lastWord = (string)end($nameParts);
    $initials .= mb_strtoupper(mb_substr($lastWord, 0, 1));
}
if ($initials === '') {
    $initials = '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Overview — Kolekta</title>
<?= csrf_meta() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,480;0,9..144,560;1,9..144,400;1,9..144,480&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%231E3B2C'/%3E%3Cpath d='M10 20c3 3 9 3 12 0s3-8 0-11' stroke='%23F5F0E4' stroke-width='2.4' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/kolekta.css">
</head>
<body>

<div class="shell">

  <aside class="side">
    <span class="wordmark on-dark"><span class="wordmark--mark"><?= brand_mark() ?></span><span class="wordmark--word">Kolekta</span></span>

    <nav class="side-nav" id="userNav">
      <a href="#overview" class="active"><?= icon('home') ?> Overview</a>
      <a href="#schedule"><?= icon('calendar') ?> My schedule</a>
      <a href="#alerts"><?= icon('truck') ?> Truck alerts</a>
      <a href="#notices"><?= icon('megaphone') ?> Barangay notices</a>
      <a href="#reports"><?= icon('flag') ?> My reports</a>
      <a href="#notifications"><?= icon('bell') ?> Notifications <span class="count" id="navNotif">0</span></a>
      <a href="#profile"><?= icon('users') ?> My profile</a>
    </nav>

    <div class="side-foot">
      <div class="side-user">
        <span class="avatar"><?= esc($initials) ?></span>
        <span class="u">
          <b><?= esc($user['name']) ?></b>
          <span id="sideZone"><?= esc($user['zone_name'] ?? 'No purok assigned') ?></span>
        </span>
      </div>
      <a class="side-link" href="legal/privacy.php"><?= icon('lock') ?> Privacy &amp; terms</a>
      <button class="side-link" onclick="signOut()"><?= icon('logout') ?> Sign out</button>
    </div>
  </aside>

  <main class="main">

    <div class="main-head">
      <div>
        <h1 id="greeting"><?= esc($greeting) ?>, <?= esc($firstName) ?>.</h1>
        <p id="todayLine">Your collection schedule and dispatches for today.</p>
      </div>
      <div class="main-head-actions">
        <span class="zone-chip" id="zoneChip"><i></i><?= esc($user['zone_name'] ?? 'No zone') ?></span>
        <button class="bell" id="bell" title="Notifications" onclick="toggleNotifs()"><?= icon('bell') ?><span class="dot"></span></button>
      </div>
    </div>

    <!-- dispatch alert banner -->
    <div id="dispatchBanner" class="alert" style="display:none">
      <div class="a-ico"><?= icon('truck') ?></div>
      <div class="a-body"><b id="bannerTitle"></b><span id="bannerBody"></span></div>
      <button class="a-close" onclick="hideBanner()"><?= icon('x') ?></button>
    </div>

    <!-- ============================ OVERVIEW ============================ -->
    <section id="overview">
      <div class="grid grid--hero" style="margin-bottom:26px">
        <section class="panel next-card">
          <div class="nc-inner">
            <div class="panel-head">
              <h2><?= icon('calendar') ?> Next collection</h2>
              <span class="right" id="nextIn"></span>
            </div>
            <div class="next-day" id="nextDay">—</div>
            <div class="next-date" id="nextDate"></div>
            <div class="next-types" id="nextTypes"></div>
            <div class="next-hint">
              Bring waste out within the collection window. Do not set bags out the
              night before — this prevents <b>litter, dogs, and scavenging</b>.
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('clock') ?> This week</h2>
            <span class="right" id="weekZone"></span>
          </div>
          <div class="week" id="week"></div>
          <div class="next-hint" style="margin-top:20px;font-size:12.5px;color:var(--muted);border-top:1px solid var(--hairline-2);padding-top:14px">
            Follow the barangay ordinance: <b style="color:var(--ink)">no segregation, no collection</b>.
          </div>
        </section>
      </div>

      <div class="quick-actions">
        <button type="button" class="btn btn--primary" data-goto="reports"><?= icon('flag') ?> Report missed pickup</button>
        <button type="button" class="btn btn--ghost" data-goto="schedule"><?= icon('calendar') ?> View my schedule</button>
        <button type="button" class="btn btn--ghost" data-goto="profile"><?= icon('users') ?> Manage my profile</button>
      </div>
    </section>

    <!-- ============================ SCHEDULE ============================ -->
    <section id="schedule" class="mt-3">
      <p class="section-title">My collection schedule</p>

      <div class="grid grid--2">
        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('calendar') ?> Weekly collection</h2>
            <span class="right" id="scheduleZone"></span>
          </div>
          <div id="scheduleDays"></div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('clock') ?> Reminders</h2>
          </div>
          <ul class="rule-list">
            <li><span class="r-num">1</span><div><b>Segregate at source.</b> Separate biodegradable, non-biodegradable, and recyclable waste before collection day.</div></li>
            <li><span class="r-num">2</span><div><b>Use the correct container.</b> Biodegradable in green bags, recyclables in clear bags, non-biodegradable in black bags.</div></li>
            <li><span class="r-num">3</span><div><b>Bring out within the window.</b> Only set waste out on the scheduled day, within the collection window.</div></li>
            <li><span class="r-num">4</span><div><b>No segregation, no collection.</b> Unsegregated waste may be left behind by the collection team.</div></li>
          </ul>
          <div class="next-hint" style="font-size:12.5px;color:var(--muted);border-top:1px solid var(--hairline-2);padding-top:14px;margin-top:6px">
            Need to change puroks? Update it in <a href="#profile" data-goto="profile">My profile</a> and your schedule updates automatically.
          </div>
        </section>
      </div>
    </section>

    <!-- ============================ ALERTS ============================ -->
    <section id="alerts" class="mt-3">
      <p class="section-title">Truck alerts</p>

      <section class="panel mb-2">
        <div class="panel-head">
          <h2><?= icon('truck') ?> Latest dispatch</h2>
        </div>
        <div id="alertLatest"></div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h2><?= icon('bell') ?> Dispatch notifications</h2>
        </div>
        <div id="alertHistory"></div>
      </section>
    </section>

    <!-- ============================ NOTICES ============================ -->
    <section id="notices" class="mt-3">
      <p class="section-title">Barangay notices</p>
      <section class="panel">
        <div class="panel-head">
          <h2><?= icon('megaphone') ?> Latest notices</h2>
          <span class="right">For <?= esc($user['zone_name'] ?? 'your purok') ?> and the whole barangay</span>
        </div>
        <div id="announcements"></div>
      </section>
    </section>

    <!-- ============================ REPORTS ============================ -->
    <section id="reports" class="mt-3">
      <p class="section-title">My missed-pickup reports</p>

      <div class="grid grid--2" style="margin-bottom:26px">
        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('flag') ?> Report a missed pickup</h2>
            <span class="right">Three steps</span>
          </div>
          <form id="reportForm" novalidate>
            <div class="field">
              <label>What was not collected?</label>
              <div class="pick" id="wastePick"></div>
              <p class="field-error"></p>
            </div>

            <div class="field">
              <label for="reportAddress">Address</label>
              <input class="input" type="text" id="reportAddress" maxlength="160" value="<?= esc($user['household'] ?? '') ?>" placeholder="Street / household">
              <p class="field-error"></p>
            </div>

            <div class="field">
              <label for="reportPhoto">Photo <span class="opt">optional, max 5 MB</span></label>
              <label class="upload" id="uploadZone">
                <input type="file" id="reportPhoto" accept="image/jpeg,image/png,image/webp">
                <div class="u-ico"><?= icon('camera') ?></div>
                <div class="u-main" id="uploadMain">Attach a photo of the uncollected waste</div>
                <div class="u-sub" id="uploadSub">JPEG, PNG, or WebP</div>
              </label>
            </div>

            <div class="field">
              <label for="reportNotes">Notes <span class="opt">optional</span></label>
              <textarea class="input" id="reportNotes" maxlength="500" rows="2" placeholder="Short description of the missed pickup"></textarea>
            </div>

            <button type="submit" class="btn btn--primary" style="width:100%">Submit report</button>
          </form>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('fileText') ?> Report history</h2>
            <span class="right" id="myReportsCount"></span>
          </div>
          <div id="myReports"></div>
        </section>
      </div>
    </section>

    <!-- ============================ NOTIFICATIONS ============================ -->
    <section id="notifications" class="mt-3">
      <p class="section-title">Notifications</p>
      <section class="panel">
        <div class="panel-head">
          <h2><?= icon('bell') ?> All notifications</h2>
          <button type="button" class="btn btn--ghost btn--sm" onclick="markAllRead()">Mark all read</button>
        </div>
        <div id="notifListFull"></div>
      </section>
    </section>

    <!-- ============================ PROFILE ============================ -->
    <section id="profile" class="mt-3">
      <p class="section-title">My profile</p>

      <div class="grid grid--2">
        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('users') ?> Account details</h2>
          </div>
          <form id="profileForm" novalidate>
            <div class="field">
              <label for="pfName">Full name</label>
              <input class="input" type="text" id="pfName" maxlength="90">
              <p class="field-error"></p>
            </div>
            <div class="form-row">
              <div class="field">
                <label for="pfEmail">Email</label>
                <input class="input" type="email" id="pfEmail" maxlength="160">
                <p class="field-error"></p>
              </div>
              <div class="field">
                <label for="pfUser">Username <span class="opt">optional</span></label>
                <input class="input" type="text" id="pfUser" maxlength="60">
                <p class="field-error"></p>
              </div>
            </div>
            <div class="form-row">
              <div class="field">
                <label for="pfZone">Purok / zone</label>
                <select class="select" id="pfZone">
                  <option value="">Select your purok…</option>
                  <?php foreach ($zones as $z): ?>
                    <option value="<?= (int)$z['id'] ?>"><?= esc($z['name']) ?> — <?= esc($z['code']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="field-error"></p>
              </div>
              <div class="field">
                <label for="pfPhone">Mobile <span class="opt">optional</span></label>
                <input class="input" type="tel" id="pfPhone" maxlength="24">
                <p class="field-error"></p>
              </div>
            </div>
            <div class="field">
              <label for="pfHouse">Household / street address <span class="opt">optional</span></label>
              <input class="input" type="text" id="pfHouse" maxlength="120">
              <p class="field-error"></p>
            </div>
            <button type="submit" class="btn btn--primary" style="width:100%">Save changes</button>
          </form>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2><?= icon('lock') ?> Change password</h2>
          </div>
          <form id="passwordForm" novalidate>
            <div class="field">
              <label for="pwCurrent">Current password</label>
              <input class="input" type="password" id="pwCurrent" maxlength="72" autocomplete="current-password">
              <p class="field-error"></p>
            </div>
            <div class="field">
              <label for="pwNew">New password</label>
              <input class="input" type="password" id="pwNew" maxlength="72" autocomplete="new-password">
              <div class="pass-hints">
                <span>8+ characters</span>
                <span>a number</span>
                <span>a letter</span>
                <span>no special symbols</span>
              </div>
              <p class="field-error"></p>
            </div>
            <div class="field">
              <label for="pwConfirm">Confirm new password</label>
              <input class="input" type="password" id="pwConfirm" maxlength="72" autocomplete="new-password">
              <p class="field-error"></p>
            </div>
            <button type="submit" class="btn btn--primary" style="width:100%">Update password</button>
          </form>
        </section>
      </div>
    </section>

  </main>
</div>

<!-- notifications drawer -->
<div class="notif" id="notifPanel">
  <div class="notif-head">
    <b>Notifications</b>
    <button onclick="markAllRead()">Mark all read</button>
  </div>
  <div id="notifList"></div>
</div>

<div class="toast"><span class="t-dot"></span><span class="t-text"></span></div>

<script>
window.KOLEKTA = {
  user: <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  zones: <?= json_encode($zones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  wasteTypes: <?= json_encode($wasteTypes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<script src="assets/js/app.js"></script>
<script src="assets/js/user.js"></script>
</body>
</html>
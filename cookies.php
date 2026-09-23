<?php
/**
 * Kolekta — Cookie Policy (template for the barangay).
 * Review and replace all [bracketed] placeholders before public use.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/page.php';

legal_page('Cookie Policy');
?>

<p class="legal--lead">
  Kolekta is designed to be <strong>privacy-respecting by default</strong>: it does not
  use advertising, analytics, or tracking cookies. This page explains the single cookie
  we set and how you can manage it.
</p>

<h2>1. What are cookies?</h2>
<p>Cookies are small text files a website stores on your device to remember information
between visits. Some are essential for a service to work; others are used to track and
profile visitors.</p>

<h2>2. The cookie Kolekta uses</h2>
<p>Kolekta sets one strictly necessary session cookie, <strong>PHPSESSID</strong>, when you
sign in. It keeps you signed in while you use the system and helps protect against request
forgery (CSRF). It is a session cookie — it expires when you close your browser or sign out —
and it contains no personal profile data in itself.</p>

<h2>3. What we do not use</h2>
<ul>
  <li>No advertising or social-media cookies.</li>
  <li>No analytics or fingerprinting scripts.</li>
  <li>No cross-site trackers of any kind.</li>
</ul>

<h2>4. Local storage preferences</h2>
<p>Your browser's local storage is used only to remember lightweight interface preferences,
such as acknowledging this policy notice and remembering which dispatch banner you have
dismissed. These stay on your own device and are not sent to or read by anyone except your
own browser.</p>

<h2>5. External resources</h2>
<p>Some Kolekta screens load fonts from Google Fonts and icons from standard icon fonts.
Fetching these public resources sends your device's IP address to those providers per their
own policies. No personal data is sent. If you prefer, the barangay can host these assets
locally — contact us to arrange it.</p>

<h2>6. Managing cookies</h2>
<p>Because the session cookie is required for the system to work, disabling cookies in your
browser will prevent you from signing in or using account features. Public pages such as
this policy remain viewable. You can clear cookies and local storage at any time through
your browser settings; doing so will sign you out.</p>

<h2>7. Contact</h2>
<p>Questions about this policy can be directed to:</p>
<address>
  <strong><?= esc(BARANGAY_NAME) ?></strong> — Solid Waste Management Office<br>
  [Barangay Hall contact person]<br>
  [Barangay Hall address]<br>
  [Barangay hotline / office hours]
</address>

<?php legal_page_close(); ?>
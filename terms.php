<?php
/**
 * Kolekta — Terms of Service (template for the barangay).
 * Review and replace all [bracketed] placeholders before public use.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/page.php';

legal_page('Terms of Service');
?>

<p class="legal--lead">
  These terms govern the use of <strong>Kolekta</strong>, operated by
  <?= esc(BARANGAY_NAME )?> (the "barangay") under the framework of Republic Act No. 9003.
  By creating an account, a resident agrees to the terms below and acknowledges the
  Privacy Policy.
</p>

<h2>1. Eligibility</h2>
<p>Kolekta is intended for residents of this barangay and its authorized staff. You may
register one household account and must be at least 16 years old. Registering on behalf of
your household means you have the authority to agree to these terms for that household.</p>

<h2>2. Your account</h2>
<ul>
  <li>Provide accurate details — your name, contact information, purok, and address.</li>
  <li>Keep your password confidential. You are responsible for activity under your account.</li>
  <li>Notify the barangay if your contact details change, so alerts reach you correctly.</li>
</ul>

<h2>3. Acceptable use</h2>
<ul>
  <li>Use Kolekta only for its intended purpose: collection alerts, schedules, and missed-pickup reporting.</li>
  <li>File a missed-pickup report only when your purok was actually scheduled and skipped.</li>
  <li>Do not upload offensive, unlawful, or unrelated photos or text.</li>
  <li>Do not attempt to access another user's account, bypass security, or disrupt the service.</li>
  <li>Do not create fake accounts or submit false reports. Repeat false reporting may lead to suspension.</li>
</ul>

<h2>4. Reporting a missed pickup</h2>
<p>A report records the address, waste type, and any photo of the uncollected waste. Filing
a report requests a follow-up; it does not guarantee a specific collection time, which
depends on barangay scheduling and resources. Reports remain pending until the barangay
marks them resolved.</p>

<h2>5. Dispatch alerts and notices</h2>
<p>Dispatch alerts indicate that collection is underway for your purok. Schedules and
notices may change at short notice — for weather, holidays, or truck maintenance — and the
barangay announces such changes in Kolekta as soon as possible. Collection hours
(e.g. 05:00 – 07:00) follow the barangay ordinance; waste set out outside collection hours
may not be collected.</p>

<h2>6. Barangay administration</h2>
<p>The Solid Waste Management Office operates staff accounts to trigger dispatches, publish
schedules and notices, and manage the missed-pickup triage board. Administrative records of
dispatches and resolutions are maintained by the barangay for accountability under R.A. 9003.</p>

<h2>7. Suspension and termination</h2>
<p>The barangay may suspend or remove access to accounts that breach these terms, and may
decline to accept false or abusive reports. You may delete your account by contacting the
barangay; deleting your account does not erase records already required for the barangay's
audit and reporting duties.</p>

<h2>8. Disclaimer and limitation</h2>
<p>Kolekta is provided "as is" and "as available." While the barangay maintains reasonable
security and availability, it is not liable for interruptions, missed alerts caused by
device or network conditions, or losses arising out of misuse by the account holder.
Collection information is advisory and does not replace the official ordinance or the
judgment of barangay staff.</p>

<h2>9. Changes to these terms</h2>
<p>The barangay may update these terms as the service evolves. Material changes will be
announced in Kolekta and, where applicable, by barangay notice. Continued use after a change
constitutes acceptance.</p>

<h2>10. Contact</h2>
<p>For questions about these terms, contact:</p>
<address>
  <strong><?= esc(BARANGAY_NAME) ?></strong> — Solid Waste Management Office<br>
  [Barangay Hall contact person]<br>
  [Barangay Hall address]<br>
  [Barangay hotline / office hours]
</address>

<?php legal_page_close(); ?>
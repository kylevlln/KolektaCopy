<?php
/**
 * Kolekta — Privacy Policy (template for the barangay).
 * Review and replace all [bracketed] placeholders before public use.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/page.php';

legal_page('Privacy Policy');
?>

<p class="legal--lead">
  <?= esc(BARANGAY_NAME) ?> ("the barangay") operates <strong>Kolekta</strong>, a waste
  collection dispatch and missed-pickup reporting system for the residents of this
  barangay. Under <strong>Republic Act No. 10173</strong> (Data Privacy Act of 2012), the
  barangay is the personal information controller of the data processed in Kolekta. This
  policy explains what data we collect, why, and the rights you have over it.
</p>

<h2>1. Data we collect</h2>
<p>When you create an account and use Kolekta, we process:</p>
<ul>
  <li><strong>Account details</strong> — your full name, email address, optional username, and optional mobile number.</li>
  <li><strong>Household details</strong> — the purok / zone you belong to and the optional household / street address you provide.</li>
  <li><strong>Report data</strong> — the address of a missed pickup, the waste type involved, your notes, and any photo you attach (which may show your home or surrounding property).</li>
  <li><strong>System records</strong> — the date and time you filed a report, received a dispatch alert, or read a notification.</li>
</ul>

<h2>2. Why we process this data</h2>
<ul>
  <li>To operate the collection calendar and send dispatch alerts for your purok (R.A. 9003 obligations).</li>
  <li>To receive, triage, and resolve missed-pickup reports with an accountable record.</li>
  <li>To run the system securely and prevent fraud, such as false or duplicate reporting.</li>
  <li>To communicate barangay notices, including collection cancellations.</li>
</ul>
<p>The legal bases are the barangay's legitimate interest in carrying out its solid waste
management functions and, for the use of your contact details, your consent given when you
register your household.</p>

<h2>3. Sharing of information</h2>
<p>We do not sell, rent, or trade your information. Access is limited to barangay staff and
the system administrator who operate the Solid Waste Management Office. Data is never
shared with third parties except as required by law or competent authority, or to a
processor under contract with the barangay.</p>

<h2>4. Storage and retention</h2>
<p>Data is stored on the barangay's own server (a local installation of Kolekta). Account
data and report records are kept while your account is active and for the period needed to
carry out the barangay's audit and record-keeping duties, after which they are deleted or
anonymized. Photos attached to reports are retained only as long as needed for resolution
and any required documentation.</p>

<h2>5. Security</h2>
<p>We apply reasonable safeguards, including encrypted passwords, session protection, and
access limited to authorized staff. In the event of a breach involving your personal data,
the barangay will notify affected residents and the National Privacy Commission as required
by R.A. 10173 and its implementing rules.</p>

<h2>6. Your rights</h2>
<p>Under the Data Privacy Act, you may:</p>
<ul>
  <li><strong>Request access</strong> to the personal data we hold about you.</li>
  <li><strong>Request correction</strong> of inaccurate or incomplete data.</li>
  <li><strong>Request deletion or blocking</strong> of data no longer needed for our purposes.</li>
  <li><strong>Withdraw consent</strong> for the use of your contact details, subject to lawful grounds for continued processing.</li>
  <li><strong>File a complaint</strong> with the National Privacy Commission.</li>
</ul>
<p>Filing a report does not imply your consent to the use of your photos beyond the purpose
of resolving that report.</p>

<h2>7. Contact</h2>
<p>Questions, requests, and concerns about this policy should be addressed to the barangay's
Data Protection contact:</p>
<address>
  <strong><?= esc(BARANGAY_NAME) ?></strong> — Solid Waste Management Office<br>
  [Data Protection Officer / Barangay Hall contact person]<br>
  [Barangay Hall address]<br>
  [Barangay hotline / office hours]
</address>
<p>We may update this policy from time to time. Significant changes will be announced in
Kolekta and by barangay notice.</p>

<?php legal_page_close(); ?>
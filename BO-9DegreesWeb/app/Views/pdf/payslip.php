<?php
// Variables: $payout, $sales, $summary, $companyName, $companyAddress, $companyRegistration, $companyPhone, $generatedDate
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
  body { font-size: 11px; color: #111; }
  .header { padding: 24px 32px 18px; border-bottom: 3px solid #00C4CC; display: table; width: 100%; }
  .header-left { display: table-cell; vertical-align: top; }
  .header-right { display: table-cell; vertical-align: top; text-align: right; }
  .company-name { font-size: 14px; font-weight: bold; margin-top: 6px; }
  .company-sub { font-size: 9px; color: #aaa; margin-top: 2px; }
  .doc-type { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 3px; }
  .doc-period { font-size: 12px; font-weight: bold; color: #00A0A6; margin-bottom: 3px; }
  .doc-ref { font-size: 8px; color: #aaa; font-family: monospace; }
  .body { padding: 18px 32px 20px; }
  .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #aaa; padding-bottom: 5px; border-bottom: 1px solid #f0f0f0; margin-bottom: 10px; margin-top: 14px; }
  .two-col { display: table; width: 100%; margin-bottom: 6px; }
  .col { display: table-cell; width: 50%; padding-bottom: 8px; }
  .field-label { font-size: 8px; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 2px; }
  .field-value { font-size: 11px; font-weight: bold; }
  .field-value.mono { font-family: monospace; font-size: 10px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  thead tr { background: #fafafa; }
  th { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #bbb; padding: 6px 6px; text-align: left; border-bottom: 1px solid #f0f0f0; }
  th.right { text-align: right; }
  td { font-size: 10px; color: #444; padding: 6px 6px; border-bottom: 1px solid #fafafa; }
  td.right { text-align: right; }
  tfoot td { font-size: 11px; font-weight: bold; color: #111; border-top: 2px solid #eee; padding: 7px 6px; }
  tfoot td.cyan { color: #00A0A6; font-size: 12px; }
  .total-box { background: #f8fffe; border: 1px solid #d0f0f0; border-left: 4px solid #00C4CC; border-radius: 4px; padding: 14px 16px; margin-bottom: 12px; display: table; width: 100%; }
  .total-left { display: table-cell; vertical-align: middle; }
  .total-right { display: table-cell; text-align: right; vertical-align: middle; }
  .total-label { font-size: 11px; font-weight: bold; color: #111; }
  .total-sub { font-size: 9px; color: #aaa; margin-top: 2px; }
  .total-amount { font-size: 20px; font-weight: bold; color: #00A0A6; }
  .bank-box { background: #f8fffe; border: 1px solid #d0f0f0; border-radius: 4px; padding: 10px 14px; margin-bottom: 12px; }
  .bank-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #00A0A6; margin-bottom: 7px; }
  .bank-row { display: table; width: 100%; }
  .bank-cell { display: table-cell; }
  .bank-label { font-size: 8px; color: #aaa; margin-bottom: 2px; }
  .bank-value { font-size: 10px; font-weight: bold; }
  .declaration { font-size: 8px; color: #bbb; line-height: 1.6; padding: 8px 10px; background: #fafafa; border: 1px solid #f0f0f0; border-radius: 4px; margin-bottom: 12px; }
  .sig-row { display: table; width: 100%; margin-bottom: 8px; }
  .sig-cell { display: table-cell; width: 48%; padding-right: 4%; }
  .sig-line { border-top: 1px solid #ddd; margin-top: 28px; margin-bottom: 5px; }
  .sig-name { font-size: 9px; font-weight: bold; color: #555; }
  .sig-role { font-size: 8px; color: #aaa; }
  .footer { border-top: 1px solid #f0f0f0; padding: 10px 32px; display: table; width: 100%; }
  .footer-left { display: table-cell; font-size: 8px; color: #bbb; }
  .footer-right { display: table-cell; text-align: right; font-size: 8px; color: #bbb; }
</style>
</head>
<body>
<div class="header">
  <div class="header-left">
    <div class="company-name"><?= esc($companyName) ?></div>
    <div class="company-sub"><?= esc($companyAddress) ?></div>
    <?php if ($companyRegistration): ?><div class="company-sub">SSM Reg: <?= esc($companyRegistration) ?><?= $companyPhone ? ' · Tel: ' . esc($companyPhone) : '' ?></div><?php endif; ?>
  </div>
  <div class="header-right">
    <div class="doc-type">Payslip</div>
    <div class="doc-period"><?= esc($payout['period_label']) ?></div>
    <div class="doc-ref">REF: <?= esc($payout['payslip_reference']) ?></div>
    <div class="doc-ref" style="margin-top:2px">Issued: <?= esc($generatedDate) ?></div>
  </div>
</div>
<div class="body">
  <div class="section-title">Payee Details</div>
  <div class="two-col">
    <div class="col"><div class="field-label">Full Name (as per IC)</div><div class="field-value"><?= esc($payout['full_name'] ?: $payout['ambassador_name']) ?></div></div>
    <div class="col"><div class="field-label">IC / Passport No.</div><div class="field-value mono"><?= esc($payout['ic'] ?? '—') ?></div></div>
    <div class="col"><div class="field-label">Role / Designation</div><div class="field-value"><?= esc($payout['role_name']) ?> (Sales)</div></div>
    <div class="col"><div class="field-label">Team</div><div class="field-value"><?= esc($payout['team_name'] ?? '—') ?></div></div>
    <div class="col"><div class="field-label">Payment Period</div><div class="field-value"><?= esc($payout['period_full_label']) ?></div></div>
    <div class="col"><div class="field-label">Payment Date</div><div class="field-value"><?= esc($generatedDate) ?></div></div>
  </div>

  <div class="section-title">Commission Earnings</div>
  <table>
    <thead><tr><th>Description</th><th class="right">Amount (RM)</th></tr></thead>
    <tbody>
      <?php foreach ($sales as $sale): ?>
      <tr>
        <td><?= esc($sale['sale_type']) ?> Sales Commission (<?= $sale['confirmed_commission_rate'] ?>% × RM <?= number_format($sale['gross_amount'], 2) ?>)</td>
        <td class="right"><?= number_format($sale['commission_amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td>Gross Commission Earned</td><td class="right cyan">RM <?= number_format($payout['total_commission'], 2) ?></td></tr>
    </tfoot>
  </table>

  <div class="total-box">
    <div class="total-left">
      <div class="total-label">Net Amount Payable</div>
      <div class="total-sub">No statutory deductions (freelance contractor)</div>
    </div>
    <div class="total-right">
      <div class="total-amount">RM <?= number_format($payout['total_commission'], 2) ?></div>
    </div>
  </div>

  <div class="bank-box">
    <div class="bank-title">Payment To</div>
    <div class="bank-row">
      <div class="bank-cell"><div class="bank-label">Bank</div><div class="bank-value"><?= esc($payout['bank_name'] ?? '—') ?></div></div>
      <div class="bank-cell"><div class="bank-label">Account No.</div><div class="bank-value"><?= esc($payout['bank_account_number'] ?? '—') ?></div></div>
      <div class="bank-cell"><div class="bank-label">Account Holder</div><div class="bank-value"><?= esc($payout['bank_owner_name'] ?? '—') ?></div></div>
    </div>
  </div>

  <div class="declaration">
    This payslip is issued as a formal record of commission payment for the period stated above. This document may be used for personal income tax declaration purposes under Lembaga Hasil Dalam Negeri Malaysia (LHDN). The payee is responsible for declaring this income in their annual income tax return (BE/B Form).
  </div>

  <div class="sig-row">
    <div class="sig-cell"><div class="sig-line"></div><div class="sig-name">Authorised Signatory</div><div class="sig-role"><?= esc($companyName) ?></div></div>
    <div class="sig-cell"><div class="sig-line"></div><div class="sig-name">Payee Acknowledgement</div><div class="sig-role"><?= esc($payout['full_name'] ?: $payout['ambassador_name']) ?></div></div>
  </div>
</div>
<div class="footer">
  <div class="footer-left">Computer-generated document · Ref: <?= esc($payout['payslip_reference']) ?></div>
  <div class="footer-right">Page 1 of 1</div>
</div>
</body>
</html>

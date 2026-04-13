<?php
// Variables available: $payout, $sales, $summary, $companyName, $generatedDate
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
  body { font-size: 11px; color: #111; }
  .header { padding: 24px 32px 18px; border-bottom: 1px solid #f0f0f0; display: table; width: 100%; }
  .header-left { display: table-cell; vertical-align: top; }
  .header-right { display: table-cell; vertical-align: top; text-align: right; }
  .company-name { font-size: 14px; font-weight: bold; margin-top: 6px; }
  .company-sub { font-size: 9px; color: #aaa; margin-top: 2px; }
  .doc-type { font-size: 10px; font-weight: bold; color: #00A0A6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .doc-ref { font-size: 9px; color: #999; font-family: monospace; }
  .cyan-bar { height: 3px; background: #00C4CC; margin-bottom: 20px; }
  .body { padding: 0 32px 24px; }
  .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #aaa; padding-bottom: 5px; border-bottom: 1px solid #f0f0f0; margin-bottom: 10px; margin-top: 16px; }
  .info-grid { display: table; width: 100%; margin-bottom: 8px; }
  .info-cell { display: table-cell; width: 50%; padding-bottom: 6px; }
  .info-label { font-size: 8px; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 2px; }
  .info-value { font-size: 11px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  thead tr { background: #fafafa; }
  th { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #bbb; padding: 6px 6px; text-align: left; border-bottom: 1px solid #f0f0f0; }
  th.right { text-align: right; }
  td { font-size: 10px; color: #444; padding: 6px 6px; border-bottom: 1px solid #fafafa; }
  td.right { text-align: right; }
  td.cyan { color: #00A0A6; font-weight: bold; }
  .pill { padding: 1px 5px; border-radius: 10px; font-size: 8px; font-weight: bold; }
  .pill-table { background: #e6fafa; color: #007a80; }
  .pill-bgo { background: #f0f0ff; color: #5555aa; }
  .summary-box { background: #f8fffe; border: 1px solid #d0f0f0; border-radius: 4px; padding: 12px 14px; margin-bottom: 16px; }
  .summary-row { display: table; width: 100%; padding: 3px 0; }
  .summary-lbl { display: table-cell; font-size: 10px; color: #777; }
  .summary-val { display: table-cell; text-align: right; font-size: 10px; font-weight: bold; color: #333; }
  .summary-total .summary-lbl { font-size: 11px; font-weight: bold; color: #111; border-top: 1px solid #c8eeee; padding-top: 6px; margin-top: 4px; }
  .summary-total .summary-val { font-size: 13px; font-weight: bold; color: #00A0A6; border-top: 1px solid #c8eeee; padding-top: 6px; }
  .footer { border-top: 1px solid #f0f0f0; padding: 10px 32px; display: table; width: 100%; }
  .footer-left { display: table-cell; font-size: 8px; color: #bbb; }
  .footer-right { display: table-cell; text-align: right; font-size: 8px; color: #bbb; }
</style>
</head>
<body>
<div class="header">
  <div class="header-left">
    <div class="company-name"><?= esc($companyName) ?></div>
    <div class="company-sub">Commission Statement</div>
  </div>
  <div class="header-right">
    <div class="doc-type">Payout Summary</div>
    <div class="doc-ref"><?= esc($payout['reference']) ?></div>
    <div class="doc-ref" style="margin-top:3px">Issued: <?= esc($generatedDate) ?></div>
  </div>
</div>
<div class="cyan-bar"></div>
<div class="body">
  <div class="section-title">Ambassador Details</div>
  <div class="info-grid">
    <div class="info-cell"><div class="info-label">Name</div><div class="info-value"><?= esc($payout['ambassador_name']) ?></div></div>
    <div class="info-cell"><div class="info-label">Period</div><div class="info-value"><?= esc($payout['period_label']) ?></div></div>
    <div class="info-cell"><div class="info-label">Role</div><div class="info-value"><?= esc($payout['role_name']) ?></div></div>
    <div class="info-cell"><div class="info-label">Team</div><div class="info-value"><?= esc($payout['team_name'] ?? '—') ?></div></div>
  </div>

  <div class="section-title">Sales Breakdown</div>
  <table>
    <thead>
      <tr>
        <th>Date</th><th>Type</th><th>Table</th>
        <th class="right">Gross (RM)</th><th class="right">Rate</th><th class="right">Commission</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sales as $sale): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($sale['date'])) ?></td>
        <td><span class="pill pill-<?= strtolower($sale['sale_type']) ?>"><?= esc($sale['sale_type']) ?></span></td>
        <td><?= esc($sale['table_number'] ?? '—') ?></td>
        <td class="right"><?= number_format($sale['gross_amount'], 2) ?></td>
        <td class="right"><?= $sale['confirmed_commission_rate'] ?>%</td>
        <td class="right cyan"><?= number_format($sale['commission_amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="section-title">Summary</div>
  <div class="summary-box">
    <div class="summary-row"><span class="summary-lbl">Table Sales</span><span class="summary-val">RM <?= number_format($summary['table_sales'], 2) ?></span></div>
    <div class="summary-row"><span class="summary-lbl">BGO Sales</span><span class="summary-val">RM <?= number_format($summary['bgo_sales'], 2) ?></span></div>
    <div class="summary-row"><span class="summary-lbl">Table Commission</span><span class="summary-val">RM <?= number_format($summary['table_commission'], 2) ?></span></div>
    <div class="summary-row"><span class="summary-lbl">BGO Commission</span><span class="summary-val">RM <?= number_format($summary['bgo_commission'], 2) ?></span></div>
    <div class="summary-row"><span class="summary-lbl">KPI Bonus Applied</span><span class="summary-val"><?= $summary['kpi_applied'] ? 'Yes' : 'No' ?></span></div>
    <div class="summary-row summary-total"><span class="summary-lbl">Total Commission</span><span class="summary-val">RM <?= number_format($payout['total_commission'], 2) ?></span></div>
  </div>
</div>
<div class="footer">
  <div class="footer-left">For reference only. Payment subject to confirmation.</div>
  <div class="footer-right"><?= esc($companyName) ?> · <?= esc($generatedDate) ?></div>
</div>
</body>
</html>

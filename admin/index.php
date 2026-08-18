<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();
$db = get_db();

// Total leads
$totalLeads = (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn();

// Leads captured today
$leadsToday = (int) $db->query("SELECT COUNT(*) FROM leads WHERE date(created_at) = date('now')")->fetchColumn();

// Generations today across all usage
$usesToday = (int) $db->query("SELECT COALESCE(SUM(use_count),0) FROM usage_tracking WHERE use_date = date('now')")->fetchColumn();

// Rough conversion: leads / unique fingerprints seen (all-time)
$uniqueFingerprints = (int) $db->query('SELECT COUNT(DISTINCT fingerprint) FROM usage_tracking')->fetchColumn();
$conversionRate = $uniqueFingerprints > 0 ? round(($totalLeads / $uniqueFingerprints) * 100, 1) : 0;

// Per-tool breakdown, last 7 days
$stmt = $db->query("
    SELECT tool,
           SUM(use_count) as total_uses,
           COUNT(DISTINCT fingerprint) as unique_visitors
    FROM usage_tracking
    WHERE use_date >= date('now', '-7 days')
    GROUP BY tool
    ORDER BY total_uses DESC
");
$toolStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header('dashboard', 'Dashboard');
?>

<h1>Dashboard</h1>
<p class="page-sub">Snapshot across all tools. Numbers reset daily at midnight server time.</p>

<div class="stat-grid">
  <div class="stat-cell">
    <div class="label">Total leads</div>
    <div class="value"><?= number_format($totalLeads) ?></div>
  </div>
  <div class="stat-cell">
    <div class="label">Leads today</div>
    <div class="value"><?= number_format($leadsToday) ?></div>
  </div>
  <div class="stat-cell">
    <div class="label">Tool runs today</div>
    <div class="value"><?= number_format($usesToday) ?></div>
  </div>
  <div class="stat-cell">
    <div class="label">Visitor → lead rate</div>
    <div class="value"><?= $conversionRate ?>%</div>
  </div>
</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Tool</th>
        <th>Runs (7d)</th>
        <th>Unique visitors (7d)</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($toolStats)): ?>
        <tr><td colspan="4" style="color:var(--slate)">No usage yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($toolStats as $row): ?>
        <tr>
          <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['tool']))) ?></td>
          <td><?= number_format((int)$row['total_uses']) ?></td>
          <td><?= number_format((int)$row['unique_visitors']) ?></td>
          <td><a href="/admin/tool-config.php?tool=<?= urlencode($row['tool']) ?>" style="color:var(--signal)">Configure →</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_footer(); ?>

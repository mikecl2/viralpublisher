<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();
$db = get_db();

// CSV export
if (isset($_GET['export'])) {
    $rows = $db->query('SELECT email, source_tool, created_at FROM leads ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="viralpublisher-leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'source_tool', 'created_at']);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$stmt = $db->query('SELECT email, source_tool, created_at FROM leads ORDER BY created_at DESC LIMIT 200');
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn();

admin_header('leads', 'Leads');
?>

<h1>Leads</h1>
<p class="page-sub"><?= number_format($total) ?> total, shared across all tools. Showing most recent 200.</p>

<div style="margin-bottom:20px;">
  <a href="?export=1" class="btn btn-secondary" style="margin-top:0;">Export CSV</a>
</div>

<div class="card" style="padding:0;">
  <table>
    <thead>
      <tr><th>Email</th><th>Source tool</th><th>Captured</th></tr>
    </thead>
    <tbody>
      <?php if (empty($leads)): ?>
        <tr><td colspan="3" style="color:var(--slate); padding:20px;">No leads captured yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($leads as $lead): ?>
        <tr>
          <td><?= htmlspecialchars($lead['email']) ?></td>
          <td><span class="badge"><?= htmlspecialchars(str_replace('_', ' ', $lead['source_tool'])) ?></span></td>
          <td style="color:var(--slate)"><?= htmlspecialchars($lead['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_footer(); ?>

<?php
/**
 * admin/lib/layout.php — shared chrome for admin pages.
 * Kept as plain functions rather than a template engine — matches the
 * rest of the stack's no-framework approach.
 */

function admin_header(string $activePage, string $title): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — Admin</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');
  :root{
    --ink:#10181B; --ink-soft:#182226; --paper:#F6F4EE; --signal:#C6FF3D;
    --coral:#FF6B4A; --slate:#8B96A3; --line:rgba(246,244,238,0.10);
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{ background:var(--ink); color:var(--paper); font-family:'Inter',sans-serif; }
  a{ color:inherit; text-decoration:none; }
  .shell{ display:flex; min-height:100vh; }

  .sidebar{ width:220px; border-right:1px solid var(--line); padding:28px 20px; flex-shrink:0; }
  .logo{ font-family:'Space Grotesk'; font-weight:700; font-size:16px; margin-bottom:36px; }
  .logo span{ color:var(--signal); }
  .nav-item{
    display:block; padding:10px 12px; font-size:14px; color:var(--slate); border-radius:2px;
    margin-bottom:2px;
  }
  .nav-item.active{ background:var(--ink-soft); color:var(--paper); }
  .nav-item:hover{ color:var(--paper); }
  .nav-section{ font-family:'IBM Plex Mono'; font-size:10px; text-transform:uppercase; letter-spacing:0.06em;
    color:var(--slate); margin:24px 0 8px; }
  .logout{ margin-top:40px; font-size:13px; color:var(--slate); }

  .main{ flex:1; padding:36px 44px; max-width:980px; }
  h1{ font-family:'Space Grotesk'; font-size:24px; font-weight:600; margin-bottom:6px; }
  .page-sub{ color:var(--slate); font-size:14px; margin-bottom:32px; }

  .card{ border:1px solid var(--line); background:var(--ink-soft); padding:24px; border-radius:2px; }
  .stat-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--line);
    border:1px solid var(--line); margin-bottom:32px; }
  .stat-cell{ background:var(--ink); padding:22px; }
  .stat-cell .label{ font-size:12px; color:var(--slate); margin-bottom:8px; }
  .stat-cell .value{ font-family:'IBM Plex Mono'; font-size:26px; color:var(--signal); }

  table{ width:100%; border-collapse:collapse; font-size:14px; }
  th{ text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--slate);
    padding:10px 14px; border-bottom:1px solid var(--line); font-weight:500; }
  td{ padding:12px 14px; border-bottom:1px solid var(--line); }
  tr:hover td{ background:var(--ink-soft); }

  .badge{ font-family:'IBM Plex Mono'; font-size:10px; padding:3px 8px; border-radius:20px;
    border:1px solid var(--line); color:var(--slate); }
  .badge.free{ border-color:var(--signal); color:var(--signal); }

  label{ display:block; font-size:13px; color:var(--slate); margin:18px 0 8px; }
  input[type=text], input[type=number], select, textarea{
    width:100%; background:var(--ink); border:1px solid var(--line); color:var(--paper);
    padding:11px 14px; font-size:14px; font-family:'Inter'; outline:none; border-radius:2px;
  }
  input:focus, select:focus, textarea:focus{ border-color:var(--signal); }
  textarea{ min-height:160px; font-family:'IBM Plex Mono'; font-size:13px; line-height:1.6; resize:vertical; }
  .row{ display:flex; gap:20px; }
  .row > div{ flex:1; }

  button, .btn{
    background:var(--signal); color:var(--ink); border:none; padding:11px 20px;
    font-weight:600; font-size:14px; cursor:pointer; margin-top:24px; border-radius:2px;
    display:inline-block;
  }
  .btn-secondary{ background:transparent; border:1px solid var(--line); color:var(--paper); }

  .flash{ background:rgba(198,255,61,0.1); border:1px solid var(--signal); color:var(--signal);
    padding:12px 16px; font-size:13px; margin-bottom:24px; border-radius:2px; }
</style>
</head>
<body>
<div class="shell">
  <div class="sidebar">
    <div class="logo">viral<span>publisher</span></div>

    <div class="nav-section">Overview</div>
    <a class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="/admin/index.php">Dashboard</a>
    <a class="nav-item <?= $activePage === 'leads' ? 'active' : '' ?>" href="/admin/leads.php">Leads</a>

    <div class="nav-section">Tools</div>
    <a class="nav-item <?= $activePage === 'hook_generator' ? 'active' : '' ?>" href="/admin/tool-config.php?tool=hook_generator">Hook Generator</a>
    <a class="nav-item <?= $activePage === 'score_checker' ? 'active' : '' ?>" href="/admin/tool-config.php?tool=score_checker">Score Checker</a>
    <a class="nav-item <?= $activePage === 'script_builder' ? 'active' : '' ?>" href="/admin/tool-config.php?tool=script_builder">Script Builder</a>

    <div class="logout"><a href="/admin/logout.php">Sign out</a></div>
  </div>
  <div class="main">
<?php
}

function admin_footer(): void {
?>
  </div>
</div>
</body>
</html>
<?php
}

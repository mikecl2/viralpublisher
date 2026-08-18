<?php
require_once __DIR__ . '/lib/auth.php';

admin_boot_session();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (attempt_admin_login($password)) {
        header('Location: /admin/index.php');
        exit;
    }
    $error = 'Incorrect password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin — viralpublisher.com</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500&display=swap');
  :root{ --ink:#10181B; --paper:#F6F4EE; --signal:#C6FF3D; --line:rgba(246,244,238,0.10); --slate:#8B96A3; }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{
    background:var(--ink); color:var(--paper); font-family:'Inter',sans-serif;
    height:100vh; display:flex; align-items:center; justify-content:center;
  }
  .box{ width:340px; }
  .logo{ font-family:'Space Grotesk'; font-weight:700; font-size:19px; margin-bottom:36px; text-align:center; }
  .logo span{ color:var(--signal); }
  label{ font-size:13px; color:var(--slate); display:block; margin-bottom:8px; }
  input{
    width:100%; background:transparent; border:1px solid var(--line); color:var(--paper);
    padding:13px 16px; font-size:14px; font-family:'Inter'; outline:none; margin-bottom:16px;
  }
  input:focus{ border-color:var(--signal); }
  button{
    width:100%; background:var(--signal); color:var(--ink); border:none; padding:13px;
    font-weight:600; font-size:14px; cursor:pointer;
  }
  .error{ color:#FF6B4A; font-size:13px; margin-bottom:16px; }
</style>
</head>
<body>
  <div class="box">
    <div class="logo">viral<span>publisher</span> · admin</div>
    <form method="POST">
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <label>Password</label>
      <input type="password" name="password" autofocus required>
      <button type="submit">Sign in</button>
    </form>
  </div>
</body>
</html>

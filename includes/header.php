<?php
/**
 * Layout header — ใช้กับทุกหน้าหลัง login
 * ตัวแปรที่ต้องกำหนดก่อน include: $pageTitle, $activeNav
 */
$user = require_login();
$nav = [
    'dashboard'    => ['dashboard.php',    'Dashboard',          '◧'],
    'subjects'     => ['subjects.php',     'รายวิชา',            '▤'],
    'availability' => ['availability.php', 'เวลาว่าง',           '◷'],
    'generate'     => ['generate.php',     'สร้างตาราง (GA)',     '⚙'],
    'schedule'     => ['schedule.php',     'ตารางอ่านหนังสือ',     '▦'],
    'progress'     => ['progress.php',     'ความก้าวหน้า',        '▰'],
    'experiments'  => ['experiments.php',  'การทดลอง GA',        '⚗'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
</head>
<body>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php">
      <img src="assets/images/logo.svg" alt="" width="32" height="32">
      <span>Smart Study<br><small>Scheduler</small></span>
    </a>
    <nav class="nav">
      <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
        <a href="<?= $href ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>">
          <span class="nav-icon" aria-hidden="true"><?= $icon ?></span><?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <a href="profile.php" class="<?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
        <span><?= e($user['name']) ?><br><small><?= e($user['email']) ?></small></span>
      </a>
      <a href="logout.php" class="logout">ออกจากระบบ</a>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <main class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="เมนู">☰</button>
      <h1><?= e($pageTitle ?? '') ?></h1>
      <div class="topbar-actions"><?= $topbarActions ?? '' ?></div>
    </header>
    <div class="content">

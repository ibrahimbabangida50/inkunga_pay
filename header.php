<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/lang.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INKUNGA Pay - <?= t('tagline') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="icon" href="assets/vusa-logo.png">
    <style>
      .bg-teal{background-color:#16A085}
      .bg-teal-light{background-color:#E8F8F5}
      .text-teal{color:#16A085}
      .border-teal{border-color:#16A085}
      .hover\:bg-teal-dark:hover{background-color:#138D75}
    </style>
</head>
<body class="bg-gray-50">
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-100">
  <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
    <a href="index.php" class="flex items-center gap-3">
      <img src="assets/vusa-logo.png" alt="VUSA" class="h-10 w-auto">
      <div class="text-xl font-bold"><span class="text-black">INKUNGA</span> <span class="bg-teal px-2.5 py-0.5 rounded text-white" style="background-color:#16A085">Pay</span></div>
    </a>
    <div class="flex gap-2 md:gap-4 items-center">
      <a href="index.php" class="hidden md:block text-gray-700 hover:text-teal text-sm font-medium" style="--tw-hover:#16A085"><?= t('explore') ?></a>
      <?php if(isLoggedIn()): $u = currentUser(); ?>
        <a href="dashboard.php" class="hidden md:block text-gray-700 hover:text-black text-sm"><?= t('dashboard') ?></a>
        <?php if($u && $u['is_admin']): ?><a href="admin.php" class="border-black px-3 py-1 rounded-full font-semibold hover:bg-black hover:text-white" style="background-color:#white">Admin</a><?php endif; ?>
        <a href="create_campaign.php" class="border-black px-3 py-1 rounded-full font-semibold hover:bg-black hover:text-white" style="background-color:#white" onmouseover="this.style.backgroundColor='#white'" onmouseout="this.style.backgroundColor='#white''"><?= t('create') ?></a>
        <a href="logout.php" class="bg-gray-100 px-3 py-2 rounded-full text-gray-600 text-sm hover:bg-black hover:text-white transition"><?= t('logout') ?></a>
      <?php else: ?>
        <a href="login.php" class="border px-5 py-2 rounded-full text-sm font-semibold bg-white text-black border-black hover:bg-black hover:text-white transition"><?= t('login') ?></a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="max-w-7xl mx-auto px-4 py-6">

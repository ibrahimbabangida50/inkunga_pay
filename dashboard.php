<?php
require_once 'config/database.php';
requireLogin();
require_once 'includes/header.php';
$user = currentUser();
$uid = $_SESSION['user_id'];

if($user['is_admin']){
  $myCampaigns = $pdo->query("SELECT c.*, u.full_name as creator FROM campaigns c JOIN users u ON c.user_id=u.id WHERE c.status != 'deleted' ORDER BY c.created_at DESC")->fetchAll();
} else {
  $stmt = $pdo->prepare("SELECT c.*, u.full_name as creator FROM campaigns c JOIN users u ON c.user_id=u.id WHERE c.user_id=? AND c.status != 'deleted' ORDER BY c.created_at DESC");
  $stmt->execute([$uid]);
  $myCampaigns = $stmt->fetchAll();
}

$all = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status='active'")->fetchColumn();

// Total in USD - correct for multi-country
$myTotalUSD = $pdo->prepare("SELECT SUM(raised_amount_usd) FROM campaigns WHERE user_id=? AND status != 'deleted'");
$myTotalUSD->execute([$uid]);
$myTotalUSD = $myTotalUSD->fetchColumn() ?: 0;

$totalAllUSD = 0;
if($user['is_admin']){
  $totalAllUSD = $pdo->query("SELECT SUM(raised_amount_usd) FROM campaigns WHERE status != 'deleted'")->fetchColumn() ?: 0;
}

$myDonations = $pdo->prepare("SELECT d.*, c.title, c.currency FROM donations d JOIN campaigns c ON d.campaign_id=c.id WHERE c.user_id=? AND c.status != 'deleted' ORDER BY d.created_at DESC LIMIT 20");
$myDonations->execute([$uid]);
$myDonations = $myDonations->fetchAll();

if($user['is_admin']){
  $allDonations = $pdo->query("SELECT d.*, c.title, c.currency FROM donations d JOIN campaigns c ON d.campaign_id=c.id WHERE c.status != 'deleted' ORDER BY d.created_at DESC LIMIT 20")->fetchAll();
}
?>
<div class="max-w-6xl mx-auto">
  <?php if(isset($_GET['msg'])): ?><div class="bg-black text-white p-3 rounded-full text-sm mb-6 text-center"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
  <?php if(isset($_GET['verified'])): ?><div class="bg-green-50 border text-green-700 p-4 rounded-2xl mb-6">Verified! Waiting for admin approval to create campaigns.</div><?php endif; ?>
  <?php if(empty($user['admin_approved']) && !$user['is_admin']): ?>
  <div class="bg-yellow-50 border border-yellow-200 p-5 rounded-2xl mb-6">
    <h3 class="font-bold text-sm">Account Not Yet Activated</h3>
    <p class="text-xs text-gray-600 mt-1">Your account exists but admin has not activated it.You will be notified once approved.</p>
    <a href="dashboard.php" class="text-xs underline mt-2 inline-block">Refresh status</a>
  </div>
  <?php endif; ?>

  <div class="flex justify-between items-center mb-6">
    <div>
      <h1 class="text-2xl font-bold">Dashboard <?= $user['is_admin'] ? '' : '' ?></h1>
      <p class="text-sm text-gray-500">Hello, <?= htmlspecialchars($user['full_name']) ?> • <?= htmlspecialchars($user['country']) ?> • <?= $user['is_verified'] ? 'Verified' : 'Unverified' ?></p>
    </div>
    <div class="flex gap-2">
      <a href="logout.php" class="border px-4 py-2 rounded-full text-sm">Logout</a>
      <?php if(!empty($user['admin_approved']) || $user['is_admin']): ?>
      <a href="create_campaign.php" class="bg-yellow-400 text-black px-6 py-2 rounded-full text-sm font-semibold">+ New Campaign</a>
      <?php else: ?>
      <span class="bg-gray-200 text-gray-500 px-6 py-2 rounded-full text-sm">Pending Activation</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border rounded-2xl p-5"><div class="text-xs text-gray-400 uppercase">Active Campaigns</div><div class="text-2xl font-bold"><?= $all ?></div></div>
    <div class="bg-white border rounded-2xl p-5"><div class="text-xs text-gray-400 uppercase"><?= $user['is_admin'] ? 'All Campaigns' : 'My Campaigns' ?></div><div class="text-2xl font-bold"><?= count($myCampaigns) ?></div></div>
    <div class="bg-white border rounded-2xl p-5"><div class="text-xs text-gray-400 uppercase">Total Raised (USD)</div><div class="text-2xl font-bold">$<?= number_format($user['is_admin'] ? $totalAllUSD : $myTotalUSD, 2) ?></div><div class="text-[10px] text-gray-400">Common value across all currencies</div></div>
    <div class="bg-white border rounded-2xl p-5"><div class="text-xs text-gray-400 uppercase">Country</div><div class="text-2xl font-bold"><?= $user['country'] ?></div></div>
  </div>

  <div class="grid md:grid-cols-3 gap-8">
    <div class="md:col-span-2">
      <h2 class="font-bold mb-4"><?= $user['is_admin'] ? 'All Campaigns' : 'My Campaigns' ?></h2>
      <?php if(empty($myCampaigns)): ?><p class="text-gray-400 text-sm">No campaigns yet.</p><?php endif; ?>
      <div class="space-y-3">
      <?php foreach($myCampaigns as $c): 
        $cur = $c['currency'] ?? 'RWF';
        $goalUSD = $c['usd_amount'] > 0 ? $c['usd_amount'] : convertToUSD($c['goal_amount'], $cur);
        $raisedUSD = $c['raised_amount_usd'] > 0 ? $c['raised_amount_usd'] : convertToUSD($c['raised_amount'], $cur);
        $percent = $goalUSD > 0 ? min(100, ($raisedUSD / $goalUSD) * 100) : 0;
      ?>
        <div class="bg-white border rounded-2xl p-4">
          <div class="flex justify-between items-start">
            <div><div class="font-bold"><?= htmlspecialchars($c['title']) ?></div><div class="text-xs text-gray-400"><?= $c['status'] ?> • $<?= number_format($raisedUSD,2) ?> / $<?= number_format($goalUSD,2) ?> • <?= htmlspecialchars($c['location']) ?> (<?= $cur ?>) <?= $c['withdrawn'] ? '• Withdrawn' : '' ?> <?= $percent>=100 ? '• Complete' : '' ?></div>
            <div class="text-[11px] text-gray-400 mt-1"><?= number_format($c['raised_amount']) ?> <?= $cur ?> of <?= number_format($c['goal_amount']) ?> <?= $cur ?></div>
            <div class="w-32 bg-gray-100 rounded-full h-1.5 mt-2"><div class="bg-black h-1.5 rounded-full" style="width: <?= $percent ?>%"></div></div>
            </div>
            <a href="campaign.php?slug=<?= $c['slug'] ?>" class="text-sm border px-3 py-1 rounded-full">View</a>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h2 class="font-bold mb-4"><?= $user['is_admin'] ? 'Recent Donations' : 'Donations to You' ?></h2>
      <div class="bg-white border rounded-2xl p-4 space-y-3">
        <?php 
        $donList = $user['is_admin'] ? ($allDonations ?? []) : $myDonations;
        if(empty($donList)): ?><p class="text-xs text-gray-400">No donations yet</p><?php endif; ?>
        <?php foreach($donList as $d): ?>
          <div class="flex justify-between text-sm border-b pb-2 gap-2"><span class="truncate"><?= htmlspecialchars($d['donor_name']) ?> → <?= htmlspecialchars($d['title']) ?> (<?= $d['donor_currency'] ?>→<?= $d['currency'] ?? 'RWF' ?>)</span><span class="font-bold">$<?= number_format($d['amount_usd'] ?? 0,2) ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php if($user['is_admin']): ?>
      <div class="mt-6 bg-black text-white rounded-2xl p-5">
        <h3 class="font-bold text-sm mb-2">Admin</h3>
        <a href="admin.php" class="text-xs underline">Manage rates & approvals</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>

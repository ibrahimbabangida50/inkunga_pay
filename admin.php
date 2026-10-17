<?php
require_once 'config/database.php';
requireLogin();
require_once 'includes/header.php';
$user = currentUser();
if(!$user['is_admin']){ die("<div class='max-w-xl mx-auto bg-red-50 p-6 rounded-2xl mt-10'>Access denied. Admin only.</div>"); }

if(isset($_GET['approve_user'])){
    $pdo->prepare("UPDATE users SET is_verified=1, admin_approved=1, kyc_status='verified' WHERE id=?")->execute([$_GET['approve_user']]);
    header("Location: admin.php?msg=User approved & activated - can now create campaigns"); exit;
}
if(isset($_GET['reject_user'])){
    $pdo->prepare("UPDATE users SET is_verified=0, admin_approved=0, kyc_status='rejected' WHERE id=?")->execute([$_GET['reject_user']]);
    header("Location: admin.php?msg=User rejected"); exit;
}
if(isset($_GET['approve_campaign'])){
    $pdo->prepare("UPDATE campaigns SET status='active' WHERE id=?")->execute([$_GET['approve_campaign']]);
    header("Location: admin.php?msg=Campaign approved"); exit;
}
if(isset($_GET['reject_campaign'])){
    $pdo->prepare("UPDATE campaigns SET status='rejected' WHERE id=?")->execute([$_GET['reject_campaign']]);
    header("Location: admin.php?msg=Campaign rejected"); exit;
}
if(isset($_GET['delete_user'])){
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_GET['delete_user']]);
    header("Location: admin.php?msg=User deleted#db_users"); exit;
}
if(isset($_GET['delete_campaign'])){
    $pdo->prepare("DELETE FROM campaigns WHERE id=?")->execute([$_GET['delete_campaign']]);
    header("Location: admin.php?msg=Campaign deleted#db_campaigns"); exit;
}
if(isset($_GET['delete_donation'])){
    $pdo->prepare("DELETE FROM donations WHERE id=?")->execute([$_GET['delete_donation']]);
    header("Location: admin.php?msg=Donation deleted#db_donations"); exit;
}
if(isset($_GET['delete_rate'])){
    $pdo->prepare("DELETE FROM exchange_rates WHERE id=?")->execute([$_GET['delete_rate']]);
    header("Location: admin.php?msg=Rate deleted#exchange"); exit;
}

if($_SERVER['REQUEST_METHOD']=='POST'){
    if(isset($_POST['save_rate'])){
        $code = $_POST['currency_code'];
        $rate = floatval($_POST['rate_to_usd']);
        $date = $_POST['rate_date'] ?: date('Y-m-d');
        $pdo->prepare("INSERT INTO exchange_rates (currency_code, rate_to_usd, rate_date) VALUES (?,?,?) ON CONFLICT(currency_code, rate_date) DO UPDATE SET rate_to_usd=excluded.rate_to_usd")->execute([$code, $rate, $date]);
        header("Location: admin.php?msg=Rate updated#exchange"); exit;
    }
    if(isset($_POST['update_user'])){
        $id = $_POST['id'];
        $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, country=?, is_verified=?, is_admin=?, admin_approved=?, kyc_status=? WHERE id=?")
            ->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['country'], intval($_POST['is_verified']), intval($_POST['is_admin']), intval($_POST['admin_approved']), $_POST['kyc_status'], $id]);
        header("Location: admin.php?msg=User updated#db_users"); exit;
    }
    if(isset($_POST['update_campaign'])){
        $id = $_POST['id'];
        $usd = convertToUSD($_POST['goal_amount'], $_POST['currency']);
        $pdo->prepare("UPDATE campaigns SET title=?, category=?, story=?, goal_amount=?, raised_amount=?, raised_amount_usd=?, beneficiary_name=?, location=?, status=?, currency=?, usd_amount=?, withdrawn=? WHERE id=?")
            ->execute([$_POST['title'], $_POST['category'], $_POST['story'], floatval($_POST['goal_amount']), floatval($_POST['raised_amount']), floatval($_POST['raised_amount_usd']), $_POST['beneficiary_name'], $_POST['location'], $_POST['status'], $_POST['currency'], $usd, intval($_POST['withdrawn']), $id]);
        header("Location: admin.php?msg=Campaign updated#db_campaigns"); exit;
    }
    if(isset($_POST['update_donation'])){
        $id = $_POST['id'];
        $pdo->prepare("UPDATE donations SET donor_name=?, donor_phone=?, amount=?, amount_usd=?, amount_in_campaign_currency=?, payment_method=?, donor_country=?, donor_currency=?, status=? WHERE id=?")
            ->execute([$_POST['donor_name'], $_POST['donor_phone'], floatval($_POST['amount']), floatval($_POST['amount_usd']), floatval($_POST['amount_in_campaign_currency']), $_POST['payment_method'], $_POST['donor_country'], $_POST['donor_currency'], $_POST['status'], $id]);
        header("Location: admin.php?msg=Donation updated#db_donations"); exit;
    }
    if(isset($_POST['update_rate'])){
        $pdo->prepare("UPDATE exchange_rates SET currency_code=?, rate_to_usd=?, rate_date=? WHERE id=?")
            ->execute([$_POST['currency_code'], floatval($_POST['rate_to_usd']), $_POST['rate_date'], $_POST['id']]);
        header("Location: admin.php?msg=Rate updated#exchange"); exit;
    }
}

$pendingUsers = $pdo->query("SELECT * FROM users WHERE admin_approved=0 ORDER BY created_at DESC")->fetchAll();
$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 100")->fetchAll();
$pendingCampaigns = $pdo->query("SELECT c.*, u.full_name FROM campaigns c JOIN users u ON c.user_id=u.id WHERE c.status='pending' ORDER BY c.created_at DESC")->fetchAll();
$allCampaigns = $pdo->query("SELECT c.*, u.full_name FROM campaigns c JOIN users u ON c.user_id=u.id ORDER BY c.created_at DESC LIMIT 100")->fetchAll();
$allDonations = $pdo->query("SELECT d.*, c.title FROM donations d JOIN campaigns c ON d.campaign_id=c.id ORDER BY d.created_at DESC LIMIT 100")->fetchAll();
$ratesToday = $pdo->query("SELECT * FROM exchange_rates WHERE rate_date=date('now') ORDER BY currency_code")->fetchAll();
$allRates = $pdo->query("SELECT * FROM exchange_rates ORDER BY rate_date DESC, currency_code LIMIT 50")->fetchAll();
$stats = [
  'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'pending_users' => count($pendingUsers),
  'campaigns' => $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn(),
  'pending_campaigns' => count($pendingCampaigns),
  'donations' => $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn(),
  'total_usd' => $pdo->query("SELECT SUM(raised_amount_usd) FROM campaigns")->fetchColumn() ?: 0
];
?>
<div class="max-w-7xl mx-auto">
  <?php if(isset($_GET['msg'])): ?><div class="bg-green-100 text-green-700 p-3 rounded-xl mb-6 text-center"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
  <h1 class="text-3xl font-bold mb-1">Admin Dashboard</h1>
  <p class="text-gray-500 text-sm mb-6">Verify accounts - Approve campaigns - Exchange rates - Full database access</p>

  <div class="grid md:grid-cols-5 gap-3 mb-6">
    <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-gray-400">Pending Users</div><div class="text-xl font-bold"><?= $stats['pending_users'] ?></div><div class="text-[10px] text-gray-400">Need activation</div></div>
    <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-gray-400">Total Users</div><div class="text-xl font-bold"><?= $stats['users'] ?></div></div>
    <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-gray-400">Pending Campaigns</div><div class="text-xl font-bold"><?= $stats['pending_campaigns'] ?></div></div>
    <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-gray-400">Donations</div><div class="text-xl font-bold"><?= $stats['donations'] ?></div></div>
    <div class="bg-black text-white border rounded-2xl p-4"><div class="text-xs text-gray-400">Total Raised</div><div class="text-xl font-bold">$<?= number_format($stats['total_usd'],2) ?></div></div>
  </div>

  <div id="exchange" class="bg-white border rounded-2xl p-6 mb-8">
    <h2 class="font-bold text-lg mb-4">Daily Exchange Rates</h2>
    <div class="grid md:grid-cols-4 gap-3 mb-6">
      <?php foreach($ratesToday as $r): ?>
        <div class="border rounded-xl p-3 text-center bg-gray-50">
          <div class="text-[10px] uppercase text-gray-400"><?= $r['currency_code'] ?></div>
          <div class="font-bold text-sm">1 USD = <?= number_format($r['rate_to_usd']) ?> <?= $r['currency_code'] ?></div>
          <div class="text-[10px] text-gray-400"><?= $r['rate_date'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <form method="POST" class="bg-gray-50 border rounded-xl p-4 flex flex-wrap gap-3 items-end">
      <div><label class="text-[10px] uppercase text-gray-400">Currency</label><select name="currency_code" class="border p-2 rounded-xl text-sm bg-white"><option value="RWF">RWF</option><option value="BIF">BIF</option><option value="TZS">TZS</option><option value="KES">KES</option><option value="UGX">UGX</option><option value="SSP">SSP</option><option value="CDF">CDF</option><option value="SOS">SOS</option></select></div>
      <div><label class="text-[10px] uppercase text-gray-400">Rate</label><input name="rate_to_usd" type="number" step="0.01" required placeholder="1300" class="border p-2 rounded-xl text-sm bg-white w-32"></div>
      <div><label class="text-[10px] uppercase text-gray-400">Date</label><input name="rate_date" type="date" value="<?= date('Y-m-d') ?>" class="border p-2 rounded-xl text-sm bg-white"></div>
      <button name="save_rate" class="bg-black text-white px-6 py-2 rounded-full text-sm font-bold">Save Rate</button>
    </form>
    <details class="mt-4"><summary class="text-xs underline cursor-pointer">Show rate history + edit</summary>
      <table class="w-full text-xs mt-2">
        <tr class="bg-black text-white"><th class="p-2 text-left">Date</th><th>Code</th><th>Rate</th><th>Action</th></tr>
        <?php foreach($allRates as $r): ?>
        <tr class="border-b">
          <td class="p-2"><form method="POST" class="flex gap-1"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input name="rate_date" value="<?= $r['rate_date'] ?>" class="border p-1 rounded w-24"></td>
          <td class="p-2"><input name="currency_code" value="<?= $r['currency_code'] ?>" class="border p-1 rounded w-16"></td>
          <td class="p-2"><input name="rate_to_usd" value="<?= $r['rate_to_usd'] ?>" class="border p-1 rounded w-20"></td>
          <td class="p-2 flex gap-1"><button name="update_rate" class="bg-black text-white px-2 py-1 rounded">Save</button></form><a href="admin.php?delete_rate=<?= $r['id'] ?>" onclick="return confirm('Delete rate?')" class="border px-2 py-1 rounded text-red-600">Del</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </details>
  </div>

  <div class="grid md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white border rounded-2xl p-6">
      <h2 class="font-bold text-lg mb-4">Pending ID Verifications - Must approve to create campaigns</h2>
      <?php if(empty($pendingUsers)): ?><p class="text-gray-400 text-sm">No pending verifications</p><?php endif; ?>
      <?php foreach($pendingUsers as $u): ?>
        <div class="border rounded-xl p-4 mb-3">
          <div class="flex justify-between"><div><b><?= htmlspecialchars($u['full_name']) ?></b><div class="text-xs text-gray-500"><?= $u['country'] ?> - <?= $u['id_type'] ?>: <?= htmlspecialchars($u['id_number']) ?> - <?= htmlspecialchars($u['phone']) ?> - <?= htmlspecialchars($u['email']) ?></div></div><span class="text-xs bg-yellow-100 px-2 py-1 rounded-full h-fit"><?= $u['kyc_status'] ?></span></div>
          <?php if($u['id_photo']): ?><div class="mt-2"><a href="<?= $u['id_photo'] ?>" target="_blank" class="text-xs underline">View ID Photo</a></div><?php endif; ?>
          <div class="flex gap-2 mt-3"><a href="admin.php?approve_user=<?= $u['id'] ?>" class="bg-black text-white px-4 py-1 rounded-full text-xs">Approve & Activate</a><a href="admin.php?reject_user=<?= $u['id'] ?>" class="border px-4 py-1 rounded-full text-xs text-red-600">Reject</a></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="bg-white border rounded-2xl p-6">
      <h2 class="font-bold text-lg mb-4">Pending Campaigns</h2>
      <?php if(empty($pendingCampaigns)): ?><p class="text-gray-400 text-sm">No pending campaigns</p><?php endif; ?>
      <?php foreach($pendingCampaigns as $c): ?>
        <div class="border rounded-xl p-4 mb-3">
          <b><?= htmlspecialchars($c['title']) ?></b><div class="text-xs text-gray-500">by <?= htmlspecialchars($c['full_name']) ?> - <?= $c['location'] ?> (<?= $c['currency']??'RWF' ?>) - <?= number_format($c['goal_amount']) ?></div>
          <p class="text-xs mt-2"><?= substr(htmlspecialchars($c['story']),0,120) ?>...</p>
          <div class="flex gap-2 mt-3"><a href="admin.php?approve_campaign=<?= $c['id'] ?>" class="bg-black text-white px-4 py-1 rounded-full text-xs">Approve</a><a href="admin.php?reject_campaign=<?= $c['id'] ?>" class="border px-4 py-1 rounded-full text-xs text-red-600">Reject</a><a href="campaign.php?slug=<?= $c['slug'] ?>" class="border px-3 py-1 rounded-full text-xs">View</a></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white border rounded-2xl p-6 mb-6">
    <h2 class="font-bold text-xl mb-2">Database Manager - Edit / Update / Delete All Data</h2>
    <p class="text-xs text-gray-500 mb-4">Admin can edit any field. Changes are immediate.</p>
    <div class="flex gap-2 text-xs mb-4 flex-wrap">
      <a href="#db_users" class="border px-3 py-1 rounded-full">Users</a>
      <a href="#db_campaigns" class="border px-3 py-1 rounded-full">Campaigns</a>
      <a href="#db_donations" class="border px-3 py-1 rounded-full">Donations</a>
      <a href="#exchange" class="border px-3 py-1 rounded-full">Rates</a>
    </div>

    <h3 id="db_users" class="font-bold mt-8 mb-2">Users Table (Edit / Delete)</h3>
    <div class="overflow-auto">
    <table class="w-full text-[11px] border">
      <tr class="bg-black text-white"><th class="p-2">ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Country</th><th>Verified</th><th>Admin</th><th>Approved</th><th>KYC</th><th>Action</th></tr>
      <?php foreach($allUsers as $u): ?>
      <tr class="border-b">
        <form method="POST">
          <td class="p-1"><?= $u['id'] ?><input type="hidden" name="id" value="<?= $u['id'] ?>"></td>
          <td class="p-1"><input name="full_name" value="<?= htmlspecialchars($u['full_name']) ?>" class="border p-1 rounded w-24"></td>
          <td class="p-1"><input name="email" value="<?= htmlspecialchars($u['email']) ?>" class="border p-1 rounded w-28"></td>
          <td class="p-1"><input name="phone" value="<?= htmlspecialchars($u['phone']) ?>" class="border p-1 rounded w-20"></td>
          <td class="p-1"><input name="country" value="<?= $u['country'] ?>" class="border p-1 rounded w-10"></td>
          <td class="p-1"><input name="is_verified" type="number" min="0" max="1" value="<?= $u['is_verified'] ?>" class="border p-1 rounded w-10"></td>
          <td class="p-1"><input name="is_admin" type="number" min="0" max="1" value="<?= $u['is_admin'] ?>" class="border p-1 rounded w-10"></td>
          <td class="p-1"><input name="admin_approved" type="number" min="0" max="1" value="<?= $u['admin_approved'] ?? 0 ?>" class="border p-1 rounded w-10"></td>
          <td class="p-1"><input name="kyc_status" value="<?= $u['kyc_status'] ?>" class="border p-1 rounded w-16"></td>
          <td class="p-1 flex gap-1"><button name="update_user" class="bg-black text-white px-2 py-1 rounded">Save</button><a href="admin.php?delete_user=<?= $u['id'] ?>" onclick="return confirm('Delete user?')" class="border px-2 py-1 rounded text-red-600">Del</a></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>

    <h3 id="db_campaigns" class="font-bold mt-8 mb-2">Campaigns Table (Edit / Delete)</h3>
    <div class="overflow-auto">
    <table class="w-full text-[11px] border">
      <tr class="bg-black text-white"><th class="p-2">ID</th><th>Title</th><th>Goal</th><th>Raised</th><th>USD</th><th>Location</th><th>Currency</th><th>Status</th><th>Withdrawn</th><th>Action</th></tr>
      <?php foreach($allCampaigns as $c): ?>
      <tr class="border-b">
        <form method="POST">
          <td class="p-1"><?= $c['id'] ?><input type="hidden" name="id" value="<?= $c['id'] ?>"></td>
          <td class="p-1"><input name="title" value="<?= htmlspecialchars($c['title']) ?>" class="border p-1 rounded w-24"><input type="hidden" name="category" value="<?= $c['category'] ?>"><textarea name="story" class="hidden"><?= htmlspecialchars($c['story']) ?></textarea><input type="hidden" name="beneficiary_name" value="<?= htmlspecialchars($c['beneficiary_name']) ?>"></td>
          <td class="p-1"><input name="goal_amount" value="<?= $c['goal_amount'] ?>" class="border p-1 rounded w-16"></td>
          <td class="p-1"><input name="raised_amount" value="<?= $c['raised_amount'] ?>" class="border p-1 rounded w-16"></td>
          <td class="p-1"><input name="raised_amount_usd" value="<?= $c['raised_amount_usd'] ?>" class="border p-1 rounded w-12"></td>
          <td class="p-1"><input name="location" value="<?= $c['location'] ?>" class="border p-1 rounded w-16"></td>
          <td class="p-1"><input name="currency" value="<?= $c['currency'] ?>" class="border p-1 rounded w-10"></td>
          <td class="p-1"><select name="status" class="border p-1 rounded"><option value="pending" <?= $c['status']=='pending'?'selected':'' ?>>pending</option><option value="active" <?= $c['status']=='active'?'selected':'' ?>>active</option><option value="completed" <?= $c['status']=='completed'?'selected':'' ?>>completed</option><option value="withdrawn" <?= $c['status']=='withdrawn'?'selected':'' ?>>withdrawn</option><option value="rejected" <?= $c['status']=='rejected'?'selected':'' ?>>rejected</option><option value="deleted" <?= $c['status']=='deleted'?'selected':'' ?>>deleted</option></select></td>
          <td class="p-1"><input name="withdrawn" type="number" min="0" max="1" value="<?= $c['withdrawn'] ?? 0 ?>" class="border p-1 rounded w-10"></td>
          <td class="p-1 flex gap-1"><button name="update_campaign" class="bg-black text-white px-2 py-1 rounded">Save</button><a href="admin.php?delete_campaign=<?= $c['id'] ?>" onclick="return confirm('Delete campaign?')" class="border px-2 py-1 rounded text-red-600">Del</a><a href="campaign.php?slug=<?= $c['slug'] ?>" class="border px-1 py-1 rounded">View</a></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>

    <h3 id="db_donations" class="font-bold mt-8 mb-2">Donations Table (Edit / Delete)</h3>
    <div class="overflow-auto">
    <table class="w-full text-[11px] border">
      <tr class="bg-black text-white"><th class="p-2">ID</th><th>Campaign</th><th>Donor</th><th>Phone</th><th>Amount</th><th>USD</th><th>Local Conv</th><th>Method</th><th>Country</th><th>Status</th><th>Action</th></tr>
      <?php foreach($allDonations as $d): ?>
      <tr class="border-b">
        <form method="POST">
          <td class="p-1"><?= $d['id'] ?><input type="hidden" name="id" value="<?= $d['id'] ?>"></td>
          <td class="p-1"><?= htmlspecialchars(substr($d['title'],0,15)) ?></td>
          <td class="p-1"><input name="donor_name" value="<?= htmlspecialchars($d['donor_name']) ?>" class="border p-1 rounded w-16"></td>
          <td class="p-1"><input name="donor_phone" value="<?= $d['donor_phone'] ?>" class="border p-1 rounded w-16"></td>
          <td class="p-1"><input name="amount" value="<?= $d['amount'] ?>" class="border p-1 rounded w-12"></td>
          <td class="p-1"><input name="amount_usd" value="<?= $d['amount_usd'] ?>" class="border p-1 rounded w-12"></td>
          <td class="p-1"><input name="amount_in_campaign_currency" value="<?= $d['amount_in_campaign_currency'] ?>" class="border p-1 rounded w-12"></td>
          <td class="p-1"><input name="payment_method" value="<?= $d['payment_method'] ?>" class="border p-1 rounded w-12"><input type="hidden" name="donor_country" value="<?= $d['donor_country'] ?>"><input type="hidden" name="donor_currency" value="<?= $d['donor_currency'] ?>"></td>
          <td class="p-1"><?= $d['donor_country'] ?></td>
          <td class="p-1"><select name="status" class="border p-1 rounded"><option value="success" <?= $d['status']=='success'?'selected':'' ?>>success</option><option value="pending" <?= $d['status']=='pending'?'selected':'' ?>>pending</option><option value="failed" <?= $d['status']=='failed'?'selected':'' ?>>failed</option></select></td>
          <td class="p-1 flex gap-1"><button name="update_donation" class="bg-black text-white px-2 py-1 rounded">Save</button><a href="admin.php?delete_donation=<?= $d['id'] ?>" onclick="return confirm('Delete donation?')" class="border px-2 py-1 rounded text-red-600">Del</a></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>

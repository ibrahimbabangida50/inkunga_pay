<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$countryFilter = $_GET['country'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE c.status = 'active'";
$params = [];
if($countryFilter){ $where .= " AND c.location = ?"; $params[] = $countryFilter; }
if($categoryFilter){ $where .= " AND c.category = ?"; $params[] = $categoryFilter; }
if($search){ $where .= " AND (c.title LIKE ? OR c.story LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("SELECT c.*, u.full_name as creator FROM campaigns c JOIN users u ON c.user_id = u.id $where ORDER BY c.created_at DESC");
$stmt->execute($params);
$campaigns = $stmt->fetchAll();
?>
<?php if(isset($_GET["logged_out"])): ?><div class="max-w-xl mx-auto bg-black text-white text-center p-3 rounded-full text-sm mb-4">You have been logged out. See you soon!</div><?php endif; ?>
<div class="text-center py-10 md:py-14">
  <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-4 tracking-tight"><?= t('tagline') ?></h1>
  <p class="text-gray-500 max-w-2xl mx-auto text-sm md:text-base"><?= t('hero_sub') ?></p>
  <p class="text-gray-400 max-w-2xl mx-auto mb-6 text-xs md:text-sm mt-2 font-light">Powered by Vusa Technology.</p>
  <div class="flex justify-center gap-3 mb-8">
    <?php if(isLoggedIn()): ?>
      <a href="create_campaign.php" class="border border-black px-8 py-3 rounded-full font-semibold hover:bg-black hover:text-white"><?= t('start_fundraiser') ?></a>
      <a href="dashboard.php" class="border border-black px-8 py-3 rounded-full font-semibold hover:bg-black hover:text-white"><?= t('dashboard') ?></a>
    <?php else: ?>
      <a href="login.php" class="bg-black text-white px-8 py-3 rounded-full font-semibold hover:bg-gray-800">Login to Start Fundraiser</a>
      <a href="register.php" class="bg-white-400 text-black px-8 py-3 rounded-full font-semibold hover:bg-gray-500">Create Verified Account</a>
    <?php endif; ?>
  </div>

  <form method="GET" class="max-w-3xl mx-auto bg-white border rounded-2xl p-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[180px]"><label class="text-[10px] uppercase text-gray-400">Search</label><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title or story..." class="w-full border p-2 rounded-xl text-sm"></div>
    <div><label class="text-[10px] uppercase text-gray-400">Country (EAC)</label><select name="country" class="border p-2 rounded-xl text-sm"><option value="">All EAC</option><option value="Tanzania" <?= $countryFilter=='Tanzania'?'selected':'' ?>>Tanzania</option><option value="Kenya" <?= $countryFilter=='Kenya'?'selected':'' ?>>Kenya</option><option value="Uganda" <?= $countryFilter=='Uganda'?'selected':'' ?>>Uganda</option><option value="Rwanda" <?= $countryFilter=='Rwanda'?'selected':'' ?>>Rwanda</option><option value="Burundi" <?= $countryFilter=='Burundi'?'selected':'' ?>>Burundi</option><option value="South Sudan" <?= $countryFilter=='South Sudan'?'selected':'' ?>>South Sudan</option><option value="DR Congo" <?= $countryFilter=='DR Congo'?'selected':'' ?>>DR Congo</option><option value="Somalia" <?= $countryFilter=='Somalia'?'selected':'' ?>>Somalia</option></select></div>
    <div><label class="text-[10px] uppercase text-gray-400">Category</label><select name="category" class="border p-2 rounded-xl text-sm"><option value="">All Categories</option><option value="education" <?= $categoryFilter=='education'?'selected':'' ?>>Education</option><option value="medical" <?= $categoryFilter=='medical'?'selected':'' ?>>Medical</option><option value="community" <?= $categoryFilter=='community'?'selected':'' ?>>Community</option><option value="business" <?= $categoryFilter=='business'?'selected':'' ?>>Business</option><option value="emergency" <?= $categoryFilter=='emergency'?'selected':'' ?>>Emergency</option></select></div>
    <button class="bg-black text-white px-6 py-2 rounded-full text-sm">Filter</button>
    <?php if($countryFilter || $categoryFilter || $search): ?><a href="index.php" class="text-xs underline text-gray-400">Clear</a><?php endif; ?>
  </form>
</div>

<div id="campaigns" class="grid md:grid-cols-3 gap-6 mt-2">
<?php foreach($campaigns as $camp): 
  $cur = $camp['currency'] ?? 'RWF';
  $goalUSD = $camp['usd_amount'] > 0 ? $camp['usd_amount'] : convertToUSD($camp['goal_amount'], $cur);
  $raisedUSD = $camp['raised_amount_usd'] > 0 ? $camp['raised_amount_usd'] : convertToUSD($camp['raised_amount'], $cur);
  $percent = $goalUSD > 0 ? min(100, ($raisedUSD / $goalUSD) * 100) : 0;
  $img = $camp['image'] ?? 'default.jpg';
?>
  <div class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition overflow-hidden border <?= $camp['status']=='pending' ? 'border-yellow-200' : '' ?>">
    <div class="h-48 overflow-hidden bg-gradient-to-br from-yellow-50 to-gray-100 flex items-center justify-center">
      <?php if(strpos($img,'uploads/')===0 && file_exists(__DIR__.'/'.$img)): ?>
        <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover">
      <?php else: ?>
        <div class="text-4xl">💛</div>
      <?php endif; ?>
    </div>
    <div class="p-5">
      <div class="flex justify-between items-center mb-2">
        <span class="text-[10px] bg-black text-white px-2 py-1 rounded-full uppercase tracking-wider"><?= htmlspecialchars($camp['category']) ?></span>
        <div class="flex gap-1 items-center">
          <span class="text-[10px] text-gray-400"><?= htmlspecialchars($camp['location']) ?> • <?= $cur ?></span>
          <?php if($camp['status']=='pending'): ?><span class="text-[10px] bg-yellow-400 text-black px-2 py-1 rounded-full uppercase font-bold">⏳ PENDING</span><?php endif; ?>
        </div>
      </div>
      <h3 class="font-bold text-lg mt-2 mb-1 leading-tight"><?= htmlspecialchars($camp['title']) ?></h3>
      <p class="text-gray-400 text-xs mb-3">by <?= htmlspecialchars($camp['creator']) ?> • <?= htmlspecialchars($camp['beneficiary_name']) ?></p>
      <p class="text-gray-600 text-sm line-clamp-2 mb-4"><?= substr(htmlspecialchars($camp['story']),0,100) ?>...</p>
      <div class="mb-2"><div class="w-full bg-gray-100 rounded-full h-2"><div class="bg-black h-2 rounded-full" style="width: <?= $percent ?>%"></div></div></div>
      <div class="flex justify-between text-xs mb-1"><span class="font-bold"><?= number_format($camp['raised_amount']) ?> <?= $cur ?></span><span class="text-gray-400">of <?= number_format($camp['goal_amount']) ?> <?= $cur ?> • <?= round($percent) ?>%</span></div>
      <div class="text-[10px] text-gray-500 mb-4">~$<?= number_format($raisedUSD,2) ?> of ~$<?= number_format($goalUSD,2) ?> USD</div>
      <div class="flex gap-2">
        <a href="campaign.php?slug=<?= $camp['slug'] ?>" class="flex-1 <?= $camp['status']=='pending' ? 'bg-gray-300 text-gray-600' : 'bg-black text-white hover:bg-gray-800' ?> text-center py-2.5 rounded-full text-sm font-semibold">View <?= $camp['status']=='pending' ? '(Pending)' : 'Campaign' ?></a>
        <button <?= $camp['status']=='pending' ? 'disabled' : '' ?> onclick="navigator.clipboard.writeText(window.location.origin+'/campaign.php?slug=<?= $camp['slug'] ?>'); alert('Link copied!')" class="border px-3 py-2 rounded-full text-sm <?= $camp['status']=='pending' ? 'opacity-30 cursor-not-allowed' : '' ?>">↗</button>
      </div>
      <?php if($camp['status']=='pending'): ?><p class="text-[10px] text-yellow-600 mt-2 text-center">Awaiting admin approval</p><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if(empty($campaigns)): ?>
<div class="text-center py-20 text-gray-400">No campaigns found for this filter. <a href="index.php" class="underline">Clear filters</a></div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

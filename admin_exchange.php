<?php
require_once 'config/database.php';
requireLogin();
require_once 'includes/header.php';
$user = currentUser();
if(!$user['is_admin']){ die("<div class='max-w-xl mx-auto bg-red-50 p-6 rounded-2xl mt-10'>Access denied. Admin only.</div>"); }

if($_SERVER['REQUEST_METHOD']=='POST'){
    $code = $_POST['currency_code'];
    $rate = floatval($_POST['rate_to_usd']);
    $date = $_POST['rate_date'] ?: date('Y-m-d');
    try{
        $pdo->prepare("INSERT INTO exchange_rates (currency_code, rate_to_usd, rate_date) VALUES (?,?,?) ON CONFLICT(currency_code, rate_date) DO UPDATE SET rate_to_usd=excluded.rate_to_usd")->execute([$code, $rate, $date]);
        $msg = "Rate updated: 1 USD = $rate $code on $date";
    } catch(Exception $e){
        $msg = "Error: ".$e->getMessage();
    }
}

$ratesToday = $pdo->query("SELECT * FROM exchange_rates WHERE rate_date=date('now') ORDER BY currency_code")->fetchAll();
$allRates = $pdo->query("SELECT * FROM exchange_rates ORDER BY rate_date DESC, currency_code LIMIT 50")->fetchAll();
$currencies = $pdo->query("SELECT * FROM currencies WHERE code!='USD' ORDER BY code")->fetchAll();
?>
<div class="max-w-5xl mx-auto">
  <?php if(isset($msg)): ?><div class="bg-green-50 border p-3 rounded-xl mb-4 text-center text-sm"><?= $msg ?></div><?php endif; ?>
  <h1 class="text-3xl font-bold mb-2">💱 Daily Exchange Rates - EAC 8 Countries</h1>
  <p class="text-gray-500 text-sm mb-6">Fill daily Dallas exchange rate for each country currency. This is used to show USD small text under campaign amounts. Rate = How much 1 USD = in local currency.</p>

  <div class="bg-white border rounded-2xl p-6 mb-8">
    <h2 class="font-bold mb-4">Add / Update Today's Rate</h2>
    <form method="POST" class="grid md:grid-cols-4 gap-3 items-end">
      <div>
        <label class="text-[10px] uppercase text-gray-400">Currency</label>
        <select name="currency_code" class="border p-3 rounded-xl w-full text-sm">
          <?php foreach($currencies as $c): ?>
            <option value="<?= $c['code'] ?>"><?= $c['code'] ?> - <?= $c['country'] ?> (<?= $c['symbol'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-[10px] uppercase text-gray-400">Rate: 1 USD = ?</label>
        <input name="rate_to_usd" type="number" step="0.01" required placeholder="e.g. 1300 for RWF" class="border p-3 rounded-xl w-full text-sm">
      </div>
      <div>
        <label class="text-[10px] uppercase text-gray-400">Date</label>
        <input name="rate_date" type="date" value="<?= date('Y-m-d') ?>" class="border p-3 rounded-xl w-full text-sm">
      </div>
      <button class="bg-black text-white px-6 py-3 rounded-full text-sm font-bold">Save Rate</button>
    </form>
    <p class="text-[11px] text-gray-400 mt-3">Example: RWF 1300, TZS 2580, KES 129, UGX 3720, BIF 2850, SSP 950, CDF 2750, SOS 570</p>
  </div>

  <div class="bg-white border rounded-2xl p-6 mb-8">
    <h2 class="font-bold mb-4">Today's Rates (<?= date('Y-m-d') ?>) - 8 EAC Countries</h2>
    <div class="grid md:grid-cols-4 gap-3">
      <?php foreach($ratesToday as $r): ?>
        <div class="border rounded-xl p-3 text-center">
          <div class="text-[10px] uppercase text-gray-400"><?= $r['currency_code'] ?></div>
          <div class="font-bold">1 USD = <?= number_format($r['rate_to_usd']) ?> <?= $r['currency_code'] ?></div>
          <div class="text-[10px] text-gray-400"><?= $r['rate_date'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if(empty($ratesToday)): ?><p class="text-gray-400 text-sm">No rates for today yet. Add above.</p><?php endif; ?>
  </div>

  <div class="bg-white border rounded-2xl p-6">
    <h2 class="font-bold mb-4">Recent Rates History (Last 50)</h2>
    <table class="w-full text-xs">
      <tr class="bg-black text-white"><th class="p-2 text-left">Date</th><th class="p-2">Currency</th><th class="p-2">Rate (1 USD =)</th></tr>
      <?php foreach($allRates as $r): ?>
        <tr class="border-b"><td class="p-2"><?= $r['rate_date'] ?></td><td class="p-2 font-bold"><?= $r['currency_code'] ?></td><td class="p-2"><?= number_format($r['rate_to_usd'],2) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="mt-6 text-center">
    <a href="admin.php" class="underline text-sm">← Back to Admin Dashboard</a>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>

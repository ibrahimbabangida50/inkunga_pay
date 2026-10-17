<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT c.*, u.full_name as creator FROM campaigns c JOIN users u ON c.user_id=u.id WHERE c.slug=?");
$stmt->execute([$slug]);
$camp = $stmt->fetch();

if(!$camp){ echo "<div class='text-center py-20'>Campaign not found</div>"; require_once 'includes/footer.php'; exit; }

$isOwner = isLoggedIn() && $_SESSION['user_id'] == $camp['user_id'];
$isAdmin = isLoggedIn() && currentUser() && currentUser()['is_admin'];

// Hidden statuses for public
if(in_array($camp['status'], ['withdrawn','deleted']) && !$isOwner && !$isAdmin){
    echo "<div class='text-center py-20'><h2 class='text-xl font-bold'>Campaign completed</h2><p class='text-gray-500 text-sm mt-2'>This campaign has been completed and withdrawn. It is no longer available for public view.<br>It remains in owner dashboard and admin records.</p><a href='index.php' class='underline text-sm'>Back to home</a></div>";
    require_once 'includes/footer.php'; exit;
}
if($camp['status']=='deleted' && !$isAdmin){
    echo "<div class='text-center py-20'><h2 class='text-xl font-bold'>Campaign deleted</h2><p class='text-gray-500 text-sm mt-2'>Kept for admin history only.</p><a href='index.php' class='underline'>Home</a></div>";
    require_once 'includes/footer.php'; exit;
}

$cur = $camp['currency'] ?? 'RWF';
$eac = include 'config/eac_payments.php';

$goalUSD = $camp['usd_amount'] > 0 ? $camp['usd_amount'] : convertToUSD($camp['goal_amount'], $cur);
$raisedUSD = $camp['raised_amount_usd'] > 0 ? $camp['raised_amount_usd'] : convertToUSD($camp['raised_amount'], $cur);
$percent = $goalUSD > 0 ? min(100, ($raisedUSD / $goalUSD) * 100) : 0;
$isComplete = $percent >= 100 || in_array($camp['status'], ['completed','withdrawn']);
$isPending = $camp['status']=='pending';
$isCompletedStatus = $camp['status']=='completed';
$isWithdrawnStatus = $camp['status']=='withdrawn';

// Auto-mark as completed when 100% reached (if still active)
if($isComplete && $camp['status']=='active'){
    $pdo->prepare("UPDATE campaigns SET status='completed' WHERE id=?")->execute([$camp['id']]);
    $camp['status'] = 'completed';
    $isCompletedStatus = true;
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['delete_campaign']) && $isOwner){
        $pdo->prepare("UPDATE campaigns SET status='deleted' WHERE id=?")->execute([$camp['id']]);
        header("Location: dashboard.php?msg=Deleted - kept for admin"); exit;
    }
    if(isset($_POST['withdraw']) && $isOwner){
        if(($isComplete || $isCompletedStatus) && !$camp['withdrawn'] && !$isWithdrawnStatus){
            $pdo->prepare("UPDATE campaigns SET withdrawn=1, status='withdrawn' WHERE id=?")->execute([$camp['id']]);
            $msg = "✅ Withdrawal successful!.";
            $camp['withdrawn'] = 1;
            $camp['status'] = 'withdrawn';
            $isWithdrawnStatus = true;
        } else $msg = "Withdraw only when completed and not yet withdrawn";
    }
    if(isset($_POST['donate'])){
        if($isPending){
            $msg = "Donate disabled - pending approval";
        } elseif($isComplete || $isCompletedStatus || $isWithdrawnStatus){
            $msg = "Fundraising finished - this campaign is completed and locked, waiting for withdraw. No more donations accepted.";
        } else {
            $amount = (float)$_POST['amount'];
            $name = trim($_POST['donor_name']);
            $phone = trim($_POST['donor_phone']);
            $payment_category = $_POST['payment_category'] ?? 'mobile_money';
            $donor_country = $_POST['donor_country'] ?? $camp['location'];
            $donor_currency = $eac[$donor_country]['currency'] ?? $cur;
            if($payment_category == 'mobile_money'){
                $payment_method = $_POST['payment_method_mobile'] ?? 'mtn_momo';
            } elseif($payment_category == 'card'){
                $payment_method = 'visa_card';
            } else {
                $payment_method = 'bank_transfer';
            }
            $amount_usd = convertToUSD($amount, $donor_currency);
            $amount_in_campaign_currency = convertCurrency($amount, $donor_currency, $cur);

            $stmt = $pdo->prepare("INSERT INTO donations (campaign_id, donor_name, donor_phone, amount, amount_in_campaign_currency, amount_usd, payment_method, payment_category, donor_country, donor_currency, status) VALUES (?,?,?,?,?,?,?,?,?,?, 'success')");
            $stmt->execute([$camp['id'], $name, $phone, $amount, $amount_in_campaign_currency, $amount_usd, $payment_method, $payment_category, $donor_country, $donor_currency]);
            
            $pdo->prepare("UPDATE campaigns SET raised_amount = raised_amount + ?, raised_amount_usd = raised_amount_usd + ? WHERE id=?")->execute([$amount_in_campaign_currency, $amount_usd, $camp['id']]);
            
            $msg = "Thank you $name! ".number_format($amount)." $donor_currency received.";
            $camp['raised_amount'] += $amount_in_campaign_currency;
            $camp['raised_amount_usd'] = ($camp['raised_amount_usd'] ?? 0) + $amount_usd;
            $raisedUSD += $amount_usd;
            $percent = $goalUSD > 0 ? min(100, ($raisedUSD / $goalUSD) * 100) : 0;
            $isComplete = $percent >= 100;
            // Auto complete if reached 100% now
            if($isComplete){
                $pdo->prepare("UPDATE campaigns SET status='completed' WHERE id=?")->execute([$camp['id']]);
                $camp['status'] = 'completed';
                $isCompletedStatus = true;
                $msg .= " 🎉 Goal reached! Campaign is now marked as completed, waiting for withdraw and locked for donations.";
            }
        }
    }
}
?>
<?php if($msg): ?><div class="max-w-5xl mx-auto bg-green-50 border border-green-200 text-green-700 p-3 rounded-xl mb-4 text-center text-sm"><?= $msg ?></div><?php endif; ?>
<?php if($isPending): ?><div class="max-w-5xl mx-auto bg-yellow-50 border border-yellow-200 text-yellow-700 p-3 rounded-xl mb-4 text-center text-sm">⏳ PENDING - Donate & Share disabled until admin activates</div><?php endif; ?>
<?php if($isCompletedStatus): ?><div class="max-w-5xl mx-auto bg-black text-white p-3 rounded-xl mb-4 text-center text-sm">✅ Fundraising finished - Completed! Waiting for withdraw.</div><?php endif; ?>
<?php if($isWithdrawnStatus): ?><div class="max-w-5xl mx-auto bg-gray-800 text-white p-3 rounded-xl mb-4 text-center text-sm">✅ Campaign withdrawn and hidden from public.</div><?php endif; ?>

<div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-8">
  <div class="md:col-span-2">
    <?php $img=$camp['image']??'default.jpg'; if(strpos($img,'uploads/')===0 && file_exists(__DIR__.'/'.$img)): ?>
      <img src="<?= htmlspecialchars($img) ?>" class="w-full h-80 object-cover rounded-2xl mb-6 border">
    <?php else: ?><div class="w-full h-80 bg-gradient-to-br from-yellow-50 to-gray-100 rounded-2xl mb-6 flex items-center justify-center border"><div class="text-center"><div class="text-6xl mb-2">💛</div><p class="text-gray-400 text-sm">No photo</p></div></div><?php endif; ?>

    <div class="flex gap-2 mb-3 flex-wrap">
      <span class="text-[10px] bg-black text-white px-2 py-1 rounded-full uppercase"><?= htmlspecialchars($camp['category']) ?></span>
      <span class="text-[10px] bg-gray-100 px-2 py-1 rounded-full"><?= htmlspecialchars($camp['location']) ?> • <?= $cur ?></span>
      <span class="text-[10px] bg-<?= $camp['status']=='active'?'green':($camp['status']=='pending'?'yellow':($camp['status']=='completed'?'blue':($camp['status']=='withdrawn'?'gray':'red'))) ?>-100 px-2 py-1 rounded-full uppercase font-bold"><?= $camp['status'] ?></span>
    </div>

    <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($camp['title']) ?></h1>
    <p class="text-gray-500 text-sm mb-2">Beneficiary: <b><?= htmlspecialchars($camp['beneficiary_name']) ?></b> • <?= htmlspecialchars($camp['creator']) ?></p>
    <p class="text-[11px] text-gray-500 mb-6"><?= number_format($camp['goal_amount']) ?> <?= $cur ?> • ~$<?= number_format($goalUSD,2) ?> USD</p>

    <?php if($isOwner): ?>
    <div class="bg-black text-white rounded-2xl p-6 mb-6">
      <h3 class="font-bold mb-3">Owner Controls</h3>
      <div class="grid grid-cols-3 gap-4 text-center mb-4">
        <div><div class="text-2xl font-bold"><?= round($percent) ?>%</div><div class="text-[10px] text-gray-400 uppercase">Complete</div></div>
        <div><div class="text-2xl font-bold"><?= number_format($camp['raised_amount']) ?></div><div class="text-[10px] text-gray-400 uppercase">Raised <?= $cur ?></div></div>
        <div><div class="text-2xl font-bold"><?= number_format(max(0, $camp['goal_amount'] - $camp['raised_amount'])) ?></div><div class="text-[10px] text-gray-400 uppercase">Remaining</div></div>
      </div>
      <p class="text-xs text-gray-300 mb-4">~$<?= number_format($raisedUSD,2) ?> raised of ~$<?= number_format($goalUSD,2) ?> USD</p>
      <div class="flex gap-2">
        <?php if($isWithdrawnStatus): ?>
          <div class="w-full bg-gray-700 text-gray-300 py-2 rounded-full text-sm font-bold text-center">Withdrawn - Hidden from public</div>
        <?php elseif($isCompletedStatus || $isComplete): ?>
          <form method="POST" class="flex-1"><button name="withdraw" class="w-full bg-yellow-400 text-black py-2 rounded-full text-sm font-bold">Withdraw Funds</button></form>
        <?php else: ?>
          <div class="w-full bg-gray-700 text-gray-400 py-2 rounded-full text-sm font-bold text-center">Withdraw at 100%</div>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Delete? Kept for admin history')"><button name="delete_campaign" class="bg-red-600 text-white px-4 py-2 rounded-full text-sm">Delete</button></form>
      </div>
      <?php if($isCompletedStatus): ?><p class="text-[10px] text-yellow-300 mt-3 text-center">Campaign locked - no more donations. Please withdraw.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-2xl border"><h3 class="font-bold mb-3">Story</h3><p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($camp['story'])) ?></p></div>
    
    <div class="mt-6 bg-white p-6 rounded-2xl border">
      <h3 class="font-bold mb-3">Donation History</h3>
      <?php
        $d = $pdo->prepare("SELECT * FROM donations WHERE campaign_id=? AND status='success' ORDER BY created_at DESC");
        $d->execute([$camp['id']]);
        $dons = $d->fetchAll();
        if(empty($dons)) echo "<p class='text-sm text-gray-400'>No donations yet. Be the first!</p>";
        foreach($dons as $don):
      ?>
        <div class="flex justify-between py-2 border-b text-sm">
          <span><?= htmlspecialchars($don['donor_name']) ?> • <?= $don['payment_category'] ?> • <?= date('M d', strtotime($don['created_at'])) ?></span>
          <span class="font-bold"><?= number_format($don['amount']) ?> <?= $don['donor_currency'] ?> → <?= number_format($don['amount_in_campaign_currency'] ?? $don['amount']) ?> <?= $cur ?> ($<?= $don['amount_usd'] ?? 0 ?>)</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <div>
    <div class="bg-white p-6 rounded-2xl border shadow-sm sticky top-24">
      <div class="mb-4">
        <div class="w-full bg-gray-100 rounded-full h-3">
          <div class="bg-black h-3 rounded-full" style="width: <?= $percent ?>%"></div>
        </div>
        <div class="flex justify-between text-xs mt-2"><span><?= round($percent) ?>% funded</span><span class="text-gray-500"><?= $isWithdrawnStatus ? 'Withdrawn' : ($percent>=100 ? 'Completed - Locked' : number_format(max(0, $goalUSD-$raisedUSD),2).'$ to go') ?></span></div>
      </div>
      <p class="font-bold text-2xl"><?= number_format($camp['raised_amount']) ?> <?= $cur ?></p>
      <p class="text-[11px] text-gray-500 mb-1">~$<?= number_format($raisedUSD,2) ?> USD raised</p>
      <p class="text-gray-500 text-sm mb-4">of <?= number_format($camp['goal_amount']) ?> <?= $cur ?> ( ~$<?= number_format($goalUSD,2) ?> USD ) goal</p>
      
      <?php if($isPending || $isCompletedStatus || $isWithdrawnStatus): ?>
        <div class="<?= $isWithdrawnStatus ? 'bg-gray-100' : ($isCompletedStatus ? 'bg-black text-white' : 'bg-yellow-50') ?> border p-4 rounded-xl text-center mb-4">
          <p class="text-sm font-bold"><?= $isPending ? 'Pending Approval' : ($isWithdrawnStatus ? 'Withdrawn & Hidden' : 'Completed - Locked') ?></p>
          <p class="text-xs <?= $isCompletedStatus ? 'text-gray-300' : 'text-gray-600' ?> mt-1"><?= $isPending ? 'Donate disabled until admin activates' : ($isWithdrawnStatus ? 'Hidden from public, visible in dashboard' : 'Fundraising finished, waiting for withdraw. No more donations.') ?></p>
        </div>
        <div class="opacity-50 pointer-events-none">
      <?php endif; ?>

      <form method="POST" class="space-y-3" id="donateForm">
        <div>
          <label class="text-[10px] uppercase text-gray-400">Country</label>
          <select name="donor_country" id="donorCountry" class="w-full border p-3 rounded-xl text-sm bg-white">
            <?php foreach($eac as $country=>$data): ?>
              <option value="<?= $country ?>" <?= $country==$camp['location']?'selected':'' ?>><?= $data['flag'] ?> <?= $country ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-[10px] uppercase text-gray-400">Payment Method</label>
          <select name="payment_category" id="paymentCategory" class="w-full border p-3 rounded-xl text-sm bg-white">
            <option value="mobile_money" selected>Mobile Money</option>
            <option value="card">Visa / Card</option>
            <option value="bank">Bank Transfer</option>
          </select>
        </div>
        <div id="mobileMoneySection">
          <label class="text-[10px] uppercase text-gray-400">Provider</label>
          <select name="payment_method_mobile" id="paymentMethodMobile" class="w-full border p-3 rounded-xl text-sm bg-white"></select>
        </div>
        <div id="cardSection" style="display:none" class="space-y-2 border rounded-xl p-3 bg-gray-50">
          <input name="card_number" placeholder="Card Number" class="w-full border p-3 rounded-xl text-sm bg-white">
          <div class="grid grid-cols-2 gap-2">
            <input name="card_expiry" placeholder="MM/YY" class="border p-3 rounded-xl text-sm bg-white">
            <input name="card_cvv" placeholder="CVV" class="border p-3 rounded-xl text-sm bg-white">
          </div>
        </div>
        <div id="bankSection" style="display:none" class="border rounded-xl p-3 bg-gray-50">
          <p class="text-xs"><b>Bank Transfer:</b><br>Bank: I&M Bank / KCB / Equity<br>Account: 400100012345678<br>Swift: IMRWRWRW<br>Ref: <?= $camp['slug'] ?></p>
        </div>
        <input name="donor_name" required placeholder="Your Name" class="w-full border p-3 rounded-xl text-sm bg-white">
        <input name="donor_phone" required placeholder="Phone Number" class="w-full border p-3 rounded-xl text-sm bg-white">
        <div>
          <label class="text-[10px] uppercase text-gray-400">Amount</label>
          <input name="amount" id="donateAmount" type="number" required placeholder="Amount" min="100" class="w-full border p-3 rounded-xl text-sm bg-white">
          <p class="text-[11px] text-gray-400 mt-1"><b id="payCurrency"><?= $cur ?></b> • <span id="usdConvert">~$0 USD</span></p>
        </div>
        <button name="donate" class="w-full bg-black text-white font-bold py-3 rounded-full text-sm">Donate Now</button>
      </form>

      <?php if($isPending || $isCompletedStatus || $isWithdrawnStatus): ?></div><?php endif; ?>
      
      <div class="mt-4 flex gap-2">
        <button <?= ($isPending || $isCompletedStatus || $isWithdrawnStatus) ? 'disabled' : '' ?> onclick="navigator.clipboard.writeText(window.location.href); alert('Link copied!')" class="flex-1 border py-2 rounded-full text-xs <?= ($isPending || $isCompletedStatus || $isWithdrawnStatus) ? 'opacity-30' : '' ?>"><?= ($isPending || $isCompletedStatus || $isWithdrawnStatus) ? 'Share Disabled' : 'Share Link' ?></button>
        <button class="flex-1 border py-2 rounded-full text-xs">Report</button>
      </div>
      <p class="text-[10px] text-gray-400 mt-4 text-center">Secured by Vusa Technology • <?= $camp['location'] ?> • <?= $cur ?></p>
    </div>
  </div>
</div>

<script>
const eacData = <?= json_encode($eac) ?>;
const donorCountryEl = document.getElementById('donorCountry');
const paymentCategoryEl = document.getElementById('paymentCategory');
const paymentMethodMobileEl = document.getElementById('paymentMethodMobile');
const mobileSection = document.getElementById('mobileMoneySection');
const cardSection = document.getElementById('cardSection');
const bankSection = document.getElementById('bankSection');
const donateAmountEl = document.getElementById('donateAmount');
const payCurrencyEl = document.getElementById('payCurrency');
const usdConvertEl = document.getElementById('usdConvert');

function updatePaymentMethods(){
  const country = donorCountryEl.value;
  const category = paymentCategoryEl.value;
  mobileSection.style.display = category=='mobile_money' ? 'block' : 'none';
  cardSection.style.display = category=='card' ? 'block' : 'none';
  bankSection.style.display = category=='bank' ? 'block' : 'none';
  if(category=='mobile_money'){
    const providers = eacData[country]?.mobile_money || {};
    paymentMethodMobileEl.innerHTML = '';
    for(const [key,label] of Object.entries(providers)){
      const opt = document.createElement('option');
      opt.value = key; opt.textContent = label;
      paymentMethodMobileEl.appendChild(opt);
    }
  }
  const cur = eacData[country]?.currency || 'RWF';
  payCurrencyEl.innerText = cur;
  updateUSD();
}
function updateUSD(){
  const amt = donateAmountEl.value || 0;
  const country = donorCountryEl.value;
  const cur = eacData[country]?.currency || 'RWF';
  if(amt>0){
    fetch(`api/exchange.php?convert=${amt}&from=${cur}&to=USD`).then(r=>r.json()).then(d=>{
      usdConvertEl.innerText = `~$${d.usd} USD`;
    });
  }
}
donorCountryEl.addEventListener('change', updatePaymentMethods);
paymentCategoryEl.addEventListener('change', updatePaymentMethods);
donateAmountEl.addEventListener('input', updateUSD);
updatePaymentMethods();
fetch('https://ipapi.co/json/').then(r=>r.json()).then(data=>{
  const countryMap = {TZ:'Tanzania', KE:'Kenya', UG:'Uganda', RW:'Rwanda', BI:'Burundi', SS:'South Sudan', CD:'DR Congo', SO:'Somalia'};
  if(countryMap[data.country_code]){
    if(donorCountryEl.value == '<?= $camp['location'] ?>'){
      donorCountryEl.value = countryMap[data.country_code];
      updatePaymentMethods();
    }
  }
}).catch(()=>{});
</script>

<?php require_once 'includes/footer.php'; ?>

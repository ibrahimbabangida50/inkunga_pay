<?php
require_once 'config/database.php';
requireLogin();
require_once 'includes/header.php';
$user = currentUser();
if(!$user['is_verified']){ echo "<div class='max-w-xl mx-auto bg-red-50 p-6 rounded-2xl mt-10'>Please verify your account first. <a href='verify_otp.php' class='underline'>Verify OTP</a></div>"; require_once 'includes/footer.php'; exit; }
if(empty($user['admin_approved'])){ echo "<div class='max-w-xl mx-auto bg-yellow-50 border border-yellow-200 p-6 rounded-2xl mt-10 text-center'><h2 class='font-bold'>⏳ Account Pending Approval</h2><p class='text-sm text-gray-600 mt-2'>Your account is created and verified, but not yet activated by admin.<br>You can view your account, but you cannot create campaigns before activation.<br>Admin will review your ID photo.</p><a href='dashboard.php' class='inline-block mt-4 border px-4 py-2 rounded-full text-sm'>Go to Dashboard</a></div>"; require_once 'includes/footer.php'; exit; }

$eac = include 'config/eac_payments.php';
$error='';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $story = trim($_POST['story']);
    $goal = (float)$_POST['goal_amount'];
    $beneficiary = trim($_POST['beneficiary_name']);
    $location = $_POST['location'];
    $currency = $_POST['currency'] ?? 'RWF';
    $slug = strtolower(preg_replace('/[^a-z0-9]+/','-', $title)).'-'.rand(100,999);
    
    $photoPath = 'default.jpg';
    if(isset($_FILES['campaign_photo']) && $_FILES['campaign_photo']['error']==0){
        $ext = strtolower(pathinfo($_FILES['campaign_photo']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['jpg','jpeg','png','webp'])){
            if(!is_dir(__DIR__.'/uploads/campaigns')) mkdir(__DIR__.'/uploads/campaigns', 0777, true);
            $fname = 'camp_'.time().'_'.rand(1000,9999).'.'.$ext;
            $dest = __DIR__.'/uploads/campaigns/'.$fname;
            move_uploaded_file($_FILES['campaign_photo']['tmp_name'], $dest);
            $photoPath = 'uploads/campaigns/'.$fname;
        } else $error = "Campaign photo must be JPG/PNG/WEBP";
    }

    if(!$error){
        $usd = convertToUSD($goal, $currency);
        $stmt = $pdo->prepare("INSERT INTO campaigns (user_id,title,slug,category,story,goal_amount,beneficiary_name,location,image,status,currency,usd_amount) VALUES (?,?,?,?,?,?,?,?,?, 'pending', ?, ?)");
        $stmt->execute([$_SESSION['user_id'],$title,$slug,$category,$story,$goal,$beneficiary,$location,$photoPath,$currency,$usd]);
        header("Location: campaign.php?slug=$slug&created=1");
        exit;
    }
}
?>
<div class="max-w-2xl mx-auto bg-white border rounded-2xl p-8 mt-6">
  <h1 class="text-2xl font-bold mb-1">Create a fundraiser - EAC</h1>
  <p class="text-sm text-gray-500 mb-6">Verified as <?= htmlspecialchars($user['full_name']) ?> • <?= $user['country'] ?> • Currency auto-detected</p>
  <?php if($error): ?><div class="bg-red-50 text-red-600 p-3 rounded-xl text-sm mb-4"><?= $error ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="space-y-4">
    <input name="title" required placeholder="Campaign Title e.g. Help Build School" class="w-full border p-3 rounded-xl">
    
    <div class="border-2 border-dashed rounded-xl p-4 bg-yellow-50/50">
      <label class="text-sm font-semibold">📷 Campaign Photo</label>
      <input type="file" name="campaign_photo" accept=".jpg,.jpeg,.png,.webp" class="w-full mt-2 text-sm">
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <select name="category" class="border p-3 rounded-xl">
        <option value="education">Education</option>
        <option value="medical">Medical</option>
        <option value="community">Community</option>
        <option value="business">Business</option>
        <option value="emergency">Emergency</option>
      </select>
      <select name="location" id="locationSelect" class="border p-3 rounded-xl">
        <option value="Tanzania">Tanzania</option>
        <option value="Kenya">Kenya</option>
        <option value="Uganda">Uganda</option>
        <option value="Rwanda" selected>Rwanda</option>
        <option value="Burundi">Burundi</option>
        <option value="South Sudan">South Sudan</option>
        <option value="DR Congo">DR Congo</option>
        <option value="Somalia">Somalia</option>
      </select>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="text-[10px] uppercase text-gray-400">Goal Amount (Local Currency)</label>
        <input name="goal_amount" id="goalAmount" type="number" required placeholder="e.g. 5000000" min="1000" class="w-full border p-3 rounded-xl">
        <input type="hidden" name="currency" id="currencyField" value="RWF">
        <p class="text-[11px] text-gray-500 mt-1"><b id="currencyDisplay">RWF</b> • <span id="usdDisplay">~$0 USD</span></p>
      </div>
      <div>
        <label class="text-[10px] uppercase text-gray-400">Beneficiary</label>
        <input name="beneficiary_name" required placeholder="Who receives funds" class="w-full border p-3 rounded-xl">
      </div>
    </div>

    <textarea name="story" required rows="5" placeholder="Tell your story... why should EAC support you?" class="w-full border p-3 rounded-xl"></textarea>
    <button class="w-full bg-black text-white py-3 rounded-full font-semibold">Launch Campaign (Pending Admin)</button>
  </form>
</div>

<script>
const currencyMap = {Tanzania:'TZS', Kenya:'KES', Uganda:'UGX', Rwanda:'RWF', Burundi:'BIF', 'South Sudan':'SSP', 'DR Congo':'CDF', Somalia:'SOS'};
const locSel = document.getElementById('locationSelect');
const curField = document.getElementById('currencyField');
const curDisp = document.getElementById('currencyDisplay');
const usdDisp = document.getElementById('usdDisplay');
const goalInp = document.getElementById('goalAmount');

function updateCurrency(){
  const cur = currencyMap[locSel.value] || 'RWF';
  curField.value = cur;
  curDisp.innerText = cur;
  updateUSD();
}
function updateUSD(){
  const amt = goalInp.value || 0;
  const cur = curField.value;
  if(amt>0){
    fetch(`api/exchange.php?convert=${amt}&from=${cur}&to=USD`).then(r=>r.json()).then(d=>{
      usdDisp.innerText = `~$${d.usd} USD`;
    });
  }
}
locSel.addEventListener('change', updateCurrency);
goalInp.addEventListener('input', updateUSD);
updateCurrency();
</script>

<?php require_once 'includes/footer.php'; ?>

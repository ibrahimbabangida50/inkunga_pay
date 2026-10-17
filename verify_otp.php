<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$uid = $_SESSION['pending_user_id'] ?? null;
if(!$uid) header("Location: register.php");
$error=''; $success='';

if($_SERVER['REQUEST_METHOD']=='POST'){
    $otp = trim($_POST['otp']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if($user && $user['otp_code']==$otp){
        $pdo->prepare("UPDATE users SET is_verified=1, kyc_status='verified', otp_code=NULL WHERE id=?")->execute([$uid]);
        $_SESSION['user_id']=$uid;
        unset($_SESSION['pending_user_id']);
        header("Location: dashboard.php?verified=1");
        exit;
    } else $error="Invalid or expired OTP. Try again.";
}
?>
<div class="max-w-md mx-auto bg-white rounded-2xl shadow-sm border p-8 mt-10 text-center">
  <h1 class="text-xl font-bold mb-2">Verify your phone</h1>
  <p class="text-gray-500 text-sm mb-2">We sent OTP to your phone. For demo, your OTP is:</p>
  <?php if(isset($_SESSION['last_otp'])): ?><div class="bg-yellow-100 border border-yellow-300 text-2xl font-mono tracking-widest p-3 rounded-xl mb-4"><?= $_SESSION['last_otp'] ?></div><p class="text-xs text-gray-400 mb-4">In production, this will be sent via MTN MoMo / Airtel SMS API</p><?php endif; ?>
  <?php if($error): ?><div class="bg-red-50 text-red-600 p-3 rounded-xl text-sm mb-4"><?= $error ?></div><?php endif; ?>
  <form method="POST" class="space-y-4">
    <input name="otp" required placeholder="Enter 6-digit OTP" maxlength="6" class="w-full border p-4 rounded-xl text-center text-2xl tracking-widest">
    <button class="w-full bg-black text-white py-3 rounded-full">Verify Account</button>
  </form>
</div>
<?php require_once 'includes/footer.php'; ?>

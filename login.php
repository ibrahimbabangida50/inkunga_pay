<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if(isLoggedIn()){
    $u = currentUser();
    if($u && $u['is_admin']) header("Location: admin.php");
    else header("Location: dashboard.php");
    exit;
}
$error='';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? OR phone=?");
    $stmt->execute([$email,$email]);
    $user = $stmt->fetch();
    if($user){
        if(password_verify($pass, $user['password_hash'])){
            if(!$user['is_verified']){
                $_SESSION['pending_user_id']=$user['id'];
                $_SESSION['last_otp']=$user['otp_code'];
                header("Location: verify_otp.php"); exit;
            }
            $_SESSION['user_id']=$user['id'];
            if($user['is_admin']){
                header("Location: admin.php"); exit;
            }
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header("Location: $redirect"); exit;
        } else $error="Wrong password. Try admin123 for admin.";
    } else $error="No account found with that email/phone";
}
?>
<div class="max-w-md mx-auto bg-white rounded-2xl shadow-sm border p-8 mt-10">
  <h1 class="text-2xl font-bold mb-1">Welcome back</h1>
  <p class="text-gray-500 text-sm mb-6">Login to manage your fundraisers</p>
  <?php if($error): ?><div class="bg-red-50 text-red-600 p-3 rounded-xl text-sm mb-4"><?= $error ?></div><?php endif; ?>
  <form method="POST" class="space-y-4">
    <input name="email" required placeholder="Email or Phone" value="admin@vusa.ltd" class="w-full border p-3 rounded-xl">
    <input name="password" type="password" required placeholder="Password" value="admin123" class="w-full border p-3 rounded-xl">
    <button class="w-full bg-black text-white py-3 rounded-full font-semibold">Login</button>
    <p class="text-center text-sm">No account? <a href="register.php" class="underline">Create verified account</a></p>
    <div class="bg-yellow-50 border border-yellow-200 p-3 rounded-xl text-xs">
      <b>Admin Demo:</b><br>
      Email: admin@vusa.ltd<br>
      Password: admin123<br>
      <a href="reset_admin.php" class="underline text-blue-600">Click here to reset admin if login fails</a>
    </div>
  </form>
</div>
<?php require_once 'includes/footer.php'; ?>

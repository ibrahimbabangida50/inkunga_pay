<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if(isLoggedIn()) header("Location: dashboard.php");

$error = '';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $id_type = $_POST['id_type'];
    $id_number = trim($_POST['id_number']);
    $country = $_POST['country'];
    $password = $_POST['password'];
    
    // Handle ID photo upload
    $photoPath = null;
    if(isset($_FILES['id_photo']) && $_FILES['id_photo']['error']==0){
        $ext = pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','pdf'];
        if(in_array(strtolower($ext), $allowed)){
            $fname = 'id_'.time().'_'.rand(1000,9999).'.'.$ext;
            $dest = __DIR__.'/uploads/ids/'.$fname;
            if(!is_dir(__DIR__.'/uploads/ids')) mkdir(__DIR__.'/uploads/ids', 0777, true);
            move_uploaded_file($_FILES['id_photo']['tmp_name'], $dest);
            $photoPath = 'uploads/ids/'.$fname;
        } else $error = "ID photo must be JPG, PNG or PDF";
    } else $error = "Please upload photo of your ID/Passport";

    if(!$error){
        if(strlen($phone) < 10) $error = "Invalid phone number";
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? OR phone=?");
            $stmt->execute([$email, $phone]);
            if($stmt->fetch()) $error = "Email or phone already registered";
            else {
                $otp = rand(100000,999999);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name,email,phone,password_hash,id_type,id_number,country,id_photo,otp_code,otp_expires,is_verified,admin_approved) VALUES (?,?,?,?,?,?,?,?,?,datetime('now','+10 minutes'),0,0)");
                $stmt->execute([$full_name,$email,$phone,$hash,$id_type,$id_number,$country,$photoPath,$otp]);
                $uid = $pdo->lastInsertId();
                $_SESSION['pending_user_id'] = $uid;
                $_SESSION['last_otp'] = $otp;
                header("Location: verify_otp.php");
                exit;
            }
        }
    }
}
?>
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border p-8 mt-6">
  <h1 class="text-2xl font-bold mb-1">Create your verified account</h1>
  <p class="text-gray-500 text-sm mb-6">ID photo + OTP required. Admin will approve after verification.</p>
  <?php if($error): ?><div class="bg-red-50 text-red-600 p-3 rounded-xl text-sm mb-4"><?= $error ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="space-y-4">
    <input name="full_name" required placeholder="Full Name as on ID" class="w-full border p-3 rounded-xl">
    <div class="grid md:grid-cols-2 gap-4">
      <select name="id_type" required class="border p-3 rounded-xl">
        <option value="national_id">National ID</option>
        <option value="passport">Passport</option>
        <option value="driving_license">Driving License</option>
      </select>
      <select name="country" required class="border p-3 rounded-xl">
        <option value="RW">Rwanda</option>
        <option value="CD">DR Congo</option>
        <option value="TZ">Tanzania</option>
        <option value="KE">Kenya</option>
        <option value="MW">Malawi</option>
        <option value="UG">Uganda</option>
        <option value="BI">Burundi</option>
        <option value="ZM">Zambia</option>
      </select>
    </div>
    <input name="id_number" required placeholder="ID / Passport Number" class="w-full border p-3 rounded-xl">
    <div class="border-2 border-dashed rounded-xl p-4">
      <label class="text-sm font-semibold">Upload Photo of ID/Passport (Required)</label>
      <input type="file" name="id_photo" accept=".jpg,.jpeg,.png,.pdf" required class="w-full mt-2 text-sm">
      <p class="text-[11px] text-gray-400 mt-1">Clear photo of front side. JPG/PNG/PDF max 5MB. This is for admin verification.</p>
    </div>
    <input name="phone" required placeholder="Phone Number e.g. 078xxxxxxx (will receive OTP)" class="w-full border p-3 rounded-xl">
    <input name="email" type="email" required placeholder="Email Address" class="w-full border p-3 rounded-xl">
    <input name="password" type="password" required placeholder="Create Password" class="w-full border p-3 rounded-xl">
    <label class="flex items-start gap-2 text-xs text-gray-500"><input type="checkbox" required> I confirm my ID and phone are correct and agree to verification by versa technology</label>
    <button class="w-full bg-black text-white py-3 rounded-full font-semibold">Create Account & Send OTP</button>
    <p class="text-center text-sm">Already have account? <a href="login.php" class="underline">Login</a></p>
  </form>
</div>
<?php require_once 'includes/footer.php'; ?>

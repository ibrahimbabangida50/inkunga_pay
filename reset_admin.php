<?php
require_once 'config/database.php';
$pdo->exec("DELETE FROM users WHERE email='admin@vusa.ltd'");
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, is_verified, is_admin, admin_approved, kyc_status, country, id_type, id_number) VALUES (?,?,?,?,1,1,1,'verified','RW','national_id','ADMIN001')")->execute(['Admin VUSA','admin@vusa.ltd','0780000000',$hash]);
echo "Admin reset! Email: admin@vusa.ltd / Password: admin123<br>";
echo "<a href='login.php'>Go to Login</a>";
?>

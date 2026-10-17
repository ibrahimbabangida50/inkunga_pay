<?php
require_once 'config/database.php';
$admin = $pdo->query("SELECT id FROM users WHERE email='admin@vusa.ltd'")->fetchColumn();
if(!$admin) $admin=1;

// Check if baby keza exists
$exists = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE slug='medical-help-baby-keza'")->fetchColumn();
if($exists==0){
  $pdo->prepare("INSERT INTO campaigns (user_id, title, slug, category, story, goal_amount, raised_amount, beneficiary_name, status, location) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$admin, 'Medical Help for Baby Keza', 'medical-help-baby-keza', 'medical', 'Baby Keza needs urgent heart surgery. Family cannot afford. Please donate to save her life. She is 2 years old.', 3000000, 2100000, 'Baby Keza', 'active', 'Rwanda']);
  echo "Baby Keza restored<br>";
}
$exists2 = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE slug='amahoro-school-construction'")->fetchColumn();
if($exists2==0){
  $pdo->prepare("INSERT INTO campaigns (user_id, title, slug, category, story, goal_amount, raised_amount, beneficiary_name, status, location) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$admin, 'Support Amahoro School Construction', 'amahoro-school-construction', 'education', 'We are building 3 classrooms for 120 children in Bugesera.', 5000000, 1250000, 'Amahoro School', 'active', 'Rwanda']);
  echo "Amahoro School restored<br>";
}
echo "<br> Done! <a href='admin.php'>Go to Admin</a> | <a href='index.php'>Go to Home</a>";
?>

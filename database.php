<?php
// config/database.php - INKUNGA Pay - Auto-update, keep DB, EAC Multi-Currency
session_start();
$dbFile = __DIR__ . '/../inkunga_pay.db';

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      full_name TEXT NOT NULL,
      email TEXT UNIQUE NOT NULL,
      phone TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      id_type TEXT DEFAULT 'national_id',
      id_number TEXT,
      id_photo TEXT,
      country TEXT DEFAULT 'RW',
      is_verified INTEGER DEFAULT 0,
      is_admin INTEGER DEFAULT 0,
      otp_code TEXT,
      otp_expires DATETIME,
      kyc_status TEXT DEFAULT 'pending',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS campaigns (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      slug TEXT UNIQUE NOT NULL,
      category TEXT DEFAULT 'community',
      story TEXT NOT NULL,
      goal_amount REAL NOT NULL,
      raised_amount REAL DEFAULT 0,
      raised_amount_usd REAL DEFAULT 0,
      beneficiary_name TEXT,
      location TEXT DEFAULT 'Africa',
      status TEXT DEFAULT 'pending',
      image TEXT DEFAULT 'default.jpg',
      currency TEXT DEFAULT 'RWF',
      usd_amount REAL DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS donations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      campaign_id INTEGER NOT NULL,
      donor_name TEXT,
      donor_phone TEXT,
      amount REAL NOT NULL,
      amount_in_campaign_currency REAL DEFAULT 0,
      amount_usd REAL DEFAULT 0,
      payment_method TEXT DEFAULT 'mtn_momo',
      payment_category TEXT DEFAULT 'mobile_money',
      donor_country TEXT DEFAULT 'Rwanda',
      donor_currency TEXT DEFAULT 'RWF',
      transaction_id TEXT,
      status TEXT DEFAULT 'pending',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS exchange_rates (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      currency_code TEXT NOT NULL,
      rate_to_usd REAL NOT NULL,
      rate_date DATE DEFAULT (date('now')),
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(currency_code, rate_date)
    );
    CREATE TABLE IF NOT EXISTS currencies (
      code TEXT PRIMARY KEY,
      country TEXT NOT NULL,
      symbol TEXT NOT NULL
    );
    ");

    // Auto-upgrade - keep your DB, no delete needed
    try { $pdo->exec("ALTER TABLE users ADD COLUMN id_type TEXT DEFAULT 'national_id'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN id_number TEXT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN country TEXT DEFAULT 'RW'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified INTEGER DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_code TEXT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_expires DATETIME"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN id_photo TEXT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN admin_approved INTEGER DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE campaigns ADD COLUMN image TEXT DEFAULT 'default.jpg'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE campaigns ADD COLUMN withdrawn INTEGER DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE campaigns ADD COLUMN currency TEXT DEFAULT 'RWF'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE campaigns ADD COLUMN usd_amount REAL DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE campaigns ADD COLUMN raised_amount_usd REAL DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE donations ADD COLUMN donor_country TEXT DEFAULT 'Rwanda'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE donations ADD COLUMN donor_currency TEXT DEFAULT 'RWF'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE donations ADD COLUMN payment_category TEXT DEFAULT 'mobile_money'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE donations ADD COLUMN amount_in_campaign_currency REAL DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE donations ADD COLUMN amount_usd REAL DEFAULT 0"); } catch(Exception $e){}

    $pdo->exec("INSERT OR IGNORE INTO currencies (code, country, symbol) VALUES 
      ('RWF','Rwanda','RWF'), ('TZS','Tanzania','TSh'), ('KES','Kenya','KSh'), 
      ('UGX','Uganda','USh'), ('BIF','Burundi','FBu'), ('SSP','South Sudan','SSP'),
      ('CDF','DR Congo','FC'), ('SOS','Somalia','SSh'), ('USD','USA','$')");

    $cnt = $pdo->query("SELECT COUNT(*) FROM exchange_rates WHERE rate_date=date('now')")->fetchColumn();
    if($cnt==0){
        $rates = [['RWF',1300], ['TZS',2580], ['KES',129], ['UGX',3720], ['BIF',2850], ['SSP',950], ['CDF',2750], ['SOS',570]];
        foreach($rates as $r){
            try { $pdo->prepare("INSERT INTO exchange_rates (currency_code, rate_to_usd, rate_date) VALUES (?,?,date('now'))")->execute($r); } catch(Exception $e){}
        }
    }

    $adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE email='admin@vusa.ltd'")->fetchColumn();
    if($adminExists == 0){
        $pdo->exec("INSERT INTO users (full_name, email, phone, password_hash, is_verified, is_admin, admin_approved, kyc_status, country) VALUES ('Admin VUSA','admin@vusa.ltd','0780000000','".password_hash('admin123', PASSWORD_DEFAULT)."',1,1,1,'verified','RW')");
    } else {
        $pdo->prepare("UPDATE users SET password_hash=?, is_verified=1, is_admin=1, admin_approved=1, kyc_status='verified' WHERE email=?")->execute([password_hash('admin123', PASSWORD_DEFAULT), 'admin@vusa.ltd']);
    }
    $count = $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
    if($count == 0){
        $pdo->exec("INSERT INTO campaigns (user_id, title, slug, category, story, goal_amount, raised_amount, beneficiary_name, status, location, currency, usd_amount, raised_amount_usd) VALUES (1, 'Support Amahoro School Construction', 'amahoro-school-construction', 'education', 'We are building 3 classrooms for 120 children in Bugesera.', 5000000, 1250000, 'Amahoro School', 'active', 'Rwanda', 'RWF', 3846, 961)");
    }
} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
function getPDO(){ global $pdo; return $pdo; }
function isLoggedIn(){ return isset($_SESSION['user_id']); }
function requireLogin(){ if(!isLoggedIn()){ header("Location: login.php?redirect=".urlencode($_SERVER['REQUEST_URI'])); exit; } }
function currentUser(){ if(!isLoggedIn()) return null; global $pdo; $stmt=$pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$_SESSION['user_id']]); return $stmt->fetch(); }

function getRate($code){
    global $pdo;
    $stmt = $pdo->prepare("SELECT rate_to_usd FROM exchange_rates WHERE currency_code=? ORDER BY rate_date DESC LIMIT 1");
    $stmt->execute([$code]);
    return $stmt->fetchColumn() ?: 0;
}
function convertToUSD($amount, $code){
    $rate = getRate($code);
    if(!$rate) return 0;
    return round($amount / $rate, 2);
}
function convertCurrency($amount, $fromCode, $toCode){
    // Convert via USD: amount / fromRate = USD, USD * toRate = toAmount
    $fromRate = getRate($fromCode);
    $toRate = getRate($toCode);
    if(!$fromRate || !$toRate) return $amount; // fallback no conversion
    $usd = $amount / $fromRate;
    return round($usd * $toRate, 2);
}
?>

<?php
require_once '../config/database.php';
header('Content-Type: application/json');
$convert = $_GET['convert'] ?? 0;
$from = $_GET['from'] ?? 'RWF';
$amount = floatval($convert);
if($amount<=0){ echo json_encode(['usd'=>0, 'rate'=>0]); exit; }
$rate = getRate($from);
if(!$rate){ echo json_encode(['usd'=>0, 'rate'=>0, 'error'=>'No rate for '.$from]); exit; }
$usd = round($amount / $rate, 2);
echo json_encode(['usd'=>$usd, 'rate'=>$rate, 'from'=>$from, 'amount'=>$amount]);

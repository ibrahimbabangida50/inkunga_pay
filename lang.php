<?php
// 4 languages: EN, FR, RW, SW
$lang = $_SESSION['lang'] ?? 'en';
if(isset($_GET['lang'])){ $_SESSION['lang'] = $_GET['lang']; $lang = $_GET['lang']; }

$translations = [
'en' => [
 'tagline' => 'Give. Support. Change Lives.',
 'hero_sub' => 'Africa’s trusted crowdfunding platform. Powering hope across the continent.',
 'explore' => 'Explore',
 'create' => '+ Create Campaign',
 'start_fundraiser' => 'Start a Fundraiser',
 'login' => 'Login',
 'register' => 'Register',
 'dashboard' => 'Dashboard',
 'logout' => 'Logout',
 'my_campaigns' => 'My Campaigns',
 'all_campaigns' => 'All Campaigns',
 'verified' => 'Verified Account',
],
'fr' => [
 'tagline' => 'Donner. Soutenir. Changer des Vies.',
 'hero_sub' => 'La plateforme de crowdfunding de confiance en Afrique.',
 'explore' => 'Explorer',
 'create' => '+ Créer une Campagne',
 'start_fundraiser' => 'Créer une collecte',
 'login' => 'Connexion',
 'register' => 'S’inscrire',
 'dashboard' => 'Tableau de bord',
 'logout' => 'Déconnexion',
 'my_campaigns' => 'Mes campagnes',
 'all_campaigns' => 'Toutes les campagnes',
 'verified' => 'Compte vérifié',
],
'rw' => [
 'tagline' => 'Tanga. Shyigikira. Hindura Ubuzima.',
 'hero_sub' => 'Urubuga rwizewe rwo gukusanya inkunga muri Afurika.',
 'explore' => 'Shakisha',
 'create' => '+ Tangiza Inkunga',
 'start_fundraiser' => 'Tangiza Inkunga',
 'login' => 'Injira',
 'register' => 'Iyandikishe',
 'dashboard' => 'Imbonerahamwe',
 'logout' => 'Sohoka',
 'my_campaigns' => 'Inkunga zanjye',
 'all_campaigns' => 'Inkunga zose',
 'verified' => 'Konti yemejwe',
],
'sw' => [
 'tagline' => 'Toa. Saidia. Badilisha Maisha.',
 'hero_sub' => 'Jukwaa la kuaminika la kuchangisha fedha barani Afrika.',
 'explore' => 'Chunguza',
 'create' => '+ Anzisha Kampeni',
 'start_fundraiser' => 'Anzisha Harambee',
 'login' => 'Ingia',
 'register' => 'Jisajili',
 'dashboard' => 'Dashibodi',
 'logout' => 'Toka',
 'my_campaigns' => 'Kampeni zangu',
 'all_campaigns' => 'Kampeni zote',
 'verified' => 'Akaunti Imehakikiwa',
],
];
function t($key){ global $translations,$lang; return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key; }
?>

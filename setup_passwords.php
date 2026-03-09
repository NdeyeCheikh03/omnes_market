<?php
// setup_passwords.php - génère les vrais hashes bcrypt pour les comptes de démo
// Exécuter UNE SEULE FOIS après l'import du SQL, puis supprimer ce fichier !

require_once __DIR__ . '/php/includes/config.php';
require_once __DIR__ . '/php/includes/db.php';

$db = db();

// hashes bcrypt corrects
$hashAdmin    = password_hash('admin123',    PASSWORD_BCRYPT, ['cost' => 12]);
$hashVendeur  = password_hash('vendeur123',  PASSWORD_BCRYPT, ['cost' => 12]);
$hashAcheteur = password_hash('acheteur123', PASSWORD_BCRYPT, ['cost' => 12]);

// admin
$db->prepare("UPDATE Administrateur SET mot_de_passe = ? WHERE email = 'admin@omnes.fr'")->execute([$hashAdmin]);

// vendeurs
$db->prepare("UPDATE Vendeur SET mot_de_passe = ? WHERE email = 'ndeye.cheikh@omnes.fr'")->execute([$hashVendeur]);
$db->prepare("UPDATE Vendeur SET mot_de_passe = ? WHERE email = 'awa.traore@omnes.fr'")->execute([$hashVendeur]);

// acheteur
$db->prepare("UPDATE Acheteur SET mot_de_passe = ? WHERE email = 'yaye.diarra@omnes.fr'")->execute([$hashAcheteur]);

echo "<h2 style='font-family:sans-serif;color:green'>✅ Mots de passe mis à jour !</h2>";
echo "<p style='font-family:sans-serif'><strong>Supprimez ce fichier immédiatement.</strong></p>";
echo "<ul style='font-family:sans-serif'>";
echo "<li>Admin : admin@omnes.fr / admin123</li>";
echo "<li>Vendeur 1 : ndeye.cheikh@omnes.fr / vendeur123</li>";
echo "<li>Vendeur 2 : awa.traore@omnes.fr / vendeur123</li>";
echo "<li>Acheteur : yaye.diarra@omnes.fr / acheteur123</li>";
echo "</ul>";

<?php
/**
 * OMNES MARKETPLACE — Script d'installation
 * Exécuter UNE SEULE FOIS après avoir importé database.sql
 * http://localhost/omnes_final/setup.php
 */
require_once __DIR__ . '/php/includes/config.php';
require_once __DIR__ . '/php/includes/db.php';

$users = [
    ['Administrateur', 'admin_id',   'admin@omnes.fr',             'admin123',    'Admin',   'OmnesMarket'],
    ['Vendeur',        'vendeur_id', 'jean.dupont@email.fr',       'vendeur123',  'Jean',    'Dupont'],
    ['Vendeur',        'vendeur_id', 'marie.laurent@gmail.com',    'vendeur123',  'Marie',   'Laurent'],
    ['Acheteur',       'acheteur_id','sophie.lemaire@gmail.com',   'acheteur123', 'Sophie',  'Lemaire'],
    ['Acheteur',       'acheteur_id','thomas.martin@ece.fr',       'acheteur123', 'Thomas',  'Martin'],
];

$ok = 0;
foreach ($users as [$table, $idCol, $email, $pwd, $prenom, $nom]) {
    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = db()->prepare("UPDATE $table SET mot_de_passe = ? WHERE email = ?");
    $n = $stmt->execute([$hash, $email]) ? $stmt->rowCount() : 0;
    echo ($n > 0 ? "✅" : "⚠️ ") . " $table $email — hash mis à jour ($n ligne)\n";
    $ok += $n;
}
echo "\n✅ $ok mots de passe mis à jour. Vous pouvez supprimer ce fichier.\n";
echo "<br><a href='pages/login.html'>→ Aller à la connexion</a>\n";

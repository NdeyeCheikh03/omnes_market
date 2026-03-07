<?php
/**
 * templates/header.html.php
 * En-tête HTML commun — injecté par PHP dans toutes les pages
 *
 * Variables attendues (définies par le controller de chaque page) :
 *   $pageTitle     string  — Titre de l'onglet
 *   $pageClass     string  — Classe CSS ajoutée à <body>
 *   $currentNav    string  — ID du lien actif (home|browse|enchere|nego)
 *   $flashBanner   bool    — Afficher la bannière promotionnelle
 *   $cartCount     int     — Nombre d'articles dans le panier
 *   $notifCount    int     — Nombre de notifications non lues
 *   $user          array|null — Utilisateur connecté
 */

// Valeurs par défaut sécurisées
$pageTitle   = isset($pageTitle)   ? htmlspecialchars($pageTitle)  : 'Omnes MarketPlace';
$pageClass   = isset($pageClass)   ? htmlspecialchars($pageClass)  : '';
$currentNav  = isset($currentNav)  ? $currentNav                   : '';
$flashBanner = isset($flashBanner) ? $flashBanner                  : true;
$cartCount   = isset($cartCount)   ? (int)$cartCount               : 0;
$notifCount  = isset($notifCount)  ? (int)$notifCount              : 0;
$user        = isset($user)        ? $user                         : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Omnes MarketPlace — La place de marché de la communauté Omnes Education">
  <title><?= $pageTitle ?> — Omnes MarketPlace</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;0,900;1,500&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- CSS — ordre important -->
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/variables.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/layout.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/pages.css">

  <?php if (isset($extraCss)): ?>
  <link rel="stylesheet" href="<?= $extraCss ?>">
  <?php endif; ?>
</head>
<body class="<?= $pageClass ?>">

<?php if ($flashBanner): ?>
<!-- ── Flash Banner ── -->
<div class="flash-banner" role="banner">
  <i class="fa-solid fa-bolt"></i>
  &nbsp;Livraison offerte dès 80 € d'achat &nbsp;·&nbsp;
  Jusqu'à -40% sur les articles rares &nbsp;·&nbsp;
  <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=enchere">Enchères en cours →</a>
</div>
<?php endif; ?>

<!-- ════════════════════════════════
     HEADER / NAVBAR
════════════════════════════════ -->
<header class="site-header" role="banner">
  <div class="container">
    <div class="header-inner">

      <!-- Logo -->
      <a href="<?= SITE_URL ?>/pages/home/index.php" class="logo" aria-label="Omnes MarketPlace — Accueil">
        <div class="logo-icon" aria-hidden="true">
          <i class="fa-solid fa-shop"></i>
        </div>
        <span class="logo-text">Omnes <span>Market</span></span>
      </a>

      <!-- Navigation principale (desktop) -->
      <nav class="main-nav" aria-label="Navigation principale">
        <a href="<?= SITE_URL ?>/pages/home/index.php"
           class="nav-link <?= $currentNav === 'home'    ? 'active' : '' ?>">
          <i class="fa-solid fa-house"></i> Accueil
        </a>
        <a href="<?= SITE_URL ?>/pages/browse/browse.php"
           class="nav-link <?= $currentNav === 'browse'  ? 'active' : '' ?>">
          <i class="fa-solid fa-grid-2"></i> Catalogue
        </a>
        <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=enchere"
           class="nav-link <?= $currentNav === 'enchere' ? 'active' : '' ?>">
          <i class="fa-solid fa-gavel"></i> Enchères
        </a>
        <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=negociation"
           class="nav-link <?= $currentNav === 'nego'    ? 'active' : '' ?>">
          <i class="fa-solid fa-handshake"></i> Négociation
        </a>
      </nav>

      <!-- Actions header -->
      <div class="header-actions">

        <!-- Recherche -->
        <a href="<?= SITE_URL ?>/pages/browse/browse.php"
           class="header-icon-btn"
           aria-label="Rechercher">
          <i class="fa-solid fa-magnifying-glass"></i>
        </a>

        <?php if ($user): ?>

          <!-- Notifications -->
          <a href="<?= SITE_URL ?>/pages/account/notifications.php"
             class="header-icon-btn"
             aria-label="Notifications <?= $notifCount > 0 ? "($notifCount non lues)" : '' ?>">
            <i class="fa-solid fa-bell"></i>
            <?php if ($notifCount > 0): ?>
            <span class="icon-badge panier-notif-badge"><?= $notifCount ?></span>
            <?php endif; ?>
          </a>

          <!-- Panier -->
          <a href="<?= SITE_URL ?>/pages/cart/cart.php"
             class="header-icon-btn"
             aria-label="Panier <?= $cartCount > 0 ? "($cartCount articles)" : '' ?>">
            <i class="fa-solid fa-bag-shopping"></i>
            <?php if ($cartCount > 0): ?>
            <span class="icon-badge panier-badge"><?= $cartCount ?></span>
            <?php endif; ?>
          </a>

          <!-- Menu utilisateur -->
          <div class="user-menu">
            <button class="user-btn" aria-haspopup="true" aria-expanded="false">
              <span><?= htmlspecialchars($user['prenom'] ?? 'Mon compte') ?></span>
              <div class="user-avatar" aria-hidden="true">
                <?= strtoupper(substr($user['prenom'] ?? 'U', 0, 1) . substr($user['nom'] ?? '', 0, 1)) ?>
              </div>
            </button>

            <div class="user-dropdown" role="menu">
              <div class="dropdown-header">
                <div class="dropdown-name"><?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></div>
                <div class="dropdown-role">
                  <?php
                  $roles = ['admin' => 'Administrateur', 'vendeur' => 'Vendeur', 'acheteur' => 'Acheteur'];
                  echo $roles[$user['role'] ?? 'acheteur'] ?? 'Acheteur';
                  ?>
                </div>
              </div>

              <?php if (($user['role'] ?? '') === 'acheteur'): ?>
              <a href="<?= SITE_URL ?>/pages/account/account.php"  class="dropdown-item" role="menuitem"><i class="fa-solid fa-user"></i> Mon compte</a>
              <a href="<?= SITE_URL ?>/pages/cart/cart.php"         class="dropdown-item" role="menuitem"><i class="fa-solid fa-bag-shopping"></i> Mon panier</a>
              <a href="<?= SITE_URL ?>/pages/account/notifications.php" class="dropdown-item" role="menuitem"><i class="fa-solid fa-bell"></i> Notifications</a>
              <?php elseif (($user['role'] ?? '') === 'vendeur'): ?>
              <a href="<?= SITE_URL ?>/pages/vendor/vendor.php"     class="dropdown-item" role="menuitem"><i class="fa-solid fa-store"></i> Mon espace vendeur</a>
              <?php elseif (($user['role'] ?? '') === 'admin'): ?>
              <a href="<?= SITE_URL ?>/pages/admin/admin.php"       class="dropdown-item" role="menuitem"><i class="fa-solid fa-shield"></i> Administration</a>
              <?php endif; ?>

              <div class="dropdown-divider"></div>
              <a href="<?= SITE_URL ?>/php/api/logout.php"          class="dropdown-item danger" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
            </div>
          </div>

        <?php else: ?>

          <!-- Non connecté -->
          <a href="<?= SITE_URL ?>/pages/auth/login.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-right-to-bracket"></i> Connexion
          </a>
          <a href="<?= SITE_URL ?>/pages/auth/register.php" class="btn btn-primary btn-sm">
            S'inscrire
          </a>

        <?php endif; ?>

        <!-- Hamburger mobile -->
        <button class="hamburger" aria-label="Menu" aria-expanded="false" aria-controls="mobile-menu">
          <span></span>
          <span></span>
          <span></span>
        </button>

      </div><!-- /header-actions -->
    </div><!-- /header-inner -->
  </div><!-- /container -->
</header>

<!-- Menu mobile -->
<nav id="mobile-menu" class="mobile-menu" aria-label="Menu mobile">
  <a href="<?= SITE_URL ?>/pages/home/index.php"                   class="mobile-nav-link <?= $currentNav === 'home'    ? 'active' : '' ?>"><i class="fa-solid fa-house fa-fw"></i> Accueil</a>
  <a href="<?= SITE_URL ?>/pages/browse/browse.php"                class="mobile-nav-link <?= $currentNav === 'browse'  ? 'active' : '' ?>"><i class="fa-solid fa-grid-2 fa-fw"></i> Catalogue</a>
  <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=enchere"   class="mobile-nav-link <?= $currentNav === 'enchere' ? 'active' : '' ?>"><i class="fa-solid fa-gavel fa-fw"></i> Enchères</a>
  <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=negociation" class="mobile-nav-link <?= $currentNav === 'nego'  ? 'active' : '' ?>"><i class="fa-solid fa-handshake fa-fw"></i> Négociation</a>
  <hr style="border-color:var(--clr-border);margin:var(--sp-4) 0">
  <?php if ($user): ?>
  <a href="<?= SITE_URL ?>/pages/account/account.php"              class="mobile-nav-link"><i class="fa-solid fa-user fa-fw"></i> Mon compte</a>
  <a href="<?= SITE_URL ?>/pages/cart/cart.php"                    class="mobile-nav-link"><i class="fa-solid fa-bag-shopping fa-fw"></i> Panier</a>
  <a href="<?= SITE_URL ?>/php/api/logout.php"                     class="mobile-nav-link" style="color:var(--clr-danger)"><i class="fa-solid fa-right-from-bracket fa-fw"></i> Déconnexion</a>
  <?php else: ?>
  <a href="<?= SITE_URL ?>/pages/auth/login.php"    class="mobile-nav-link"><i class="fa-solid fa-right-to-bracket fa-fw"></i> Connexion</a>
  <a href="<?= SITE_URL ?>/pages/auth/register.php" class="mobile-nav-link"><i class="fa-solid fa-user-plus fa-fw"></i> S'inscrire</a>
  <?php endif; ?>
</nav>

<!-- Contenu de la page -->
<main class="page-main" id="main-content" role="main">

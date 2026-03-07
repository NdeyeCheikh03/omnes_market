<?php
/**
 * templates/footer.html.php
 * Pied de page HTML commun — injecté par PHP dans toutes les pages
 */
?>
</main><!-- /page-main -->

<!-- ════════════════════════════════
     FOOTER
════════════════════════════════ -->
<footer class="site-footer" role="contentinfo">
  <div class="container">

    <div class="footer-grid">

      <!-- Colonne marque -->
      <div class="footer-brand">
        <div class="logo">
          <div class="logo-icon"><i class="fa-solid fa-shop"></i></div>
          <span class="logo-text">Omnes <span>Market</span></span>
        </div>
        <p class="footer-desc">
          La place de marché exclusive de la communauté Omnes Education.
          Achetez, vendez, enchérissez entre étudiants et anciens élèves.
        </p>
        <div class="footer-socials">
          <a href="#" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="#" class="social-btn" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
          <a href="#" class="social-btn" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
          <a href="#" class="social-btn" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        </div>
      </div>

      <!-- Marketplace -->
      <div>
        <h3 class="footer-col-title">Marketplace</h3>
        <nav class="footer-links" aria-label="Liens marketplace">
          <a href="<?= SITE_URL ?>/pages/browse/browse.php"                    class="footer-link"><i class="fa-solid fa-chevron-right"></i> Tout parcourir</a>
          <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=enchere"       class="footer-link"><i class="fa-solid fa-chevron-right"></i> Enchères live</a>
          <a href="<?= SITE_URL ?>/pages/browse/browse.php?type=negociation"   class="footer-link"><i class="fa-solid fa-chevron-right"></i> Négociation</a>
          <a href="<?= SITE_URL ?>/pages/browse/browse.php?categorie=rare"     class="footer-link"><i class="fa-solid fa-chevron-right"></i> Articles rares</a>
          <a href="<?= SITE_URL ?>/pages/browse/browse.php?categorie=luxe"     class="footer-link"><i class="fa-solid fa-chevron-right"></i> Haute gamme</a>
        </nav>
      </div>

      <!-- Mon compte -->
      <div>
        <h3 class="footer-col-title">Mon Compte</h3>
        <nav class="footer-links" aria-label="Liens compte">
          <a href="<?= SITE_URL ?>/pages/auth/login.php"             class="footer-link"><i class="fa-solid fa-chevron-right"></i> Connexion</a>
          <a href="<?= SITE_URL ?>/pages/auth/register.php"          class="footer-link"><i class="fa-solid fa-chevron-right"></i> Inscription</a>
          <a href="<?= SITE_URL ?>/pages/account/account.php"        class="footer-link"><i class="fa-solid fa-chevron-right"></i> Mon profil</a>
          <a href="<?= SITE_URL ?>/pages/cart/cart.php"              class="footer-link"><i class="fa-solid fa-chevron-right"></i> Mon panier</a>
          <a href="<?= SITE_URL ?>/pages/account/notifications.php"  class="footer-link"><i class="fa-solid fa-chevron-right"></i> Notifications</a>
        </nav>
      </div>

      <!-- Newsletter -->
      <div>
        <h3 class="footer-col-title">Rester informé</h3>
        <p style="font-size:var(--text-sm);color:rgba(255,255,255,.5);margin-bottom:var(--sp-4);line-height:var(--lh-loose);">
          Recevez les nouvelles enchères et offres exclusives directement dans votre boîte mail.
        </p>
        <div class="newsletter-form">
          <input type="email" class="newsletter-input" placeholder="votre@email.fr" aria-label="Votre adresse email">
          <button class="newsletter-btn" aria-label="S'abonner">
            <i class="fa-solid fa-paper-plane"></i>
          </button>
        </div>
        <p style="font-size:var(--text-xs);color:rgba(255,255,255,.3);margin-top:var(--sp-3);">
          Pas de spam. Désabonnement à tout moment.
        </p>
      </div>

    </div><!-- /footer-grid -->

    <!-- Footer bottom -->
    <div class="footer-bottom">
      <p class="footer-copy">
        &copy; <?= date('Y') ?> Omnes MarketPlace &mdash; Projet Web ING3 APP &mdash; ECE Paris / Omnes Education
      </p>
      <div class="footer-payment-icons" aria-label="Modes de paiement acceptés">
        <div class="payment-icon" title="Visa">VISA</div>
        <div class="payment-icon" title="Mastercard">MC</div>
        <div class="payment-icon" title="PayPal">PP</div>
        <div class="payment-icon" title="Chèque cadeau">🎁</div>
      </div>
    </div>

  </div><!-- /container -->
</footer>

<!-- ════════════════════════════════
     SCRIPTS — fin de body
════════════════════════════════ -->
<script src="<?= SITE_URL ?>/assets/js/utils.js" defer></script>
<script src="<?= SITE_URL ?>/assets/js/ui.js"    defer></script>
<script src="<?= SITE_URL ?>/assets/js/api.js"   defer></script>

<?php if (isset($extraJs)): ?>
<script src="<?= $extraJs ?>" defer></script>
<?php endif; ?>

<!-- Scripts inline injectés par le controller de page -->
<?php if (isset($inlineJs)) echo $inlineJs; ?>

</body>
</html>

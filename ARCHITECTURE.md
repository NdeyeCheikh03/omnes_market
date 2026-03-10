# Architecture Omnes MarketPlace v2

```
omnes_marketplace/
│
├── assets/                    ← STATIQUE (HTML/CSS/JS pur)
│   ├── css/
│   │   ├── variables.css      ← Variables couleurs, fonts, espacements
│   │   ├── base.css           ← Reset, typographie, utilitaires
│   │   ├── layout.css         ← Header, Footer, Sidebar, Grid
│   │   ├── components.css     ← Cartes, boutons, badges, modals
│   │   └── pages.css          ← Styles spécifiques par page
│   ├── js/
│   │   ├── utils.js           ← Helpers : toast, formatPrice, countdown
│   │   ├── ui.js              ← Menu, modals, tabs, toggles (UI pur)
│   │   └── api.js             ← Appels AJAX vers /php/api/*.php
│   └── images/uploads/
│
├── templates/                 ← SQUELETTES HTML (inclus par PHP)
│   ├── header.html.php        ← <head> + navbar (données injectées)
│   └── footer.html.php        ← footer + scripts
│
├── pages/                     ← PAGES HTML/PHP (séparées logique/vue)
│   ├── home/
│   │   ├── home.html          ← Maquette HTML statique
│   │   └── index.php          ← Controller : récupère données → injecte dans home.html
│   ├── browse/
│   │   ├── browse.html
│   │   └── browse.php
│   ├── article/
│   │   ├── article.html
│   │   └── article.php
│   ├── auth/
│   │   ├── login.html
│   │   ├── login.php
│   │   ├── register.html
│   │   └── register.php
│   ├── cart/
│   │   ├── cart.html
│   │   └── cart.php
│   ├── account/
│   │   ├── account.html
│   │   └── account.php
│   ├── admin/
│   │   ├── admin.html
│   │   └── admin.php
│   └── vendor/
│       ├── vendor.html
│       └── vendor.php
│
├── php/
│   ├── includes/
│   │   ├── config.php         ← Constantes (DB, SITE_URL...)
│   │   ├── db.php             ← PDO singleton
│   │   ├── auth.php           ← Fonctions auth (login, register...)
│   │   └── functions.php      ← Fonctions métier
│   ├── controllers/
│   │   ├── ArticleController.php
│   │   ├── EnchereController.php
│   │   ├── NegociationController.php
│   │   └── PanierController.php
│   └── api/                   ← Endpoints AJAX (JSON)
│       ├── panier.php
│       ├── enchere.php
│       ├── negociation.php
│       ├── commande.php
│       └── logout.php
│
├── database.sql
├── README.md
└── index.php                  ← Point d'entrée (redirige vers pages/home/)
```

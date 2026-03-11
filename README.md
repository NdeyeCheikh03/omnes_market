# Omnes Market — Plateforme de vente entre étudiants

Projet web développé dans le cadre d'un cours à l'OMNES Education.
Application permettant aux étudiants d'acheter, vendre et enchérir sur des articles entre eux.

## Fonctionnalités

- Accueil et navigation des articles
- Inscription, connexion et gestion du profil
- Panier et système de commandes
- Enchères en temps réel
- Négociation de prix
- Notifications utilisateurs
- Dashboards admin et vendeur

## Stack technique

- **Frontend** : HTML, CSS, JavaScript
- **Backend** : PHP
- **Base de données** : MySQL

## Structure du projet

```
omnes_v5/
├── assets/          # CSS et JS globaux
├── images/          # Images uploadées
├── pages/           # Pages HTML et scripts JS par page
├── php/
│   ├── api/         # Endpoints API REST
│   └── includes/    # Config, BDD, fonctions utilitaires
├── templates/       # Header et footer partagés
├── database.sql     # Schéma de la base de données
└── index.php        # Point d'entrée principal
```

## Installation

1. Cloner le dépôt
2. Importer `database.sql` dans MySQL
3. Configurer `php/includes/config.php` avec vos identifiants BDD
4. Lancer un serveur PHP local (`php -S localhost:8000`)

## Équipe

| Membre | Responsabilités |
|---|---|
| **Ndeye Cheikh Diop** | Pages principales (accueil, browse, article), structure, intégration backend |
| **Yaye Diarratoullah Cissoko** | Pages utilisateurs (login, register, profil), authentification, notifications |
| **Awa Traore** | Panier, commandes, enchères, négociation, APIs |

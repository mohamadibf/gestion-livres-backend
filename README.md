![image](https://github.com/user-attachments/assets/7206fa5a-8da1-469a-894c-eaae79f48363)

---

# API de gestion de bibliothèque : backend

API RESTful Laravel pour gérer un catalogue de livres, les emprunts et les comptes lecteurs, consommée par le [frontend React](https://github.com/mohamadibf/gestion-livres-frontend).

## Fonctionnalités

- **Catalogue** : CRUD des livres avec couverture (2 Mo max), recherche (titre, auteur, ISBN, cote), filtre par genre, tri, pagination.
- **Fiche enrichie** : cote de rangement, état de conservation, mots-clés, disponibilité à l'emprunt.
- **Brouillons** : un brouillon n'exige que le titre et l'auteur et n'est visible que des administrateurs.
- **Emprunts** : emprunter, rendre, prolonger, historique, vue administrateur de tous les emprunts.
- **Comptes** : inscription, connexion, vérification d'email, mot de passe oublié, profil (nom, email, mot de passe, suppression).
- **Rôles** : `admin` (gestion complète) et `reader` (consultation et emprunts).
- **Réglages** modifiables par un administrateur, avec exceptions par lecteur.
- **Catalogue public** : les visiteurs consultent les livres publiés sans compte.

## Technologies

PHP 8.2+, Laravel 12, Laravel Sanctum (mode SPA, cookies de session), MySQL 8 (SQLite pour les tests).

## Installation

```bash
git clone https://github.com/mohamadibf/gestion-livres-backend.git
cd gestion-livres-backend
composer install
cp .env.example .env
php artisan key:generate
```

Variables à renseigner dans `.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=gestion_livres
DB_USERNAME=root
DB_PASSWORD=

# Authentification SPA : le frontend et l'API doivent partager le même hôte
# (localhost des deux côtés, pas 127.0.0.1) pour que les cookies fonctionnent.
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost

# Emails (vérification, mot de passe oublié) : Mailpit en développement
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

Puis :

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve          # http://localhost:8000
```

[Mailpit](https://mailpit.axllent.org/docs/install/) (interface sur http://localhost:8025) permet de lire les emails envoyés en local :
`docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit`.

### Comptes de démonstration (développement uniquement)

`php artisan migrate --seed` crée `admin@example.com` (administrateur) et `reader@example.com` (lecteur), mot de passe `password`.

## Règles de gestion

| Sujet | Règle |
|---|---|
| Prêt | Un livre empruntable, publié et non déjà emprunté. Un seul prêt actif par livre, garanti par la base de données. |
| Durée / limite / prolongations | 21 jours, 1 livre par lecteur, 1 prolongation par défaut (modifiable, voir ci-dessous). |
| Retard | Un emprunt en retard ne peut pas être prolongé. |
| Retour | Par l'emprunteur ou par un administrateur. |
| Suppression d'un livre | Refusée tant qu'il est emprunté. |
| Brouillon | Titre et auteur suffisent. Publier exige ISBN, année et genre. Invisible pour visiteurs et lecteurs. |
| Suppression d'un compte | Mot de passe requis. Refusée avec un livre emprunté ou pour le dernier administrateur. |

### Réglages

Les valeurs par défaut sont dans `config/loans.php` (surchargeables par `.env` : `LOAN_DURATION_DAYS`, `LOAN_MAX_ACTIVE_PER_USER`, `LOAN_MAX_RENEWALS`, `LOAN_DUE_SOON_DAYS`, `LIBRARY_NAME`).
Un administrateur peut les modifier depuis l'application (page *Paramètres*) : les valeurs sont alors enregistrées en base et priment. Des exceptions par lecteur (limite d'emprunts, durée du prêt) se définissent depuis la page *Utilisateurs*.

## Endpoints

Authentification : `GET /sanctum/csrf-cookie` avant toute requête d'écriture, puis cookies de session et en-tête `X-XSRF-TOKEN`.

| Méthode | Route | Accès |
|---|---|---|
| POST | `/api/register`, `/api/login`, `/api/forgot-password`, `/api/reset-password` | public |
| POST | `/api/logout` ; GET `/api/user` | connecté |
| GET | `/api/books`, `/api/books/{id}` | public (livres publiés), brouillons pour les admins |
| POST, PUT/PATCH, DELETE | `/api/books`, `/api/books/{id}` | admin |
| GET | `/api/settings` | public |
| PUT | `/api/settings` | admin |
| GET | `/api/users` ; PATCH `/api/users/{id}` | admin (exceptions de prêt) |
| GET | `/api/loans?scope=mine\|all&status=active\|returned` | connecté vérifié (`all` : admin) |
| POST | `/api/books/{id}/borrow`, `/api/loans/{id}/return`, `/api/loans/{id}/renew` | connecté vérifié |
| PATCH | `/api/profile` ; PUT `/api/profile/password` ; DELETE `/api/profile` ; GET `/api/profile/stats` | connecté |

Paramètres de `GET /api/books` : `search`, `genre`, `sort` (`recent`, `title`, `author`, `year_asc`, `year_desc`), `per_page` (1 à 50), `status` (`published` ou `draft`, administrateurs uniquement), `page`.

## Sécurité

- Cookies de session HttpOnly et protection CSRF (Sanctum SPA), CORS limité à `FRONTEND_URL` avec credentials.
- Autorisations par `BookPolicy` ; rôle et exceptions de prêt non modifiables par l'utilisateur lui-même.
- Limitation de débit sur la connexion, l'inscription, la réinitialisation, le profil et les lectures publiques.
- Validation systématique (FormRequest), jokers `LIKE` échappés, upload d'images limité à 2 Mo.
- Les noms d'emprunteurs ne sont visibles que des administrateurs et de l'emprunteur.

## Tests

```bash
php artisan test
```

Les tests couvrent l'authentification, les droits, les livres (recherche, brouillons, champs de fiche), les emprunts, les réglages, les exceptions par lecteur et le profil.
Avec PHP 8.5, des avertissements de dépréciation de PDO peuvent s'afficher sans affecter les résultats.

## Dépannage

- Journaux : `storage/logs/laravel.log`.
- Les cookies ne passent pas : vérifiez que le site et l'API sont tous deux en `localhost`, et `SANCTUM_STATEFUL_DOMAINS`.
- Aucun email reçu : lancez Mailpit et vérifiez `MAIL_MAILER=smtp`, `MAIL_PORT=1025`.
- Le port 8000 est occupé : `php artisan serve --port=8001` et adaptez `VITE_API_URL` côté frontend.

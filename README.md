<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>


---

# API de Gestion de Bibliothèque - Backend

1. **Aperçu**  
   API RESTful sécurisée avec Laravel pour gérer une base de données de livres, incluant le stockage d'images.

2. **Fonctionnalités**  
   - 📚 CRUD complet  
   - 🖼 Stockage sécurisé d'images  
   - 🔒 Validation des données  
   - 📊 Pagination  
   - 🔄 CORS configuré  

3. **Technologies**  
   - 🐘 PHP 8.2+  
   - 🎼 Laravel 12  
   - 🗃 MySQL 8  
   - 🛡 Sanctum  

4. **Installation**  
   ```bash
   git clone https://github.com/votre-repo.git
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   # Configurer la DB dans .env :
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE="mettez le nom de votre base de donnée"
   DB_USERNAME="Mettez l'utilisateur"
   DB_PASSWORD= "Le mot de passe s'il y'en a
  
   npm install
   composer run dev

5. **Endpoints API**
GET|POST    /api/books
GET|PUT|DELETE /api/books/{id}

6. **Sécurité**
 - Validation des champs
 - Middleware CORS
 - Limite upload 2MB

7. **Structure**

php artisan serve          # Port 8000
php artisan storage:link   # Lien symbolique
php artisan test           # Tests

8. **Dépannage**
 - storage/logs/laravel.log
 - Mode debug dans .env
    ```bash
    APP_DEBUG=true

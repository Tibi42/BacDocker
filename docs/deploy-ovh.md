# Guide déploiement — VPS OVH + Nginx (`guillaumepecquet.ovh`)

## 1. DNS

Chez OVH, zone DNS du domaine :

| Type | Nom | Cible |
|------|-----|--------|
| A | `@` | IP publique du VPS |
| A | `www` | IP publique du VPS (optionnel) |

Attendre la propagation (souvent quelques minutes).

## 2. Paquets serveur (Ubuntu)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl

# PHP 8.4 (si pas dans les dépôts par défaut, utiliser ondrej/php)
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-xml php8.4-mbstring \
  php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-opcache
```

Composer :

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 3. MySQL

```bash
sudo mysql
```

```sql
CREATE DATABASE laboiteachimere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON laboiteachimere.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Code applicatif

```bash
sudo mkdir -p /var/www
sudo chown "$USER":www-data /var/www
cd /var/www
git clone <URL_DU_REPO> laboiteachimere
cd laboiteachimere
```

Secrets :

```bash
cp .env.prod.dist .env.local
nano .env.local   # APP_SECRET, DATABASE_URL, MAILER_DSN, DEFAULT_URI
```

Générer un secret :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Premier install :

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:warmup
sudo chown -R www-data:www-data var public/images
sudo chmod -R ug+rwX var public/images
```

Créer le compte admin **manuellement** (pas de fixtures en prod) :

```bash
php bin/console app:…   # ou insertion SQL / commande make:user si disponible
```

Si aucune commande dédiée : créer un utilisateur via une requête SQL temporaire puis changer le mot de passe depuis l’UI, ou temporairement utiliser un script one-shot — **ne jamais charger les fixtures**.

## 5. Nginx

```bash
sudo cp deploy/nginx/guillaumepecquet.ovh.conf /etc/nginx/sites-available/guillaumepecquet.ovh
sudo ln -sf /etc/nginx/sites-available/guillaumepecquet.ovh /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Vérifier le socket PHP : `ls /run/php/` — adapter `fastcgi_pass` dans le conf si besoin (`php8.3-fpm.sock`, etc.).

## 6. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d guillaumepecquet.ovh -d www.guillaumepecquet.ovh
```

## 7. Mises à jour suivantes

```bash
cd /var/www/laboiteachimere
chmod +x bin/deploy.sh
./bin/deploy.sh
```

## 8. Checklist go-live

- [ ] Site joignable en HTTPS
- [ ] Login admin fonctionne
- [ ] Envoi d’email (reset password / inscription) OK
- [ ] Upload images articles OK (`public/images` writable)
- [ ] `APP_DEBUG=0`, profiler absent
- [ ] Pas de fixtures chargées
- [ ] Firewall : `ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable`

## Dépannage rapide

| Symptôme | Piste |
|----------|--------|
| 502 Bad Gateway | Socket PHP-FPM incorrect dans Nginx |
| 503 APP_SECRET | Secret trop court / placeholder |
| Page blanche | `sudo tail -f /var/log/nginx/*.error.log` et `var/log/prod.log` |
| CSS cassé | Relancer `asset-map:compile` + `tailwind:build --minify` |
| CSRF login | Cookies HTTPS / `DEFAULT_URI` en `https://…` |

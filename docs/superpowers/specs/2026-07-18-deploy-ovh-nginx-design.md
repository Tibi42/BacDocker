# Déploiement production — VPS OVH + Nginx

**Date :** 2026-07-18  
**Domaine :** `guillaumepecquet.ovh`  
**Approche :** A — PHP 8.4-FPM + Nginx + MySQL sur le VPS (sans Docker)

## Objectif

Mettre en ligne l’application Symfony La Boîte Chimère sur un VPS OVH, derrière Nginx + HTTPS (Let’s Encrypt).

## Architecture

```
Internet → Nginx (443 TLS) → PHP-FPM 8.4 → Symfony (public/index.php)
                ↓
            MySQL 8 (localhost)
```

- Document root : `/var/www/laboiteachimere/public`
- Code : `/var/www/laboiteachimere` (clone git)
- Secrets : `/var/www/laboiteachimere/.env.local` (non versionné)
- TLS : Certbot (`certbot --nginx`)

## Prérequis serveur

- Ubuntu 22.04/24.04 LTS
- Accès SSH root ou sudo
- DNS A/AAAA de `guillaumepecquet.ovh` → IP du VPS
- Ports 80/443 ouverts

## Variables d’environnement (prod)

| Variable | Exemple / règle |
|----------|------------------|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | aléatoire ≥ 32 caractères (pas de placeholder) |
| `DEFAULT_URI` | `https://guillaumepecquet.ovh` |
| `DATABASE_URL` | MySQL local dédié |
| `MAILER_DSN` | SMTP réel (OVH / Brevo / etc.) |
| `SYMFONY_TRUSTED_PROXIES` | optionnel si reverse-proxy |

`ProductionSecretSubscriber` bloque le démarrage si `APP_SECRET` est trop court ou placeholder.

## Déploiement applicatif

1. `git pull`
2. `composer install --no-dev --optimize-autoloader`
3. Migrations Doctrine
4. `php bin/console tailwind:build --minify`
5. `php bin/console asset-map:compile`
6. `php bin/console cache:clear` + `cache:warmup`
7. Permissions `var/` (www-data)

**Interdit en prod :** `doctrine:fixtures:load`

## Livrables repo

- `deploy/nginx/guillaumepecquet.ovh.conf` — vhost Nginx
- `.env.prod.dist` — modèle de secrets
- `bin/deploy.sh` — script de mise à jour
- `docs/deploy-ovh.md` — guide pas à pas VPS

## Hors scope (cette itération)

- CI/CD GitHub Actions
- Docker en production
- Migration DNS `laboiteachimere.fr` (domaine actuel = `guillaumepecquet.ovh`)

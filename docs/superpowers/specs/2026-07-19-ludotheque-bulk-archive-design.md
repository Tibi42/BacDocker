# Archivage en masse des jeux (ludothèque admin) — Design

## Contexte

Dans le dashboard admin de la ludothèque (`/admin/ludotheque`), chaque jeu **disponible** peut être archivé individuellement via `POST /admin/ludotheque/{id}/archiver` (`app_admin_ludotheque_archive`). L’archivage est un soft-delete : le jeu disparaît des listes mais conserve son historique (notes, journal d’emprunts). Les jeux en attente ou empruntés ne peuvent pas être archivés.

Pour gérer plusieurs jeux d’un coup (nettoyage après import CSV, etc.), il manque une sélection multiple.

## Objectif

Permettre à un admin de cocher plusieurs jeux **disponibles** sur la page courante et de les archiver en une seule action, avec confirmation, CSRF, et le même comportement métier que l’archivage unitaire.

## Approche

Formulaire POST classique + JS minimal pour le compteur / « tout sélectionner » (pas de Stimulus dédié, pas d’AJAX).

## UI (`templates/admin/ludotheque/index.html.twig`)

- Colonne checkbox en tête du tableau desktop ; case équivalente sur chaque carte mobile.
- Case « tout sélectionner » dans le header : ne coche / décoche que les jeux **disponibles** de la page courante (les cases des jeux non disponibles restent `disabled`).
- Jeux `pending` / `loaned` : checkbox visible mais `disabled` (non sélectionnable).
- Barre d’action (sous les filtres de recherche) :
  - bouton **Archiver la sélection (N)** ;
  - visible / activé seulement si N ≥ 1 ;
  - `confirm('Archiver N jeu(x) ?')` avant soumission.
- Les boutons **Archiver** individuels restent inchangés.
- La sélection ne survit pas au changement de page (pagination) — hors périmètre.

## Backend (`src/Controller/Admin/LudothequeController.php`)

- Nouvelle route : `POST /admin/ludotheque/archiver-selection` (`app_admin_ludotheque_archive_bulk`), déclarée **avant** les routes `/{id}/…` (comme `nouveau` / `export-csv`) pour éviter qu’elle soit capturée comme un id.
- CSRF : token id `archive_bulk`.
- Body : `ids[]` (entiers), optionnellement `page` / `q` pour le redirect (mêmes params que `redirectParams`).
- Pour chaque id :
  - charger le `BoardGame` ;
  - ignorer silencieusement (ou compter en « skipped ») si introuvable, déjà archivé, ou statut ≠ `available` ;
  - sinon `setArchived(true)`.
- Un seul `flush()` après la boucle.
- Flash :
  - succès si au moins 1 archivé : « N jeu(x) archivé(s). » ;
  - si 0 archivé et des ids envoyés : message d’erreur explicite (ex. aucun jeu disponible sélectionné).
- Redirect vers `app_admin_ludotheque_index` avec conservation de `q` / `page` si fournis.

## JS minimal

Script inline (ou petit bloc dans le template) :
- écouter le changement des checkboxes `name="ids[]"` ;
- mettre à jour le libellé / l’état du bouton d’action ;
- gérer la case « tout sélectionner » (indeterminate si sélection partielle).

## Hors périmètre

- Suppression définitive en base.
- Sélection multi-pages.
- Archivage des jeux non disponibles.
- Stimulus / fetch AJAX.
- Modification du workflow d’emprunt.

## Tests / vérification

Manuelle sur `/admin/ludotheque` :
1. Cocher 0 jeu → bouton d’action absent ou désactivé.
2. Cocher 2 jeux disponibles → confirmer → les 2 disparaissent de la liste, flash de succès.
3. « Tout sélectionner » ne coche pas les jeux empruntés / en attente.
4. Envoyer manuellement un id non disponible (tamper) → non archivé, pas d’erreur fatale.
5. CSRF invalide → flash erreur, aucun archivage.
6. Vue mobile : checkboxes + barre d’action fonctionnelles.

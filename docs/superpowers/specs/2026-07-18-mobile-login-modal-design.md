# Modale de connexion mobile pour petits écrans — Design

## Contexte

Sur mobile (< lg, 1024px), le bouton **NOUS REJOINDRE** du tiroir de menu (`#mobile-join-btn`) déplie actuellement un formulaire de connexion inline (`#mobile-join-form`) dans le tiroir, via une animation `max-height`. Sur les écrans de faible hauteur (typiquement < 900px), le tiroir + le formulaire déplié peuvent dépasser la hauteur visible de l'écran, rendant le formulaire difficile à utiliser (champs hors-écran, scroll dans le tiroir).

Le site dispose déjà d'une modale plein-écran pour l'inscription (`#register-modal`, dupliquée dans `templates/home/index.html.twig` et `templates/layouts/page.html.twig`, les deux seuls templates qui incluent la navbar/le tiroir mobile). Il n'existe en revanche aucune modale de connexion : la connexion n'existe qu'en formulaire inline (panel desktop `#join-panel` ou accordéon mobile `#mobile-join-form`).

## Objectif

Sur mobile, quand la hauteur de viewport est insuffisante (< 900px), remplacer l'accordéon inline par une modale de connexion (`#login-modal`) au clic sur NOUS REJOINDRE. Rien ne change pour :
- le desktop (≥ 1024px), qui garde `#join-panel` ;
- le mobile avec une hauteur ≥ 900px, qui garde l'accordéon `#mobile-join-form`.

## Condition de seuil

```js
window.innerWidth < 1024 && window.innerHeight < 900
```

Évaluée à chaque clic (pas mise en cache), pour rester correcte après rotation d'écran ou redimensionnement.

## Nouvelle modale `#login-modal`

Structure identique à `#register-modal` (overlay `bg-black/80 backdrop-blur-sm`, carte `max-w-md`, header avec titre + bouton fermer, `z-[9999]`), avec le contenu du formulaire de connexion actuellement dupliqué dans `#join-panel` / `#mobile-join-form` :
- Email (`_username`), mot de passe (`_password`) avec bouton afficher/masquer (`data-toggle-password`)
- Message d'erreur (`login_error`) et pré-remplissage (`last_username`), affichés comme dans les formulaires inline existants
- Bouton submit **SE CONNECTER** (POST vers `app_login`, CSRF token `authenticate`)
- Lien **Mot de passe oublié ?**
- Bouton **S'INSCRIRE** : ferme `#login-modal` puis ouvre `#register-modal` (réutilise l'attribut `data-open-register` déjà géré par `initRegisterModal()`)

Ajoutée dans les deux mêmes templates que `#register-modal` : `templates/home/index.html.twig` et `templates/layouts/page.html.twig`.

## Comportement JS (`assets/join_panel.js`)

1. **Clic sur `#mobile-join-btn`** : si la condition de seuil est vraie, ouvrir `#login-modal` au lieu de déplier `#mobile-join-form`. Sinon, comportement actuel inchangé (toggle accordéon).
2. **Flux `?open=login`** (redirection après échec de connexion, gérée dans `bootJoinPanel()`) : si la condition de seuil est vraie au chargement, ouvrir `#login-modal` (avec le message d'erreur dedans) au lieu de forcer l'ouverture du tiroir + accordéon. Le comportement desktop et mobile ≥ 900px reste inchangé.

Le tiroir mobile n'est pas fermé automatiquement à l'ouverture de la modale — comportement identique à celui du bouton S'INSCRIRE actuel, qui ouvre déjà `#register-modal` par-dessus un tiroir resté ouvert.

`#login-modal` s'ouvre/se ferme via une paire de fonctions (`openLoginModal` / `closeLoginModal`) au même niveau que les fonctions du module, sur le modèle de `openModal`/`closeModal` de `assets/modal.js`. Fermeture : bouton close, clic sur l'overlay, touche Échap (écouteur dédié, comme pour `#register-modal`).

## Hors périmètre

- Pas d'onglets ni de fusion connexion/inscription dans une seule modale.
- Pas de changement du comportement desktop.
- Pas de fermeture automatique du tiroir mobile à l'ouverture de la modale.
- Pas de refactor de la duplication existante entre `home/index.html.twig` et `layouts/page.html.twig`.

## Tests / vérification

Vérification manuelle en redimensionnant le viewport (DevTools ou Playwright) :
- < 1024px large / < 900px haut → clic NOUS REJOINDRE ouvre `#login-modal`
- < 1024px large / ≥ 900px haut → clic NOUS REJOINDRE déplie l'accordéon (comportement actuel)
- ≥ 1024px large → clic NOUS REJOINDRE ouvre le panel desktop (comportement actuel, bouton `#join-btn` seul visible)
- Bouton S'INSCRIRE dans `#login-modal` ferme la modale de connexion et ouvre `#register-modal`
- `?open=login` sur un viewport court ouvre `#login-modal` avec le message d'erreur

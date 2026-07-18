# Pied de page du menu burger (déconnexion) sur écrans courts — Design

## Contexte

Dans le tiroir de menu mobile (`templates/partials/_mobile_menu.html.twig`), quand l'utilisateur est connecté, le bouton **DÉCONNEXION** est rendu dans le flux normal de `<nav>`, juste après les liens de navigation et un `<hr>`. Le texte `© LA BOÎTE À CHIMÈRE` est positionné indépendamment en `absolute bottom-10` par rapport au conteneur du tiroir.

Sur les écrans courts (< 900px de hauteur — même seuil que la modale de connexion mobile déjà en place, cf. `docs/superpowers/specs/2026-07-18-mobile-login-modal-design.md`), le contenu de `<nav>` (liens + bouton déconnexion) peut être proche de, voire dépasser, l'espace disponible avant le texte copyright positionné en absolu, créant un risque de chevauchement ou de contenu poussé hors écran.

## Objectif

Sur mobile, quand `largeur < 1024px && hauteur < 900px` **et que l'utilisateur est connecté**, le bouton DÉCONNEXION doit apparaître directement au-dessus du texte `© LA BOÎTE À CHIMÈRE`, les deux formant un pied de page groupé en bas du tiroir. Au-dessus de 900px de hauteur, la disposition actuelle reste inchangée. Aucun changement pour un utilisateur non connecté (déjà couvert par la modale de connexion existante).

## Condition de seuil

Réutilise exactement la fonction déjà écrite dans `assets/join_panel.js` :

```js
window.innerWidth < 1024 && window.innerHeight < 900
```

`isShortMobileViewport()` est exportée depuis `assets/join_panel.js` et importée dans `assets/mobile_menu.js` pour éviter de dupliquer la logique de seuil.

## Changements de markup (`templates/partials/_mobile_menu.html.twig`)

- Le `<form>` de déconnexion existant reçoit `id="mobile-logout-form"`. Sa position par défaut dans le DOM (à l'intérieur de `<nav>`, après les liens et le `<hr>`) ne change pas — c'est la position utilisée quand la hauteur est ≥ 900px.
- Le bloc copyright existant est transformé en conteneur de pied de page identifiable :

```twig
<div id="mobile-menu-footer" class="absolute bottom-10 left-8 right-8 flex flex-col items-center gap-3 text-center">
    <p class="text-[10px] uppercase tracking-widest text-text-secondary font-bold">&copy; LA BOÎTE À CHIMÈRE</p>
</div>
```

## Comportement JS (`assets/mobile_menu.js`)

À l'ouverture du tiroir (fonction `open()` existante dans `initMobileMenu()`), si `isShortMobileViewport()` est vrai et que `#mobile-logout-form` existe (utilisateur connecté) :
- Déplacer `#mobile-logout-form` dans `#mobile-menu-footer`, en l'insérant avant le `<p>` du copyright (donc visuellement au-dessus).
- Ajouter `flex-1 min-h-0 overflow-y-auto` à `<nav>` pour qu'elle devienne défilable si son contenu restant dépasse l'espace disponible, garantissant que le pied de page (déconnexion + copyright) reste toujours visible et non chevauché.

Si la condition est fausse (hauteur ≥ 900px, ou non connecté), aucun déplacement n'a lieu — le DOM et les classes restent dans leur état par défaut tel que rendu par Twig.

La condition est évaluée à chaque ouverture du tiroir (pas au chargement de page uniquement), pour rester correcte après une rotation d'écran.

## Hors périmètre

- Aucun changement pour un utilisateur non connecté.
- Aucun changement de disposition au-dessus de 900px de hauteur.
- Pas de media-query CSS pure : le déplacement du bouton nécessite de déplacer un vrai nœud DOM (le formulaire avec son token CSRF), pas seulement de changer un style — d'où le choix JS plutôt que CSS, cohérent avec le mécanisme déjà utilisé pour la modale de connexion mobile.

## Tests / vérification

Vérification manuelle via Playwright, connecté, en redimensionnant le viewport :
- < 1024px large / < 900px haut, connecté → ouvrir le tiroir → `#mobile-logout-form` est un enfant de `#mobile-menu-footer`, positionné avant le `<p>` copyright.
- < 1024px large / ≥ 900px haut, connecté → ouvrir le tiroir → `#mobile-logout-form` reste dans `<nav>` à sa position d'origine ; `#mobile-menu-footer` ne contient que le `<p>` copyright.
- < 1024px large / < 900px haut, non connecté → ouvrir le tiroir → aucun changement (pas de `#mobile-logout-form` à déplacer).
- Le bouton DÉCONNEXION déplacé reste fonctionnel (soumet bien vers `app_logout`).

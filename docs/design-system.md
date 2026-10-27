# Système de design « Prime »

Référence du design system de Prime Business Suite. Aucun framework JS :
PHP natif pour les vues, CSS custom properties pour les jetons, JavaScript
vanille ES2024 pour les comportements.

## 1. Jetons de design

Tous les jetons vivent dans `public/assets/css/app.css` (`:root`) :

- **Couleurs** : neutres (`--c-bg`, `--c-surface`, `--c-border`, `--c-ink`,
  `--c-body`, `--c-secondary`, `--c-muted`), un accent unique (`--accent`),
  quatre sémantiques (`--success`, `--warning`, `--danger`, `--info`) avec
  leur version subtile `*-subtle`.
- **Thème sombre** : `:root[data-theme='dark']` ; le thème automatique
  `:root[data-theme='auto']` suit `prefers-color-scheme`. L'attribut
  `data-bs-theme` est synchronisé pour que les composants Bootstrap suivent.
- **Échelle** : espacements 4 / 8 px (`--sp-1` à `--sp-12`), rayons continus
  7 → 20 px (`--r-xs` … `--r-xl`, pilule), ombres multicouches douces
  (`--shadow-xs` … `--shadow-lg`), durées 160 / 220 / 320 ms avec courbes
  `--ease-out` et `--ease-in-out`.
- **Typographie** : pile système (`-apple-system`, SF Pro, Inter, Segoe UI…),
  chiffres tabulaires via `.tnum` (défaut sur les KPI et colonnes monétaires).

Le script inline du `<head>` applique le thème avant le premier rendu : aucun
clignotement, même en thème sombre.

## 2. Bibliothèques (self-hosted, versions figées)

Voir `public/assets/vendor/VERSIONS.md`. Tout est servi depuis
`/assets/vendor/` : Bootstrap 5.3.3 (socle de grille et composants, redécliné
via les jetons), Lucide 1.62.0 (unique jeu d'icônes SVG), Chart.js 4.4.3
(uniquement sur le tableau de bord, chargé à la demande). Aucun CDN runtime :
la CSP ne déclare que `'self'`.

## 3. Icônes

Une seule source de vérité : **Lucide**, rendu en SVG inline.

- Nou code : `<i data-lucide="receipt"></i>` (transformé au chargement et à
  chaque mutation DOM, donc compatible avec les nœuds ajoutés en JS).
- Ancien code : les `<i class="bi bi-…">` historiques sont convertis
  automatiquement par la table `ICON_MAP` de `app.js` — aucune vue n'a besoin
  d'être migrée d'urgence, mais les nouvelles vues utilisent directement
  `data-lucide`.

## 4. Composants principaux

| Besoin | Classe / composant |
| --- | --- |
| Carte KPI | `.kpi-grid` > `.kpi-card` (`.kpi-icon.is-*`, `.kpi-value`, `.kpi-label`, `.kpi-trend`) |
| Carte standard | `.card` / `.card-header` / `.card-body` |
| En-tête de page | `.page-header` > `.page-title` + `.page-subtitle` |
| Actions rapides | `.quick-actions` > `.quick-action` |
| Tableau | `..table` (en-têtes capitalisés, tris via `data-sortable`, recherche via `data-table-target`) |
| Badge de statut | `.badge.text-bg-*` (pastille avec point) |
| État vide | `.empty-state` (`.empty-icon`, `.empty-title`, `.empty-text`, action suggérée) |
| Squelette | `.skeleton`, `.skeleton-text`, `.skeleton-card` |
| Toast | conteneur `#toastRegion` + API `PBSApp.toast(message, type, titre)` |
| Modale de confirmation | `#confirmModal`, déclenchée par `form[data-confirm]` |
| Palette de commandes | `#commandPalette` (Ctrl/⌘ + K), données injectées via `window.PBS_DATA.palette` |
| Formulaires | `.form-control` / `.form-select` / `.form-check`, protection anti-perte via `form[data-guard]` |

## 5. Comportements JavaScript (`public/assets/js/app.js`)

Module unique, initialisé une seule fois sur `DOMContentLoaded` :

- thème auto / clair / sombre persisté (`localStorage`, bascule dans la barre
  haute, événement document `pbs:theme-changed` pour rafraîchir les graphiques) ;
- sidebar : tiroir mobile avec voile, repli en rail sur desktop, persisté ;
- palette de commandes : recherche insensible aux accents, navigation clavier
  (flèches, Entrée, Échap), raccourcis récents mémorisés ;
- compteurs animés `[data-countup]` (respect de `prefers-reduced-motion`) ;
- tri et recherche instantanée des tableaux, toasts, confirmations,
  protection de saisie, formulaires AJAX historiques conservés ;
- aide-mémoire des raccourcis en appuyant sur `?`.

## 6. Accessibilité

- Contraste AA des textes, focus visible unique (`:focus-visible`), cibles
  tactiles d'au moins 38–44 px.
- Navigation clavier complète : sidebar, palette, tableaux triables,
  confirmations, fermeture par Échap.
- Sémantique : `<main>`, `<nav aria-label>`, `aria-current="page"` sur l'entrée
  active, `aria-sort` sur les en-têtes triables, `role="status"`/`alert` sur
  les toasts, libellés explicites sur tous les boutons iconiques.
- `prefers-reduced-motion` désactive toutes les animations ;
  `prefers-color-scheme` est respecté par défaut.

## 7. Extension d'une vue

1. Utiliser les classes ci-dessus et les jetons existants ; ne pas introduire
   de couleur hexadécimale hors de `app.css`.
2. Pour une nouvelle page applicative, les permissions restent déclarées dans
   les routes et la navigation (y compris la palette) se filtre automatiquement.
3. Pour une icône, vérifier son existence dans le paquet Lucide et utiliser
   `data-lucide` ; sinon l'ajouter à `ICON_MAP` si de l'ancien code en dépend.

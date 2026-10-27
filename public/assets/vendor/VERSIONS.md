# Bibliothèques front self-hosted (versions figées)

Toutes les dépendances front sont servies localement : aucun CDN runtime,
aucune dépendance à un réseau tiers. Mises à jour par remplacement manuel du
fichier après vérification de compatibilité.

| Bibliothèque | Version | Fichiers | Rôle | Licence |
| --- | --- | --- | --- | --- |
| Bootstrap | 5.3.3 | `bootstrap/bootstrap.min.css`, `bootstrap/bootstrap.bundle.min.js` | Grille, composants de base (modale, dropdown, collapse), largement redéclinés par `assets/css/app.css` | MIT |
| Lucide | 1.62.0 | `lucide/lucide.min.js` | Unique jeu d'icônes SVG (trait fin, rendu en SVG inline) | ISC |
| Chart.js | 4.4.3 | `chartjs/chart.umd.js` | Unique graphique du tableau de bord (chargé à la demande) | MIT |

Origine des archives : registre npm officiel (`https://registry.npmjs.org`),
paquets `bootstrap@5.3.3`, `lucide@1.62.0`, `chart.js@4.4.3`.

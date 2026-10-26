# 08 — Architecture et conventions de développement

## Principes

- PHP 8.4 natif, orienté objet, **aucun framework backend** (ni Laravel, ni
  Symfony, ni autre) ; aucun framework frontend (vanilla JS ES2024, Bootstrap 5) ;
- PostgreSQL exclusif, PDO, requêtes préparées, transactions, vues et fonctions
  SQL. Pas d'ORM ; un constructeur de requêtes maison est disponible mais
  facultatif (`App\Core\Database\QueryBuilder`) ;
- PSR-1, PSR-4, PSR-12 ; SOLID, DRY, KISS ;
- monolithe **modulaire** : chaque module est indépendant et contient ses
  propres couches.

## Arborescence

```
app/
  Core/              # Noyau technique (HTTP, routage, DI, auth, validation, …)
  Controllers/       # Contrôleurs transversaux (accueil, dashboard, santé)
  Views/             # Mises en page et vues transversales
  Helpers/           # Fonctions globales (e(), csrf_field(), audit(), …)
  Console/Commands/  # Commandes CLI enregistrées dans app/commands.php
config/              # Configuration par domaine, lue via env()
modules/<Module>/
  Controllers/       # Contrôleurs web (et Controllers/Api pour l'API JWT)
  Models/            # Objets métier immuables (fromRow/toArray)
  Repositories/      # Accès données SQL explicites
  Services/          # Logique métier, transactions, règles de gestion
  Events/ Listeners/ # Découplage par événements (EventDispatcher)
  Routes/            # web.php et/ou api.php, chargés automatiquement
  Views/             # Vues du module, rendues via la syntaxe Module::vue
  Tests/             # Tests unitaires et fonctionnels du module
database/
  migrations/        # SQL versionné + vues
  Seeds/             # Seeders idempotents
tests/Core/          # Socle de test (TestCase, DatabaseTestCase, runner)
public/              # Front controller unique, assets, passerelle console dev
bin/                 # console (CLI production) et health.php (healthcheck)
```

## Cycle d'une requête web

```
Requête HTTP
  → public/index.php
  → Kernel (middlewares globaux : SecurityHeaders)
  → Router (résolution méthode/chemin, typage des paramètres numériques)
  → MiddlewarePipeline (auth, csrf, permission, throttle, jwt…)
  → Controller (injection de dépendances via le Container)
  → Service → Repository → Connection PDO
  → ViewRenderer (layout + vue module) ou JsonResponse
```

## Routage

Les fichiers de routes **retournent une fermeture** recevant le `Router` :

```php
return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/invoicing', static function (Router $router): void {
        $router->get('/invoices', [InvoiceController::class, 'index'])
               ->middleware('permission:invoicing.read');
    });
};
```

Les segments de route purement numériques (`{id}`) sont convertis en `int`
par `App\Core\Router\Route`, ce qui permet des signatures `int $id` dans les
contrôleurs.

## Injection de dépendances

`App\Core\Container` résout automatiquement les classes concrètes par
réflexion (constructeur typé). Les singletons du noyau sont déclarés dans
`Application::bootstrap()`. Les services métiers sont résolus à la volée ;
leurs dépendances doivent être des classes concrètes ou des services liés.

## Événements

`app/listeners.php` cartographie les événements vers leurs listeners. Exemple :
l'émission d'une facture (`InvoiceIssued`) déclenche la génération
automatique du document UBL (`GenerateInvoiceDocument`). Les listeners sont
résolus par le conteneur et doivent exposer `handle()`.

## Accès aux données

- héritier de `BaseRepository` en implémentant `table()` ;
- méthodes aux noms métier (`paginateInvoices`, `findDetailed`) pour éviter
  les collisions avec le socle ;
- transactions via `$this->db->transaction(fn () => …)` avec support des
  points de sauvegarde imbriqués ;
- toute écriture multi-tables sensible (facturation, contrats, congés) est
  transactionnelle.

## Audit et traçabilité

Le helper `audit('action', 'Entité', $id, ['champ' => $valeur])` écrit dans
`audit_logs` avec l'utilisateur courant. Les modifications métier sensibles
sont systématiquement tracées (factures, avoirs, contrats, congés, sauvegardes).

## Vues

PHP natif, échappement systématique via `e()`, jetons CSRF via
`csrf_field()`, formats monétaires via `money_format()`. Les vues d'un module
se référencent par `Module::dossier/vue` (ex. `'Invoicing::invoices/show'`).

## File d'attente et planification

- file en mode fichier par défaut (`queue:work`), Redis possible ;
- planificateur maison (`schedule:run`) à appeler périodiquement (cron) ;
- commandes métier récurrentes : `invoices:recurring-run`,
  `invoices:mark-overdue`, `subscriptions:generate`.

## Ajouter un module

1. créer `modules/Nom/` avec les sous-dossiers ci-dessus ;
2. déclarer permissions et rôles dans `RbacSeeder` (migration SQL si nouvelle
   permission en environnement déjà déployé) ;
3. exposer `Routes/web.php` et/ou `Routes/api.php` (chargement automatique par
   glob) ;
4. ajouter les commandes CLI dans `app/commands.php` si besoin ;
5. écrire les tests dans `modules/Nom/Tests/` ;
6. ne jamais introduire de dépendance framework : conserver PHP/PDO natif.

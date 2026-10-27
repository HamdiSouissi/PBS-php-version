# 09 — Tests

## Moteur de tests

PBS embarque un moteur de tests maison compatible avec les conventions PHPUnit
(`tests/Core/TestRunner.php`, assertions dans `tests/Core/TestCase.php`), afin
de fonctionner sans dépendance de framework. PHPUnit reste déclaré en
dépendance de développement dans `composer.json` pour les environnements qui
souhaitent l'utiliser.

## Catégories

| Catégorie | Emplacement | Base réelle ? |
|-----------|-------------|---------------|
| Unitaires | `tests/Unit/**`, `modules/*/Tests/Unit` | Non |
| Fonctionnels / intégration | `modules/*/Tests/Functional`, `tests/Core/DatabaseTestCase` | Oui, `pbs_test` |

`DatabaseTestCase` :

1. se connecte à `PBS_TEST_DB` (défaut `pbs_test`) ;
2. applique les migrations une seule fois par exécution ;
3. avant chaque test, tronque toutes les tables (`TRUNCATE ... CASCADE`) puis
   recharge les seeders RBAC et les données de référence ;
4. isole donc chaque test sur un socle connu et reproductible.

## Exécution

```bash
# Via la console (recommandé, identique en Docker)
php bin/console test:run unit

# Via Composer / PHPUnit quand les dépendances de dev sont installées
composer test
```

Résultat de référence sur la version courante :

```
Total : 30 | Réussis : 30 | Échecs : 0 | Erreurs : 0
```

## Couverture actuelle

- **Sécurité** : aller-retour JWT, expiration, jeton trafiqué rejeté ; secret
  TOTP Base32, code à 6 chiffres, rejet d'un mauvais code ;
- **Validation** : e-mail, SIRET, règle `max/min` (longueur sur chaîne, valeur
  sur entier), confirmations ;
- **Employés / contrats** : matricules séquentiels, unicité du contrat actif,
  clôture du contrat précédent, sortie salarié ;
- **RH / congés** : jours ouvrés lundi-vendredi, contrôle de solde, workflow
  soumission → approbation/rejet, consommation de solde ;
- **Facturation** : calculs TVA et arrondis commerciaux, numérotation
  séquentielle par année, cycle brouillon → émis → partiel → soldé,
  trop-perçu refusé, avoirs, devis et conversion, génération UBL/CII validée
  par parseur XML, idempotence de la boîte d'envoi.

## Ajouter un test

```php
namespace Modules\MonModule\Tests\Functional;

use Tests\Core\DatabaseTestCase;

final class MonServiceTest extends DatabaseTestCase
{
    public function testMonCas(): void
    {
        // given / when / then
        self::assertSame('attendu', $resultat);
    }
}
```

Conventions : nom de classe `*Test.php`, méthodes `test*`, une assertion par
intention, données créées dans le test (la base est tronquée entre deux tests).

## Vérifications manuelles obligatoires

Certaines vérifications dépendent de binaires externes et ne sont pas dans la
suite automatisée :

```bash
# Sauvegarde / restauration (mysqldump et mysql requis dans le PATH)
php bin/console backup:run
php bin/console backup:restore --file=pbs-AAAAMMJJ-HHMMSS.sql.gz

# Sonde de santé
php bin/health.php
```

## Environnements

- les tests ne doivent jamais pointer sur la base de production ;
- la variable `PBS_TEST_DB` ne désigne qu'une base jetable ;
- en CI, monter un conteneur MySQL 8 éphémère (base `pbs_test`,
  `DB_CONNECTION=mysql`) et lancer `migrate:run` + `test:run functional`,
  ou utiliser la suite SQLite par défaut sans service externe.

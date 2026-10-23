# Sylius Pack : conformité et outillage

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Six plugins Sylius 2 pour la conformité et l'administration, installés avec un seul `composer require` : factures Factur-X, données de sécurité des produits GPSR, journal d'audit, chronologie des commandes et des clients, palette de commandes et actions groupées.**

Ce paquet est un métapaquet Composer : il ne contient aucun code. Il requiert les six plugins ci-dessous, avec des bornes de versions qui se résolvent ensemble, pour ne pas les choisir un par un. Il est gratuit et open source.

## Ce que fait un métapaquet, et ce qu'il ne fait pas

- Il fait : une commande installe les six plugins à des versions dont la résolution ensemble est vérifiée.
- Il ne fait pas : déclarer les bundles, importer la configuration et les routes, lancer les migrations ni planifier les tâches. Ces étapes restent manuelles, plugin par plugin, et sont listées dans [Ce qui reste à faire à la main](#ce-qui-reste-à-faire-à-la-main). Tant que la recette Flex du pack n'est pas publiée sur `symfony/recipes-contrib` (voir [Recette Flex](#recette-flex)), `composer require` ne les fait pas.
- Il ne garantit pas votre boutique : l'intégration continue installe le pack dans un projet Sylius vierge, avec les gestes ci-dessous et rien d'autre (voir [Vérifié en CI](#vérifié-en-ci)) ; votre application peut différer.

## Prérequis

- PHP 8.2 ou plus
- Sylius 2.2 ou plus (2.3 pris en charge)
- Le [plugin de facturation officiel de Sylius](https://github.com/Sylius/InvoicingPlugin#installation), installé et configuré avant le plugin Factur-X. Composer l'installe avec le pack ; son bundle, sa configuration et ses routes sont faits par sa recette (voir ci-dessous), ses autres étapes (migrations, moteur PDF) suivent sa documentation.

## Installation

```bash
composer require tmonier/sylius-pack-compliance-tooling
```

Suivre ensuite [Ce qui reste à faire à la main](#ce-qui-reste-à-faire-à-la-main).

## Plugins du pack

| Plugin | Borne | Rôle |
|--------|-------|------|
| [sylius-facturx-plugin](https://github.com/thibmonier/sylius-facturx-plugin) | `^1.1` | Factures Factur-X / ZUGFeRD (EN 16931) sur le plugin de facturation officiel de Sylius |
| [sylius-gpsr-plugin](https://github.com/thibmonier/sylius-gpsr-plugin) | `^1.0` | Informations de sécurité des produits exigées par le règlement européen GPSR |
| [sylius-audit-log-plugin](https://github.com/thibmonier/sylius-audit-log-plugin) | `^1.0` | Journal des modifications de l'administration avec diff par champ |
| [sylius-timeline-plugin](https://github.com/thibmonier/sylius-timeline-plugin) | `^1.0` | Chronologie des commandes et des clients avec notes internes |
| [sylius-command-palette-plugin](https://github.com/thibmonier/sylius-command-palette-plugin) | `^1.1` | Recherche globale Ctrl+K / Cmd+K dans l'administration |
| [sylius-bulk-actions-plugin](https://github.com/thibmonier/sylius-bulk-actions-plugin) | `^1.0` | Actions groupées sur les grilles de l'administration |

Aucun plugin n'est suggéré : aucun des six ne déclare de `suggest` vers un autre plugin de la même famille.

## Ce qui reste à faire à la main

Pour chaque plugin, dans cet ordre (le plugin de facturation avant Factur-X). Les étapes détaillées, avec leurs options, sont dans le README de chaque plugin.

| Plugin | Bundles à déclarer dans `config/bundles.php` | Configuration (`config/packages/`) | Routes (`config/routes/`) | Migrations | Autre |
|--------|-----------------------------------------------|------------------------------------|---------------------------|------------|-------|
| facturx | `Tmonier\SyliusFacturXPlugin\TmonierSyliusFacturXPlugin` | `@TmonierSyliusFacturXPlugin/config/config.yaml` | `@TmonierSyliusFacturXPlugin/config/routes.yaml` | oui | réglages Factur-X et données du vendeur par canal, dans l'administration |
| gpsr | `Tmonier\SyliusGpsrPlugin\TmonierSyliusGpsrPlugin` | `@TmonierSyliusGpsrPlugin/config/config.yaml` | `@TmonierSyliusGpsrPlugin/config/routes.yaml` | oui | créer les personnes responsables et les fabricants, remplir l'onglet de sécurité des produits |
| audit-log | `Tmonier\SyliusAuditLogPlugin\TmonierSyliusAuditLogPlugin` | `@TmonierSyliusAuditLogPlugin/config/config.yaml` | `@TmonierSyliusAuditLogPlugin/config/routes.yaml` | oui | planifier `tmonier:audit-log:purge` |
| timeline | `Tmonier\SyliusTimelinePlugin\TmonierSyliusTimelinePlugin` | `@TmonierSyliusTimelinePlugin/config/config.yaml` | `@TmonierSyliusTimelinePlugin/config/routes.yaml` | oui | `tmonier:timeline:backfill` facultatif pour les commandes existantes |
| command-palette | `Tmonier\SyliusCommandPalettePlugin\TmonierSyliusCommandPalettePlugin` | `@TmonierSyliusCommandPalettePlugin/config/config.yaml` | `@TmonierSyliusCommandPalettePlugin/config/routes.yaml` | non | `bin/console assets:install` |
| bulk-actions | `Tmonier\SyliusBulkActionsPlugin\TmonierSyliusBulkActionsPlugin` | `@TmonierSyliusBulkActionsPlugin/config/config.yaml` | `@TmonierSyliusBulkActionsPlugin/config/routes.yaml` | non | aucun |

Total pour le pack : 6 bundles, 6 imports de configuration, 6 imports de routes, 4 jeux de migrations et 2 tâches planifiées ou ponctuelles (purge, assets). Le bundle, la configuration et les routes du plugin de facturation sont faits par sa recette Symfony Flex (section suivante).

### Fait par la recette du plugin de facturation

Dans un projet qui utilise Symfony Flex (Sylius-Standard en utilise), `composer require` exécute la recette de `sylius/invoicing-plugin` : elle déclare `Sylius\InvoicingPlugin\SyliusInvoicingPlugin` avant le plugin Factur-X et importe `@SyliusInvoicingPlugin/config/config.yaml` et `@SyliusInvoicingPlugin/config/routes.yaml`. Ne pas les répéter. Sans recettes Flex, les ajouter à la main, comme le décrit la documentation du plugin de facturation. L'intégration continue vérifie que la recette a bien été exécutée (`bin/apply-gestures --verify-flex`).

### Bundles

```php
// config/bundles.php
Tmonier\SyliusFacturXPlugin\TmonierSyliusFacturXPlugin::class => ['all' => true],
Tmonier\SyliusGpsrPlugin\TmonierSyliusGpsrPlugin::class => ['all' => true],
Tmonier\SyliusAuditLogPlugin\TmonierSyliusAuditLogPlugin::class => ['all' => true],
Tmonier\SyliusTimelinePlugin\TmonierSyliusTimelinePlugin::class => ['all' => true],
Tmonier\SyliusCommandPalettePlugin\TmonierSyliusCommandPalettePlugin::class => ['all' => true],
Tmonier\SyliusBulkActionsPlugin\TmonierSyliusBulkActionsPlugin::class => ['all' => true],
```

### Configuration et routes

Un fichier par plugin, sous la forme ci-dessous (remplacer l'alias par celui du tableau) :

```yaml
# config/packages/tmonier_sylius_gpsr.yaml
imports:
    - { resource: "@TmonierSyliusGpsrPlugin/config/config.yaml" }
```

```yaml
# config/routes/tmonier_sylius_gpsr.yaml
tmonier_sylius_gpsr:
    resource: "@TmonierSyliusGpsrPlugin/config/routes.yaml"
```

### Migrations

```bash
bin/console doctrine:migrations:migrate
```

## Recette Flex

Le dossier `recipe/` contient la recette Symfony Flex de ce pack, dans la structure de `symfony/recipes-contrib` : un manifeste qui déclare les bundles du tableau ci-dessus et copie les fichiers de configuration et de routes. **Elle n'est pas encore publiée.** Flex ne trouve que les recettes publiées sur `symfony/recipes-contrib` : tant qu'elle ne l'est pas, `composer require` ne déclare rien et les gestes ci-dessus restent nécessaires. Une pull request sur ce dépôt public n'est ouverte qu'avec l'accord du mainteneur.

Ce qui est testé :

- Flex applique une recette à un métapaquet : la CI sert la recette depuis un endpoint local (`bin/build-recipe-endpoint`), requiert le pack dans un projet Sylius vierge **sans appliquer les gestes**, puis rejoue les contrôles du job `install` (job `recipe`, Sylius-Standard 2.2.4 et 2.3.0).
- `bin/flex-trial` rejoue, dans un projet Symfony jetable, l'installation, le retrait (`composer remove` retire les bundles et les fichiers écrits par la recette) et le cas d'un fichier déjà présent dans l'application (il n'est pas écrasé).
- `bin/check-recipe` échoue quand la recette diffère de `etc/gestures.json` : les bundles et les fichiers viennent de la même source que les gestes.

Limite de Flex, observée à l'essai : il consigne dans `symfony.lock` les fichiers de la recette, y compris un fichier que l'application avait déjà écrit sous le même nom, et le supprime à `composer remove`. Garder un tel fichier sous un autre nom ou sous gestion de version. La recette ne lance pas les migrations et ne fait pas les étapes de la colonne « Autre ».

```bash
php tests/recipe.php               # tests de la recette et de l'endpoint
bin/check-recipe                   # recette contre etc/gestures.json
bin/flex-trial                     # rejoue l'essai (php, composer et réseau nécessaires)
bin/build-recipe-endpoint --out=DIR # le JSON que Flex lit depuis un endpoint
```

## Vérifications

`bin/check-composition` vérifie que :

- les plugins requis par `composer.json` sont exactement les six membres listés dans `etc/composition.json` ;
- aucun plugin exclu des packs n'apparaît dans `composer.json` ni dans un README ;
- avec `--lock`, après une résolution Composer, aucun plugin verrouillé ne requiert un plugin absent du pack (l'échec nomme la dépendance).

`bin/check-gestures` vérifie que les plugins de `etc/gestures.json` sont exactement les membres, et que chaque bundle et chaque ressource de configuration ou de routes de ce fichier figure dans les deux README, sans autre.

```bash
php tests/run.php                  # tests du vérificateur
bin/check-composition              # composition de composer.json et des README
bin/check-composition --lock=composer.lock
```

L'intégration continue résout aussi le pack dans un projet jetable (`composer update --no-install`) avec Sylius 2.2 et 2.3, et l'installe (voir ci-dessous).

## Vérifié en CI

Les gestes ci-dessus ne sont pas seulement décrits : ils sont appliqués par `bin/apply-gestures` à partir de `etc/gestures.json`, et `bin/check-gestures` échoue quand l'un des deux README décrit d'autres gestes que ce fichier.

Le job `install` crée un projet `sylius/sylius-standard` vierge (2.2.4 avec Sylius `~2.2.9`, et 2.3.0 avec Sylius `~2.3.0`), ajoute ce dépôt comme dépôt Composer de type `path` (le pack n'est pas republié pour l'essai), le requiert, applique les gestes, lance les migrations, puis `lint:container` et `doctrine:schema:validate`. Un échec nomme le plugin et l'étape : `FAIL plugin=… step=…` (`bin/step`). Le job `install` du [pack IA et headless](https://github.com/thibmonier/sylius-pack-ai-headless) installe aussi les deux packs dans le même projet et compile le conteneur (aucun conflit de service ni de route).

```bash
php tests/gestures.php             # tests des gestes, de leur contrôle contre les README et de l'enveloppe d'étape
bin/check-gestures                 # etc/gestures.json contre les deux README
bin/apply-gestures --project=DIR   # applique les gestes à un projet Symfony
```

Deux ajustements sont écrits dans le workflow plutôt que cachés. Le squelette 2.2.4 déclare `FOSRestBundle` et son fichier de configuration alors que Sylius 2.2.12 n'installe plus ce paquet : le job retire les deux quand le paquet est absent. Le schéma d'un projet Sylius-Standard vierge est lui-même désynchronisé après ses migrations (tables des bundles Mollie, de remboursement, de demande de paiement et de messenger) : `doctrine:schema:validate --skip-sync` vérifie donc le mapping, et la synchronisation n'est contrôlée que sur les tables du pack.

Ce que le job ne couvre pas : la colonne « Autre » du tableau (réglages Factur-X, tâches planifiées, assets), le moteur PDF du plugin de facturation, PHP 8.2 (Sylius-Standard exige PHP 8.3, donc le job tourne sur PHP 8.3 alors que les plugins du pack acceptent PHP 8.2) et Sylius 2.2 avant 2.2.9.

## Maintenance

Une nouvelle version majeure d'un membre demande de relire sa borne ici et de rejouer les vérifications. Un plugin qui requiert un plugin hors du pack ne peut pas y entrer.

## Licence

MIT. Voir [LICENSE](LICENSE).

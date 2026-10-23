# Sylius Pack : conformité et outillage

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Six plugins Sylius 2 pour la conformité et l'administration, installés avec un seul `composer require` : factures Factur-X, données de sécurité des produits GPSR, journal d'audit, chronologie des commandes et des clients, palette de commandes et actions groupées.**

Ce paquet est un métapaquet Composer : il ne contient aucun code. Il requiert les six plugins ci-dessous, avec des bornes de versions qui se résolvent ensemble, pour ne pas les choisir un par un. Il est gratuit et open source.

## Ce que fait un métapaquet, et ce qu'il ne fait pas

- Il fait : une commande installe les six plugins à des versions dont la résolution ensemble est vérifiée.
- Il ne fait pas : déclarer les bundles, importer la configuration et les routes, lancer les migrations ni planifier les tâches. Ces étapes restent manuelles, plugin par plugin, et sont listées dans [Ce qui reste à faire à la main](#ce-qui-reste-à-faire-à-la-main). Aucune recette Symfony Flex n'existe encore pour ce pack.
- Il ne garantit pas une boutique qui démarre : la composition est vérifiée par la résolution de Composer (voir [Vérifications](#vérifications)), pas par une installation complète.

## Prérequis

- PHP 8.2 ou plus
- Sylius 2.2 ou plus (2.3 pris en charge)
- Le [plugin de facturation officiel de Sylius](https://github.com/Sylius/InvoicingPlugin#installation), installé et configuré avant le plugin Factur-X. Composer l'installe avec le pack ; ses propres étapes (bundle, configuration, routes, migrations, moteur PDF) restent manuelles.

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
| facturx | `Sylius\InvoicingPlugin\SyliusInvoicingPlugin` (déjà configuré), puis `Tmonier\SyliusFacturXPlugin\TmonierSyliusFacturXPlugin` | `@TmonierSyliusFacturXPlugin/config/config.yaml` | `@TmonierSyliusFacturXPlugin/config/routes.yaml` | oui | réglages Factur-X et données du vendeur par canal, dans l'administration |
| gpsr | `Tmonier\SyliusGpsrPlugin\TmonierSyliusGpsrPlugin` | `@TmonierSyliusGpsrPlugin/config/config.yaml` | `@TmonierSyliusGpsrPlugin/config/routes.yaml` | oui | créer les personnes responsables et les fabricants, remplir l'onglet de sécurité des produits |
| audit-log | `Tmonier\SyliusAuditLogPlugin\TmonierSyliusAuditLogPlugin` | `@TmonierSyliusAuditLogPlugin/config/config.yaml` | `@TmonierSyliusAuditLogPlugin/config/routes.yaml` | oui | planifier `tmonier:audit-log:purge` |
| timeline | `Tmonier\SyliusTimelinePlugin\TmonierSyliusTimelinePlugin` | `@TmonierSyliusTimelinePlugin/config/config.yaml` | `@TmonierSyliusTimelinePlugin/config/routes.yaml` | oui | `tmonier:timeline:backfill` facultatif pour les commandes existantes |
| command-palette | `Tmonier\SyliusCommandPalettePlugin\TmonierSyliusCommandPalettePlugin` | `@TmonierSyliusCommandPalettePlugin/config/config.yaml` | `@TmonierSyliusCommandPalettePlugin/config/routes.yaml` | non | `bin/console assets:install` |
| bulk-actions | `Tmonier\SyliusBulkActionsPlugin\TmonierSyliusBulkActionsPlugin` | `@TmonierSyliusBulkActionsPlugin/config/config.yaml` | `@TmonierSyliusBulkActionsPlugin/config/routes.yaml` | non | aucun |

Total pour le pack : 7 bundles (le plugin de facturation peut déjà être déclaré), 6 imports de configuration, 6 imports de routes, 4 jeux de migrations et 2 tâches planifiées ou ponctuelles (purge, assets).

### Bundles

```php
// config/bundles.php
Sylius\InvoicingPlugin\SyliusInvoicingPlugin::class => ['all' => true],
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

## Vérifications

`bin/check-composition` vérifie que :

- les plugins requis par `composer.json` sont exactement les six membres listés dans `etc/composition.json` ;
- aucun plugin exclu des packs n'apparaît dans `composer.json` ni dans un README ;
- avec `--lock`, après une résolution Composer, aucun plugin verrouillé ne requiert un plugin absent du pack (l'échec nomme la dépendance).

```bash
php tests/run.php                  # tests du vérificateur
bin/check-composition              # composition de composer.json et des README
bin/check-composition --lock=composer.lock
```

L'intégration continue résout aussi le pack dans un projet jetable (`composer update --no-install`) avec Sylius 2.2 et 2.3.

## Maintenance

Une nouvelle version majeure d'un membre demande de relire sa borne ici et de rejouer les vérifications. Un plugin qui requiert un plugin hors du pack ne peut pas y entrer.

## Licence

MIT. Voir [LICENSE](LICENSE).

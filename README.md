# Sylius Pack: compliance and tooling

> Français : [README.fr.md](README.fr.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Six Sylius 2 plugins for compliance and for the admin panel, installed with one `composer require`: Factur-X invoices, GPSR product safety data, an audit log, an order and customer timeline, a command palette and bulk actions.**

This package is a Composer metapackage: it contains no code. It requires the six plugins below, with version bounds that resolve together, so that you do not choose them one by one. It is free and open source.

## What a metapackage does, and does not do

- It does: one command installs the six plugins at versions known to resolve together.
- It does not: declare the bundles, import the configuration and the routes, run the migrations or schedule the tasks. Those steps stay manual, plugin by plugin, and are listed in [What is left to do by hand](#what-is-left-to-do-by-hand). No Symfony Flex recipe exists yet for this pack.
- It does not guarantee a running shop: the composition is checked by Composer resolution (see [Checks](#checks)), not by a full installation.

## Requirements

- PHP 8.2 or later
- Sylius 2.2 or later (2.3 supported)
- The [Sylius Invoicing Plugin](https://github.com/Sylius/InvoicingPlugin#installation), installed and configured before the Factur-X plugin. Composer installs it with the pack; its own steps (bundle, configuration, routes, migrations, PDF engine) stay manual.

## Installation

```bash
composer require tmonier/sylius-pack-compliance-tooling
```

Then follow [What is left to do by hand](#what-is-left-to-do-by-hand).

## Plugins in the pack

| Plugin | Bound | Role |
|--------|-------|------|
| [sylius-facturx-plugin](https://github.com/thibmonier/sylius-facturx-plugin) | `^1.1` | Factur-X / ZUGFeRD (EN 16931) invoices on the official Sylius Invoicing Plugin |
| [sylius-gpsr-plugin](https://github.com/thibmonier/sylius-gpsr-plugin) | `^1.0` | Product safety information required by the EU General Product Safety Regulation |
| [sylius-audit-log-plugin](https://github.com/thibmonier/sylius-audit-log-plugin) | `^1.0` | Journal of admin changes with a diff per field |
| [sylius-timeline-plugin](https://github.com/thibmonier/sylius-timeline-plugin) | `^1.0` | Order and customer timeline with internal notes |
| [sylius-command-palette-plugin](https://github.com/thibmonier/sylius-command-palette-plugin) | `^1.1` | Ctrl+K / Cmd+K global search in the admin |
| [sylius-bulk-actions-plugin](https://github.com/thibmonier/sylius-bulk-actions-plugin) | `^1.0` | Bulk actions on the admin grids |

No plugin is suggested: none of the six has a `suggest` towards another plugin of the same family.

## What is left to do by hand

For each plugin, in this order (the Invoicing Plugin before Factur-X). The detailed steps, with their options, are in the README of each plugin.

| Plugin | Bundles to register in `config/bundles.php` | Configuration (`config/packages/`) | Routes (`config/routes/`) | Migrations | Other |
|--------|----------------------------------------------|------------------------------------|---------------------------|------------|-------|
| facturx | `Sylius\InvoicingPlugin\SyliusInvoicingPlugin` (already configured), then `Tmonier\SyliusFacturXPlugin\TmonierSyliusFacturXPlugin` | `@TmonierSyliusFacturXPlugin/config/config.yaml` | `@TmonierSyliusFacturXPlugin/config/routes.yaml` | yes | Factur-X settings and seller data per channel in the admin |
| gpsr | `Tmonier\SyliusGpsrPlugin\TmonierSyliusGpsrPlugin` | `@TmonierSyliusGpsrPlugin/config/config.yaml` | `@TmonierSyliusGpsrPlugin/config/routes.yaml` | yes | create responsible persons and manufacturers, fill the product safety tab |
| audit-log | `Tmonier\SyliusAuditLogPlugin\TmonierSyliusAuditLogPlugin` | `@TmonierSyliusAuditLogPlugin/config/config.yaml` | `@TmonierSyliusAuditLogPlugin/config/routes.yaml` | yes | schedule `tmonier:audit-log:purge` |
| timeline | `Tmonier\SyliusTimelinePlugin\TmonierSyliusTimelinePlugin` | `@TmonierSyliusTimelinePlugin/config/config.yaml` | `@TmonierSyliusTimelinePlugin/config/routes.yaml` | yes | optional `tmonier:timeline:backfill` for existing orders |
| command-palette | `Tmonier\SyliusCommandPalettePlugin\TmonierSyliusCommandPalettePlugin` | `@TmonierSyliusCommandPalettePlugin/config/config.yaml` | `@TmonierSyliusCommandPalettePlugin/config/routes.yaml` | no | `bin/console assets:install` |
| bulk-actions | `Tmonier\SyliusBulkActionsPlugin\TmonierSyliusBulkActionsPlugin` | `@TmonierSyliusBulkActionsPlugin/config/config.yaml` | `@TmonierSyliusBulkActionsPlugin/config/routes.yaml` | no | none |

Totals for the pack: 7 bundles (the Invoicing Plugin may already be registered), 6 configuration imports, 6 route imports, 4 sets of migrations and 2 scheduled or one-off tasks (purge, assets).

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

### Configuration and routes

One file per plugin, in the form below (replace the alias with the one of the table):

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

## Checks

`bin/check-composition` verifies that:

- the plugins required by `composer.json` are exactly the six members listed in `etc/composition.json`;
- no plugin excluded from the packs appears in `composer.json` or in a README;
- with `--lock`, after a Composer resolution, no locked plugin requires a plugin that is not in the pack (the failure names the dependency).

```bash
php tests/run.php                  # tests of the checker
bin/check-composition              # composition of composer.json and of the READMEs
bin/check-composition --lock=composer.lock
```

The continuous integration also resolves the pack in a throwaway project (`composer update --no-install`) with Sylius 2.2 and 2.3.

## Maintenance

A new major version of a member requires reviewing its bound here and running the checks again. A plugin that requires a plugin outside the pack cannot join it.

## License

MIT. See [LICENSE](LICENSE).

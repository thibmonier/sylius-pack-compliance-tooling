# Sylius Pack: compliance and tooling

> Français : [README.fr.md](README.fr.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-pack-compliance-tooling/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Six Sylius 2 plugins for compliance and for the admin panel, installed with one `composer require`: Factur-X invoices, GPSR product safety data, an audit log, an order and customer timeline, a command palette and bulk actions.**

This package is a Composer metapackage: it contains no code. It requires the six plugins below, with version bounds that resolve together, so that you do not choose them one by one. It is free and open source.

## What a metapackage does, and does not do

- It does: one command installs the six plugins at versions known to resolve together.
- It does not: declare the bundles, import the configuration and the routes, run the migrations or schedule the tasks. Those steps stay manual, plugin by plugin, and are listed in [What is left to do by hand](#what-is-left-to-do-by-hand). No Symfony Flex recipe exists yet for this pack.
- It does not guarantee your shop: the pack is installed in a blank Sylius project by the continuous integration, with the gestures below and nothing else (see [Verified in CI](#verified-in-ci)); your own application may differ.

## Requirements

- PHP 8.2 or later
- Sylius 2.2 or later (2.3 supported)
- The [Sylius Invoicing Plugin](https://github.com/Sylius/InvoicingPlugin#installation), installed and configured before the Factur-X plugin. Composer installs it with the pack; its bundle, configuration and routes are done by its recipe (see below), its other steps (migrations, PDF engine) follow its documentation.

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
| facturx | `Tmonier\SyliusFacturXPlugin\TmonierSyliusFacturXPlugin` | `@TmonierSyliusFacturXPlugin/config/config.yaml` | `@TmonierSyliusFacturXPlugin/config/routes.yaml` | yes | Factur-X settings and seller data per channel in the admin |
| gpsr | `Tmonier\SyliusGpsrPlugin\TmonierSyliusGpsrPlugin` | `@TmonierSyliusGpsrPlugin/config/config.yaml` | `@TmonierSyliusGpsrPlugin/config/routes.yaml` | yes | create responsible persons and manufacturers, fill the product safety tab |
| audit-log | `Tmonier\SyliusAuditLogPlugin\TmonierSyliusAuditLogPlugin` | `@TmonierSyliusAuditLogPlugin/config/config.yaml` | `@TmonierSyliusAuditLogPlugin/config/routes.yaml` | yes | schedule `tmonier:audit-log:purge` |
| timeline | `Tmonier\SyliusTimelinePlugin\TmonierSyliusTimelinePlugin` | `@TmonierSyliusTimelinePlugin/config/config.yaml` | `@TmonierSyliusTimelinePlugin/config/routes.yaml` | yes | optional `tmonier:timeline:backfill` for existing orders |
| command-palette | `Tmonier\SyliusCommandPalettePlugin\TmonierSyliusCommandPalettePlugin` | `@TmonierSyliusCommandPalettePlugin/config/config.yaml` | `@TmonierSyliusCommandPalettePlugin/config/routes.yaml` | no | `bin/console assets:install` |
| bulk-actions | `Tmonier\SyliusBulkActionsPlugin\TmonierSyliusBulkActionsPlugin` | `@TmonierSyliusBulkActionsPlugin/config/config.yaml` | `@TmonierSyliusBulkActionsPlugin/config/routes.yaml` | no | none |

Totals for the pack: 6 bundles, 6 configuration imports, 6 route imports, 4 sets of migrations and 2 scheduled or one-off tasks (purge, assets). The bundle, the configuration and the routes of the Invoicing Plugin are done by its Symfony Flex recipe (next section).

### Done by the recipe of the Invoicing Plugin

In a project that uses Symfony Flex (Sylius-Standard does), `composer require` runs the recipe of `sylius/invoicing-plugin`: it registers `Sylius\InvoicingPlugin\SyliusInvoicingPlugin` before the Factur-X plugin and imports `@SyliusInvoicingPlugin/config/config.yaml` and `@SyliusInvoicingPlugin/config/routes.yaml`. Do not repeat them. Without Flex recipes, add them by hand, as the documentation of the Invoicing Plugin describes. The continuous integration checks that the recipe ran (`bin/apply-gestures --verify-flex`).

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

`bin/check-gestures` verifies that the plugins of `etc/gestures.json` are exactly the members, and that every bundle and every configuration or routes resource of that file is in both READMEs and that no other one is.

```bash
php tests/run.php                  # tests of the checker
bin/check-composition              # composition of composer.json and of the READMEs
bin/check-composition --lock=composer.lock
```

The continuous integration also resolves the pack in a throwaway project (`composer update --no-install`) with Sylius 2.2 and 2.3, and installs it (see below).

## Verified in CI

The gestures above are not only described: they are applied by `bin/apply-gestures` from `etc/gestures.json`, and `bin/check-gestures` fails when one of the two READMEs describes other gestures than that file.

The `install` job creates a blank `sylius/sylius-standard` project (2.2.4 with Sylius `~2.2.9`, and 2.3.0 with Sylius `~2.3.0`), adds this repository as a Composer `path` repository (the pack is not republished for the test), requires it, applies the gestures, runs the migrations, then `lint:container` and `doctrine:schema:validate`. A failure names the plugin and the step: `FAIL plugin=… step=…` (`bin/step`). The `install` job of the [AI and headless pack](https://github.com/thibmonier/sylius-pack-ai-headless) also installs both packs in the same project and compiles the container (no service or route conflict).

```bash
php tests/gestures.php             # tests of the gestures, of their README check and of the step wrapper
bin/check-gestures                 # etc/gestures.json against both READMEs
bin/apply-gestures --project=DIR   # applies the gestures to a Symfony project
```

What the job does not cover: the "Other" column of the table (Factur-X settings, scheduled tasks, assets), the PDF engine of the Invoicing Plugin, PHP 8.2 (Sylius-Standard requires PHP 8.3, so the job runs on PHP 8.3 although the plugins of the pack accept PHP 8.2), and Sylius 2.2 before 2.2.9.

## Maintenance

A new major version of a member requires reviewing its bound here and running the checks again. A plugin that requires a plugin outside the pack cannot join it.

## License

MIT. See [LICENSE](LICENSE).

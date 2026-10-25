---
title: The Docker harness
order: 70
---

## The Docker harness

`docker/` composes this core and every provider (`docker/harness/plugins.php`) into one
installation, PHP 8.4 with GD:

    docker compose run --rm omnimap test       # every package's tests, no network
    docker compose run --rm omnimap live       # the checks that reach OpenStreetMap, within its policies
    docker compose run --rm omnimap bare       # the bare script: plain PHP, and what PHP loaded
    docker compose run --rm omnimap phpstan    # PHPStan at the highest level, each package
    docker compose down -v

Providers come from GitHub (branch 1.x) unless `OMNIMAP_PLUGINS` (in `docker/.env`) points at a
directory holding their checkouts - `../..` when this core sits in the workspace beside them.

### What it is made of

| File | |
|---|---|
| `docker/Dockerfile` | `php:8.4-cli` with GD (PNG, JPEG, WebP, FreeType), git, unzip and Composer 2; image `omniscient/omnimap-harness` |
| `docker/compose.yml` | one service, `omnimap`, on Docker's default bridge (no network of its own); mounts the core at `/omnimap/core`, the providers' checkouts read-only at `/omnimap/plugins`, and a volume `harness` at `/harness` (the composed installation and Composer's cache) |
| `docker/.env` | `OMNIMAP_PLUGINS=../..`: the checkouts beside the core |
| `docker/harness/plugins.php` | the providers installed beside the core and their tests' namespaces: `google`, `openstreetmap`, `maplibre` |
| `docker/harness/setup.php` | writes `/harness/composer.json` and `/harness/phpunit.xml`: the core and each provider as path repositories (symlinked) when their checkout is there, else a git clone of `https://github.com/glitchr-studio/omnimap-<name>.git`; the dev tools (PHPUnit 11, PHPStan 2, symfony/http-client and cache, nyholm/psr7, Flysystem and its memory adapter, omnidrive, opis/json-schema); one test suite per package; `/harness/packages/<name>` linked to each provider for PHPStan. Files are rewritten only when they change |
| `docker/entrypoint.sh` | runs `setup.php`, then `composer install` (no `vendor/`) or `composer update` (`composer.json` newer than the lock), then the command |
| `docker/harness/bin/bare` | the plain-PHP script `Tests/BareTest.php` runs |

### Commands

| Command | |
|---|---|
| `test [phpunit arguments]` | PHPUnit over every package, the group `live` excluded; arguments go to PHPUnit (`test --filter Signer`, `test --testdox /scratch/tests`) |
| `live [phpunit arguments]` | the group `live` only: an image of Kyoto from tile.openstreetmap.org and one Nominatim query (omnimap/openstreetmap), OpenFreeMap's style, tiles, glyphs and sprite and unpkg's MapLibre hashes (omnimap/maplibre) |
| `bare` | `docker/harness/bin/bare`: a view of Kyoto with two markers described, its picture made of tiles answered by `PlainTiles`, signed, cut into a directory, read back and printed, made again from cached tiles, deleted, Kyoto geocoded twice; prints what happened and every class and file PHP loaded, as JSON |
| `phpstan` | PHPStan on the core and on each package that has a `phpstan.neon`, at its baseline |
| `composer ...`, `php ...`, `sh` | as they are, in `/harness` |

### Running a script of one's own

The examples of this manual were run this way: put the script in a directory, mount it, and
require the harness's autoloader.

```sh
cd docker
docker compose run --rm -v "$PWD/../scratch:/scratch" omnimap php /scratch/example.php
```

```php
<?php

require '/harness/vendor/autoload.php';   // in the harness; __DIR__.'/vendor/autoload.php' in an application
```

`--rm` removes the container after the run; the `harness` volume stays (the installation is reused
by the next run) until `docker compose down -v`.

Run on 2026-10-25: `test` 92 tests, 568 assertions, green (PHP 8.4.26, PHPUnit 11.5.57).

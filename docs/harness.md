# The harness

`docker/` composes this core and every provider (`docker/harness/plugins.php`) into one
installation, PHP 8.4 with GD:

    docker compose run --rm omnimap test       # every package's tests, no network
    docker compose run --rm omnimap live       # the checks that reach OpenStreetMap, within its policies
    docker compose run --rm omnimap bare       # the bare script: plain PHP, and what PHP loaded
    docker compose run --rm omnimap phpstan    # PHPStan at the highest level, each package
    docker compose down -v

Providers come from GitHub (branch 1.x) unless `OMNIMAP_PLUGINS` (in `docker/.env`) points at a
directory holding their checkouts - `../..` when this core sits in the workspace beside them.

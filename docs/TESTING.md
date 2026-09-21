# Tests

Les tests tournent sur une base **PostgreSQL + PostGIS dédiée** (`ouego_test`, port 5435),
jamais sur la base du `.env` (Railway). PostGIS est requis par les migrations, SQLite n'est pas utilisable.

## Lancer

```bash
docker compose -f docker-compose.test.yml up -d --wait   # ou: composer test:db
composer test            # tout
composer test:unit       # tests/Unit
composer test:feature    # tests/Feature (intégration : HTTP + base)
php artisan test --filter=NomDuTest
```

## Conventions

- `Tests\TestCase` applique `RefreshDatabase` : chaque test est isolé dans une transaction.
- Un garde-fou dans `setUp()` refuse de tourner si l'hôte n'est pas local ou si le nom de base ne finit pas par `_test`.
- Test **unitaire pur** (sans base, sans app) : étendre `PHPUnit\Framework\TestCase`.
- Test **d'intégration** (routes API, services + base) : étendre `Tests\TestCase` dans `tests/Feature`, données via factories (`database/factories`).
- Services externes (Firebase, SMS, Google Maps) : à mocker (`Http::fake()`, `Event::fake()`, `Queue::fake()`, Mockery).

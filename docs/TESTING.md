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

## Non-régression

La CI (`.github/workflows/tests.yml`) lance toute la suite sur chaque PR et push vers `develop` / `main`,
avec la même base PostGIS que `docker-compose.test.yml`. Une PR dont un test échoue ne doit pas être fusionnée
(à rendre obligatoire dans GitHub : *Settings → Branches → Require status checks → `phpunit`*).

- **Filet large** : `tests/Feature/Regression/ApiRoutesSmokeTest` appelle chaque route `api/*` (corps vide,
  identifiants inexistants, avec le bon type d'utilisateur) et échoue si l'une répond en 5xx.
  Toute nouvelle route est couverte automatiquement ; une exclusion se justifie dans `SKIPPED`.
- **Parcours critiques** :
  - `Feature/Auth/OtpAuthenticationFlowTest` : OTP → jeton → profil → refresh → logout (client et chauffeur) ;
  - `Feature/Orders/OrderDeliveryLifecycleTest` : acceptation → enlèvement → livraison → règlement chauffeur → note ;
  - `Feature/Payments/DriverWalletPaymentFlowTest` : dépôt / retrait et webhook de paiement (crédit unique, échecs) ;
  - création, estimation, confirmation, annulation : `Feature/Orders/Aggregats/*`.
- **Règle** : chaque bug corrigé arrive avec le test qui l'aurait détecté.

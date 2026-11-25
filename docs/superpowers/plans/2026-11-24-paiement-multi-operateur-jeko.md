# Paiement multi-opérateur (Jèko) — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** Encaisser les commandes en ligne et les recharges de portefeuille chauffeur via un opérateur de paiement
actif (Jèko en premier), avec confirmation vérifiée, rattrapage, et remboursement automatique.

**Architecture :** Un contrat `PaymentGateway` implémenté par `JekoGateway`, résolu par `PaymentGatewayManager`
(opérateur actif lu dans `settings`, opérateur d'origine pour un paiement existant). Toute la logique métier
(facture payée, recherche de chauffeur, crédit portefeuille, remboursement) vit dans `PaymentService` ; les
contrôleurs, le webhook et la commande `payments:reconcile` ne font qu'appeler ce service.

**Tech Stack :** PHP 8.3, Laravel 10, PostgreSQL/PostGIS, file `database`, Reverb, PHPUnit (`Http::fake()`).

**Spec :** `docs/superpowers/specs/2026-11-24-paiement-multi-operateur-jeko-design.md` (à lire avant chaque tâche).

## Global Constraints

- Tests : `composer test:db` puis `php artisan test --filter=…` ; base `ouego_test` uniquement (`docs/TESTING.md`).
  Tout appel HTTP externe est simulé (`Http::preventStrayRequests()` + `Http::fake()`).
- Messages d'API en **français**, format `sendResponse` / `sendError` d'`AppBaseController`.
- Jèko : base `https://api.jeko.africa`, en-têtes `X-API-KEY` + `X-API-KEY-ID`, `amountCents` = XOF × 100,
  `currency` = `XOF`, `reference` 5–100 caractères, `paymentDetails.type` = `redirect`,
  moyens `wave|orange|mtn|moov|djamo|jeko`, plafond 2 000 000 F par transaction.
- Webhook Jèko : `Jeko-Signature` = `hash_hmac('sha256', <corps brut>, <secret>)` en hex minuscule, comparé avec
  `hash_equals`. En-tête `Jeko-Event` ; seul `TRANSACTION_COMPLETED` nous intéresse.
- Montant d'un `Payment` = `(int) ceil(invoice.total)`.
- « Commande en ligne » = `payment_method_code !== 'cash'` (`Order::isPaidOnline()`).
- Settings : `PAYMENT_ACTIVE_PROVIDER` (ex. `jeko`), `ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES` (défaut 30).
- Secrets uniquement en variables d'environnement : `JEKO_API_KEY`, `JEKO_API_KEY_ID`, `JEKO_STORE_ID`,
  `JEKO_WEBHOOK_SECRET`, `JEKO_BASE_URL` ; deep links `APP_PAYMENT_RETURN_DEEPLINK_CUSTOMER`,
  `APP_PAYMENT_RETURN_DEEPLINK_DRIVER`.
- Ne jamais supprimer un `Payment` lié à Jèko ; seul l'ancien webhook des retraits garde son `forceDelete`.
- **Aucun commit sans l'accord explicite de l'utilisateur** : les étapes « commit » proposent le message et attendent son feu vert.
- Une commande / facture peut être modifiée hors API (dashboard, même base) : les garanties reposent sur
  `payments:reconcile`, pas sur des observers.

## Review Focus

1. **Webhook et polling concurrents** sur le même paiement → un seul crédit / un seul lancement de recherche.
   Test : Task 4 `concurrent_success_applies_effects_once` (deux `applyStatus` successifs ; le verrou + l'état
   final garantissent l'unicité).
2. **Paiement confirmé après annulation de la commande** → pas de recherche de chauffeur, remboursement créé.
   Test : Task 9 `a_payment_confirmed_after_cancellation_is_refunded`.
3. **Client qui relance `pay` plusieurs fois** (double tap, retour arrière) → un seul paiement en attente
   réutilisé, pas deux payment requests Jèko. Test : Task 5 `paying_twice_reuses_the_pending_payment`.
4. **Jèko injoignable ou en erreur** lors de `pay` / `deposit` → 502 propre en français, aucune facture modifiée,
   paiement marqué `FAILED`. Test : Task 5 `a_gateway_error_returns_502_and_marks_the_payment_failed`.
5. **Bascule d'opérateur pendant un paiement en attente** → le paiement est encore confirmé par son opérateur
   d'origine. Test : Task 6 `a_payment_from_a_deactivated_provider_is_still_confirmed`.

---

### Task 1 : Schéma et modèles

**Files :**
- Create : `database/migrations/2026_11_24_000001_add_gateway_columns_to_payments_table.php`
- Create : `database/migrations/2026_11_24_000002_create_payment_refunds_table.php`
- Create : `app/Models/PaymentRefund.php`
- Modify : `app/Models/Payment.php`, `app/Models/Invoice.php`, `app/Models/Order.php`
- Test : `tests/Feature/Payments/PaymentModelTest.php`

**Interfaces :**
- Produces :
  - colonnes `payments` : `provider` string null, `provider_payment_id` string null indexé, `payment_url` text null,
    `expires_at` timestamp null, `last_checked_at` timestamp null, `check_attempts` int défaut 0 ;
    index unique sur `payment_reference` (avant d'ajouter la contrainte, la migration renomme les doublons
    existants en suffixant `-{id}`).
  - table `payment_refunds` : `id`, `payment_id` (unique), `order_id` null, `provider`, `amount` int,
    `currency_code` défaut `XOF`, `reference` unique, `provider_refund_id` null, `recipient_phone` null,
    `recipient_method` null, `status`, `attempts` int défaut 0, `next_attempt_at` timestamp null,
    `last_error` text null, timestamps.
  - `PaymentRefund` : constantes `PENDING`, `PROCESSING`, `SUCCESSFUL`, `FAILED` ; `belongsTo payment`.
  - `Payment::generateReference(): string` → `'OUEGO-' . Str::ulid()` (32 caractères).
  - `Payment` : fillable des nouvelles colonnes, casts `expires_at`/`last_checked_at` en datetime ;
    `isFinal(): bool` (SUCCESSFUL, FAILED, CANCELLED, EXPIRED) ; relation `refund(): HasOne`.
  - `Invoice::REFUNDED = "REFUNDED"`.
  - `Order::isPaidOnline(): bool` → `$this->payment_method_code !== self::PAYMENT_MODE_CASH`.

- [ ] **Step 1 : tests** — `PaymentModelTest` :
  `generated_references_are_unique_and_prefixed` (100 références distinctes, `str_starts_with('OUEGO-')`,
  longueur entre 5 et 100) ; `duplicate_payment_reference_is_rejected` (2ᵉ insert même référence →
  `QueryException`) ; `only_one_refund_per_payment` (2ᵉ `PaymentRefund` même `payment_id` → `QueryException`) ;
  `is_paid_online_is_true_for_everything_but_cash` (`cash` → false ; `online`, `mobile-money` → true).
- [ ] **Step 2 :** `php artisan test --filter=PaymentModelTest` → FAIL.
- [ ] **Step 3 :** migrations + modèles ci-dessus.
- [ ] **Step 4 :** `php artisan test --filter=PaymentModelTest` → PASS ; `composer test` → tout vert.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): colonnes opérateur, table des remboursements`.

### Task 2 : Couche opérateur (contrat, DTO, manager, configuration)

**Files :**
- Create : `app/Payments/Contracts/PaymentGateway.php`
- Create : `app/Payments/Data/GatewayCheckout.php`, `GatewayPaymentStatus.php`, `GatewayWebhookEvent.php`,
  `GatewayRefundResult.php`
- Create : `app/Payments/Exceptions/PaymentProviderUnavailable.php`, `InvalidWebhookSignature.php`,
  `GatewayException.php`
- Create : `app/Payments/PaymentGatewayManager.php`, `config/payments.php`
- Modify : `app/Providers/AppServiceProvider.php` (singleton du manager), `.env.example`
- Test : `tests/Unit/Payments/PaymentGatewayManagerTest.php` (étend `Tests\TestCase`, utilise la base pour `Setting`)

**Interfaces :**
- Produces :
  ```php
  interface PaymentGateway {
      public function code(): string;
      public function isConfigured(): bool;
      /** @return list<array{code:string,label:string}> */
      public function supportedMethods(): array;
      public function maxAmount(): int;                       // en XOF
      public function createPayment(Payment $payment, string $method, string $successUrl, string $errorUrl): GatewayCheckout;
      public function fetchPaymentStatus(Payment $payment): GatewayPaymentStatus;
      public function parseWebhook(Request $request): GatewayWebhookEvent; // lève InvalidWebhookSignature
      public function refund(PaymentRefund $refund, Payment $payment): GatewayRefundResult;
      public function fetchRefundStatus(PaymentRefund $refund): GatewayRefundResult;
  }
  ```
  DTO `final readonly` :
  - `GatewayCheckout(string $providerPaymentId, string $paymentUrl, ?CarbonInterface $expiresAt = null)`
  - `GatewayPaymentStatus(string $status, ?int $amount = null, ?string $payerPhone = null, ?string $payerMethod = null, array $raw = [])`
    avec constantes `PENDING='pending'`, `SUCCESSFUL='successful'`, `FAILED='failed'`.
  - `GatewayWebhookEvent(string $event, ?string $providerPaymentId, ?string $reference)`
  - `GatewayRefundResult(string $status, ?string $providerRefundId = null, ?string $error = null)` ; `$status` ∈
    `PaymentRefund::PROCESSING|SUCCESSFUL|FAILED`.
  - `GatewayException` porte un message destiné aux logs ; les contrôleurs renvoient un message français générique.
  - `PaymentGatewayManager::active(): PaymentGateway` (lève `PaymentProviderUnavailable` si setting vide, code
    inconnu de `config('payments.providers')`, ou `!isConfigured()`) ; `driver(string $code): PaymentGateway`
    (lève `PaymentProviderUnavailable` si inconnu) ; `activeCode(): ?string`.
  - `config/payments.php` : `active_setting => 'PAYMENT_ACTIVE_PROVIDER'`, `providers.jeko` (`class`,
    `base_url`, `api_key`, `api_key_id`, `store_id`, `webhook_secret`, `max_amount` = 2000000, `timeout` = 15),
    `return_deeplinks.customers|drivers`, `pending_ttl_minutes` = 30, `refund_max_attempts` = 5.

- [ ] **Step 1 : tests** — avec un faux opérateur enregistré dans `config('payments.providers.fake')` (classe
  anonyme / `tests/Support/FakePaymentGateway.php` implémentant le contrat, `isConfigured()` paramétrable) :
  `active_throws_when_no_provider_is_set` ; `active_throws_for_an_unknown_provider` ;
  `active_throws_when_the_provider_is_not_configured` ; `active_returns_the_configured_provider` ;
  `driver_resolves_a_provider_even_when_it_is_not_active`.
- [ ] **Step 2 :** `php artisan test --filter=PaymentGatewayManagerTest` → FAIL.
- [ ] **Step 3 :** implémenter. `tests/Support/FakePaymentGateway.php` est conservé : les tâches 4 à 9 s'en servent
  pour tester la logique métier sans HTTP (méthodes de pilotage : `willReturnStatus(GatewayPaymentStatus)`,
  `willFailCreate()`, `willReturnRefund(GatewayRefundResult)`, compteurs `createCalls`, `refundCalls`).
  Ajouter `"Tests\\Support\\": "tests/Support/"` à l'autoload-dev si absent.
- [ ] **Step 4 :** PASS.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): contrat opérateur et gestionnaire de l'opérateur actif`.

### Task 3 : JekoGateway (encaissement, statut, webhook)

**Files :**
- Create : `app/Payments/Gateways/JekoGateway.php`
- Test : `tests/Unit/Payments/JekoGatewayTest.php` (étend `Tests\TestCase`, `Http::fake()`)

**Interfaces :**
- Consumes : contrat et DTO de la Task 2.
- Produces : `JekoGateway` (code `jeko`) ; `refund` / `fetchRefundStatus` lèvent `GatewayException('not implemented')`
  jusqu'à la Task 9.

Correspondances :
- `createPayment` → `POST {base}/partner_api/payment_requests`, corps
  `{storeId, amountCents: amount*100, currency: 'XOF', reference: payment_reference, paymentDetails: {type: 'redirect', data: {paymentMethod, successUrl, errorUrl}}}` ;
  réponse `id` → `providerPaymentId`, `redirectUrl` → `paymentUrl`. Réponse non-2xx ou sans `redirectUrl` →
  `GatewayException`.
- `fetchPaymentStatus` → `GET {base}/partner_api/payment_requests/{provider_payment_id}` ; `status` `pending|success|error`
  → `PENDING|SUCCESSFUL|FAILED` ; `amount` = `transaction.amount.amount` converti en XOF entier (vérifier
  dans la réponse réelle si l'unité est le franc ou le centime — voir étape 3) ; `payerPhone` =
  `transaction.counterpartIdentifier` ; `payerMethod` = `paymentMethod`.
- `parseWebhook` → signature sur `$request->getContent()` ; absente / fausse → `InvalidWebhookSignature` ;
  `event` = en-tête `Jeko-Event` ; `providerPaymentId` = `transactionDetails.id` ; `reference` =
  `transactionDetails.reference`.
- `supportedMethods()` : Wave, Orange Money, MTN MoMo, Moov Money, Djamo (codes `wave`, `orange`, `mtn`, `moov`,
  `djamo`).

- [ ] **Step 1 : tests** — `creates_a_payment_request_with_amount_in_cents` (assertSent : URL, en-têtes
  `X-API-KEY`/`X-API-KEY-ID`, `amountCents` = 237500*100, `reference`, `paymentMethod` = `wave`, URLs) et
  retourne `redirectUrl` ; `a_failed_creation_throws_a_gateway_exception` (HTTP 422) ;
  `maps_success_error_and_pending_statuses` (dataProvider) ; `a_valid_signature_is_accepted` (corps brut signé
  avec le secret) ; `a_tampered_body_is_rejected` ; `a_missing_signature_is_rejected` ;
  `is_not_configured_without_keys`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter avec `Http::baseUrl()->withHeaders()->timeout()`. Avant d'écrire le mapping du
  montant, lire `https://developer.jeko.africa/docs/payments/checkout` (exemple de réponse `transaction.amount`) et
  le noter en commentaire.
- [ ] **Step 4 :** PASS.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): opérateur Jèko (encaissement, statut, webhook signé)`.

### Task 4 : PaymentService — démarrage et application idempotente d'un statut (dépôt chauffeur)

**Files :**
- Create : `app/Payments/PaymentService.php`, `app/Services/OrderDispatchService.php`,
  `app/Events/PaymentStatusUpdated.php`, `app/Payments/Exceptions/PaymentRefused.php`
- Modify : `app/Http/Controllers/API/OrderAPIController.php` (`confirm` délègue à `OrderDispatchService`),
  `app/Http/Controllers/API/DriverAPIController.php` (`depositBalance`), `routes/api.php`
- Test : `tests/Feature/Payments/DriverWalletPaymentFlowTest.php` (réécrire les tests de dépôt),
  `tests/Feature/Payments/PaymentServiceTest.php`

**Interfaces :**
- Consumes : Tasks 1–3.
- Produces :
  - `OrderDispatchService::startDriverLookup(Order $order, string $actorTable, ?int $actorId): void` — corps
    actuel de `confirm` : `is_draft=false`, `order_date=now()`, statut `PERFORMER_LOOKUP`, historique,
    `DriverAssignmentService::sendInvitations($order, 10)`.
  - `PaymentService::start(Invoice $invoice, int $userId, string $method, string $userTable): Payment`
    — `$userTable` ∈ `customers|drivers` (choisit le deep link de retour). Comportement :
    1. montant = `ceil(invoice.total)` ; si > `maxAmount()` → `ValidationException`-like : lève
       `PaymentRefused` (nouvelle exception, message « Montant trop élevé pour le paiement en ligne, veuillez
       payer en espèces ») ;
    2. `$method` doit être dans `supportedMethods()` sinon `PaymentRefused('Moyen de paiement non supporté')` ;
    3. réutilise un `Payment` de la facture en `PENDING`, même `provider`, `expires_at` futur → le renvoie ;
    4. sinon crée `Payment` (`provider` = actif, `PENDING`, `expires_at` = now + `pending_ttl_minutes`), appelle
       `createPayment` avec `successUrl`/`errorUrl` = `route('payments.return', [reference, 'success'|'error'])`,
       enregistre `provider_payment_id`, `payment_url` ; `GatewayException` → paiement `FAILED`,
       `payment_gateway_error_message`, relance `GatewayException`.
    5. total = 0 → `Payment` `provider = null`, puis `applyStatus` SUCCESSFUL directement (pas d'appel opérateur).
  - `PaymentService::applyStatus(Payment $payment, GatewayPaymentStatus $status): void` — dans
    `DB::transaction`, recharge le paiement avec `lockForUpdate()` ; si `isFinal()` → rien. `PENDING` → met à jour
    `last_checked_at` seulement. `FAILED` → paiement `FAILED`, `is_waiting=false`, `is_completed=true`,
    transaction de dépôt `FAILED`. `SUCCESSFUL` → si `status->amount !== null && status->amount < payment.amount`
    → `Log::critical`, rien d'autre ; sinon paiement `SUCCESSFUL` (+ `payment_gateway_buyer_reference` =
    payerPhone, `payment_gateway_payment_method` = payerMethod), facture `PAID` + `is_paid_by_customer=true`, puis
    `applySuccessEffects()`.
  - `applySuccessEffects` (privée) : facture `order_source = 'transactions'` → `Transaction` `SUCCESSFUL` +
    `Driver::creditBalance(amount)` pour un dépôt ; commande → Task 5 ajoute la branche commande.
  - `PaymentService::refresh(Payment $payment): void` — `driver($payment->provider)->fetchPaymentStatus` puis
    `applyStatus` ; incrémente `check_attempts` ; si toujours `PENDING` et `expires_at` passé → `EXPIRED`.
  - `PaymentStatusUpdated` (`ShouldBroadcast`, `ShouldDispatchAfterCommit`) sur `private-customers.{id}` ou
    `private-drivers.{id}` selon la facture, payload `{payment_id, reference, status, invoice_id, order_id?}` ;
    émis à chaque passage à un état final.
  - Route `GET payments/methods` (publique) → `supportedMethods()` de l'opérateur actif ; 503 si aucun.
  - `POST drivers/transactions/balance/deposit` accepte `payment_method` (requis, string) ; réponse = `Payment`
    avec `payment_url`. `PaymentProviderUnavailable` → 503 « Le paiement en ligne est momentanément indisponible » ;
    `GatewayException` → 502 « Le service de paiement n'a pas répondu, veuillez réessayer » ; `PaymentRefused` → 400.

- [ ] **Step 1 : tests** — dans `DriverWalletPaymentFlowTest` (avec `FakePaymentGateway` actif) remplacer les
  tests de dépôt basés sur l'ancien webhook par : `a_deposit_returns_a_payment_url_without_touching_the_balance`
  ; `a_successful_status_credits_the_driver_once` (deux `applyStatus` SUCCESSFUL → solde 6000) ;
  `a_failed_status_leaves_the_balance_and_keeps_the_payment` (paiement `FAILED`, non supprimé) ;
  `a_short_paid_amount_is_not_validated` (amount 4000 sur 5000 → facture `UNPAID`, solde 1000) ;
  `a_deposit_without_active_provider_returns_503` ; `a_deposit_with_an_unsupported_method_returns_400` ;
  `a_gateway_error_returns_502_and_marks_the_payment_failed`. Garder intacts les tests de retrait.
  Dans `PaymentServiceTest` : `concurrent_success_applies_effects_once` ; `an_expired_pending_payment_is_marked_expired`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter ; `confirm` appelle `OrderDispatchService::startDriverLookup` (comportement
  inchangé, tests de `confirm` existants verts).
- [ ] **Step 4 :** `php artisan test --filter='DriverWalletPaymentFlowTest|PaymentServiceTest'` → PASS ;
  `composer test` vert.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): recharge portefeuille via l'opérateur actif`.

### Task 5 : Paiement d'une commande en ligne et blocage de la recherche

**Files :**
- Modify : `app/Payments/PaymentService.php` (branche commande), `app/Http/Controllers/API/OrderAPIController.php`
  (`pay`, `confirm`, `performDriverLookup`)
- Test : `tests/Feature/Payments/OrderOnlinePaymentTest.php` (réécrit),
  `tests/Feature/Orders/OrderDeliveryLifecycleTest.php` (helper `confirmedOrder`)

**Interfaces :**
- Consumes : `PaymentService::start/applyStatus`, `OrderDispatchService::startDriverLookup`, `Order::isPaidOnline`.
- Produces :
  - `PUT orders/{id}/pay` : `payment_method` requis ; 400 si commande cash (« Cette commande se paie en
    espèces »), annulée/terminée (« Cette commande ne peut plus être payée »), facture `PAID` (existant) ;
    sinon `PaymentService::start(..., 'customers')`. Mêmes codes d'erreur que la Task 4.
  - Branche commande de `applySuccessEffects` : recharge la commande ; si elle est annulée
    (`CANCELLED`, `CANCELLED_WITH_PAYMENT`) → ne lance rien (le remboursement est ajouté en Task 9) ; sinon si
    `is_draft` ou statut `INITIATED` → `OrderDispatchService::startDriverLookup($order, 'system', null)` +
    notification client « Paiement reçu, recherche de chauffeur en cours » (`CustomerNotification::create` +
    `CustomerNotificationCreated`, comme `RoutePointAPIController`).
  - `confirm` et `perform_driver_lookup` : si `isPaidOnline()` et facture non `PAID` → 400 « Veuillez d'abord
    payer la commande ».

- [ ] **Step 1 : tests** (`FakePaymentGateway` actif) :
  `the_payment_amount_is_the_rounded_invoice_total` (total 237500 → amount 237500 ; un total à 237499.4 →
  237500) ; `paying_twice_reuses_the_pending_payment` (`createCalls` = 1) ;
  `a_successful_payment_starts_the_driver_lookup_once` (statut `PERFORMER_LOOKUP`, historique unique,
  `sendInvitations` appelé une fois — mocker `DriverAssignmentService`) ;
  `an_unpaid_online_order_cannot_be_confirmed` ; `an_unpaid_online_order_cannot_trigger_a_driver_lookup` ;
  `a_mobile_money_order_is_treated_as_online` ; `a_cash_order_cannot_be_paid_online` ;
  `an_amount_above_the_cap_is_refused` (`maxAmount` du fake = 100000) ;
  `a_zero_total_order_is_paid_without_calling_the_provider` (`createCalls` = 0, recherche lancée) ;
  `a_paid_invoice_cannot_be_paid_again` (conservé) ;
  `a_gateway_error_returns_502_and_marks_the_payment_failed`.
  Dans `OrderDeliveryLifecycleTest::confirmedOrder('online')`, marquer la facture `PAID` avant `confirm`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter.
- [ ] **Step 4 :** `composer test` vert.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(orders): paiement en ligne avant la recherche de chauffeur`.

### Task 6 : Webhook Jèko, ancien webhook restreint aux retraits, page de retour

**Files :**
- Create : `app/Http/Controllers/API/PaymentWebhookController.php`,
  `app/Http/Controllers/PaymentReturnController.php`, `resources/views/payments/return.blade.php`
- Modify : `routes/api.php`, `routes/web.php`, `app/Http/Controllers/API/PaymentAPIController.php` (`webhook`)
- Test : `tests/Feature/Payments/JekoWebhookTest.php`, `tests/Feature/Payments/DriverWalletPaymentFlowTest.php`
  (tests retrait / ancien webhook)

**Interfaces :**
- Consumes : `PaymentGatewayManager::driver`, `PaymentService::refresh`.
- Produces :
  - `POST api/v1/payments/webhook/{provider}` (nom `payments.webhook.provider`) : opérateur inconnu → 404 ;
    `InvalidWebhookSignature` → 401 ; événement autre que `TRANSACTION_COMPLETED` → 200 ignoré ; `Payment` trouvé
    par `provider_payment_id` (sinon `payment_reference`) **et** `provider` = `{provider}`, sinon 200 + `Log::warning` ;
    puis `PaymentService::refresh($payment)` ; toute `GatewayException` → `Log::error` et 200 (le polling conclura).
    Réponse `{"status":"OK"}`.
  - `GET payments/return/{reference}/{result}` (web, nom `payments.return`) : vue HTML « Paiement reçu » /
    « Paiement non abouti », redirection JS + lien vers le deep link
    (`config('payments.return_deeplinks.{customers|drivers}')` + `?reference=…&result=…`) ; aucun effet sur les
    données ; référence inconnue → même page sans deep link.
  - Ancien `POST api/v1/payments/webhook` : si la facture du paiement n'est pas une `Transaction`
    `TYPE_WITHDRAWAL` → `{"status":"KO","message":"nothing"}`, rien n'est modifié. Comportement retrait inchangé.
    Supprimer la route `GET payments/webhook` (vérifier d'abord avec l'équipe / les logs Railway qu'aucun appel
    GET n'arrive ; si c'est le cas, la garder et le noter dans la PR).

- [ ] **Step 1 : tests** — `JekoWebhookTest` (`Http::fake` sur `GET …/payment_requests/*` renvoyant `success`) :
  `a_signed_webhook_confirms_the_payment` ; `an_unsigned_webhook_is_rejected_with_401_and_changes_nothing` ;
  `a_webhook_for_an_unknown_payment_returns_200` ; `a_duplicated_webhook_credits_once` ;
  `the_webhook_trusts_the_provider_status_not_the_body` (corps signé `status: success` mais GET renvoie `pending`
  → facture `UNPAID`) ; `a_payment_from_a_deactivated_provider_is_still_confirmed` (setting changé vers `fake`
  après création). `DriverWalletPaymentFlowTest` : `the_legacy_webhook_cannot_validate_a_deposit` ;
  `the_legacy_webhook_cannot_validate_an_order_payment` ; tests de retrait existants verts.
  `PaymentReturnTest` : `the_return_page_redirects_to_the_customer_deeplink_and_changes_nothing`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter ; le webhook n'a pas de middleware d'auth ni de throttle strict.
- [ ] **Step 4 :** `composer test` vert (dont `ApiRoutesSmokeTest` : nouvelle route sans 5xx avec corps vide).
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): webhook Jèko signé et ancien webhook limité aux retraits`.

### Task 7 : Rattrapage planifié (`payments:reconcile`) et expiration des commandes non payées

**Files :**
- Create : `app/Console/Commands/ReconcilePayments.php`
- Modify : `app/Console/Kernel.php`, `database/seeders/SettingsSeeder.php`
- Test : `tests/Feature/Payments/ReconcilePaymentsTest.php`

**Interfaces :**
- Consumes : `PaymentService::refresh`.
- Produces : commande `payments:reconcile`, planifiée `everyMinute()->withoutOverlapping(10)`. Étapes :
  1. paiements `PENDING` avec `provider` non null, dus selon le backoff : `last_checked_at` null, ou ancienneté
     < 10 min et dernier contrôle ≥ 1 min, ou sinon dernier contrôle ≥ 5 min ; max 200 par exécution ;
     `refresh()` chacun, une erreur n'arrête pas les suivants.
  2. commandes en ligne `INITIATED`, non annulées, facture `UNPAID`, créées depuis plus de
     `ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES` (défaut 30) et **sans** paiement `PENDING` non expiré → statut
     `CANCELLED`, `is_completed=true`, historique `system`, notification client « Commande annulée : paiement non
     reçu ».
  (L'étape remboursements est ajoutée en Task 9.)

- [ ] **Step 1 : tests** — `a_pending_payment_reported_failed_is_marked_failed` ;
  `a_missed_success_is_applied` ; `a_payment_past_expiry_is_expired` ;
  `recently_checked_payments_are_skipped` ; `an_unpaid_online_order_is_cancelled_after_the_timeout` ;
  `an_order_with_a_live_pending_payment_is_not_cancelled` ; `cash_orders_are_never_cancelled_by_reconcile` ;
  `one_provider_error_does_not_stop_the_run`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter ; ajouter `ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES=30` au `SettingsSeeder`.
- [ ] **Step 4 :** PASS, `composer test` vert.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): rattrapage planifié et expiration des commandes non payées`.

### Task 8 : « Aucun chauffeur trouvé » devient non terminal et relançable

**Files :**
- Modify : `app/Jobs/ProcessPendingOrderAssignments.php` (`markAsNotFound`), `app/Models/Order.php`
  (`scopeInProgress`), `app/Http/Controllers/API/OrderAPIController.php` (`performDriverLookup`)
- Test : `tests/Feature/Assignment/PerformerNotFoundTest.php`

**Interfaces :**
- Produces :
  - `markAsNotFound` : `status = PERFORMER_NOT_FOUND`, `is_waiting = false`, **`is_completed` reste `false`**, pas de
    `is_successful=false` ; le reste inchangé.
  - `scopeInProgress` : retirer `PERFORMER_NOT_FOUND` de la liste d'exclusion.
  - `perform_driver_lookup` sur une commande `PERFORMER_NOT_FOUND` (et payée si en ligne) →
    `OrderDispatchService::startDriverLookup($order, 'customers', $customer->id)` (statut `PERFORMER_LOOKUP`,
    historique, invitations).
  - Avant d'écrire, lister tous les lecteurs de `is_completed` et `PERFORMER_NOT_FOUND`
    (`grep -rn "is_completed\|PERFORMER_NOT_FOUND" app`) et vérifier que `ProcessPendingOrderAssignments`,
    les assignations nuit / Express et `ReleaseUnstartedExpressOrders` **ne reprennent pas** automatiquement une
    commande `PERFORMER_NOT_FOUND` ; ajouter un filtre explicite si besoin.

- [ ] **Step 1 : tests** — `a_not_found_order_stays_open` ; `the_customer_can_relaunch_a_not_found_order`
  (statut `PERFORMER_LOOKUP`, invitations envoyées) ; `a_paid_online_not_found_order_relaunches_without_new_payment`
  (aucun nouveau `Payment`) ; `a_not_found_order_is_not_picked_up_by_the_assignment_job` ;
  `a_not_found_order_blocks_account_deletion` ; `a_not_found_order_can_be_cancelled`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter.
- [ ] **Step 4 :** `composer test` vert (mettre à jour les snapshots / tests existants qui supposaient
  `is_completed=true`, en le signalant dans le commit).
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(orders): une commande sans chauffeur reste ouverte et relançable`.

### Task 9 : Remboursements automatiques

**Files :**
- Create : `app/Jobs/ProcessRefund.php`
- Modify : `app/Payments/PaymentService.php`, `app/Payments/Gateways/JekoGateway.php` (`refund`,
  `fetchRefundStatus`), `app/Http/Controllers/API/OrderAPIController.php` (`cancel`),
  `app/Console/Commands/ReconcilePayments.php`
- Test : `tests/Feature/Payments/OrderRefundTest.php`, `tests/Unit/Payments/JekoGatewayTest.php`

**Interfaces :**
- Consumes : tout ce qui précède.
- Produces :
  - `PaymentService::requestRefund(Order $order): ?PaymentRefund` — si la commande est en ligne et a un `Payment`
    `SUCCESSFUL` avec `provider` non null : crée (ou retrouve, grâce à l'unicité `payment_id`) un `PaymentRefund`
    `PENDING`, `amount` = `payment.amount`, `reference` = `'RF-' . payment_reference`, `recipient_phone` /
    `recipient_method` depuis `payment_gateway_buyer_reference` / `payment_gateway_payment_method` ; commande →
    `CANCELLED_WITH_PAYMENT` ; facture → `REFUNDED` ; dispatch `ProcessRefund` après commit. Renvoie `null` sinon.
  - Appelé par : `cancel` (après la mise à jour existante) ; la branche commande de `applySuccessEffects` quand la
    commande est déjà annulée ; `payments:reconcile` (étape 3 : commandes en ligne `CANCELLED` dont le paiement est
    `SUCCESSFUL` sans `PaymentRefund`, max 100 par exécution).
  - `ProcessRefund(int $refundId)` : verrou sur le remboursement ; si `PENDING` → `driver(provider)->refund()` ;
    met à jour `status`, `provider_refund_id`, `attempts++`, `last_error`, `next_attempt_at` (backoff 5, 15, 30,
    60 min). Échec avec `attempts >= refund_max_attempts` (5) → `FAILED` + `Log::critical('Remboursement en échec', …)`.
    Succès → `SUCCESSFUL` + notification client « Votre paiement de X F a été remboursé ».
  - `payments:reconcile` étape 4 : remboursements `PROCESSING` → `fetchRefundStatus` ; remboursements
    `PENDING` dont `next_attempt_at` est passé → redispatch `ProcessRefund`.
  - `JekoGateway::refund` : lire d'abord `https://developer.jeko.africa/docs/transfers/integration` et
    `/docs/transfers/beneficiary` ; créer le contact Mobile Money (`POST /partner_api/contacts`) depuis
    `recipient_phone` + `recipient_method`, puis `POST /partner_api/transfers` avec `storeId`, le contact,
    le montant, `reference`. HTTP 409 → considérer le transfert déjà créé et relire son statut.
    `fetchRefundStatus` mappe les statuts de transfert vers `PROCESSING|SUCCESSFUL|FAILED`. Les noms exacts des
    champs viennent de la doc ; les reporter dans les tests `Http::fake`.

- [ ] **Step 1 : tests** — `OrderRefundTest` (`FakePaymentGateway`) :
  `cancelling_a_paid_online_order_refunds_the_full_amount` (refund `amount` = payment.amount, commande
  `CANCELLED_WITH_PAYMENT`, facture `REFUNDED`, `refundCalls` = 1) ; `cancelling_twice_refunds_once` ;
  `cancelling_an_unpaid_online_order_creates_no_refund` ; `cancelling_a_cash_order_creates_no_refund` ;
  `a_payment_confirmed_after_cancellation_is_refunded` (pas de `PERFORMER_LOOKUP`) ;
  `an_order_cancelled_directly_in_database_is_refunded_by_reconcile` ;
  `a_failed_refund_is_retried_then_marked_failed_with_an_alert` (`Log::spy`, 5 tentatives) ;
  `a_processing_refund_is_completed_by_reconcile`. `JekoGatewayTest` :
  `refund_creates_a_contact_then_a_transfer` ; `a_conflict_on_transfer_reads_the_existing_status`.
- [ ] **Step 2 :** FAIL.
- [ ] **Step 3 :** implémenter.
- [ ] **Step 4 :** `composer test` vert.
- [ ] **Step 5 :** proposer à l'utilisateur le commit `feat(payments): remboursement automatique des commandes payées annulées`.

### Task 10 : Mise en service

**Files :**
- Modify : `README.md` ou `docs/PAIEMENTS.md` (créer), `.env.example`

- [ ] **Step 1 :** rédiger `docs/PAIEMENTS.md` : variables d'environnement, activation (`PAYMENT_ACTIVE_PROVIDER`),
  URL du webhook à déclarer dans le Dashboard Jèko (`https://<api>/api/v1/payments/webhook/jeko`), secret à
  copier une seule fois, test en production avec un store dédié et un petit montant, procédure en cas de
  remboursement `FAILED`, ajout d'un nouvel opérateur (classe + entrée `config/payments.php`).
- [ ] **Step 2 :** `composer test` vert ; `php artisan route:list --path=payments` montre les routes attendues.
- [ ] **Step 3 :** proposer à l'utilisateur le commit `docs(payments): mise en service du paiement Jèko`.

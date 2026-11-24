# Paiement multi-opérateur (Jèko en premier) — conception

Date : 2026-11-24 · Statut : en revue

## 1. Intention

Aujourd'hui, le paiement en ligne n'est branché à aucun opérateur : `PUT orders/{id}/pay` et
`POST drivers/transactions/balance/deposit` créent un `Payment` en attente, sans lien de paiement, et un webhook
générique non signé (`payments/webhook`) valide les factures sur simple appel.

OUEGO veut :

- encaisser réellement **le paiement des commandes en ligne** et **la recharge du portefeuille chauffeur** ;
- via une **couche multi-opérateur** où **un seul opérateur est actif à la fois**, et que l'on peut basculer
  sans redéploiement ;
- **Jèko** (jeko.africa) comme premier opérateur.

## 2. Objectifs, non-objectifs, anti-objectifs

**Objectifs**

1. Contrat commun `PaymentGateway` + implémentation `JekoGateway`.
2. Opérateur actif choisi dans `settings` ; les paiements déjà créés restent traités par leur opérateur d'origine.
3. Commande `online` : **payée avant la recherche de chauffeur** ; la confirmation du paiement lance la recherche.
4. Recharge portefeuille chauffeur : crédit du solde uniquement sur paiement confirmé par l'opérateur.
5. Annulation d'une commande payée : **remboursement automatique, montant intégral, frais à la charge d'OUEGO**,
   via l'opérateur.
6. Robustesse : webhooks signés, idempotence, rattrapage par polling, statut des remboursements suivi et relancé.

**Non-objectifs**

- Retrait du portefeuille chauffeur (`balance/withdraw`) : inchangé, hors périmètre. Il reste finalisé
  **uniquement** par l'ancien webhook générique, seule source de vérité pour les retraits (voir 5.4).
- Plusieurs opérateurs actifs simultanément, ou choix de l'opérateur par le client.
- Solde / avoir client.
- Interface d'administration (le projet n'en a pas) ; la bascule se fait en écrivant dans `settings`.

**Anti-objectifs (échec même si « ça marche »)**

- Une facture `PAID`, un solde crédité ou une recherche de chauffeur déclenchés sans confirmation vérifiée de
  l'opérateur (signature webhook valide ou lecture du statut chez l'opérateur).
- Un double crédit ou un double remboursement (webhook dupliqué, polling + webhook concurrents, relance de job).
- Une commande en ligne non payée qui part en recherche de chauffeur (par `confirm` ou `perform_driver_lookup`).
- Un client qui a payé puis annulé et dont le remboursement échoue silencieusement.
- Des règles métier (facture, recherche, crédit, remboursement) dupliquées dans chaque opérateur.

## 3. Contraintes

### Projet

- Laravel, PostgreSQL/PostGIS, file `database`, worker et cron séparés sur Railway (cache non partagé entre
  conteneurs : `Setting` a un cache de 300 s, donc une bascule d'opérateur peut mettre jusqu'à 5 min à se propager —
  acceptable).
- Tests : `docs/TESTING.md`. Les appels HTTP externes sont simulés avec `Http::fake()`. Tests existants à faire
  évoluer : `tests/Feature/Payments/OrderOnlinePaymentTest.php`, `DriverWalletPaymentFlowTest.php`.
- Le smoke test `ApiRoutesSmokeTest` couvre automatiquement toute nouvelle route (aucune ne doit répondre 5xx).
- **Le dashboard `ouego-dashboard.adjemin.com` partage la même base** et peut modifier commandes et factures
  directement. Les observers / événements Eloquent de ce dépôt ne se déclenchent pas pour ses écritures : les
  garanties de ce document doivent donc tenir **même si une ligne est modifiée hors de cette API** (voir 5.6, filet
  de rattrapage).
- Le paiement en ligne **n'est pas encore proposé dans l'app** : aucune compatibilité avec les versions mobiles
  actuelles n'est nécessaire. Le mobile activera le choix « en ligne » après l'intégration.

### Montants

- Jèko n'accepte que des XOF entiers : le montant du `Payment` = total de la facture **arrondi au franc
  supérieur** ; c'est ce montant qui est contrôlé à la confirmation et remboursé.
- Total > 2 000 000 F (plafond Jèko) : `pay` refuse avec un message invitant à payer en cash. Le plafond est une
  valeur de configuration de l'opérateur (`maxAmount()`), pas une constante en dur.
- Total = 0 (remise commerciale) : `pay` marque la facture `PAID` sans appeler l'opérateur et lance la recherche.

### Jèko (doc : https://developer.jeko.africa)

- Base `https://api.jeko.africa`, **pas de sandbox** (tests en production avec un store dédié et de petits montants).
- Authentification : en-têtes `X-API-KEY` et `X-API-KEY-ID`.
- Encaissement : `POST /partner_api/payment_requests` avec `storeId`, `amountCents` (centimes, multiple de 100 ;
  1 XOF = 100), `currency` (`XOF`), `reference` unique (5–100 car.),
  `paymentDetails: { type: "redirect", data: { paymentMethod, successUrl, errorUrl } }`.
  `successUrl` / `errorUrl` doivent être des URL HTTPS. Réponse : `id`, `status: pending`, `redirectUrl`.
- `paymentMethod` est **obligatoire** : `wave`, `orange`, `mtn`, `moov`, `djamo`, `jeko`.
- Statut : `GET /partner_api/payment_requests/{id}` → `pending` | `success` | `error` (+ `transaction` en cas de
  succès, avec `counterpartIdentifier`, `paymentMethod`, `fees`).
- Webhook `TRANSACTION_COMPLETED` (en-têtes `Jeko-Event`, `Jeko-Signature` = HMAC-SHA256 hex du **corps brut** avec
  le secret du webhook, sans préfixe ni horodatage). Envoyé **uniquement en cas de succès** ; 3 tentatives en ~3 s ;
  webhook désactivé après 15 échecs consécutifs ; doublons et désordre possibles. Le corps contient
  `transactionDetails.id` (= id de la payment request) et `transactionDetails.reference`.
- **Aucun endpoint de remboursement** d'un encaissement. Le remboursement se fait par **transfert**
  (`POST /partner_api/transfers`) vers un contact Mobile Money (`POST /partner_api/contacts`), avec une `reference`
  unique (rejeu → 409). Frais : 1,5 % ; le store doit avoir le solde suffisant.
- Limites : 500 req/min par entreprise ; montant max 2 000 000 F par transaction.

## 4. Approches envisagées

1. **Contrat `PaymentGateway` + un adaptateur par opérateur, effets métier centralisés dans `PaymentService`**
   — **retenue** par OUEGO. Ajouter un opérateur = une classe + une route webhook.
2. Un contrôleur complet par opérateur — écartée : règles métier dupliquées et divergentes.
3. Adapter Jèko au format du webhook générique actuel — écartée : pas de signature, `forceDelete` des échecs,
   ni remboursement ni lecture de statut.

## 5. Conception

### 5.1 Couche opérateur

```
app/Payments/
  Contracts/PaymentGateway.php
  PaymentGatewayManager.php        // résout l'opérateur actif ou un opérateur nommé
  Gateways/JekoGateway.php
  Data/…                            // objets de résultat neutres (voir ci-dessous)
```

Contrat (signatures indicatives, le builder affine les types) :

- `code(): string` — `jeko`.
- `createPayment(Payment $payment, PaymentContext $ctx): GatewayCheckout` → id opérateur + URL de paiement.
- `fetchPaymentStatus(Payment $payment): GatewayPaymentStatus` → `pending|successful|failed`, montant, payeur
  (numéro, moyen), frais, données brutes.
- `parseWebhook(Request $request): ?GatewayWebhookEvent` → **vérifie la signature** (sinon exception / null →
  réponse 401) et renvoie l'id de paiement concerné. Le webhook ne sert que de **signal** : la décision finale
  passe par `fetchPaymentStatus` (voir 5.4).
- `refund(Refund $refund): GatewayRefundResult` et `fetchRefundStatus(Refund $refund)`.

`PaymentGatewayManager` :

- `active()` : lit `Setting::get('PAYMENT_ACTIVE_PROVIDER')` ; si vide, inconnu ou non configuré → exception métier
  « paiement en ligne indisponible » (l'API répond 503 avec un message en français, pas de 500).
- `driver(string $code)` : pour traiter un paiement existant avec **son** opérateur, même s'il n'est plus actif.

Configuration : `config/payments.php` (ou bloc dans `config/services.php`) — `jeko.api_key`, `jeko.api_key_id`,
`jeko.store_id`, `jeko.webhook_secret`, `jeko.base_url`, timeouts. Secrets uniquement en variables
d'environnement Railway. Un opérateur sans configuration complète ne peut pas être activé.

### 5.2 Données

Migration sur `payments` (on conserve les colonnes existantes) :

- `provider` (string, nullable pour l'historique) — opérateur qui a créé le paiement.
- `provider_payment_id` (string, index) — id de la payment request Jèko.
- `payment_url` (text) — URL de paiement à ouvrir dans l'app.
- `payable_type` (string) — `order` ou `wallet_deposit` (aujourd'hui déduit de `invoices.order_source`, on peut
  garder cette déduction ; au choix du builder).
- `expires_at` (timestamp), `last_checked_at` (timestamp).
- Contrainte d'unicité sur `payment_reference` (aujourd'hui `md5(time())` tronqué, non unique en base) et
  génération plus robuste (ex. `OUEGO-` + ULID), compatible avec la contrainte Jèko 5–100 caractères.

Statuts `Payment` : réutiliser `INITIATED`, `PENDING`, `SUCCESSFUL`, `FAILED`, `CANCELLED`, `EXPIRED`.
**On ne supprime plus les paiements échoués** (`cancelTransaction` fait aujourd'hui un `forceDelete`).

Nouvelle table `payment_refunds` : `payment_id`, `order_id`, `provider`, `amount`, `currency_code`, `reference`
(unique), `provider_refund_id`, `recipient_phone`, `recipient_method`, `status`
(`PENDING|PROCESSING|SUCCESSFUL|FAILED`), `attempts`, `last_error`, timestamps.
Contrainte d'unicité : un seul remboursement par `payment_id`.

### 5.3 Parcours

**Moyens de paiement.** Jèko exige le moyen (`wave`, `orange`…). L'app l'envoie dans `pay` / `deposit`
(`payment_method` : ex. `wave`). La liste vient de **l'opérateur actif** (`PaymentGateway::supportedMethods()` :
code, libellé, logo), exposée par `GET payments/methods`. La table `payment_methods` (`mobile-money`, `cash`) reste
le choix « cash / en ligne » de la commande et n'est pas modifiée.

**Commande en ligne** = toute commande dont `payment_method_code` **n'est pas `cash`** (donc `online` et
`mobile-money`), règle identique à `Order::chargeDriver()`. Centraliser ce test dans `Order::isPaidOnline()`.

1. `orders/create` : inchangé. La commande reste `INITIATED`, facture `UNPAID`.
2. `PUT orders/{id}/pay` (`payment_method` requis) :
   - refuse si la commande n'est pas `online`, si elle est annulée/terminée, ou si la facture est `PAID` ;
   - si un paiement `PENDING` non expiré existe pour la facture, il est **réutilisé** (pas de doublon) ;
   - sinon crée le `Payment` (opérateur actif), appelle `createPayment`, enregistre `provider_payment_id`,
     `payment_url`, `expires_at`, et renvoie le `Payment` (avec `payment_url`).
3. Le client paie dans la page Jèko, puis revient via `successUrl`/`errorUrl` (voir 5.5).
4. Confirmation (webhook ou polling, 5.4) → `PaymentService::markSuccessful()` :
   facture `PAID`, `is_paid_by_customer = true`, puis **même logique que `confirm`** : `is_draft = false`,
   `order_date = now()`, statut `PERFORMER_LOOKUP`, historique, `sendInvitations`. Cette logique est extraite de
   `OrderAPIController::confirm` dans un service réutilisé par les deux chemins.
5. `confirm` et `perform_driver_lookup` **refusent** (400) une commande `online` dont la facture n'est pas `PAID`.
6. Paiement échoué : le `Payment` passe `FAILED`, la commande reste `INITIATED` ; le client peut relancer `pay`.
7. Non payée au bout de `ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES` (setting, défaut 30) depuis la création :
   la commande est annulée par la tâche planifiée (historique + notification client).

**Aucun chauffeur trouvé (toutes commandes, cash comme en ligne).** Changement de comportement demandé par OUEGO :

- `ProcessPendingOrderAssignments::markAsNotFound` passe la commande en `PERFORMER_NOT_FOUND` **sans** la terminer :
  `is_completed` reste `false` (`is_waiting = false`, invitations restantes annulées comme aujourd'hui).
- Le client relance la recherche par `PUT orders/{id}/perform_driver_lookup` autant de fois qu'il veut : la commande
  repasse `PERFORMER_LOOKUP` (+ historique) et de nouvelles invitations partent. Pour une commande en ligne, le
  paiement déjà confirmé reste valable : **pas de nouveau paiement**.
- La commande ne se termine que par une livraison ou une **annulation explicite** du client (`cancel`), qui déclenche
  le remboursement si elle était payée en ligne.
- `Order::scopeInProgress` : une commande `PERFORMER_NOT_FOUND` non terminée compte désormais comme en cours (elle
  bloque la suppression du compte, ce qui protège un client payé).
- Vérifier les autres lecteurs de `is_completed` / `PERFORMER_NOT_FOUND` (listes client/chauffeur, assignation de
  nuit, Express) pour qu'une commande « non trouvée » ne soit ni réassignée automatiquement ni perdue.

**Recharge portefeuille chauffeur** (`balance/deposit`) : même création de `Transaction` + `Invoice` qu'aujourd'hui,
puis `createPayment` avec l'opérateur actif ; réponse avec `payment_url`. Sur succès : facture `PAID`,
`Transaction` `SUCCESSFUL`, `Driver::creditBalance()` — **une seule fois**. Sur échec : `Transaction` `FAILED`.

**Cash** : inchangé.

### 5.4 Confirmation, idempotence et rattrapage

- Route `POST payments/webhook/{provider}` (publique, hors auth, exclue du CSRF). Elle :
  1. résout l'opérateur par `{provider}` (pas l'opérateur actif) ;
  2. `parseWebhook` → signature invalide = **401**, rien n'est modifié ;
  3. retrouve le `Payment` par `provider_payment_id` (ou `reference`) ; inconnu = 200 + log (pour ne pas faire
     désactiver le webhook) ;
  4. relit le statut chez l'opérateur (`fetchPaymentStatus`) et applique le résultat via `PaymentService` ;
  5. répond 200 rapidement. Si l'opérateur est injoignable, on répond 200 et on laisse le polling conclure.
- `PaymentService::applyStatus()` est **idempotent** : verrou de ligne (`lockForUpdate`) sur le `Payment` dans une
  transaction DB ; si le `Payment` est déjà dans un état final, ne fait rien. Le crédit portefeuille et le lancement
  de la recherche ne se produisent qu'à la transition `PENDING → SUCCESSFUL`.
- Contrôle de montant : si le montant confirmé par l'opérateur ≠ montant du `Payment`, on ne valide pas ; on loggue
  en erreur et on alerte.
- Tâche planifiée `payments:reconcile` (chaque minute, `withoutOverlapping`) : pour chaque `Payment` `PENDING` d'un
  opérateur, appelle `fetchPaymentStatus` (backoff : d'abord chaque minute, puis plus espacé), marque `FAILED` /
  `SUCCESSFUL`, marque `EXPIRED` au-delà de `expires_at`. C'est le seul moyen de voir les échecs Jèko (pas de webhook
  d'échec) et le filet si le webhook est désactivé. Respecter la limite de 500 req/min.
- L'ancienne route générique `payments/webhook` **est conservée, mais restreinte aux retraits chauffeur** : elle
  reste la seule source de vérité pour confirmer un retrait (`Transaction::TYPE_WITHDRAWAL`). Elle **refuse** tout
  paiement de commande ou de dépôt (sinon n'importe qui pourrait, avec une référence, marquer une facture payée ou
  créditer un portefeuille sans passer par Jèko). La variante `GET` est supprimée si l'appelant n'en dépend pas, à
  vérifier dans les logs. Son comportement pour les retraits (débit du solde, `forceDelete` sur échec) est inchangé.

### 5.5 Retour dans l'app

Jèko exige des `successUrl`/`errorUrl` en HTTPS. Le backend expose `GET payments/return/{reference}/{result}`
qui renvoie une petite page HTML (« Paiement reçu, retour à l'application… ») et redirige vers un deep link de
l'app (`APP_PAYMENT_RETURN_DEEPLINK_CUSTOMER` / `_DRIVER`, en configuration). Cette page n'a **aucun** effet sur le
paiement. L'app rafraîchit ensuite la commande / le paiement (`GET` existants) ou écoute l'événement temps réel.

Événements Reverb : `PaymentStatusUpdated` (commande et dépôt) diffusé au client / chauffeur concerné, pour que l'app
réagisse sans polling. Notification push au client : « Paiement reçu, recherche de chauffeur en cours ».

### 5.6 Remboursements

Déclencheurs, pour une commande en ligne dont la facture est `PAID` par l'opérateur :

- annulation client (`cancel`) — y compris depuis `PERFORMER_NOT_FOUND` ;
- **paiement confirmé après annulation** (le client a annulé pendant que le paiement était en cours) ;
- **filet de rattrapage** dans `payments:reconcile` : toute commande en ligne annulée dont le paiement est
  `SUCCESSFUL` et sans ligne `payment_refunds` → remboursement. C'est ce qui couvre les annulations faites
  **depuis le dashboard** (même base, hors de cette API).

Pas de remboursement pour `PERFORMER_NOT_FOUND` seul : la commande reste ouverte et relançable (5.3).

1. `PaymentService::refund($payment)` crée un `payment_refunds` (`PENDING`, montant **intégral** du paiement,
   référence unique) — la contrainte d'unicité empêche un double remboursement.
2. La commande passe `CANCELLED_WITH_PAYMENT` (statut déjà existant), facture marquée remboursée
   (champ ou statut au choix du builder, ex. `Invoice::REFUNDED`).
3. Job `ProcessRefund` (file `database`, worker existant) : appelle `refund()` de **l'opérateur d'origine du
   paiement**. Pour Jèko : créer/réutiliser un contact à partir du `counterpartIdentifier` et du `paymentMethod`
   du paiement d'origine, puis `POST /partner_api/transfers` avec la référence du remboursement (rejeu sûr : 409 =
   déjà créé, on relit le statut).
4. Suivi : `payments:reconcile` relit aussi les remboursements `PROCESSING`. Succès → `SUCCESSFUL` + notification
   client. Échec → nouvelles tentatives avec backoff (ex. 5 tentatives sur ~2 h) ; au-delà, `FAILED` + **alerte**
   (log `critical` + email/Slack selon ce qui existe ; le builder choisit le canal disponible) pour traitement
   manuel.
5. Les frais Jèko (1,5 % encaissement + 1,5 % transfert) restent à la charge d'OUEGO : le client reçoit le montant
   payé.

Cas couverts par l'annulation : agrégats annulables jusqu'au chargement, autres services jusqu'au démarrage
(règles actuelles de `cancel`, inchangées).

### 5.7 Bascule d'opérateur

- `PAYMENT_ACTIVE_PROVIDER` dans `settings` (`jeko` au lancement).
- Nouveaux paiements → opérateur actif. Paiements, webhooks, polling et remboursements existants → opérateur
  enregistré dans `payments.provider`.
- Opérateur désactivé : son webhook et son polling continuent de fonctionner tant que sa configuration existe.

## 6. Tests attendus

Fonctionnels (`Http::fake()` pour Jèko) :

- `pay` : crée la payment request, renvoie `payment_url`, réutilise un paiement en attente, refuse cash / payée /
  annulée, 503 si aucun opérateur actif.
- Webhook : signature invalide → 401 sans effet ; signature valide + statut `success` → facture `PAID` et recherche
  de chauffeur lancée **une seule fois** même avec deux webhooks ; montant incohérent → pas de validation.
- `confirm` / `perform_driver_lookup` refusés pour une commande en ligne non payée.
- Polling : échec détecté → `FAILED` ; succès manqué → validé ; expiration.
- Dépôt chauffeur : crédit unique.
- Annulation d'une commande payée → remboursement créé et transfert appelé une fois ; paiement confirmé après
  annulation → remboursement ; échec de transfert → relances puis `FAILED` + alerte.
- Bascule : un paiement créé sous Jèko reste traité par Jèko quand l'opérateur actif change.
- Aucun chauffeur trouvé : commande non terminée, relance possible sans nouveau paiement, annulation ensuite →
  remboursement.
- Filet de rattrapage : commande en ligne payée annulée directement en base → remboursement créé une seule fois.
- Ancien webhook : confirme un retrait comme avant ; refuse une facture de commande ou de dépôt.
- Montants : arrondi, plafond, total 0.

Mettre à jour `OrderOnlinePaymentTest` et `DriverWalletPaymentFlowTest`, qui utilisent l'ancien webhook.

## 7. Décisions

**Prises par OUEGO**

- Opérateurs multiples, un seul actif à la fois ; Jèko en premier.
- Commande en ligne payée **avant** la recherche de chauffeur.
- Remboursement automatique via l'opérateur, montant intégral, frais à la charge d'OUEGO.
- Aucun chauffeur trouvé : la commande n'est pas terminée ; le client relance jusqu'à annuler explicitement.
- Retraits chauffeur : l'ancien webhook reste leur seule source de vérité.
- Pas de compatibilité mobile à prévoir : le paiement en ligne sera activé côté app après l'intégration.
- Le dashboard partage la même base.

**Prises par le concepteur (à vérifier par OUEGO)**

- Commande en attente de paiement : reste `INITIATED`, pas de nouveau statut de commande ; l'app lit l'état du
  paiement.
- Délai d'expiration d'une commande en ligne non payée : 30 min, réglable dans `settings`.
- L'app choisit le moyen de paiement (Wave, Orange…) avant d'ouvrir la page Jèko ; liste fournie par l'opérateur.
- « En ligne » = tout ce qui n'est pas `cash` (inclut `mobile-money`).
- Montant arrondi au franc supérieur ; > 2 000 000 F refusé (payer en cash) ; total 0 → payé sans opérateur.
- Aucune compensation ni pénalité chauffeur lors d'une annulation remboursée.
- Ancien webhook restreint aux retraits (refuse commandes et dépôts).
- Annulations faites depuis le dashboard : remboursées par le filet de rattrapage (délai ≤ 1 min).
- Retours HTTPS via une page backend qui redirige vers un deep link.

**Risques connus, hors périmètre**

- L'ancien webhook n'a toujours **aucune signature** : quiconque connaît une référence de retrait peut le
  confirmer. À sécuriser avec l'équipe qui l'appelle (secret partagé ou signature).
- Le dashboard peut marquer une facture `PAID` à la main sans paiement opérateur : ce dépôt ne peut pas
  l'empêcher. À aligner côté dashboard (ne pas modifier le statut des factures en ligne).

## 8. Laissé au builder

- Noms exacts des classes, DTO et jobs ; découpage des migrations.
- Champs exacts de `POST /partner_api/contacts` et `POST /partner_api/transfers` et statuts des transferts : à
  lire dans la doc Jèko (pages *transfers/integration*, *beneficiary*) avant d'implémenter `refund()`.
- Rythme précis du backoff de polling.
- Canal d'alerte (selon ce qui existe dans le projet).

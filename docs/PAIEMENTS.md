# Paiements en ligne — mise en service et exploitation

OUEGO encaisse en ligne par **un opérateur actif à la fois** (aujourd'hui Jèko). Deux parcours l'utilisent :

| Parcours | Endpoint | Effet du paiement réussi |
|---|---|---|
| Commande payée en ligne | `PUT /api/v1/orders/{id}/pay` `{payment_method}` | facture `PAID`, puis recherche de chauffeur |
| Recharge du portefeuille chauffeur | `POST /api/v1/drivers/transactions/balance/deposit` `{amount, payment_method}` | transaction `SUCCESSFUL`, solde crédité |

Les deux renvoient un `Payment` avec `payment_url` : l'app ouvre cette page, le client paie chez l'opérateur, puis
il revient dans l'app par `payments/return/{reference}/{success|error}`. Ce retour **ne prouve rien** : seuls
le webhook signé et la relecture du statut chez l'opérateur changent l'état d'un paiement.

Une commande en ligne (`payment_method_code` ≠ `cash`) **ne part en recherche de chauffeur qu'une fois payée**.
`confirm` et `perform_driver_lookup` répondent 400 « Veuillez d'abord payer la commande » tant que la facture
n'est pas payée.

## 1. Variables d'environnement

À définir sur les services **api**, **worker** et **cron** de Railway :

| Variable | Valeur |
|---|---|
| `JEKO_BASE_URL` | `https://api.jeko.africa` |
| `JEKO_API_KEY` | Dashboard Jèko → Développeurs → Clés API (la clé n'est affichée qu'une fois) |
| `JEKO_API_KEY_ID` | identifiant de cette clé |
| `JEKO_STORE_ID` | UUID du magasin qui encaisse et qui paie les remboursements |
| `JEKO_WEBHOOK_SECRET` | secret du webhook (voir §3, affiché **une seule fois**) |
| `JEKO_CONTACT_METHOD_ORANGE` | `orange_money` par défaut ; mettre `orange` si Jèko refuse les remboursements Orange |
| `APP_PAYMENT_RETURN_DEEPLINK_CUSTOMER` | lien de retour vers l'app client, ex. `ouego://payment` |
| `APP_PAYMENT_RETURN_DEEPLINK_DRIVER` | lien de retour vers l'app chauffeur |

Un opérateur dont une clé manque est considéré comme **non configuré** : `GET /api/v1/payments/methods` et les
paiements répondent 503 « Le paiement en ligne est momentanément indisponible ».

## 2. Activer l'opérateur

L'opérateur actif est un setting, modifiable sans redéploiement :

```sql
INSERT INTO settings (name, value, created_at, updated_at)
VALUES ('PAYMENT_ACTIVE_PROVIDER', 'jeko', now(), now())
ON CONFLICT DO NOTHING; -- ou UPDATE settings SET value = 'jeko' WHERE name = 'PAYMENT_ACTIVE_PROVIDER'
```

Sans ce setting, aucun nouveau paiement en ligne n'est possible (503). Modifié depuis le dashboard ou en SQL, il
prend effet en **5 minutes au plus** (cache des settings, `Setting::CACHE_TTL`).

Changer d'opérateur ne touche que les **nouveaux** paiements : un paiement en cours, son webhook, sa relecture
et son remboursement restent traités par l'opérateur qui l'a encaissé (`payments.provider`).

Autre setting, à créer en production (le seeder ne couvre que les bases neuves) :

```sql
INSERT INTO settings (name, value, created_at, updated_at)
VALUES ('ONLINE_ORDER_PAYMENT_TIMEOUT_MINUTES', '30', now(), now());
```

Une commande en ligne non payée après ce délai, sans paiement encore en cours, est annulée et le client notifié.

**Avant le premier déploiement**, compter les anciennes commandes que le premier passage de `payments:reconcile`
annulerait (toute commande non `cash` restée `INITIATED`, même antérieure à cette fonctionnalité) :

```sql
SELECT count(*) FROM orders
WHERE status = 'initiated' AND is_completed = false AND payment_method_code <> 'cash'
  AND created_at <= now() - interval '30 minutes';
```

Si le résultat n'est pas nul, les clore sans notification avant de déployer
(`UPDATE orders SET status = 'cancelled', is_completed = true, completion_time = now() WHERE …`), sinon chacune
recevra une notification « Commande annulée : paiement non reçu ».

## 3. Déclarer le webhook dans le Dashboard Jèko

- URL : `https://<domaine-api>/api/v1/payments/webhook/jeko`
- Événement : `TRANSACTION_COMPLETED` (paiements et transferts).
- **Copier le secret immédiatement** dans `JEKO_WEBHOOK_SECRET` : Jèko ne l'affiche qu'une fois. Perdu, il faut
  régénérer un webhook.

Le webhook vérifie la signature `Jeko-Signature` (HMAC-SHA256 du corps brut), puis **relit le statut chez Jèko**
avant d'agir : un corps falsifié mais bien signé ne suffit pas à créditer. Il répond 200 même quand le paiement est
inconnu ou que Jèko ne répond pas, car Jèko désactive un webhook après 15 échecs. Une signature invalide répond 401.

L'ancien webhook `api/v1/payments/webhook` (sans opérateur) ne traite plus que les **retraits** ; il ne doit pas
être retiré tant que les retraits en dépendent.

## 4. Tâches planifiées et file

- `payments:reconcile` tourne **chaque minute** (`app/Console/Kernel.php`) ; le service cron doit exécuter
  `php artisan schedule:run`. Il :
  1. relit les paiements en attente (Jèko n'envoie **aucun webhook d'échec**) : chaque minute pendant 10 min,
     puis toutes les 5 min, et les marque `EXPIRED` après 30 min ; un paiement expiré est encore relu toutes
     les heures pendant 24 h (paiement tardif) ;
  2. annule les commandes en ligne non payées (§2) ;
  3. rembourse les commandes en ligne annulées hors API (dashboard, SQL) dont le paiement a réussi ;
  4. relit les remboursements en cours et relance ceux dont l'essai suivant est dû.
- Le job `ProcessRefund` passe par la file `database` : le service **worker** (`php artisan queue:work`) doit
  tourner. S'il est arrêté, `payments:reconcile` relance les remboursements en attente.

## 5. Premier test en production

Jèko n'a pas de sandbox. Tester avec de vrais petits montants, idéalement sur un **magasin Jèko dédié** :

1. Recharger un portefeuille chauffeur de test de **100 F** par Wave : le solde doit être crédité dans la minute.
2. Vérifier dans `payments` : `status = SUCCESSFUL`, `amount = 100`, `payment_gateway_buyer_reference` rempli.
   **Point à confirmer** : l'unité de `transaction.amount.amount` renvoyée par Jèko est supposée en centimes. Si
   le paiement passe `FAILED` avec le log « montant confirmé inférieur au montant attendu », l'unité est en
   francs : corriger `JekoGateway::fetchPaymentStatus`.
3. Créer une commande en ligne, la payer, vérifier le passage en `PERFORMER_LOOKUP`.
4. Annuler cette commande : un `payment_refunds` est créé, puis `SUCCESSFUL` dès la confirmation du transfert ;
   le payeur reçoit le montant intégral, les frais sont débités du magasin.
5. Refaire l'étape 4 avec **Orange Money** pour valider `JEKO_CONTACT_METHOD_ORANGE`.
6. Abandonner un paiement : il doit passer `EXPIRED` après 30 min.

Le magasin doit avoir un **solde suffisant** pour payer les remboursements : sinon ils échouent (voir §6).

## 6. Remboursements

Une commande payée en ligne puis annulée (par le client, le dashboard, ou un paiement confirmé après annulation)
est remboursée **intégralement**, par transfert Mobile Money vers le numéro qui a payé. La commande passe
`CANCELLED_WITH_PAYMENT` et la facture `REFUNDED`. Un second paiement sur une facture déjà réglée est aussi
remboursé, de même qu'un paiement arrivé sur une commande close sans avoir été servie (`FAILED`,
`CANCELLED_BY_TAXI`) et tout argent encaissé par l'opérateur sur un paiement que nous avions clos (échec, montant
incomplet : seul le montant reçu est rendu) — ce dernier cas écrit aussi un log `critical`.

Suivi dans `payment_refunds` :

| `status` | Sens |
|---|---|
| `PENDING` | à envoyer ; `next_attempt_at` = prochain essai (5, 15, 30, puis 60 min) |
| `PROCESSING` | transfert accepté par Jèko, en attente de confirmation |
| `SUCCESSFUL` | remboursé ; le client a reçu une notification |
| `FAILED` | 5 essais en échec : **action manuelle requise** |

La référence envoyée à Jèko est `{reference}-{transfer_sequence}` ; elle ne change qu'après un refus définitif, ce
qui empêche de rembourser deux fois après une réponse perdue.

### Procédure pour un remboursement `FAILED`

Le log `critical` « Remboursement en échec » donne `refund_id`, `order_id`, `amount` et `last_error`.

1. Lire `last_error` : solde du magasin insuffisant, numéro invalide, moyen de paiement refusé (Orange, §1)…
2. Vérifier dans le Dashboard Jèko qu'**aucun transfert** n'est parti pour la référence `RF-…` (toutes
   séquences), pour ne pas payer deux fois.
3. Corriger la cause, puis relancer :
   ```sql
   UPDATE payment_refunds
   SET status = 'PENDING', attempts = 0, next_attempt_at = NULL, transfer_sequence = transfer_sequence + 1
   WHERE id = <refund_id>;
   ```
   `payments:reconcile` l'enverra à la minute suivante.
4. Ou rembourser à la main hors OUEGO, puis `UPDATE payment_refunds SET status = 'SUCCESSFUL' WHERE id = …`.

Un remboursement resté `PROCESSING` plus de 24 h déclenche une fois le log `critical` « Remboursement bloqué en
cours de transfert » (et `last_error` le signale). Un remboursement resté `PROCESSING` sans `provider_refund_id` signifie que Jèko a répondu 409 (transfert déjà
créé) et que le webhook n'est pas arrivé : retrouver le transfert dans le Dashboard Jèko par sa référence et
reporter son id dans `provider_refund_id`.

## 7. « Aucun chauffeur trouvé »

`PERFORMER_NOT_FOUND` n'est plus une fin : la commande reste ouverte (`is_completed = false`). Le client relance
la recherche par `PUT /api/v1/orders/{id}/perform_driver_lookup` (sans nouveau paiement) ou annule — une commande
payée est alors remboursée. Les commandes `PERFORMER_NOT_FOUND` antérieures à ce changement ont
`is_completed = true` et restent closes.

## 8. Ajouter un opérateur

1. Créer `app/Payments/Gateways/<Nom>Gateway.php` qui implémente `App\Payments\Contracts\PaymentGateway` :
   `code`, `isConfigured`, `supportedMethods`, `maxAmount`, `createPayment`, `fetchPaymentStatus`,
   `parseWebhook` (vérifier la signature, lever `InvalidWebhookSignature`), `refund`, `fetchRefundStatus`.
   Toute erreur réseau ou HTTP devient une `GatewayException` ; les statuts sont traduits vers
   `GatewayPaymentStatus` / `PaymentRefund`.
2. Ajouter son entrée dans `config/payments.php` → `providers.<code>` (`class` + ses clés) et ses variables dans
   `.env.example`.
3. Tests sur le modèle de `tests/Unit/Payments/JekoGatewayTest.php` (`Http::fake`).
4. Déclarer son webhook : `https://<domaine-api>/api/v1/payments/webhook/<code>`.
5. Basculer : `UPDATE settings SET value = '<code>' WHERE name = 'PAYMENT_ACTIVE_PROVIDER'`.

La logique métier (états, effets, remboursements) est dans `App\Payments\PaymentService` et ne change pas.

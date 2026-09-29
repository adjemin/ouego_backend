# Temps réel avec Laravel Reverb

Les apps client et chauffeur reçoivent les mises à jour des commandes en temps réel via
**Laravel Reverb**, un serveur WebSocket compatible avec le protocole Pusher.

## Architecture

Trois processus doivent tourner en même temps :

| Processus | Commande | Rôle |
|---|---|---|
| API | `php artisan serve` | Déclenche les events et authentifie les canaux privés |
| Worker | `php artisan queue:work` | Envoie les events au serveur Reverb (les broadcasts passent par la queue) |
| Reverb | `php artisan reverb:start` | Serveur WebSocket auquel les apps se connectent |

```
App mobile ──(1) POST /api/v1/.../broadcasting/auth (JWT)──▶ API
     │
     └──(2) WebSocket ws://REVERB_HOST:REVERB_PORT/app/{KEY}──▶ Reverb
                                                               ▲
API ──event──▶ table jobs ──▶ queue:work ──(3) HTTP publish────┘
```

> Sans `queue:work`, aucun event n'arrive : ils restent dans la table `jobs`.

## Configuration (`.env`)

```dotenv
BROADCAST_DRIVER=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=ouego
REVERB_APP_KEY=<clé publique>        # partagée avec les apps mobiles
REVERB_APP_SECRET=<secret>           # reste côté serveur
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

Générer une clé et un secret :

```bash
openssl rand -hex 16   # REVERB_APP_KEY
openssl rand -hex 32   # REVERB_APP_SECRET
```

`REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` indiquent à Laravel **où publier** les events.
Le serveur, lui, écoute sur `REVERB_SERVER_HOST` (défaut `0.0.0.0`) et `REVERB_SERVER_PORT` (défaut `8080`).

Après toute modification du `.env` : `php artisan config:clear`.

## Lancer en local

Prérequis : la base locale est démarrée (`docker compose up -d`) et migrée — la table `jobs` doit exister.

Dans trois terminaux :

```bash
php artisan serve                  # API sur http://127.0.0.1:8000
php artisan queue:work             # worker
php artisan reverb:start --debug   # WebSocket sur ws://localhost:8080, logs des connexions et messages
```

Pour tester depuis un téléphone sur le même réseau, remplacer `localhost` par l'IP de la machine
côté app mobile (`php artisan serve --host=0.0.0.0` pour l'API).

## Canaux et events

Tous les canaux sont **privés** : le client doit s'authentifier avant de s'abonner.

| Canal | Qui peut écouter | Endpoint d'auth | Events |
|---|---|---|---|
| `private-orders.{orderId}` | Le client propriétaire de la commande | `POST /api/v1/broadcasting/auth` (JWT client) | `order.status.updated` |
| `private-customers.{customerId}` | Le client lui-même | `POST /api/v1/broadcasting/auth` (JWT client) | `order.created`, `order.status.updated` |
| `private-drivers.{driverId}` | Le chauffeur lui-même | `POST /api/v1/drivers/broadcasting/auth` (JWT chauffeur) | `order.invitation.created` |

Autorisations : `routes/channels.php`. Routes d'auth : `app/Providers/BroadcastServiceProvider.php`.

### `order.created` — `app/Events/OrderCreated.php`

Déclenché à la création d'une commande ayant un `customer_id`, après le commit de la transaction.

```json
{
  "order_id": 42,
  "reference": "ORD-XXXX",
  "status": "new",
  "service_slug": "agregats-construction",
  "delivery_type_code": "EXPRESS",
  "order_price": 162500,
  "delivery_price": 75000,
  "total": 237500,
  "currency_code": "XOF",
  "created_at": "2026-09-28T10:00:00+00:00"
}
```

### `order.status.updated` — `app/Events/OrderStatusUpdated.php`

Déclenché quand `status` ou `driver_id` d'une commande change (hook `updated` du modèle `Order`).
Le payload reflète l'état au moment du changement.

```json
{
  "order_id": 42,
  "reference": "ORD-XXXX",
  "status": "performer_found",
  "service_slug": "agregats-construction",
  "previous_status": "performer_lookup",
  "is_completed": false,
  "acceptation_time": "2026-09-28 10:05:00",
  "driver": { "id": 7, "name": "…", "phone": "…", "photo_url": "…", "rate": 4.8 },
  "updated_at": "2026-09-28T10:05:00+00:00"
}
```

`driver` vaut `null` tant qu'aucun chauffeur n'est affecté.

### `order.invitation.created` — `app/Events/OrderAssigned.php`

Déclenché par les services d'assignation (`app/Services/Driver*AssignmentService.php`, `TripService`)
quand un chauffeur est invité sur une commande. Non envoyé si l'invitation n'est plus en attente
(`is_waiting_acceptation = false`) au moment où le worker la traite.

```json
{
  "invitation_id": 15,
  "order_id": 42,
  "status": "…",
  "created_at": "2026-09-28T10:01:00+00:00",
  "order": {
    "reference": "ORD-XXXX",
    "service_slug": "agregats-construction",
    "delivery_type_code": "EXPRESS",
    "driver_due": 232500,
    "currency_code": "XOF",
    "route_points": [
      { "type": "source", "address_name": "…", "latitude": 5.35, "longitude": -4.00 },
      { "type": "destination", "address_name": "…", "latitude": 5.30, "longitude": -3.98 }
    ]
  }
}
```

## Tester

### 1. Tests automatisés

Les tests n'appellent pas le serveur Reverb (`BROADCAST_DRIVER=log` forcé dans `phpunit.xml`).
Ils vérifient les canaux, les noms d'events, les payloads et les autorisations.

```bash
docker compose -f docker-compose.test.yml up -d --wait
php artisan test tests/Feature/Realtime
```

| Fichier | Couvre |
|---|---|
| `OrderStatusBroadcastTest.php` | `order.status.updated` et le canal `orders.{id}` |
| `CustomerOrdersBroadcastTest.php` | `order.created` et le canal `customers.{id}` |
| `DriverInvitationsBroadcastTest.php` | `order.invitation.created` et le canal `drivers.{id}` |

Voir aussi `docs/TESTING.md`.

### 2. Test de bout en bout avec un client Node

Ce script se comporte comme une app mobile : il s'authentifie avec un JWT et affiche les events reçus.

**a. Obtenir un JWT** (sans passer par l'OTP) :

```bash
php artisan tinker
>>> auth('api-customers')->login(App\Models\Customer::find(1))   # JWT client
>>> auth('api-drivers')->login(App\Models\Driver::find(1))       # JWT chauffeur
```

**b. Script d'écoute** — dans un dossier hors du projet :

```bash
npm init -y && npm install pusher-js
```

`listen.mjs` :

```js
import Pusher from 'pusher-js';

const { KEY, TOKEN, CHANNEL, AUTH_URL = 'http://127.0.0.1:8000/api/v1/broadcasting/auth' } = process.env;

const pusher = new Pusher(KEY, {
  cluster: 'mt1',          // requis par pusher-js, ignoré par Reverb
  wsHost: 'localhost',
  wsPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws'],
  channelAuthorization: {
    endpoint: AUTH_URL,
    headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' },
  },
});

pusher.connection.bind('connected', () => console.log('Connecté, socket_id =', pusher.connection.socket_id));
pusher.connection.bind('error', (err) => console.error('Erreur connexion', err));

const channel = pusher.subscribe(CHANNEL);
channel.bind('pusher:subscription_succeeded', () => console.log('Abonné à', CHANNEL));
channel.bind('pusher:subscription_error', (err) => console.error('Abonnement refusé', err));
channel.bind_global((event, data) => {
  if (!event.startsWith('pusher:')) console.log(event, JSON.stringify(data, null, 2));
});
```

**c. Lancer l'écoute** :

```bash
# Client : liste des commandes
KEY=<REVERB_APP_KEY> TOKEN=<jwt client> CHANNEL=private-customers.1 node listen.mjs

# Client : suivi d'une commande
KEY=<REVERB_APP_KEY> TOKEN=<jwt client> CHANNEL=private-orders.42 node listen.mjs

# Chauffeur : invitations (endpoint d'auth différent)
KEY=<REVERB_APP_KEY> TOKEN=<jwt chauffeur> CHANNEL=private-drivers.1 \
  AUTH_URL=http://127.0.0.1:8000/api/v1/drivers/broadcasting/auth node listen.mjs
```

**d. Déclencher les events** depuis `php artisan tinker` :

```php
// order.status.updated (orders.{id} + customers.{customerId})
$order = App\Models\Order::find(42);
$order->update(['status' => App\Models\Order::PERFORMER_FOUND]);

// order.created (customers.{customerId}) : créer une commande via l'API POST /api/v1/orders/create
// ou rediffuser pour une commande existante :
App\Events\OrderCreated::dispatch(App\Models\Order::find(42));

// order.invitation.created (drivers.{driverId}) : l'invitation doit être en attente
$invitation = App\Models\OrderInvitation::where('driver_id', 1)->where('is_waiting_acceptation', true)->latest()->first();
event(new App\Events\OrderAssigned($invitation));
```

On peut aussi passer par l'API : créer une commande (`POST /api/v1/orders/create`), l'annuler
(`PUT /api/v1/orders/{id}/cancel`), accepter une invitation (`PUT /api/v1/drivers/orders_invitations/{id}/accept`)…

L'event doit apparaître dans le terminal `listen.mjs`, dans les logs de `reverb:start --debug`
et le job doit passer en `DONE` dans le terminal `queue:work`.

### 3. Test manuel sans dépendance (wscat + curl)

Utile pour vérifier un serveur déployé.

```bash
npx wscat -c "ws://localhost:8080/app/<REVERB_APP_KEY>?protocol=7&client=js&version=8.4.0"
# Reverb répond : {"event":"pusher:connection_established","data":"{\"socket_id\":\"123.456\",...}"}
```

Dans un autre terminal, signer l'abonnement avec ce `socket_id` :

```bash
curl -X POST http://127.0.0.1:8000/api/v1/broadcasting/auth \
  -H "Authorization: Bearer <jwt client>" -H "Accept: application/json" \
  -d socket_id=123.456 -d channel_name=private-customers.1
# {"auth":"<KEY>:<signature>"}
```

Puis dans wscat :

```json
{"event":"pusher:subscribe","data":{"channel":"private-customers.1","auth":"<KEY>:<signature>"}}
```

Réponse attendue : `pusher_internal:subscription_succeeded`, puis les events au fil de l'eau.

### 4. Test avec Postman

Même principe que wscat, sans rien installer : un onglet **WebSocket** pour écouter et un onglet
**HTTP** pour signer l'abonnement au canal privé. Pour obtenir un JWT, voir l'étape 2.a.

**a. Ouvrir la connexion** — *New → WebSocket*, URL :

```
ws://localhost:8080/app/<REVERB_APP_KEY>?protocol=7&client=js&version=8.4.0
```

En prod : `wss://<domaine-reverb>.up.railway.app/app/<REVERB_APP_KEY>?protocol=7&client=js&version=8.4.0`.

Cliquer sur **Connect**. Reverb répond :

```json
{"event":"pusher:connection_established","data":"{\"socket_id\":\"123456789.987654321\",\"activity_timeout\":30}"}
```

Copier le `socket_id`.

**b. Signer l'abonnement** — requête HTTP :

- `POST http://127.0.0.1:8000/api/v1/broadcasting/auth` (client)
  ou `POST http://127.0.0.1:8000/api/v1/drivers/broadcasting/auth` (chauffeur, JWT chauffeur)
- Headers : `Authorization: Bearer <JWT>`, `Accept: application/json`
- Body → raw → JSON :

```json
{
  "socket_id": "123456789.987654321",
  "channel_name": "private-customers.1"
}
```

Réponse attendue : `{"auth":"<REVERB_APP_KEY>:5f3c...a91"}`.

**c. S'abonner** — dans la zone *Message* de l'onglet WebSocket :

```json
{
  "event": "pusher:subscribe",
  "data": {
    "channel": "private-customers.1",
    "auth": "<REVERB_APP_KEY>:5f3c...a91"
  }
}
```

Réponse attendue :

```json
{"event":"pusher_internal:subscription_succeeded","channel":"private-customers.1","data":"{}"}
```

**d. Déclencher un event** — via l'API dans Postman (`POST /api/v1/orders/create`,
`PUT /api/v1/orders/{id}/cancel`…) ou depuis tinker (voir 2.d). Le message arrive dans l'onglet WebSocket :

```json
{"event":"order.status.updated","channel":"private-customers.1","data":"{\"order_id\":42,\"status\":\"performer_found\",...}"}
```

`data` est une chaîne JSON encodée dans le message : c'est le format normal du protocole Pusher.

**Points d'attention**

- Le `socket_id` change à chaque connexion : après un Disconnect/Connect, refaire l'étape b,
  sinon l'abonnement est refusé (`pusher:error`, code 4009).
- Le `channel_name` doit être identique aux étapes b et c, préfixe `private-` compris.
- Après environ une minute sans message, Reverb envoie `pusher:ping`. Postman ne répond pas tout seul :
  envoyer `{"event":"pusher:pong"}`, sinon la connexion est coupée.
- Pour gagner du temps, enregistrer les requêtes dans une collection avec les variables
  `{{reverb_key}}`, `{{jwt}}` et `{{socket_id}}`.

## Intégration côté apps

- Clé : `REVERB_APP_KEY`. Hôte/port : ceux du serveur Reverb (en prod `wss://<domaine reverb>:443`).
- Canaux privés : préfixe `private-`, auth via l'endpoint client ou chauffeur avec le header `Authorization: Bearer <jwt>`.
- Nom d'event à écouter : `order.status.updated`, `order.created`, `order.invitation.created`.
  Avec Laravel Echo, préfixer d'un point (`.listen('.order.status.updated', …)`) car les events utilisent `broadcastAs()`.
- Toute librairie cliente Pusher fonctionne (pusher-js, Laravel Echo, clients Pusher Flutter/Dart…) en pointant l'hôte sur Reverb.

## Déploiement Railway

Un service Railway par processus, tous avec les mêmes variables `REVERB_APP_*` :

| Service | Script |
|---|---|
| API | démarrage habituel (`railway/init-app.sh`) |
| Worker | `railway/run-worker.sh` |
| Reverb | `railway/run-reverb.sh` |

Service **Reverb** : `run-reverb.sh` écoute sur `$PORT` injecté par Railway ; le domaine public du
service doit cibler ce même port.

Services **API et Worker** : pointer vers le domaine public de Reverb.

```dotenv
REVERB_HOST=<domaine-reverb>.up.railway.app
REVERB_PORT=443
REVERB_SCHEME=https
```

Les apps se connectent alors en `wss://<domaine-reverb>.up.railway.app` (port 443).

## Dépannage

| Symptôme | Cause probable |
|---|---|
| Aucun event reçu, rien dans les logs Reverb | `queue:work` non lancé, ou `BROADCAST_DRIVER` ≠ `reverb` (penser à `config:clear`) |
| Job en échec `cURL error 7 / Connection refused` | `REVERB_HOST`/`REVERB_PORT` ne pointent pas vers le serveur Reverb |
| Auth `401` | JWT absent, expiré, ou JWT client envoyé sur l'endpoint chauffeur (et inversement) |
| Auth `403` | Le canal n'appartient pas à l'utilisateur (autre `customerId`, commande d'un autre client…) |
| `pusher:error` code 4001 à la connexion | `REVERB_APP_KEY` côté client différente de celle du serveur |
| `pusher:error` code 4201 `Pong reply not received in time` | Le client n'a pas répondu au `pusher:ping` (Postman/wscat : envoyer `{"event":"pusher:pong"}` ; en local, on peut augmenter `REVERB_APP_PING_INTERVAL`). Sur mobile, souvent l'app en arrière-plan : le client Pusher se reconnecte seul |
| Connexion WebSocket refusée en prod | Mauvais port du domaine Railway, ou `ws://` au lieu de `wss://` |
| Code modifié non pris en compte | Redémarrer `queue:work` et `reverb:start` (processus longs) |

Les jobs en échec : `php artisan queue:failed`, relance avec `php artisan queue:retry all`.

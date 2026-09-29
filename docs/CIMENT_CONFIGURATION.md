# Configuration du Ciment (Agrégat)

## Vue d'ensemble

Le ciment est un **agrégat de construction** géré via le service `agregats-construction`.
Sa configuration se fait en **4 étapes séquentielles** dans l'interface d'administration.

```
1. Produit (products)          →  slug: "ciment", tonne_options: [...]
         ↓
2. Variantes (product_types)   →  ciment-cpa35, ciment-portland, etc. + prix/tonne
         ↓
3. Carrières (carriers)        →  products: ["ciment-portland", ...]  +  is_active: true
         ↓
4. Tarifications (settings)    →  CIMENT_* = valeurs réelles (≠ 0)
```

> **Attention :** Tant que les paramètres `CIMENT_*` sont tous à `0`, l'API
> `/api/orders/delivery/ciment/estimate_price` retournera un prix de `0 XOF`.
> Configurer les tarifications est la **dernière étape obligatoire** avant de rendre
> le ciment disponible en production.

---

## Étape 1 — Enregistrer le produit (Agrégat)

**Navigation :** Admin › Produits › Nouveau produit

| Champ | Valeur |
|---|---|
| `name` | Ciment |
| `slug` | `ciment` *(doit être exactement ce slug)* |
| `per` | Tonne |
| `pricing_title` | Prix par tonne |
| `description` | Livraison de ciment en sac ou en vrac |
| `color` | `#8B7355` *(ou couleur souhaitée)* |
| `icon` | URL de l'icône ciment |

### Options de tonnage (`tonne_options`)

Ce champ JSON définit les quantités proposées au client (en tonnes) :

```json
[1, 2, 3, 5, 10, 20, 25, 50]
```

> Adapter les valeurs selon les tonnages réalistes proposés sur le terrain.

---

## Étape 2 — Enregistrer les variantes (`product_types`)

**Navigation :** Admin › Types de produit › Nouveau type

Chaque variante représente une qualité ou une marque de ciment avec son prix unitaire à la tonne.

| `product_id` | `name` | `slug` | `price` (XOF) | `currency_code` |
|---|---|---|---|---|
| *(id ciment)* | CPA 35 | `ciment-cpa35` | ex : 85 000 | XOF |
| *(id ciment)* | CPA 45 | `ciment-cpa45` | ex : 90 000 | XOF |
| *(id ciment)* | CPJ 35 | `ciment-cpj35` | ex : 80 000 | XOF |
| *(id ciment)* | Portland | `ciment-portland` | ex : 95 000 | XOF |

> **Important :** Le `slug` de chaque type est référencé dans le champ `products` JSON
> des carrières (étape 3) et dans les `meta_data` des commandes.
> **Ne pas modifier un slug après la mise en production.**

Le champ `price` correspond au **prix de base à la tonne** (hors transport).
Il est utilisé pour calculer : `order_price = price × quantity`.

---

## Étape 3 — Attribuer le ciment aux carrières

**Navigation :** Admin › Carrières › Modifier une carrière

Le champ `products` de chaque carrière est un **tableau JSON** listant les slugs
des types de produits qu'elle propose.

### Exemple de configuration d'une carrière ciment

```json
{
  "name": "Carrière Ciment Abidjan",
  "phone": "+225 07 00 00 00",
  "location_latitude": 5.3545,
  "location_longitude": -4.0083,
  "is_active": true,
  "products": [
    "ciment-cpa35",
    "ciment-cpa45",
    "ciment-portland"
  ]
}
```

> Une même carrière peut distribuer plusieurs variantes.
> Seules les carrières avec `is_active = true` et dont `products` contient le slug
> demandé sont retournées lors d'une estimation de prix.

La sélection de la carrière la plus proche est automatique via le service
`CarrierLocationService` qui utilise une requête spatiale PostGIS.

---

## Étape 4 — Configurer les tarifications

**Navigation :** Admin › Paramètres › Tarification Ciment

Ces 8 clés dans la table `settings` contrôlent intégralement le calcul du prix de transport.

### Formule de calcul

```
Prix Transport =
    CIMENT_PRIX_DE_BASE
    + MAX(0, distance - CIMENT_DISTANCE_DE_BASE) × CIMENT_PRIX_KILOMETRE
    + MAX(0, quantité - CIMENT_QUANTITE_DE_BASE) × CIMENT_PRIX_TONNAGE
    + CIMENT_FRAIS_DE_ROUTE
    + CIMENT_COMMISSION_OUEGO
```

Le résultat est **arrondi au 100 XOF supérieur**, puis multiplié selon le type de livraison.

### Description des paramètres

| Clé | Unité | Rôle |
|---|---|---|
| `CIMENT_DISTANCE_DE_BASE` | km | Distance incluse dans le prix de base. En dessous de ce seuil, aucun supplément kilométrique n'est facturé. |
| `CIMENT_QUANTITE_DE_BASE` | tonnes | Quantité incluse dans le prix de base. En dessous, aucun supplément de tonnage n'est facturé. |
| `CIMENT_PRIX_DE_BASE` | XOF | Montant fixe facturé pour toute livraison, quel que soit la distance ou la quantité. |
| `CIMENT_PRIX_KILOMETRE` | XOF/km | Surcoût par kilomètre supplémentaire au-delà de `CIMENT_DISTANCE_DE_BASE`. |
| `CIMENT_PRIX_TONNAGE` | XOF/tonne | Surcoût par tonne supplémentaire au-delà de `CIMENT_QUANTITE_DE_BASE`. |
| `CIMENT_FRAIS_DE_ROUTE` | XOF | Frais fixes de péage/route ajoutés à chaque livraison. |
| `CIMENT_COMMISSION_OUEGO` | XOF | Commission Ouégo prélevée sur chaque commande. |
| `CIMENT_COMMISSION_OUEGO_MIN` | XOF | Commission minimum garantie. Si la commission calculée est inférieure à ce seuil, c'est ce minimum qui s'applique. |

### Modificateurs par type de livraison

| Type | Multiplicateur | Description |
|---|---|---|
| `EXPRESS` | × 1.0 | Tarif plein |
| `en-journee` | × 0.5 | Tarif réduit (livraison journée programmée) |
| `de-nuit` | × 2.5 | Tarif majoré (livraison nocturne) |
| `en-semaine` | × 0.33 | Tarif préférentiel (livraison en semaine) |

### Exemple de configuration réaliste

```
CIMENT_DISTANCE_DE_BASE     = 10      (10 km offerts)
CIMENT_QUANTITE_DE_BASE     = 5       (5 tonnes offertes)
CIMENT_PRIX_DE_BASE         = 15000   (15 000 XOF forfait)
CIMENT_PRIX_KILOMETRE       = 500     (500 XOF/km au-delà de 10 km)
CIMENT_PRIX_TONNAGE         = 1000    (1 000 XOF/tonne au-delà de 5 T)
CIMENT_FRAIS_DE_ROUTE       = 2000    (2 000 XOF de péage)
CIMENT_COMMISSION_OUEGO     = 3000    (3 000 XOF de commission)
CIMENT_COMMISSION_OUEGO_MIN = 2000    (2 000 XOF minimum)
```

**Simulation :** livraison EXPRESS, 45 km, 20 tonnes

```
= 15 000 + (45-10)×500 + (20-5)×1 000 + 2 000 + 3 000
= 15 000 + 17 500 + 15 000 + 2 000 + 3 000
= 52 500 XOF  →  arrondi à 52 600 XOF
```

---

## Références techniques

| Élément | Chemin |
|---|---|
| Modèle Product | `app/Models/Product.php` |
| Modèle ProductType | `app/Models/ProductType.php` |
| Modèle Carrier | `app/Models/Carrier.php` |
| Modèle Setting | `app/Models/Setting.php` |
| Calcul du prix | `app/Utilities/PricingUtils.php` — `transportCiment()` |
| Service géolocalisation | `app/Services/CarrierLocationService.php` |
| API estimation prix | `app/Http/Controllers/API/OrderAPIController.php` |
| Seeder produits | `database/seeders/ProductsSeeder.php` |
| Seeder types | `database/seeders/ProductTypesSeeder.php` |
| Seeder carrières | `database/seeders/CarriersSeeder.php` |
| Seeder paramètres | `database/seeders/SettingsSeeder.php` |
| Tests unitaires | `tests/Unit/PricingUtilsTest.php` |

### Endpoint API

```
POST /api/orders/delivery/ciment/estimate_price
Authorization: Bearer {token client}
```

**Corps de la requête :**

```json
{
  "service_slug": "agregats-construction",
  "meta_data": {
    "product_type_slug": "ciment-portland",
    "product_slug": "ciment",
    "delivery_type_code": "EXPRESS"
  },
  "quantity": 3,
  "route_points": [
    {
      "address_name": "Adresse de livraison",
      "latitude": 5.3994128,
      "longitude": -3.9999536,
      "type": "destination",
      "contact_fullname": "Nom du contact",
      "contact_phone": "+225 07 00 00 00"
    }
  ]
}
```

**Réponse :**

```json
{
  "carrier": { "...": "..." },
  "amount": 52600,
  "amount_with_discount": 50000,
  "discount": 2600,
  "has_commercial_discount": true,
  "distance": "45 km",
  "duration": "1 heure",
  "delivery_type": "EXPRESS",
  "is_available": true,
  "error_message": null
}
```

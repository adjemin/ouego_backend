# Configuration du Gravier (Agrégat)

## Vue d'ensemble

Le gravier est un **agrégat de construction** géré via le service `agregats-construction`.
Sa configuration se fait en **4 étapes séquentielles** dans l'interface d'administration.

```
1. Produit (products)          →  slug: "gravier", tonne_options: [20,25,30,35,40]
         ↓
2. Variantes (product_types)   →  gravier-515-petit-grain, gravier-525-melange, etc. + prix/tonne
         ↓
3. Carrières (carriers)        →  products: ["gravier-515-petit-grain", ...]  +  is_active: true
         ↓
4. Tarifications (settings)    →  GRAVIER_* = valeurs configurées
```

---

## Étape 1 — Enregistrer le produit (Agrégat)

**Navigation :** Admin › Produits › Nouveau produit

| Champ | Valeur |
|---|---|
| `name` | Gravier |
| `slug` | `gravier` *(doit être exactement ce slug)* |
| `per` | T |
| `pricing_title` | *(null)* |
| `description` | Les classifications de gravier comme 5/15, 5/25, et 15/25 font référence aux dimensions des granulats en millimètres, indiquant la plage de taille des particules de gravier. |
| `color` | `#ebdd5f` |
| `icon` | URL de l'icône gravier |

### Options de tonnage (`tonne_options`)

Ce champ JSON définit les quantités proposées au client (en tonnes) :

```json
[20, 25, 30, 35, 40]
```

> Le gravier utilise `tonne_options` pour le choix de la quantité. Le champ `pricings` est laissé vide (`[]`).

---

## Étape 2 — Enregistrer les variantes (`product_types`)

**Navigation :** Admin › Types de produit › Nouveau type

Chaque variante représente une granulométrie de gravier avec son prix unitaire à la tonne.

| `product_id` | `name` | `slug` | `price` (XOF) | `currency_code` |
|---|---|---|---|---|
| *(id gravier)* | Gravier 5/15 (Petit grain) | `gravier-515-petit-grain` | 6 500 | XOF |
| *(id gravier)* | Gravier 5/25 (Mélange) | `gravier-525-melange` | 6 500 | XOF |
| *(id gravier)* | Gravier 15/25 (Gros grain) | `gravier-1525-gros-grain` | 6 500 | XOF |

> **Important :** Le `slug` de chaque type est référencé dans le champ `products` JSON
> des carrières (étape 3) et dans les `meta_data` des commandes.
> **Ne pas modifier un slug après la mise en production.**

Le champ `price` correspond au **prix de base à la tonne** (hors transport).
Il est utilisé pour calculer : `order_price = price × quantity`.

---

## Étape 3 — Attribuer le gravier aux carrières

**Navigation :** Admin › Carrières › Modifier une carrière

Le champ `products` de chaque carrière est un **tableau JSON** listant les slugs
des types de produits qu'elle propose.

### Exemple de configuration d'une carrière gravier

```json
{
  "name": "SISAG Carrière",
  "phone": "+225 07 00 00 00",
  "location_latitude": 5.3545,
  "location_longitude": -4.0083,
  "is_active": true,
  "products": [
    "gravier-515-petit-grain",
    "gravier-525-melange",
    "gravier-1525-gros-grain"
  ]
}
```

### Carrières gravier actuellement configurées

| Nom | Statut |
|---|---|
| SISAG Carrière | Actif |
| Carrière PK 36 | Actif |
| Carrière de granite AMG | Actif |
| Carrière Visitée | Actif |
| Abeille Groupe Carrière Bago | Actif |
| CMR GRANIT (Carrière) | Actif |
| ABEILLE CARRIERE MBRAGO | Actif |
| Carrière soremi | Actif |
| soligra ci | Actif |
| Carriere Kossihouen CADERAC | Inactif |

> Seules les carrières avec `is_active = true` et dont `products` contient le slug
> demandé sont retournées lors d'une estimation de prix.

La sélection de la carrière la plus proche est automatique via le service
`CarrierLocationService` qui utilise une requête spatiale PostGIS.

---

## Étape 4 — Configurer les tarifications

**Navigation :** Admin › Paramètres › Tarification Gravier

Ces 8 clés dans la table `settings` contrôlent intégralement le calcul du prix de transport.

### Formule de calcul

```
Prix Transport =
    GRAVIER_PRIX_DE_BASE
    + MAX(0, distance - GRAVIER_DISTANCE_DE_BASE) × GRAVIER_PRIX_KILOMETRE
    + MAX(0, quantité - GRAVIER_QUANTITE_DE_BASE) × GRAVIER_PRIX_TONNAGE
    + GRAVIER_FRAIS_DE_ROUTE
    + GRAVIER_COMMISSION_OUEGO
```

Le résultat est **arrondi au 100 XOF supérieur**, puis multiplié selon le type de livraison.

### Description des paramètres

| Clé | Valeur actuelle | Unité | Rôle |
|---|---|---|---|
| `GRAVIER_DISTANCE_DE_BASE` | 45 | km | Distance incluse dans le prix de base. En dessous de ce seuil, aucun supplément kilométrique n'est facturé. |
| `GRAVIER_QUANTITE_DE_BASE` | 20 | tonnes | Quantité incluse dans le prix de base. En dessous, aucun supplément de tonnage n'est facturé. |
| `GRAVIER_PRIX_DE_BASE` | 55 000 | XOF | Montant fixe facturé pour toute livraison, quelle que soit la distance ou la quantité. |
| `GRAVIER_PRIX_KILOMETRE` | 1 000 | XOF/km | Surcoût par kilomètre supplémentaire au-delà de `GRAVIER_DISTANCE_DE_BASE`. |
| `GRAVIER_PRIX_TONNAGE` | 1 000 | XOF/tonne | Surcoût par tonne supplémentaire au-delà de `GRAVIER_QUANTITE_DE_BASE`. |
| `GRAVIER_FRAIS_DE_ROUTE` | 10 000 | XOF | Frais fixes de péage/route ajoutés à chaque livraison. |
| `GRAVIER_COMMISSION_OUEGO` | 5 000 | XOF | Commission Ouégo prélevée sur chaque commande. |
| `GRAVIER_COMMISSION_OUEGO_MIN` | 5 000 | XOF | Commission minimum garantie. Si la commission calculée est inférieure à ce seuil, c'est ce minimum qui s'applique. |

### Modificateurs par type de livraison

| Type | Multiplicateur | Description |
|---|---|---|
| `EXPRESS` | × 1.0 | Tarif plein |
| `en-journee` | × 0.5 | Tarif réduit (livraison journée programmée) |
| `de-nuit` | × 2.5 | Tarif majoré (livraison nocturne) |
| `en-semaine` | × 0.33 | Tarif préférentiel (livraison en semaine) |

### Simulations de prix

| Distance | Quantité | Type | Calcul | Prix final |
|---|---|---|---|---|
| 45 km | 20 T | EXPRESS | 55 000 + 0 + 0 + 10 000 + 5 000 | **70 000 XOF** |
| 49.8 km | 20 T | EXPRESS | 55 000 + (4.8×1 000) + 0 + 10 000 + 5 000 | **74 800 XOF** |
| 45 km | 35 T | EXPRESS | 55 000 + 0 + (15×1 000) + 10 000 + 5 000 | **85 000 XOF** |
| 45 km | 20 T | en-journee | 70 000 × 0.5 | **35 000 XOF** |
| 45 km | 20 T | de-nuit | 70 000 × 2.5 | **175 000 XOF** |
| 45 km | 20 T | en-semaine | 70 000 × 0.33 | **23 100 XOF** |

---

## Références techniques

| Élément | Chemin |
|---|---|
| Modèle Product | `app/Models/Product.php` |
| Modèle ProductType | `app/Models/ProductType.php` |
| Modèle Carrier | `app/Models/Carrier.php` |
| Modèle Setting | `app/Models/Setting.php` |
| Calcul du prix | `app/Utilities/PricingUtils.php` — `transportGravier()` |
| Service géolocalisation | `app/Services/CarrierLocationService.php` |
| API estimation prix | `app/Http/Controllers/API/OrderAPIController.php` — `estimateDeliveryPriceGravier()` |
| Seeder produits | `database/seeders/ProductsSeeder.php` |
| Seeder types | `database/seeders/ProductTypesSeeder.php` |
| Seeder carrières | `database/seeders/CarriersSeeder.php` |
| Seeder paramètres | `database/seeders/SettingsSeeder.php` |
| Tests unitaires | `tests/Unit/PricingUtilsTest.php` |

### Endpoint API

```
POST /api/v1/orders/delivery/gravier/estimate_price
Authorization: Bearer {token client}
```

**Corps de la requête :**

```json
{
  "service_slug": "agregats-construction",
  "meta_data": {
    "product_type_slug": "gravier-515-petit-grain",
    "product_slug": "gravier",
    "delivery_type_code": "EXPRESS"
  },
  "quantity": 25,
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
  "amount": 70000,
  "amount_with_discount": 67500,
  "discount": 2500,
  "has_commercial_discount": true,
  "distance": "45 km",
  "duration": "1 heure",
  "delivery_type": "EXPRESS",
  "is_available": true,
  "error_message": null
}
```

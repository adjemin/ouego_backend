# Configuration du Sable (Agrégat)

## Vue d'ensemble

Le sable est un **agrégat de construction** géré via le service `agregats-construction`.
Sa configuration se fait en **4 étapes séquentielles** dans l'interface d'administration.

> **Différence clé avec le gravier et le ciment :** Le prix de transport du sable
> **ne dépend pas de la quantité**. Il est calculé uniquement sur la distance.
> La quantité influe uniquement sur le `order_price` (prix du produit).

```
1. Produit (products)          →  slug: "sable", pricings: [{6 roues}, {10 roues}, {12 roues}]
         ↓
2. Variantes (product_types)   →  sable-fin, sable-petit-grain, sable-gros-grain + prix/tonne
         ↓
3. Carrières (carriers)        →  products: ["sable-fin", ...]  +  is_active: true
         ↓
4. Tarifications (settings)    →  SABLE_* = valeurs configurées
```

---

## Étape 1 — Enregistrer le produit (Agrégat)

**Navigation :** Admin › Produits › Nouveau produit

| Champ | Valeur |
|---|---|
| `name` | Sable |
| `slug` | `sable` *(doit être exactement ce slug)* |
| `per` | T |
| `pricing_title` | *(null)* |
| `description` | Le sable est un composant essentiel dans le secteur de la construction, utilisé pour diverses applications telles que le mortier, le béton, et le remblayage. |
| `color` | `#ed5d3e` |
| `icon` | URL de l'icône sable |

### Tarification par type de camion (`pricings`)

Le sable utilise le champ `pricings` (et non `tonne_options`) pour proposer des forfaits
selon le type de véhicule. Le champ `tonne_options` est laissé vide (`[]`).

```json
[
  {"name": "6 roues (8m3)",  "roues": "6",  "price": "25000"},
  {"name": "10 roues (12m3)", "roues": "10", "price": "45000"},
  {"name": "12 roues (20m3)", "roues": "12", "price": "90000"}
]
```

| Type de camion | Volume | Prix forfaitaire |
|---|---|---|
| 6 roues | 8 m³ | 25 000 XOF |
| 10 roues | 12 m³ | 45 000 XOF |
| 12 roues | 20 m³ | 90 000 XOF |

---

## Étape 2 — Enregistrer les variantes (`product_types`)

**Navigation :** Admin › Types de produit › Nouveau type

Chaque variante représente une granulométrie de sable avec son prix unitaire à la tonne.

| `product_id` | `name` | `slug` | `price` (XOF) | `currency_code` |
|---|---|---|---|---|
| *(id sable)* | Sable fin | `sable-fin` | 3 000 | XOF |
| *(id sable)* | Sable petit grain | `sable-petit-grain` | 4 000 | XOF |
| *(id sable)* | Sable gros grain | `sable-gros-grain` | 5 000 | XOF |

> **Important :** Le `slug` de chaque type est référencé dans le champ `products` JSON
> des carrières (étape 3) et dans les `meta_data` des commandes.
> **Ne pas modifier un slug après la mise en production.**

Le champ `price` correspond au **prix de base à la tonne** (hors transport).
Il est utilisé pour calculer : `order_price = price × quantity`.

---

## Étape 3 — Attribuer le sable aux carrières

**Navigation :** Admin › Carrières › Modifier une carrière

Le champ `products` de chaque carrière est un **tableau JSON** listant les slugs
des types de produits qu'elle propose. Une carrière peut ne proposer qu'un seul type
ou plusieurs selon sa disponibilité.

### Exemple de configuration d'une carrière sable

```json
{
  "name": "Carrière Koumassi commando",
  "phone": "+225 07 00 00 00",
  "location_latitude": 5.3200,
  "location_longitude": -3.9800,
  "is_active": true,
  "products": [
    "sable-fin",
    "sable-petit-grain",
    "sable-gros-grain"
  ]
}
```

### Carrières sable actuellement configurées

| Nom | Produits proposés | Statut |
|---|---|---|
| Carrière Abobo Doumé | `sable-gros-grain` | Actif |
| Carrière Koumassi commando | `sable-fin`, `sable-petit-grain`, `sable-gros-grain` | Actif |
| Carrière Agban CEFAL | `sable-gros-grain`, `sable-petit-grain`, `sable-fin` | Actif |
| Carrière Diamant noir | *(variable)* | Inactif |
| Carrière Diakité | *(variable)* | Inactif |
| Carrière De Sable Tian.cheng | *(variable)* | Inactif |

> Seules les carrières avec `is_active = true` et dont `products` contient le slug
> demandé sont retournées lors d'une estimation de prix.

La sélection de la carrière la plus proche est automatique via le service
`CarrierLocationService` qui utilise une requête spatiale PostGIS.

---

## Étape 4 — Configurer les tarifications

**Navigation :** Admin › Paramètres › Tarification Sable

Ces 6 clés dans la table `settings` contrôlent intégralement le calcul du prix de transport.

### Formule de calcul

```
Prix Transport =
    SABLE_PRIX_DE_BASE
    + MAX(0, distance - SABLE_DISTANCE_DE_BASE) × SABLE_PRIX_KILOMETRE
    + SABLE_FRAIS_DE_ROUTE
    + SABLE_COMMISSION_OUEGO
```

> Le sable **n'a pas** de paramètre `SABLE_QUANTITE_DE_BASE` ni `SABLE_PRIX_TONNAGE`.
> Le transport est forfaitaire à la distance, indépendamment du tonnage commandé.

Le résultat est **arrondi au 100 XOF supérieur**, puis multiplié selon le type de livraison.

### Description des paramètres

| Clé | Valeur actuelle | Unité | Rôle |
|---|---|---|---|
| `SABLE_DISTANCE_DE_BASE` | 5 | km | Distance incluse dans le prix de base. En dessous de ce seuil, aucun supplément kilométrique n'est facturé. |
| `SABLE_PRIX_DE_BASE` | 20 000 | XOF | Montant fixe facturé pour toute livraison, quelle que soit la quantité. |
| `SABLE_PRIX_KILOMETRE` | 1 000 | XOF/km | Surcoût par kilomètre supplémentaire au-delà de `SABLE_DISTANCE_DE_BASE`. |
| `SABLE_FRAIS_DE_ROUTE` | 0 | XOF | Frais fixes de péage/route ajoutés à chaque livraison. |
| `SABLE_COMMISSION_OUEGO` | 5 000 | XOF | Commission Ouégo prélevée sur chaque commande. |
| `SABLE_COMMISSION_OUEGO_MIN` | 5 000 | XOF | Commission minimum garantie. Si la commission calculée est inférieure à ce seuil, c'est ce minimum qui s'applique. |

### Modificateurs par type de livraison

| Type | Multiplicateur | Description |
|---|---|---|
| `EXPRESS` | × 1.0 | Tarif plein |
| `en-journee` | × 0.5 | Tarif réduit (livraison journée programmée) |
| `de-nuit` | × 2.5 | Tarif majoré (livraison nocturne) |
| `en-semaine` | × 0.33 | Tarif préférentiel (livraison en semaine) |

### Simulations de prix

| Distance | Type | Calcul | Prix final |
|---|---|---|---|
| 5 km | EXPRESS | 20 000 + 0 + 0 + 5 000 | **25 000 XOF** |
| 10 km | EXPRESS | 20 000 + (5×1 000) + 0 + 5 000 | **30 000 XOF** |
| 5 km | en-journee | 25 000 × 0.5 | **12 500 XOF** |
| 5 km | de-nuit | 25 000 × 2.5 | **62 500 XOF** |
| 5 km | en-semaine | 25 000 × 0.33 | **8 300 XOF** |

> La quantité commandée (nombre de tonnes) **n'a aucun impact** sur le prix de transport.
> Que le client commande 1 tonne ou 20 tonnes, le transport reste le même.

---

## Comparatif Sable / Gravier / Ciment

| Paramètre | Sable | Gravier | Ciment |
|---|---|---|---|
| Transport basé sur la distance | Oui | Oui | Oui |
| Transport basé sur la quantité | **Non** | Oui | Oui |
| Choix par `tonne_options` | Non | Oui | Oui |
| Choix par `pricings` (type camion) | **Oui** | Non | Non |
| Distance de base | 5 km | 45 km | à configurer |
| Prix de base | 20 000 XOF | 55 000 XOF | à configurer |
| Frais de route | 0 XOF | 10 000 XOF | à configurer |
| Commission Ouégo | 5 000 XOF | 5 000 XOF | à configurer |

---

## Références techniques

| Élément | Chemin |
|---|---|
| Modèle Product | `app/Models/Product.php` |
| Modèle ProductType | `app/Models/ProductType.php` |
| Modèle Carrier | `app/Models/Carrier.php` |
| Modèle Setting | `app/Models/Setting.php` |
| Calcul du prix | `app/Utilities/PricingUtils.php` — `transportSable()` |
| Service géolocalisation | `app/Services/CarrierLocationService.php` |
| API estimation prix | `app/Http/Controllers/API/OrderAPIController.php` — `estimateDeliveryPriceSable()` |
| Seeder produits | `database/seeders/ProductsSeeder.php` |
| Seeder types | `database/seeders/ProductTypesSeeder.php` |
| Seeder carrières | `database/seeders/CarriersSeeder.php` |
| Seeder paramètres | `database/seeders/SettingsSeeder.php` |
| Tests unitaires | `tests/Unit/PricingUtilsTest.php` |

### Endpoint API

```
POST /api/v1/orders/delivery/sable/estimate_price
Authorization: Bearer {token client}
```

**Corps de la requête :**

```json
{
  "service_slug": "agregats-construction",
  "meta_data": {
    "product_type_slug": "sable-fin",
    "product_slug": "sable",
    "delivery_type_code": "EXPRESS"
  },
  "quantity": 8,
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
  "amount": 25000,
  "amount_with_discount": 23000,
  "discount": 2000,
  "has_commercial_discount": true,
  "distance": "5 km",
  "duration": "30 minutes",
  "delivery_type": "EXPRESS",
  "is_available": true,
  "error_message": null
}
```

# Règles de passation et d'attribution des commandes OUEGO

> **Public :** toutes les équipes (opérations, support client, commercial, produit, tech).
> **Source :** règles extraites du code du backend OUEGO (`ouego_backend`), état au 2 octobre 2026.
> **Objet :** expliquer comment un client passe une commande, et comment la plateforme choisit le chauffeur qui la réalisera.

---

## 1. En bref

- Un client passe une commande pour l'un des **3 services** : **Course**, **Agrégats de construction** (sable, gravier, ciment) ou **Location d'engin**.
- Pour les courses et les agrégats, il choisit un **type de livraison** : **Express**, **En journée**, **En semaine** ou **De nuit**. Certains types ne sont disponibles qu'à certaines heures.
- Une fois la commande confirmée, la plateforme **n'impose pas** la commande à un chauffeur : elle envoie une **invitation** à **5 chauffeurs au maximum**, choisis selon des règles d'éligibilité et de priorité.
- **Le premier chauffeur qui accepte obtient la commande.** Les autres invitations sont alors annulées.
- Sans acceptation, la recherche est relancée **toutes les 2 minutes**. Une commande toujours sans chauffeur passe au statut **« Chauffeur non trouvé »** **5 minutes** après sa confirmation, sauf les commandes **de nuit** (à 07h00) et les **locations réservées à l'avance** (à la date de début).

---

## 2. Vocabulaire

| Terme | Signification |
|---|---|
| **Commande** | Demande d'un client (une course, une livraison d'agrégats ou une location). |
| **Service** | Nature de la commande : `course`, `agregats-construction` ou `location`. |
| **Type de livraison** | Délai ou créneau choisi : `EXPRESS`, `en-journee`, `en-semaine` ou `de-nuit`. |
| **Invitation** | Proposition de commande envoyée à un chauffeur, qui peut l'**accepter** ou la **refuser**. |
| **Carrière** | Point d'enlèvement des agrégats (sable, gravier, ciment). |
| **Chauffeur rattaché** | Chauffeur associé à une carrière donnée. Seuls ces chauffeurs peuvent livrer les agrégats de cette carrière. |
| **Commande active** | Commande confirmée (pas un brouillon) et pas encore terminée. |
| **Commande démarrée** | Commande que le chauffeur a commencée ou est en train de réaliser. |
| **Jetons** | Solde du chauffeur sur la plateforme (`current_balance`). |

---

## 3. Passation de commande

### 3.1 Les étapes

```
1. Estimation du prix         →  le client voit les prix et les types de livraison disponibles
2. Création de la commande    →  statut « initiated »
3. Confirmation par le client →  statut « performer_lookup » (recherche de chauffeur)
                                  → les invitations partent immédiatement
```

1. **Estimation** : l'application calcule le prix pour chaque type de livraison et indique si chacun est **disponible maintenant**. Un type indisponible est affiché avec un message d'explication.
2. **Création** : le client envoie sa commande avec un **mode de paiement** et **au moins un article**. La plateforme vérifie les créneaux horaires (voir 3.3), calcule le prix et enregistre la commande.
3. **Confirmation** : la commande passe en **recherche de chauffeur** et la plateforme envoie immédiatement les premières invitations (voir section 4).

### 3.2 Informations obligatoires selon le service

| Service | Informations obligatoires |
|---|---|
| **Course** | Type de livraison, modèle d'engin, points de départ et d'arrivée |
| **Agrégats** (sable, gravier, ciment) | Type de livraison, carrière, produit, quantité, point de livraison |
| **Location** | Type d'engin, modèle d'engin, quantité, date de début, date de fin, adresse |

**Pour les agrégats**, la carrière est choisie au moment de l'estimation : la plateforme repère la **zone de livraison** du client et propose les carrières **actives** de cette zone qui **ont le produit demandé**, de la plus proche à la plus éloignée. Si l'adresse du client n'est dans aucune zone couverte, la commande est impossible (« votre position n'est pas couverte par notre zone de livraison »).

**Pour la location**, le client peut choisir le service de **jour**, de **nuit** ou **double** (jour + nuit). Le service double compte deux fois le prix journalier. La durée facturée est le nombre de jours entre la date de début et la date de fin, avec un minimum d'un jour.

### 3.3 Créneaux horaires par type de livraison

| Type de livraison | Quand peut-on commander ? | Contrôle |
|---|---|---|
| **Express** | Toute la journée **sauf de 06h00 à 09h00 et de 17h00 à 19h30** | Affiché à l'estimation (option grisée) **et** bloqué à la création |
| **En journée** | De **06h00 à 12h00** (heure limite exclue, réglable, 12h par défaut) | Affiché à l'estimation (option grisée) **et** bloqué à la création |
| **En semaine** | Tous les jours (la restriction du lundi au jeudi est **désactivée**) | — |
| **De nuit** | De **07h00 à 19h30** | Affiché à l'estimation **et** bloqué à la création |
| **Location** | Sans restriction horaire | — |

> Une commande **de nuit** se passe pendant la journée, mais la recherche de chauffeur ne démarre qu'**à partir de 20h00** (voir 4.5).

---

## 4. Attribution de commande

### 4.1 Le principe : l'invitation

La plateforme ne choisit pas un seul chauffeur. Elle :

1. dresse la liste des chauffeurs **éligibles** (4.2 à 4.4) ;
2. les **classe** (le plus proche d'abord, ou par score pour les agrégats) ;
3. envoie une **invitation aux 5 premiers**, par notification push : *« Course #123 vous a été affectée — Acceptez ou Refusez la course »*.

Ensuite :

- **Le premier chauffeur qui accepte** devient le chauffeur de la commande. La commande passe au statut **« performer_found »** et le client reçoit la notification *« Nous avons trouvé un conducteur pour la course »*.
- Toutes les autres invitations en attente pour cette commande sont **annulées**.
- Si un chauffeur **accepte trop tard** (commande déjà terminée ou attribuée), il reçoit le message « Affectation déjà traitée » ou « Order already completed ».
- Un **refus** ferme seulement l'invitation de ce chauffeur. La recherche ne repart pas tout de suite : elle reprend au prochain passage automatique (au plus 2 minutes).

### 4.2 Conditions communes à tous les chauffeurs

Pour recevoir une invitation, un chauffeur doit **toujours** :

| # | Condition |
|---|---|
| 1 | Être **disponible** (en ligne dans l'application) |
| 2 | Avoir un compte **actif** |
| 3 | Avoir donné signe de vie (position ou activité) **dans les 30 dernières minutes** |
| 4 | Proposer le **service** de la commande (course, agrégats ou location) dans son profil |

### 4.3 Limites de charge du chauffeur (courses et agrégats)

Pour éviter qu'un chauffeur ne prenne trop de commandes, la plateforme applique ces limites, **quel que soit le type de la nouvelle commande** :

| Règle | Détail |
|---|---|
| **Maximum 3 commandes « en journée »** actives | Au-delà, le chauffeur ne reçoit plus d'invitation. |
| **Maximum 5 commandes « en semaine »** actives | Au-delà, le chauffeur ne reçoit plus d'invitation. |
| **Règle du samedi** | Le samedi, un chauffeur qui a **au moins une commande « en semaine » active** ne reçoit **aucune** nouvelle invitation. |
| **Location en cours** | Un chauffeur dont une **location est en cours aujourd'hui** ne reçoit pas d'invitation (avec une exception pour le type « en semaine », voir 4.5). |

### 4.4 Règles par type de livraison

#### Express

Règles **les plus strictes**, car le chauffeur doit partir tout de suite. En plus des sections 4.2 et 4.3, le chauffeur doit :

- n'avoir **aucune commande démarrée** (tous types confondus) ;
- n'avoir **aucune commande Express** active non terminée, même en attente ;
- **après l'heure limite de la journée** (12h par défaut) : ne pas avoir 3 commandes « en journée » non terminées ou plus ;
- **entre minuit et 07h00** : avoir **moins de 3 commandes de nuit** actives.

#### En journée

Conditions communes (4.2) et limites de charge (4.3) seulement. Un chauffeur peut recevoir une commande « en journée » même s'il a déjà une autre commande en cours.

#### En semaine

Conditions communes (4.2) et limites de charge (4.3), avec une **exception pour les locations** :

- **du lundi au jeudi** : un chauffeur dont la location **se termine aujourd'hui** (dernier jour) peut quand même recevoir une commande « en semaine » ;
- **du vendredi au dimanche** : pas d'exception, un chauffeur avec une location en cours aujourd'hui est exclu.

#### De nuit

- La recherche n'a lieu que **de 20h00 à 07h00**. Une commande de nuit confirmée en journée attend 20h00, sans être clôturée en attendant.
- De 20h00 à 07h00, la recherche est relancée **toutes les 2 minutes** jusqu'à ce qu'un chauffeur accepte.
- À **07h00**, une commande de nuit toujours sans chauffeur passe en **« Chauffeur non trouvé »**.
- À **20h00**, une tâche automatique lance la recherche pour toutes les commandes de nuit sans chauffeur.
- À **05h00**, une tâche automatique **réattribue** toutes les commandes de nuit **pas encore démarrées**, même déjà acceptées : le chauffeur est retiré, les invitations en attente sont annulées et une nouvelle recherche est lancée.
- Critères d'éligibilité : conditions communes (4.2) et limites de charge (4.3).

### 4.5 Comment les chauffeurs sont classés

#### Courses : le plus proche d'abord

- Point de référence : le **point de départ** de la course.
- **Rayon de recherche : 10 km.**
- Les **5 chauffeurs éligibles les plus proches** sont invités.

#### Agrégats : un score de pertinence

- Seuls les **chauffeurs rattachés à la carrière** de la commande sont considérés.
- Point de référence : la **carrière** (point d'enlèvement).
- **Rayon de recherche :** 10 km autour de la carrière pour le **sable** ; **aucune limite de distance** pour le **gravier** et le **ciment**.
- Chaque chauffeur reçoit un **score sur 100** ; les **5 meilleurs scores** sont invités.

| Critère | Poids | Ce qui fait monter le score |
|---|---|---|
| Proximité chauffeur ↔ carrière | **35 %** | Être le plus proche de la carrière |
| Jetons | **25 %** | Avoir un solde élevé par rapport aux autres candidats (un solde négatif compte pour zéro) |
| Proximité chauffeur ↔ client | **25 %** | Être le plus proche du point de livraison |
| Note du chauffeur | **15 %** | Avoir une bonne note (sur 5) |

Pour les deux critères de proximité, les distances de moins de **100 m** (précision du GPS) sont considérées comme égales.

#### Location : disponibilité sur toute la période

Pour une location, la plateforme vérifie que le chauffeur est **libre sur toute la période demandée** :

- même conditions communes (4.2) ;
- le chauffeur ne doit avoir **aucune autre location** qui chevauche les dates demandées (hors locations annulées ou échouées) ;
- les limites de charge de la section 4.3 **ne s'appliquent pas** ;
- **aucun rayon** de distance.

Classement :

| Situation | Ordre de priorité |
|---|---|
| **Location urgente** (début dans moins de 24h) | 1. Chauffeurs **sans location en cours aujourd'hui** → 2. Chauffeurs avec **le moins de commandes « en semaine » non terminées** → 3. Les plus proches |
| **Location non urgente** | Les plus proches de l'adresse |

---

## 5. Cycle de vie et délais

```
Confirmation (t = 0)
   │  invitations envoyées à 5 chauffeurs max
   ▼
Toutes les 2 min : tâche automatique
   │  • une invitation sans réponse depuis plus de 2 min est supprimée
   │  • avant l'échéance → nouvelle recherche (de nouveaux chauffeurs peuvent être invités)
   │  • après l'échéance → statut « performer_not_found » (commande close)
   ▼
Un chauffeur accepte → « performer_found » → le client est notifié
```

| Délai | Valeur |
|---|---|
| Fréquence de relance de la recherche | **toutes les 2 minutes** |
| Durée de vie d'une invitation sans réponse | **2 minutes** |
| Échéance avant « Chauffeur non trouvé » (cas général) | **5 minutes** après la confirmation de la commande |
| Échéance pour une commande **de nuit** | **07h00** à la fin de la nuit (la recherche ne commence qu'à 20h00) |
| Échéance pour une **location** | Début du **jour de début de location** (au moins 5 minutes après la confirmation) |
| Nombre de chauffeurs invités par recherche | **5** |
| Rayon de recherche (courses, sable) | **10 km** |
| Chauffeur considéré comme hors ligne après | **30 minutes** sans activité |

Le client (ou l'application) peut aussi **relancer manuellement** la recherche. La relance ne fait quelque chose que si la commande n'a pas de chauffeur **et** qu'aucune invitation n'est en attente.

### Principaux statuts de commande

| Statut | Signification |
|---|---|
| `initiated` | Commande créée, pas encore confirmée |
| `performer_lookup` | Recherche de chauffeur en cours |
| `performer_found` | Un chauffeur a accepté |
| `performer_not_found` | Aucun chauffeur n'a accepté à temps, commande close |
| `pickup_arrived` / `pickuped` | Chauffeur arrivé à l'enlèvement / colis récupéré |
| `delivery_arrived` / `delivered` | Chauffeur arrivé à la livraison / livré |
| `cancelled`, `cancelled_with_payment`, `cancelled_by_taxi`, `failed` | Commande annulée ou échouée |

---

## 6. Tâches automatiques

| Heure | Tâche | Effet |
|---|---|---|
| Toutes les 2 min | Traitement des commandes en attente | Expire les invitations, relance la recherche ou clôt la commande en « Chauffeur non trouvé » une fois l'échéance passée. Ignore les commandes de nuit avant 20h00. |
| Tous les jours à **20h00** | Attribution des commandes de nuit | Lance la recherche pour les commandes de nuit sans chauffeur |
| Tous les jours à **05h00** | Réattribution des commandes de nuit | Retire le chauffeur des commandes de nuit non démarrées et relance la recherche |

---

## 7. Paramètres réglables sans développement

| Paramètre | Valeur par défaut | Effet |
|---|---|---|
| `JOURNEE_CUTOFF_HOUR` | `12` | Heure limite pour commander « en journée », également utilisée dans les règles Express |

Les autres valeurs (5 chauffeurs, 10 km, 2 min, 5 min, 30 min, créneaux Express et De nuit, pondérations du score) sont **fixées dans le code**. Pour les modifier, il faut une intervention de l'équipe technique.

---

## 8. Points d'attention (écarts constatés dans le code actuel)

Cette section liste les comportements actuels qui ne correspondent probablement pas à l'intention métier. Ils sont signalés pour être arbitrés, **pas encore corrigés**.

1. ~~**Commandes de nuit closes avant 20h.**~~ **Corrigé.** Les commandes de nuit attendent 20h00 et restent en recherche jusqu'à 07h00. Les locations restent en recherche jusqu'à leur date de début. La recherche de nuit fonctionne aussi après minuit, ce qui rend effective la réattribution de 05h00.
2. ~~**Délai de 5 minutes calculé depuis la création, pas depuis la confirmation.**~~ **Corrigé.** Les délais sont maintenant comptés à partir de la confirmation de la commande par le client.
3. ~~**Créneau Express non bloqué à la création.**~~ **Corrigé.** Le créneau (06h00–09h00 et 17h00–19h30) est défini à un seul endroit. Il est appliqué à toutes les estimations, y compris la course avec arrêts, et bloque la création d'une commande Express.
4. ~~**Heure limite « en journée ».**~~ **Corrigé.** Une commande « en journée » est possible de 06h00 jusqu'à l'heure limite exclue (12h00 par défaut). Ce créneau est appliqué à toutes les estimations et à la création ; il avait été désactivé dans les estimations le 18 septembre puis réactivé par erreur à la création seulement. Si le paramètre `JOURNEE_CUTOFF_HOUR` est absent, l'heure limite retombe sur 12h (elle tombait auparavant à 0h, ce qui bloquait toutes les commandes).
5. ~~**Score des agrégats.**~~ **Corrigé.** Les deux critères qui valaient toujours 100 % (hérités d'une version où l'on choisissait aussi la carrière) sont remplacés : la proximité chauffeur ↔ client (25 %) et un poids plus fort pour la proximité chauffeur ↔ carrière (35 %). Un chauffeur situé exactement sur la carrière ne provoque plus d'erreur : auparavant, une division par zéro empêchait l'envoi de toute invitation pour la commande.
6. **Refus d'un chauffeur.** Un refus ne relance pas immédiatement la recherche : il faut attendre la tâche suivante (jusqu'à 2 minutes).

---

*Document généré à partir du code source. En cas de doute, demander confirmation à l'équipe technique.*

# IPAM — SAE 2.03

Plateforme web de gestion d'adresses IP (IPAM) pour un operateur multi-sites
en MPLS : attribution automatique d'un sous-reseau `/28`, d'un VLAN et d'une
VRF a chaque nouveau client, avec generation de la configuration Cisco IOS
associee (VRF, sous-interface, MP-BGP, OSPF).

---

## Sommaire

1. [Presentation du projet](#1-presentation-du-projet)
2. [Architecture technique](#2-architecture-technique)
3. [Prerequis](#3-prerequis)
4. [Installation et lancement](#4-installation-et-lancement)
5. [Utilisation de l'application](#5-utilisation-de-lapplication)
6. [Base de donnees](#6-base-de-donnees)
7. [Plan d'adressage](#7-plan-dadressage)
8. [Generation de configuration Cisco](#8-generation-de-configuration-cisco)
9. [Choix techniques](#9-choix-techniques)
10. [Tests et validation](#10-tests-et-validation)
11. [Limites et evolutions possibles](#11-limites-et-evolutions-possibles)

---

## 1. Presentation du projet

Cette plateforme IPAM permet a un operateur de gerer l'attribution d'adresses
IP a ses clients sur plusieurs sites. Elle couvre :

- l'attribution automatique d'un sous-reseau `/28` a chaque nouveau client ;
- le stockage centralise dans une base MySQL accessible depuis tous les sites ;
- une interface web pour consulter (avec recherche et filtres), creer et
  supprimer des clients, sur un site ou sur tous les sites a la fois ;
- la generation automatique de la configuration Cisco IOS associee (VRF,
  sous-interface, MP-BGP, OSPF).

## 2. Architecture technique

```
┌─────────────────────────────────────────────────────┐
│                   Navigateur web                    │
└───────────────────────┬─────────────────────────────┘
                         │ HTTP
┌───────────────────────▼─────────────────────────────┐
│              Serveur Apache/PHP 8+                   │
│  index.php  │  ajouter.php  │  voir.php              │
│  supprimer.php  │  entete.php / pied.php  │ style.css │
│  fonctions.php (logique metier)  │  db.php (PDO)      │
└───────────────────────┬─────────────────────────────┘
                         │ PDO / MySQL
┌───────────────────────▼─────────────────────────────┐
│             Serveur MySQL/MariaDB central            │
│              Base de donnees : ipam                  │
└─────────────────────────────────────────────────────┘
```

**Fichiers du projet :**

| Fichier               | Role                                                          |
|------------------------|----------------------------------------------------------------|
| `init.py`              | Script d'automatisation : execute `ipam.sql` et verifie/complete les donnees de depart |
| `ipam.sql`             | Creation de la base + donnees de depart (sites, PE, pools, /28) |
| `config.php`           | Parametres (BDD, site gere, AS, masque, interface PE)          |
| `db.php`               | Connexion PDO a la base MySQL                                  |
| `fonctions.php`        | Automatisation : recherche/filtres, attribution, generation config, ajout/suppression, stats |
| `entete.php` / `pied.php` | Gabarit HTML commun (nav, footer)                            |
| `index.php`            | Dashboard + liste des clients (recherche, filtres site/statut) |
| `ajouter.php`          | Formulaire de creation d'un client                              |
| `voir.php`             | Fiche client + configuration Cisco generee                     |
| `supprimer.php`        | Suppression d'un client + liberation du `/28`                  |
| `style.css`            | Mise en forme                                                   |

## 3. Prerequis

| Composant | Version minimum | Notes |
|-----------|------------------|-------|
| PHP       | 8.0+             | Extension `pdo_mysql` requise |
| MySQL / MariaDB | 5.7+ / 10.x | Accessible en reseau pour une vraie utilisation multi-sites |
| Python    | 3.8+             | Pour le script d'initialisation `init.py` (optionnel si import manuel) |
| pymysql   | 1.0+             | `pip install pymysql` (uniquement si `init.py` est utilise) |

Sous XAMPP/WAMP : demarrer **Apache** et **MySQL** depuis le panneau de
controle avant de lancer l'application.

## 4. Installation et lancement

### Etape 1 — Copier les fichiers

Copier le dossier `ipam/` dans le repertoire web du serveur (sous XAMPP :
`C:\xampp\htdocs\ipam`).

### Etape 2 — Configurer la connexion a la base

Ouvrir `config.php` et adapter :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ipam');
define('DB_USER', 'root');
define('DB_PASS', '');        // mot de passe MySQL
define('SITE_ID', 1);         // le site gere par TON binome (1, 2 ou 3)
```

`SITE_ID` est le seul reglage propre a chaque binome.

### Etape 3 — Initialiser la base de donnees

**Option A — via phpMyAdmin (le plus simple) :**

Onglet **Importer** → choisir `ipam.sql` → **Executer**.

**Option B — via le script d'automatisation Python :**

```bash
pip install pymysql
python init.py
```

Ce script execute `ipam.sql`, puis verifie que les 3 sites, les 3 routeurs
PE, les 3 pools `/24` et les 48 sous-reseaux `/28` sont bien presents (et les
recree si besoin) — il peut etre relance sans rien dupliquer.

### Etape 4 — Acceder a l'application

```
http://localhost/ipam/index.php
```

## 5. Utilisation de l'application

### Dashboard et liste des clients (`index.php`)

Affiche un bandeau de statistiques globales (nombre de clients, sous-reseaux
`/28` libres/alloues, nombre de sites), puis la liste des clients du site
courant. Un formulaire de recherche/filtre permet de :

- rechercher un client par nom (partiel) ou par identifiant exact (`q`) ;
- changer de site consulte (`site`, y compris **« tous les sites »**) ;
- filtrer le tableau des sous-reseaux par statut IP (`etat` : libre /
  reservee / allouee).

Ces filtres sont transmis en GET, donc partageables par URL.

### Creer un client (`ajouter.php`)

Saisir un nom → l'application attribue automatiquement le premier `/28`
libre du site, fixe `VLAN = numero client`, cree la VRF (`nom_vrf = nom`) et
affiche toutes les infos ainsi que la configuration Cisco generee.

### Fiche client (`voir.php`)

Affiche le detail d'un client (VLAN, VRF, Route Distinguisher, sous-reseau,
adresse de sous-interface, routeur PE et son loopback) ainsi que la
configuration Cisco IOS complete (VRF, sous-interface, MP-BGP, OSPF).

### Supprimer (`supprimer.php`)

Supprime le client et repasse son `/28` a l'etat **« libre »**, qui devient
alors a nouveau disponible pour la prochaine attribution.

## 6. Base de donnees

### Schema

```
SITE ──< PLAGE_ADRESSES ──< SOUS_RESEAU
  │                              │
  └──< ROUTEUR_PE ──────────< CLIENT >──┘
```

### Tables

| Table             | Role |
|-------------------|------|
| `site`            | Les sites de production (3 par defaut) |
| `routeur_pe`      | Routeurs Provider Edge (un par site), avec leur adresse de loopback |
| `plage_adresses`  | Plages `/24` allouees a chaque site |
| `sous_reseau`     | Sous-reseaux `/28` generes a partir des plages, avec leur etat (`libre`/`reserve`/`alloue`) |
| `client`          | Clients de l'operateur (VLAN = id_client, VRF = nom du client) |

## 7. Plan d'adressage

| Site            | Plage allouee   | Sous-reseaux /28 disponibles |
|------------------|------------------|-------------------------------|
| Site Groupe 1    | 164.166.1.0/24   | 16                            |
| Site Groupe 2    | 164.166.2.0/24   | 16                            |
| Site Groupe 3    | 164.166.3.0/24   | 16                            |

Chaque plage `/24` genere 16 sous-reseaux `/28` de 14 adresses hotes
chacun. La premiere adresse utilisable du sous-reseau est attribuee comme
adresse de sous-interface du PE.

## 8. Generation de configuration Cisco

Pour chaque client, la plateforme genere automatiquement :

```cisco
! ── 1. Definition de la VRF
vrf definition VRF_CLIENT
 rd 65556:<id_client>
 address-family ipv4
  route-target export 65556:<id_client>
  route-target import 65556:<id_client>
 exit-address-family
!
! ── 2. Sous-interface vers le client
interface GigabitEthernet0/0/0.<vlan>
 encapsulation dot1Q <vlan>
 vrf forwarding VRF_CLIENT
 ip address <premiere_ip> 255.255.255.240
 no shutdown
!
! ── 3. MP-BGP (diffusion de la VRF entre PE)
router bgp 65556
 address-family vpnv4
  neighbor <IP_PE_VOISIN> activate
  neighbor <IP_PE_VOISIN> send-community extended
 exit-address-family
 address-family ipv4 vrf VRF_CLIENT
  redistribute connected
 exit-address-family
!
! ── 4. OSPF (annonce du loopback du PE)
router ospf 1
 network <LOOPBACK_PE> 0.0.0.0 area 0
!
```

**Parametres fixes (cahier des charges) :**

| Element | Regle |
|---|---|
| Sous-reseau | 1er `/28` a l'etat `libre` du pool du site → passe a `alloue` |
| Numero client | `id_client` auto-incremente |
| VLAN | = `id_client` |
| VRF | nom = nom du client |
| Route Distinguisher | `65556:id_client` (AS constant, calcule, non stocke) |
| AS de l'operateur | `65556` |
| Masque du `/28` | `255.255.255.240` |

## 9. Choix techniques

| Composant       | Technologie choisie | Justification |
|------------------|---------------------|----------------|
| Backend          | PHP 8 (PDO)         | Compatible Apache, syntaxe simple, requetes preparees securisees |
| Base de donnees  | MySQL/MariaDB       | SQL standard, accessible en reseau, performant |
| Frontend         | HTML/CSS natif      | Pas de dependance lourde, chargement rapide |
| Init BDD         | Python 3 + pymysql  | Script d'automatisation idempotent, lisible, multiplateforme |
| Concurrence      | Transaction PDO + `SELECT ... FOR UPDATE` | Empeche que deux creations simultanees de client se voient attribuer le meme `/28` |

La configuration Cisco est generee cote serveur PHP sous forme de texte
brut, affichee dans un bloc `<pre>`. Le push automatique sur les routeurs
(via SSH/Netmiko) n'est pas implemente dans ce POC : la configuration est
destinee a etre copiee-collee par l'administrateur reseau.

## 10. Tests et validation

| Scenario | Resultat attendu | Resultat obtenu |
|----------|-------------------|-------------------|
| Creer un client sur un site | Sous-reseau `/28` alloue, VLAN = id_client, VRF creee | OK |
| Creer un second client sur le meme site | Sous-reseau `/28` suivant alloue | OK |
| Supprimer un client | Sous-reseau repasse en etat `libre` | OK |
| Recreer un client apres suppression | Le sous-reseau libere est reattribue en premier | OK |
| Creer un client avec un nom invalide | Message d'erreur, pas d'insertion | OK |
| Consulter la vue « tous les sites » | Les clients de tous les sites sont affiches | OK |
| Epuiser tous les `/28` d'un site (16 clients) | Exception "Plus aucun sous-reseau /28 disponible" | OK |
| Deux creations simultanees sur le meme site | Verrou `FOR UPDATE` : pas de double attribution du meme `/28` | OK |

### Verification de la base de donnees

```sql
-- Sous-reseaux libres
SELECT COUNT(*) FROM sous_reseau WHERE etat = 'libre';

-- Coherence VLAN = id_client
SELECT id_client, vlan FROM client WHERE id_client != vlan;
-- Doit retourner 0 ligne

-- Aucun sous-reseau alloue a plusieurs clients
SELECT id_sous_reseau, COUNT(*) FROM client
GROUP BY id_sous_reseau HAVING COUNT(*) > 1;
-- Doit retourner 0 ligne
```

## 11. Limites et evolutions possibles

- **Securite** : les identifiants MySQL sont actuellement en clair dans
  `config.php`. En production, ils devraient etre deplaces dans un fichier
  `.env` non versionne.
- **Push automatique sur routeur** : la configuration Cisco est generee mais
  non deployee automatiquement. Une evolution avec `Netmiko` permettrait de
  la pousser directement via SSH sur les routeurs PE.
- **Etat "reserve"** : l'ENUM prevoit l'etat `reserve` (prereservation avant
  signature de contrat) mais cette fonctionnalite n'est pas encore exposee
  dans l'interface.
- **Authentification** : l'application ne comporte pas de systeme de
  connexion. En production, un acces authentifie serait necessaire.
- **Multi-routeurs par site** : le schema supporte un seul routeur PE par
  site. Une evolution permettrait d'en associer plusieurs avec repartition
  de charge.

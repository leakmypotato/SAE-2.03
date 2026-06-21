<?php
// ============================================================
//  fonctions.php - Logique metier et automatisation
// ============================================================
require_once __DIR__ . '/db.php';

/**
 * Liste les clients du site (avec infos reseau), avec recherche et filtre de site optionnels.
 *
 * @param string|null $q       Recherche par nom (LIKE) ou par identifiant client (egalite).
 * @param int|null    $siteId  Site a filtrer. null => SITE_ID (site courant). 0 => tous les sites.
 */
function listerClients(?string $q = null, ?int $siteId = null): array
{
    $site = $siteId !== null ? $siteId : SITE_ID;

    $sql = "SELECT c.id_client, c.nom, c.vlan, c.nom_vrf, c.date_creation,
                   sr.adresse_reseau, sr.premiere_ip, sr.prefixe,
                   pe.nom AS nom_pe, s.id_site, s.nom_site
            FROM client c
            JOIN sous_reseau sr ON c.id_sous_reseau = sr.id_sous_reseau
            JOIN routeur_pe pe  ON c.id_routeur_pe  = pe.id_routeur_pe
            JOIN site s         ON pe.id_site       = s.id_site
            WHERE 1=1";
    $params = [];

    if ($site !== 0) {
        $sql .= " AND s.id_site = :site";
        $params[':site'] = $site;
    }

    $q = $q !== null ? trim($q) : '';
    if ($q !== '') {
        if (ctype_digit($q)) {
            $sql .= " AND (c.id_client = :qid OR c.nom LIKE :qnom)";
            $params[':qid']  = (int) $q;
            $params[':qnom'] = '%' . $q . '%';
        } else {
            $sql .= " AND c.nom LIKE :qnom";
            $params[':qnom'] = '%' . $q . '%';
        }
    }

    $sql .= " ORDER BY c.id_client";
    $st = getPDO()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Liste tous les sites de production (pour le filtre "site").
 */
function listerSites(): array
{
    return getPDO()->query("SELECT id_site, nom_site, groupe FROM site ORDER BY id_site")->fetchAll();
}

/**
 * Liste les sous-reseaux /28 (avec leur etat), filtrables par site et par statut IP.
 *
 * @param string|null $etat    'libre' | 'reserve' | 'alloue' | null (tous)
 * @param int|null    $siteId  Site a filtrer. null => SITE_ID (site courant). 0 => tous les sites.
 */
function listerSousReseaux(?string $etat = null, ?int $siteId = null): array
{
    $site = $siteId !== null ? $siteId : SITE_ID;

    $sql = "SELECT sr.id_sous_reseau, sr.adresse_reseau, sr.premiere_ip, sr.prefixe, sr.etat,
                   s.id_site, s.nom_site
            FROM sous_reseau sr
            JOIN plage_adresses p ON sr.id_plage = p.id_plage
            JOIN site s           ON p.id_site   = s.id_site
            WHERE 1=1";
    $params = [];

    if ($site !== 0) {
        $sql .= " AND s.id_site = :site";
        $params[':site'] = $site;
    }
    if ($etat !== null && $etat !== '' && in_array($etat, ['libre', 'reserve', 'alloue'], true)) {
        $sql .= " AND sr.etat = :etat";
        $params[':etat'] = $etat;
    }

    $sql .= " ORDER BY s.id_site, sr.id_sous_reseau";
    $st = getPDO()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Recupere un client complet par son id.
 */
function getClient(int $id): ?array
{
    $sql = "SELECT c.*, sr.adresse_reseau, sr.premiere_ip, sr.prefixe,
                   pe.nom AS nom_pe, pe.adresse_loopback
            FROM client c
            JOIN sous_reseau sr ON c.id_sous_reseau = sr.id_sous_reseau
            JOIN routeur_pe pe  ON c.id_routeur_pe  = pe.id_routeur_pe
            WHERE c.id_client = :id";
    $st = getPDO()->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Combien de sous-reseaux /28 restent libres sur le site.
 */
function nbSousReseauxLibres(): int
{
    $sql = "SELECT COUNT(*) AS n
            FROM sous_reseau sr
            JOIN plage_adresses p ON sr.id_plage = p.id_plage
            WHERE p.id_site = :site AND sr.etat = 'libre'";
    $st = getPDO()->prepare($sql);
    $st->execute([':site' => SITE_ID]);
    return (int) $st->fetch()['n'];
}

/**
 * Statistiques globales (tous sites confondus) pour le bandeau du dashboard.
 */
function statsGlobales(): array
{
    $pdo = getPDO();
    $nbClients = (int) $pdo->query("SELECT COUNT(*) AS n FROM client")->fetch()['n'];
    $nbLibres  = (int) $pdo->query("SELECT COUNT(*) AS n FROM sous_reseau WHERE etat = 'libre'")->fetch()['n'];
    $nbAlloues = (int) $pdo->query("SELECT COUNT(*) AS n FROM sous_reseau WHERE etat = 'alloue'")->fetch()['n'];
    $nbSites   = (int) $pdo->query("SELECT COUNT(*) AS n FROM site")->fetch()['n'];
    return [
        'clients' => $nbClients,
        'libres'  => $nbLibres,
        'alloues' => $nbAlloues,
        'sites'   => $nbSites,
    ];
}

/**
 * Ajoute un client : attribue automatiquement le 1er /28 libre du site,
 * le passe a "alloue", cree le client (vlan = id_client, nom_vrf = nom).
 * Retourne l'id du client cree.
 */
function ajouterClient(string $nom): int
{
    // Validation : nom utilisable dans une VRF / config Cisco
    $nom = trim($nom);
    if (!preg_match('/^[A-Za-z0-9_\-]{2,100}$/', $nom)) {
        throw new InvalidArgumentException(
            "Nom invalide : lettres, chiffres, '_' et '-' uniquement (2 a 100 caracteres)."
        );
    }

    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        // 1) Verrouille et recupere le 1er sous-reseau LIBRE du site
        $sql = "SELECT sr.id_sous_reseau
                FROM sous_reseau sr
                JOIN plage_adresses p ON sr.id_plage = p.id_plage
                WHERE p.id_site = :site AND sr.etat = 'libre'
                ORDER BY sr.id_sous_reseau
                LIMIT 1
                FOR UPDATE";
        $st = $pdo->prepare($sql);
        $st->execute([':site' => SITE_ID]);
        $sr = $st->fetch();
        if (!$sr) {
            throw new RuntimeException("Plus aucun sous-reseau /28 disponible sur ce site.");
        }
        $idSousReseau = (int) $sr['id_sous_reseau'];

        // 2) Recupere le routeur PE du site
        $st = $pdo->prepare("SELECT id_routeur_pe FROM routeur_pe WHERE id_site = :site LIMIT 1");
        $st->execute([':site' => SITE_ID]);
        $pe = $st->fetch();
        if (!$pe) {
            throw new RuntimeException("Aucun routeur PE configure pour ce site.");
        }
        $idPe = (int) $pe['id_routeur_pe'];

        // 3) Marque le sous-reseau comme alloue
        $st = $pdo->prepare("UPDATE sous_reseau SET etat = 'alloue' WHERE id_sous_reseau = :id");
        $st->execute([':id' => $idSousReseau]);

        // 4) Cree le client (vlan provisoire, corrige juste apres)
        //    NB : :nom_vrf et :nom sont deux placeholders distincts avec la meme valeur,
        //    car PDO (PDO::ATTR_EMULATE_PREPARES = false) interdit de reutiliser
        //    un meme nom de parametre deux fois dans une requete preparee MySQL.
        $st = $pdo->prepare(
            "INSERT INTO client (id_sous_reseau, id_routeur_pe, nom, vlan, nom_vrf, date_creation)
             VALUES (:sr, :pe, :nom, 0, :nom_vrf, CURDATE())"
        );
        $st->execute([':sr' => $idSousReseau, ':pe' => $idPe, ':nom' => $nom, ':nom_vrf' => $nom]);
        $idClient = (int) $pdo->lastInsertId();

        // 5) vlan = id_client (regle du cahier de charge)
        $st = $pdo->prepare("UPDATE client SET vlan = :v WHERE id_client = :id");
        $st->execute([':v' => $idClient, ':id' => $idClient]);

        $pdo->commit();
        return $idClient;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Supprime un client et libere son sous-reseau (etat -> libre).
 */
function supprimerClient(int $id): void
{
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT id_sous_reseau FROM client WHERE id_client = :id");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if ($row) {
            $st = $pdo->prepare("DELETE FROM client WHERE id_client = :id");
            $st->execute([':id' => $id]);
            $st = $pdo->prepare("UPDATE sous_reseau SET etat = 'libre' WHERE id_sous_reseau = :sr");
            $st->execute([':sr' => $row['id_sous_reseau']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Genere la configuration Cisco complete d'un client :
 * VRF, sous-interface, MP-BGP (VPNv4) et OSPF (annonce du loopback du PE).
 */
function genererConfig(array $client): string
{
    $num      = (int) $client['id_client'];          // = vlan = n sous-interface
    $vrf      = $client['nom_vrf'];
    $ip       = $client['premiere_ip'];
    $rd       = AS_SOCIETE . ':' . $num;
    $intf     = INTERFACE_PE . '.' . $num;
    $loopback = $client['adresse_loopback'] ?? null;

    $cfg  = "! ── 1. Definition de la VRF\n";
    $cfg .= "vrf definition {$vrf}\n";
    $cfg .= " rd {$rd}\n";
    $cfg .= " address-family ipv4\n";
    $cfg .= "  route-target export {$rd}\n";
    $cfg .= "  route-target import {$rd}\n";
    $cfg .= " exit-address-family\n";
    $cfg .= "!\n";
    $cfg .= "! ── 2. Sous-interface vers le client\n";
    $cfg .= "interface {$intf}\n";
    $cfg .= " encapsulation dot1Q {$num}\n";
    $cfg .= " vrf forwarding {$vrf}\n";
    $cfg .= " ip address {$ip} " . MASQUE_28 . "\n";
    $cfg .= " no shutdown\n";
    $cfg .= "!\n";
    $cfg .= "! ── 3. MP-BGP (diffusion de la VRF entre PE)\n";
    $cfg .= "router bgp " . AS_SOCIETE . "\n";
    $cfg .= " address-family vpnv4\n";
    $cfg .= "  neighbor <IP_PE_VOISIN> activate\n";
    $cfg .= "  neighbor <IP_PE_VOISIN> send-community extended\n";
    $cfg .= " exit-address-family\n";
    $cfg .= " address-family ipv4 vrf {$vrf}\n";
    $cfg .= "  redistribute connected\n";
    $cfg .= " exit-address-family\n";
    $cfg .= "!\n";
    $cfg .= "! ── 4. OSPF (annonce du loopback du PE dans le coeur de reseau)\n";
    $cfg .= "router ospf 1\n";
    $cfg .= " network " . ($loopback ?: '<LOOPBACK_PE>') . " 0.0.0.0 area 0\n";
    $cfg .= "!";
    return $cfg;
}

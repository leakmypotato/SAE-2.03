<?php
// ============================================================
//  voir.php - Affiche la configuration d'un client
// ============================================================
require_once __DIR__ . '/entete.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$client = $id > 0 ? getClient($id) : null;

if (!$client) {
    echo '<div class="alert err">Client introuvable.</div>';
    require __DIR__ . '/pied.php';
    exit;
}
?>

<div class="card">
    <h2>Client #<?= htmlspecialchars((string) $client['id_client']) ?> — <?= htmlspecialchars($client['nom']) ?></h2>
    <div class="kv">
        <div class="k">VLAN</div><div><?= htmlspecialchars((string) $client['vlan']) ?></div>
        <div class="k">VRF</div><div><?= htmlspecialchars($client['nom_vrf']) ?></div>
        <div class="k">Route Distinguisher</div><div><code><?= htmlspecialchars(AS_SOCIETE . ':' . $client['id_client']) ?></code></div>
        <div class="k">Sous-reseau</div><div><code><?= htmlspecialchars($client['adresse_reseau']) ?>/<?= htmlspecialchars((string) $client['prefixe']) ?></code></div>
        <div class="k">Adresse sous-interface</div><div><code><?= htmlspecialchars($client['premiere_ip']) ?></code></div>
        <div class="k">Routeur PE</div><div><?= htmlspecialchars($client['nom_pe']) ?></div>
        <div class="k">Loopback PE</div><div><code><?= htmlspecialchars($client['adresse_loopback'] ?? '-') ?></code></div>
        <div class="k">Date de creation</div><div><?= htmlspecialchars($client['date_creation']) ?></div>
    </div>

    <h2 style="margin-top:20px;">Configuration Cisco</h2>
    <pre class="config"><?= htmlspecialchars(genererConfig($client)) ?></pre>

    <p style="margin-top:16px;">
        <a class="btn ghost" href="index.php">Retour</a>
        <a class="btn danger" href="supprimer.php?id=<?= (int)$client['id_client'] ?>"
           onclick="return confirm('Supprimer ce client et liberer son sous-reseau ?');">Supprimer</a>
    </p>
</div>

<?php require __DIR__ . '/pied.php'; ?>

<?php
// ============================================================
//  index.php - IHM de consultation (liste des clients du site)
//  + recherche par nom/identifiant, filtre par site et par statut IP
// ============================================================
require_once __DIR__ . '/entete.php';

// --- Lecture des parametres de recherche / filtre (GET) ---
$q         = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$siteParam = isset($_GET['site']) && $_GET['site'] !== '' ? (int) $_GET['site'] : SITE_ID;
$etatParam = isset($_GET['etat']) ? (string) $_GET['etat'] : '';

try {
    $sites       = listerSites();
    $clients     = listerClients($q !== '' ? $q : null, $siteParam);
    $sousReseaux = listerSousReseaux($etatParam !== '' ? $etatParam : null, $siteParam);
    $libres      = nbSousReseauxLibres();
    $stats       = statsGlobales();
} catch (Throwable $e) {
    echo '<div class="alert err">Erreur base de donnees : '
        . htmlspecialchars($e->getMessage()) . '</div>';
    require __DIR__ . '/pied.php';
    exit;
}

$libelleEtat = ['libre' => 'Libre', 'reserve' => 'Reservee', 'alloue' => 'Allouee'];
?>

<div class="stats">
    <div class="stat-box">
        <div class="stat-num"><?= $stats['clients'] ?></div>
        <div class="stat-label">Clients (tous sites)</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $stats['libres'] ?></div>
        <div class="stat-label">Sous-reseaux /28 libres</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $stats['alloues'] ?></div>
        <div class="stat-label">Sous-reseaux /28 alloues</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $stats['sites'] ?></div>
        <div class="stat-label">Sites de production</div>
    </div>
</div>

<div class="card">
    <h2>Recherche &amp; filtres</h2>
    <form method="get" action="index.php" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:180px;">
            <label for="q">Nom ou identifiant du client</label>
            <input type="text" id="q" name="q" placeholder="ex: ClientAlpha ou 5"
                   value="<?= htmlspecialchars($q) ?>" style="margin-bottom:0;">
        </div>
        <div style="min-width:180px;">
            <label for="site">Site de production</label>
            <select id="site" name="site" style="width:100%; padding:11px 13px; border:1px solid #cdd4e0; border-radius:7px; font-size:15px;">
                <option value="0" <?= $siteParam === 0 ? 'selected' : '' ?>>Tous les sites</option>
                <?php foreach ($sites as $s): ?>
                    <option value="<?= (int) $s['id_site'] ?>" <?= $siteParam === (int) $s['id_site'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nom_site']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width:180px;">
            <label for="etat">Statut IP (sous-reseaux)</label>
            <select id="etat" name="etat" style="width:100%; padding:11px 13px; border:1px solid #cdd4e0; border-radius:7px; font-size:15px;">
                <option value="" <?= $etatParam === '' ? 'selected' : '' ?>>Tous</option>
                <option value="libre"   <?= $etatParam === 'libre'   ? 'selected' : '' ?>>Libre</option>
                <option value="reserve" <?= $etatParam === 'reserve' ? 'selected' : '' ?>>Reservee</option>
                <option value="alloue"  <?= $etatParam === 'alloue'  ? 'selected' : '' ?>>Allouee</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn">Filtrer</button>
            <a href="index.php" class="btn ghost">Reinitialiser</a>
        </div>
    </form>
</div>

<div class="card">
    <h2>Clients</h2>
    <p class="muted" style="margin-bottom:14px;">
        <?= count($clients) ?> client(s) trouve(s) — <?= $libres ?> sous-reseau(x) /28 encore libre(s) sur votre site.
    </p>

    <?php if (count($clients) === 0): ?>
        <p class="muted">Aucun client ne correspond a cette recherche. Cliquez sur « + Nouveau client ».</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>N&deg;</th><th>Nom</th><th>Site</th><th>VLAN</th><th>Sous-reseau /28</th>
                <th>Adresse interface</th><th>VRF</th><th>PE</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
            <tr>
                <td><?= htmlspecialchars((string) $c['id_client']) ?></td>
                <td><?= htmlspecialchars($c['nom']) ?></td>
                <td><?= htmlspecialchars($c['nom_site']) ?></td>
                <td><?= htmlspecialchars((string) $c['vlan']) ?></td>
                <td><code><?= htmlspecialchars($c['adresse_reseau']) ?>/<?= htmlspecialchars((string) $c['prefixe']) ?></code></td>
                <td><code><?= htmlspecialchars($c['premiere_ip']) ?></code></td>
                <td><?= htmlspecialchars($c['nom_vrf']) ?></td>
                <td><?= htmlspecialchars($c['nom_pe']) ?></td>
                <td class="row-actions">
                    <a href="voir.php?id=<?= (int)$c['id_client'] ?>">Config</a>
                    <a href="supprimer.php?id=<?= (int)$c['id_client'] ?>"
                       onclick="return confirm('Supprimer ce client et liberer son sous-reseau ?');"
                       style="color:#c0392b;">Supprimer</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <p style="margin-top:18px;">
        <a class="btn" href="ajouter.php">+ Nouveau client</a>
    </p>
</div>

<div class="card">
    <h2>Sous-reseaux /28 — statut IP</h2>
    <?php if (count($sousReseaux) === 0): ?>
        <p class="muted">Aucun sous-reseau ne correspond a ce filtre.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>Site</th><th>Sous-reseau</th><th>1ere adresse</th><th>Statut</th></tr>
        </thead>
        <tbody>
        <?php foreach ($sousReseaux as $sr): ?>
            <tr>
                <td><?= htmlspecialchars($sr['nom_site']) ?></td>
                <td><code><?= htmlspecialchars($sr['adresse_reseau']) ?>/<?= htmlspecialchars((string) $sr['prefixe']) ?></code></td>
                <td><code><?= htmlspecialchars($sr['premiere_ip']) ?></code></td>
                <td><span class="badge <?= htmlspecialchars($sr['etat']) ?>"><?= htmlspecialchars($libelleEtat[$sr['etat']] ?? $sr['etat']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/pied.php'; ?>

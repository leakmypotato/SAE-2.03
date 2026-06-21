<?php
// ============================================================
//  entete.php - En-tete HTML + barre de navigation
// ============================================================
require_once __DIR__ . '/fonctions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPAM - Gestion des clients</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Plateforme IPAM</h1>
    <span class="site-badge">Site Groupe <?= htmlspecialchars((string) SITE_ID) ?></span>
    <nav>
        <a href="index.php">Clients</a>
        <a href="ajouter.php">+ Nouveau client</a>
    </nav>
</header>
<div class="container">

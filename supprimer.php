<?php
// ============================================================
//  supprimer.php - Supprime un client et libere son /28
// ============================================================
require_once __DIR__ . '/fonctions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    try {
        supprimerClient($id);
    } catch (Throwable $e) {
        // En cas d'erreur on revient quand meme a la liste
    }
}
header('Location: index.php');
exit;

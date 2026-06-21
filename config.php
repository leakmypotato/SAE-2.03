<?php
// ============================================================
//  config.php - Parametres de l'application IPAM
// ============================================================

// --- Connexion a la base de donnees ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ipam');
define('DB_USER', 'root');        
define('DB_PASS', '');            

// C'est le seul parametre a changer selon ton groupe.
define('SITE_ID', 1);

// --- Parametres operateur (constantes du cahier de charge) ---
define('AS_SOCIETE', 65556);              
define('MASQUE_28', '255.255.255.240');   // masque d'un /28
define('INTERFACE_PE', 'GigabitEthernet0/0/0'); // interface physique du PE

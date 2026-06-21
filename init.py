"""
init.py - script d'automatisation
----------------------------------
Initialise la base de donnees "ipam" en executant ipam.sql, puis verifie et
complete les donnees de depart si besoin (3 sites, 1 PE/site, 1 pool /24 par
site, 48 sous-reseaux /28). Idempotent : peut etre relance sans tout dupliquer.

Usage : python init.py
"""

import ipaddress
import subprocess
import sys

try:
    import pymysql
except ImportError:
    print("[!] pymysql non installe. Lance : pip install pymysql")
    sys.exit(1)

# ── Config BDD (doit correspondre a config.php) ────────────
DB_HOST = "localhost"
DB_NAME = "ipam"
DB_USER = "root"
DB_PASS = ""

SITES = [
    {"id_site": 1, "nom_site": "Site Groupe 1", "groupe": 1},
    {"id_site": 2, "nom_site": "Site Groupe 2", "groupe": 2},
    {"id_site": 3, "nom_site": "Site Groupe 3", "groupe": 3},
]

ROUTEURS = [
    {"id_routeur_pe": 1, "id_site": 1, "nom": "PE1", "modele": "Cisco 4221", "adresse_loopback": "10.255.0.1"},
    {"id_routeur_pe": 2, "id_site": 2, "nom": "PE2", "modele": "Cisco 4221", "adresse_loopback": "10.255.0.2"},
    {"id_routeur_pe": 3, "id_site": 3, "nom": "PE3", "modele": "Cisco 4221", "adresse_loopback": "10.255.0.3"},
]

PLAGES = [
    {"id_plage": 1, "id_site": 1, "reseau": "164.166.1.0", "prefixe": 24},
    {"id_plage": 2, "id_site": 2, "reseau": "164.166.2.0", "prefixe": 24},
    {"id_plage": 3, "id_site": 3, "reseau": "164.166.3.0", "prefixe": 24},
]


def run_sql_file(filepath="ipam.sql"):
    print("\n── Execution du fichier SQL ────────────────────────────")
    try:
        with open(filepath, "r", encoding="utf-8") as f:
            cmd = ["mysql", "-h", DB_HOST, "-u", DB_USER]
            if DB_PASS:
                cmd.append(f"-p{DB_PASS}")
            result = subprocess.run(cmd, stdin=f, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"  [OK]    {filepath} execute avec succes.")
        else:
            print(f"  [ERREUR] {result.stderr.strip()}")
            sys.exit(1)
    except FileNotFoundError:
        print(f"  [ERREUR] Fichier introuvable : {filepath}")
        sys.exit(1)


def get_connection():
    return pymysql.connect(
        host=DB_HOST,
        db=DB_NAME,
        user=DB_USER,
        password=DB_PASS,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


def seed_sites(cursor):
    print("\n── Sites ────────────────────────────────────────────────")
    for s in SITES:
        cursor.execute("SELECT 1 FROM site WHERE id_site = %s", (s["id_site"],))
        if cursor.fetchone():
            print(f"  [SKIP]  {s['nom_site']} deja present.")
        else:
            cursor.execute(
                "INSERT INTO site (id_site, nom_site, groupe) VALUES (%s, %s, %s)",
                (s["id_site"], s["nom_site"], s["groupe"]),
            )
            print(f"  [OK]    {s['nom_site']} (groupe {s['groupe']}) cree.")


def seed_routeurs(cursor):
    print("\n── Routeurs PE ──────────────────────────────────────────")
    for r in ROUTEURS:
        cursor.execute("SELECT 1 FROM routeur_pe WHERE id_routeur_pe = %s", (r["id_routeur_pe"],))
        if cursor.fetchone():
            print(f"  [SKIP]  {r['nom']} deja present.")
        else:
            cursor.execute(
                "INSERT INTO routeur_pe (id_routeur_pe, id_site, nom, modele, adresse_loopback) "
                "VALUES (%s, %s, %s, %s, %s)",
                (r["id_routeur_pe"], r["id_site"], r["nom"], r["modele"], r["adresse_loopback"]),
            )
            print(f"  [OK]    {r['nom']} ({r['adresse_loopback']}) cree.")


def seed_plages(cursor):
    print("\n── Plages d'adresses ────────────────────────────────────")
    for p in PLAGES:
        cursor.execute("SELECT 1 FROM plage_adresses WHERE id_plage = %s", (p["id_plage"],))
        if cursor.fetchone():
            print(f"  [SKIP]  {p['reseau']}/{p['prefixe']} deja presente.")
        else:
            cursor.execute(
                "INSERT INTO plage_adresses (id_plage, id_site, reseau, prefixe) VALUES (%s, %s, %s, %s)",
                (p["id_plage"], p["id_site"], p["reseau"], p["prefixe"]),
            )
            print(f"  [OK]    {p['reseau']}/{p['prefixe']} -> site {p['id_site']} creee.")


def generate_subnets(cursor):
    print("\n── Generation des sous-reseaux /28 ─────────────────────")
    total = 0
    for p in PLAGES:
        network = ipaddress.IPv4Network(f"{p['reseau']}/{p['prefixe']}", strict=False)
        inserted = 0
        for subnet in network.subnets(new_prefix=28):
            adresse_reseau = str(subnet.network_address)
            premiere_ip = str(subnet.network_address + 1)

            cursor.execute(
                "SELECT 1 FROM sous_reseau WHERE adresse_reseau = %s AND id_plage = %s",
                (adresse_reseau, p["id_plage"]),
            )
            if cursor.fetchone():
                continue

            cursor.execute(
                "INSERT INTO sous_reseau (id_plage, adresse_reseau, premiere_ip, prefixe, etat) "
                "VALUES (%s, %s, %s, 28, 'libre')",
                (p["id_plage"], adresse_reseau, premiere_ip),
            )
            inserted += 1

        total += inserted
        if inserted:
            print(f"  [OK]    {p['reseau']}/{p['prefixe']} -> {inserted} sous-reseaux /28 inseres.")
        else:
            print(f"  [SKIP]  {p['reseau']}/{p['prefixe']} deja generee.")

    print(f"\n  Total : {total} sous-reseaux inseres (ou deja presents).")


def main():
    print("╔══════════════════════════════════════╗")
    print("║        IPAM — Initialisation         ║")
    print("╚══════════════════════════════════════╝")

    run_sql_file()

    conn = None
    cursor = None
    try:
        conn = get_connection()
        cursor = conn.cursor()

        seed_sites(cursor)
        seed_routeurs(cursor)
        seed_plages(cursor)
        generate_subnets(cursor)

        conn.commit()
        print("\n✔  Initialisation terminee avec succes.")
        print("   Acces a l'application : http://localhost/ipam/index.php\n")

    except pymysql.MySQLError as e:
        print(f"\n[ERREUR MySQL] {e}")
        if conn:
            conn.rollback()
        sys.exit(1)
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()


if __name__ == "__main__":
    main()

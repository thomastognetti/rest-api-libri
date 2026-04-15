<?php
/**
 * REST API - Gestione Catalogo Libri
 * Autore: Tognetti Thomas
 * Sezione: 5CI

 * ─────────────────────────────────────────────
 * Tools - Creazione del DB e della tabella
 * ─────────────────────────────────────────────
 **/

// ─────────────────────────────────────────────
// 1. CONFIGURAZIONE DATABASE
// ─────────────────────────────────────────────

define("SQL_HOST", "localhost");
define("SQL_USER", "5CI");
define("SQL_PWD", "ille");          
define("SQL_NAME", "catalogo_libri");
define("SQL_CHARSET", "utf8mb4");

// ─────────────────────────────────────────────
// 2. CREAZIONE DB E TABELLA
// ─────────────────────────────────────────────

/**
 * Funzione per istanziare una connessione PDO al database.
 */
function getSQLConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=" . SQL_HOST . ";charset=" . SQL_CHARSET,
            SQL_USER,
            SQL_PWD,
        );

        // Opzioni PDO raccomandate
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        try {
            $pdo = new PDO($dsn, SQL_USER, SQL_PWD, $options);
        } catch (PDOException $e) {
            sendError(503, 'Errore di connessione al database: ' . $e->getMessage());
        }
    }

    return $pdo;
}

// Connessione al server MySQL.
$pdo = getSQLConnection();

// Crea il database se non esiste
$dbName = DB_NAME;
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");

// Seleziona il database
$pdo->exec("USE `$dbName`");

// Crea la tabella (con id AUTO_INCREMENT)
$sql = "CREATE TABLE IF NOT EXISTS catalogo_libri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titolo VARCHAR(255) NOT NULL,
            autore VARCHAR(255) NOT NULL,
            anno YEAR NOT NULL
        )";
$pdo->exec($sql);



?>


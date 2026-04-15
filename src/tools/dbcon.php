<?php

define("DB_HOST", "localhost");
define("DB_USER", "5CI");
define("DB_PWD", "ille");          
define("DB_NAME", "catalogo_libri");
define("DB_CHARSET", "utf8mb4");

/**
 * REST API - Gestione Catalogo Libri
 * Autore: Tognetti Thomas
 * Sezione: 5CI
 *
 * Tools - Connessione al DB (che già esiste)
 */

/**
 * Funzione per istanziare una connessione PDO al database.
 * Se il database non esiste, tenta di crearlo tramite build_db.php.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        // Opzioni PDO raccomandate
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PWD, $options);
        } catch (PDOException $e) {
            // Se il database non esiste, prova a crearlo
            if (str_contains($e->getMessage(), 'Unknown database')) {
                include __DIR__ . '/build_db.php';
                // Dopo la creazione, riconnettiti
                $pdo = new PDO($dsn, DB_USER, DB_PWD, $options);
            } else {
                // Altro errore: rilancia o gestisci
                throw new PDOException("Connessione fallita: " . $e->getMessage());
            }
        }
    }

    return $pdo;
}
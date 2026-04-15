<?php

/**
 * REST API - Gestione Catalogo catalogo_libri
 * Autore: Tognetti Thomas
 * Sezione: 5CI
 */

/* ─────────────────────────────────────────────
// 0. ENDPOINT API
// ─────────────────────────────────────────────
 * Endpoint API:
 *   GET    /rest.php          → Lista dei catalogo_libri
 *   POST   /rest.php          → Aggiungi un libro
 *   PUT    /rest.php?id={n}   → Aggiorna un libro
 *   DELETE /rest.php?id={n}   → Elimina un libro
 */

require 'tools/dbcon.php';

// ─────────────────────────────────────────────
// 1. HEADER HTTP – JSON
// ─────────────────────────────────────────────

// Si definiscono gli header di risposta:
/*
    - Tipo del Body di risposta: JSON (UTF-8)
    - Permette la richiesta da qualsiasi end device
    - Si definiscono i metodi utilizzabili (vedi il punto 0)

*/
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─────────────────────────────────────────────
// 2. RISPOSTE JSON
// ─────────────────────────────────────────────

/**
 * Invia una risposta JSON (Pretty Print abilitato) 
 * con codice HTTP e termina l'esecuzione.
 *
 * @param int   $code    Codice HTTP (200, 201, 400, 404, …)
 * @param mixed $payload Array o stringa da serializzare in JSON
 */
function sendResponse(int $code, mixed $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Invia una risposta di errore JSON con delle informazioni 
 * sul codice di errore e sul messaggio.
 *
 * @param int    $code    Codice HTTP di errore
 * @param string $message Descrizione leggibile dell'errore
 */
function sendError(int $code, string $message): never
{
    sendResponse($code, [
        'errore'  => true,
        'codice'  => $code,
        'messaggio' => $message,
    ]);
}

// ─────────────────────────────────────────────
// 3. GESTIONE DELL'INPUT
// ─────────────────────────────────────────────

/**
 * Sanifica una stringa rimuovendo spazi inutili e tag HTML.
 */
function sanitizeString(mixed $value): string
{
    return htmlspecialchars(strip_tags(trim((string) $value)), ENT_QUOTES, 'UTF-8');
}

/**
 * Legge e decodifica il body JSON della richiesta.
 * Restituisce null se il body è vuoto o non è JSON valido.
 */
function getRequestBody(): ?array
{
    $rawBody = file_get_contents('php://input');

    if (empty($rawBody)) {
        return null;
    }

    $data = json_decode($rawBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Body della richiesta non è un JSON valido: ' . json_last_error_msg());
    }

    return $data;
}

/**
 * Recupera e valida l'ID dalla query string (?id=n).
 * Termina con 400 se assente o non numerico positivo.
 */
function getIdFromQuery(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        sendError(400, "Parametro 'id' mancante o non valido. Deve essere un intero positivo.");
    }
    return $id;
}

// ─────────────────────────────────────────────
// 4. API Endpoint - GET /rest.php
//    Restituisce tutti i catalogo_libri nel catalogo.
// ─────────────────────────────────────────────

function handleGetAll(): void
{
    // Connessione ale DB
    $pdo  = getDBConnection();

    // Si istanzia un PDOStatement con la query adatta.
    $stmt = $pdo->query('SELECT id, titolo, autore, anno FROM catalogo_libri ORDER BY id ASC');
    
    // Si recuperano i risultati della query sottoforma di Array
    $catalogo_libri = $stmt->fetchAll();

    // Convertiamo i tipi numerici (PDO li restituisce come stringhe di default)
    foreach ($catalogo_libri as &$libro) {
        $libro['id']   = (int) $libro['id'];
        $libro['anno'] = (int) $libro['anno'];
    }
    unset($libro);

    sendResponse(200, $catalogo_libri);
}

// ─────────────────────────────────────────────
// 5. API Endpoint – POST /rest.php
//    Aggiunge un nuovo libro al catalogo.
// ─────────────────────────────────────────────

function handleCreate(): void
{
    $data = getRequestBody();
    if ($data === null) {
        sendError(400, 'Body della richiesta vuoto.');
    }

    $campiRichiesti = ['titolo', 'autore', 'anno'];
    $campiMancanti = array_diff($campiRichiesti, array_keys($data));
    if (!empty($campiMancanti)) {
        sendError(400, 'Campi obbligatori mancanti: ' . implode(', ', $campiMancanti));
    }

    $titolo = sanitizeString($data['titolo']);
    $autore = sanitizeString($data['autore']);
    $anno   = (int) $data['anno'];

    if (trim($titolo) === '') sendError(400, "Il campo 'titolo' non può essere vuoto.");
    if (trim($autore) === '') sendError(400, "Il campo 'autore' non può essere vuoto.");
    if ($anno < 1 || $anno > (int) date('Y') + 1) {
        sendError(400, "Anno non valido.");
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('INSERT INTO catalogo_libri (titolo, autore, anno) VALUES (:titolo, :autore, :anno)');
    $stmt->execute([':titolo' => $titolo, ':autore' => $autore, ':anno' => $anno]);

    $nuovoId = $pdo->lastInsertId();

    sendResponse(201, [
        'messaggio' => 'Libro aggiunto con successo.',
        'libro' => [
            'id' => (int) $nuovoId,
            'titolo' => $titolo,
            'autore' => $autore,
            'anno' => $anno
        ]
    ]);
}

// ─────────────────────────────────────────────
// 6. API Endpoint – PUT /rest.php?id={n}
//    Aggiorna un libro esistente all'interno 
//    del catalogo.
// ─────────────────────────────────────────────

function handleUpdate(): void
{
    // Recuperiamo l'ID dalla query string e i dati dal Body
    // della richiesta HTTP.
    $id   = getIdFromQuery();
    $data = getRequestBody();

    // Se il Body della richiesta HTTP è vuoto, presentiamo un errore.
    if ($data === null || empty($data)) {
        sendError(400, 'Body della richiesta vuoto. Invia almeno un campo da aggiornare (titolo, autore, anno).');
    }

    // Verifichiamo l'esistenza di un libro con ID
    // uguale a quello fornito.

    // Connessione al DB
    $pdo = getDBConnection();

    // Istanziamo un PDOStatement per cercare all'interno del catalogo.
    $chk = $pdo->prepare('SELECT id, titolo, autore, anno FROM catalogo_libri WHERE id = :id');
    $chk->execute([':id' => $id]);
    $libroEsistente = $chk->fetch();

    // Se non c'è un libro corrispondente, presentiamo un errore.
    if (!$libroEsistente) {
        sendError(404, "Nessun libro trovato con id = {$id}.");
    }

    // In questo caso, se non vengono fornite alcune informazioni
    // (ad es. si vuole soltanto aggiornare il titolo del libro),
    // si utilizzano i dati già presenti.
    $titolo = isset($data['titolo']) ? sanitizeString($data['titolo']) : $libroEsistente['titolo'];
    $autore = isset($data['autore']) ? sanitizeString($data['autore']) : $libroEsistente['autore'];
    $anno   = isset($data['anno'])   ? (int) $data['anno']             : (int) $libroEsistente['anno'];

    // Effettuiamo gli adeguati controlli sui dati forniti.
    if (trim($titolo) === '') {
        sendError(400, "Il campo 'titolo' non può essere vuoto.");
    }

    if (trim($autore) === '') {
        sendError(400, "Il campo 'autore' non può essere vuoto.");
    }

    if ($anno < 1 || $anno > (int) date('Y') + 1) {
        sendError(400, "Il campo 'anno' deve essere un intero positivo e non superiore all'anno prossimo.");
    }

    // Si aggiornano i dati del libro indicato dall'ID.
    // Si istanzia un PDOStatement di UPDATE (utilizzando i prepared
    // statements per neutralizzare la vulnerabilità SQL injection)
    $stmt = $pdo->prepare(
        'UPDATE catalogo_catalogo_libri SET titolo = :titolo, autore = :autore, anno = :anno WHERE id = :id'
    );
    $stmt->execute([
        ':titolo' => $titolo,
        ':autore' => $autore,
        ':anno'   => $anno,
        ':id'     => $id,
    ]);

    // Si invia una risposta di feedback sull'operazione.
    sendResponse(200, [
        'messaggio' => "Libro con id = {$id} aggiornato con successo.",
        'libro'     => [
            'id'     => $id,
            'titolo' => $titolo,
            'autore' => $autore,
            'anno'   => $anno,
        ],
    ]);
}

// ─────────────────────────────────────────────
// 7. API Endpoint – DELETE /rest.php?id={n}
//    Rimuove un libro dal catalogo.
// ─────────────────────────────────────────────

function handleDelete(): void
{
    // Otteniamo l'ID e non il Body (ci serve solo l'ID).
    $id  = getIdFromQuery();

    // Connessione al DB.
    $pdo = getDBConnection();

    // Verifichiamo l'esistenza di un libro con l'ID fornito.
    $chk = $pdo->prepare('SELECT id FROM catalogo_libri WHERE id = :id');
    $chk->execute([':id' => $id]);

    // Presentiamo un errore se non esiste.
    if (!$chk->fetch()) {
        sendError(404, "Nessun libro trovato con id = {$id}. Impossibile eliminare.");
    }

    // Altrimenti, eliminiamo la tupla dal catalogo.
    $stmt = $pdo->prepare('DELETE FROM catalogo_libri WHERE id = :id');
    $stmt->execute([':id' => $id]);

    // Inviamo una risposta di feedback sull'operazione.
    sendResponse(200, [
        'messaggio' => "Libro con id = {$id} eliminato con successo.",
    ]);
}

// ─────────────────────────────────────────────
// 8. Gestione delle richieste
// ─────────────────────────────────────────────

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    // Effettuiamo un match sul metodo della richiesta HTTP per
    // reindirizzare all'handler API corretto grazie a
    /*                                  match()
        The match expression branches evaluation based on an identity check of a value. 
        Similarly to a switch statement, a match expression has a subject expression that 
        is compared against multiple alternatives. 
        
        Unlike switch, it will evaluate to a value much like ternary expressions. 
        Unlike switch, the comparison is an identity check (===) 
        rather than a weak equality check (==). 
        Match expressions are available as of PHP 8.0.0.
        da (https://www.php.net/manual/en/control-structures.match.php)
    */
    match ($metodo) {
        'GET'    => handleGetAll(),
        'POST'   => handleCreate(),
        'PUT'    => handleUpdate(),
        'DELETE' => handleDelete(),
        // Inviamo un errore se il metodo HTTP richiesto non è supportato.
        default  => sendError(405, "Metodo HTTP '{$metodo}' non supportato. Metodi consentiti: GET, POST, PUT, DELETE."),
    };
} catch (PDOException $e) {
    // Errore imprevisto del database
    sendError(500, 'Errore interno del database: ' . $e->getMessage());
} catch (Throwable $e) {
    // Qualsiasi altro errore non gestito
    sendError(500, 'Errore interno del server: ' . $e->getMessage());
}
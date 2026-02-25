<?php
// ============================================================
// includes/db.php — PDO database connection
// Edit DB_NAME, DB_USER, DB_PASS to match your local setup.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'midnightbuilds');
define('DB_USER', 'root');   // XAMPP/MAMP default
define('DB_PASS', '');       // XAMPP default blank; MAMP often 'root'
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose raw error to the browser in production.
            die('<p style="color:red;font-family:monospace;">Database connection failed: '
                . htmlspecialchars($e->getMessage()) . '</p>');
        }
    }

    return $pdo;
}

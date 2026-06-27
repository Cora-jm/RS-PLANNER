<?php
namespace App\Core;

/**
 * CLASSE DATABASE — Connexion SQLite (Singleton)
 * Géré par : DAREL
 * Usage : $db = Database::getConnexion();
 */
class Database {
    private static ?self $instance = null;
    private \PDO $pdo;

    private function __construct() {
        // Crée le fichier SQLite s'il n'existe pas encore
        $this->pdo = new \PDO('sqlite:' . DB_PATH, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        // Active les clés étrangères (désactivées par défaut en SQLite)
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
    }

    public static function getConnexion(): \PDO {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}

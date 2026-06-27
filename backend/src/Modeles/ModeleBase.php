<?php
namespace App\Modeles;

use App\Core\Database;

/**
 * MODELE BASE — Classe parente commune à tous les modèles
 * Géré par : DAREL
 * Ne pas modifier sauf si tout le monde est d'accord.
 */
abstract class ModeleBase {
    protected \PDO $db;
    protected string $table = '';

    public function __construct() {
        $this->db = Database::getConnexion();
    }

    /** Retourne toutes les lignes d'une table. */
    public function tout(): array {
        return $this->db->query("SELECT * FROM {$this->table}")->fetchAll();
    }

    /** Retourne une ligne par son ID. */
    public function trouverParId(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Supprime une ligne par son ID. */
    public function supprimer(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

<?php
namespace App\Services;

use App\Core\Database;

/**
 * SERVICE NOTIFICATION
 * Rôle : Gérer l'historique des alertes pour les utilisateurs.
 */
class ServiceNotification {

    /**
     * AJOUTER UNE NOTIFICATION
     */
    public static function ajouter(int $id_utilisateur, string $type, string $titre, string $message, ?string $lien = null): bool {
        $db = Database::getConnexion();
        $sql = "INSERT INTO notifications (id_utilisateur, type, titre, message, lien) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id_utilisateur, $type, $titre, $message, $lien]);
    }

    /**
     * LISTE DES NOTIFICATIONS D'UN UTILISATEUR
     */
    public function listePourUtilisateur(int $id_utilisateur): array {
        $db = Database::getConnexion();
        $sql = "SELECT * FROM notifications 
                WHERE id_utilisateur = ? 
                ORDER BY cree_le DESC 
                LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id_utilisateur]);
        return $stmt->fetchAll();
    }

    /**
     * MARQUER COMME LU
     */
    public function marquerCommeLu(int $id_utilisateur, ?int $id_notification = null): bool {
        $db = Database::getConnexion();
        if ($id_notification) {
            $sql = "UPDATE notifications SET lu = 1 WHERE id = ? AND id_utilisateur = ?";
            return $db->prepare($sql)->execute([$id_notification, $id_utilisateur]);
        } else {
            $sql = "UPDATE notifications SET lu = 1 WHERE id_utilisateur = ?";
            return $db->prepare($sql)->execute([$id_utilisateur]);
        }
    }
}

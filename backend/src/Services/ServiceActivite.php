<?php
namespace App\Services;

use App\Core\Database;

/**
 * SERVICE ACTIVITÉ
 * Rôle : Enregistrer et récupérer les actions effectuées sur les projets.
 */
class ServiceActivite {

    /**
     * ENREGISTRER UNE ACTIVITÉ
     */
    public static function ajouter(int $id_projet, int $id_utilisateur, string $description): bool {
        $db = Database::getConnexion();
        $sql = "INSERT INTO activites (id_projet, id_utilisateur, description) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id_projet, $id_utilisateur, $description]);
    }

    /**
     * RÉCUPÉRER LE FLUX D'ACTIVITÉ POUR UN UTILISATEUR
     * (Toutes les activités des projets dont il est membre)
     */
    public static function fluxPourUtilisateur(int $id_utilisateur, int $limite = 10): array {
        $db = Database::getConnexion();
        $sql = "SELECT a.*, u.nom as nom_utilisateur, u.photo as photo_utilisateur, p.titre as titre_projet
                FROM activites a
                JOIN utilisateurs u ON a.id_utilisateur = u.id
                JOIN projets p ON a.id_projet = p.id
                JOIN membres_projet mp ON p.id = mp.id_projet
                WHERE mp.id_utilisateur = ? AND mp.statut = 'accepte'
                ORDER BY a.cree_le DESC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id_utilisateur, $limite]);
        return $stmt->fetchAll();
    }
}

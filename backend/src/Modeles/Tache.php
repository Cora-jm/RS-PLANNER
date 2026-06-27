<?php
namespace App\Modeles;

/**
 * MODÈLE TÂCHE
 * Géré par : CEDRIC
 * Rôle : CRUD tâches, assignation, statuts.
 */
class Tache extends ModeleBase {

    public function __construct() {
        parent::__construct();
        $this->table = 'taches';
    }

    /**
     * AJOUTER UNE TÂCHE
     */
    public function ajouter(int $id_projet, string $titre, ?string $date_echeance, ?int $id_parent, ?int $id_assigne = null, ?string $description = null, string $statut = 'A faire'): int {
        $sql = "INSERT INTO taches (id_projet, id_parent, id_assigne, titre, description, date_echeance, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_projet, $id_parent, $id_assigne, $titre, $description, $date_echeance, $statut]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * MODIFIER UNE TÂCHE
     */
    public function modifier(int $id, array $donnees): bool {
        $champs = [];
        $params = [];

        if (isset($donnees['titre'])) { $champs[] = "titre = ?"; $params[] = $donnees['titre']; }
        if (isset($donnees['date_echeance'])) { $champs[] = "date_echeance = ?"; $params[] = $donnees['date_echeance']; }
        if (isset($donnees['statut'])) { $champs[] = "statut = ?"; $params[] = $donnees['statut']; }
        if (isset($donnees['id_assigne'])) { $champs[] = "id_assigne = ?"; $params[] = $donnees['id_assigne']; }

        if (empty($champs)) return true;

        $params[] = $id;
        $sql = "UPDATE taches SET " . implode(', ', $champs) . " WHERE id = ?";
        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * METTRE À JOUR LE STATUT
     */
    public function mettreAJourStatut(int $id, string $statut, int $id_user_action = 0): bool {
        $tache = $this->trouverParId($id);
        if (!$tache) return false;

        // Règle 1 : Si passage en 'En cours' et qu'il y a un parent, le parent doit être 'Terminé'
        if ($statut === 'En cours' && $tache['id_parent']) {
            $parent = $this->trouverParId($tache['id_parent']);
            if ($parent && $parent['statut'] !== 'Termine') {
                return false; // Règle violée
            }
        }

        // Règle 2 : Si passage en 'Termine' et qu'il y a des enfants, tous les enfants doivent être 'Termine'
        if ($statut === 'Termine') {
            $stmt = $this->db->prepare("SELECT id FROM taches WHERE id_parent = ? AND statut != 'Termine' LIMIT 1");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                return false; // Règle violée : il reste des sous-tâches non terminées
            }
        }

        // MISE À JOUR DU STATUT DE LA TÂCHE ACTUELLE
        $stmt = $this->db->prepare("UPDATE taches SET statut = ? WHERE id = ?");
        $success = $stmt->execute([$statut, $id]);

        // ACTIVITÉ ET NOTIFICATION : Si la tâche est terminée
        if ($success && $statut === 'Termine') {
            // Récupérer le proprio du projet pour le notifier
            $sql_p = "SELECT p.id as id_projet, p.id_proprietaire, p.titre as projet_titre, t.titre as tache_titre 
                      FROM projets p 
                      JOIN taches t ON p.id = t.id_projet 
                      WHERE t.id = ?";
            $stmt_p = $this->db->prepare($sql_p);
            $stmt_p->execute([$id]);
            $info = $stmt_p->fetch();
            
            if ($info) {
                // ACTIVITÉ
                if ($id_user_action > 0) {
                    \App\Services\ServiceActivite::ajouter(
                        $info['id_projet'],
                        $id_user_action,
                        "a terminé la tâche « " . $info['tache_titre'] . " »"
                    );
                }

                // NOTIFICATION : Notifier le propriétaire (si ce n'est pas lui qui a fini la tâche)
                if ($info['id_proprietaire'] !== $id_user_action) {
                    \App\Services\ServiceNotification::ajouter(
                        $info['id_proprietaire'],
                        'Tâche',
                        'Tâche terminée',
                        "La tâche « " . $info['tache_titre'] . " » a été terminée sur le projet " . $info['projet_titre'],
                        "/projet/" . $info['id_projet']
                    );
                }
            }
        }

        // AUTOMATISATION : Si c'est une sous-tâche qui vient de changer de statut
        if ($success && $tache['id_parent']) {
            $id_parent = (int)$tache['id_parent'];
            
            if ($statut === 'Termine') {
                // On vérifie s'il reste d'autres sous-tâches non finies pour ce parent
                $stmt = $this->db->prepare("SELECT id FROM taches WHERE id_parent = ? AND statut != 'Termine' LIMIT 1");
                $stmt->execute([$id_parent]);
                
                if (!$stmt->fetch()) {
                    // S'il n'y en a plus, on termine automatiquement le parent
                    $stmt = $this->db->prepare("UPDATE taches SET statut = 'Termine' WHERE id = ?");
                    $stmt->execute([$id_parent]);
                }
            } else {
                // Si on a décoché une sous-tâche (statut != Termine)
                // On repasse automatiquement le parent en 'En cours'
                $stmt = $this->db->prepare("UPDATE taches SET statut = 'En cours' WHERE id = ?");
                $stmt->execute([$id_parent]);
            }
        }

        return $success;
    }

    /**
     * ASSIGNER À UN UTILISATEUR
     */
    public function assignerA(int $id_tache, int $id_utilisateur): bool {
        $stmt = $this->db->prepare("UPDATE taches SET id_assigne = ? WHERE id = ?");
        return $stmt->execute([$id_utilisateur, $id_tache]);
    }

    /**
     * SUPPRIMER UNE TÂCHE
     */
    public function supprimerTache(int $id): bool {
        return $this->supprimer($id);
    }
}

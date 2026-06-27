<?php
namespace App\Modeles;

/**
 * MODÈLE PROJET
 * Géré par : CEDRIC
 * Rôle : CRUD projets, membres, avancement.
 */
class Projet extends ModeleBase {

    // Couleurs disponibles pour les membres d'un projet
    private const COULEURS = [
        '#e74c3c', '#3498db', '#2ecc71', '#f39c12',
        '#9b59b6', '#1abc9c', '#e67e22', '#34495e'
    ];

    public function __construct() {
        parent::__construct();
        $this->table = 'projets';
    }

    /**
     * LISTE DES PROJETS D'UN UTILISATEUR
     */
    public function listePourUtilisateur(int $id_user): array {
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM membres_projet WHERE id_projet = p.id AND statut = 'accepte') as nb_membres,
                (SELECT COUNT(*) FROM taches WHERE id_projet = p.id) as nb_taches
                FROM projets p
                JOIN membres_projet mp ON p.id = mp.id_projet
                WHERE mp.id_utilisateur = ? AND mp.statut = 'accepte'
                ORDER BY p.cree_le DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_user]);
        $projets = $stmt->fetchAll();

        foreach ($projets as &$p) {
            $p['avancement'] = $this->calculerAvancement($p['id']);
        }

        return $projets;
    }

    /**
     * DÉTAIL D'UN PROJET (avec membres + tâches + compétences + stats)
     */
    public function detail(int $id): ?array {
        $projet = $this->trouverParId($id);
        if (!$projet) return null;

        $projet['avancement'] = $this->calculerAvancement($id);

        // 1. Récupérer les membres (avec leur nombre de tâches dans ce projet)
        $sql_m = "SELECT u.id, u.nom, u.email, u.photo, mp.couleur, mp.statut,
                  (SELECT COUNT(*) FROM taches WHERE id_projet = ? AND id_assigne = u.id) as nb_taches
                  FROM utilisateurs u
                  JOIN membres_projet mp ON u.id = mp.id_utilisateur
                  WHERE mp.id_projet = ?";
        $stmt_m = $this->db->prepare($sql_m);
        $stmt_m->execute([$id, $id]);
        $projet['membres'] = $stmt_m->fetchAll();

        // 2. Récupérer les tâches
        $sql_t = "SELECT t.*, u.nom as nom_assigne 
                  FROM taches t
                  LEFT JOIN utilisateurs u ON t.id_assigne = u.id
                  WHERE t.id_projet = ?
                  ORDER BY t.cree_le ASC";
        $stmt_t = $this->db->prepare($sql_t);
        $stmt_t->execute([$id]);
        $projet['taches'] = $stmt_t->fetchAll();

        // 3. Récupérer les compétences requises
        $sql_c = "SELECT c.id, c.nom FROM competences c
                  JOIN competences_projet cp ON c.id = cp.id_competence
                  WHERE cp.id_projet = ?";
        $stmt_c = $this->db->prepare($sql_c);
        $stmt_c->execute([$id]);
        $projet['competences_requises'] = $stmt_c->fetchAll();

        // 4. Statistiques granulaires
        $stmt_s = $this->db->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'Termine' THEN 1 ELSE 0 END) as terminees,
            SUM(CASE WHEN statut = 'En cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'A faire' THEN 1 ELSE 0 END) as a_faire
            FROM taches WHERE id_projet = ?");
        $stmt_s->execute([$id]);
        $projet['stats'] = $stmt_s->fetch();

        return $projet;
    }

    /**
     * CRÉER UN PROJET
     */
    public function creerProjet(int $id_proprietaire, string $titre, string $description, array $competences = []): int {
        $stmt = $this->db->prepare("INSERT INTO projets (id_proprietaire, titre, description) VALUES (?, ?, ?)");
        $stmt->execute([$id_proprietaire, $titre, $description]);
        $id_projet = (int)$this->db->lastInsertId();

        // ACTIVITÉ
        \App\Services\ServiceActivite::ajouter($id_projet, $id_proprietaire, "a créé le projet");

        // Ajouter automatiquement le propriétaire comme membre (directement accepté)
        $this->ajouterMembre($id_projet, $id_proprietaire, 'accepte');

        // Ajouter les compétences requises
        foreach ($competences as $id_comp) {
            $this->gererCompetencesProjet($id_projet, (int)$id_comp, 'ajouter');
        }

        return $id_projet;
    }

    /**
     * GÉRER COMPÉTENCES PROJET
     */
    public function gererCompetencesProjet(int $id_projet, int $id_competence, string $action = 'ajouter'): bool {
        if ($action === 'supprimer') {
            $stmt = $this->db->prepare("DELETE FROM competences_projet WHERE id_projet = ? AND id_competence = ?");
            return $stmt->execute([$id_projet, $id_competence]);
        } else {
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO competences_projet (id_projet, id_competence) VALUES (?, ?)");
            return $stmt->execute([$id_projet, $id_competence]);
        }
    }

    /**
     * AJOUTER UN MEMBRE (ou envoyer une invitation)
     */
    public function ajouterMembre(int $id_projet, int $id_utilisateur, string $statut = 'en_attente'): ?string {
        // Vérifier si déjà présent (quel que soit le statut)
        $stmt = $this->db->prepare("SELECT couleur FROM membres_projet WHERE id_projet = ? AND id_utilisateur = ?");
        $stmt->execute([$id_projet, $id_utilisateur]);
        if ($stmt->fetch()) return null;

        $stmt = $this->db->prepare("SELECT couleur FROM membres_projet WHERE id_projet = ?");
        $stmt->execute([$id_projet]);
        $couleurs_prises = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $couleur = self::COULEURS[0];
        foreach (self::COULEURS as $c) {
            if (!in_array($c, $couleurs_prises)) {
                $couleur = $c;
                break;
            }
        }

        $stmt = $this->db->prepare("INSERT INTO membres_projet (id_projet, id_utilisateur, couleur, statut) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$id_projet, $id_utilisateur, $couleur, $statut]) ? $couleur : null;
    }

    /**
     * RÉPONDRE À UNE INVITATION
     */
    public function repondreInvitation(int $id_projet, int $id_utilisateur, string $reponse): bool {
        if (!in_array($reponse, ['accepte', 'refuse'])) return false;

        if ($reponse === 'refuse') {
            $stmt = $this->db->prepare("DELETE FROM membres_projet WHERE id_projet = ? AND id_utilisateur = ?");
            return $stmt->execute([$id_projet, $id_utilisateur]);
        } else {
            $stmt = $this->db->prepare("UPDATE membres_projet SET statut = 'accepte' WHERE id_projet = ? AND id_utilisateur = ?");
            $success = $stmt->execute([$id_projet, $id_utilisateur]);
            
            if ($success) {
                // ACTIVITÉ
                \App\Services\ServiceActivite::ajouter($id_projet, $id_utilisateur, "a rejoint le projet");
            }
            return $success;
        }
    }

    /**
     * RETIRER UN MEMBRE
     */
    public function retirerMembre(int $id_projet, int $id_utilisateur): bool {
        $stmt = $this->db->prepare("DELETE FROM membres_projet WHERE id_projet = ? AND id_utilisateur = ?");
        $ok = $stmt->execute([$id_projet, $id_utilisateur]);

        if ($ok) {
            $stmt = $this->db->prepare("UPDATE taches SET id_assigne = NULL WHERE id_projet = ? AND id_assigne = ?");
            $stmt->execute([$id_projet, $id_utilisateur]);
        }
        return $ok;
    }

    /**
     * CALCULER AVANCEMENT
     */
    public function calculerAvancement(int $id_projet): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM taches WHERE id_projet = ?");
        $stmt->execute([$id_projet]);
        $total = $stmt->fetch()['total'] ?? 0;

        if ($total === 0) return 0;

        $stmt = $this->db->prepare("SELECT COUNT(*) as finies FROM taches WHERE id_projet = ? AND statut = 'Termine'");
        $stmt->execute([$id_projet]);
        $finies = $stmt->fetch()['finies'] ?? 0;

        return (int)(($finies / $total) * 100);
    }
}

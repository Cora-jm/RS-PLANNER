<?php
namespace App\Modeles;

/**
 * MODÈLE UTILISATEUR
 * Géré par : DAREL
 * Rôle : Auth, profil, compétences.
 * Consulter docs/API_CONTRACT.md pour les formats de retour attendus.
 */
class Utilisateur extends ModeleBase {

    public function __construct() {
        parent::__construct();
        $this->table = 'utilisateurs';
    }

    /**
     * INSCRIPTION
     */
    public function inscription(string $nom, string $email, string $mdp): bool {
        // 1. Vérifier si l'email existe déjà
        $stmt = $this->db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return false;
        }

        // 2. Hasher le mot de passe
        $mdp_hashe = password_hash($mdp, PASSWORD_DEFAULT);

        // 3. Insérer l'utilisateur
        $stmt = $this->db->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe) VALUES (?, ?, ?)");
        return $stmt->execute([$nom, $email, $mdp_hashe]);
    }

    /**
     * CONNEXION
     */
    public function connexion(string $email, string $mdp): ?array {
        $stmt = $this->db->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            // Ne pas renvoyer le mot de passe
            unset($user['mot_de_passe']);
            return $user;
        }

        return null;
    }

    /**
     * PROFIL COMPLET (avec compétences et statistiques)
     */
    public function profilComplet(int $id): ?array {
        $user = $this->trouverParId($id);
        if (!$user) {
            error_log("profilComplet : Utilisateur ID $id non trouvé.");
            return null;
        }
        unset($user['mot_de_passe']);

        // 1. Récupérer les compétences
        $sql = "SELECT c.id, c.nom, uc.niveau 
                FROM competences c
                JOIN utilisateurs_competences uc ON c.id = uc.id_competence
                WHERE uc.id_utilisateur = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $user['competences'] = $stmt->fetchAll();

        // 2. Récupérer le nombre de projets actifs (où il est membre ou proprio)
        $sql_projets = "SELECT COUNT(DISTINCT id_projet) as total 
                        FROM membres_projet WHERE id_utilisateur = ?";
        $stmt_p = $this->db->prepare($sql_projets);
        $stmt_p->execute([$id]);
        $user['nb_projets'] = $stmt_p->fetch()['total'] ?? 0;

        // 3. Récupérer le nombre de tâches terminées (tous projets confondus)
        $sql_t = "SELECT COUNT(id) as total FROM taches 
                       WHERE id_assigne = ? AND statut IN ('Termine', 'Terminé')";
        $stmt_t = $this->db->prepare($sql_t);
        $stmt_t->execute([$id]);
        $user['nb_taches_finies'] = $stmt_t->fetch()['total'] ?? 0;

        // 4. Récupérer le nombre de tâches en retard (assignées à l'utilisateur)
        // On considère en retard si date_echeance < aujourd'hui et statut != Terminé
        $aujourdhui = date('Y-m-d');
        $sql_r = "SELECT COUNT(id) as total FROM taches 
                  WHERE id_assigne = ? 
                  AND statut NOT IN ('Termine', 'Terminé') 
                  AND date_echeance < ? AND date_echeance IS NOT NULL AND date_echeance != ''";
        $stmt_r = $this->db->prepare($sql_r);
        $stmt_r->execute([$id, $aujourdhui]);
        $user['nb_taches_retard'] = $stmt_r->fetch()['total'] ?? 0;

        // 5. Récupérer le nombre de matchings (suggestions de troc)
        $serviceMatching = new \App\Services\ServiceMatching();
        $trocs = $serviceMatching->suggererTroc($id);
        $user['nb_matchings'] = count($trocs);

        return $user;
    }

    /**
     * MODIFIER PROFIL
     */
    public function modifierProfil(int $id, array $donnees, ?string $photo_path = null): bool {
        $champs = [];
        $params = [];

        if (isset($donnees['nom'])) {
            $champs[] = "nom = ?";
            $params[] = $donnees['nom'];
        }
        if (isset($donnees['bio'])) {
            $champs[] = "bio = ?";
            $params[] = $donnees['bio'];
        }
        if (isset($donnees['email'])) {
            $champs[] = "email = ?";
            $params[] = $donnees['email'];
        }
        if ($photo_path) {
            $champs[] = "photo = ?";
            $params[] = $photo_path;
        }

        if (empty($champs)) return true;

        $params[] = $id;
        $sql = "UPDATE utilisateurs SET " . implode(', ', $champs) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * GÉRER COMPÉTENCES
     */
    public function gererCompetences(int $id_utilisateur, int $id_competence, string $niveau, string $action = 'sauvegarder'): bool {
        if ($action === 'supprimer') {
            $stmt = $this->db->prepare("DELETE FROM utilisateurs_competences WHERE id_utilisateur = ? AND id_competence = ?");
            return $stmt->execute([$id_utilisateur, $id_competence]);
        } else {
            // SQLite INSERT OR REPLACE
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO utilisateurs_competences (id_utilisateur, id_competence, niveau) VALUES (?, ?, ?)");
            return $stmt->execute([$id_utilisateur, $id_competence, $niveau]);
        }
    }

    /**
     * SUPPRIMER COMPTE
     */
    public function supprimerCompte(int $id): bool {
        $user = $this->trouverParId($id);
        if ($user && $user['photo']) {
            $chemin = __DIR__ . '/../../' . $user['photo'];
            if (file_exists($chemin)) {
                unlink($chemin);
            }
        }
        return $this->supprimer($id);
    }

    /**
     * GÉNÉRER UN TOKEN DE RÉCUPÉRATION
     */
    public function genererTokenRecuperation(string $email): ?string {
        // Vérifier si l'utilisateur existe
        $stmt = $this->db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) return null;

        $token = bin2hex(random_bytes(32));
        $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->db->prepare("INSERT INTO recuperation_mot_de_passe (email, token, expire_le) VALUES (?, ?, ?)");
        if ($stmt->execute([$email, $token, $expire])) {
            return $token;
        }
        return null;
    }

    /**
     * VÉRIFIER UN TOKEN DE RÉCUPÉRATION
     */
    public function verifierTokenRecuperation(string $token): ?string {
        $stmt = $this->db->prepare("SELECT email FROM recuperation_mot_de_passe WHERE token = ? AND expire_le > datetime('now')");
        $stmt->execute([$token]);
        $res = $stmt->fetch();
        return $res ? $res['email'] : null;
    }

    /**
     * RÉINITIALISER LE MOT DE PASSE
     */
    public function reinitialiserMdp(string $email, string $nouveau_mdp): bool {
        $mdp_hashe = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE email = ?");
        if ($stmt->execute([$mdp_hashe, $email])) {
            // Supprimer les tokens utilisés pour cet email
            $stmt = $this->db->prepare("DELETE FROM recuperation_mot_de_passe WHERE email = ?");
            $stmt->execute([$email]);
            return true;
        }
        return false;
    }
}

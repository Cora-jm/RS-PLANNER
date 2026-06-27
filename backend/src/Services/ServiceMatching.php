<?php
namespace App\Services;

use App\Core\Database;

/**
 * SERVICE MATCHING
 * Géré par : DAREL
 * Rôle : Calculer les compatibilités utilisateurs ↔ projets.
 * Consulter docs/API_CONTRACT.md section "Matching" pour les formats.
 */
class ServiceMatching {

    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnexion();
    }

    /**
     * RECHERCHER DES COLLABORATEURS POUR UN PROJET
     * Basé sur les compétences requises du projet et l'affinité des utilisateurs.
     */
    public function rechercherCollaborateurs(int $id_projet): array {
        // 1. Récupérer les compétences requises du projet
        $stmtReq = $this->db->prepare("SELECT id_competence FROM competences_projet WHERE id_projet = ?");
        $stmtReq->execute([$id_projet]);
        $competencesRequises = $stmtReq->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($competencesRequises)) return [];

        // 2. Trouver les utilisateurs qui possèdent au moins une de ces compétences
        // On exclut le propriétaire et les membres actuels du projet
        $placeholders = implode(',', array_fill(0, count($competencesRequises), '?'));
        $sql = "SELECT DISTINCT u.id, u.nom, u.photo 
                FROM utilisateurs u
                JOIN utilisateurs_competences uc ON u.id = uc.id_utilisateur
                WHERE uc.id_competence IN ($placeholders)
                AND u.id NOT IN (SELECT id_utilisateur FROM membres_projet WHERE id_projet = ?)
                AND u.id NOT IN (SELECT id_proprietaire FROM projets WHERE id = ?)";
        
        $params = array_merge($competencesRequises, [$id_projet, $id_projet]);
        $stmtUsers = $this->db->prepare($sql);
        $stmtUsers->execute($params);
        $candidats = $stmtUsers->fetchAll();

        $resultats = [];
        foreach ($candidats as $candidat) {
            // 3. Calculer l'affinité
            $affinite = $this->calculerAffinite($candidat['id'], $id_projet);
            
            // Récupérer les noms des compétences que l'utilisateur possède parmis celles du projet
            $stmtComp = $this->db->prepare("
                SELECT c.nom 
                FROM competences c
                JOIN utilisateurs_competences uc ON c.id = uc.id_competence
                WHERE uc.id_utilisateur = ? AND c.id IN ($placeholders)
            ");
            $stmtComp->execute(array_merge([$candidat['id']], $competencesRequises));
            $compNoms = $stmtComp->fetchAll(\PDO::FETCH_COLUMN);

            $resultats[] = [
                'id'          => $candidat['id'],
                'nom'         => $candidat['nom'],
                'photo'       => $candidat['photo'],
                'affinite'    => $affinite,
                'competences' => $compNoms
            ];
        }

        // 4. Trier par score décroissant
        usort($resultats, fn($a, $b) => $b['affinite'] <=> $a['affinite']);

        return $resultats;
    }

    /**
     * CALCULER L'AFFINITÉ UTILISATEUR ↔ PROJET (score en %)
     */
    public function calculerAffinite(int $id_utilisateur, int $id_projet): int {
        // 1. Récupérer les compétences requises du projet
        $stmtReq = $this->db->prepare("SELECT id_competence FROM competences_projet WHERE id_projet = ?");
        $stmtReq->execute([$id_projet]);
        $reqs = $stmtReq->fetchAll(\PDO::FETCH_COLUMN);
        
        if (empty($reqs)) return 0;

        // 2. Récupérer les niveaux des compétences de l'utilisateur correspondantes
        $placeholders = implode(',', array_fill(0, count($reqs), '?'));
        $stmtUser = $this->db->prepare("
            SELECT niveau FROM utilisateurs_competences 
            WHERE id_utilisateur = ? AND id_competence IN ($placeholders)
        ");
        $stmtUser->execute(array_merge([$id_utilisateur], $reqs));
        $competencesUser = $stmtUser->fetchAll();

        $pointsObtenus = 0;
        foreach ($competencesUser as $c) {
            $pointsObtenus += match($c['niveau']) {
                'Expert'        => 3,
                'Intermédiaire' => 2,
                'Débutant'      => 1,
                default         => 0
            };
        }

        $pointsMaxPossibles = count($reqs) * 3;
        return (int)(($pointsObtenus / $pointsMaxPossibles) * 100);
    }

    /**
     * SUGGÉRER DES TROCS (WIN-WIN)
     */
    public function suggererTroc(int $id_utilisateur): array {
        // 1. Récupérer mes projets et leurs besoins (compétences cherchées)
        $stmtMesBesoins = $this->db->prepare("
            SELECT p.id as mon_projet_id, p.titre as mon_projet_titre, cp.id_competence
            FROM projets p
            JOIN competences_projet cp ON p.id = cp.id_projet
            WHERE p.id_proprietaire = ?
        ");
        $stmtMesBesoins->execute([$id_utilisateur]);
        $mesBesoins = $stmtMesBesoins->fetchAll();

        if (empty($mesBesoins)) return [];

        $suggestions = [];

        // 2. Pour chaque besoin de mes projets, chercher qui peut y répondre
        foreach ($mesBesoins as $besoin) {
            $stmtAutres = $this->db->prepare("
                SELECT DISTINCT u.id, u.nom, c.nom as competence_nom
                FROM utilisateurs u
                JOIN utilisateurs_competences uc ON u.id = uc.id_utilisateur
                JOIN competences c ON uc.id_competence = c.id
                WHERE uc.id_competence = ? AND u.id != ?
            ");
            $stmtAutres->execute([$besoin['id_competence'], $id_utilisateur]);
            $candidats = $stmtAutres->fetchAll();

            foreach ($candidats as $candidat) {
                // 3. Pour chaque candidat, vérifier s'il a un projet qui a besoin de MOI
                $stmtLeursBesoins = $this->db->prepare("
                    SELECT p.id as leur_projet_id, p.titre as leur_projet_titre, cp.id_competence, c.nom as leur_besoin_nom
                    FROM projets p
                    JOIN competences_projet cp ON p.id = cp.id_projet
                    JOIN competences c ON cp.id_competence = c.id
                    WHERE p.id_proprietaire = ?
                ");
                $stmtLeursBesoins->execute([$candidat['id']]);
                $leursBesoins = $stmtLeursBesoins->fetchAll();

                foreach ($leursBesoins as $leurBesoin) {
                    // Est-ce que j'ai la compétence qu'ils cherchent ?
                    $stmtMaComp = $this->db->prepare("
                        SELECT 1 FROM utilisateurs_competences 
                        WHERE id_utilisateur = ? AND id_competence = ?
                    ");
                    $stmtMaComp->execute([$id_utilisateur, $leurBesoin['id_competence']]);
                    
                    if ($stmtMaComp->fetch()) {
                        // WIN-WIN identifié !
                        $suggestions[] = [
                            'utilisateur' => [
                                'id' => $candidat['id'],
                                'nom' => $candidat['nom']
                            ],
                            'mon_projet' => $besoin['mon_projet_titre'],
                            'ce_quil_apporte' => $candidat['competence_nom'],
                            'son_projet' => $leurBesoin['leur_projet_titre'],
                            'ce_que_tu_apportes' => $leurBesoin['leur_besoin_nom']
                        ];
                    }
                }
            }
        }

        // Dédupliquer les suggestions (un utilisateur peut matcher sur plusieurs compétences)
        $unique = [];
        foreach ($suggestions as $s) {
            $key = $s['utilisateur']['id'] . '-' . $s['mon_projet'] . '-' . $s['son_projet'];
            $unique[$key] = $s;
        }

        return array_values($unique);
    }
}

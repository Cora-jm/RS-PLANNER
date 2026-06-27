<?php
/**
 * ENDPOINT : /api/taches.php
 * Géré par : CEDRIC
 * Actions : ajouter | modifier | statut | assigner | supprimer
 */

require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Helpers.php';

use App\Modeles\Tache;
use function App\Core\setCorsHeaders;
use function App\Core\jsonSuccess;
use function App\Core\jsonError;
use function App\Core\getJsonBody;
use function App\Core\requireAuth;

setCorsHeaders();

$action = $_GET['action'] ?? '';
$modele = new Tache();

switch ($action) {

    case 'ajouter':
        $user_auth = requireAuth();
        $data = getJsonBody();
        $id_projet = (int)($data['id_projet'] ?? 0);
        $titre     = $data['titre'] ?? '';
        $date      = $data['date_echeance'] ?? null;
        $id_parent = !empty($data['id_parent']) ? (int)$data['id_parent'] : null;
        $id_assigne = !empty($data['id_assigne']) ? (int)$data['id_assigne'] : null;
        $desc       = $data['description'] ?? null;
        $statut     = $data['statut'] ?? 'A faire';

        if ($id_projet <= 0 || empty($titre)) jsonError('Données manquantes.');

        try {
            $id = $modele->ajouter($id_projet, $titre, $date, $id_parent, $id_assigne, $desc, $statut);
            if ($id) {
                jsonSuccess(['id' => $id, 'message' => 'Tâche créée !'], 201);
            } else {
                jsonError('Erreur lors de la création de la tâche.');
            }
        } catch (\Exception $e) {
            error_log("ERREUR CRÉATION TÂCHE : " . $e->getMessage());
            jsonError('Erreur serveur : ' . $e->getMessage(), 500);
        }
        break;

    case 'modifier':
        $user_auth = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jsonError('ID invalide.');
        if ($modele->modifier($id, getJsonBody())) jsonSuccess(['message' => 'Tâche modifiée.']);
        else jsonError('Erreur modification.');
        break;

    case 'statut':
        $user_auth = requireAuth();
        $data = getJsonBody();
        $id = (int)($data['id'] ?? 0);
        $statut = $data['statut'] ?? '';

        if ($id <= 0 || empty($statut)) jsonError('Données invalides.');

        if ($modele->mettreAJourStatut($id, $statut, $user_auth['id'])) {
            jsonSuccess(['message' => 'Statut mis à jour !']);
        } else {
            jsonError('Impossible de passer en cours : la tâche parente n\'est pas terminée.', 403);
        }
        break;

    case 'assigner':
        $user_auth = requireAuth();
        $data = getJsonBody();
        $id = (int)($data['id'] ?? 0);
        $id_u = (int)($data['id_utilisateur'] ?? 0);

        if ($id <= 0 || $id_u <= 0) jsonError('Données invalides.');

        if ($modele->assignerA($id, $id_u)) jsonSuccess(['message' => 'Tâche assignée !']);
        else jsonError('Erreur assignation.');
        break;

    case 'supprimer':
        $user_auth = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jsonError('ID invalide.');
        if ($modele->supprimerTache($id)) jsonSuccess(['message' => 'Tâche supprimée.']);
        else jsonError('Erreur suppression.');
        break;

    default:
        jsonError('Action inconnue.', 404);
}

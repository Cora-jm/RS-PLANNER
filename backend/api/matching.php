<?php
/**
 * ENDPOINT : /api/matching.php
 * Géré par : DAREL
 * Actions : collaborateurs | troc
 * Voir docs/API_CONTRACT.md pour le détail de chaque action.
 */

require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Helpers.php';

use App\Services\ServiceMatching;
use function App\Core\setCorsHeaders;
use function App\Core\jsonSuccess;
use function App\Core\jsonError;
use function App\Core\requireAuth;

setCorsHeaders();

$action  = $_GET['action'] ?? '';
$service = new ServiceMatching();

switch ($action) {

    // ------------------------------------------------------------------
    case 'collaborateurs':
        requireAuth();
        $id_projet = (int)($_GET['id_projet'] ?? 0);
        if ($id_projet <= 0) {
            jsonError('ID de projet invalide.');
        }
        $liste = $service->rechercherCollaborateurs($id_projet);
        jsonSuccess(['collaborateurs' => $liste]);
        break;

    // ------------------------------------------------------------------
    case 'troc':
        $user = requireAuth();
        $liste = $service->suggererTroc($user['id']);
        jsonSuccess(['suggestions' => $liste]);
        break;

    // ------------------------------------------------------------------
    default:
        jsonError('Action inconnue.', 404);
}

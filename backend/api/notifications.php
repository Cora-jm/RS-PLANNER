<?php
/**
 * ENDPOINT : /api/notifications.php
 * Actions : liste | lu
 */

require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Helpers.php';

use App\Services\ServiceNotification;
use function App\Core\setCorsHeaders;
use function App\Core\jsonSuccess;
use function App\Core\jsonError;
use function App\Core\requireAuth;

setCorsHeaders();

$action  = $_GET['action'] ?? '';
$service = new ServiceNotification();

switch ($action) {

    case 'liste':
        $user  = requireAuth();
        $liste = $service->listePourUtilisateur($user['id']);
        jsonSuccess(['notifications' => $liste]);
        break;

    case 'lu':
        $user = requireAuth();
        $id_notif = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($service->marquerCommeLu($user['id'], $id_notif)) {
            jsonSuccess(['message' => 'Marqué comme lu.']);
        } else {
            jsonError('Erreur mise à jour.');
        }
        break;

    default:
        jsonError('Action inconnue.', 404);
}

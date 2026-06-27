<?php
/**
 * ENDPOINT : /api/projets.php
 * Géré par : CEDRIC
 * Actions : liste | creer | detail | ajouter_membre | retirer_membre
 */

require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Helpers.php';

use App\Modeles\Projet;
use function App\Core\setCorsHeaders;
use function App\Core\jsonSuccess;
use function App\Core\jsonError;
use function App\Core\getJsonBody;
use function App\Core\requireAuth;

setCorsHeaders();

$action = $_GET['action'] ?? '';
$modele = new Projet();

switch ($action) {

    case 'liste':
        $user_auth = requireAuth();
        $projets   = $modele->listePourUtilisateur($user_auth['id']);
        jsonSuccess(['projets' => $projets]);
        break;

    case 'creer':
        $user_auth = requireAuth();
        $data = getJsonBody();
        $titre = $data['titre'] ?? '';
        $desc  = $data['description'] ?? '';
        $comps = $data['competences'] ?? []; // Array d'IDs

        if (empty($titre)) {
            jsonError('Le titre du projet est obligatoire.');
        }

        $id = $modele->creerProjet($user_auth['id'], $titre, $desc, $comps);
        if ($id) {
            jsonSuccess(['id' => $id, 'message' => 'Projet créé avec succès !'], 201);
        } else {
            jsonError('Erreur lors de la création du projet.');
        }
        break;

    case 'detail':
        $user_auth = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jsonError('ID de projet invalide.');

        $projet = $modele->detail($id);
        if (!$projet) jsonError('Projet non trouvé.', 404);

        // Vérifier si l'utilisateur est membre
        $est_membre = false;
        foreach ($projet['membres'] as $membre) {
            if ($membre['id'] === $user_auth['id']) {
                $est_membre = true;
                break;
            }
        }

        if (!$est_membre) {
            jsonError('Accès refusé à ce projet.', 403);
        }

        jsonSuccess(['projet' => $projet]);
        break;

    case 'ajouter_membre':
        $user_auth = requireAuth();
        $id_projet = (int)($_GET['id'] ?? 0);
        $data = getJsonBody();
        $id_utilisateur = (int)($data['id_utilisateur'] ?? 0);

        if ($id_projet <= 0 || $id_utilisateur <= 0) jsonError('Données invalides.');

        $couleur = $modele->ajouterMembre($id_projet, $id_utilisateur);
        if ($couleur) {
            // NOTIFICATION : Prévenir l'utilisateur qu'il a une invitation
            $projet = $modele->trouverParId($id_projet);
            \App\Services\ServiceNotification::ajouter(
                $id_utilisateur,
                'Invitation',
                'Invitation au projet',
                "Vous avez été invité au projet « " . $projet['titre'] . " » par " . $user_auth['nom'],
                "/projet/" . $id_projet
            );
            jsonSuccess(['couleur' => $couleur, 'message' => 'Invitation envoyée !']);
        } else {
            jsonError('Utilisateur déjà membre ou erreur.');
        }
        break;

    case 'repondre_invitation':
        $user_auth = requireAuth();
        $data = getJsonBody();
        $id_projet = (int)($data['id_projet'] ?? 0);
        $reponse   = $data['reponse'] ?? ''; // 'accepte' | 'refuse'

        if ($id_projet <= 0 || !in_array($reponse, ['accepte', 'refuse'])) {
            jsonError('Données invalides.');
        }

        if ($modele->repondreInvitation($id_projet, $user_auth['id'], $reponse)) {
            $msg = $reponse === 'accepte' ? 'Invitation acceptée !' : 'Invitation refusée.';
            jsonSuccess(['message' => $msg]);
        } else {
            jsonError('Erreur lors de la réponse à l\'invitation.');
        }
        break;

    case 'retirer_membre':
        $user_auth = requireAuth();
        $id_projet = (int)($_GET['id'] ?? 0);
        $id_utilisateur = (int)($_GET['id_utilisateur'] ?? 0);

        if ($id_projet <= 0 || $id_utilisateur <= 0) jsonError('Données invalides.');

        // Vérifier que l'utilisateur connecté est le proprio
        $projet = $modele->trouverParId($id_projet);
        if ($projet['id_proprietaire'] !== $user_auth['id']) {
            jsonError('Seul le propriétaire peut retirer des membres.', 403);
        }

        if ($modele->retirerMembre($id_projet, $id_utilisateur)) {
            jsonSuccess(['message' => 'Membre retiré.']);
        } else {
            jsonError('Erreur lors de la suppression.');
        }
        break;

    default:
        jsonError('Action inconnue.', 404);
}

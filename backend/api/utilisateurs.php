<?php
/**
 * ENDPOINT : /api/utilisateurs.php
 * Géré par : DAREL
 * Actions : inscription | connexion | profil | modifier | competence | mot_de_passe_oublie | reinitialiser_mot_de_passe
 */

require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/Autoloader.php';
require_once __DIR__ . '/../core/Helpers.php';

use App\Modeles\Utilisateur;
use App\Services\ServiceEmail;
use function App\Core\setCorsHeaders;
use function App\Core\jsonSuccess;
use function App\Core\jsonError;
use function App\Core\getJsonBody;
use function App\Core\jwtGenerer;
use function App\Core\requireAuth;

setCorsHeaders();

$action = $_GET['action'] ?? '';
$modele = new Utilisateur();

switch ($action) {

    case 'inscription':
        $data = getJsonBody();
        $nom   = $data['nom'] ?? '';
        $email = $data['email'] ?? '';
        $mdp   = $data['mot_de_passe'] ?? '';
        if (empty($nom) || empty($email) || empty($mdp)) jsonError('Champs requis.');
        if ($modele->inscription($nom, $email, $mdp)) jsonSuccess(['message' => 'Inscription réussie !'], 201);
        else jsonError('Email déjà utilisé.');
        break;

    case 'connexion':
        $data  = getJsonBody();
        $email = $data['email'] ?? '';
        $mdp   = $data['mot_de_passe'] ?? '';
        $user = $modele->connexion($email, $mdp);
        if ($user) {
            $token = jwtGenerer(['id' => $user['id'], 'nom' => $user['nom'], 'email' => $user['email']]);
            jsonSuccess(['token' => $token, 'utilisateur' => $user]);
        } else jsonError('Email ou mot de passe incorrect.', 401);
        break;

    case 'profil':
        $user_auth = requireAuth();
        $profil    = $modele->profilComplet($user_auth['id']);
        if ($profil) jsonSuccess(['utilisateur' => $profil]);
        else jsonError('Utilisateur non trouvé.', 404);
        break;

    case 'modifier':
        $user_auth = requireAuth();
        $donnees = $_POST ?: getJsonBody();
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename  = $user_auth['id'] . '_' . time() . '.' . $extension;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                $photo_path = 'backend/uploads/profiles/' . $filename;
            }
        }
        if ($modele->modifierProfil($user_auth['id'], $donnees, $photo_path)) jsonSuccess(['message' => 'Profil mis à jour !', 'photo' => $photo_path]);
        else jsonError('Erreur lors de la mise à jour.');
        break;

    case 'activites':
        $user_auth = requireAuth();
        $activites = \App\Services\ServiceActivite::fluxPourUtilisateur($user_auth['id']);
        jsonSuccess(['activites' => $activites]);
        break;

    case 'competence':
        $user_auth = requireAuth();
        $data      = getJsonBody();
        if ($modele->gererCompetences($user_auth['id'], $data['id_competence'] ?? 0, $data['niveau'] ?? 'Débutant', $data['action'] ?? 'sauvegarder')) jsonSuccess(['message' => 'Compétence mise à jour !']);
        else jsonError('Erreur compétence.');
        break;

    case 'mot_de_passe_oublie':
        $data = getJsonBody();
        $email = $data['email'] ?? '';
        if (empty($email)) jsonError('Email requis.');
        $token = $modele->genererTokenRecuperation($email);
        
        if ($token) {
            ServiceEmail::envoyerRecuperation($email, $token);
        }
        
        jsonSuccess(['message' => 'Si un compte existe, un lien vous a été envoyé par email.']);
        break;

    case 'reinitialiser_mot_de_passe':
        $data  = getJsonBody();
        $token = $data['token'] ?? '';
        $mdp   = $data['nouveau_mot_de_passe'] ?? '';
        if (empty($token) || empty($mdp)) jsonError('Données manquantes.');
        $email = $modele->verifierTokenRecuperation($token);
        if ($email && $modele->reinitialiserMdp($email, $mdp)) jsonSuccess(['message' => 'Mot de passe modifié !']);
        else jsonError('Lien invalide ou expiré.', 401);
        break;

    case 'rechercher':
        requireAuth();
        $query = $_GET['q'] ?? '';
        if (strlen($query) < 2) jsonSuccess(['utilisateurs' => []]);
        
        // Recherche par nom ou email
        $sql = "SELECT id, nom, email, photo FROM utilisateurs 
                WHERE (nom LIKE ? OR email LIKE ?) 
                LIMIT 10";
        $stmt = App\Core\Database::getConnexion()->prepare($sql);
        $stmt->execute(["%$query%", "%$query%"]);
        jsonSuccess(['utilisateurs' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Action inconnue.', 404);
}

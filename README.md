# RS Planner — Stratis

Gestionnaire de projets collaboratif avec matching de compétences.

## Stack

| Couche    | Techno                        |
|-----------|-------------------------------|
| Frontend  | React 18 + React Router v6 + Vite |
| Backend   | PHP 8.1+ REST API             |
| Base de données | SQLite (fichier `backend/data/rs_planner.db`) |

---

## Répartition des tâches

| Membre   | Zone                                        | Fichiers à toucher |
|----------|---------------------------------------------|--------------------|
| FRANCK   | Frontend — pages projets & tâches           | `frontend/src/pages/Dashboard.jsx`, `ProjectDetail.jsx` |
| AHMADOU  | Frontend — auth & profil                    | `frontend/src/pages/Login.jsx`, `Register.jsx`, `Profile.jsx`, `context/AuthContext.jsx`, `components/Navbar.jsx` |
| CEDRIC   | Backend — API projets & tâches              | `backend/api/projets.php`, `backend/api/taches.php`, `backend/src/Modeles/Projet.php`, `backend/src/Modeles/Tache.php` |
| DAREL    | Backend — auth, matching, config BD         | `backend/api/utilisateurs.php`, `backend/api/matching.php`, `backend/src/Modeles/Utilisateur.php`, `backend/src/Services/ServiceMatching.php`, `backend/schema.sql`, `backend/core/` |

> **Règle d'or :** personne ne touche aux fichiers de l'autre équipe.  
> Le seul point de contact est `docs/API_CONTRACT.md`.

---

## Installation

### Backend (CEDRIC + DAREL)

1. Placer le dossier `backend/` dans le répertoire de votre serveur PHP (ex: `htdocs/RS_planner/backend/`).
2. Initialiser la base de données SQLite :
   ```bash
   php -r "require 'backend/config/configuration.php'; require 'backend/core/Database.php'; \App\Core\Database::getConnexion();"
   sqlite3 backend/data/rs_planner.db < backend/schema.sql
   ```
3. Vérifier que `backend/data/` est accessible en écriture par le serveur PHP.

### Frontend (FRANCK + AHMADOU)

```bash
cd frontend
npm install
npm run dev
```

Le proxy Vite redirige `/api/*` vers `http://localhost/RS_planner/backend/api/`.  
Adapter l'URL dans `vite.config.js` si votre config PHP est différente.

---

## Contrat d'API

Voir [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md) — **lecture obligatoire pour tout le monde**.
# RS-PLANNER

# 📋 Contrat d'API — Stratis RS Planner

> **Ce fichier est la seule interface entre le Frontend et le Backend.**
> Tout changement ici doit être validé par les deux équipes.

**Base URL :** `http://localhost/RS_planner/backend/api`  
**Format :** JSON exclusivement  
**Auth :** JWT Bearer Token dans le header `Authorization: Bearer <token>`

---

## 🔐 Authentification — DAREL (backend) / AHMADOU (frontend)

### `POST /utilisateurs.php?action=inscription`
**Body :**
```json
{ "nom": "string", "email": "string", "mot_de_passe": "string" }
```
**Réponse 201 :**
```json
{ "success": true, "message": "Compte créé avec succès." }
```

### `POST /utilisateurs.php?action=connexion`
**Body :**
```json
{ "email": "string", "mot_de_passe": "string" }
```
**Réponse 200 :**
```json
{
  "success": true,
  "token": "jwt_token_ici",
  "utilisateur": { "id": 1, "nom": "Alice", "email": "alice@mail.com", "photo": "url_ou_null" }
}
```
**Réponse 401 :**
```json
{ "success": false, "message": "Email ou mot de passe incorrect." }
```

### `GET /utilisateurs.php?action=profil` *(Auth requise)*
**Réponse 200 :**
```json
{
  "id": 1,
  "nom": "Alice",
  "email": "alice@mail.com",
  "bio": "Dev passionnée",
  "photo": "url_ou_null",
  "competences": [
    { "id": 3, "nom": "React", "niveau": "Expert" }
  ]
}
```

### `PUT /utilisateurs.php?action=modifier` *(Auth requise)*
**Body :**
```json
{ "nom": "string", "bio": "string", "email": "string" }
```
**Réponse 200 :**
```json
{ "success": true, "message": "Profil mis à jour." }
```

### `POST /utilisateurs.php?action=competence` *(Auth requise)*
**Body :**
```json
{ "id_competence": 3, "niveau": "Expert", "action": "sauvegarder" }
```
Valeurs de `action` : `"sauvegarder"` | `"supprimer"`  
**Réponse 200 :**
```json
{ "success": true }
```

---

## 📁 Projets — CEDRIC (backend) / FRANCK (frontend)

### `GET /projets.php?action=liste` *(Auth requise)*
**Réponse 200 :**
```json
[
  {
    "id": 1,
    "titre": "Mon App",
    "description": "Description...",
    "statut": "En cours",
    "avancement": 65,
    "nb_membres": 3
  }
]
```

### `POST /projets.php?action=creer` *(Auth requise)*
**Body :**
```json
{ "titre": "string", "description": "string" }
```
**Réponse 201 :**
```json
{ "success": true, "id": 5 }
```

### `GET /projets.php?action=detail&id=1` *(Auth requise)*
**Réponse 200 :**
```json
{
  "id": 1,
  "titre": "Mon App",
  "description": "Description...",
  "statut": "En cours",
  "avancement": 65,
  "membres": [
    { "id": 2, "nom": "Bob", "couleur": "#e74c3c", "photo": null }
  ],
  "taches": [
    {
      "id": 10,
      "titre": "Maquetter la page d'accueil",
      "statut": "Terminé",
      "date_echeance": "2026-02-15",
      "id_parent": null,
      "assigne_a": { "id": 2, "nom": "Bob", "couleur": "#e74c3c" }
    }
  ]
}
```

### `POST /projets.php?action=ajouter_membre&id=1` *(Auth requise)*
**Body :**
```json
{ "id_utilisateur": 4 }
```
**Réponse 200 :**
```json
{ "success": true, "couleur": "#3498db" }
```

### `DELETE /projets.php?action=retirer_membre&id=1` *(Auth requise)*
**Body :**
```json
{ "id_utilisateur": 4 }
```
**Réponse 200 :**
```json
{ "success": true }
```

---

## ✅ Tâches — CEDRIC (backend) / FRANCK (frontend)

### `POST /taches.php?action=ajouter` *(Auth requise)*
**Body :**
```json
{ "id_projet": 1, "titre": "string", "date_echeance": "2026-03-01", "id_parent": null }
```
**Réponse 201 :**
```json
{ "success": true, "id": 11 }
```

### `PUT /taches.php?action=modifier&id=10` *(Auth requise)*
**Body :**
```json
{ "titre": "string", "date_echeance": "2026-03-10" }
```
**Réponse 200 :**
```json
{ "success": true }
```

### `PUT /taches.php?action=statut&id=10` *(Auth requise)*
**Body :**
```json
{ "statut": "En cours" }
```
Valeurs valides : `"À faire"` | `"En cours"` | `"Terminé"`  
**Réponse 200 :**
```json
{ "success": true }
```

### `PUT /taches.php?action=assigner&id=10` *(Auth requise)*
**Body :**
```json
{ "id_utilisateur": 2 }
```
**Réponse 200 :**
```json
{ "success": true }
```

### `DELETE /taches.php?action=supprimer&id=10` *(Auth requise)*
**Réponse 200 :**
```json
{ "success": true }
```

---

## 🤝 Matching — DAREL (backend) / FRANCK (frontend)

### `GET /matching.php?action=collaborateurs&id_projet=1` *(Auth requise)*
**Réponse 200 :**
```json
[
  { "id": 5, "nom": "Clara", "affinite": 85, "competences": ["React", "Node"] }
]
```

### `GET /matching.php?action=troc&id_utilisateur=1` *(Auth requise)*
**Réponse 200 :**
```json
[
  {
    "utilisateur": { "id": 6, "nom": "David" },
    "ce_quil_apporte": "Design UI",
    "ce_que_tu_apportes": "Backend PHP"
  }
]
```

---

## ⚠️ Format d'erreur standard
Toute erreur retourne :
```json
{ "success": false, "message": "Description de l'erreur." }
```
Codes HTTP utilisés : `200`, `201`, `400` (mauvaise requête), `401` (non auth), `403` (interdit), `404`, `500`

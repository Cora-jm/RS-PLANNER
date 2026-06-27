<?php
/**
 * CONFIGURATION — RS Planner Backend
 * Géré par : DAREL
 * À ne PAS versionner en production (ajouter dans .gitignore).
 */

// Chemin vers le fichier SQLite (relatif à ce fichier)
define('DB_PATH', __DIR__ . '/../data/rs_planner.db');

// Clé secrète pour les tokens JWT — À CHANGER en production !
define('JWT_SECRET', 'stratis_secret_key_change_me');

// Durée de validité du token JWT (en secondes)
define('JWT_EXPIRATION', 60 * 60 * 24); // 24 heures

// Origines autorisées pour les requêtes CORS
define('CORS_ORIGIN', getenv('CORS_ORIGIN') ?: 'http://localhost');

// CONFIGURATION SMTP GMAIL (Pour l'envoi de mails)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); // Port SSL
define('SMTP_USER', 'alex6.draken@gmail.com'); // À REMPLIR
define('SMTP_PASS', 'qmvzprckewzondfm'); // À REMPLIR (16 caractères)
define('MAIL_FROM_NAME', 'Stratis — RS Planner');


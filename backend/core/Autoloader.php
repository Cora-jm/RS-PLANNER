<?php
namespace App\Core;

/**
 * AUTOLOADER — Chargement automatique des classes
 * Géré par : DAREL
 * Ne pas modifier ce fichier.
 */
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $relativeClass = substr($class, 4);
    $chemin = str_replace('\\', '/', $relativeClass);
    
    // On définit la racine du projet
    $baseDir = '/var/www/html';
    
    // 1. Cas particulier pour le dossier core
    if (str_starts_with($relativeClass, 'Core\\')) {
        $fichier = $baseDir . '/core/' . substr($chemin, 5) . '.php';
    } 
    // 2. Autres classes (Modeles, Services)
    else {
        $fichier = $baseDir . '/src/' . $chemin . '.php';
    }

    if (file_exists($fichier)) {
        require_once $fichier;
    }
});

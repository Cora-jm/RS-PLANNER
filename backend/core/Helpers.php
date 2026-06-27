<?php
namespace App\Core;

/**
 * HELPERS GLOBAUX — Router, CORS, JWT, Réponses JSON
 * Géré par : DAREL
 * Inclus automatiquement via l'Autoloader.
 */

// ---------------------------------------------------------------
// CORS — autorise les requêtes depuis le frontend React
// ---------------------------------------------------------------
function setCorsHeaders(): void {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Réponse aux pre-flight OPTIONS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ---------------------------------------------------------------
// RÉPONSES JSON
// ---------------------------------------------------------------
function jsonSuccess(array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ---------------------------------------------------------------
// JWT — Génération et vérification
// ---------------------------------------------------------------

/**
 * Génère un token JWT simple (HS256 maison, sans lib externe).
 */
function jwtGenerer(array $payload): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + JWT_EXPIRATION;
    $payload_enc = base64url_encode(json_encode($payload));
    $signature   = base64url_encode(hash_hmac('sha256', "$header.$payload_enc", JWT_SECRET, true));
    return "$header.$payload_enc.$signature";
}

/**
 * Vérifie un token JWT et retourne le payload, ou null si invalide/expiré.
 */
function jwtVerifier(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload_enc, $signature] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload_enc", JWT_SECRET, true));

    if (!hash_equals($expected, $signature)) return null;

    $payload = json_decode(base64url_decode($payload_enc), true);
    if (!$payload || $payload['exp'] < time()) return null;

    return $payload;
}

/**
 * Récupère l'utilisateur connecté depuis le header Authorization.
 * Appelle jsonError(401) et stoppe si non authentifié.
 */
function requireAuth(): array {
    // Vérifie le header standard ou le header redirigé par Apache
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    if (!str_starts_with($header, 'Bearer ')) {
        jsonError('Token manquant ou invalide.', 401);
    }
    $token   = substr($header, 7);
    $payload = jwtVerifier($token);
    if (!$payload) {
        jsonError('Token expiré ou invalide.', 401);
    }
    return $payload; // contient au minimum ['id' => X, 'nom' => '...']
}

// ---------------------------------------------------------------
// Helpers Base64URL (utilisés par JWT)
// ---------------------------------------------------------------
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

// ---------------------------------------------------------------
// Body JSON de la requête entrante
// ---------------------------------------------------------------
function getJsonBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

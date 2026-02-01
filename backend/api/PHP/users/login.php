<?php
// par maxime derènes
// normalement c'est prêt et adapté pour être utilisé
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/manager/UserManager.php';

$content = file_get_contents('php://input');
$data = json_decode($content, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['email']) && isset($data['password'])) {
    $userManager = new UserManager($pdo);
    $result = $userManager->login($data['email'], $data['password']);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'reponse' => 'Connexion réussie',
            'api_token' => $result['api_token'],
            'user' => $result['user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['reponse' => $result['message']]);
    }
    exit;
} else {
    http_response_code(400);
    echo json_encode(['reponse' => 'Données manquantes']);
    exit;
}

// TP1 Le Vessnard
<?php
ob_start();

// maxime, sebastien, joachim

header('Access-Control-Allow-Origin: https://sae301-303.netlify.app');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration des sessions pour le cross-domain (Netlify <-> Render)
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None',
]);

require_once __DIR__ . '/../config/configdb.php';
require_once __DIR__ . '/PHP/users/manager/UserManager.php';
require_once __DIR__ . '/PHP/boxes/boxmanager.php';
require_once __DIR__ . '/PHP/stats/StatsManager.php';
require_once __DIR__ . '/PHP/orders/OrderManager.php';


$content = file_get_contents('php://input');
$data = json_decode($content, true);
$userManager = new UserManager($pdo);

// On s'assure qu'aucune sortie (lignes vides, BOM, warnings) ne corrompt le JSON
if (ob_get_length()) ob_clean();

// Utilisateurs

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'check-session') {
        echo json_encode($userManager->checkSession());
        exit;
    }
    if ($_GET['action'] === 'stats') {
        $statsManager = new StatsManager($pdo);
        echo json_encode($statsManager->getAllStats());
        exit;
    }
    if ($_GET['action'] === 'get-orders') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id_user = $_SESSION['id_user'] ?? null;
        if (!$id_user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté']);
            exit;
        }
        $orderManager = new OrderManager($pdo);
        echo json_encode($orderManager->getOrdersByUser($id_user));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier d'abord les actions spécifiques (réinitialisation, déconnexion)
    if (isset($data['action'])) {
        // Réinitialisation du mot de passe
        if ($data['action'] === 'reset-password') {
            if (isset($data['email']) && isset($data['password'])) {
                $result = $userManager->resetPassword($data['email'], $data['password']);
                if ($result['success']) {
                    http_response_code(200);
                    echo json_encode($result);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis.']);
            }
            exit;
        }

        // Déconnexion
        if ($data['action'] === 'logout') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $id_user = $_SESSION['id_user'] ?? null;
            if ($id_user) {
                echo json_encode($userManager->logout($id_user));
            } else {
                echo json_encode(['success' => true, 'message' => 'Déjà déconnecté']);
            }
            exit;
        }

        // Mise à jour des informations utilisateur
        if ($data['action'] === 'update-user') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $id_user = $_SESSION['id_user'] ?? null;
            if (!$id_user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
                exit;
            }
            $result = $userManager->updateUser($id_user, $data);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            exit;
        }
    }

    // Inscription
    if (isset($data['nom']) && !isset($data['action'])) {
        try {
            $userManager->insertUser($data);
            http_response_code(201);
            echo json_encode(['reponse' => 'utilisateur créé', 'success' => true]);
            exit;
        } catch (Exception $e) {
            error_log("Erreur inscription: " . $e->getMessage());
            http_response_code($e->getMessage() === "L'email existe déjà" ? 409 : 400);
            echo json_encode(['error' => $e->getMessage(), 'success' => false]);
            exit;
        }
    }

    // Connexion (seulement si pas d'action spécifique)
    // On vérifie soit les champs directs, soit un flag d'action, soit la présence d'email/password même avec d'autres noms
    $isLoginAttempt = (!isset($data['action']) || $data['action'] === 'login') && 
                      (isset($data['email']) || isset($data['email_conn']) || isset($data['email_inscr'])) &&
                      (isset($data['password']) || isset($data['mdp_conn']) || isset($data['mdp_inscr']));

    if ($isLoginAttempt && !isset($data['nom'])) { // !isset($data['nom']) pour ne pas confondre avec l'inscription
        try {
            $result = $userManager->login($data, null);
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(401);
                echo json_encode(['reponse' => $result['message'], 'success' => false]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'reponse' => 'Erreur serveur lors de la connexion', 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Met à jour la fidélité
    if (isset($data['action']) && $data['action'] === 'update-fidelite') {
        $session = $userManager->checkSession();
        
        if ($session['logged_in'] && isset($data['fidelite'])) {
            try {
                $userManager->setFidelite($_SESSION['id_user'], (int)$data['fidelite']);
                echo json_encode(['success' => true, 'message' => 'Points mis à jour']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Non connecté ou données manquantes']);
        }
        exit;
    }
}

// Commandes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($data['action'])) {
        if ($data['action'] === 'create-commande') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $id_user = $_SESSION['id_user'] ?? null;
            
            if (!$id_user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté']);
                exit;
            }
            
            if (isset($data['date_commande']) && isset($data['prix_total']) && isset($data['mode']) && isset($data['produits'])) {
                try {
                    error_log("Données reçues: " . json_encode($data));
                    
                    $pdo->beginTransaction();
                    
                    $query = $pdo->prepare("INSERT INTO commande (id_user, date_commande, prix_total, mode) VALUES (?, ?, ?, ?)");
                    $query->execute([
                        $id_user,
                        $data['date_commande'],
                        $data['prix_total'],
                        $data['mode']
                    ]);
                    
                    $id_commande = $pdo->lastInsertId();
                    
                    $queryDetail = $pdo->prepare("INSERT INTO detail_commande (id_commande, id_produit, quantite) VALUES (?, ?, ?)");
                    
                    foreach ($data['produits'] as $produit) {
                        if (!isset($produit['id_produit']) || !isset($produit['quantite'])) {
                            throw new Exception('Produit invalide: id_produit ou quantite manquant. Données reçues: ' . json_encode($produit));
                        }
                        
                        if (!is_numeric($produit['id_produit']) || !is_numeric($produit['quantite'])) {
                            throw new Exception('Produit invalide: id_produit ou quantite n\'est pas numérique');
                        }
                        
                        if ($produit['quantite'] <= 0) {
                            throw new Exception('Produit invalide: quantite doit être supérieure à 0');
                        }
                        
                        error_log("Insertion produit: id_produit=" . $produit['id_produit'] . ", quantite=" . $produit['quantite']);
                        
                        $queryDetail->execute([
                            $id_commande,
                            $produit['id_produit'],
                            $produit['quantite']
                        ]);
                    }
                    
                    // Mettre à jour la fidélité de l'utilisateur avec le montant de la commande
                    $userManager->addFidelite($id_user, floatval($data['prix_total']));
                    
                    $pdo->commit();
                    
                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Commande envoyer avec succès',
                        'id_commande' => $id_commande
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    
                    error_log("Erreur envoi commande: " . $e->getMessage());
                    
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Erreur lors de l\'envoi de la commande: ' . $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(400);
                $missing = [];
                if (!isset($data['date_commande'])) $missing[] = 'date_commande';
                if (!isset($data['prix_total'])) $missing[] = 'prix_total';
                if (!isset($data['mode'])) $missing[] = 'mode';
                if (!isset($data['produits'])) $missing[] = 'produits';
                
                echo json_encode([
                    'success' => false, 
                    'error' => 'Données manquantes: ' . implode(', ', $missing)
                ]);
            }
            exit;
        }
    }
}

// Boxes
$boxManager = new BoxManager($pdo);
try {
    if (isset($_GET['id_produit'])) {
        $boxes = $boxManager->findById($_GET['id_produit']);
    } else {
        $boxes = $boxManager->findAll();
    }
    echo json_encode($boxes);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des produits']);
}
exit;
?>
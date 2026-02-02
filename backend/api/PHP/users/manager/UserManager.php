<?php
// maxime derènes
require_once __DIR__ . '/../../../../config/configdb.php'; // connexion

// id_user api_token nom prenom mail password statut_etud tel adresse fidelite
// id auto increment
// fonction insert
class UserManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }


    public function insertUser(array $data): void
    {
        // Note: 'email_inscr' et 'mdp_inscr' viennent de la page de joachim ( rajouté par joachim )
        $nom = $data['nom'] ?? null;
        $prenom = $data['prenom'] ?? null;
        $email = $data['email'] ?? ($data['email_inscr'] ?? null);
        $password = $data['password'] ?? ($data['mdp_inscr'] ?? null);
        $tel = $data['telephone'] ?? ($data['tel'] ?? null);
        $adresse = $data['adresse'] ?? null;

        if (!$nom || !$prenom || !$email || !$password || !$tel || !$adresse) {
            throw new Exception("Données manquantes : nom, prenom, email, password, telephone et adresse sont requis.");
        }

        $sqlCheck = "SELECT id_user FROM utilisateur WHERE LOWER(email) = LOWER(:email)";
        $stmtCheck = $this->pdo->prepare($sqlCheck);
        $stmtCheck->execute(['email' => $email]);
        if ($stmtCheck->fetch()) {
            throw new Exception("L'email existe déjà");
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO utilisateur (api_token, nom, prenom, email, password, statut_etud, tel, adresse, fidelite) 
        VALUES (:api_token, :nom, :prenom, :email, :password, :statut_etud, :tel, :adresse, :fidelite)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'api_token' => $data['api_token'] ?? null,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $passwordHash,
            'statut_etud' => $data['statut_etud'] ?? 0,
            'tel' => $tel,
            'adresse' => $adresse,
            'fidelite' => $data['fidelite'] ?? 0,
        ]);
    }


    public function login($email, $password): array
    {
        // On permet les différents noms de champs possibles
        if (is_array($email)) {
            $data = $email;
            $email = $data['email'] ?? ($data['email_conn'] ?? ($data['email_inscr'] ?? null));
            $password = $data['password'] ?? ($data['mdp_conn'] ?? ($data['mdp_inscr'] ?? null));
        }

        if (!$email || !$password) {
            return ['success' => false, 'message' => 'Email ou mot de passe manquant'];
        }

        $sql = "SELECT * FROM utilisateur WHERE LOWER(email) = LOWER(:email)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $token = bin2hex(random_bytes(32));

            // Mise à jour du token dans la DB
            $updateSql = "UPDATE utilisateur SET api_token = :token WHERE id_user = :id_user";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute(['token' => $token, 'id_user' => $user['id_user']]);

            // Initialisation de la session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['api_token'] = $token;
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['statut_etud'] = $user['statut_etud'];
            $_SESSION['tel'] = $user['tel'];
            $_SESSION['adresse'] = $user['adresse'];
            $_SESSION['fidelite'] = $user['fidelite'];
 
            // Définir un cookie persistant pour la restauration de session (SameSite=None pour cross-domain)
            setcookie('api_token', $token, [
                'expires' => time() + (86400 * 1), // 1 jour
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ]);

             return [
                 'success' => true,
                 'api_token' => $token,
                 'user' => [
                     'id_user' => $user['id_user'],
                     'nom' => $user['nom'],
                     'prenom' => $user['prenom'],
                     'email' => $user['email'],
                     'statut_etud' => (bool)$user['statut_etud']
                 ]
             ];
        }

        return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    }


    public function logout(int $id_user): array
    {
        // On invalide le token dans la base
        $sql = "UPDATE utilisateur SET api_token = NULL WHERE id_user = :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_user' => $id_user]);

        // Nettoyage session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
 
        // Supprimer le cookie de restauration
        setcookie('api_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);

         return ['success' => true];
    }


    public function checkSession(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si la session PHP est vide, on tente de la restaurer via le cookie api_token
        if ((empty($_SESSION['api_token']) || empty($_SESSION['id_user'])) && !empty($_COOKIE['api_token'])) {
            $sql = "SELECT * FROM utilisateur WHERE api_token = :token";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $_COOKIE['api_token']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Restauration de la session depuis les données de la DB
                $_SESSION['id_user'] = $user['id_user'];
                $_SESSION['api_token'] = $user['api_token'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['statut_etud'] = $user['statut_etud'];
                $_SESSION['tel'] = $user['tel'];
                $_SESSION['adresse'] = $user['adresse'];
                $_SESSION['fidelite'] = $user['fidelite'];
            }
        }

        // Si on a une session valide (soit déjà présente, soit restaurée)
        if (!empty($_SESSION['api_token']) && !empty($_SESSION['id_user'])) {
            return [
                'isLoggedIn' => true,
                'user' => [
                    'id_user' => $_SESSION['id_user'],
                    'nom' => $_SESSION['nom'] ?? '',
                    'prenom' => $_SESSION['prenom'] ?? '',
                    'email' => $_SESSION['email'] ?? '',
                    'statut_etud' => (bool)($_SESSION['statut_etud'] ?? false),
                    'tel' => $_SESSION['tel'] ?? '',
                    'adresse' => $_SESSION['adresse'] ?? '',
                    'fidelite' => $_SESSION['fidelite'] ?? 0
                ]
            ];
        }

        return ['isLoggedIn' => false, 'user' => null];
    }

    public function resetPassword(string $email, string $newPassword): array
    {
        $sql = "SELECT id_user FROM utilisateur WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Aucun utilisateur trouvé avec cet email.'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateSql = "UPDATE utilisateur SET password = :password WHERE email = :email";
        $updateStmt = $this->pdo->prepare($updateSql);
        $updateStmt->execute([
            'password' => $passwordHash,
            'email' => $email
        ]);

        return [
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès !'
        ];
    }

    public function setFidelite($id_user, $fidelite)
    {
        $sql = "UPDATE utilisateur SET fidelite = :fidelite WHERE id_user = :id_user";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['fidelite' => $fidelite, 'id_user' => $id_user]);
    }

    public function addFidelite($id_user, $montant)
    {
        // Récupérer la fidélité actuelle
        $sql = "SELECT fidelite FROM utilisateur WHERE id_user = :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_user' => $id_user]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $nouvelleFidelite = (int)round(($user['fidelite'] ?? 0) + $montant);
            $this->setFidelite($id_user, $nouvelleFidelite);
            
            // Mettre à jour la session si elle existe
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_user) {
                $_SESSION['fidelite'] = $nouvelleFidelite;
            }
            
            return $nouvelleFidelite;
        }
        return false;
    }


    public function updateUser($id_user, array $data): array
    {
        try {
            // Vérifier que l'utilisateur existe
            $sqlCheck = "SELECT * FROM utilisateur WHERE id_user = :id_user";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute(['id_user' => $id_user]);
            $user = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'message' => 'Utilisateur non trouvé'];
            }

            // Vérifier si l'email est modifié et s'il n'existe pas déjà
            if (isset($data['email']) && strtolower($data['email']) !== strtolower($user['email'])) {
                $sqlEmailCheck = "SELECT id_user FROM utilisateur WHERE LOWER(email) = LOWER(:email) AND id_user != :id_user";
                $stmtEmailCheck = $this->pdo->prepare($sqlEmailCheck);
                $stmtEmailCheck->execute(['email' => $data['email'], 'id_user' => $id_user]);
                if ($stmtEmailCheck->fetch()) {
                    return ['success' => false, 'message' => "Cet email est déjà utilisé par un autre compte"];
                }
            }

            // Vérifier le mot de passe actuel si un nouveau mot de passe est fourni
            if (isset($data['new_password']) && !empty($data['new_password'])) {
                if (!isset($data['current_password']) || empty($data['current_password'])) {
                    return ['success' => false, 'message' => 'Le mot de passe actuel est requis pour changer le mot de passe'];
                }
                
                if (!password_verify($data['current_password'], $user['password'])) {
                    return ['success' => false, 'message' => 'Le mot de passe actuel est incorrect'];
                }

                // Hasher le nouveau mot de passe
                $data['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
            }

            // Construire la requête UPDATE dynamiquement
            $updateFields = [];
            $params = ['id_user' => $id_user];

            if (isset($data['nom'])) {
                $updateFields[] = "nom = :nom";
                $params['nom'] = $data['nom'];
            }
            if (isset($data['prenom'])) {
                $updateFields[] = "prenom = :prenom";
                $params['prenom'] = $data['prenom'];
            }
            if (isset($data['email'])) {
                $updateFields[] = "email = :email";
                $params['email'] = $data['email'];
            }
            if (isset($data['tel']) || isset($data['telephone'])) {
                $updateFields[] = "tel = :tel";
                $params['tel'] = $data['tel'] ?? $data['telephone'];
            }
            if (isset($data['adresse'])) {
                $updateFields[] = "adresse = :adresse";
                $params['adresse'] = $data['adresse'];
            }
            if (isset($data['password'])) {
                $updateFields[] = "password = :password";
                $params['password'] = $data['password'];
            }

            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'Aucune donnée à mettre à jour'];
            }

            $sql = "UPDATE utilisateur SET " . implode(', ', $updateFields) . " WHERE id_user = :id_user";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Mettre à jour la session si elle existe
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_user) {
                if (isset($data['nom'])) $_SESSION['nom'] = $data['nom'];
                if (isset($data['prenom'])) $_SESSION['prenom'] = $data['prenom'];
                if (isset($data['email'])) $_SESSION['email'] = $data['email'];
                if (isset($data['tel']) || isset($data['telephone'])) {
                    $_SESSION['tel'] = $data['tel'] ?? $data['telephone'];
                }
                if (isset($data['adresse'])) $_SESSION['adresse'] = $data['adresse'];
            }

            return ['success' => true, 'message' => 'Informations mises à jour avec succès'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
        }
    }
}
?>
<?php
// témi kergastel
require_once __DIR__ . '/../../../config/configdb.php';

class OrderManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function createOrder($id_user, $prix_total, $mode, $produits)
    {
        file_put_contents(__DIR__ . '/../../debug_log.txt', "Début createOrder: User=$id_user, Total=$prix_total\n", FILE_APPEND);
        try {
            // soit on sauvegarde tout , soit rien 
            $this->pdo->beginTransaction();

            // Insérer la commande principale
            $sql = "INSERT INTO commande (id_user, date_commande, prix_total, mode) VALUES (:id_user, NOW(), :prix_total, :mode)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id_user' => $id_user,
                'prix_total' => $prix_total,
                'mode' => $mode
            ]);

            // Récupérer l'ID de la commande qu'on vient de créer
            $id_commande = $this->pdo->lastInsertId();

            // Insérer chaque produit dans detail_commande
            $sqlDetail = "INSERT INTO detail_commande (id_commande, id_produit, quantite) VALUES (:id_commande, :id_produit, :quantite)";
            $stmtDetail = $this->pdo->prepare($sqlDetail);

            foreach ($produits as $item) {
                $stmtDetail->execute([
                    'id_commande' => $id_commande,
                    // Attention: le front envoie 'produit' qui contient 'id_produit'
                    'id_produit' => $item['produit']['id_produit'],
                    'quantite' => $item['quantite']
                ]);
            }

            // Valider la transaction
            $this->pdo->commit();

            return ['success' => true, 'id_commande' => $id_commande];

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            // En cas d'erreur, on annule tout
            $this->pdo->rollBack();
            file_put_contents(__DIR__ . '/../../debug_log.txt', "Erreur createOrder: " . $e->getMessage() . "\n", FILE_APPEND);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getOrdersByUser($id_user)
    {
        try {
            $sql = "SELECT c.id_commande, c.date_commande, c.prix_total, c.mode,
                           STRING_AGG(COALESCE(p.nom, 'Produit inconnu') || ' (x' || COALESCE(dc.quantite::text, '0') || ')', ', ') as produits
                    FROM commande c
                    LEFT JOIN detail_commande dc ON c.id_commande = dc.id_commande
                    LEFT JOIN produit p ON dc.id_produit = p.id_produit
                    WHERE c.id_user = :id_user
                    GROUP BY c.id_commande, c.date_commande, c.prix_total, c.mode
                    ORDER BY c.date_commande DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id_user' => $id_user]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $orders;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
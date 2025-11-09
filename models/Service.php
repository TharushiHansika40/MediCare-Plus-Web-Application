<?php
require_once __DIR__ . '/../config/config.php';

class Service {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function create($data) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO services (name, category, description) 
                 VALUES (?, ?, ?)"
            );
            return $stmt->execute([
                $data['name'],
                $data['category'],
                $data['description']
            ]);
        } catch (PDOException $e) {
            error_log("Error creating service: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE services 
                 SET name = ?, category = ?, description = ? 
                 WHERE id = ?"
            );
            return $stmt->execute([
                $data['name'],
                $data['category'],
                $data['description'],
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating service: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM services WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting service: " . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting service: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($category = null) {
        try {
            $query = "SELECT * FROM services";
            $params = [];

            if ($category) {
                $query .= " WHERE category = ?";
                $params[] = $category;
            }

            $query .= " ORDER BY category, name";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting services: " . $e->getMessage());
            return false;
        }
    }

    public function getCategories() {
        try {
            $stmt = $this->pdo->query(
                "SELECT DISTINCT category 
                 FROM services 
                 WHERE category IS NOT NULL 
                 ORDER BY category"
            );
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting service categories: " . $e->getMessage());
            return false;
        }
    }

    public function getDoctorsByService($serviceId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT d.*, u.first_name, u.last_name 
                 FROM doctors d
                 JOIN users u ON d.user_id = u.id
                 JOIN doctor_services ds ON d.id = ds.doctor_id
                 WHERE ds.service_id = ?
                 ORDER BY d.rating_avg DESC"
            );
            $stmt->execute([$serviceId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting doctors by service: " . $e->getMessage());
            return false;
        }
    }
}
?>
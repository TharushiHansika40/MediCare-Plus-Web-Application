<?php
require_once __DIR__ . '/../config/config.php';

class Doctor {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function create($userId, $data) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO doctors (
                    user_id, display_name, specialization, qualifications,
                    experience_years, consultation_fee, location, bio, avatar
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            return $stmt->execute([
                $userId,
                $data['display_name'],
                $data['specialization'],
                $data['qualifications'],
                $data['experience_years'],
                $data['consultation_fee'],
                $data['location'],
                $data['bio'],
                $data['avatar'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error creating doctor profile: " . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT d.*, u.email, u.first_name, u.last_name, u.phone 
                 FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE d.id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting doctor: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE doctors 
                 SET display_name = ?, specialization = ?, qualifications = ?,
                     experience_years = ?, consultation_fee = ?, location = ?,
                     bio = ?, avatar = ?
                 WHERE id = ?"
            );
            
            return $stmt->execute([
                $data['display_name'],
                $data['specialization'],
                $data['qualifications'],
                $data['experience_years'],
                $data['consultation_fee'],
                $data['location'],
                $data['bio'],
                $data['avatar'] ?? null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating doctor: " . $e->getMessage());
            return false;
        }
    }

    public function search($criteria) {
        try {
            $query = "SELECT d.*, u.first_name, u.last_name 
                     FROM doctors d 
                     JOIN users u ON d.user_id = u.id 
                     WHERE 1=1";
            $params = [];

            if (!empty($criteria['specialization'])) {
                $query .= " AND d.specialization LIKE ?";
                $params[] = '%' . $criteria['specialization'] . '%';
            }

            if (!empty($criteria['location'])) {
                $query .= " AND d.location LIKE ?";
                $params[] = '%' . $criteria['location'] . '%';
            }

            if (!empty($criteria['name'])) {
                $query .= " AND (d.display_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
                $params[] = '%' . $criteria['name'] . '%';
                $params[] = '%' . $criteria['name'] . '%';
                $params[] = '%' . $criteria['name'] . '%';
            }

            // Add pagination
            $page = $criteria['page'] ?? 1;
            $limit = $criteria['limit'] ?? 10;
            $offset = ($page - 1) * $limit;
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error searching doctors: " . $e->getMessage());
            return false;
        }
    }

    public function updateRating($id) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE doctors d
                 SET rating_avg = (
                     SELECT AVG(rating) 
                     FROM reviews 
                     WHERE doctor_id = ?
                 ),
                 rating_count = (
                     SELECT COUNT(*) 
                     FROM reviews 
                     WHERE doctor_id = ?
                 )
                 WHERE d.id = ?"
            );
            return $stmt->execute([$id, $id, $id]);
        } catch (PDOException $e) {
            error_log("Error updating doctor rating: " . $e->getMessage());
            return false;
        }
    }

    public function addService($doctorId, $serviceId) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO doctor_services (doctor_id, service_id) 
                 VALUES (?, ?)"
            );
            return $stmt->execute([$doctorId, $serviceId]);
        } catch (PDOException $e) {
            error_log("Error adding doctor service: " . $e->getMessage());
            return false;
        }
    }

    public function removeService($doctorId, $serviceId) {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM doctor_services 
                 WHERE doctor_id = ? AND service_id = ?"
            );
            return $stmt->execute([$doctorId, $serviceId]);
        } catch (PDOException $e) {
            error_log("Error removing doctor service: " . $e->getMessage());
            return false;
        }
    }

    public function getServices($doctorId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT s.* 
                 FROM services s
                 JOIN doctor_services ds ON s.id = ds.service_id
                 WHERE ds.doctor_id = ?"
            );
            $stmt->execute([$doctorId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting doctor services: " . $e->getMessage());
            return false;
        }
    }
}
?>
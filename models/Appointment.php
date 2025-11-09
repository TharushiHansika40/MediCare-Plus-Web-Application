<?php
require_once __DIR__ . '/../config/config.php';

class Appointment {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function create($data) {
        try {
            // Start transaction
            $this->pdo->beginTransaction();

            // Check if slot is available
            $slotStmt = $this->pdo->prepare(
                "SELECT slot_status 
                 FROM availability_slots 
                 WHERE id = ? AND slot_status = 'available'
                 FOR UPDATE"
            );
            $slotStmt->execute([$data['slot_id']]);
            
            if (!$slotStmt->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'This slot is no longer available'];
            }

            // Create appointment
            $appointmentStmt = $this->pdo->prepare(
                "INSERT INTO appointments (
                    patient_id, doctor_id, slot_id, reason, status
                ) VALUES (?, ?, ?, ?, 'pending')"
            );
            
            $appointmentStmt->execute([
                $data['patient_id'],
                $data['doctor_id'],
                $data['slot_id'],
                $data['reason']
            ]);
            
            $appointmentId = $this->pdo->lastInsertId();

            // Update slot status
            $updateSlotStmt = $this->pdo->prepare(
                "UPDATE availability_slots 
                 SET slot_status = 'booked' 
                 WHERE id = ?"
            );
            $updateSlotStmt->execute([$data['slot_id']]);

            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'appointment_id' => $appointmentId,
                'message' => 'Appointment booked successfully'
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error creating appointment: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error booking appointment'];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT a.*, 
                        u1.first_name as patient_first_name,
                        u1.last_name as patient_last_name,
                        d.display_name as doctor_name,
                        s.date, s.start_time, s.end_time
                 FROM appointments a
                 JOIN users u1 ON a.patient_id = u1.id
                 JOIN doctors d ON a.doctor_id = d.id
                 JOIN availability_slots s ON a.slot_id = s.id
                 WHERE a.id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting appointment: " . $e->getMessage());
            return false;
        }
    }

    public function updateStatus($id, $status, $updatedBy) {
        try {
            $this->pdo->beginTransaction();

            // Update appointment status
            $stmt = $this->pdo->prepare(
                "UPDATE appointments 
                 SET status = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            $stmt->execute([$status, $id]);

            // If cancelled, free up the slot
            if ($status === 'cancelled') {
                $stmt = $this->pdo->prepare(
                    "UPDATE availability_slots s
                     JOIN appointments a ON s.id = a.slot_id
                     SET s.slot_status = 'available'
                     WHERE a.id = ?"
                );
                $stmt->execute([$id]);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating appointment status: " . $e->getMessage());
            return false;
        }
    }

    public function getByPatient($patientId, $status = null) {
        try {
            $query = "SELECT a.*, 
                            d.display_name as doctor_name,
                            d.specialization,
                            s.date, s.start_time, s.end_time
                     FROM appointments a
                     JOIN doctors d ON a.doctor_id = d.id
                     JOIN availability_slots s ON a.slot_id = s.id
                     WHERE a.patient_id = ?";
            $params = [$patientId];

            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }

            $query .= " ORDER BY s.date DESC, s.start_time DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting patient appointments: " . $e->getMessage());
            return false;
        }
    }

    public function getByDoctor($doctorId, $status = null) {
        try {
            $query = "SELECT a.*, 
                            u.first_name as patient_first_name,
                            u.last_name as patient_last_name,
                            s.date, s.start_time, s.end_time
                     FROM appointments a
                     JOIN users u ON a.patient_id = u.id
                     JOIN availability_slots s ON a.slot_id = s.id
                     WHERE a.doctor_id = ?";
            $params = [$doctorId];

            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }

            $query .= " ORDER BY s.date ASC, s.start_time ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting doctor appointments: " . $e->getMessage());
            return false;
        }
    }
}
?>
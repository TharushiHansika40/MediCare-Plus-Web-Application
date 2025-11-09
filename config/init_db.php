<?php
require_once 'config.php';

try {
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('admin','doctor','patient') NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        phone VARCHAR(30),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Doctors table
    $pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        display_name VARCHAR(255) NOT NULL,
        specialization VARCHAR(255),
        qualifications TEXT,
        experience_years INT,
        consultation_fee DECIMAL(10,2),
        rating_avg DECIMAL(3,2) DEFAULT 0,
        rating_count INT DEFAULT 0,
        location VARCHAR(255),
        bio TEXT,
        avatar VARCHAR(255),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Services table
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(255),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Doctor services table
    $pdo->exec("CREATE TABLE IF NOT EXISTS doctor_services (
        doctor_id INT NOT NULL,
        service_id INT NOT NULL,
        PRIMARY KEY (doctor_id, service_id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Availability slots table
    $pdo->exec("CREATE TABLE IF NOT EXISTS availability_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        slot_status ENUM('available','booked','blocked') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Appointments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        slot_id INT NOT NULL,
        status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL,
        FOREIGN KEY (patient_id) REFERENCES users(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (slot_id) REFERENCES availability_slots(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Medical reports table
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        title VARCHAR(255),
        description TEXT,
        file_path VARCHAR(255),
        uploaded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        subject VARCHAR(255),
        body TEXT,
        read_flag TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Reviews table
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT,
        doctor_id INT NOT NULL,
        patient_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Payments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT,
        patient_id INT,
        amount DECIMAL(10,2),
        currency VARCHAR(10) DEFAULT 'USD',
        status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
        transaction_ref VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create indexes for commonly searched columns
    $pdo->exec("CREATE INDEX idx_doctor_specialization ON doctors(specialization)");
    $pdo->exec("CREATE INDEX idx_doctor_location ON doctors(location)");
    $pdo->exec("CREATE INDEX idx_appointment_status ON appointments(status)");
    $pdo->exec("CREATE INDEX idx_slot_date ON availability_slots(date)");

    echo "Database and tables created successfully!";

} catch(PDOException $e) {
    die("Error creating database: " . $e->getMessage());
}
?>
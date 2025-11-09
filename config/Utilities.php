<?php
class Utilities {
    public static function uploadFile($file, $targetDir, $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf']) {
        try {
            if (!isset($file['error']) || is_array($file['error'])) {
                throw new RuntimeException('Invalid file parameters');
            }

            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new RuntimeException('No file sent');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new RuntimeException('File size exceeded');
                default:
                    throw new RuntimeException('Unknown file error');
            }

            if (!in_array($file['type'], $allowedTypes)) {
                throw new RuntimeException('Invalid file type');
            }

            $fileName = sprintf(
                '%s-%s.%s',
                uniqid('file_', true),
                date('Ymd'),
                pathinfo($file['name'], PATHINFO_EXTENSION)
            );

            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new RuntimeException('Failed to move uploaded file');
            }

            return [
                'success' => true,
                'file_path' => $fileName
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function formatDate($date, $format = 'Y-m-d') {
        return date($format, strtotime($date));
    }

    public static function formatTime($time, $format = 'H:i') {
        return date($format, strtotime($time));
    }

    public static function generateTimeSlots($startTime, $endTime, $duration = 30) {
        $slots = [];
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        
        for ($time = $start; $time < $end; $time += $duration * 60) {
            $slots[] = [
                'start' => date('H:i', $time),
                'end' => date('H:i', $time + $duration * 60)
            ];
        }
        
        return $slots;
    }

    public static function validateDateTime($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public static function sendEmail($to, $subject, $body) {
        // Implement email sending functionality here
        // For example, using PHPMailer or mail() function
        return mail($to, $subject, $body);
    }

    public static function generatePaginationLinks($currentPage, $totalPages, $baseUrl) {
        $links = [];
        
        if ($currentPage > 1) {
            $links['prev'] = $baseUrl . '?page=' . ($currentPage - 1);
        }
        
        if ($currentPage < $totalPages) {
            $links['next'] = $baseUrl . '?page=' . ($currentPage + 1);
        }
        
        return $links;
    }

    public static function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    public static function formatCurrency($amount, $currency = 'USD') {
        return number_format($amount, 2) . ' ' . $currency;
    }

    public static function validatePhone($phone) {
        return preg_match('/^\+?[\d\s-()]{10,}$/', $phone);
    }

    public static function getTimeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' days ago';
        } else {
            return date('M j, Y', $time);
        }
    }

    public static function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        return substr(str_shuffle($chars), 0, $length);
    }
}
?>
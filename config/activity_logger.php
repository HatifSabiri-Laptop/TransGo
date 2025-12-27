<?php

/**
 * Activity Logger Class
 * Logs all important admin and user actions
 */
class ActivityLogger {
    private $conn;
    private $logFile;
    
    // Action types
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_REGISTER = 'register';
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_VIEW = 'view';
    const ACTION_FAILED_LOGIN = 'failed_login';
    const ACTION_RESERVATION = 'create_reservation';
    const ACTION_PAYMENT = 'payment';
    const ACTION_CANCELLATION = 'cancellation';
    
    public function __construct($conn = null) {
        $this->conn = $conn ?: getDBConnection();
        
        // ✅ SET MYSQL TIMEZONE TO MATCH PHP
        try {
            $this->conn->query("SET time_zone = '+07:00'");
        } catch (Exception $e) {
            error_log("Failed to set MySQL timezone: " . $e->getMessage());
        }
        
        // Create logs directory if it doesn't exist
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Daily log file
        $this->logFile = $logDir . '/activity_' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log an activity
     * 
     * @param int|null $userId User ID (null for guest/system actions)
     * @param string $action Action type (use constants)
     * @param string $description Description of the action
     * @param string|null $ipAddress IP address (auto-detected if null)
     * @param array|null $metadata Additional data as JSON
     * @return bool Success status
     */
    public function log($userId, $action, $description, $ipAddress = null, $metadata = null) {
        try {
            // Get IP address
            if ($ipAddress === null) {
                $ipAddress = $this->getClientIP();
            }
            
            // Get user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Get username - FIXED: Always get from database first
            $username = $this->getUsername($userId);
            
            // Convert metadata to JSON
            $metadataJson = $metadata ? json_encode($metadata) : null;
            
            // Log to database
            $result = $this->logToDatabase($userId, $username, $action, $description, $ipAddress, $userAgent, $metadataJson);
            
            // Log to file (with error suppression for InfinityFree)
            @$this->logToFile($userId, $username, $action, $description, $ipAddress);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Activity Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get username from user ID - FIXED VERSION
     */
    private function getUsername($userId) {
        // If user ID provided, ALWAYS get from database (most reliable)
        if ($userId) {
            try {
                $stmt = $this->conn->prepare("SELECT full_name FROM users WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $username = $row['full_name'];
                        $stmt->close();
                        return $username;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log("Get username error: " . $e->getMessage());
            }
        }
        
        // Fallback: check session
        if (isset($_SESSION['full_name']) && !empty($_SESSION['full_name'])) {
            return $_SESSION['full_name'];
        }
        
        return 'Guest';
    }
    
    /**
     * Log to database - FIXED: Return boolean
     */
    private function logToDatabase($userId, $username, $action, $description, $ipAddress, $userAgent, $metadata) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO activity_logs 
                (user_id, username, action, description, ip_address, user_agent, metadata, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            // Handle NULL user_id properly
            $userIdParam = $userId ?: null;
            
            $stmt->bind_param(
                "issssss",
                $userIdParam,
                $username,
                $action,
                $description,
                $ipAddress,
                $userAgent,
                $metadata
            );
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Activity log execute error: " . $stmt->error);
            }
            
            $stmt->close();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Activity log database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log to file (with InfinityFree compatibility)
     */
    private function logToFile($userId, $username, $action, $description, $ipAddress) {
        try {
            // Check if directory is writable (won't work on InfinityFree)
            if (!is_writable(dirname($this->logFile))) {
                return false;
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $userInfo = $username ? "$username (ID: $userId)" : ($userId ? "User ID: $userId" : "Guest");
            $logEntry = "[$timestamp] [$action] $userInfo | IP: $ipAddress | $description" . PHP_EOL;
            
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Silently fail on InfinityFree
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get activity logs with filters
     * 
     * @param array $filters Filters (user_id, action, date_from, date_to, limit)
     * @return array Activity logs
     */
    public function getLogs($filters = []) {
        $sql = "SELECT * FROM activity_logs WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
            $types .= "s";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
            $types .= "i";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        return $logs;
    }
    
    /**
     * Clean old logs (optional - for maintenance)
     * Delete logs older than specified days
     */
    public function cleanOldLogs($days = 90) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM activity_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param("i", $days);
            $result = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            return $affected;
        } catch (Exception $e) {
            error_log("Clean old logs error: " . $e->getMessage());
            return false;
        }
    }
}

?>
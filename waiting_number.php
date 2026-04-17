<?php
// includes/waiting_number.php - Generate unique waiting numbers

class WaitingNumber {
    private $conn;
    
    // Waiting number patterns
    const PATTERNS = [
        'ALPHA' => 'A-Z',      // A, B, C... Z, AA, AB...
        'NUMERIC' => '001',    // 001, 002, 003...
        'ALPHA_NUM' => 'A1',   // A1, A2... Z99
        'EMOJI' => '🎯'        // 🎯1, 🎯2, 🎯3...
    ];
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Generate unique waiting number for order
     */
    public function generateWaitingNumber($restaurant_id, $pattern = 'NUMERIC') {
        $today = date('Y-m-d');
        
        // Get or create sequence for today
        $query = "SELECT last_number FROM waiting_number_sequences 
                  WHERE restaurant_id = :restaurant_id AND date = :date
                  FOR UPDATE";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':date', $today);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            // Create new sequence for today
            $query = "INSERT INTO waiting_number_sequences (restaurant_id, date, last_number)
                      VALUES (:restaurant_id, :date, 0)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':date', $today);
            $stmt->execute();
            
            $next_number = 1;
        } else {
            $sequence = $stmt->fetch();
            $next_number = $sequence['last_number'] + 1;
        }
        
        // Update sequence
        $query = "UPDATE waiting_number_sequences 
                  SET last_number = :next_number
                  WHERE restaurant_id = :restaurant_id AND date = :date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':next_number', $next_number);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':date', $today);
        $stmt->execute();
        
        // Format based on pattern
        return $this->formatWaitingNumber($next_number, $pattern);
    }
    
    /**
     * Format waiting number based on pattern
     */
    private function formatWaitingNumber($number, $pattern) {
        switch ($pattern) {
            case 'ALPHA':
                return $this->numberToAlpha($number);
            
            case 'NUMERIC':
                return str_pad($number, 3, '0', STR_PAD_LEFT); // 001, 002, 003
            
            case 'ALPHA_NUM':
                $letter = chr(65 + (($number - 1) % 26)); // A-Z
                $num = ceil($number / 26);
                return $letter . $num; // A1, B1... Z1, A2, B2...
            
            case 'EMOJI':
                $emojis = ['🎯', '🍕', '🍔', '🍜', '🌮', '🍱', '🥗', '🍛'];
                $emoji = $emojis[($number - 1) % count($emojis)];
                return $emoji . $number;
            
            default:
                return str_pad($number, 3, '0', STR_PAD_LEFT);
        }
    }
    
    /**
     * Convert number to alpha (A, B, C... Z, AA, AB...)
     */
    private function numberToAlpha($number) {
        $alpha = '';
        while ($number > 0) {
            $number--;
            $alpha = chr(65 + ($number % 26)) . $alpha;
            $number = floor($number / 26);
        }
        return $alpha;
    }
    
    /**
     * Get current waiting numbers for display board
     */
    public function getCurrentWaitingNumbers($restaurant_id, $limit = 10) {
        $query = "SELECT waiting_number, order_status, table_number, order_type, created_at
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE(created_at) = CURDATE()
                  AND order_status IN ('pending', 'confirmed', 'preparing', 'ready')
                  ORDER BY created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check if waiting number is ready
     */
    public function isOrderReady($restaurant_id, $waiting_number) {
        $query = "SELECT order_status FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND waiting_number = :waiting_number
                  AND DATE(created_at) = CURDATE()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':waiting_number', $waiting_number);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $order = $stmt->fetch();
            return $order['order_status'] === 'ready';
        }
        
        return false;
    }
    
    /**
     * Reset daily sequences (run via cron at midnight)
     */
    public function resetDailySequences() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Archive old sequences
        $query = "DELETE FROM waiting_number_sequences WHERE date < :yesterday";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':yesterday', $yesterday);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}

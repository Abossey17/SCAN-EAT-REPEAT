<?php
// includes/payment_split.php - Split Payment with Dual Commission

class PaymentSplit {
    private $conn;
    
    // Commission rates
    const VISA_PLATFORM_COMMISSION = 10.00; // 10%
    const VISA_DEVELOPER_COMMISSION = 5.00; // 5%
    const VISA_TOTAL_COMMISSION = 15.00; // 15%
    
    const MOMO_PLATFORM_COMMISSION = 10.00; // 10%
    const MOMO_DEVELOPER_COMMISSION = 1.00; // 1%
    const MOMO_TOTAL_COMMISSION = 11.00; // 11%
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Calculate commission breakdown
     */
    public function calculateCommissions($amount, $payment_method) {
        if ($payment_method === 'visa') {
            $platform_commission = ($amount * self::VISA_PLATFORM_COMMISSION) / 100;
            $developer_commission = ($amount * self::VISA_DEVELOPER_COMMISSION) / 100;
            $total_commission = ($amount * self::VISA_TOTAL_COMMISSION) / 100;
        } else {
            $platform_commission = ($amount * self::MOMO_PLATFORM_COMMISSION) / 100;
            $developer_commission = ($amount * self::MOMO_DEVELOPER_COMMISSION) / 100;
            $total_commission = ($amount * self::MOMO_TOTAL_COMMISSION) / 100;
        }
        
        $restaurant_amount = $amount - $total_commission;
        
        return [
            'order_amount' => $amount,
            'platform_commission' => round($platform_commission, 2),
            'developer_commission' => round($developer_commission, 2),
            'total_commission' => round($total_commission, 2),
            'restaurant_amount' => round($restaurant_amount, 2)
        ];
    }
    
    /**
     * Create Paystack subaccount for restaurant
     */
    public function createPaystackSubaccount($restaurant_id, $business_name, $bank_code, $account_number) {
        $url = "https://api.paystack.co/subaccount";
        
        // Platform takes 10%, Developer takes 5% = 15% total
        // Paystack subaccount will handle the split
        $data = [
            'business_name' => $business_name,
            'settlement_bank' => $bank_code,
            'account_number' => $account_number,
            'percentage_charge' => self::VISA_TOTAL_COMMISSION, // 15% total deduction
            'description' => 'Restaurant ID: ' . $restaurant_id,
            'metadata' => [
                'restaurant_id' => $restaurant_id,
                'platform_commission' => self::VISA_PLATFORM_COMMISSION,
                'developer_commission' => self::VISA_DEVELOPER_COMMISSION
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $response = json_decode($result, true);
        
        if ($http_code === 200 || $http_code === 201) {
            if ($response['status'] === true) {
                return [
                    'success' => true,
                    'subaccount_code' => $response['data']['subaccount_code'],
                    'subaccount_id' => $response['data']['id']
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => $response['message'] ?? 'Failed to create subaccount'
        ];
    }
    
    /**
     * Initialize Paystack payment with split commission
     */
    public function initializePaystackPayment($order_id, $amount, $email, $callback_url, $restaurant_id) {
        // Get restaurant's subaccount
        $query = "SELECT paystack_subaccount_code, name FROM restaurants WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $restaurant_id);
        $stmt->execute();
        $restaurant = $stmt->fetch();
        
        if (!$restaurant || !$restaurant['paystack_subaccount_code']) {
            throw new Exception('Restaurant subaccount not configured');
        }
        
        // Calculate commissions
        $commissions = $this->calculateCommissions($amount, 'visa');
        
        $url = "https://api.paystack.co/transaction/initialize";
        
        $fields = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to pesewas
            'currency' => CURRENCY,
            'reference' => $this->generateReference('PS'),
            'callback_url' => $callback_url,
            'subaccount' => $restaurant['paystack_subaccount_code'],
            'transaction_charge' => 0, // Platform bears Paystack fees
            'bearer' => 'account', // Platform pays transaction fees
            'metadata' => [
                'order_id' => $order_id,
                'restaurant_id' => $restaurant_id,
                'restaurant_name' => $restaurant['name'],
                'platform_commission' => $commissions['platform_commission'],
                'developer_commission' => $commissions['developer_commission'],
                'restaurant_amount' => $commissions['restaurant_amount']
            ]
        ];
        
        $fields_string = json_encode($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
    
    /**
     * Verify Paystack payment and record commissions
     */
    public function verifyPaystackPayment($reference) {
        $url = "https://api.paystack.co/transaction/verify/" . $reference;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
    
    /**
     * Initialize Mobile Money payment with commission tracking
     */
    public function initializeMoMoPayment($order_id, $amount, $phone_number, $network, $restaurant_id) {
        // Calculate commissions for mobile money (11% total)
        $commissions = $this->calculateCommissions($amount, 'mobile_money');
        
        $reference = $this->generateReference('MM');
        
        // This is a placeholder - implement based on your MoMo provider
        // The actual amount charged should be the full order amount
        // Commission is deducted from settlement
        
        $url = "https://your-momo-api-endpoint.com/debit";
        
        $fields = [
            'amount' => $amount,
            'currency' => CURRENCY,
            'phone_number' => $phone_number,
            'network' => $network,
            'reference' => $reference,
            'description' => 'Order #' . $order_id,
            'metadata' => [
                'order_id' => $order_id,
                'restaurant_id' => $restaurant_id,
                'platform_commission' => $commissions['platform_commission'],
                'developer_commission' => $commissions['developer_commission'],
                'restaurant_amount' => $commissions['restaurant_amount']
            ]
        ];
        
        // Store payment record
        $query = "INSERT INTO payments (order_id, amount, payment_method, transaction_id, 
                 mobile_money_number, mobile_money_network, status) 
                 VALUES (:order_id, :amount, 'mobile_money', :transaction_id, 
                 :phone_number, :network, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':transaction_id', $reference);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':network', $network);
        $stmt->execute();
        
        return [
            'status' => true,
            'reference' => $reference,
            'message' => 'Please approve the payment prompt on your phone',
            'commissions' => $commissions
        ];
    }
    
    /**
     * Record commission after successful payment
     */
    public function recordCommission($order_id, $restaurant_id, $amount, $payment_method, $status = 'settled') {
        $commissions = $this->calculateCommissions($amount, $payment_method);
        
        // Update order with commission details
        $query = "UPDATE orders SET 
                  platform_commission = :platform_commission,
                  developer_commission = :developer_commission,
                  restaurant_amount = :restaurant_amount
                  WHERE id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':platform_commission', $commissions['platform_commission']);
        $stmt->bindParam(':developer_commission', $commissions['developer_commission']);
        $stmt->bindParam(':restaurant_amount', $commissions['restaurant_amount']);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        // Record in commission_records table
        $query = "INSERT INTO commission_records 
                  (order_id, restaurant_id, order_amount, platform_commission, developer_commission, 
                   restaurant_amount, payment_method, status, settled_at)
                  VALUES 
                  (:order_id, :restaurant_id, :order_amount, :platform_commission, :developer_commission,
                   :restaurant_amount, :payment_method, :status, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':order_amount', $commissions['order_amount']);
        $stmt->bindParam(':platform_commission', $commissions['platform_commission']);
        $stmt->bindParam(':developer_commission', $commissions['developer_commission']);
        $stmt->bindParam(':restaurant_amount', $commissions['restaurant_amount']);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        
        return $commissions;
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($order_id, $status, $transaction_id = null, $response_data = null) {
        $query = "UPDATE payments SET status = :status";
        
        if ($transaction_id) {
            $query .= ", transaction_id = :transaction_id";
        }
        
        if ($response_data) {
            $query .= ", response_data = :response_data";
        }
        
        $query .= ", updated_at = NOW() WHERE order_id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_id', $order_id);
        
        if ($transaction_id) {
            $stmt->bindParam(':transaction_id', $transaction_id);
        }
        
        if ($response_data) {
            $response_json = json_encode($response_data);
            $stmt->bindParam(':response_data', $response_json);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Update order payment status
     */
    public function updateOrderPaymentStatus($order_id, $payment_status, $order_status = null) {
        $query = "UPDATE orders SET payment_status = :payment_status";
        
        if ($order_status) {
            $query .= ", order_status = :order_status";
        }
        
        $query .= ", updated_at = NOW() WHERE id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':payment_status', $payment_status);
        $stmt->bindParam(':order_id', $order_id);
        
        if ($order_status) {
            $stmt->bindParam(':order_status', $order_status);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Get developer commission report
     */
    public function getDeveloperCommissionReport($start_date = null, $end_date = null) {
        $query = "SELECT 
                    payment_method,
                    COUNT(*) as total_transactions,
                    SUM(order_amount) as total_order_value,
                    SUM(platform_commission) as total_platform_commission,
                    SUM(developer_commission) as total_developer_commission,
                    SUM(restaurant_amount) as total_restaurant_amount
                  FROM commission_records
                  WHERE status = 'settled'";
        
        if ($start_date && $end_date) {
            $query .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
        }
        
        $query .= " GROUP BY payment_method";
        
        $stmt = $this->conn->prepare($query);
        
        if ($start_date && $end_date) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Generate unique payment reference
     */
    private function generateReference($prefix = 'PAY') {
        return $prefix . '-' . time() . '-' . rand(1000, 9999);
    }
}

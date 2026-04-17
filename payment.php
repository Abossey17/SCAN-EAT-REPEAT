<?php
// includes/payment.php

class Payment {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Initialize Paystack payment (for VISA cards)
     */
    public function initializePaystackPayment($order_id, $amount, $email, $callback_url) {
        $url = "https://api.paystack.co/transaction/initialize";
        
        $fields = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to pesewas
            'currency' => CURRENCY,
            'reference' => $this->generateReference('PS'),
            'callback_url' => $callback_url,
            'metadata' => [
                'order_id' => $order_id,
                'custom_fields' => [
                    [
                        'display_name' => 'Order ID',
                        'variable_name' => 'order_id',
                        'value' => $order_id
                    ]
                ]
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
     * Verify Paystack payment
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
     * Initialize Mobile Money payment
     * This is a generic implementation - adjust based on your MoMo provider
     */
    public function initializeMoMoPayment($order_id, $amount, $phone_number, $network) {
        // This is a placeholder - implement based on your Mobile Money provider
        // Common providers in Ghana: MTN Mobile Money, Vodafone Cash, AirtelTigo Money
        
        $reference = $this->generateReference('MM');
        
        // Example API call structure (adjust to your provider)
        $url = "https://your-momo-api-endpoint.com/debit";
        
        $fields = [
            'amount' => $amount,
            'currency' => CURRENCY,
            'phone_number' => $phone_number,
            'network' => $network,
            'reference' => $reference,
            'description' => 'Order #' . $order_id,
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
            'message' => 'Please approve the payment prompt on your phone'
        ];
    }
    
    /**
     * Check Mobile Money payment status
     */
    public function checkMoMoStatus($reference) {
        // Implement based on your provider's API
        $url = "https://your-momo-api-endpoint.com/status/" . $reference;
        
        // Make API call and return status
        return [
            'status' => 'success',
            'message' => 'Payment completed successfully'
        ];
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
        
        $query .= " WHERE order_id = :order_id";
        
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
        
        $query .= " WHERE id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':payment_status', $payment_status);
        $stmt->bindParam(':order_id', $order_id);
        
        if ($order_status) {
            $stmt->bindParam(':order_status', $order_status);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Generate unique payment reference
     */
    private function generateReference($prefix = 'PAY') {
        return $prefix . '-' . time() . '-' . rand(1000, 9999);
    }
}

<?php
// includes/qr_generator.php

class QRGenerator {
    
    public static function generateQRCode($restaurant_id, $restaurant_name) {
        require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
        
        // Generate unique QR code filename
        $filename = 'qr_' . $restaurant_id . '_' . time() . '.png';
        $filepath = QR_DIR . $filename;
        
        // URL that the QR code will point to
        $url = SITE_URL . '/menu.php?r=' . $restaurant_id;
        
        // Generate QR code
        QRcode::png($url, $filepath, QR_ECLEVEL_L, 10, 2);
        
        return $filename;
    }
    
    public static function regenerateQRCode($restaurant_id, $restaurant_name) {
        // Delete old QR code if exists
        $query = "SELECT qr_code FROM restaurants WHERE id = :id";
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $restaurant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            if ($row['qr_code'] && file_exists(QR_DIR . $row['qr_code'])) {
                unlink(QR_DIR . $row['qr_code']);
            }
        }
        
        // Generate new QR code
        return self::generateQRCode($restaurant_id, $restaurant_name);
    }
}
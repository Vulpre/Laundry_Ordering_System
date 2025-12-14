<?php

function sendEmailNotification($to, $subject, $body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Laundry System <noreply@laundry.com>" . "\r\n";
    
    return mail($to, $subject, $body, $headers);
}

function sendNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare notification: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('issss', $user_id, $type, $title, $message, $link);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

function notifyNewOrder($conn, $order_id, $customer_name, $total_cost) {
    $admins = $conn->query("SELECT id, email, name FROM users WHERE role = 'admin'");
    
    while ($admin = $admins->fetch_assoc()) {
        sendNotification(
            $conn,
            $admin['id'],
            'order',
            'New Order Received',
            "Order #$order_id from $customer_name - Total: ₱" . number_format($total_cost, 2),
            'manage_orders.php'
        );
        
        sendEmailNotification(
            $admin['email'],
            "New Order #$order_id",
            "<p>Hello {$admin['name']},</p>
             <p>New order received from <strong>$customer_name</strong></p>
             <p>Order #: $order_id</p>
             <p>Total: ₱" . number_format($total_cost, 2) . "</p>"
        );
    }
    
    return true;
}

function testEmailSending($email, $name) {
    $subject = "Test Email from Laundry System";
    $body = "
        <h2>Test Email</h2>
        <p>Hello $name,</p>
        <p>This is a test email from your Laundry Management System.</p>
        <p>If you received this, your email configuration is working!</p>
        <p><strong>Sent at:</strong> " . date('F d, Y h:i A') . "</p>
    ";
    
    return sendEmailNotification($email, $subject, $body);
}

function notifyCustomerStatusChange($conn, $order_id, $user_id, $new_status) {
    return sendNotification(
        $conn,
        $user_id,
        'order',
        "Order #$order_id Status Updated",
        "Your order status has been changed to: $new_status",
        '../user/order_status.php'
    );
}

function notifyCustomerPaymentReceived($conn, $order_id, $user_id, $amount) {
    return sendNotification(
        $conn,
        $user_id,
        'payment',
        "Payment Received - Order #$order_id",
        "We have received your payment of ₱" . number_format($amount, 2),
        '../user/order_status.php'
    );
}

function buildEmailTemplate($data) {
    $greeting = $data['greeting'] ?? 'Customer';
    $subject = $data['subject'] ?? 'Notification';
    $body = $data['body'] ?? '';
    $cta_text = $data['cta_text'] ?? '';
    $cta_link = $data['cta_link'] ?? '';
    
    return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2563eb;'>$subject</h2>
            <p>Hello <strong>$greeting</strong>,</p>
            $body
            " . ($cta_link ? "<p style='text-align: center; margin: 30px 0;'>
                <a href='$cta_link' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;'>$cta_text</a>
            </p>" : "") . "
            <hr style='border: 1px solid #e5e7eb; margin: 30px 0;'>
            <p style='color: #6b7280; font-size: 13px;'>© " . date('Y') . " Laundry Management System. All rights reserved.</p>
        </div>
    ";
}

?>
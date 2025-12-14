<?php
session_start();

require '../db_connect.php';
require '../includes/constants.php';
require '../includes/security.php';
require '../includes/notification_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ../index.php');
    exit;
}

$err = '';
$success = '';

if (isset($_POST['ajax_send_email'])) {
    header('Content-Type: application/json');

    $order_id = intval($_POST['order_id']);

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            o.*,
            COALESCE(o.customer_email, u.email) AS email,
            COALESCE(o.customer_name, u.name) AS name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = $result->fetch_assoc();
    $stmt->close();

    $emailBody = "
        <p>Hello <strong>" . htmlspecialchars($order['name']) . "</strong>,</p>
        <p>Your laundry order is ready for pickup!</p>

        <div style='background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2563eb; margin: 20px 0;'>
            <h3 style='margin: 0; color: #1e40af;'>Order Details:</h3>
            <ul style='margin: 10px 0 0 0;'>
                <li><strong>Order #:</strong> {$order_id}</li>
                <li><strong>Services:</strong> " . htmlspecialchars($order['options']) . "</li>
                <li><strong>Total Amount:</strong> ₱" . number_format($order['total_cost'], 2) . "</li>
                <li><strong>Status:</strong> " . htmlspecialchars($order['status']) . "</li>
            </ul>
        </div>

        <p>Please collect your order at your earliest convenience.</p>
    ";

    $emailSent = sendEmailNotification(
        $order['email'],
        "Your Order #{$order_id} is Ready for Pickup",
        buildEmailTemplate([
            'greeting'  => $order['name'],
            'subject'   => 'Order Ready for Pickup',
            'body'      => $emailBody,
            'cta_text'  => 'View Order',
            'cta_link'  => ''
        ])
    );

    if ($emailSent) {
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }

    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        $delStmt = $conn->prepare("DELETE FROM orders WHERE id = ?");

        if (!$delStmt) {
            $err = "Database error: " . $conn->error;
        } else {
            $delStmt->bind_param('i', $id);

            if ($delStmt->execute()) {
                $success = "Order #{$id} deleted successfully.";
            } else {
                $err = "Failed to delete order: " . $delStmt->error;
            }

            $delStmt->close();
        }
    } else {
        $err = "Invalid order ID.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verifyCsrfToken();

    $id     = intval($_POST['order_id']);
    $status = validateOrderStatus($_POST['status'] ?? '');

    if (!$status) {
        $err = "Invalid status selected.";
    } elseif ($id <= 0) {
        $err = "Invalid order ID.";
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");

        if (!$stmt) {
            $err = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param('si', $status, $id);

            if ($stmt->execute()) {
                $success = "Order #{$id} status updated to {$status}.";

                $orderStmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
                $orderStmt->bind_param('i', $id);
                $orderStmt->execute();
                $orderResult = $orderStmt->get_result();

                if ($orderResult->num_rows > 0) {
                    $orderData = $orderResult->fetch_assoc();
                    if ($orderData['user_id']) {
                        notifyCustomerStatusChange(
                            $conn,
                            $id,
                            $orderData['user_id'],
                            $status
                        );
                    }
                }

                $orderStmt->close();
            } else {
                $err = "Failed to update status: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    verifyCsrfToken();

    $id             = intval($_POST['order_id']);
    $payment_status = validatePaymentStatus($_POST['payment_status'] ?? '');

    if (!$payment_status) {
        $err = "Invalid payment status selected.";
    } elseif ($id <= 0) {
        $err = "Invalid order ID.";
    } else {
        $stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");

        if (!$stmt) {
            $err = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param('si', $payment_status, $id);

            if ($stmt->execute()) {
                $success = "Order #{$id} payment updated to {$payment_status}.";

                if ($payment_status === PAYMENT_STATUS_PAID) {
                    $orderStmt = $conn->prepare("
                        SELECT user_id, total_cost 
                        FROM orders 
                        WHERE id = ?
                    ");

                    $orderStmt->bind_param('i', $id);
                    $orderStmt->execute();
                    $orderResult = $orderStmt->get_result();

                    if ($orderResult->num_rows > 0) {
                        $orderData = $orderResult->fetch_assoc();
                        if ($orderData['user_id']) {
                            notifyCustomerPaymentReceived(
                                $conn,
                                $id,
                                $orderData['user_id'],
                                $orderData['total_cost']
                            );
                        }
                    }

                    $orderStmt->close();
                }
            } else {
                $err = "Failed to update payment status: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$searchTerm    = $_GET['search'] ?? '';
$statusFilter  = $_GET['status_filter'] ?? '';
$paymentFilter = $_GET['payment_filter'] ?? '';

$whereClause = "1=1";

if (!empty($searchTerm)) {
    $safeSearch = $conn->real_escape_string($searchTerm);
    $whereClause .= "
        AND (
            COALESCE(o.customer_name, u.name) LIKE '%$safeSearch%' 
            OR o.id LIKE '%$safeSearch%'
        )
    ";
}

if (!empty($statusFilter)) {
    $safeStatus = $conn->real_escape_string($statusFilter);
    if (in_array($safeStatus, VALID_ORDER_STATUSES)) {
        $whereClause .= " AND o.status = '$safeStatus'";
    }
}

if (!empty($paymentFilter)) {
    $safePayment = $conn->real_escape_string($paymentFilter);
    if (in_array($safePayment, VALID_PAYMENT_STATUSES)) {
        $whereClause .= " AND o.payment_status = '$safePayment'";
    }
}

$orders = $conn->query("
    SELECT 
        o.*,
        COALESCE(o.customer_name, u.name) AS customer,
        COALESCE(o.customer_email, u.email) AS email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $whereClause
    ORDER BY o.created_at DESC
");

if (!$orders) {
    $err = "Database error: " . $conn->error;
}
?>

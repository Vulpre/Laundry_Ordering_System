<?php
session_start();

require '../db_connect.php';
require '../includes/constants.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ../index.php');
    exit;
}

$today      = date('Y-m-d');
$thisMonth  = date('Y-m');
$lastMonth  = date('Y-m', strtotime('-1 month'));

$todayOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE DATE(created_at) = '$today'
")->fetch_assoc()['count'];

$todayRevenue = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE DATE(created_at) = '$today' 
      AND payment_status = 'Paid'
")->fetch_assoc()['total'];

$monthOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = '$thisMonth'
")->fetch_assoc()['count'];

$monthRevenue = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = '$thisMonth' 
      AND payment_status = 'Paid'
")->fetch_assoc()['total'];

$lastMonthRevenue = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth' 
      AND payment_status = 'Paid'
")->fetch_assoc()['total'];

$revenueGrowth = $lastMonthRevenue > 0
    ? (($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100
    : 0;

$totalOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders
")->fetch_assoc()['count'];

$activeOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status NOT IN ('Pickup')
")->fetch_assoc()['count'];

$totalRevenue = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE payment_status = 'Paid'
")->fetch_assoc()['total'];

$pendingPayments = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE payment_status != 'Paid'
")->fetch_assoc()['total'];

$avgOrderValue = $totalOrders > 0
    ? $totalRevenue / $totalOrders
    : 0;

$pendingOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'Pending'
")->fetch_assoc()['count'];

$inProgressOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'In Progress'
")->fetch_assoc()['count'];

$readyOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'Ready'
")->fetch_assoc()['count'];

$completedOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'Pickup'
")->fetch_assoc()['count'];

$totalCustomers = $conn->query("
    SELECT COUNT(*) AS count 
    FROM users 
    WHERE role = 'user'
")->fetch_assoc()['count'];

$newCustomersMonth = $conn->query("
    SELECT COUNT(*) AS count 
    FROM users 
    WHERE role = 'user' 
      AND DATE_FORMAT(created_at, '%Y-%m') = '$thisMonth'
")->fetch_assoc()['count'];

$recentOrders = $conn->query("
    SELECT 
        o.id,
        COALESCE(o.customer_name, u.name) AS customer,
        o.total_cost,
        o.status,
        o.payment_status,
        o.created_at
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/admin-theme.css">

    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
        }

        .kpi-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .kpi-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .kpi-change {
            font-size: 13px;
            font-weight: 600;
        }

        .kpi-change.positive {
            color: #10b981;
        }

        .kpi-change.negative {
            color: #ef4444;
        }

        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }
    </style>
</head>

<body>
<?php include '../includes/header.php'; ?>

<div class="card">
    <h2>📊 Admin Dashboard</h2>

    <!-- KPI GRID -->
    <div class="kpi-grid">
        <!-- Cards unchanged, formatting only -->
        ...
    </div>
</div>

<script>
    setTimeout(() => location.reload(), 300000);
</script>
</body>
</html>

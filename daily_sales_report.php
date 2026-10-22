
<?php
session_start();
include 'db_connect.php';

// Check login
if (!isset($_SESSION['admin']) && !isset($_SESSION['pharmacist'])) {
    header("Location: login.php");
    exit();
}

// Identify the logged-in user
$isAdmin = isset($_SESSION['admin']);
$isPharmacist = isset($_SESSION['pharmacist']);

// Default date = today
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch daily sales
$sql = "SELECT
            s.SaleID,
            s.SaleDate,
            c.CustomerName,
            p.Name AS PharmacistName,
            m.MedicineName,
            si.Quantity,
            si.SellingPrice,
            si.SubTotal,
            s.PaymentMethod
        FROM sale s
        INNER JOIN customer c
            ON s.CustomerID = c.CustomerID
        INNER JOIN pharmacist p
            ON s.PharmacistID = p.PharmacistID
        INNER JOIN sale_items si
            ON s.SaleID = si.SaleID
        INNER JOIN medicine m
            ON si.MedicineID = m.MedicineID
        WHERE s.SaleDate = ?
        ORDER BY s.SaleID DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $selectedDate);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

// Calculate summary
$totalSales = 0;
$totalQuantity = 0;
$totalRevenue = 0;

// Store rows so we can display them
$sales = [];

while ($row = mysqli_fetch_assoc($result)) {
    $sales[] = $row;

    $totalQuantity += $row['Quantity'];
    $totalRevenue += $row['SubTotal'];
}

// Count unique SaleIDs
$uniqueSales = [];

foreach ($sales as $row) {
    $uniqueSales[$row['SaleID']] = true;
}

$totalSales = count($uniqueSales);

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daily Sales Report</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        /* Back to Dashboard button */
        .dashboard-bar {
            margin-bottom: 20px;
        }

        .back-dashboard {
            display: inline-block;
            padding: 10px 16px;
            background-color: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .back-dashboard:hover {
            background-color: #333;
        }

        .date-form {
            text-align: center;
            margin-bottom: 25px;
        }

        input[type="date"] {
            padding: 10px;
            font-size: 15px;
        }

        button {
            padding: 10px 18px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        .summary {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-box {
            flex: 1;
            background-color: #f1f5f9;
            padding: 18px;
            text-align: center;
            border-radius: 8px;
        }

        .summary-box h3 {
            margin: 5px 0;
        }

        .summary-box p {
            font-size: 20px;
            font-weight: bold;
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }

        th {
            background-color: #343a40;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .no-data {
            text-align: center;
            padding: 25px;
            font-size: 16px;
        }

        .print-btn {
            margin-top: 20px;
            background-color: #28a745;
        }

        .print-btn:hover {
            background-color: #218838;
        }

        @media screen and (max-width: 700px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 15px;
                overflow-x: auto;
            }

            .summary {
                flex-direction: column;
                gap: 10px;
            }

            table {
                min-width: 850px;
            }
        }

        @media print {
            .date-form,
            .print-btn,
            .dashboard-bar {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                max-width: none;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Daily Sales Report</h2>

    <!-- Back to Dashboard Button -->
    <div class="dashboard-bar">

        <?php if ($isAdmin): ?>

            <a
                href="admin_dashboard.php"
                class="back-dashboard"
            >
                &larr; Back to Admin Dashboard
            </a>

        <?php elseif ($isPharmacist): ?>

            <a
                href="pharmacist_dashboard.php"
                class="back-dashboard"
            >
                &larr; Back to Pharmacist Dashboard
            </a>

        <?php endif; ?>

    </div>

    <!-- Date Selection -->
    <div class="date-form">

        <form method="GET">

            <label for="date">
                <strong>Select Date:</strong>
            </label>

            <input
                type="date"
                id="date"
                name="date"
                value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>"
                required
            >

            <button type="submit">
                Generate Report
            </button>

        </form>

    </div>

    <!-- Summary -->
    <div class="summary">

        <div class="summary-box">
            <h3>Total Sales</h3>
            <p><?php echo $totalSales; ?></p>
        </div>

        <div class="summary-box">
            <h3>Total Quantity Sold</h3>
            <p><?php echo $totalQuantity; ?></p>
        </div>

        <div class="summary-box">
            <h3>Total Revenue</h3>
            <p>₹<?php echo number_format($totalRevenue, 2); ?></p>
        </div>

    </div>

    <!-- Sales Table -->
    <?php if ($totalSales > 0): ?>

        <table>

            <thead>
                <tr>
                    <th>Sale ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Pharmacist</th>
                    <th>Medicine</th>
                    <th>Quantity</th>
                    <th>Selling Price</th>
                    <th>Subtotal</th>
                    <th>Payment Method</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($sales as $row): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars((string)$row['SaleID'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars((string)$row['SaleDate'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars((string)$row['CustomerName'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars((string)$row['PharmacistName'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars((string)$row['MedicineName'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars((string)$row['Quantity'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                    <td>
                        ₹<?php echo number_format((float)$row['SellingPrice'], 2); ?>
                    </td>

                    <td>
                        ₹<?php echo number_format((float)$row['SubTotal'], 2); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars((string)$row['PaymentMethod'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php else: ?>

        <div class="no-data">
            No sales found for
            <?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>.
        </div>

    <?php endif; ?>

    <!-- Print Button -->
    <div style="text-align:center;">

        <button
            class="print-btn"
            onclick="window.print()"
        >
            Print Report
        </button>

    </div>

</div>

</body>
</html>


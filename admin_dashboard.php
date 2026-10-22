<?php

session_start();

include("db_connect.php");


/* ==========================================
   CHECK WHETHER ADMIN IS LOGGED IN
   ========================================== */

if (!isset($_SESSION['admin']) || empty($_SESSION['admin'])) {

    header("Location: login.php");
    exit();

}


/* ==========================================
   SAFE OUTPUT FUNCTION
   ========================================== */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ==========================================
   INVENTORY ALERT COUNTS
   ========================================== */


/* ------------------------------------------
   LOW STOCK
   ------------------------------------------ */

$lowStockSQL = "
    SELECT COUNT(*) AS total
    FROM medicine
    WHERE StockQty <= 10
";

$lowStockResult = mysqli_query(
    $conn,
    $lowStockSQL
);

$lowStockCount = 0;

if ($lowStockResult) {

    $lowStockRow = mysqli_fetch_assoc(
        $lowStockResult
    );

    $lowStockCount = (int)$lowStockRow['total'];

}


/* ------------------------------------------
   EXPIRED MEDICINES
   ------------------------------------------ */

$expiredSQL = "
    SELECT COUNT(*) AS total
    FROM medicine
    WHERE ExpiryDate < CURDATE()
";

$expiredResult = mysqli_query(
    $conn,
    $expiredSQL
);

$expiredCount = 0;

if ($expiredResult) {

    $expiredRow = mysqli_fetch_assoc(
        $expiredResult
    );

    $expiredCount = (int)$expiredRow['total'];

}


/* ------------------------------------------
   EXPIRING WITHIN 30 DAYS
   ------------------------------------------ */

$expiringSoonSQL = "
    SELECT COUNT(*) AS total
    FROM medicine
    WHERE ExpiryDate >= CURDATE()
    AND ExpiryDate <= DATE_ADD(
        CURDATE(),
        INTERVAL 30 DAY
    )
";

$expiringSoonResult = mysqli_query(
    $conn,
    $expiringSoonSQL
);

$expiringSoonCount = 0;

if ($expiringSoonResult) {

    $expiringSoonRow = mysqli_fetch_assoc(
        $expiringSoonResult
    );

    $expiringSoonCount =
        (int)$expiringSoonRow['total'];

}


/* ==========================================
   PENDING MEDICINE REQUEST COUNT
   ========================================== */

$pendingRequestSQL = "
    SELECT COUNT(*) AS total
    FROM medicine_request
    WHERE Status = 'Pending'
";

$pendingRequestResult = mysqli_query(
    $conn,
    $pendingRequestSQL
);

$pendingRequestCount = 0;

if ($pendingRequestResult) {

    $pendingRequestRow =
        mysqli_fetch_assoc(
            $pendingRequestResult
        );

    $pendingRequestCount =
        (int)$pendingRequestRow['total'];

}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Smart Pharmacy - Admin Dashboard
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }


        /* =====================================
           HEADER
           ===================================== */

        .header {
            background-color: #333;
            color: white;
            padding: 20px;
            text-align: center;
        }


        .header h1 {
            margin: 0;
            font-size: 30px;
        }


        .header p {
            margin: 8px 0 0;
            font-size: 16px;
        }


        /* =====================================
           MAIN CONTAINER
           ===================================== */

        .container {
            width: 90%;
            margin: 30px auto;
        }


        /* =====================================
           WELCOME SECTION
           ===================================== */

        .welcome {
            background-color: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 0 8px #ccc;
        }


        .welcome h2 {
            margin-top: 0;
        }


        /* =====================================
           DASHBOARD MENU
           ===================================== */

        .menu {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }


        .card {
            background-color: white;
            padding: 25px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 0 8px #ccc;
        }


        .card h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }


        /* =====================================
           BUTTONS
           ===================================== */

        .button {
            display: inline-block;
            padding: 10px 18px;
            margin: 4px;
            background-color: green;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }


        .button:hover {
            opacity: 0.85;
        }


        .logout {
            background-color: #c0392b;
        }


        /* =====================================
           INVENTORY ALERT CARD
           ===================================== */

        .alert-card {
            border-top: 5px solid orange;
        }


        .alert-counts {
            margin-bottom: 15px;
        }


        .alert-item {
            margin: 8px 0;
            padding: 8px;
            border-radius: 5px;
            font-weight: bold;
        }


        .low-stock {
            background-color: #fff3cd;
        }


        .expired {
            background-color: #f8d7da;
        }


        .expiring-soon {
            background-color: #ffe5b4;
        }


        /* =====================================
           MEDICINE REQUEST CARD
           ===================================== */

        .request-card {
            border-top: 5px solid #3498db;
        }


        .pending-count {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }


        /* =====================================
           RESPONSIVE DESIGN
           ===================================== */

        @media screen and (max-width: 900px) {

            .menu {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media screen and (max-width: 600px) {

            .menu {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<!-- =====================================
     HEADER
     ===================================== -->

<div class="header">

    <h1>
        Smart Pharmacy
    </h1>

    <p>
        Admin Dashboard
    </p>

</div>



<div class="container">


    <!-- =====================================
         WELCOME SECTION
         ===================================== -->

    <div class="welcome">

        <h2>

            Welcome,

            <?php

            echo e($_SESSION['admin']);

            ?>

        </h2>


        <p>

            Manage medicines, customers,
            suppliers, pharmacists, purchases,
            sales and stock.

        </p>

    </div>



    <!-- =====================================
         DASHBOARD MENU
         ===================================== -->

    <div class="menu">


        <!-- =====================================
             1. MEDICINE MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Medicine Management
            </h3>


            <a
                href="add_medicine.php"
                class="button"
            >
                Add Medicine
            </a>


            <a
                href="view_medicine.php"
                class="button"
            >
                Manage Medicines
            </a>

        </div>



        <!-- =====================================
             2. MEDICINE REQUEST MANAGEMENT
             ===================================== -->

        <div class="card request-card">

            <h3>
                Medicine Requests
            </h3>


            <div class="pending-count">

                Pending Requests:

                <?php

                echo e($pendingRequestCount);

                ?>

            </div>


            <a
                href="manage_medicine_requests.php"
                class="button"
            >
                Manage Requests
            </a>

        </div>



        <!-- =====================================
             3. CUSTOMER MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Customer Management
            </h3>


            <a
                href="view_customer.php"
                class="button"
            >
                Manage Customers
            </a>

        </div>



        <!-- =====================================
             4. SUPPLIER MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Supplier Management
            </h3>


            <a
                href="view_supplier.php"
                class="button"
            >
                Manage Suppliers
            </a>

        </div>



        <!-- =====================================
             5. PHARMACIST MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Pharmacist Management
            </h3>


            <a
                href="view_pharmacist.php"
                class="button"
            >
                Manage Pharmacists
            </a>

        </div>



        <!-- =====================================
             6. PURCHASE MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Purchase Management
            </h3>


            <!-- ADD PURCHASE -->

            <a
                href="add_purchase.php"
                class="button"
            >
                Add Purchase
            </a>


            <!-- ADD PURCHASE ITEM -->

            <a
                href="add_purchase_item.php"
                class="button"
            >
                Add Purchase Item
            </a>


            <!-- VIEW PURCHASES -->

            <a
                href="view_purchase.php"
                class="button"
            >
                View Purchases
            </a>

        </div>



        <!-- =====================================
             7. SALES MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Sales Management
            </h3>


            <a
                href="view_sale.php"
                class="button"
            >
                View Sales
            </a>


            <a
                href="daily_sales_report.php"
                class="button"
            >
                Daily Sales Report
            </a>

        </div>



        <!-- =====================================
             8. STOCK MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Stock Management
            </h3>


            <a
                href="stock_management.php"
                class="button"
            >
                View Stock
            </a>

        </div>



        <!-- =====================================
             9. INVENTORY ALERTS
             ===================================== -->

        <div class="card alert-card">

            <h3>
                ⚠ Inventory Alerts
            </h3>


            <div class="alert-counts">


                <div class="alert-item low-stock">

                    ⚠ Low Stock:

                    <?php

                    echo e($lowStockCount);

                    ?>

                </div>



                <div class="alert-item expired">

                    🔴 Expired:

                    <?php

                    echo e($expiredCount);

                    ?>

                </div>



                <div class="alert-item expiring-soon">

                    🟠 Expiring Soon:

                    <?php

                    echo e($expiringSoonCount);

                    ?>

                </div>


            </div>



            <a
                href="inventory_alerts.php"
                class="button"
            >
                View Alerts
            </a>


        </div>



        <!-- =====================================
             10. ACCOUNT
             ===================================== -->

        <div class="card">

            <h3>
                Account
            </h3>


            <a
                href="logout.php"
                class="button logout"
            >
                Logout
            </a>

        </div>


    </div>


</div>


</body>

</html>
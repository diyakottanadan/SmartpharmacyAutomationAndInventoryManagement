<?php

session_start();

include("db_connect.php");


/* =========================================
   CHECK WHETHER PHARMACIST IS LOGGED IN
   ========================================= */

if (!isset($_SESSION['pharmacist'])) {

    header("Location: login.php");
    exit();

}


/* =========================================
   SAFE OUTPUT FUNCTION
   ========================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   GET AND VALIDATE PHARMACIST ID
   ========================================= */

$pharmacistID = filter_var(
    $_SESSION['PharmacistID'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);


if ($pharmacistID === false) {

    session_destroy();

    header("Location: login.php");
    exit();

}


/* =========================================
   GET PHARMACIST NAME
   ========================================= */

$pharmacistName =
    $_SESSION['PharmacistName'] ?? 'Pharmacist';


/* =========================================
   DASHBOARD STATISTICS
   ========================================= */


/* -----------------------------------------
   TOTAL MEDICINES
   ----------------------------------------- */

$medicineCountSQL = "

    SELECT COUNT(*) AS TotalMedicines

    FROM medicine

";


$medicineCountResult =
    mysqli_query(
        $conn,
        $medicineCountSQL
    );


if (!$medicineCountResult) {

    die("Unable to load dashboard statistics.");

}


$medicineCount =
    mysqli_fetch_assoc(
        $medicineCountResult
    );


/* -----------------------------------------
   TOTAL CUSTOMERS
   ----------------------------------------- */

$customerCountSQL = "

    SELECT COUNT(*) AS TotalCustomers

    FROM customer

";


$customerCountResult =
    mysqli_query(
        $conn,
        $customerCountSQL
    );


if (!$customerCountResult) {

    die("Unable to load dashboard statistics.");

}


$customerCount =
    mysqli_fetch_assoc(
        $customerCountResult
    );


/* -----------------------------------------
   TOTAL SALES
   ----------------------------------------- */

$saleCountSQL = "

    SELECT COUNT(*) AS TotalSales

    FROM sale

";


$saleCountResult =
    mysqli_query(
        $conn,
        $saleCountSQL
    );


if (!$saleCountResult) {

    die("Unable to load dashboard statistics.");

}


$saleCount =
    mysqli_fetch_assoc(
        $saleCountResult
    );


/* -----------------------------------------
   LOW STOCK MEDICINES
   ----------------------------------------- */

$lowStockSQL = "

    SELECT COUNT(*) AS LowStock

    FROM medicine

    WHERE StockQty <= 10

";


$lowStockResult =
    mysqli_query(
        $conn,
        $lowStockSQL
    );


if (!$lowStockResult) {

    die("Unable to load inventory statistics.");

}


$lowStock =
    mysqli_fetch_assoc(
        $lowStockResult
    );


/* -----------------------------------------
   EXPIRED MEDICINES
   ----------------------------------------- */

$expiredSQL = "

    SELECT COUNT(*) AS Expired

    FROM medicine

    WHERE ExpiryDate < CURDATE()

";


$expiredResult =
    mysqli_query(
        $conn,
        $expiredSQL
    );


if (!$expiredResult) {

    die("Unable to load inventory statistics.");

}


$expired =
    mysqli_fetch_assoc(
        $expiredResult
    );


/* -----------------------------------------
   MEDICINES EXPIRING SOON
   Within next 30 days
   ----------------------------------------- */

$expiringSoonSQL = "

    SELECT COUNT(*) AS ExpiringSoon

    FROM medicine

    WHERE ExpiryDate >= CURDATE()

    AND ExpiryDate <= DATE_ADD(
        CURDATE(),
        INTERVAL 30 DAY
    )

";


$expiringSoonResult =
    mysqli_query(
        $conn,
        $expiringSoonSQL
    );


if (!$expiringSoonResult) {

    die("Unable to load inventory statistics.");

}


$expiringSoon =
    mysqli_fetch_assoc(
        $expiringSoonResult
    );


/* =========================================
   MEDICINE REQUEST STATISTICS
   ========================================= */


/* -----------------------------------------
   PENDING REQUESTS
   ----------------------------------------- */

$pendingRequestSQL = "

    SELECT COUNT(*) AS PendingRequests

    FROM medicine_request

    WHERE PharmacistID = ?

    AND Status = 'Pending'

";


$pendingRequestStmt =
    mysqli_prepare(
        $conn,
        $pendingRequestSQL
    );


if (!$pendingRequestStmt) {

    die("Unable to load request statistics.");

}


mysqli_stmt_bind_param(
    $pendingRequestStmt,
    "i",
    $pharmacistID
);


if (
    !mysqli_stmt_execute(
        $pendingRequestStmt
    )
) {

    mysqli_stmt_close(
        $pendingRequestStmt
    );

    die("Unable to load request statistics.");

}


$pendingRequestResult =
    mysqli_stmt_get_result(
        $pendingRequestStmt
    );


$pendingRequest =
    mysqli_fetch_assoc(
        $pendingRequestResult
    );


mysqli_stmt_close(
    $pendingRequestStmt
);


/* -----------------------------------------
   APPROVED REQUESTS
   ----------------------------------------- */

$approvedRequestSQL = "

    SELECT COUNT(*) AS ApprovedRequests

    FROM medicine_request

    WHERE PharmacistID = ?

    AND Status = 'Approved'

";


$approvedRequestStmt =
    mysqli_prepare(
        $conn,
        $approvedRequestSQL
    );


if (!$approvedRequestStmt) {

    die("Unable to load request statistics.");

}


mysqli_stmt_bind_param(
    $approvedRequestStmt,
    "i",
    $pharmacistID
);


if (
    !mysqli_stmt_execute(
        $approvedRequestStmt
    )
) {

    mysqli_stmt_close(
        $approvedRequestStmt
    );

    die("Unable to load request statistics.");

}


$approvedRequestResult =
    mysqli_stmt_get_result(
        $approvedRequestStmt
    );


$approvedRequest =
    mysqli_fetch_assoc(
        $approvedRequestResult
    );


mysqli_stmt_close(
    $approvedRequestStmt
);


/* -----------------------------------------
   REJECTED REQUESTS
   ----------------------------------------- */

$rejectedRequestSQL = "

    SELECT COUNT(*) AS RejectedRequests

    FROM medicine_request

    WHERE PharmacistID = ?

    AND Status = 'Rejected'

";


$rejectedRequestStmt =
    mysqli_prepare(
        $conn,
        $rejectedRequestSQL
    );


if (!$rejectedRequestStmt) {

    die("Unable to load request statistics.");

}


mysqli_stmt_bind_param(
    $rejectedRequestStmt,
    "i",
    $pharmacistID
);


if (
    !mysqli_stmt_execute(
        $rejectedRequestStmt
    )
) {

    mysqli_stmt_close(
        $rejectedRequestStmt
    );

    die("Unable to load request statistics.");

}


$rejectedRequestResult =
    mysqli_stmt_get_result(
        $rejectedRequestStmt
    );


$rejectedRequest =
    mysqli_fetch_assoc(
        $rejectedRequestResult
    );


mysqli_stmt_close(
    $rejectedRequestStmt
);

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
        Smart Pharmacy - Pharmacist Dashboard
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


        /* =========================================
           HEADER
           ========================================= */

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


        /* =========================================
           MAIN CONTAINER
           ========================================= */

        .container {

            width: 90%;
            margin: 30px auto;

        }


        /* =========================================
           WELCOME SECTION
           ========================================= */

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


        /* =========================================
           STATISTICS
           ========================================= */

        .stats {

            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;

        }


        .stat-card {

            background-color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 0 8px #ccc;

        }


        .stat-card h3 {

            margin: 0;
            font-size: 16px;

        }


        .stat-card p {

            font-size: 30px;
            font-weight: bold;
            margin: 15px 0 0 0;

        }


        /* =========================================
           MEDICINE REQUEST STATUS
           ========================================= */

        .request-stats {

            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;

        }


        .request-card {

            background-color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 0 8px #ccc;

        }


        .request-card h3 {

            margin: 0;

        }


        .request-card p {

            font-size: 30px;
            font-weight: bold;
            margin: 15px 0 0;

        }


        .pending-card {

            border-top: 5px solid orange;

        }


        .approved-card {

            border-top: 5px solid green;

        }


        .rejected-card {

            border-top: 5px solid red;

        }


        /* =========================================
           DASHBOARD MENU
           ========================================= */

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


        /* =========================================
           INVENTORY ALERTS
           ========================================= */

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


        /* =========================================
           BUTTONS
           ========================================= */

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


        .request-button {

            background-color: #e67e22;

        }


        .logout {

            background-color: #c0392b;

        }


        /* =========================================
           RESPONSIVE DESIGN
           ========================================= */

        @media screen and (max-width: 1000px) {

            .stats {

                grid-template-columns: repeat(2, 1fr);

            }

        }


        @media screen and (max-width: 900px) {

            .menu {

                grid-template-columns: repeat(2, 1fr);

            }


            .request-stats {

                grid-template-columns: 1fr;

            }

        }


        @media screen and (max-width: 600px) {

            .menu {

                grid-template-columns: 1fr;

            }


            .stats {

                grid-template-columns: 1fr;

            }


            .request-stats {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<!-- =========================================
     HEADER
     ========================================= -->

<div class="header">

    <h1>
        Smart Pharmacy
    </h1>

    <p>
        Pharmacist Dashboard
    </p>

</div>



<div class="container">


    <!-- =========================================
         WELCOME SECTION
         ========================================= -->

    <div class="welcome">

        <h2>

            Welcome,

            <?php
            echo e($pharmacistName);
            ?>

        </h2>


        <p>

            Manage daily pharmacy operations including
            customers, sales, purchases and stock.

        </p>

    </div>



    <!-- =========================================
         MAIN STATISTICS
         ========================================= -->

    <div class="stats">


        <div class="stat-card">

            <h3>
                Total Medicines
            </h3>

            <p>

                <?php
                echo e(
                    $medicineCount['TotalMedicines']
                );
                ?>

            </p>

        </div>



        <div class="stat-card">

            <h3>
                Total Customers
            </h3>

            <p>

                <?php
                echo e(
                    $customerCount['TotalCustomers']
                );
                ?>

            </p>

        </div>



        <div class="stat-card">

            <h3>
                Total Sales
            </h3>

            <p>

                <?php
                echo e(
                    $saleCount['TotalSales']
                );
                ?>

            </p>

        </div>



        <div class="stat-card">

            <h3>
                Low Stock Medicines
            </h3>

            <p>

                <?php
                echo e(
                    $lowStock['LowStock']
                );
                ?>

            </p>

        </div>


    </div>



    <!-- =========================================
         MEDICINE REQUEST STATUS
         ========================================= -->

    <h2>
        My Medicine Requests
    </h2>


    <div class="request-stats">


        <div class="request-card pending-card">

            <h3>
                Pending Requests
            </h3>

            <p>

                <?php
                echo e(
                    $pendingRequest['PendingRequests']
                );
                ?>

            </p>

        </div>



        <div class="request-card approved-card">

            <h3>
                Approved Requests
            </h3>

            <p>

                <?php
                echo e(
                    $approvedRequest['ApprovedRequests']
                );
                ?>

            </p>

        </div>



        <div class="request-card rejected-card">

            <h3>
                Rejected Requests
            </h3>

            <p>

                <?php
                echo e(
                    $rejectedRequest['RejectedRequests']
                );
                ?>

            </p>

        </div>


    </div>



    <!-- =========================================
         DASHBOARD MENU
         ========================================= -->

    <div class="menu">


        <!-- =====================================
             MEDICINE MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Medicine Management
            </h3>


            <a
                href="view_medicine.php"
                class="button"
            >
                View Medicines
            </a>


            <br>


            <a
                href="request_medicine.php"
                class="button request-button"
            >
                Request New Medicine
            </a>


            <br>


            <a
                href="my_medicine_requests.php"
                class="button request-button"
            >
                My Medicine Requests
            </a>


        </div>



        <!-- =====================================
             CUSTOMER MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Customer Management
            </h3>


            <a
                href="add_customer.php"
                class="button"
            >
                Add Customer
            </a>


            <br>


            <a
                href="view_customer.php"
                class="button"
            >
                View Customers
            </a>


        </div>



        <!-- =====================================
             SALES MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Sales Management
            </h3>


            <a
                href="add_sale.php"
                class="button"
            >
                Add Sale
            </a>


            <br>


            <a
                href="view_sale.php"
                class="button"
            >
                View Sales
            </a>


            <br>


            <a
                href="daily_sales_report.php"
                class="button"
            >
                Daily Sales Report
            </a>


        </div>



        <!-- =====================================
             PURCHASE MANAGEMENT
             ===================================== -->

        <div class="card">

            <h3>
                Purchase Management
            </h3>


            <a
                href="add_purchase.php"
                class="button"
            >
                Add Purchase
            </a>


            <br>


            <a
                href="add_purchase_item.php"
                class="button"
            >
                Add Purchase Item
            </a>


            <br>


            <a
                href="view_purchase.php"
                class="button"
            >
                View Purchases
            </a>


        </div>



        <!-- =====================================
             STOCK MANAGEMENT
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
             INVENTORY ALERTS
             ===================================== -->

        <div class="card alert-card">

            <h3>
                ⚠ Inventory Alerts
            </h3>


            <div class="alert-counts">


                <div class="alert-item low-stock">

                    ⚠ Low Stock:

                    <?php
                    echo e(
                        $lowStock['LowStock']
                    );
                    ?>

                </div>



                <div class="alert-item expired">

                    🔴 Expired:

                    <?php
                    echo e(
                        $expired['Expired']
                    );
                    ?>

                </div>



                <div class="alert-item expiring-soon">

                    🟠 Expiring Soon:

                    <?php
                    echo e(
                        $expiringSoon['ExpiringSoon']
                    );
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
             ACCOUNT
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
<?php

session_start();
include("db_connect.php");


/* =========================
   LOGIN CHECK
   ALLOW ADMIN OR PHARMACIST
   ========================= */

$isAdmin = isset($_SESSION['admin']);
$isPharmacist = isset($_SESSION['pharmacist']);

if (!$isAdmin && !$isPharmacist) {
    header("Location: login.php");
    exit();
}


/* =========================
   CSRF TOKEN
   ========================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


/* =========================
   FETCH ALL MEDICINES
   DO NOT HIDE UNAVAILABLE OR
   EXPIRED MEDICINES
   ========================= */

$sql = "
    SELECT
        m.MedicineID,
        m.MedicineName,
        m.Category,
        m.SupplierID,
        s.SupplierName,
        m.BatchNo,
        m.PurchasePrice,
        m.SellingPrice,
        m.StockQty,
        m.ExpiryDate,
        m.Description,
        m.Status
    FROM medicine m
    LEFT JOIN supplier s
        ON m.SupplierID = s.SupplierID
    ORDER BY m.MedicineID DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Unable to load medicine records.");
}


/* =========================
   SAFE OUTPUT
   ========================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================
   DETERMINE DISPLAY STATUS
   ========================= */

function getMedicineStatus($row)
{
    // Stock completely over
    if ((int)$row['StockQty'] <= 0) {
        return 'Unavailable';
    }

    // Expiry date has passed
    if (
        !empty($row['ExpiryDate']) &&
        $row['ExpiryDate'] < date('Y-m-d')
    ) {
        return 'Expired';
    }

    // Respect any other unavailable status
    if ($row['Status'] !== 'Available') {
        return 'Unavailable';
    }

    return 'Available';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>View Medicines</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .message {
            width: 95%;
            margin: 0 auto 15px auto;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .top-bar {
            width: 95%;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .left-buttons,
        .right-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .back {
            background: #555;
            color: white;
            padding: 9px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .add {
            background: green;
            color: white;
            padding: 9px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .purchase {
            background: #2980b9;
            color: white;
            padding: 9px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .purchase-item {
            background: #8e44ad;
            color: white;
            padding: 9px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .table-container {
            width: 95%;
            margin: auto;
            overflow-x: auto;
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 1200px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }

        th {
            background: green;
            color: white;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        tr:hover {
            background: #eeeeee;
        }

        .edit {
            color: blue;
            text-decoration: none;
            font-weight: bold;
        }

        .delete {
            color: red;
            text-decoration: none;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .delete-form {
            display: inline;
        }

        .status-available {
            color: green;
            font-weight: bold;
        }

        .status-unavailable {
            color: red;
            font-weight: bold;
        }

        .status-expired {
            color: #c05600;
            font-weight: bold;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            font-weight: bold;
            color: #555;
        }

        @media screen and (max-width: 800px) {

            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .left-buttons,
            .right-buttons {
                justify-content: center;
            }
        }

    </style>

</head>

<body>

<h2>Medicine List</h2>


<!-- =========================
     SUCCESS MESSAGE
     ========================= -->

<?php if (
    isset($_GET['deleted']) &&
    $_GET['deleted'] === '1'
): ?>

    <div class="message success">
        Medicine deleted successfully.
    </div>

<?php endif; ?>


<!-- =========================
     TOP BUTTONS
     ========================= -->

<div class="top-bar">

    <div class="left-buttons">

        <?php if ($isAdmin): ?>

            <a
                href="admin_dashboard.php"
                class="back"
            >
                Back to Admin Dashboard
            </a>

        <?php else: ?>

            <a
                href="pharmacist_dashboard.php"
                class="back"
            >
                Back to Pharmacist Dashboard
            </a>

        <?php endif; ?>

    </div>


    <div class="right-buttons">

        <a
            href="add_purchase.php"
            class="purchase"
        >
            + Add Purchase
        </a>

        <a
            href="add_purchase_item.php"
            class="purchase-item"
        >
            + Add Purchase Item
        </a>

        <?php if ($isAdmin): ?>

            <a
                href="add_medicine.php"
                class="add"
            >
                + Add Medicine
            </a>

        <?php endif; ?>

    </div>

</div>


<!-- =========================
     MEDICINE TABLE
     ========================= -->

<div class="table-container">

<table>

    <tr>

        <th>ID</th>
        <th>Medicine Name</th>
        <th>Category</th>
        <th>Supplier</th>
        <th>Batch No</th>
        <th>Purchase Price</th>
        <th>Selling Price</th>
        <th>Stock Qty</th>
        <th>Expiry Date</th>
        <th>Description</th>
        <th>Status</th>

        <?php if ($isAdmin): ?>
            <th>Action</th>
        <?php endif; ?>

    </tr>


    <?php if (mysqli_num_rows($result) > 0): ?>

        <?php while ($row = mysqli_fetch_assoc($result)): ?>

            <?php
                $displayStatus = getMedicineStatus($row);
            ?>

            <tr>

                <!-- ID -->

                <td>
                    <?= e($row['MedicineID']) ?>
                </td>


                <!-- MEDICINE NAME -->

                <td>
                    <?= e($row['MedicineName']) ?>
                </td>


                <!-- CATEGORY -->

                <td>
                    <?= e($row['Category']) ?>
                </td>


                <!-- SUPPLIER -->

                <td>

                    <?php if (!empty($row['SupplierName'])): ?>

                        <?= e($row['SupplierName']) ?>

                    <?php else: ?>

                        Supplier ID: <?= e($row['SupplierID']) ?>

                    <?php endif; ?>

                </td>


                <!-- BATCH -->

                <td>
                    <?= e($row['BatchNo']) ?>
                </td>


                <!-- PURCHASE PRICE -->

                <td>
                    ₹<?= number_format(
                        (float)$row['PurchasePrice'],
                        2
                    ) ?>
                </td>


                <!-- SELLING PRICE -->

                <td>
                    ₹<?= number_format(
                        (float)$row['SellingPrice'],
                        2
                    ) ?>
                </td>


                <!-- STOCK -->

                <td>
                    <?= e($row['StockQty']) ?>
                </td>


                <!-- EXPIRY DATE -->

                <td>

                    <?php if (!empty($row['ExpiryDate'])): ?>

                        <?= e(
                            date(
                                'd-m-Y',
                                strtotime($row['ExpiryDate'])
                            )
                        ) ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>


                <!-- DESCRIPTION -->

                <td>
                    <?= e($row['Description']) ?>
                </td>


                <!-- STATUS -->

                <td>

                    <?php if ($displayStatus === 'Available'): ?>

                        <span class="status-available">
                            Available
                        </span>

                    <?php elseif ($displayStatus === 'Expired'): ?>

                        <span class="status-expired">
                            Expired
                        </span>

                    <?php else: ?>

                        <span class="status-unavailable">
                            Unavailable
                        </span>

                    <?php endif; ?>

                </td>


                <!-- ADMIN ACTIONS -->

                <?php if ($isAdmin): ?>

                    <td>

                        <a
                            class="edit"
                            href="edit_medicine.php?id=<?= urlencode(
                                (string)$row['MedicineID']
                            ) ?>"
                        >
                            Edit
                        </a>

                        |

                        <form
                            method="POST"
                            action="delete_medicine.php?id=<?= urlencode(
                                (string)$row['MedicineID']
                            ) ?>"
                            class="delete-form"
                            onsubmit="return confirmDelete();"
                        >

                            <input
                                type="hidden"
                                name="medicineID"
                                value="<?= e($row['MedicineID']) ?>"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrf_token) ?>"
                            >

                            <button
                                type="submit"
                                name="delete"
                                class="delete"
                            >
                                Delete
                            </button>

                        </form>

                    </td>

                <?php endif; ?>

            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>

            <td
                colspan="<?= $isAdmin ? '12' : '11' ?>"
                class="no-data"
            >
                No medicines found.
            </td>

        </tr>

    <?php endif; ?>

</table>

</div>


<script>

function confirmDelete()
{
    return confirm(
        "Are you sure you want to permanently delete this medicine?"
    );
}

</script>

</body>
</html>
<?php

session_start();
include("db_connect.php");


/* =========================================
   ADMIN LOGIN CHECK
   ========================================= */

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}


/* =========================================
   CSRF TOKEN
   ========================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


/* =========================================
   HELPER
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
   VARIABLES
   ========================================= */

$message = "";
$messageType = "";

$medicine = null;
$medicineID = 0;


/* =========================================
   GET MEDICINE ID
   Accept both id and MedicineID
   ========================================= */

if (isset($_GET['id'])) {

    $id = trim($_GET['id']);

} elseif (isset($_GET['MedicineID'])) {

    $id = trim($_GET['MedicineID']);

} else {

    $id = "";

}


/* =========================================
   IF POST, GET ID FROM POST
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedID = trim($_POST['medicineID'] ?? "");

    if ($postedID !== "") {
        $id = $postedID;
    }

}


/* =========================================
   VALIDATE ID
   ========================================= */

if ($id === "") {

    $message = "Medicine ID was not provided.";
    $messageType = "error";

} elseif (
    !ctype_digit($id) ||
    (int)$id <= 0
) {

    $message = "Invalid medicine ID.";
    $messageType = "error";

} else {

    $medicineID = (int)$id;

}


/* =========================================
   DELETE PROCESS
   ========================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete']) &&
    $message === ""
) {


    /* =====================================
       CSRF CHECK
       ===================================== */

    $submittedToken = $_POST['csrf_token'] ?? "";

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        )
    ) {

        $message =
            "Invalid form submission. Please refresh the page and try again.";

        $messageType = "error";

    }


    /* =====================================
       CHECK MEDICINE EXISTS
       ===================================== */

    if ($message === "") {

        $stmt = $conn->prepare(
            "SELECT MedicineID
             FROM medicine
             WHERE MedicineID = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $message =
                "Unable to verify the medicine.";

            $messageType = "error";

        } else {

            $stmt->bind_param(
                "i",
                $medicineID
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {

                $message =
                    "The selected medicine does not exist.";

                $messageType = "error";

            }

            $stmt->close();
        }
    }


    /* =====================================
       CHECK SALES
       ===================================== */

    if ($message === "") {

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM sale_items
             WHERE MedicineID = ?"
        );

        if (!$stmt) {

            $message =
                "Unable to check sales records.";

            $messageType = "error";

        } else {

            $stmt->bind_param(
                "i",
                $medicineID
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $row = $result->fetch_assoc();

            $stmt->close();


            if ((int)$row['total'] > 0) {

                $message =
                    "This medicine cannot be deleted because it is already used in sales records.";

                $messageType = "error";
            }
        }
    }


    /* =====================================
       CHECK PURCHASES
       ===================================== */

    if ($message === "") {

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM purchase_items
             WHERE MedicineID = ?"
        );

        if (!$stmt) {

            $message =
                "Unable to check purchase records.";

            $messageType = "error";

        } else {

            $stmt->bind_param(
                "i",
                $medicineID
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $row = $result->fetch_assoc();

            $stmt->close();


            if ((int)$row['total'] > 0) {

                $message =
                    "This medicine cannot be deleted because it is already used in purchase records.";

                $messageType = "error";
            }
        }
    }


    /* =====================================
       DELETE MEDICINE
       ===================================== */

    if ($message === "") {

        $stmt = $conn->prepare(
            "DELETE FROM medicine
             WHERE MedicineID = ?"
        );

        if (!$stmt) {

            $message =
                "Unable to prepare the deletion request.";

            $messageType = "error";

        } else {

            $stmt->bind_param(
                "i",
                $medicineID
            );

            if ($stmt->execute()) {

                if ($stmt->affected_rows === 1) {

                    /* Generate new CSRF token */

                    $_SESSION['csrf_token'] =
                        bin2hex(random_bytes(32));


                    header(
                        "Location: view_medicine.php?deleted=1"
                    );

                    exit();

                } else {

                    $message =
                        "Medicine could not be deleted.";

                    $messageType = "error";
                }

            } else {

                $message =
                    "Unable to delete the medicine.";

                $messageType = "error";
            }

            $stmt->close();
        }
    }
}


/* =========================================
   LOAD MEDICINE DETAILS
   ========================================= */

if (
    $medicineID > 0 &&
    $message !==
    "The selected medicine does not exist."
) {

    $stmt = $conn->prepare(
        "SELECT
            m.MedicineID,
            m.MedicineName,
            m.Category,
            m.BatchNo,
            m.PurchasePrice,
            m.SellingPrice,
            m.StockQty,
            m.ExpiryDate,
            m.Status,
            s.SupplierName
         FROM medicine m
         LEFT JOIN supplier s
            ON m.SupplierID = s.SupplierID
         WHERE m.MedicineID = ?
         LIMIT 1"
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $medicineID
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $medicine =
                $result->fetch_assoc();

        } elseif ($message === "") {

            $message =
                "Medicine not found.";

            $messageType = "error";
        }

        $stmt->close();
    }
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

    <title>Delete Medicine</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #333;
        }

        .container {

            width: 600px;

            max-width: 95%;

            margin: 60px auto;

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.12);
        }

        h2 {

            text-align: center;

            margin-top: 0;

            color: #c0392b;
        }

        .warning {

            background: #fff3cd;

            color: #856404;

            border:
                1px solid #ffeeba;

            padding: 15px;

            border-radius: 6px;

            margin-bottom: 20px;

            line-height: 1.6;
        }

        .error {

            background: #f8d7da;

            color: #721c24;

            border:
                1px solid #f5c6cb;

            padding: 14px;

            border-radius: 6px;

            margin-bottom: 20px;

            text-align: center;

            font-weight: bold;
        }

        .medicine-details {

            background: #f8f9fa;

            border:
                1px solid #ddd;

            border-radius: 8px;

            padding: 18px;

            margin-bottom: 20px;
        }

        .detail-row {

            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            padding: 9px 0;

            border-bottom:
                1px solid #e5e5e5;
        }

        .detail-row:last-child {

            border-bottom: none;
        }

        .detail-label {

            font-weight: bold;

            color: #555;
        }

        .detail-value {

            text-align: right;

            color: #222;
        }

        .delete-btn {

            width: 100%;

            padding: 13px;

            background: #c0392b;

            color: white;

            border: none;

            border-radius: 6px;

            font-size: 16px;

            cursor: pointer;
        }

        .delete-btn:hover {

            background: #a93226;
        }

        .delete-btn:disabled {

            background: #999;

            cursor: not-allowed;
        }

        .back-btn {

            display: block;

            width: 100%;

            padding: 13px;

            margin-top: 10px;

            background: #555;

            color: white;

            text-decoration: none;

            text-align: center;

            border-radius: 6px;
        }

        .back-btn:hover {

            background: #444;
        }

        .danger-note {

            text-align: center;

            margin-top: 15px;

            color: #777;

            font-size: 13px;
        }

        @media screen and (max-width: 600px) {

            .container {

                margin: 25px auto;

                padding: 20px;
            }

            .detail-row {

                flex-direction: column;

                gap: 4px;
            }

            .detail-value {

                text-align: left;
            }
        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Delete Medicine
    </h2>


    <?php if ($message !== ""): ?>

        <div class="error">

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>


    <?php if ($medicine !== null): ?>


        <div class="warning">

            <strong>Warning:</strong>

            You are about to permanently delete
            this medicine from the inventory.

            This action cannot be undone.

        </div>


        <div class="medicine-details">


            <div class="detail-row">

                <span class="detail-label">
                    Medicine ID
                </span>

                <span class="detail-value">
                    <?php echo e($medicine['MedicineID']); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Medicine Name
                </span>

                <span class="detail-value">
                    <?php echo e($medicine['MedicineName']); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Category
                </span>

                <span class="detail-value">
                    <?php echo e($medicine['Category']); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Supplier
                </span>

                <span class="detail-value">
                    <?php
                    echo e(
                        $medicine['SupplierName']
                        ?? 'Not available'
                    );
                    ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Batch Number
                </span>

                <span class="detail-value">
                    <?php echo e($medicine['BatchNo']); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Purchase Price
                </span>

                <span class="detail-value">
                    ₹<?php
                    echo number_format(
                        (float)$medicine['PurchasePrice'],
                        2
                    );
                    ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Selling Price
                </span>

                <span class="detail-value">
                    ₹<?php
                    echo number_format(
                        (float)$medicine['SellingPrice'],
                        2
                    );
                    ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Stock Quantity
                </span>

                <span class="detail-value">
                    <?php echo e($medicine['StockQty']); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Expiry Date
                </span>

                <span class="detail-value">
                    <?php echo e($medicine['ExpiryDate']); ?>
                </span>

            </div>


        </div>


        <form
            method="POST"
            id="deleteForm"
        >

            <input
                type="hidden"
                name="medicineID"
                value="<?php
                    echo e($medicine['MedicineID']);
                ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrfToken); ?>"
            >


            <button
                type="submit"
                name="delete"
                class="delete-btn"
                id="deleteButton"
            >
                Permanently Delete Medicine
            </button>

        </form>


        <div class="danger-note">

            This medicine will be permanently removed
            only after you confirm the deletion.

        </div>


        <a
            href="view_medicine.php"
            class="back-btn"
        >
            Cancel
        </a>


    <?php else: ?>


        <a
            href="view_medicine.php"
            class="back-btn"
        >
            Back to Medicine List
        </a>


    <?php endif; ?>


</div>


<script>

const deleteForm =
    document.getElementById("deleteForm");

if (deleteForm) {

    deleteForm.addEventListener(
        "submit",
        function(event) {

            const confirmed = confirm(
                "Are you sure you want to permanently delete this medicine?"
            );

            if (!confirmed) {

                event.preventDefault();

                return;
            }

            const button =
                document.getElementById(
                    "deleteButton"
                );

            if (button) {

                button.disabled = true;

                button.innerText =
                    "Deleting Medicine...";
            }

        }
    );

}

</script>


</body>

</html>
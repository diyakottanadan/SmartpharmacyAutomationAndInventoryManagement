<?php

session_start();

include("db_connect.php");


/* =========================================
   CHECK LOGIN
   ========================================= */

if (
    !isset($_SESSION['admin']) &&
    !isset($_SESSION['pharmacist'])
) {

    header("Location: login.php");
    exit();

}


/* =========================================
   CSRF TOKEN
   ========================================= */

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken = $_SESSION['csrf_token'];


/* =========================================
   HELPER FUNCTION
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
   GET SUPPLIER ID
   ========================================= */

if (
    !isset($_GET['id']) ||
    !ctype_digit($_GET['id']) ||
    (int)$_GET['id'] <= 0
) {

    die("Invalid Supplier ID.");

}

$supplierID = (int)$_GET['id'];


/* =========================================
   GET SUPPLIER DETAILS
   ========================================= */

$selectSql = "
    SELECT
        SupplierID,
        SupplierName,
        ContactPerson,
        ContactNo,
        Email
    FROM supplier
    WHERE SupplierID = ?
    LIMIT 1
";

$selectStmt = mysqli_prepare(
    $conn,
    $selectSql
);

if (!$selectStmt) {

    die("Unable to load supplier information.");

}

mysqli_stmt_bind_param(
    $selectStmt,
    "i",
    $supplierID
);

mysqli_stmt_execute(
    $selectStmt
);

mysqli_stmt_store_result(
    $selectStmt
);

if (
    mysqli_stmt_num_rows($selectStmt) === 0
) {

    mysqli_stmt_close($selectStmt);

    die("Supplier not found.");

}

mysqli_stmt_bind_result(
    $selectStmt,
    $dbSupplierID,
    $supplierName,
    $contactPerson,
    $contactNo,
    $email
);

mysqli_stmt_fetch(
    $selectStmt
);

mysqli_stmt_close(
    $selectStmt
);


/* =========================================
   DELETE ONLY AFTER POST
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =====================================
       CSRF VALIDATION
       ===================================== */

    $submittedToken =
        $_POST['csrf_token'] ?? '';

    if (
        empty($submittedToken) ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        die("Invalid form submission.");

    }


    /* =====================================
       VALIDATE SUPPLIER ID
       ===================================== */

    $postedSupplierID =
        $_POST['SupplierID'] ?? '';

    if (
        !ctype_digit($postedSupplierID) ||
        (int)$postedSupplierID <= 0 ||
        (int)$postedSupplierID !== $supplierID
    ) {

        die("Invalid Supplier ID.");

    }


    /* =====================================
       CHECK PURCHASE REFERENCES
       ===================================== */

    /*
       The primary key in your purchase table
       is Purchase_Id, not PurchaseID.
    */

    $purchaseSql = "
        SELECT Purchase_Id
        FROM purchase
        WHERE SupplierID = ?
        LIMIT 1
    ";

    $purchaseStmt = mysqli_prepare(
        $conn,
        $purchaseSql
    );

    if (!$purchaseStmt) {

        die(
            "Unable to check supplier purchase records."
        );

    }

    mysqli_stmt_bind_param(
        $purchaseStmt,
        "i",
        $supplierID
    );

    mysqli_stmt_execute(
        $purchaseStmt
    );

    mysqli_stmt_store_result(
        $purchaseStmt
    );

    if (
        mysqli_stmt_num_rows($purchaseStmt) > 0
    ) {

        mysqli_stmt_close(
            $purchaseStmt
        );

        die(
            "This supplier cannot be deleted because purchase records are associated with this supplier. You can keep the supplier record for historical transactions."
        );

    }

    mysqli_stmt_close(
        $purchaseStmt
    );


    /* =====================================
       DELETE SUPPLIER
       ===================================== */

    $deleteSql = "
        DELETE FROM supplier
        WHERE SupplierID = ?
    ";

    $deleteStmt = mysqli_prepare(
        $conn,
        $deleteSql
    );

    if (!$deleteStmt) {

        die(
            "Unable to process the deletion."
        );

    }

    mysqli_stmt_bind_param(
        $deleteStmt,
        "i",
        $supplierID
    );

    if (
        mysqli_stmt_execute(
            $deleteStmt
        )
    ) {

        mysqli_stmt_close(
            $deleteStmt
        );

        header(
            "Location: view_supplier.php?deleted=1"
        );

        exit();

    }

    mysqli_stmt_close(
        $deleteStmt
    );

    die(
        "Unable to delete supplier. Please try again."
    );

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
        Delete Supplier
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                sans-serif;

            background-color:
                #f4f4f4;

            margin:
                0;

        }


        .container {

            width:
                500px;

            max-width:
                95%;

            margin:
                80px auto;

            background-color:
                white;

            padding:
                30px;

            border-radius:
                10px;

            box-shadow:
                0 0 10px #ccc;

            text-align:
                center;

        }


        h2 {

            color:
                #c0392b;

            margin-top:
                0;

        }


        .warning {

            background:
                #fff3cd;

            border:
                1px solid #ffeeba;

            color:
                #856404;

            padding:
                15px;

            border-radius:
                5px;

            margin:
                20px 0;

            line-height:
                1.5;

        }


        .supplier-info {

            text-align:
                left;

            background:
                #f8f8f8;

            padding:
                15px;

            border-radius:
                5px;

            margin-bottom:
                20px;

        }


        .supplier-info p {

            margin:
                8px 0;

        }


        .delete-button {

            padding:
                11px 20px;

            background:
                #c0392b;

            color:
                white;

            border:
                none;

            border-radius:
                5px;

            cursor:
                pointer;

            font-size:
                15px;

        }


        .delete-button:hover {

            background:
                #962d22;

        }


        .cancel {

            display:
                inline-block;

            padding:
                10px 20px;

            margin-left:
                8px;

            background:
                #555;

            color:
                white;

            text-decoration:
                none;

            border-radius:
                5px;

        }


        .cancel:hover {

            background:
                #333;

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Delete Supplier
    </h2>


    <div class="warning">

        <strong>Warning:</strong>

        Are you sure you want to delete this supplier?

        <br>

        This action cannot be undone.

    </div>


    <div class="supplier-info">


        <p>

            <strong>Supplier ID:</strong>

            <?php
            echo e($dbSupplierID);
            ?>

        </p>


        <p>

            <strong>Supplier Name:</strong>

            <?php
            echo e($supplierName);
            ?>

        </p>


        <p>

            <strong>Contact Person:</strong>

            <?php
            echo e($contactPerson);
            ?>

        </p>


        <p>

            <strong>Contact Number:</strong>

            <?php
            echo e($contactNo);
            ?>

        </p>


        <p>

            <strong>Email:</strong>

            <?php
            echo e($email);
            ?>

        </p>


    </div>


    <form
        method="POST"
        onsubmit="
            return confirm(
                'Are you sure you want to permanently delete this supplier?'
            );
        "
    >


        <input
            type="hidden"
            name="SupplierID"
            value="<?php
                echo e($supplierID);
            ?>"
        >


        <input
            type="hidden"
            name="csrf_token"
            value="<?php
                echo e($csrfToken);
            ?>"
        >


        <button
            type="submit"
            class="delete-button"
        >

            Yes, Delete Supplier

        </button>


        <a
            href="view_supplier.php"
            class="cancel"
        >

            Cancel

        </a>


    </form>


</div>


</body>

</html>
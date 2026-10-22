<?php

session_start();
include("db_connect.php");


/* =====================================================
   ADMIN LOGIN CHECK
   ===================================================== */

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}


/* =====================================================
   CSRF TOKEN
   ===================================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


/* =====================================================
   FORM VARIABLES
   ===================================================== */

$medicineName  = "";
$category      = "";
$supplierID    = "";
$batchNo      = "";
$purchasePrice = "";
$sellingPrice  = "";
$stockQty      = "";
$expiryDate    = "";
$description   = "";

$errors = [];
$successMessage = "";


/* =====================================================
   LOAD SUPPLIERS
   ===================================================== */

$supplierSQL = "
    SELECT SupplierID, SupplierName
    FROM supplier
    ORDER BY SupplierName ASC
";

$supplierResult = mysqli_query($conn, $supplierSQL);

if (!$supplierResult) {
    $errors['general'] = "Unable to load supplier information.";
}


/* =====================================================
   PROCESS FORM
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* =================================================
       CSRF VALIDATION
       ================================================= */

    $submittedToken = $_POST['csrf_token'] ?? "";

    if (
        empty($submittedToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors['general'] =
            "Invalid form submission. Please refresh the page and try again.";
    }


    /* =================================================
       GET FORM VALUES
       ================================================= */

    $medicineName = trim($_POST['MedicineName'] ?? "");
    $category     = trim($_POST['Category'] ?? "");
    $supplierID   = trim($_POST['SupplierID'] ?? "");
    $batchNo      = trim($_POST['BatchNo'] ?? "");
    $purchasePrice = trim($_POST['PurchasePrice'] ?? "");
    $sellingPrice  = trim($_POST['SellingPrice'] ?? "");
    $stockQty      = trim($_POST['StockQty'] ?? "");
    $expiryDate    = trim($_POST['ExpiryDate'] ?? "");
    $description   = trim($_POST['Description'] ?? "");


    /* =================================================
       MEDICINE NAME VALIDATION
       ================================================= */

    if ($medicineName === "") {

        $errors['MedicineName'] =
            "Medicine name is required.";

    } elseif (strlen($medicineName) < 2) {

        $errors['MedicineName'] =
            "Medicine name must contain at least 2 characters.";

    } elseif (strlen($medicineName) > 25) {

        $errors['MedicineName'] =
            "Medicine name cannot exceed 25 characters.";

    } elseif (
        !preg_match(
            "/^[A-Za-z0-9][A-Za-z0-9 .()&+'\/-]*$/",
            $medicineName
        )
    ) {

        $errors['MedicineName'] =
            "Medicine name contains invalid characters.";
    }


    /* =================================================
       CATEGORY VALIDATION
       ================================================= */

    if ($category === "") {

        $errors['Category'] =
            "Category is required.";

    } elseif (strlen($category) < 2) {

        $errors['Category'] =
            "Category must contain at least 2 characters.";

    } elseif (strlen($category) > 25) {

        $errors['Category'] =
            "Category cannot exceed 25 characters.";

    } elseif (
        !preg_match(
            "/^[A-Za-z][A-Za-z &\/-]*$/",
            $category
        )
    ) {

        $errors['Category'] =
            "Category contains invalid characters.";
    }


    /* =================================================
       SUPPLIER VALIDATION
       ================================================= */

    if ($supplierID === "") {

        $errors['SupplierID'] =
            "Please select a supplier.";

    } elseif (
        !ctype_digit($supplierID) ||
        (int)$supplierID <= 0
    ) {

        $errors['SupplierID'] =
            "Please select a valid supplier.";
    }


    /* =================================================
       BATCH NUMBER VALIDATION
       ================================================= */

    if ($batchNo === "") {

        $errors['BatchNo'] =
            "Batch number is required.";

    } elseif (strlen($batchNo) < 2) {

        $errors['BatchNo'] =
            "Batch number must contain at least 2 characters.";

    } elseif (strlen($batchNo) > 25) {

        $errors['BatchNo'] =
            "Batch number cannot exceed 25 characters.";

    } elseif (
        !preg_match(
            "/^[A-Za-z0-9][A-Za-z0-9-]*$/",
            $batchNo
        )
    ) {

        $errors['BatchNo'] =
            "Batch number can contain only letters, numbers and hyphens.";
    }


    /* =================================================
       PURCHASE PRICE VALIDATION
       ================================================= */

    if ($purchasePrice === "") {

        $errors['PurchasePrice'] =
            "Purchase price is required.";

    } elseif (
        !preg_match(
            "/^\d+(\.\d{1,2})?$/",
            $purchasePrice
        )
    ) {

        $errors['PurchasePrice'] =
            "Purchase price must be a valid amount with a maximum of 2 decimal places.";

    } elseif ((float)$purchasePrice <= 0) {

        $errors['PurchasePrice'] =
            "Purchase price must be greater than 0.";

    } elseif ((float)$purchasePrice > 99999999.99) {

        $errors['PurchasePrice'] =
            "Purchase price is too large.";
    }


    /* =================================================
       SELLING PRICE VALIDATION
       ================================================= */

    if ($sellingPrice === "") {

        $errors['SellingPrice'] =
            "Selling price is required.";

    } elseif (
        !preg_match(
            "/^\d+(\.\d{1,2})?$/",
            $sellingPrice
        )
    ) {

        $errors['SellingPrice'] =
            "Selling price must be a valid amount with a maximum of 2 decimal places.";

    } elseif ((float)$sellingPrice <= 0) {

        $errors['SellingPrice'] =
            "Selling price must be greater than 0.";

    } elseif ((float)$sellingPrice > 99999999.99) {

        $errors['SellingPrice'] =
            "Selling price is too large.";
    }


    /* =================================================
       PRICE BUSINESS RULE
       ================================================= */

    if (
        $purchasePrice !== "" &&
        $sellingPrice !== "" &&
        is_numeric($purchasePrice) &&
        is_numeric($sellingPrice)
    ) {

        if (
            (float)$sellingPrice <
            (float)$purchasePrice
        ) {

            $errors['SellingPrice'] =
                "Selling price cannot be less than purchase price.";
        }
    }


    /* =================================================
       STOCK QUANTITY VALIDATION
       ================================================= */

    if ($stockQty === "") {

        $errors['StockQty'] =
            "Stock quantity is required.";

    } elseif (!ctype_digit($stockQty)) {

        $errors['StockQty'] =
            "Stock quantity must be a whole number.";

    } elseif ((int)$stockQty > 100000) {

        $errors['StockQty'] =
            "Stock quantity cannot exceed 100000.";
    }


    /* =================================================
       EXPIRY DATE VALIDATION
       ================================================= */

    if ($expiryDate === "") {

        $errors['ExpiryDate'] =
            "Expiry date is required.";

    } else {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $expiryDate
        );

        $dateErrors = DateTime::getLastErrors();

        $validDate = true;

        if ($dateObject === false) {
            $validDate = false;
        }

        if (
            $dateErrors !== false &&
            (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )
        ) {
            $validDate = false;
        }

        if (!$validDate) {

            $errors['ExpiryDate'] =
                "Please enter a valid expiry date.";

        } else {

            $today = new DateTime();
            $today->setTime(0, 0, 0);

            $dateObject->setTime(0, 0, 0);

            if ($dateObject <= $today) {

                $errors['ExpiryDate'] =
                    "Expiry date must be a future date.";
            }
        }
    }


    /* =================================================
       DESCRIPTION VALIDATION
       ================================================= */

    if ($description === "") {

        $errors['Description'] =
            "Description is required.";

    } elseif (strlen($description) < 5) {

        $errors['Description'] =
            "Description must contain at least 5 characters.";

    } elseif (strlen($description) > 500) {

        $errors['Description'] =
            "Description cannot exceed 500 characters.";
    }


    /* =================================================
       CHECK SUPPLIER EXISTS
       ================================================= */

    if (
        !isset($errors['SupplierID']) &&
        $supplierID !== ""
    ) {

        $supplierCheckSQL = "
            SELECT SupplierID
            FROM supplier
            WHERE SupplierID = ?
            LIMIT 1
        ";

        $supplierCheckStmt =
            mysqli_prepare(
                $conn,
                $supplierCheckSQL
            );

        if ($supplierCheckStmt) {

            $supplierIDInt = (int)$supplierID;

            mysqli_stmt_bind_param(
                $supplierCheckStmt,
                "i",
                $supplierIDInt
            );

            mysqli_stmt_execute(
                $supplierCheckStmt
            );

            mysqli_stmt_store_result(
                $supplierCheckStmt
            );

            if (
                mysqli_stmt_num_rows(
                    $supplierCheckStmt
                ) === 0
            ) {

                $errors['SupplierID'] =
                    "The selected supplier does not exist.";
            }

            mysqli_stmt_close(
                $supplierCheckStmt
            );

        } else {

            $errors['general'] =
                "Unable to validate supplier information.";
        }
    }


    /* =================================================
       CHECK DUPLICATE MEDICINE + BATCH
       ================================================= */

    if (
        !isset($errors['MedicineName']) &&
        !isset($errors['BatchNo'])
    ) {

        $duplicateSQL = "
            SELECT MedicineID
            FROM medicine
            WHERE LOWER(MedicineName) = LOWER(?)
            AND BatchNo = ?
            LIMIT 1
        ";

        $duplicateStmt =
            mysqli_prepare(
                $conn,
                $duplicateSQL
            );

        if ($duplicateStmt) {

            mysqli_stmt_bind_param(
                $duplicateStmt,
                "ss",
                $medicineName,
                $batchNo
            );

            mysqli_stmt_execute(
                $duplicateStmt
            );

            mysqli_stmt_store_result(
                $duplicateStmt
            );

            if (
                mysqli_stmt_num_rows(
                    $duplicateStmt
                ) > 0
            ) {

                $errors['general'] =
                    "This medicine with the same batch number already exists.";
            }

            mysqli_stmt_close(
                $duplicateStmt
            );

        } else {

            $errors['general'] =
                "Unable to check for duplicate medicine records.";
        }
    }


    /* =================================================
       INSERT MEDICINE
       ================================================= */

    if (empty($errors)) {

        $supplierIDInt =
            (int)$supplierID;

        $purchasePriceFloat =
            (float)$purchasePrice;

        $sellingPriceFloat =
            (float)$sellingPrice;

        $stockQtyInt =
            (int)$stockQty;


        /* ---------------------------------------------
           AUTOMATIC STATUS
           --------------------------------------------- */

        $status =
            ($stockQtyInt > 0)
            ? "Available"
            : "Unavailable";


        /* ---------------------------------------------
           INSERT QUERY
           --------------------------------------------- */

        $insertSQL = "
            INSERT INTO medicine
            (
                MedicineName,
                Category,
                SupplierID,
                BatchNo,
                PurchasePrice,
                SellingPrice,
                StockQty,
                ExpiryDate,
                Description,
                Status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $insertStmt =
            mysqli_prepare(
                $conn,
                $insertSQL
            );

        if ($insertStmt) {

            mysqli_stmt_bind_param(
                $insertStmt,
                "ssisddiiss",
                $medicineName,
                $category,
                $supplierIDInt,
                $batchNo,
                $purchasePriceFloat,
                $sellingPriceFloat,
                $stockQtyInt,
                $expiryDate,
                $description,
                $status
            );

            if (
                mysqli_stmt_execute(
                    $insertStmt
                )
            ) {

                $successMessage =
                    "Medicine added successfully.";

                /* -----------------------------------------
                   CLEAR FORM AFTER SUCCESS
                   ----------------------------------------- */

                $medicineName  = "";
                $category      = "";
                $supplierID    = "";
                $batchNo       = "";
                $purchasePrice = "";
                $sellingPrice  = "";
                $stockQty      = "";
                $expiryDate    = "";
                $description   = "";

            } else {

                $errors['general'] =
                    "Unable to add the medicine. Please try again.";
            }

            mysqli_stmt_close(
                $insertStmt
            );

        } else {

            $errors['general'] =
                "Unable to prepare the medicine record.";
        }
    }
}


/* =====================================================
   HELPER FUNCTION
   ===================================================== */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
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

    <title>Add Medicine</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #333;
        }

        .container {
            width: 650px;
            max-width: 95%;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow:
                0 3px 15px
                rgba(0, 0, 0, 0.12);
        }

        h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 8px;
            color: #222;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .error-box {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .error-box strong {
            display: block;
            margin-bottom: 7px;
        }

        .error-box ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .error-box li {
            margin-bottom: 4px;
        }

        .success-box {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .required {
            color: #c0392b;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            font-family: Arial, sans-serif;
            background: white;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #2980b9;
            box-shadow:
                0 0 0 2px
                rgba(41, 128, 185, 0.12);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .help-text {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }

        .field-error {
            display: block;
            margin-top: 5px;
            color: #c0392b;
            font-size: 13px;
            font-weight: bold;
        }

        button,
        .back-button {
            display: block;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }

        .submit-button {
            background: #198754;
            color: white;
            margin-top: 25px;
        }

        .submit-button:hover {
            background: #157347;
        }

        .back-button {
            background: #555;
            color: white;
            margin-top: 10px;
        }

        .back-button:hover {
            background: #444;
        }

        @media screen and (max-width: 600px) {

            .container {
                margin: 20px auto;
                padding: 20px;
            }

        }

    </style>

</head>


<body>


<div class="container">

    <h2>Add New Medicine</h2>

    <div class="subtitle">
        Enter complete and accurate medicine information
        for inventory management.
    </div>


    <?php if (!empty($errors)) { ?>

        <div class="error-box">

            <strong>
                Please correct the following:
            </strong>

            <ul>

                <?php foreach ($errors as $error) { ?>

                    <li>
                        <?php echo e($error); ?>
                    </li>

                <?php } ?>

            </ul>

        </div>

    <?php } ?>


    <?php if ($successMessage !== "") { ?>

        <div class="success-box">

            <?php echo e($successMessage); ?>

        </div>

    <?php } ?>


    <form
        method="POST"
        action=""
        id="medicineForm"
        novalidate
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrfToken); ?>"
        >


        <!-- MEDICINE NAME -->

        <div class="form-group">

            <label for="MedicineName">

                Medicine Name
                <span class="required">*</span>

            </label>

            <input
                type="text"
                id="MedicineName"
                name="MedicineName"
                minlength="2"
                maxlength="25"
                value="<?php echo e($medicineName); ?>"
                placeholder="Example: Paracetamol 500mg"
                autocomplete="off"
                required
            >

            <span class="help-text">
                2–25 characters.
            </span>

            <?php if (isset($errors['MedicineName'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['MedicineName']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- CATEGORY -->

        <div class="form-group">

            <label for="Category">

                Category
                <span class="required">*</span>

            </label>

            <input
                type="text"
                id="Category"
                name="Category"
                minlength="2"
                maxlength="25"
                value="<?php echo e($category); ?>"
                placeholder="Example: Antibiotic"
                autocomplete="off"
                required
            >

            <?php if (isset($errors['Category'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['Category']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- SUPPLIER -->

        <div class="form-group">

            <label for="SupplierID">

                Supplier
                <span class="required">*</span>

            </label>

            <select
                id="SupplierID"
                name="SupplierID"
                required
            >

                <option value="">
                    Select Supplier
                </option>

                <?php

                if ($supplierResult) {

                    while (
                        $supplier =
                        mysqli_fetch_assoc($supplierResult)
                    ) {

                ?>

                    <option
                        value="<?php
                            echo (int)$supplier['SupplierID'];
                        ?>"
                        <?php
                        if (
                            (string)$supplierID ===
                            (string)$supplier['SupplierID']
                        ) {
                            echo "selected";
                        }
                        ?>
                    >

                        <?php
                        echo e(
                            $supplier['SupplierName']
                        );
                        ?>

                    </option>

                <?php

                    }
                }

                ?>

            </select>

            <?php if (isset($errors['SupplierID'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['SupplierID']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- BATCH NUMBER -->

        <div class="form-group">

            <label for="BatchNo">

                Batch Number
                <span class="required">*</span>

            </label>

            <input
                type="text"
                id="BatchNo"
                name="BatchNo"
                minlength="2"
                maxlength="25"
                value="<?php echo e($batchNo); ?>"
                placeholder="Example: PCM500-A24"
                autocomplete="off"
                required
            >

            <span class="help-text">
                Letters, numbers and hyphens are allowed.
            </span>

            <?php if (isset($errors['BatchNo'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['BatchNo']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- PURCHASE PRICE -->

        <div class="form-group">

            <label for="PurchasePrice">

                Purchase Price (₹)
                <span class="required">*</span>

            </label>

            <input
                type="number"
                id="PurchasePrice"
                name="PurchasePrice"
                min="0.01"
                max="99999999.99"
                step="0.01"
                value="<?php echo e($purchasePrice); ?>"
                placeholder="0.00"
                required
            >

            <?php if (isset($errors['PurchasePrice'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['PurchasePrice']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- SELLING PRICE -->

        <div class="form-group">

            <label for="SellingPrice">

                Selling Price (₹)
                <span class="required">*</span>

            </label>

            <input
                type="number"
                id="SellingPrice"
                name="SellingPrice"
                min="0.01"
                max="99999999.99"
                step="0.01"
                value="<?php echo e($sellingPrice); ?>"
                placeholder="0.00"
                required
            >

            <span class="help-text">
                Selling price cannot be lower than purchase price.
            </span>

            <?php if (isset($errors['SellingPrice'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['SellingPrice']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- STOCK -->

        <div class="form-group">

            <label for="StockQty">

                Initial Stock Quantity
                <span class="required">*</span>

            </label>

            <input
                type="number"
                id="StockQty"
                name="StockQty"
                min="0"
                max="100000"
                step="1"
                value="<?php echo e($stockQty); ?>"
                placeholder="Example: 100"
                required
            >

            <span class="help-text">
                Enter a whole number.
                Zero stock will be marked as Unavailable.
            </span>

            <?php if (isset($errors['StockQty'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['StockQty']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- EXPIRY DATE -->

        <div class="form-group">

            <label for="ExpiryDate">

                Expiry Date
                <span class="required">*</span>

            </label>

            <input
                type="date"
                id="ExpiryDate"
                name="ExpiryDate"
                min="<?php
                    echo date(
                        'Y-m-d',
                        strtotime('+1 day')
                    );
                ?>"
                value="<?php echo e($expiryDate); ?>"
                required
            >

            <span class="help-text">
                Expiry date must be in the future.
            </span>

            <?php if (isset($errors['ExpiryDate'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['ExpiryDate']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- DESCRIPTION -->

        <div class="form-group">

            <label for="Description">

                Description
                <span class="required">*</span>

            </label>

            <textarea
                id="Description"
                name="Description"
                minlength="5"
                maxlength="500"
                placeholder="Enter medicine details, dosage information, usage or other relevant information..."
                required
            ><?php echo e($description); ?></textarea>

            <span class="help-text">
                5–500 characters.
            </span>

            <?php if (isset($errors['Description'])) { ?>

                <span class="field-error">
                    <?php echo e($errors['Description']); ?>
                </span>

            <?php } ?>

        </div>


        <!-- SUBMIT -->

        <button
            type="submit"
            name="save"
            class="submit-button"
        >
            Save Medicine
        </button>


        <!-- BACK -->

        <a
            href="admin_dashboard.php"
            class="back-button"
        >
            Back to Dashboard
        </a>

    </form>

</div>


<script>

/* =====================================================
   CLIENT-SIDE VALIDATION
   ===================================================== */

document
    .getElementById("medicineForm")
    .addEventListener("submit", function(event) {

        const medicineName =
            document
                .getElementById("MedicineName")
                .value
                .trim();

        const category =
            document
                .getElementById("Category")
                .value
                .trim();

        const supplier =
            document
                .getElementById("SupplierID")
                .value;

        const batchNo =
            document
                .getElementById("BatchNo")
                .value
                .trim();

        const purchasePriceText =
            document
                .getElementById("PurchasePrice")
                .value;

        const sellingPriceText =
            document
                .getElementById("SellingPrice")
                .value;

        const stockQtyText =
            document
                .getElementById("StockQty")
                .value;

        const expiryDate =
            document
                .getElementById("ExpiryDate")
                .value;

        const description =
            document
                .getElementById("Description")
                .value
                .trim();


        /* MEDICINE NAME */

        if (
            medicineName.length < 2 ||
            medicineName.length > 25 ||
            !/^[A-Za-z0-9][A-Za-z0-9 .()&+'\/-]*$/
                .test(medicineName)
        ) {

            alert(
                "Please enter a valid medicine name."
            );

            event.preventDefault();
            return;
        }


        /* CATEGORY */

        if (
            category.length < 2 ||
            category.length > 25 ||
            !/^[A-Za-z][A-Za-z &\/-]*$/
                .test(category)
        ) {

            alert(
                "Please enter a valid category."
            );

            event.preventDefault();
            return;
        }


        /* SUPPLIER */

        if (supplier === "") {

            alert(
                "Please select a supplier."
            );

            event.preventDefault();
            return;
        }


        /* BATCH */

        if (
            batchNo.length < 2 ||
            batchNo.length > 25 ||
            !/^[A-Za-z0-9][A-Za-z0-9-]*$/
                .test(batchNo)
        ) {

            alert(
                "Please enter a valid batch number."
            );

            event.preventDefault();
            return;
        }


        /* PURCHASE PRICE */

        if (
            !/^\d+(\.\d{1,2})?$/
                .test(purchasePriceText)
        ) {

            alert(
                "Purchase price must be a valid amount with maximum 2 decimal places."
            );

            event.preventDefault();
            return;
        }


        const purchasePrice =
            parseFloat(purchasePriceText);


        if (
            purchasePrice <= 0 ||
            purchasePrice > 99999999.99
        ) {

            alert(
                "Please enter a valid purchase price."
            );

            event.preventDefault();
            return;
        }


        /* SELLING PRICE */

        if (
            !/^\d+(\.\d{1,2})?$/
                .test(sellingPriceText)
        ) {

            alert(
                "Selling price must be a valid amount with maximum 2 decimal places."
            );

            event.preventDefault();
            return;
        }


        const sellingPrice =
            parseFloat(sellingPriceText);


        if (
            sellingPrice <= 0 ||
            sellingPrice > 99999999.99
        ) {

            alert(
                "Please enter a valid selling price."
            );

            event.preventDefault();
            return;
        }


        /* PRICE RELATION */

        if (
            sellingPrice <
            purchasePrice
        ) {

            alert(
                "Selling price cannot be less than purchase price."
            );

            event.preventDefault();
            return;
        }


        /* STOCK */

        if (
            !/^\d+$/.test(stockQtyText)
        ) {

            alert(
                "Stock quantity must be a whole number."
            );

            event.preventDefault();
            return;
        }


        const stockQty =
            Number(stockQtyText);


        if (
            stockQty < 0 ||
            stockQty > 100000
        ) {

            alert(
                "Stock quantity must be between 0 and 100000."
            );

            event.preventDefault();
            return;
        }


        /* EXPIRY */

        if (expiryDate === "") {

            alert(
                "Please select an expiry date."
            );

            event.preventDefault();
            return;
        }


        const today =
            new Date();

        today.setHours(
            0, 0, 0, 0
        );


        const selectedDate =
            new Date(
                expiryDate + "T00:00:00"
            );


        if (
            isNaN(selectedDate.getTime()) ||
            selectedDate <= today
        ) {

            alert(
                "Expiry date must be a valid future date."
            );

            event.preventDefault();
            return;
        }


        /* DESCRIPTION */

        if (
            description.length < 5 ||
            description.length > 500
        ) {

            alert(
                "Description must contain between 5 and 500 characters."
            );

            event.preventDefault();
            return;
        }

    });

</script>


</body>
</html>
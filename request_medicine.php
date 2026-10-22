<?php

session_start();

include("db_connect.php");


/* =========================================================
   ACCESS CONTROL
   ========================================================= */

if (!isset($_SESSION['pharmacist'])) {

    header("Location: login.php");
    exit();

}

if (
    !isset($_SESSION['PharmacistID']) ||
    !ctype_digit((string)$_SESSION['PharmacistID']) ||
    (int)$_SESSION['PharmacistID'] <= 0
) {

    header("Location: login.php");
    exit();

}

$pharmacistID = (int)$_SESSION['PharmacistID'];


/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (!isset($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken = $_SESSION['csrf_token'];


/* =========================================================
   FORM VARIABLES
   ========================================================= */

$medicineName = "";
$category = "";
$supplierID = "";
$batchNo = "";
$purchasePrice = "";
$sellingPrice = "";
$expiryDate = "";
$description = "";

$errors = [];
$successMessage = "";


/* =========================================================
   HELPER FUNCTION
   ========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   GET SUPPLIERS
   ========================================================= */

$supplierSQL = "
    SELECT
        SupplierID,
        SupplierName
    FROM supplier
    ORDER BY SupplierName ASC
";

$supplierResult = mysqli_query(
    $conn,
    $supplierSQL
);

if (!$supplierResult) {

    $errors['general'] =
        "Unable to load supplier information.";

}


/* =========================================================
   PROCESS FORM
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =====================================================
       CSRF VALIDATION
       ===================================================== */

    $submittedToken =
        $_POST['csrf_token'] ?? "";

    if (
        $submittedToken === "" ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        )
    ) {

        $errors['general'] =
            "Invalid form submission. Please refresh the page and try again.";

    }


    /* =====================================================
       GET FORM VALUES
       ===================================================== */

    $medicineName =
        trim($_POST['medicineName'] ?? "");

    $category =
        trim($_POST['category'] ?? "");

    $supplierID =
        trim($_POST['supplierID'] ?? "");

    $batchNo =
        trim($_POST['batchNo'] ?? "");

    $purchasePrice =
        trim($_POST['purchasePrice'] ?? "");

    $sellingPrice =
        trim($_POST['sellingPrice'] ?? "");

    $expiryDate =
        trim($_POST['expiryDate'] ?? "");

    $description =
        trim($_POST['description'] ?? "");


    /* =====================================================
       MEDICINE NAME VALIDATION
       ===================================================== */

    if ($medicineName === "") {

        $errors['medicineName'] =
            "Medicine name is required.";

    }
    elseif (strlen($medicineName) < 2) {

        $errors['medicineName'] =
            "Medicine name must contain at least 2 characters.";

    }
    elseif (strlen($medicineName) > 25) {

        $errors['medicineName'] =
            "Medicine name cannot exceed 25 characters.";

    }
    elseif (
        !preg_match(
            "/^[A-Za-z0-9][A-Za-z0-9 .()&+'\/-]*$/",
            $medicineName
        )
    ) {

        $errors['medicineName'] =
            "Medicine name contains invalid characters.";

    }


    /* =====================================================
       CATEGORY VALIDATION
       ===================================================== */

    if ($category === "") {

        $errors['category'] =
            "Category is required.";

    }
    elseif (strlen($category) < 2) {

        $errors['category'] =
            "Category must contain at least 2 characters.";

    }
    elseif (strlen($category) > 25) {

        $errors['category'] =
            "Category cannot exceed 25 characters.";

    }
    elseif (
        !preg_match(
            "/^[A-Za-z][A-Za-z &\/-]*$/",
            $category
        )
    ) {

        $errors['category'] =
            "Category contains invalid characters.";

    }


    /* =====================================================
       SUPPLIER VALIDATION
       ===================================================== */

    if ($supplierID === "") {

        $errors['supplierID'] =
            "Please select a supplier.";

    }
    elseif (
        !ctype_digit($supplierID) ||
        (int)$supplierID <= 0
    ) {

        $errors['supplierID'] =
            "Please select a valid supplier.";

    }


    /* =====================================================
       BATCH NUMBER VALIDATION
       ===================================================== */

    if ($batchNo === "") {

        $errors['batchNo'] =
            "Batch number is required.";

    }
    elseif (strlen($batchNo) < 2) {

        $errors['batchNo'] =
            "Batch number must contain at least 2 characters.";

    }
    elseif (strlen($batchNo) > 25) {

        $errors['batchNo'] =
            "Batch number cannot exceed 25 characters.";

    }
    elseif (
        !preg_match(
            "/^[A-Za-z0-9][A-Za-z0-9-]*$/",
            $batchNo
        )
    ) {

        $errors['batchNo'] =
            "Batch number can contain only letters, numbers and hyphens.";

    }


    /* =====================================================
       PURCHASE PRICE VALIDATION
       ===================================================== */

    if ($purchasePrice === "") {

        $errors['purchasePrice'] =
            "Purchase price is required.";

    }
    elseif (
        !preg_match(
            "/^\d+(\.\d{1,2})?$/",
            $purchasePrice
        )
    ) {

        $errors['purchasePrice'] =
            "Purchase price must be a valid amount with maximum 2 decimal places.";

    }
    else {

        $purchasePriceValue =
            (float)$purchasePrice;

        if ($purchasePriceValue <= 0) {

            $errors['purchasePrice'] =
                "Purchase price must be greater than 0.";

        }
        elseif (
            $purchasePriceValue > 99999999.99
        ) {

            $errors['purchasePrice'] =
                "Purchase price exceeds the allowed limit.";

        }

    }


    /* =====================================================
       SELLING PRICE VALIDATION
       ===================================================== */

    if ($sellingPrice === "") {

        $errors['sellingPrice'] =
            "Selling price is required.";

    }
    elseif (
        !preg_match(
            "/^\d+(\.\d{1,2})?$/",
            $sellingPrice
        )
    ) {

        $errors['sellingPrice'] =
            "Selling price must be a valid amount with maximum 2 decimal places.";

    }
    else {

        $sellingPriceValue =
            (float)$sellingPrice;

        if ($sellingPriceValue <= 0) {

            $errors['sellingPrice'] =
                "Selling price must be greater than 0.";

        }
        elseif (
            $sellingPriceValue > 99999999.99
        ) {

            $errors['sellingPrice'] =
                "Selling price exceeds the allowed limit.";

        }

    }


    /* =====================================================
       PRICE BUSINESS RULE
       ===================================================== */

    if (
        !isset($errors['purchasePrice']) &&
        !isset($errors['sellingPrice']) &&
        $purchasePrice !== "" &&
        $sellingPrice !== ""
    ) {

        if (
            (float)$sellingPrice <
            (float)$purchasePrice
        ) {

            $errors['sellingPrice'] =
                "Selling price cannot be less than purchase price.";

        }

    }


    /* =====================================================
       EXPIRY DATE VALIDATION
       ===================================================== */

    if ($expiryDate === "") {

        $errors['expiryDate'] =
            "Expiry date is required.";

    }
    else {

        $dateObject =
            DateTime::createFromFormat(
                'Y-m-d',
                $expiryDate
            );

        $dateErrors =
            DateTime::getLastErrors();

        $dateIsValid = true;


        if ($dateObject === false) {

            $dateIsValid = false;

        }
        elseif (
            $dateErrors !== false &&
            (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )
        ) {

            $dateIsValid = false;

        }


        if (!$dateIsValid) {

            $errors['expiryDate'] =
                "Please enter a valid expiry date.";

        }
        else {

            $formattedDate =
                $dateObject->format('Y-m-d');

            if ($formattedDate !== $expiryDate) {

                $errors['expiryDate'] =
                    "Please enter a valid expiry date.";

            }
            else {

                $today =
                    new DateTime(
                        date("Y-m-d")
                    );

                $today->setTime(
                    0,
                    0,
                    0
                );

                $dateObject->setTime(
                    0,
                    0,
                    0
                );


                if ($dateObject <= $today) {

                    $errors['expiryDate'] =
                        "Expiry date must be a future date.";

                }

            }

        }

    }


    /* =====================================================
       DESCRIPTION VALIDATION
       ===================================================== */

    if ($description === "") {

        $errors['description'] =
            "Description is required.";

    }
    elseif (strlen($description) < 5) {

        $errors['description'] =
            "Description must contain at least 5 characters.";

    }
    elseif (strlen($description) > 500) {

        $errors['description'] =
            "Description cannot exceed 500 characters.";

    }
    elseif (
        preg_match(
            '/^[\s\W_]+$/u',
            $description
        )
    ) {

        $errors['description'] =
            "Description must contain meaningful information.";

    }


    /* =====================================================
       DATABASE VALIDATION
       ===================================================== */

    if (empty($errors)) {


        /* =================================================
           CHECK PHARMACIST EXISTS
           ================================================= */

        $pharmacistSQL = "
            SELECT PharmacistID
            FROM pharmacist
            WHERE PharmacistID = ?
            LIMIT 1
        ";

        $pharmacistStmt =
            mysqli_prepare(
                $conn,
                $pharmacistSQL
            );


        if ($pharmacistStmt) {

            mysqli_stmt_bind_param(
                $pharmacistStmt,
                "i",
                $pharmacistID
            );

            mysqli_stmt_execute(
                $pharmacistStmt
            );

            mysqli_stmt_store_result(
                $pharmacistStmt
            );


            if (
                mysqli_stmt_num_rows(
                    $pharmacistStmt
                ) === 0
            ) {

                $errors['general'] =
                    "Invalid pharmacist account.";

            }


            mysqli_stmt_close(
                $pharmacistStmt
            );

        }
        else {

            $errors['general'] =
                "Unable to validate pharmacist information.";

        }

    }


    /* =================================================
       CHECK SUPPLIER EXISTS
       ================================================= */

    if (
        empty($errors) &&
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

            $supplierIDInt =
                (int)$supplierID;


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

                $errors['supplierID'] =
                    "The selected supplier does not exist.";

            }


            mysqli_stmt_close(
                $supplierCheckStmt
            );

        }
        else {

            $errors['general'] =
                "Unable to validate supplier information.";

        }

    }


    /* =================================================
       CHECK EXISTING MEDICINE
       ================================================= */

    if (empty($errors)) {

        $checkMedicineSQL = "
            SELECT MedicineID
            FROM medicine
            WHERE LOWER(MedicineName) = LOWER(?)
            AND BatchNo = ?
            LIMIT 1
        ";

        $checkMedicineStmt =
            mysqli_prepare(
                $conn,
                $checkMedicineSQL
            );


        if ($checkMedicineStmt) {

            mysqli_stmt_bind_param(
                $checkMedicineStmt,
                "ss",
                $medicineName,
                $batchNo
            );

            mysqli_stmt_execute(
                $checkMedicineStmt
            );

            mysqli_stmt_store_result(
                $checkMedicineStmt
            );


            if (
                mysqli_stmt_num_rows(
                    $checkMedicineStmt
                ) > 0
            ) {

                $errors['general'] =
                    "This medicine with the same batch number already exists in the medicine inventory.";

            }


            mysqli_stmt_close(
                $checkMedicineStmt
            );

        }
        else {

            $errors['general'] =
                "Unable to check existing medicine records.";

        }

    }


    /* =================================================
       CHECK EXISTING PENDING REQUEST
       ================================================= */

    if (empty($errors)) {

        /*
         * IMPORTANT:
         * The primary key in medicine_request is RequestID.
         * It is NOT MedicineRequestID.
         */

        $pendingRequestSQL = "
            SELECT RequestID
            FROM medicine_request
            WHERE PharmacistID = ?
            AND LOWER(MedicineName) = LOWER(?)
            AND BatchNo = ?
            AND Status = 'Pending'
            LIMIT 1
        ";

        $pendingRequestStmt =
            mysqli_prepare(
                $conn,
                $pendingRequestSQL
            );


        if ($pendingRequestStmt) {

            mysqli_stmt_bind_param(
                $pendingRequestStmt,
                "iss",
                $pharmacistID,
                $medicineName,
                $batchNo
            );

            mysqli_stmt_execute(
                $pendingRequestStmt
            );

            mysqli_stmt_store_result(
                $pendingRequestStmt
            );


            if (
                mysqli_stmt_num_rows(
                    $pendingRequestStmt
                ) > 0
            ) {

                $errors['general'] =
                    "You already have a pending request for this medicine and batch number.";

            }


            mysqli_stmt_close(
                $pendingRequestStmt
            );

        }
        else {

            $errors['general'] =
                "Unable to check existing medicine requests.";

        }

    }


    /* =================================================
       INSERT REQUEST
       ================================================= */

    if (empty($errors)) {

        $insertSQL = "
            INSERT INTO medicine_request
            (
                PharmacistID,
                MedicineName,
                Category,
                SupplierID,
                BatchNo,
                PurchasePrice,
                SellingPrice,
                ExpiryDate,
                Description,
                RequestDate,
                Status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW(),
                'Pending'
            )
        ";


        $insertStmt =
            mysqli_prepare(
                $conn,
                $insertSQL
            );


        if ($insertStmt) {

            $supplierIDInt =
                (int)$supplierID;

            $purchasePriceFloat =
                (float)$purchasePrice;

            $sellingPriceFloat =
                (float)$sellingPrice;


            mysqli_stmt_bind_param(
                $insertStmt,
                "issisddss",
                $pharmacistID,
                $medicineName,
                $category,
                $supplierIDInt,
                $batchNo,
                $purchasePriceFloat,
                $sellingPriceFloat,
                $expiryDate,
                $description
            );


            if (
                mysqli_stmt_execute(
                    $insertStmt
                )
            ) {

                $successMessage =
                    "Medicine request submitted successfully. It is now waiting for Admin approval.";


                /* =====================================
                   CLEAR FORM
                   ===================================== */

                $medicineName = "";
                $category = "";
                $supplierID = "";
                $batchNo = "";
                $purchasePrice = "";
                $sellingPrice = "";
                $expiryDate = "";
                $description = "";

            }
            else {

                $errors['general'] =
                    "Unable to submit the medicine request. Please try again.";

            }


            mysqli_stmt_close(
                $insertStmt
            );

        }
        else {

            $errors['general'] =
                "Unable to prepare the medicine request.";

        }

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

<title>Request New Medicine</title>


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

    width: 650px;

    max-width: 95%;

    margin: 40px auto;

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px rgba(
            0,
            0,
            0,
            0.12
        );

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

    color: #333;

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

    transition:
        border-color 0.2s,
        box-shadow 0.2s;

}

input:focus,
select:focus,
textarea:focus {

    outline: none;

    border-color: #2980b9;

    box-shadow:
        0 0 0 2px rgba(
            41,
            128,
            185,
            0.12
        );

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

.submit-button:disabled {

    background: #6c757d;

    cursor: not-allowed;

}

.back-button {

    background: #555;

    color: white;

    margin-top: 10px;

}

.back-button:hover {

    background: #444;

}

.input-error {

    border-color: #c0392b !important;

    background-color: #fff8f8;

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


<h2>
    Request New Medicine
</h2>


<div class="subtitle">

    Provide complete medicine information
    for Admin review and approval.

</div>


<?php if (!empty($errors)) { ?>

<div class="error-box">

    <strong>
        Please correct the following:
    </strong>

    <ul>

        <?php

        foreach ($errors as $error) {

            echo "<li>" . e($error) . "</li>";

        }

        ?>

    </ul>

</div>

<?php } ?>


<?php if ($successMessage !== "") { ?>

<div class="success-box">

    <?php
    echo e($successMessage);
    ?>

</div>

<?php } ?>


<form
    method="POST"
    action=""
    id="medicineRequestForm"
    novalidate
>


<input
    type="hidden"
    name="csrf_token"
    value="<?php echo e($csrfToken); ?>"
>


<!-- MEDICINE NAME -->

<div class="form-group">

<label for="medicineName">

    Medicine Name
    <span class="required">*</span>

</label>

<input
    type="text"
    id="medicineName"
    name="medicineName"
    maxlength="25"
    minlength="2"
    pattern="[A-Za-z0-9][A-Za-z0-9 .()&+'/-]*"
    value="<?php echo e($medicineName); ?>"
    placeholder="Enter medicine name"
    autocomplete="off"
    required
>

<span class="help-text">
    Example: Paracetamol 500mg
</span>

<?php if (isset($errors['medicineName'])) { ?>

<span class="field-error">
    <?php echo e($errors['medicineName']); ?>
</span>

<?php } ?>

</div>


<!-- CATEGORY -->

<div class="form-group">

<label for="category">

    Category
    <span class="required">*</span>

</label>

<input
    type="text"
    id="category"
    name="category"
    maxlength="25"
    minlength="2"
    pattern="[A-Za-z][A-Za-z &/-]*"
    value="<?php echo e($category); ?>"
    placeholder="Example: Antibiotic"
    autocomplete="off"
    required
>

<?php if (isset($errors['category'])) { ?>

<span class="field-error">
    <?php echo e($errors['category']); ?>
</span>

<?php } ?>

</div>


<!-- SUPPLIER -->

<div class="form-group">

<label for="supplierID">

    Supplier
    <span class="required">*</span>

</label>

<select
    id="supplierID"
    name="supplierID"
    required
>

<option value="">
    Select Supplier
</option>

<?php

if ($supplierResult) {

    while (
        $supplier =
        mysqli_fetch_assoc(
            $supplierResult
        )
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


<?php if (isset($errors['supplierID'])) { ?>

<span class="field-error">
    <?php echo e($errors['supplierID']); ?>
</span>

<?php } ?>

</div>


<!-- BATCH NUMBER -->

<div class="form-group">

<label for="batchNo">

    Batch Number
    <span class="required">*</span>

</label>

<input
    type="text"
    id="batchNo"
    name="batchNo"
    maxlength="25"
    minlength="2"
    pattern="[A-Za-z0-9][A-Za-z0-9-]*"
    value="<?php echo e($batchNo); ?>"
    placeholder="Example: PCM500-A24"
    autocomplete="off"
    required
>

<span class="help-text">
    Use letters, numbers and hyphens only.
</span>

<?php if (isset($errors['batchNo'])) { ?>

<span class="field-error">
    <?php echo e($errors['batchNo']); ?>
</span>

<?php } ?>

</div>


<!-- PURCHASE PRICE -->

<div class="form-group">

<label for="purchasePrice">

    Purchase Price (₹)
    <span class="required">*</span>

</label>

<input
    type="number"
    id="purchasePrice"
    name="purchasePrice"
    min="0.01"
    max="99999999.99"
    step="0.01"
    value="<?php echo e($purchasePrice); ?>"
    placeholder="0.00"
    required
>

<?php if (isset($errors['purchasePrice'])) { ?>

<span class="field-error">
    <?php echo e($errors['purchasePrice']); ?>
</span>

<?php } ?>

</div>


<!-- SELLING PRICE -->

<div class="form-group">

<label for="sellingPrice">

    Selling Price (₹)
    <span class="required">*</span>

</label>

<input
    type="number"
    id="sellingPrice"
    name="sellingPrice"
    min="0.01"
    max="99999999.99"
    step="0.01"
    value="<?php echo e($sellingPrice); ?>"
    placeholder="0.00"
    required
>

<span class="help-text">
    Selling price must not be lower than purchase price.
</span>

<?php if (isset($errors['sellingPrice'])) { ?>

<span class="field-error">
    <?php echo e($errors['sellingPrice']); ?>
</span>

<?php } ?>

</div>


<!-- EXPIRY DATE -->

<div class="form-group">

<label for="expiryDate">

    Expiry Date
    <span class="required">*</span>

</label>

<input
    type="date"
    id="expiryDate"
    name="expiryDate"
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
    Expiry date must be a future date.
</span>

<?php if (isset($errors['expiryDate'])) { ?>

<span class="field-error">
    <?php echo e($errors['expiryDate']); ?>
</span>

<?php } ?>

</div>


<!-- DESCRIPTION -->

<div class="form-group">

<label for="description">

    Description
    <span class="required">*</span>

</label>

<textarea
    id="description"
    name="description"
    maxlength="500"
    minlength="5"
    placeholder="Enter medicine details, dosage information, usage or other relevant information..."
    required
><?php echo e($description); ?></textarea>

<span class="help-text">
    Maximum 500 characters.
</span>

<?php if (isset($errors['description'])) { ?>

<span class="field-error">
    <?php echo e($errors['description']); ?>
</span>

<?php } ?>

</div>


<!-- SUBMIT -->

<button
    type="submit"
    name="submit_request"
    class="submit-button"
    id="submitButton"
>

    Send Request to Admin

</button>


<!-- BACK -->

<a
    href="pharmacist_dashboard.php"
    class="back-button"
>

    Back to Dashboard

</a>


</form>

</div>


<script>

document
    .getElementById("medicineRequestForm")
    .addEventListener(
        "submit",
        function(event) {

            const medicineName =
                document
                    .getElementById("medicineName")
                    .value
                    .trim();

            const category =
                document
                    .getElementById("category")
                    .value
                    .trim();

            const supplierID =
                document
                    .getElementById("supplierID")
                    .value;

            const batchNo =
                document
                    .getElementById("batchNo")
                    .value
                    .trim();

            const purchasePrice =
                document
                    .getElementById("purchasePrice")
                    .value
                    .trim();

            const sellingPrice =
                document
                    .getElementById("sellingPrice")
                    .value
                    .trim();

            const expiryDate =
                document
                    .getElementById("expiryDate")
                    .value;

            const description =
                document
                    .getElementById("description")
                    .value
                    .trim();


            /* =================================
               MEDICINE NAME
               ================================= */

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


            /* =================================
               CATEGORY
               ================================= */

            if (
                category.length < 2 ||
                category.length > 25 ||
                !/^[A-Za-z][A-Za-z &\/-]*$/
                    .test(category)
            ) {

                alert(
                    "Please enter a valid medicine category."
                );

                event.preventDefault();

                return;

            }


            /* =================================
               SUPPLIER
               ================================= */

            if (
                !/^\d+$/.test(supplierID) ||
                parseInt(supplierID) <= 0
            ) {

                alert(
                    "Please select a valid supplier."
                );

                event.preventDefault();

                return;

            }


            /* =================================
               BATCH
               ================================= */

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


            /* =================================
               PURCHASE PRICE
               ================================= */

            if (
                !/^\d+(\.\d{1,2})?$/
                    .test(purchasePrice)
            ) {

                alert(
                    "Purchase price must be a valid amount with maximum 2 decimal places."
                );

                event.preventDefault();

                return;

            }


            const purchase =
                parseFloat(purchasePrice);


            if (
                purchase <= 0 ||
                purchase > 99999999.99
            ) {

                alert(
                    "Please enter a valid purchase price."
                );

                event.preventDefault();

                return;

            }


            /* =================================
               SELLING PRICE
               ================================= */

            if (
                !/^\d+(\.\d{1,2})?$/
                    .test(sellingPrice)
            ) {

                alert(
                    "Selling price must be a valid amount with maximum 2 decimal places."
                );

                event.preventDefault();

                return;

            }


            const selling =
                parseFloat(sellingPrice);


            if (
                selling <= 0 ||
                selling > 99999999.99
            ) {

                alert(
                    "Please enter a valid selling price."
                );

                event.preventDefault();

                return;

            }


            /* =================================
               PRICE COMPARISON
               ================================= */

            if (selling < purchase) {

                alert(
                    "Selling price cannot be less than purchase price."
                );

                event.preventDefault();

                return;

            }


            /* =================================
               EXPIRY DATE
               ================================= */

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
                0,
                0,
                0,
                0
            );


            const selectedDate =
                new Date(
                    expiryDate +
                    "T00:00:00"
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


            /* =================================
               DESCRIPTION
               ================================= */

            if (
                description.length < 5 ||
                description.length > 500 ||
                /^[\s\W_]+$/u.test(description)
            ) {

                alert(
                    "Please enter a meaningful description between 5 and 500 characters."
                );

                event.preventDefault();

                return;

            }


            /* =================================
               PREVENT DOUBLE SUBMISSION
               ================================= */

            const submitButton =
                document.getElementById(
                    "submitButton"
                );

            submitButton.disabled = true;

            submitButton.textContent =
                "Submitting...";

        }
    );

</script>


</body>

</html>
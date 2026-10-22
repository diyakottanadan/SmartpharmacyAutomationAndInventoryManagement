<?php

session_start();
include("db_connect.php");


/* =========================================
   CHECK ADMIN LOGIN
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
   VARIABLES
   ========================================= */

$row = null;

$message = "";
$messageType = "";

$errors = [];


/* =========================================
   FORM VARIABLES
   ========================================= */

$medicineID = "";
$medicineName = "";
$category = "";
$stock = "";
$expiryDate = "";


/* =========================================
   GET MEDICINE ID
   ========================================= */

if (isset($_GET['id'])) {

    $medicineID = trim($_GET['id']);

}


/* =========================================
   VALIDATE MEDICINE ID
   ========================================= */

if ($medicineID === "") {

    $message = "No medicine was selected.";
    $messageType = "error";

}

elseif (
    !ctype_digit($medicineID) ||
    (int)$medicineID <= 0
) {

    $message = "Invalid medicine ID.";
    $messageType = "error";

}

else {

    $medicineIDInt = (int)$medicineID;


    /* =====================================
       GET MEDICINE DETAILS
       ===================================== */

    $sql = "
        SELECT
            m.*,
            s.SupplierName
        FROM medicine m
        LEFT JOIN supplier s
            ON m.SupplierID = s.SupplierID
        WHERE m.MedicineID = ?
        LIMIT 1
    ";


    $stmt = mysqli_prepare($conn, $sql);


    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $medicineIDInt
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);


        if (
            $result &&
            mysqli_num_rows($result) > 0
        ) {

            $row = mysqli_fetch_assoc($result);


            /* =============================
               INITIAL FORM VALUES
               ============================= */

            $medicineName = $row['MedicineName'];

            $category = $row['Category'];

            $stock = $row['StockQty'];

            $expiryDate = $row['ExpiryDate'];

        }

        else {

            $message = "Medicine not found.";
            $messageType = "error";

        }


        mysqli_stmt_close($stmt);

    }

    else {

        $message = "Unable to load medicine details.";
        $messageType = "error";

    }

}


/* =========================================
   UPDATE MEDICINE
   ========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['update'])
) {


    /* =====================================
       CHECK CSRF TOKEN
       ===================================== */

    $submittedToken = $_POST['csrf_token'] ?? "";


    if (
        empty($submittedToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        )
    ) {

        $errors['general'] =
            "Invalid form submission. Please refresh the page and try again.";

    }


    /* =====================================
       GET POST VALUES
       ===================================== */

    $medicineID =
        trim($_POST['medicineID'] ?? "");

    $medicineName =
        trim($_POST['medicineName'] ?? "");

    $category =
        trim($_POST['category'] ?? "");

    $stock =
        trim($_POST['stock'] ?? "");

    $expiryDate =
        trim($_POST['expiryDate'] ?? "");


    /* =====================================
       MEDICINE ID VALIDATION
       ===================================== */

    if ($medicineID === "") {

        $errors['medicineID'] =
            "Medicine ID is required.";

    }

    elseif (
        !ctype_digit($medicineID) ||
        (int)$medicineID <= 0
    ) {

        $errors['medicineID'] =
            "Invalid medicine ID.";

    }


    /* =====================================
       MEDICINE NAME VALIDATION
       ===================================== */

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


    /* =====================================
       CATEGORY VALIDATION
       ===================================== */

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


    /* =====================================
       STOCK VALIDATION
       ===================================== */

    if ($stock === "") {

        $errors['stock'] =
            "Stock quantity is required.";

    }

    elseif (!ctype_digit($stock)) {

        $errors['stock'] =
            "Stock quantity must be a whole number.";

    }

    elseif ((int)$stock < 0) {

        $errors['stock'] =
            "Stock quantity cannot be negative.";

    }

    elseif ((int)$stock > 100000) {

        $errors['stock'] =
            "Stock quantity is too large.";

    }


    /* =====================================
       EXPIRY DATE VALIDATION
       ===================================== */

    if ($expiryDate === "") {

        $errors['expiryDate'] =
            "Expiry date is required.";

    }

    else {

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

            $errors['expiryDate'] =
                "Please enter a valid expiry date.";

        }

        else {

            $today = new DateTime();

            $today->setTime(0, 0, 0);

            $dateObject->setTime(0, 0, 0);


            if ($dateObject <= $today) {

                $errors['expiryDate'] =
                    "Expiry date must be a future date.";

            }

        }

    }


    /* =====================================
       CHECK MEDICINE STILL EXISTS
       ===================================== */

    if (!isset($errors['medicineID'])) {

        $medicineIDInt = (int)$medicineID;


        $checkSQL = "
            SELECT MedicineID
            FROM medicine
            WHERE MedicineID = ?
            LIMIT 1
        ";


        $checkStmt =
            mysqli_prepare(
                $conn,
                $checkSQL
            );


        if ($checkStmt) {

            mysqli_stmt_bind_param(
                $checkStmt,
                "i",
                $medicineIDInt
            );

            mysqli_stmt_execute(
                $checkStmt
            );

            mysqli_stmt_store_result(
                $checkStmt
            );


            if (
                mysqli_stmt_num_rows(
                    $checkStmt
                ) === 0
            ) {

                $errors['medicineID'] =
                    "The selected medicine no longer exists.";

            }


            mysqli_stmt_close($checkStmt);

        }

        else {

            $errors['general'] =
                "Unable to verify the medicine.";

        }

    }


    /* =====================================
       UPDATE DATABASE
       ===================================== */

    if (empty($errors)) {

        $medicineIDInt = (int)$medicineID;

        $stockInt = (int)$stock;


        /* =================================
           AUTOMATIC STATUS
           ================================= */

        if ($stockInt > 0) {

            $status = "Available";

        }

        else {

            $status = "Unavailable";

        }


        /* =================================
           UPDATE QUERY
           ================================= */

        $updateSQL = "
            UPDATE medicine
            SET
                MedicineName = ?,
                Category = ?,
                StockQty = ?,
                ExpiryDate = ?,
                Status = ?
            WHERE MedicineID = ?
        ";


        $updateStmt =
            mysqli_prepare(
                $conn,
                $updateSQL
            );


        if ($updateStmt) {

            mysqli_stmt_bind_param(
                $updateStmt,
                "ssissi",
                $medicineName,
                $category,
                $stockInt,
                $expiryDate,
                $status,
                $medicineIDInt
            );


            if (
                mysqli_stmt_execute(
                    $updateStmt
                )
            ) {

                $message =
                    "Medicine updated successfully.";

                $messageType =
                    "success";

            }

            else {

                $errors['general'] =
                    "Unable to update the medicine. Please try again.";

            }


            mysqli_stmt_close($updateStmt);

        }

        else {

            $errors['general'] =
                "Unable to prepare the update request.";

        }


        /* =================================
           LOAD UPDATED DATA
           ================================= */

        if (empty($errors)) {

            $selectSQL = "
                SELECT
                    m.*,
                    s.SupplierName
                FROM medicine m
                LEFT JOIN supplier s
                    ON m.SupplierID = s.SupplierID
                WHERE m.MedicineID = ?
                LIMIT 1
            ";


            $selectStmt =
                mysqli_prepare(
                    $conn,
                    $selectSQL
                );


            if ($selectStmt) {

                mysqli_stmt_bind_param(
                    $selectStmt,
                    "i",
                    $medicineIDInt
                );

                mysqli_stmt_execute($selectStmt);

                $result =
                    mysqli_stmt_get_result(
                        $selectStmt
                    );


                if ($result) {

                    $row =
                        mysqli_fetch_assoc(
                            $result
                        );

                }


                mysqli_stmt_close(
                    $selectStmt
                );

            }

        }

    }

    else {

        $message =
            "Please correct the errors below.";

        $messageType =
            "error";

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

    <title>Edit Medicine</title>


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


        input {

            width: 100%;

            padding: 11px 12px;

            border:
                1px solid #ccc;

            border-radius: 6px;

            font-size: 15px;

        }


        input:focus {

            outline: none;

            border-color: #2980b9;

            box-shadow:
                0 0 0 2px
                rgba(41, 128, 185, 0.12);

        }


        .readonly {

            background: #eeeeee;

            color: #555;

            cursor: not-allowed;

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


        .info-box {

            background: #f1f5f9;

            border-left: 4px solid #2980b9;

            padding: 14px;

            margin-bottom: 22px;

            border-radius: 4px;

            font-size: 14px;

            line-height: 1.6;

        }


        button,
        .back-btn {

            display: block;

            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 6px;

            font-size: 16px;

            text-align: center;

            text-decoration: none;

            cursor: pointer;

        }


        .update-btn {

            background: #198754;

            color: white;

            margin-top: 25px;

        }


        .update-btn:hover {

            background: #157347;

        }


        .back-btn {

            background: #555;

            color: white;

            margin-top: 10px;

        }


        .back-btn:hover {

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


    <h2>
        Edit Medicine
    </h2>


    <div class="subtitle">
        Update the editable inventory information carefully.
    </div>


    <?php if (!empty($errors)) { ?>

        <div class="error-box">

            <strong>
                Please correct the following:
            </strong>

            <ul>

                <?php foreach ($errors as $error) { ?>

                    <li>
                        <?php echo htmlspecialchars($error); ?>
                    </li>

                <?php } ?>

            </ul>

        </div>

    <?php } ?>


    <?php if (
        $message !== "" &&
        $messageType === "success"
    ) { ?>

        <div class="success-box">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php } ?>


    <?php if ($row !== null) { ?>


        <div class="info-box">

            <strong>
                Inventory Information
            </strong>

            <br>

            Medicine ID, supplier, batch number,
            purchase price and selling price are
            protected from modification on this page.

        </div>


        <form
            method="POST"
            action=""
            id="editMedicineForm"
            novalidate
        >


            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>"
            >


            <!-- MEDICINE ID -->

            <div class="form-group">

                <label>
                    Medicine ID
                </label>

                <input
                    type="text"
                    name="medicineID"
                    value="<?php echo htmlspecialchars($row['MedicineID']); ?>"
                    readonly
                    class="readonly"
                >

            </div>


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
                    minlength="2"
                    maxlength="25"
                    value="<?php echo htmlspecialchars($medicineName); ?>"
                    required
                >

                <span class="help-text">
                    2–25 characters.
                </span>

                <?php if (isset($errors['medicineName'])) { ?>

                    <span class="field-error">
                        <?php echo htmlspecialchars($errors['medicineName']); ?>
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
                    minlength="2"
                    maxlength="25"
                    value="<?php echo htmlspecialchars($category); ?>"
                    required
                >

                <?php if (isset($errors['category'])) { ?>

                    <span class="field-error">
                        <?php echo htmlspecialchars($errors['category']); ?>
                    </span>

                <?php } ?>

            </div>


            <!-- SUPPLIER -->

            <div class="form-group">

                <label>
                    Supplier
                </label>

                <input
                    type="text"
                    value="<?php
                        echo htmlspecialchars(
                            $row['SupplierName']
                            ?? 'Supplier not available'
                        );
                    ?>"
                    readonly
                    class="readonly"
                >

            </div>


            <!-- BATCH -->

            <div class="form-group">

                <label>
                    Batch Number
                </label>

                <input
                    type="text"
                    value="<?php echo htmlspecialchars($row['BatchNo']); ?>"
                    readonly
                    class="readonly"
                >

            </div>


            <!-- PURCHASE PRICE -->

            <div class="form-group">

                <label>
                    Purchase Price (₹)
                </label>

                <input
                    type="text"
                    value="<?php
                        echo number_format(
                            (float)$row['PurchasePrice'],
                            2
                        );
                    ?>"
                    readonly
                    class="readonly"
                >

            </div>


            <!-- SELLING PRICE -->

            <div class="form-group">

                <label>
                    Selling Price (₹)
                </label>

                <input
                    type="text"
                    value="<?php
                        echo number_format(
                            (float)$row['SellingPrice'],
                            2
                        );
                    ?>"
                    readonly
                    class="readonly"
                >

            </div>


            <!-- STOCK -->

            <div class="form-group">

                <label for="stock">

                    Stock Quantity
                    <span class="required">*</span>

                </label>

                <input
                    type="number"
                    id="stock"
                    name="stock"
                    min="0"
                    max="100000"
                    step="1"
                    value="<?php echo htmlspecialchars($stock); ?>"
                    required
                >

                <span class="help-text">
                    Enter a whole number from 0 to 100,000.
                    Status is automatically updated according to stock quantity.
                </span>

                <?php if (isset($errors['stock'])) { ?>

                    <span class="field-error">
                        <?php echo htmlspecialchars($errors['stock']); ?>
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
                    value="<?php echo htmlspecialchars($expiryDate); ?>"
                    required
                >

                <span class="help-text">
                    Expiry date must be in the future.
                </span>

                <?php if (isset($errors['expiryDate'])) { ?>

                    <span class="field-error">
                        <?php echo htmlspecialchars($errors['expiryDate']); ?>
                    </span>

                <?php } ?>

            </div>


            <!-- CURRENT STATUS -->

            <div class="form-group">

                <label>
                    Current Status
                </label>

                <input
                    type="text"
                    value="<?php echo htmlspecialchars($row['Status']); ?>"
                    readonly
                    class="readonly"
                >

                <span class="help-text">
                    Status automatically becomes Available when
                    stock is greater than 0 and Unavailable when stock is 0.
                </span>

            </div>


            <!-- UPDATE -->

            <button
                type="submit"
                name="update"
                class="update-btn"
            >
                Update Medicine
            </button>


        </form>


    <?php } ?>


    <a
        href="view_medicine.php"
        class="back-btn"
    >
        Back to Medicine List
    </a>


</div>


<script>

/* =========================================
   CLIENT-SIDE VALIDATION
   ========================================= */

document
    .getElementById("editMedicineForm")
    ?.addEventListener(
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


            const stock =
                document
                    .getElementById("stock")
                    .value;


            const expiryDate =
                document
                    .getElementById("expiryDate")
                    .value;


            /* =============================
               MEDICINE NAME
               ============================= */

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


            /* =============================
               CATEGORY
               ============================= */

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


            /* =============================
               STOCK
               ============================= */

            if (
                stock === "" ||
                !/^\d+$/.test(stock)
            ) {

                alert(
                    "Stock quantity must be a valid whole number."
                );

                event.preventDefault();

                return;
            }


            const stockNumber =
                Number(stock);


            if (
                stockNumber < 0 ||
                stockNumber > 100000
            ) {

                alert(
                    "Stock quantity must be between 0 and 100,000."
                );

                event.preventDefault();

                return;
            }


            /* =============================
               EXPIRY DATE
               ============================= */

            if (expiryDate === "") {

                alert(
                    "Please select an expiry date."
                );

                event.preventDefault();

                return;
            }


            const today =
                new Date();

            today.setHours(0, 0, 0, 0);


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

        }
    );

</script>


</body>

</html>
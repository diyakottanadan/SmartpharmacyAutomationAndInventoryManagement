<?php

session_start();

include("db_connect.php");


/* =================================================
   LOGIN CHECK
   ================================================= */

if (
    !isset($_SESSION['admin']) &&
    !isset($_SESSION['pharmacist'])
) {
    header("Location: login.php");
    exit();
}


/* =================================================
   CSRF TOKEN
   ================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


/* =================================================
   SAFE OUTPUT FUNCTION
   ================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =================================================
   MESSAGE
   ================================================= */

$message = "";
$messageType = "";


/* =================================================
   FORM VALUES
   ================================================= */

$purchaseID = "";
$medicineID = "";
$quantity = "";
$purchasePrice = "";


/* =================================================
   GET PURCHASES
   SHOW USEFUL BILL INFORMATION
   ================================================= */

$purchaseQuery = mysqli_query(
    $conn,
    "
    SELECT
        p.Purchase_Id,
        p.PurchaseDate,
        p.TotalAmount,
        p.Status,
        s.SupplierName
    FROM purchase p
    LEFT JOIN supplier s
        ON p.SupplierID = s.SupplierID
    ORDER BY p.Purchase_Id DESC
    "
);

if (!$purchaseQuery) {

    $message =
        "Unable to load purchases: " .
        $conn->error;

    $messageType = "error";
}


/* =================================================
   GET MEDICINES
   ================================================= */

$medicineQuery = mysqli_query(
    $conn,
    "
    SELECT
        MedicineID,
        MedicineName,
        PurchasePrice,
        StockQty
    FROM medicine
    ORDER BY MedicineName ASC
    "
);

if (!$medicineQuery) {

    $message =
        "Unable to load medicines: " .
        $conn->error;

    $messageType = "error";
}


/* =================================================
   ADD PURCHASE ITEM
   ================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =================================================
       KEEP SUBMITTED VALUES
       ================================================= */

    $purchaseID =
        trim(
            (string)($_POST['PurchaseID'] ?? '')
        );

    $medicineID =
        trim(
            (string)($_POST['MedicineID'] ?? '')
        );

    $quantity =
        trim(
            (string)($_POST['Quantity'] ?? '')
        );

    $purchasePrice =
        trim(
            (string)($_POST['PurchasePrice'] ?? '')
        );


    /* =================================================
       CSRF VALIDATION
       ================================================= */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            (string)$_POST['csrf_token']
        )
    ) {

        $message =
            "Invalid form submission. Please try again.";

        $messageType = "error";

    } else {


        /* =================================================
           PURCHASE VALIDATION
           ================================================= */

        if (
            $purchaseID === "" ||
            !ctype_digit($purchaseID) ||
            (int)$purchaseID <= 0
        ) {

            $message =
                "Please select a valid purchase.";

            $messageType = "error";

        }


        /* =================================================
           MEDICINE VALIDATION
           ================================================= */

        elseif (
            $medicineID === "" ||
            !ctype_digit($medicineID) ||
            (int)$medicineID <= 0
        ) {

            $message =
                "Please select a valid medicine.";

            $messageType = "error";

        }


        /* =================================================
           QUANTITY VALIDATION
           ================================================= */

        elseif (
            $quantity === "" ||
            !ctype_digit($quantity) ||
            (int)$quantity <= 0
        ) {

            $message =
                "Quantity must be a positive whole number.";

            $messageType = "error";

        }

        elseif ((int)$quantity > 100000) {

            $message =
                "Quantity cannot exceed 100000.";

            $messageType = "error";

        }


        /* =================================================
           PURCHASE PRICE VALIDATION
           ================================================= */

        elseif (
            $purchasePrice === "" ||
            !preg_match(
                '/^\d+(\.\d{1,2})?$/',
                $purchasePrice
            )
        ) {

            $message =
                "Purchase price must be a valid amount with maximum 2 decimal places.";

            $messageType = "error";

        }

        else {


            /* =================================================
               CONVERT VALUES
               ================================================= */

            $purchaseID =
                (int)$purchaseID;

            $medicineID =
                (int)$medicineID;

            $quantity =
                (int)$quantity;

            $purchasePrice =
                (float)$purchasePrice;


            /* =================================================
               PRICE RANGE
               ================================================= */

            if ($purchasePrice <= 0) {

                $message =
                    "Purchase price must be greater than 0.";

                $messageType = "error";

            }

            elseif ($purchasePrice > 99999999.99) {

                $message =
                    "Purchase price is too large.";

                $messageType = "error";

            }

            else {


                /* =================================================
                   CALCULATE SUBTOTAL
                   ================================================= */

                $subTotal =
                    round(
                        $quantity * $purchasePrice,
                        2
                    );


                if ($subTotal > 9999999999.99) {

                    $message =
                        "The calculated subtotal is too large.";

                    $messageType = "error";

                }

                else {


                    /* =================================================
                       START TRANSACTION
                       ================================================= */

                    mysqli_begin_transaction($conn);


                    try {


                        /* =================================================
                           1. CHECK PURCHASE
                           ================================================= */

                        $checkPurchase =
                            $conn->prepare(
                                "
                                SELECT
                                    Purchase_Id
                                FROM purchase
                                WHERE Purchase_Id = ?
                                LIMIT 1
                                "
                            );


                        if (!$checkPurchase) {

                            throw new Exception(
                                "Unable to verify purchase: " .
                                $conn->error
                            );
                        }


                        $checkPurchase->bind_param(
                            "i",
                            $purchaseID
                        );


                        if (
                            !$checkPurchase->execute()
                        ) {

                            throw new Exception(
                                "Unable to verify purchase: " .
                                $checkPurchase->error
                            );
                        }


                        $checkPurchase->store_result();


                        if (
                            $checkPurchase->num_rows === 0
                        ) {

                            $checkPurchase->close();

                            throw new Exception(
                                "Selected purchase does not exist."
                            );
                        }


                        $checkPurchase->close();


                        /* =================================================
                           2. CHECK MEDICINE
                           ================================================= */

                        $checkMedicine =
                            $conn->prepare(
                                "
                                SELECT
                                    MedicineID
                                FROM medicine
                                WHERE MedicineID = ?
                                LIMIT 1
                                "
                            );


                        if (!$checkMedicine) {

                            throw new Exception(
                                "Unable to verify medicine: " .
                                $conn->error
                            );
                        }


                        $checkMedicine->bind_param(
                            "i",
                            $medicineID
                        );


                        if (
                            !$checkMedicine->execute()
                        ) {

                            throw new Exception(
                                "Unable to verify medicine: " .
                                $checkMedicine->error
                            );
                        }


                        $checkMedicine->store_result();


                        if (
                            $checkMedicine->num_rows === 0
                        ) {

                            $checkMedicine->close();

                            throw new Exception(
                                "Selected medicine does not exist."
                            );
                        }


                        $checkMedicine->close();


                        /* =================================================
                           3. CHECK DUPLICATE ITEM
                           ================================================= */

                        $duplicateCheck =
                            $conn->prepare(
                                "
                                SELECT
                                    PurchaseID
                                FROM purchase_items
                                WHERE PurchaseID = ?
                                AND MedicineID = ?
                                LIMIT 1
                                "
                            );


                        if (!$duplicateCheck) {

                            throw new Exception(
                                "Unable to check duplicate purchase item: " .
                                $conn->error
                            );
                        }


                        $duplicateCheck->bind_param(
                            "ii",
                            $purchaseID,
                            $medicineID
                        );


                        if (
                            !$duplicateCheck->execute()
                        ) {

                            throw new Exception(
                                "Unable to check duplicate purchase item: " .
                                $duplicateCheck->error
                            );
                        }


                        $duplicateCheck->store_result();


                        if (
                            $duplicateCheck->num_rows > 0
                        ) {

                            $duplicateCheck->close();

                            throw new Exception(
                                "This medicine is already added to the selected purchase."
                            );
                        }


                        $duplicateCheck->close();


                        /* =================================================
                           4. INSERT PURCHASE ITEM
                           ================================================= */

                        $insertItem =
                            $conn->prepare(
                                "
                                INSERT INTO purchase_items
                                (
                                    PurchaseID,
                                    MedicineID,
                                    Quantity,
                                    PurchasePrice,
                                    SubTotal
                                )
                                VALUES
                                (?, ?, ?, ?, ?)
                                "
                            );


                        if (!$insertItem) {

                            throw new Exception(
                                "Unable to prepare purchase item: " .
                                $conn->error
                            );
                        }


                        $insertItem->bind_param(
                            "iiidd",
                            $purchaseID,
                            $medicineID,
                            $quantity,
                            $purchasePrice,
                            $subTotal
                        );


                        if (
                            !$insertItem->execute()
                        ) {

                            throw new Exception(
                                "Unable to add purchase item: " .
                                $insertItem->error
                            );
                        }


                        $insertItem->close();


                        /* =================================================
                           5. INCREASE MEDICINE STOCK
                           ================================================= */

                        $updateStock =
                            $conn->prepare(
                                "
                                UPDATE medicine
                                SET
                                    StockQty =
                                        StockQty + ?,
                                    Status =
                                        CASE
                                            WHEN StockQty + ? > 0
                                            THEN 'Available'
                                            ELSE 'Unavailable'
                                        END
                                WHERE MedicineID = ?
                                "
                            );


                        if (!$updateStock) {

                            throw new Exception(
                                "Unable to prepare stock update: " .
                                $conn->error
                            );
                        }


                        $updateStock->bind_param(
                            "iii",
                            $quantity,
                            $quantity,
                            $medicineID
                        );


                        if (
                            !$updateStock->execute()
                        ) {

                            throw new Exception(
                                "Unable to update medicine stock: " .
                                $updateStock->error
                            );
                        }


                        if (
                            $updateStock->affected_rows === 0
                        ) {

                            throw new Exception(
                                "Medicine stock could not be updated."
                            );
                        }


                        $updateStock->close();


                        /* =================================================
                           6. UPDATE PURCHASE TOTAL
                           ================================================= */

                        $updateTotal =
                            $conn->prepare(
                                "
                                UPDATE purchase
                                SET
                                    TotalAmount =
                                        TotalAmount + ?
                                WHERE Purchase_Id = ?
                                "
                            );


                        if (!$updateTotal) {

                            throw new Exception(
                                "Unable to prepare purchase total update: " .
                                $conn->error
                            );
                        }


                        $updateTotal->bind_param(
                            "di",
                            $subTotal,
                            $purchaseID
                        );


                        if (
                            !$updateTotal->execute()
                        ) {

                            throw new Exception(
                                "Unable to update purchase total: " .
                                $updateTotal->error
                            );
                        }


                        $updateTotal->close();


                        /* =================================================
                           7. COMMIT
                           ================================================= */

                        if (
                            !mysqli_commit($conn)
                        ) {

                            throw new Exception(
                                "Purchase item could not be saved: " .
                                $conn->error
                            );
                        }


                        /* =================================================
                           SUCCESS
                           ================================================= */

                        $message =
                            "Purchase item added successfully. "
                            . "Medicine stock and purchase total have been updated.";

                        $messageType = "success";


                        /* Clear form */

                        $purchaseID = "";
                        $medicineID = "";
                        $quantity = "";
                        $purchasePrice = "";

                    }

                    catch (Throwable $e) {

                        mysqli_rollback($conn);

                        $message =
                            $e->getMessage();

                        $messageType = "error";
                    }
                }
            }
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

    <title>Add Purchase Item</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }


        .container {
            width: 550px;
            max-width: 95%;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }


        h2 {
            text-align: center;
            margin-bottom: 25px;
        }


        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }


        select,
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }


        select:focus,
        input:focus {
            outline: none;
            border-color: #198754;
        }


        button {
            width: 100%;
            padding: 12px;
            margin-top: 25px;
            background-color: green;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }


        button:hover {
            opacity: 0.85;
        }


        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }


        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }


        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }


        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }


        .back {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }


        .back:hover {
            opacity: 0.85;
        }


        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }


    </style>

</head>


<body>


<div class="container">


    <h2>
        Add Purchase Item
    </h2>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo e($messageType); ?>">

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        id="purchaseItemForm"
        onsubmit="return validateForm()"
    >


        <!-- =================================================
             CSRF
             ================================================= -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrfToken); ?>"
        >


        <!-- =================================================
             PURCHASE
             ================================================= -->

        <label for="purchaseID">
            Purchase / Bill
        </label>


        <select
            name="PurchaseID"
            id="purchaseID"
            required
        >

            <option value="">
                Select Purchase / Bill
            </option>


            <?php if ($purchaseQuery): ?>

                <?php while (
                    $purchase =
                        mysqli_fetch_assoc(
                            $purchaseQuery
                        )
                ): ?>


                    <?php

                    $formattedDate =
                        date(
                            'd-m-Y',
                            strtotime(
                                $purchase['PurchaseDate']
                            )
                        );


                    $supplierName =
                        $purchase['SupplierName']
                        ?: "Unknown Supplier";


                    $totalAmount =
                        number_format(
                            (float)$purchase['TotalAmount'],
                            2
                        );


                    $selected =
                        (
                            (string)$purchaseID ===
                            (string)$purchase['Purchase_Id']
                        )
                        ? "selected"
                        : "";

                    ?>


                    <option
                        value="<?php
                            echo e(
                                $purchase['Purchase_Id']
                            );
                        ?>"
                        <?php echo $selected; ?>
                    >

                        <?php
                        echo e(
                            "Purchase #" .
                            $purchase['Purchase_Id'] .
                            " — " .
                            $supplierName .
                            " — " .
                            $formattedDate .
                            " — ₹" .
                            $totalAmount .
                            " — " .
                            $purchase['Status']
                        );
                        ?>

                    </option>


                <?php endwhile; ?>

            <?php endif; ?>


        </select>


        <div class="hint">
            Select the bill to which you want to add an extra medicine.
        </div>


        <!-- =================================================
             MEDICINE
             ================================================= -->

        <label for="medicine">
            Medicine
        </label>


        <select
            name="MedicineID"
            id="medicine"
            required
            onchange="setPrice()"
        >

            <option value="">
                Select Medicine
            </option>


            <?php if ($medicineQuery): ?>

                <?php while (
                    $medicine =
                        mysqli_fetch_assoc(
                            $medicineQuery
                        )
                ): ?>


                    <?php

                    $selected =
                        (
                            (string)$medicineID ===
                            (string)$medicine['MedicineID']
                        )
                        ? "selected"
                        : "";

                    ?>


                    <option
                        value="<?php
                            echo e(
                                $medicine['MedicineID']
                            );
                        ?>"
                        data-price="<?php
                            echo e(
                                $medicine['PurchasePrice']
                            );
                        ?>"
                        <?php echo $selected; ?>
                    >

                        <?php
                        echo e(
                            $medicine['MedicineName']
                        );
                        ?>

                        (Current Stock:
                        <?php
                        echo e(
                            $medicine['StockQty']
                        );
                        ?>)

                    </option>


                <?php endwhile; ?>

            <?php endif; ?>


        </select>


        <!-- =================================================
             QUANTITY
             ================================================= -->

        <label for="quantity">
            Quantity
        </label>


        <input
            type="number"
            name="Quantity"
            id="quantity"
            min="1"
            max="100000"
            step="1"
            value="<?php echo e($quantity); ?>"
            required
        >


        <div class="hint">
            Enter a positive whole number.
        </div>


        <!-- =================================================
             PURCHASE PRICE
             ================================================= -->

        <label for="purchasePrice">
            Purchase Price
        </label>


        <input
            type="number"
            name="PurchasePrice"
            id="purchasePrice"
            min="0.01"
            max="99999999.99"
            step="0.01"
            value="<?php echo e($purchasePrice); ?>"
            required
        >


        <div class="hint">
            Maximum 2 decimal places.
        </div>


        <!-- =================================================
             SUBMIT
             ================================================= -->

        <button
            type="submit"
            name="add_item"
            id="submitButton"
        >

            Add Purchase Item

        </button>


    </form>


    <a
        href="<?php
            if (isset($_SESSION['admin'])) {
                echo "admin_dashboard.php";
            } else {
                echo "pharmacist_dashboard.php";
            }
        ?>"
        class="back"
    >

        Back to Dashboard

    </a>


</div>


<script>


/* =================================================
   AUTOMATICALLY SET PURCHASE PRICE
   ================================================= */

function setPrice() {

    const medicine =
        document.getElementById(
            "medicine"
        );


    const selected =
        medicine.options[
            medicine.selectedIndex
        ];


    const price =
        selected.getAttribute(
            "data-price"
        );


    const priceInput =
        document.getElementById(
            "purchasePrice"
        );


    if (price !== null) {

        priceInput.value = price;

    } else {

        priceInput.value = "";
    }
}


/* =================================================
   CLIENT-SIDE VALIDATION
   ================================================= */

function validateForm() {

    const purchase =
        document.getElementById(
            "purchaseID"
        );


    const medicine =
        document.getElementById(
            "medicine"
        );


    const quantity =
        document.getElementById(
            "quantity"
        );


    const price =
        document.getElementById(
            "purchasePrice"
        );


    if (purchase.value === "") {

        alert(
            "Please select a purchase / bill."
        );

        purchase.focus();

        return false;
    }


    if (medicine.value === "") {

        alert(
            "Please select a medicine."
        );

        medicine.focus();

        return false;
    }


    if (
        quantity.value === "" ||
        !/^[1-9][0-9]*$/.test(
            quantity.value
        )
    ) {

        alert(
            "Quantity must be a positive whole number."
        );

        quantity.focus();

        return false;
    }


    if (
        parseInt(
            quantity.value,
            10
        ) > 100000
    ) {

        alert(
            "Quantity cannot exceed 100000."
        );

        quantity.focus();

        return false;
    }


    if (
        price.value === "" ||
        !/^\d+(\.\d{1,2})?$/.test(
            price.value
        )
    ) {

        alert(
            "Purchase price must be a valid amount with maximum 2 decimal places."
        );

        price.focus();

        return false;
    }


    if (
        parseFloat(
            price.value
        ) <= 0
    ) {

        alert(
            "Purchase price must be greater than 0."
        );

        price.focus();

        return false;
    }


    return true;
}


/* =================================================
   PREVENT DOUBLE SUBMISSION
   ================================================= */

document
    .getElementById(
        "purchaseItemForm"
    )
    .addEventListener(
        "submit",
        function() {

            const button =
                document.getElementById(
                    "submitButton"
                );


            button.disabled = true;

            button.innerText =
                "Adding...";

        }
    );


</script>


</body>

</html>
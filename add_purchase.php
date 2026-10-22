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

$csrf_token = $_SESSION['csrf_token'];


/* =================================================
   HELPER FUNCTION
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

$supplierID = "";
$pharmacistID = "";
$purchaseDate = date("Y-m-d");
$status = "Completed";

$formMedicineIDs = [];
$formQuantities = [];


/* =================================================
   IF PHARMACIST IS LOGGED IN
   AUTOMATICALLY SELECT THAT PHARMACIST
   ================================================= */

if (isset($_SESSION['pharmacist'])) {

    $sessionPharmacistID = filter_var(
        $_SESSION['PharmacistID'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    );

    if ($sessionPharmacistID !== false) {
        $pharmacistID = (string)$sessionPharmacistID;
    }
}


/* =================================================
   GET SUPPLIERS
   ================================================= */

$supplierQuery = mysqli_query(
    $conn,
    "
    SELECT SupplierID, SupplierName
    FROM supplier
    ORDER BY SupplierName ASC
    "
);

if (!$supplierQuery) {

    $message =
        "Unable to load suppliers: " .
        $conn->error;

    $messageType = "error";
}


/* =================================================
   GET PHARMACISTS
   ================================================= */

$pharmacistQuery = mysqli_query(
    $conn,
    "
    SELECT PharmacistID, Name
    FROM pharmacist
    ORDER BY Name ASC
    "
);

if (!$pharmacistQuery) {

    $message =
        "Unable to load pharmacists: " .
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

$medicines = [];

if ($medicineQuery) {

    while ($medicine = mysqli_fetch_assoc($medicineQuery)) {

        $medicines[] = $medicine;
    }

} else {

    $message =
        "Unable to load medicines: " .
        $conn->error;

    $messageType = "error";
}


/* =================================================
   COMPLETE PURCHASE
   ================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


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
            "Invalid request. Please try again.";

        $messageType = "error";

    } else {


        /* =================================================
           GET FORM DATA
           ================================================= */

        $supplierID = isset($_POST['SupplierID'])
            ? trim((string)$_POST['SupplierID'])
            : "";

        $pharmacistID = isset($_POST['PharmacistID'])
            ? trim((string)$_POST['PharmacistID'])
            : "";

        $purchaseDate = isset($_POST['PurchaseDate'])
            ? trim((string)$_POST['PurchaseDate'])
            : "";

        $status = isset($_POST['Status'])
            ? trim((string)$_POST['Status'])
            : "";

        $formMedicineIDs =
            isset($_POST['MedicineID']) &&
            is_array($_POST['MedicineID'])
            ? $_POST['MedicineID']
            : [];

        $formQuantities =
            isset($_POST['Quantity']) &&
            is_array($_POST['Quantity'])
            ? $_POST['Quantity']
            : [];


        /* =================================================
           BASIC VALIDATION
           ================================================= */

        if (
            $supplierID === "" ||
            !ctype_digit($supplierID) ||
            (int)$supplierID <= 0
        ) {

            $message =
                "Please select a valid supplier.";

            $messageType = "error";

        } elseif (
            $pharmacistID === "" ||
            !ctype_digit($pharmacistID) ||
            (int)$pharmacistID <= 0
        ) {

            $message =
                "Please select a valid pharmacist.";

            $messageType = "error";

        } elseif ($purchaseDate === "") {

            $message =
                "Please select a purchase date.";

            $messageType = "error";

        } elseif (
            !in_array(
                $status,
                [
                    "Completed",
                    "Pending",
                    "Cancelled"
                ],
                true
            )
        ) {

            $message =
                "Please select a valid purchase status.";

            $messageType = "error";

        } elseif (
            empty($formMedicineIDs) ||
            empty($formQuantities)
        ) {

            $message =
                "Please add at least one medicine.";

            $messageType = "error";

        } elseif (
            count($formMedicineIDs) !==
            count($formQuantities)
        ) {

            $message =
                "Invalid medicine details.";

            $messageType = "error";

        } else {


            /* =================================================
               CONVERT IDS
               ================================================= */

            $supplierID = (int)$supplierID;
            $pharmacistID = (int)$pharmacistID;


            /* =================================================
               DATE VALIDATION
               ================================================= */

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $purchaseDate
            );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !==
                $purchaseDate
            ) {

                $message =
                    "Please enter a valid purchase date.";

                $messageType = "error";

            } elseif ($purchaseDate > date('Y-m-d')) {

                $message =
                    "Purchase date cannot be a future date.";

                $messageType = "error";
            }


            /* =================================================
               CHECK SUPPLIER
               NO get_result()
               ================================================= */

            if ($message === "") {

                $supplierCheck = $conn->prepare(
                    "
                    SELECT SupplierID
                    FROM supplier
                    WHERE SupplierID = ?
                    LIMIT 1
                    "
                );

                if (!$supplierCheck) {

                    $message =
                        "Unable to verify supplier: " .
                        $conn->error;

                    $messageType = "error";

                } else {

                    $supplierCheck->bind_param(
                        "i",
                        $supplierID
                    );

                    if (!$supplierCheck->execute()) {

                        $message =
                            "Unable to verify supplier: " .
                            $supplierCheck->error;

                        $messageType = "error";

                    } else {

                        $supplierCheck->store_result();

                        if ($supplierCheck->num_rows === 0) {

                            $message =
                                "Selected supplier does not exist.";

                            $messageType = "error";
                        }
                    }

                    $supplierCheck->close();
                }
            }


            /* =================================================
               CHECK PHARMACIST
               NO get_result()
               ================================================= */

            if ($message === "") {

                $pharmacistCheck = $conn->prepare(
                    "
                    SELECT PharmacistID
                    FROM pharmacist
                    WHERE PharmacistID = ?
                    LIMIT 1
                    "
                );

                if (!$pharmacistCheck) {

                    $message =
                        "Unable to verify pharmacist: " .
                        $conn->error;

                    $messageType = "error";

                } else {

                    $pharmacistCheck->bind_param(
                        "i",
                        $pharmacistID
                    );

                    if (!$pharmacistCheck->execute()) {

                        $message =
                            "Unable to verify pharmacist: " .
                            $pharmacistCheck->error;

                        $messageType = "error";

                    } else {

                        $pharmacistCheck->store_result();

                        if ($pharmacistCheck->num_rows === 0) {

                            $message =
                                "Selected pharmacist does not exist.";

                            $messageType = "error";
                        }
                    }

                    $pharmacistCheck->close();
                }
            }


            /* =================================================
               PROCESS MEDICINES
               ================================================= */

            if ($message === "") {

                $validatedItems = [];
                $medicineIDsUsed = [];
                $totalAmount = 0.00;


                for (
                    $i = 0;
                    $i < count($formMedicineIDs);
                    $i++
                ) {

                    $medicineID = trim(
                        (string)$formMedicineIDs[$i]
                    );

                    $quantity = trim(
                        (string)$formQuantities[$i]
                    );


                    /* -----------------------------------------
                       IGNORE COMPLETELY EMPTY ROW
                       ----------------------------------------- */

                    if (
                        $medicineID === "" &&
                        $quantity === ""
                    ) {
                        continue;
                    }


                    /* -----------------------------------------
                       MEDICINE ID
                       ----------------------------------------- */

                    if (
                        !ctype_digit($medicineID) ||
                        (int)$medicineID <= 0
                    ) {

                        $message =
                            "Please select a valid medicine.";

                        $messageType = "error";

                        break;
                    }

                    $medicineID = (int)$medicineID;


                    /* -----------------------------------------
                       QUANTITY
                       ----------------------------------------- */

                    if (
                        !ctype_digit($quantity) ||
                        (int)$quantity <= 0
                    ) {

                        $message =
                            "Medicine quantity must be a positive whole number.";

                        $messageType = "error";

                        break;
                    }

                    $quantity = (int)$quantity;


                    if ($quantity > 100000) {

                        $message =
                            "Medicine quantity cannot exceed 100000.";

                        $messageType = "error";

                        break;
                    }


                    /* -----------------------------------------
                       DUPLICATE MEDICINE
                       ----------------------------------------- */

                    if (
                        in_array(
                            $medicineID,
                            $medicineIDsUsed,
                            true
                        )
                    ) {

                        $message =
                            "The same medicine cannot be added more than once.";

                        $messageType = "error";

                        break;
                    }

                    $medicineIDsUsed[] = $medicineID;


                    /* -----------------------------------------
                       GET MEDICINE
                       NO get_result()
                       ----------------------------------------- */

                    $medicineCheck = $conn->prepare(
                        "
                        SELECT
                            MedicineID,
                            MedicineName,
                            PurchasePrice,
                            StockQty
                        FROM medicine
                        WHERE MedicineID = ?
                        LIMIT 1
                        "
                    );

                    if (!$medicineCheck) {

                        $message =
                            "Unable to verify medicine: " .
                            $conn->error;

                        $messageType = "error";

                        break;
                    }


                    $medicineCheck->bind_param(
                        "i",
                        $medicineID
                    );


                    if (!$medicineCheck->execute()) {

                        $message =
                            "Unable to verify medicine: " .
                            $medicineCheck->error;

                        $messageType = "error";

                        $medicineCheck->close();

                        break;
                    }


                    /*
                     * Get result using bind_result()
                     * instead of get_result().
                     */

                    $medicineCheck->store_result();


                    if ($medicineCheck->num_rows === 0) {

                        $medicineCheck->close();

                        $message =
                            "Selected medicine does not exist.";

                        $messageType = "error";

                        break;
                    }


                    $medicineCheck->bind_result(
                        $dbMedicineID,
                        $dbMedicineName,
                        $dbPurchasePrice,
                        $dbStockQty
                    );


                    $medicineCheck->fetch();

                    $medicineCheck->close();


                    /* -----------------------------------------
                       PURCHASE PRICE
                       ----------------------------------------- */

                    $purchasePrice =
                        (float)$dbPurchasePrice;


                    if (
                        $purchasePrice <= 0 ||
                        $purchasePrice > 99999999.99
                    ) {

                        $message =
                            "Invalid purchase price for " .
                            $dbMedicineName .
                            ".";

                        $messageType = "error";

                        break;
                    }


                    /* -----------------------------------------
                       SUBTOTAL
                       ----------------------------------------- */

                    $subTotal = round(
                        $quantity * $purchasePrice,
                        2
                    );


                    if (
                        $subTotal > 9999999999.99
                    ) {

                        $message =
                            "Purchase amount is too large.";

                        $messageType = "error";

                        break;
                    }


                    $totalAmount = round(
                        $totalAmount + $subTotal,
                        2
                    );


                    /* -----------------------------------------
                       SAVE VALIDATED ITEM
                       ----------------------------------------- */

                    $validatedItems[] = [
                        'MedicineID' =>
                            $medicineID,

                        'Quantity' =>
                            $quantity,

                        'PurchasePrice' =>
                            $purchasePrice,

                        'SubTotal' =>
                            $subTotal
                    ];
                }


                /* =================================================
                   VALID ITEM CHECK
                   ================================================= */

                if (
                    $message === "" &&
                    empty($validatedItems)
                ) {

                    $message =
                        "Please add at least one valid medicine.";

                    $messageType = "error";
                }


                /* =================================================
                   SAVE PURCHASE
                   ================================================= */

                if ($message === "") {

                    mysqli_begin_transaction($conn);


                    try {


                        /* =========================================
                           INSERT PURCHASE
                           ========================================= */

                        $purchaseStmt = $conn->prepare(
                            "
                            INSERT INTO purchase
                            (
                                SupplierID,
                                PharmacistID,
                                PurchaseDate,
                                TotalAmount,
                                Status
                            )
                            VALUES
                            (?, ?, ?, ?, ?)
                            "
                        );


                        if (!$purchaseStmt) {

                            throw new Exception(
                                "Unable to create purchase: " .
                                $conn->error
                            );
                        }


                        $purchaseStmt->bind_param(
                            "iisds",
                            $supplierID,
                            $pharmacistID,
                            $purchaseDate,
                            $totalAmount,
                            $status
                        );


                        if (!$purchaseStmt->execute()) {

                            throw new Exception(
                                "Unable to create purchase: " .
                                $purchaseStmt->error
                            );
                        }


                        $purchaseID =
                            $conn->insert_id;


                        if ($purchaseID <= 0) {

                            throw new Exception(
                                "Purchase ID could not be created."
                            );
                        }


                        $purchaseStmt->close();


                        /* =========================================
                           INSERT PURCHASE ITEMS
                           ========================================= */

                        $itemStmt = $conn->prepare(
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


                        if (!$itemStmt) {

                            throw new Exception(
                                "Unable to add purchase items: " .
                                $conn->error
                            );
                        }


                        /* =========================================
                           UPDATE MEDICINE STOCK
                           ========================================= */

                        $stockStmt = $conn->prepare(
                            "
                            UPDATE medicine
                            SET
                                StockQty = StockQty + ?,
                                Status =
                                    CASE
                                        WHEN StockQty + ? > 0
                                        THEN 'Available'
                                        ELSE 'Unavailable'
                                    END
                            WHERE MedicineID = ?
                            "
                        );


                        if (!$stockStmt) {

                            $itemStmt->close();

                            throw new Exception(
                                "Unable to update medicine stock: " .
                                $conn->error
                            );
                        }


                        foreach (
                            $validatedItems as $item
                        ) {


                            /* -------------------------------------
                               PURCHASE ITEM
                               ------------------------------------- */

                            $itemStmt->bind_param(
                                "iiidd",
                                $purchaseID,
                                $item['MedicineID'],
                                $item['Quantity'],
                                $item['PurchasePrice'],
                                $item['SubTotal']
                            );


                            if (!$itemStmt->execute()) {

                                throw new Exception(
                                    "Unable to add purchase item: " .
                                    $itemStmt->error
                                );
                            }


                            /* -------------------------------------
                               STOCK UPDATE
                               ------------------------------------- */

                            $stockStmt->bind_param(
                                "iii",
                                $item['Quantity'],
                                $item['Quantity'],
                                $item['MedicineID']
                            );


                            if (!$stockStmt->execute()) {

                                throw new Exception(
                                    "Unable to update medicine stock: " .
                                    $stockStmt->error
                                );
                            }


                            if (
                                $stockStmt->affected_rows === 0
                            ) {

                                throw new Exception(
                                    "Medicine stock could not be updated."
                                );
                            }
                        }


                        $itemStmt->close();
                        $stockStmt->close();


                        /* =========================================
                           UPDATE PURCHASE TOTAL
                           ========================================= */

                        $totalStmt = $conn->prepare(
                            "
                            UPDATE purchase
                            SET TotalAmount = ?
                            WHERE Purchase_Id = ?
                            "
                        );


                        if (!$totalStmt) {

                            throw new Exception(
                                "Unable to update purchase total: " .
                                $conn->error
                            );
                        }


                        $totalStmt->bind_param(
                            "di",
                            $totalAmount,
                            $purchaseID
                        );


                        if (!$totalStmt->execute()) {

                            throw new Exception(
                                "Unable to update purchase total: " .
                                $totalStmt->error
                            );
                        }


                        $totalStmt->close();


                        /* =========================================
                           COMMIT
                           ========================================= */

                        if (!mysqli_commit($conn)) {

                            throw new Exception(
                                "Purchase could not be completed: " .
                                $conn->error
                            );
                        }


                        /* =========================================
                           GO TO INVOICE
                           ========================================= */

                        header(
                            "Location: purchase_invoice.php?PurchaseID=" .
                            urlencode((string)$purchaseID)
                        );

                        exit();


                    } catch (Throwable $e) {

                        mysqli_rollback($conn);

                        $message =
                            "Purchase could not be saved. " .
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

    <title>Complete Purchase</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 15px;
        }

        select,
        input {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            font-size: 15px;
            border: 1px solid #bbb;
            border-radius: 4px;
        }

        select:focus,
        input:focus {
            outline: none;
            border-color: #007bff;
        }

        .medicine-section {
            margin-top: 25px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 10px;
        }

        .medicine-row {
            display: grid;
            grid-template-columns: 1fr 180px 100px;
            gap: 12px;
            margin-top: 15px;
            align-items: center;
        }

        .remove-button {
            background: #c0392b;
            color: white;
            border: none;
            padding: 12px;
            cursor: pointer;
            border-radius: 5px;
        }

        .remove-button:hover {
            background: #a93226;
        }

        .add-button {
            background: #1976d2;
            color: white;
            border: none;
            padding: 12px 20px;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
        }

        .add-button:hover {
            background: #125ca1;
        }

        .complete-button {
            width: 100%;
            background: green;
            color: white;
            border: none;
            padding: 15px;
            font-size: 18px;
            margin-top: 25px;
            cursor: pointer;
            border-radius: 5px;
        }

        .complete-button:hover {
            opacity: 0.85;
        }

        .complete-button:disabled {
            background: #777;
            cursor: not-allowed;
        }

        .message {
            text-align: center;
            padding: 12px;
            margin-bottom: 20px;
            font-weight: bold;
            border-radius: 5px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .total {
            text-align: right;
            font-size: 22px;
            font-weight: bold;
            margin-top: 20px;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            background: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back:hover {
            background: #333;
        }

        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        @media screen and (max-width: 700px) {

            .medicine-row {
                grid-template-columns: 1fr;
            }

            .container {
                width: 95%;
                padding: 20px;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Complete Purchase
    </h2>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo e($messageType); ?>">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        id="purchaseForm"
        onsubmit="return validatePurchaseForm();"
        novalidate
    >


        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrf_token); ?>"
        >


        <!-- SUPPLIER -->

        <label for="SupplierID">
            Supplier
        </label>

        <select
            id="SupplierID"
            name="SupplierID"
        >

            <option value="">
                Select Supplier
            </option>

            <?php if ($supplierQuery): ?>

                <?php while (
                    $supplier =
                    mysqli_fetch_assoc($supplierQuery)
                ): ?>

                    <option
                        value="<?php
                            echo e(
                                $supplier['SupplierID']
                            );
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

                <?php endwhile; ?>

            <?php endif; ?>

        </select>


        <!-- PHARMACIST -->

        <label for="PharmacistID">
            Pharmacist
        </label>

        <select
            id="PharmacistID"
            name="PharmacistID"
        >

            <option value="">
                Select Pharmacist
            </option>

            <?php if ($pharmacistQuery): ?>

                <?php while (
                    $pharmacist =
                    mysqli_fetch_assoc($pharmacistQuery)
                ): ?>

                    <option
                        value="<?php
                            echo e(
                                $pharmacist['PharmacistID']
                            );
                        ?>"
                        <?php
                        if (
                            (string)$pharmacistID ===
                            (string)$pharmacist['PharmacistID']
                        ) {
                            echo "selected";
                        }
                        ?>
                    >

                        <?php
                        echo e(
                            $pharmacist['Name']
                        );
                        ?>

                    </option>

                <?php endwhile; ?>

            <?php endif; ?>

        </select>


        <!-- PURCHASE DATE -->

        <label for="PurchaseDate">
            Purchase Date
        </label>

        <input
            type="date"
            id="PurchaseDate"
            name="PurchaseDate"
            value="<?php echo e($purchaseDate); ?>"
            max="<?php echo date('Y-m-d'); ?>"
        >


        <!-- STATUS -->

        <label for="Status">
            Purchase Status
        </label>

        <select
            id="Status"
            name="Status"
        >

            <option
                value="Completed"
                <?php
                echo $status === "Completed"
                    ? "selected"
                    : "";
                ?>
            >
                Completed
            </option>

            <option
                value="Pending"
                <?php
                echo $status === "Pending"
                    ? "selected"
                    : "";
                ?>
            >
                Pending
            </option>

            <option
                value="Cancelled"
                <?php
                echo $status === "Cancelled"
                    ? "selected"
                    : "";
                ?>
            >
                Cancelled
            </option>

        </select>


        <!-- MEDICINES -->

        <div class="medicine-section">

            <h3>
                Medicines
            </h3>

            <div class="hint">
                Select a medicine and enter the quantity to purchase.
            </div>


            <div id="medicineRows">

                <?php

                $rowCount = count($formMedicineIDs);

                if ($rowCount === 0) {
                    $rowCount = 1;
                }

                for (
                    $r = 0;
                    $r < $rowCount;
                    $r++
                ):

                    $selectedMedicine =
                        isset($formMedicineIDs[$r])
                        ? (string)$formMedicineIDs[$r]
                        : "";

                    $enteredQuantity =
                        isset($formQuantities[$r])
                        ? (string)$formQuantities[$r]
                        : "";

                ?>

                    <div class="medicine-row">

                        <select
                            name="MedicineID[]"
                            class="medicineSelect"
                        >

                            <option value="">
                                Select Medicine
                            </option>

                            <?php foreach (
                                $medicines as $medicine
                            ): ?>

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
                                    <?php
                                    if (
                                        $selectedMedicine ===
                                        (string)$medicine['MedicineID']
                                    ) {
                                        echo "selected";
                                    }
                                    ?>
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

                            <?php endforeach; ?>

                        </select>


                        <input
                            type="number"
                            name="Quantity[]"
                            class="quantity"
                            placeholder="Quantity"
                            min="1"
                            max="100000"
                            value="<?php
                                echo e(
                                    $enteredQuantity
                                );
                            ?>"
                        >


                        <button
                            type="button"
                            class="remove-button"
                            onclick="removeMedicine(this)"
                        >
                            Remove
                        </button>

                    </div>

                <?php endfor; ?>

            </div>


            <button
                type="button"
                class="add-button"
                onclick="addMedicine()"
            >
                + Add Medicine
            </button>

        </div>


        <!-- TOTAL -->

        <div class="total">

            Total Purchase Amount:
            ₹

            <span id="totalAmount">
                0.00
            </span>

        </div>


        <!-- SUBMIT -->

        <button
            type="submit"
            name="complete_purchase"
            id="completeButton"
            class="complete-button"
        >
            Complete Purchase
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
   CALCULATE TOTAL
   ================================================= */

function calculateTotal() {

    let total = 0;

    document
        .querySelectorAll(".medicine-row")
        .forEach(function(row) {

            const medicine =
                row.querySelector(
                    ".medicineSelect"
                );

            const quantity =
                row.querySelector(
                    ".quantity"
                );

            if (
                medicine.value !== "" &&
                quantity.value !== ""
            ) {

                const selected =
                    medicine.options[
                        medicine.selectedIndex
                    ];

                const price =
                    parseFloat(
                        selected.getAttribute(
                            "data-price"
                        )
                    );

                const qty =
                    parseInt(
                        quantity.value,
                        10
                    );

                if (
                    !isNaN(price) &&
                    !isNaN(qty) &&
                    qty > 0
                ) {

                    total += price * qty;
                }
            }
        });


    document
        .getElementById("totalAmount")
        .textContent =
        total.toFixed(2);
}


/* =================================================
   ADD MEDICINE
   ================================================= */

function addMedicine() {

    const container =
        document.getElementById(
            "medicineRows"
        );

    const firstRow =
        document.querySelector(
            ".medicine-row"
        );

    if (!firstRow) {
        return;
    }

    const newRow =
        firstRow.cloneNode(true);

    newRow
        .querySelector(
            ".medicineSelect"
        )
        .value = "";

    newRow
        .querySelector(
            ".quantity"
        )
        .value = "";

    container.appendChild(
        newRow
    );

    calculateTotal();
}


/* =================================================
   REMOVE MEDICINE
   ================================================= */

function removeMedicine(button) {

    const rows =
        document.querySelectorAll(
            ".medicine-row"
        );

    if (rows.length > 1) {

        button
            .closest(".medicine-row")
            .remove();

    } else {

        const row =
            button.closest(
                ".medicine-row"
            );

        row.querySelector(
            ".medicineSelect"
        ).value = "";

        row.querySelector(
            ".quantity"
        ).value = "";
    }

    calculateTotal();
}


/* =================================================
   CLIENT VALIDATION
   ================================================= */

function validatePurchaseForm() {

    const supplier =
        document.getElementById(
            "SupplierID"
        ).value;

    const pharmacist =
        document.getElementById(
            "PharmacistID"
        ).value;

    const purchaseDate =
        document.getElementById(
            "PurchaseDate"
        ).value;

    const status =
        document.getElementById(
            "Status"
        ).value;


    if (!supplier) {

        alert(
            "Please select a supplier."
        );

        return false;
    }


    if (!pharmacist) {

        alert(
            "Please select a pharmacist."
        );

        return false;
    }


    if (!purchaseDate) {

        alert(
            "Please select a purchase date."
        );

        return false;
    }


    const today =
        new Date()
            .toISOString()
            .split("T")[0];


    if (purchaseDate > today) {

        alert(
            "Purchase date cannot be a future date."
        );

        return false;
    }


    if (
        ![
            "Completed",
            "Pending",
            "Cancelled"
        ].includes(status)
    ) {

        alert(
            "Please select a valid purchase status."
        );

        return false;
    }


    const rows =
        document.querySelectorAll(
            ".medicine-row"
        );

    let validRows = 0;

    const medicineIDs = [];


    for (
        let i = 0;
        i < rows.length;
        i++
    ) {

        const medicine =
            rows[i]
                .querySelector(
                    ".medicineSelect"
                )
                .value;

        const quantity =
            rows[i]
                .querySelector(
                    ".quantity"
                )
                .value;


        if (
            medicine === "" &&
            quantity === ""
        ) {
            continue;
        }


        if (medicine === "") {

            alert(
                "Please select a medicine for every entered row."
            );

            return false;
        }


        if (
            quantity === "" ||
            !/^[0-9]+$/.test(quantity) ||
            parseInt(quantity, 10) <= 0
        ) {

            alert(
                "Please enter a valid positive quantity."
            );

            return false;
        }


        if (
            parseInt(quantity, 10) > 100000
        ) {

            alert(
                "Medicine quantity cannot exceed 100000."
            );

            return false;
        }


        if (
            medicineIDs.includes(medicine)
        ) {

            alert(
                "The same medicine cannot be added more than once."
            );

            return false;
        }


        medicineIDs.push(medicine);

        validRows++;
    }


    if (validRows === 0) {

        alert(
            "Please add at least one medicine."
        );

        return false;
    }


    const button =
        document.getElementById(
            "completeButton"
        );

    button.disabled = true;

    button.textContent =
        "Processing Purchase...";


    return true;
}


/* =================================================
   TOTAL UPDATE
   ================================================= */

document.addEventListener(
    "input",
    function(event) {

        if (
            event.target.classList.contains(
                "quantity"
            )
        ) {

            calculateTotal();
        }

    }
);


document.addEventListener(
    "change",
    function(event) {

        if (
            event.target.classList.contains(
                "medicineSelect"
            )
        ) {

            calculateTotal();
        }

    }
);


/* =================================================
   INITIAL TOTAL
   ================================================= */

calculateTotal();

</script>


</body>

</html>
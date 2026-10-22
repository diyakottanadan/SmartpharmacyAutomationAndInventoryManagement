<?php

include("db_connect.php");

$message = "";


/* ==========================================
   GET EXISTING SALES WITH CUSTOMER DETAILS
   ========================================== */

$saleQuery = mysqli_query(
    $conn,
    "
    SELECT
        sale.SaleID,
        sale.SaleDate,
        customer.CustomerName

    FROM sale

    LEFT JOIN customer
        ON sale.CustomerID = customer.CustomerID

    ORDER BY sale.SaleID DESC
    "
);


/* ==========================================
   GET AVAILABLE MEDICINES
   ========================================== */

$medicineQuery = mysqli_query(
    $conn,
    "
    SELECT
        MedicineID,
        MedicineName,
        SellingPrice,
        StockQty

    FROM medicine

    WHERE StockQty > 0
      AND Status = 'Available'

    ORDER BY MedicineName
    "
);


/* ==========================================
   ADD SALE ITEM
   ========================================== */

if (isset($_POST['add_item'])) {


    /* Get form values */

    $saleID = isset($_POST['SaleID'])
        ? (int)$_POST['SaleID']
        : 0;

    $medicineID = isset($_POST['MedicineID'])
        ? (int)$_POST['MedicineID']
        : 0;

    $quantity = isset($_POST['Quantity'])
        ? (int)$_POST['Quantity']
        : 0;


    /* Validate */

    if ($saleID <= 0) {

        $message = "Please select a sale.";

    }
    elseif ($medicineID <= 0) {

        $message = "Please select a medicine.";

    }
    elseif ($quantity <= 0) {

        $message =
            "Quantity must be greater than 0.";

    }
    else {


        /* Start transaction */

        mysqli_begin_transaction($conn);


        try {


            /* ==========================================
               1. CHECK SALE
               ========================================== */

            $checkSale = mysqli_query(
                $conn,
                "
                SELECT SaleID
                FROM sale
                WHERE SaleID = $saleID
                "
            );


            if (
                !$checkSale ||
                mysqli_num_rows($checkSale) == 0
            ) {

                throw new Exception(
                    "Sale not found."
                );

            }



            /* ==========================================
               2. GET MEDICINE DETAILS
               IMPORTANT:
               Price comes from database.
               User cannot manipulate the price.
               ========================================== */

            $checkMedicine = mysqli_query(
                $conn,
                "
                SELECT
                    MedicineID,
                    MedicineName,
                    SellingPrice,
                    StockQty,
                    Status

                FROM medicine

                WHERE MedicineID = $medicineID
                "
            );


            if (
                !$checkMedicine ||
                mysqli_num_rows($checkMedicine) == 0
            ) {

                throw new Exception(
                    "Medicine not found."
                );

            }


            $medicine =
                mysqli_fetch_assoc(
                    $checkMedicine
                );


            $currentStock =
                (int)$medicine['StockQty'];


            $sellingPrice =
                (float)$medicine['SellingPrice'];


            $medicineName =
                $medicine['MedicineName'];



            /* ==========================================
               3. CHECK MEDICINE STATUS
               ========================================== */

            if (
                $medicine['Status']
                != "Available"
            ) {

                throw new Exception(
                    "This medicine is not available for sale."
                );

            }



            /* ==========================================
               4. CHECK STOCK
               ========================================== */

            if ($quantity > $currentStock) {

                throw new Exception(

                    "Insufficient stock for "
                    . $medicineName
                    . ". Available stock: "
                    . $currentStock

                );

            }



            /* ==========================================
               5. CALCULATE SUBTOTAL
               ========================================== */

            $subTotal =
                $quantity * $sellingPrice;



            /* ==========================================
               6. INSERT SALE ITEM
               ========================================== */

            $insertSQL = "
                INSERT INTO sale_items
                (
                    SaleID,
                    MedicineID,
                    Quantity,
                    SellingPrice,
                    SubTotal
                )

                VALUES
                (
                    $saleID,
                    $medicineID,
                    $quantity,
                    $sellingPrice,
                    $subTotal
                )
            ";


            if (
                !mysqli_query(
                    $conn,
                    $insertSQL
                )
            ) {

                throw new Exception(
                    "Error adding sale item: "
                    . mysqli_error($conn)
                );

            }



            /* ==========================================
               7. REDUCE MEDICINE STOCK
               ========================================== */

            $stockSQL = "
                UPDATE medicine

                SET StockQty =
                    StockQty - $quantity

                WHERE MedicineID = $medicineID
            ";


            if (
                !mysqli_query(
                    $conn,
                    $stockSQL
                )
            ) {

                throw new Exception(
                    "Error updating stock: "
                    . mysqli_error($conn)
                );

            }



            /* ==========================================
               8. UPDATE SALE TOTAL

               Calculate total again from all items.
               This is safer than simply adding.
               ========================================== */

            $saleTotalSQL = "
                UPDATE sale

                SET TotalAmount =
                (
                    SELECT
                        COALESCE(
                            SUM(SubTotal),
                            0
                        )

                    FROM sale_items

                    WHERE sale_items.SaleID =
                        $saleID
                )

                WHERE SaleID = $saleID
            ";


            if (
                !mysqli_query(
                    $conn,
                    $saleTotalSQL
                )
            ) {

                throw new Exception(
                    "Error updating sale total: "
                    . mysqli_error($conn)
                );

            }



            /* ==========================================
               9. COMMIT TRANSACTION
               ========================================== */

            mysqli_commit($conn);


            $newStock =
                $currentStock - $quantity;


            $message =
                "Sale item added successfully! "
                . $medicineName
                . " stock changed from "
                . $currentStock
                . " to "
                . $newStock
                . ".";


        }


        catch (Exception $e) {


            /* Undo everything if an error occurs */

            mysqli_rollback($conn);


            $message =
                "Error: "
                . $e->getMessage();

        }

    }

}

?>


<!DOCTYPE html>

<html>

<head>

    <title>
        Add Sale Item
    </title>


    <style>

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

            margin:
                50px auto;

            background-color:
                white;

            padding:
                30px;

            border-radius:
                10px;

            box-shadow:
                0 0 10px #ccc;

        }


        h2 {

            text-align:
                center;

        }


        label {

            display:
                block;

            margin-top:
                15px;

            font-weight:
                bold;

        }


        select,
        input {

            width:
                100%;

            padding:
                10px;

            margin-top:
                5px;

            box-sizing:
                border-box;

        }


        button {

            width:
                100%;

            padding:
                12px;

            margin-top:
                25px;

            background-color:
                green;

            color:
                white;

            border:
                none;

            cursor:
                pointer;

            font-size:
                16px;

        }


        button:hover {

            opacity:
                0.85;

        }


        .message {

            text-align:
                center;

            font-weight:
                bold;

            margin-bottom:
                15px;

        }


        .price-box {

            margin-top:
                10px;

            padding:
                10px;

            background-color:
                #f2f2f2;

            border-radius:
                5px;

            font-weight:
                bold;

        }


        .back {

            display:
                inline-block;

            margin-top:
                15px;

            padding:
                10px 15px;

            background-color:
                #555;

            color:
                white;

            text-decoration:
                none;

            border-radius:
                5px;

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Add Sale Item
    </h2>



    <?php

    if ($message != "") {

        echo
            "<div class='message'>";

        echo
            htmlspecialchars($message);

        echo
            "</div>";

    }

    ?>



    <form method="POST">


        <!-- =========================
             SALE
             ========================= -->

        <label>
            Select Sale
        </label>


        <select
            name="SaleID"
            required
        >


            <option value="">

                Select Sale

            </option>


            <?php

            if ($saleQuery) {

                while (

                    $sale =
                    mysqli_fetch_assoc(
                        $saleQuery
                    )

                ) {


                    echo
                        "<option value='";

                    echo
                        $sale['SaleID'];

                    echo
                        "'>";


                    echo
                        "Sale ID: "
                        . $sale['SaleID'];


                    echo
                        " | Customer: "
                        . htmlspecialchars(
                            $sale['CustomerName']
                            ?? "Unknown"
                        );


                    echo
                        " | Date: "
                        . $sale['SaleDate'];


                    echo
                        "</option>";

                }

            }

            ?>


        </select>



        <!-- =========================
             MEDICINE
             ========================= -->

        <label>
            Select Medicine
        </label>


        <select
            name="MedicineID"
            id="medicine"
            required
            onchange="showMedicineDetails()"
        >


            <option value="">

                Select Medicine

            </option>


            <?php

            if ($medicineQuery) {

                while (

                    $medicine =
                    mysqli_fetch_assoc(
                        $medicineQuery
                    )

                ) {


                    echo
                        "<option value='";

                    echo
                        $medicine['MedicineID'];

                    echo
                        "' ";


                    echo
                        "data-price='";

                    echo
                        $medicine['SellingPrice'];

                    echo
                        "' ";


                    echo
                        "data-stock='";

                    echo
                        $medicine['StockQty'];

                    echo
                        "'>";


                    echo
                        "ID: "
                        . $medicine['MedicineID']
                        . " | ";


                    echo
                        htmlspecialchars(
                            $medicine['MedicineName']
                        );


                    echo
                        " | Stock: "
                        . $medicine['StockQty'];


                    echo
                        "</option>";

                }

            }

            ?>


        </select>



        <!-- =========================
             MEDICINE DETAILS
             ========================= -->

        <div
            class="price-box"
            id="medicineDetails"
        >

            Select a medicine to view
            price and available stock.

        </div>



        <!-- =========================
             QUANTITY
             ========================= -->

        <label>
            Quantity
        </label>


        <input
            type="number"
            name="Quantity"
            id="quantity"
            min="1"
            required
        >



        <!-- =========================
             BUTTON
             ========================= -->

        <button
            type="submit"
            name="add_item"
        >

            Add Sale Item

        </button>


    </form>



    <a
        href="admin_dashboard.php"
        class="back"
    >

        Back to Dashboard

    </a>


</div>



<script>


function showMedicineDetails() {


    var medicine =
        document.getElementById(
            "medicine"
        );


    var selected =
        medicine.options[
            medicine.selectedIndex
        ];


    var price =
        selected.getAttribute(
            "data-price"
        );


    var stock =
        selected.getAttribute(
            "data-stock"
        );


    var details =
        document.getElementById(
            "medicineDetails"
        );


    if (
        price === null ||
        stock === null
    ) {


        details.innerHTML =
            "Select a medicine to view "
            + "price and available stock.";


    }
    else {


        details.innerHTML =
            "Selling Price: ₹"
            + price
            + "<br>"
            + "Available Stock: "
            + stock;

    }


}


</script>


</body>

</html>
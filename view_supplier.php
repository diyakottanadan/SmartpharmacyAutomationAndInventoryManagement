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

$csrfToken =
    $_SESSION['csrf_token'];


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
   GET SUPPLIERS
   ========================================= */

$sql = "
    SELECT
        SupplierID,
        SupplierName,
        ContactPerson,
        ContactNo,
        Email,
        Address
    FROM supplier
    ORDER BY SupplierID
";


$result = mysqli_query(
    $conn,
    $sql
);


$queryError = "";

if (!$result) {

    $queryError =
        "Unable to load supplier information.";

}

?>


<!DOCTYPE html>

<html>

<head>

    <title>
        Supplier Management
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
                95%;

            margin:
                40px auto;

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

            margin-top:
                0;

        }


        .top-bar {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                10px;

            margin-bottom:
                20px;

        }


        .left-buttons {

            display:
                flex;

            gap:
                8px;

            flex-wrap:
                wrap;

        }


        .button {

            display:
                inline-block;

            padding:
                9px 14px;

            color:
                white;

            text-decoration:
                none;

            border-radius:
                5px;

            border:
                none;

            cursor:
                pointer;

            font-size:
                14px;

        }


        .add {

            background-color:
                green;

        }


        .add:hover {

            background-color:
                #006400;

        }


        .edit {

            background-color:
                #007bff;

        }


        .edit:hover {

            background-color:
                #0056b3;

        }


        .delete {

            background-color:
                #dc3545;

        }


        .delete:hover {

            background-color:
                #b02a37;

        }


        .back {

            background-color:
                #555;

        }


        .back:hover {

            background-color:
                #333;

        }


        .table-container {

            width:
                100%;

            overflow-x:
                auto;

        }


        table {

            width:
                100%;

            border-collapse:
                collapse;

            margin-top:
                10px;

        }


        th,
        td {

            border:
                1px solid #ddd;

            padding:
                12px;

            text-align:
                center;

        }


        th {

            background-color:
                #333;

            color:
                white;

        }


        tr:nth-child(even) {

            background-color:
                #f2f2f2;

        }


        .no-data {

            text-align:
                center;

            padding:
                25px;

            color:
                #666;

        }


        .error-message {

            background-color:
                #f8d7da;

            color:
                #721c24;

            border:
                1px solid #f5c6cb;

            padding:
                12px;

            border-radius:
                5px;

            margin-bottom:
                15px;

            text-align:
                center;

        }


        .success-message {

            background-color:
                #d4edda;

            color:
                #155724;

            border:
                1px solid #c3e6cb;

            padding:
                12px;

            border-radius:
                5px;

            margin-bottom:
                15px;

            text-align:
                center;

            font-weight:
                bold;

        }


        .delete-form {

            display:
                inline;

        }


        @media (max-width: 700px) {

            .container {

                width:
                    98%;

                padding:
                    15px;

            }


            th,
            td {

                padding:
                    8px;

                font-size:
                    13px;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Supplier Management
    </h2>


    <!-- =====================================
         TOP BUTTONS
         ===================================== -->

    <div class="top-bar">


        <div class="left-buttons">


            <a
                href="admin_dashboard.php"
                class="button back"
            >

                ← Back to Dashboard

            </a>


            <a
                href="add_supplier.php"
                class="button add"
            >

                + Add Supplier

            </a>


        </div>


    </div>



    <!-- =====================================
         SUCCESS MESSAGES
         ===================================== -->

    <?php if (
        isset($_GET['updated']) &&
        $_GET['updated'] === '1'
    ) { ?>

        <div class="success-message">

            Supplier updated successfully.

        </div>

    <?php } ?>


    <?php if (
        isset($_GET['deleted']) &&
        $_GET['deleted'] === '1'
    ) { ?>

        <div class="success-message">

            Supplier deleted successfully.

        </div>

    <?php } ?>



    <!-- =====================================
         ERROR MESSAGE
         ===================================== -->

    <?php if ($queryError !== "") { ?>

        <div class="error-message">

            <?php
            echo e($queryError);
            ?>

        </div>

    <?php } ?>



    <?php if ($queryError === "") { ?>


        <div class="table-container">


            <table>


                <thead>

                    <tr>

                        <th>
                            Supplier ID
                        </th>

                        <th>
                            Supplier Name
                        </th>

                        <th>
                            Contact Person
                        </th>

                        <th>
                            Contact No
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Address
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                if (
                    mysqli_num_rows($result) > 0
                ) {


                    while (
                        $row =
                            mysqli_fetch_assoc($result)
                    ) {

                ?>


                    <tr>


                        <!-- Supplier ID -->

                        <td>

                            <?php
                            echo e(
                                $row['SupplierID']
                            );
                            ?>

                        </td>


                        <!-- Supplier Name -->

                        <td>

                            <?php
                            echo e(
                                $row['SupplierName']
                            );
                            ?>

                        </td>


                        <!-- Contact Person -->

                        <td>

                            <?php
                            echo e(
                                $row['ContactPerson']
                            );
                            ?>

                        </td>


                        <!-- Contact Number -->

                        <td>

                            <?php
                            echo e(
                                $row['ContactNo']
                            );
                            ?>

                        </td>


                        <!-- Email -->

                        <td>

                            <?php
                            echo e(
                                $row['Email']
                            );
                            ?>

                        </td>


                        <!-- Address -->

                        <td>

                            <?php
                            echo e(
                                $row['Address']
                            );
                            ?>

                        </td>


                        <!-- Actions -->

                        <td>


                            <!-- EDIT -->

                            <a
                                href="edit_supplier.php?id=<?php
                                    echo e(
                                        $row['SupplierID']
                                    );
                                ?>"
                                class="button edit"
                            >

                                Edit

                            </a>



                            <!-- DELETE -->

                            <form
                                method="POST"
                                action="delete_supplier.php?id=<?php
                                    echo e(
                                        $row['SupplierID']
                                    );
                                ?>"
                                class="delete-form"
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
                                        echo e(
                                            $row['SupplierID']
                                        );
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php
                                        echo e(
                                            $csrfToken
                                        );
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    class="button delete"
                                >

                                    Delete

                                </button>


                            </form>


                        </td>


                    </tr>


                <?php

                    }

                }
                else {

                ?>


                    <tr>

                        <td
                            colspan="7"
                            class="no-data"
                        >

                            No suppliers found.

                        </td>

                    </tr>


                <?php

                }

                ?>


                </tbody>


            </table>


        </div>


    <?php } ?>


</div>


</body>

</html>
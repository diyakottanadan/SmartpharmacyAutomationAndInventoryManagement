<?php

session_start();
include("db_connect.php");


/* -------------------------------------------------
   Admin Login Check
------------------------------------------------- */

if (!isset($_SESSION['admin'])) {

    header("Location: login.php");
    exit();

}


/* -------------------------------------------------
   CSRF Token
------------------------------------------------- */

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrf_token = $_SESSION['csrf_token'];


/* -------------------------------------------------
   Helper Function
------------------------------------------------- */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* -------------------------------------------------
   Get Pharmacists
------------------------------------------------- */

$stmt = $conn->prepare(
    "SELECT
        PharmacistID,
        Name,
        Phone,
        Email,
        Username
     FROM pharmacist
     ORDER BY PharmacistID ASC"
);

if (!$stmt) {

    die("Unable to load pharmacists.");

}

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pharmacist Management</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 95%;
            max-width: 1200px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            box-sizing: border-box;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        .top-buttons {
            margin-bottom: 15px;
        }

        .button {
            display: inline-block;
            padding: 9px 14px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 3px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .add {
            background-color: green;
        }

        .edit {
            background-color: #007bff;
        }

        .delete {
            background-color: #dc3545;
        }

        .back {
            background-color: #555;
        }

        .button:hover {
            opacity: 0.85;
        }

        .button:disabled {
            background-color: #999;
            cursor: not-allowed;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 800px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #e9e9e9;
        }

        .action-form {
            display: inline;
        }

        .no-data {
            padding: 20px;
            text-align: center;
            color: #555;
        }

        @media (max-width: 600px) {

            .container {
                margin: 20px auto;
                padding: 15px;
            }

            .button {
                margin-bottom: 5px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h2>Pharmacist Management</h2>


    <!-- ---------------------------------------------
         Success Messages
    ---------------------------------------------- -->

    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>

        <div class="success-message">

            Pharmacist updated successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>

        <div class="success-message">

            Pharmacist deleted successfully.

        </div>

    <?php endif; ?>


    <!-- ---------------------------------------------
         Navigation Buttons
    ---------------------------------------------- -->

    <div class="top-buttons">

        <a
            href="admin_dashboard.php"
            class="button back"
        >
            Back to Dashboard
        </a>


        <a
            href="add_pharmacist.php"
            class="button add"
        >
            Add Pharmacist
        </a>

    </div>


    <!-- ---------------------------------------------
         Pharmacist Table
    ---------------------------------------------- -->

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>Pharmacist ID</th>

                    <th>Name</th>

                    <th>Phone</th>

                    <th>Email</th>

                    <th>Username</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php
                            echo e($row['PharmacistID']);
                            ?>
                        </td>


                        <td>
                            <?php
                            echo e($row['Name']);
                            ?>
                        </td>


                        <td>
                            <?php
                            echo e($row['Phone']);
                            ?>
                        </td>


                        <td>
                            <?php
                            echo e($row['Email']);
                            ?>
                        </td>


                        <td>
                            <?php
                            echo e($row['Username']);
                            ?>
                        </td>


                        <td>

                            <!-- ---------------------------------
                                 Edit Button
                            ---------------------------------- -->

                            <a
                                class="button edit"
                                href="edit_pharmacist.php?id=<?php
                                    echo urlencode(
                                        $row['PharmacistID']
                                    );
                                ?>"
                            >
                                Edit
                            </a>


                            <!-- ---------------------------------
                                 Delete Form
                            ---------------------------------- -->

                            <form
                                method="POST"
                                action="delete_pharmacist.php?id=<?php
                                    echo urlencode(
                                        $row['PharmacistID']
                                    );
                                ?>"
                                class="action-form"
                                onsubmit="return confirmDelete(this);"
                            >

                                <input
                                    type="hidden"
                                    name="PharmacistID"
                                    value="<?php
                                        echo e(
                                            $row['PharmacistID']
                                        );
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php
                                        echo e($csrf_token);
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    name="delete_pharmacist"
                                    class="button delete"
                                >
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="no-data"
                    >
                        No pharmacists found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<script>

function confirmDelete(form) {

    const confirmed = confirm(
        "Are you sure you want to permanently delete this pharmacist?"
    );

    if (!confirmed) {
        return false;
    }


    /* Prevent double submission */

    const button =
        form.querySelector("button[type='submit']");

    if (button) {

        button.disabled = true;

        button.innerText =
            "Deleting...";

    }

    return true;
}

</script>

</body>

</html>

<?php

$stmt->close();

?>
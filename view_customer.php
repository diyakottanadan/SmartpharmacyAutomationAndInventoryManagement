
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
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
   ROLE-BASED BACK LINK
   Change filenames if your dashboards
   use different names.
   ========================================= */

if (isset($_SESSION['admin'])) {
    $backPage = "admin_dashboard.php";
} else {
    $backPage = "pharmacist_dashboard.php";
}

/* =========================================
   GET ALL CUSTOMERS
   ========================================= */

$sql = "
    SELECT
        CustomerID,
        CustomerName,
        Phone,
        Email,
        Address
    FROM customer
    ORDER BY CustomerID DESC
";

$result = mysqli_query($conn, $sql);

/* =========================================
   CHECK QUERY
   ========================================= */

$queryError = "";

if (!$result) {
    $queryError = "Unable to load customer information.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Customers</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        .header {
            background: #333;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h2 {
            margin: 0;
        }

        .container {
            width: 95%;
            margin: 30px auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 10px 16px;
            background: green;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .button:hover {
            background: #006400;
        }

        .back-button {
            background: #555;
        }

        .back-button:hover {
            background: #444;
        }

        .table-container {
            overflow-x: auto;
            background: white;
            box-shadow: 0 0 8px #ccc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #333;
            color: white;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        tr:nth-child(even) {
            background: #f8f8f8;
        }

        .edit {
            display: inline-block;
            padding: 7px 10px;
            background: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 5px;
        }

        .edit:hover {
            background: #21618c;
        }

        .delete {
            display: inline-block;
            padding: 7px 10px;
            background: #c0392b;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .delete:hover {
            background: #962d22;
        }

        .delete-form {
            display: inline;
        }

        .no-data {
            text-align: center;
            padding: 25px;
            color: #666;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin-bottom: 20px;
            text-align: center;
        }

        @media (max-width: 700px) {
            th,
            td {
                padding: 8px;
                font-size: 13px;
            }
        }

    </style>

</head>

<body>

    <!-- HEADER -->

    <div class="header">
        <h2>Customer List</h2>
    </div>

    <div class="container">

        <!-- TOP BUTTONS -->

        <div class="top-bar">

            <a
                href="<?php echo e($backPage); ?>"
                class="button back-button"
            >
                &#8592; Back
            </a>

            <a
                href="add_customer.php"
                class="button"
            >
                + Add Customer
            </a>

        </div>

        <!-- SUCCESS MESSAGES -->

        <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') { ?>

            <div class="success-message">
                Customer updated successfully.
            </div>

        <?php } ?>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1') { ?>

            <div class="success-message">
                Customer deleted successfully.
            </div>

        <?php } ?>

        <!-- QUERY ERROR -->

        <?php if ($queryError !== "") { ?>

            <div class="error-message">
                <?php echo e($queryError); ?>
            </div>

        <?php } ?>

        <!-- CUSTOMER TABLE -->

        <?php if ($queryError === "") { ?>

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($result) > 0) { ?>

                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                                <tr>

                                    <td>
                                        <?php echo e($row['CustomerID']); ?>
                                    </td>

                                    <td>
                                        <?php echo e($row['CustomerName']); ?>
                                    </td>

                                    <td>
                                        <?php echo e($row['Phone']); ?>
                                    </td>

                                    <td>
                                        <?php echo e($row['Email']); ?>
                                    </td>

                                    <td>
                                        <?php echo e($row['Address']); ?>
                                    </td>

                                    <td>

                                        <!-- EDIT -->

                                        <a
                                            class="edit"
                                            href="edit_customer.php?id=<?php echo e($row['CustomerID']); ?>"
                                        >
                                            Edit
                                        </a>

                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            action="delete_customer.php"
                                            class="delete-form"
                                            onsubmit="return confirm('Are you sure you want to permanently delete this customer?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo e($csrfToken); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="customerID"
                                                value="<?php echo e($row['CustomerID']); ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php } ?>

                        <?php } else { ?>

                            <tr>
                                <td colspan="6" class="no-data">
                                    No customers found.
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </div>

</body>
</html>


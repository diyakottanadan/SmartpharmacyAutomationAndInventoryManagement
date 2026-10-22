<?php
include("db_connect.php");

$sql = "SELECT * FROM purchase_items";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Error loading purchase items: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Purchase Items</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 95%;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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

        .button {
            display: inline-block;
            padding: 10px 15px;
            background-color: green;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }

        .back {
            background-color: #555;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Purchase Items</h2>

    <a href="admin_dashboard.php" class="button back">
        Back to Dashboard
    </a>

    <a href="add_purchase_item.php" class="button">
        Add Purchase Item
    </a>

    <table>

        <tr>

            <?php

            /* Get the actual columns from purchase_items */

            $fields = mysqli_fetch_fields($result);

            foreach ($fields as $field) {

                echo "<th>";
                echo htmlspecialchars($field->name);
                echo "</th>";

            }

            ?>

        </tr>


        <?php

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_assoc($result)) {

                echo "<tr>";

                foreach ($row as $value) {

                    echo "<td>";
                    echo htmlspecialchars($value);
                    echo "</td>";

                }

                echo "</tr>";

            }

        } else {

            $fieldCount = mysqli_num_fields($result);

            echo "<tr>";

            echo "<td colspan='" . $fieldCount . "'>";
            echo "No purchase items found.";
            echo "</td>";

            echo "</tr>";

        }

        ?>

    </table>

</div>

</body>

</html>
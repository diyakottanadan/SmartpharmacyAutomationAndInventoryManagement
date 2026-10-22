<?php
session_start();
require_once 'db_connect.php';

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['pharmacist'])) {
    header('Location: login.php');
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validDate($date) {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (empty($_SESSION['sale_csrf'])) {
    $_SESSION['sale_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$today = date('Y-m-d');

$pharmacistID = (int)($_SESSION['PharmacistID'] ?? 0);

if ($pharmacistID <= 0) {
    die('Pharmacist session ID is missing. Please log in again.');
}

$customers = [];
$medicines = [];

try {
    $result = mysqli_query(
        $conn,
        "SELECT CustomerID, CustomerName, Phone
         FROM customer
         ORDER BY CustomerName"
    );

    $customers = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // CHANGE 1: Do not display expired medicines in New Sale.
    $result = mysqli_query(
        $conn,
        "SELECT MedicineID, MedicineName, BatchNo,
                SellingPrice, StockQty
         FROM medicine
         WHERE Status = 'Available'
           AND StockQty > 0
           AND ExpiryDate >= CURDATE()
         ORDER BY MedicineName"
    );

    $medicines = mysqli_fetch_all($result, MYSQLI_ASSOC);

} catch (Throwable $ex) {
    $error = 'Unable to load customer or medicine details.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['complete_sale'])) {

    try {
        if (!hash_equals(
            $_SESSION['sale_csrf'],
            (string)($_POST['csrf_token'] ?? '')
        )) {
            throw new Exception(
                'Invalid form session. Refresh the page and try again.'
            );
        }

        $customerMode = $_POST['customer_mode'] ?? 'existing';
        $saleDate = trim($_POST['SaleDate'] ?? '');
        $paymentMethod = $_POST['PaymentMethod'] ?? '';

        if (!validDate($saleDate) || $saleDate > $today) {
            throw new Exception('Please select a valid sale date.');
        }

        if (!in_array($paymentMethod, ['Cash', 'Card', 'UPI'], true)) {
            throw new Exception('Please select a valid payment method.');
        }

        $medicineIDs = $_POST['MedicineID'] ?? [];
        $quantities = $_POST['Quantity'] ?? [];

        if (
            !is_array($medicineIDs) ||
            !is_array($quantities) ||
            count($medicineIDs) === 0 ||
            count($medicineIDs) !== count($quantities)
        ) {
            throw new Exception('Please add at least one medicine.');
        }

        $items = [];
        $seen = [];

        foreach ($medicineIDs as $index => $medicineIDValue) {

            $medicineID = filter_var(
                $medicineIDValue,
                FILTER_VALIDATE_INT
            );

            $quantity = filter_var(
                $quantities[$index] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !$medicineID ||
                $medicineID <= 0 ||
                !$quantity ||
                $quantity <= 0
            ) {
                throw new Exception(
                    'Select a medicine and enter a valid quantity.'
                );
            }

            if (isset($seen[$medicineID])) {
                throw new Exception(
                    'Please select each medicine only once.'
                );
            }

            $seen[$medicineID] = true;

            $items[] = [
                'MedicineID' => $medicineID,
                'Quantity' => $quantity
            ];
        }

        // Validate customer information.
        $customerID = 0;
        $newCustomer = null;

        if ($customerMode === 'existing') {

            $customerID = filter_var(
                $_POST['CustomerID'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$customerID || $customerID <= 0) {
                throw new Exception(
                    'Please search for and select a customer.'
                );
            }

        } elseif ($customerMode === 'new') {

            $name = trim($_POST['CustomerName'] ?? '');
            $phone = trim($_POST['Phone'] ?? '');
            $email = trim($_POST['Email'] ?? '');
            $address = trim($_POST['Address'] ?? '');

            if ($name === '' || mb_strlen($name) > 15) {
                throw new Exception(
                    'Customer name is required and must be at most 15 characters.'
                );
            }

            if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
                throw new Exception(
                    'Enter a valid 10-digit Indian mobile number.'
                );
            }

            if (
                $email === '' ||
                mb_strlen($email) > 15 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new Exception(
                    'Enter a valid email address of at most 15 characters.'
                );
            }

            if ($address === '' || mb_strlen($address) > 15) {
                throw new Exception(
                    'Address is required and must be at most 15 characters.'
                );
            }

            $newCustomer = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ];

        } elseif ($customerMode !== 'walkin') {

            throw new Exception('Invalid customer selection.');
        }

        mysqli_begin_transaction($conn);

        // Add a new customer or reuse an existing customer.
        if ($customerMode === 'new') {

            $find = mysqli_prepare(
                $conn,
                "SELECT CustomerID
                 FROM customer
                 WHERE Phone = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $find,
                's',
                $newCustomer['phone']
            );

            mysqli_stmt_execute($find);

            $found = mysqli_stmt_get_result($find)->fetch_assoc();

            mysqli_stmt_close($find);

            if ($found) {

                $customerID = (int)$found['CustomerID'];

            } else {

                $insertCustomer = mysqli_prepare(
                    $conn,
                    "INSERT INTO customer
                     (CustomerName, Phone, Email, Address)
                     VALUES (?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insertCustomer,
                    'ssss',
                    $newCustomer['name'],
                    $newCustomer['phone'],
                    $newCustomer['email'],
                    $newCustomer['address']
                );

                mysqli_stmt_execute($insertCustomer);

                $customerID = mysqli_insert_id($conn);

                mysqli_stmt_close($insertCustomer);
            }

        } elseif ($customerMode === 'walkin') {

            // Use the permanent Walk-in customer record.
            $walkin = mysqli_query(
                $conn,
                "SELECT CustomerID
                 FROM customer
                 WHERE CustomerName = 'Walk-in'
                 ORDER BY CustomerID ASC
                 LIMIT 1"
            )->fetch_assoc();

            if ($walkin) {

                $customerID = (int)$walkin['CustomerID'];

            } else {

                $insertWalkin = mysqli_prepare(
                    $conn,
                    "INSERT INTO customer
                     (CustomerName, Phone, Email, Address)
                     VALUES ('Walk-in', '0000000000', '', '')"
                );

                mysqli_stmt_execute($insertWalkin);

                $customerID = mysqli_insert_id($conn);

                mysqli_stmt_close($insertWalkin);
            }
        }

        // Verify that the customer exists.
        $verifyCustomer = mysqli_prepare(
            $conn,
            "SELECT CustomerID
             FROM customer
             WHERE CustomerID = ?"
        );

        mysqli_stmt_bind_param(
            $verifyCustomer,
            'i',
            $customerID
        );

        mysqli_stmt_execute($verifyCustomer);

        $validCustomer = mysqli_stmt_get_result(
            $verifyCustomer
        )->fetch_assoc();

        mysqli_stmt_close($verifyCustomer);

        if (!$validCustomer) {
            throw new Exception(
                'The selected customer was not found.'
            );
        }

        // Lock medicine rows and calculate totals.
        $verifiedItems = [];
        $totalAmount = 0.0;

        // CHANGE 2: Include ExpiryDate in the database query.
        $lockMedicine = mysqli_prepare(
            $conn,
            "SELECT MedicineName, SellingPrice, StockQty,
                    Status, ExpiryDate
             FROM medicine
             WHERE MedicineID = ?
             FOR UPDATE"
        );

        foreach ($items as $item) {

            $id = $item['MedicineID'];
            $qty = $item['Quantity'];

            mysqli_stmt_bind_param(
                $lockMedicine,
                'i',
                $id
            );

            mysqli_stmt_execute($lockMedicine);

            $medicine = mysqli_stmt_get_result(
                $lockMedicine
            )->fetch_assoc();

            if (!$medicine) {
                throw new Exception(
                    'A selected medicine does not exist.'
                );
            }

            // CHANGE 3: Block expired medicines during checkout.
            if (
                !empty($medicine['ExpiryDate']) &&
                $medicine['ExpiryDate'] < date('Y-m-d')
            ) {
                throw new Exception(
                    'Cannot sell expired medicine: ' .
                    $medicine['MedicineName']
                );
            }

            // Check availability and stock quantity.
            if (
                $medicine['Status'] !== 'Available' ||
                (int)$medicine['StockQty'] < $qty
            ) {
                throw new Exception(
                    'Insufficient stock for ' .
                    $medicine['MedicineName'] .
                    '. Available quantity: ' .
                    $medicine['StockQty']
                );
            }

            $price = (float)$medicine['SellingPrice'];

            $subtotal = round($price * $qty, 2);

            $totalAmount = round(
                $totalAmount + $subtotal,
                2
            );

            $verifiedItems[] = [
                'MedicineID' => $id,
                'Quantity' => $qty,
                'SellingPrice' => $price,
                'SubTotal' => $subtotal
            ];
        }

        mysqli_stmt_close($lockMedicine);

        // Create the sale record.
        $insertSale = mysqli_prepare(
            $conn,
            "INSERT INTO sale
             (CustomerID, PharmacistID, SaleDate,
              TotalAmount, PaymentMethod)
             VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $insertSale,
            'iisds',
            $customerID,
            $pharmacistID,
            $saleDate,
            $totalAmount,
            $paymentMethod
        );

        mysqli_stmt_execute($insertSale);

        $saleID = mysqli_insert_id($conn);

        mysqli_stmt_close($insertSale);

        // Add sale items and reduce inventory.
        $insertItem = mysqli_prepare(
            $conn,
            "INSERT INTO sale_items
             (SaleID, MedicineID, Quantity, SellingPrice, SubTotal)
             VALUES (?, ?, ?, ?, ?)"
        );

        $updateStock = mysqli_prepare(
            $conn,
            "UPDATE medicine
             SET StockQty = StockQty - ?,
                 Status = CASE
                     WHEN StockQty - ? <= 0 THEN 'Unavailable'
                     ELSE 'Available'
                 END
             WHERE MedicineID = ? AND StockQty >= ?"
        );

        foreach ($verifiedItems as $item) {

            $id = $item['MedicineID'];
            $qty = $item['Quantity'];
            $price = $item['SellingPrice'];
            $subtotal = $item['SubTotal'];

            mysqli_stmt_bind_param(
                $insertItem,
                'iiidd',
                $saleID,
                $id,
                $qty,
                $price,
                $subtotal
            );

            mysqli_stmt_execute($insertItem);

            mysqli_stmt_bind_param(
                $updateStock,
                'iiii',
                $qty,
                $qty,
                $id,
                $qty
            );

            mysqli_stmt_execute($updateStock);

            if (mysqli_stmt_affected_rows($updateStock) !== 1) {
                throw new Exception(
                    'Stock update failed. Please try again.'
                );
            }
        }

        mysqli_stmt_close($insertItem);
        mysqli_stmt_close($updateStock);

        mysqli_commit($conn);

        // Open the invoice after completing the sale.
        header('Location: invoice.php?SaleID=' . $saleID);
        exit();

    } catch (Throwable $ex) {

        try {
            mysqli_rollback($conn);
        } catch (Throwable $ignored) {
            // No active transaction to roll back.
        }

        $error = $ex instanceof mysqli_sql_exception
            ? 'The sale could not be saved. Check the customer details and database constraints.'
            : $ex->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Sale - Smart Pharmacy</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #f4f7fb;
    color: #243247;
    font-family: Arial, sans-serif;
}

.container {
    max-width: 1050px;
    margin: 0 auto;
}

h1 {
    margin-top: 0;
    color: #17365d;
}

h2 {
    font-size: 20px;
    margin-top: 0;
}

.card {
    background: #fff;
    padding: 22px;
    margin-bottom: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    padding: 11px;
    margin-bottom: 15px;
    border: 1px solid #ccd5e0;
    border-radius: 6px;
    font-size: 15px;
}

input:focus,
select:focus {
    outline: 2px solid #b9d8ff;
    border-color: #3979c3;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.customer-options {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.customer-options label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-weight: normal;
}

.customer-options input {
    width: auto;
    margin: 0;
}

.medicine-row {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 12px;
    align-items: start;
}

.medicine-row button {
    margin-top: 0;
}

button,
.back-link {
    display: inline-block;
    border: 0;
    border-radius: 6px;
    padding: 11px 16px;
    cursor: pointer;
    font-size: 15px;
    text-decoration: none;
}

.primary {
    color: white;
    background: #1769aa;
}

.secondary {
    background: #e7edf5;
    color: #243247;
}

.danger {
    background: #ffe5e5;
    color: #a32121;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.total {
    font-size: 23px;
    font-weight: bold;
    color: #1769aa;
    margin: 15px 0 20px;
}

.error {
    padding: 12px;
    border-radius: 6px;
    background: #ffe5e5;
    color: #9c2020;
    margin-bottom: 18px;
}

.hint {
    color: #65758b;
    font-size: 13px;
    margin-top: -8px;
    margin-bottom: 14px;
}

@media (max-width: 650px) {
    body {
        padding: 12px;
    }

    .grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .medicine-row {
        grid-template-columns: 1fr 1fr;
    }

    .medicine-row .medicine-select {
        grid-column: 1 / -1;
    }
}
</style>
</head>

<body>
<div class="container">

    <h1>New Sale</h1>

    <p>
        Create a sale, select the customer, and update stock automatically.
    </p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="saleForm">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['sale_csrf']) ?>"
        >

        <div class="card">

            <h2>Customer Details</h2>

            <div class="customer-options">

                <label>
                    <input
                        type="radio"
                        name="customer_mode"
                        value="existing"
                        checked
                    >
                    Existing Customer
                </label>

                <label>
                    <input
                        type="radio"
                        name="customer_mode"
                        value="new"
                    >
                    New Customer
                </label>

                <label>
                    <input
                        type="radio"
                        name="customer_mode"
                        value="walkin"
                    >
                    Walk-in Customer
                </label>

            </div>

            <div id="existingCustomer">

                <label for="customerSearch">
                    Search by name or mobile number
                </label>

                <input
                    type="text"
                    id="customerSearch"
                    placeholder="Type a name or phone number"
                >

                <label for="CustomerID">
                    Select Customer
                </label>

                <select name="CustomerID" id="CustomerID">

                    <option value="">
                        -- Select a customer --
                    </option>

                    <?php foreach ($customers as $c): ?>

                        <?php if ($c['CustomerName'] !== 'Walk-in'): ?>

                            <option
                                value="<?= (int)$c['CustomerID'] ?>"
                                data-name="<?= e($c['CustomerName']) ?>"
                                data-phone="<?= e($c['Phone']) ?>"
                            >
                                <?= e($c['CustomerName']) ?>
                                — <?= e($c['Phone']) ?>
                            </option>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </select>

            </div>

            <div id="newCustomer" hidden>

                <div class="grid">

                    <div>
                        <label for="newName">Customer Name</label>

                        <input
                            type="text"
                            name="CustomerName"
                            id="newName"
                            maxlength="15"
                            placeholder="Customer name"
                        >
                    </div>

                    <div>
                        <label for="newPhone">Mobile Number</label>

                        <input
                            type="tel"
                            name="Phone"
                            id="newPhone"
                            maxlength="10"
                            pattern="[6-9][0-9]{9}"
                            placeholder="10-digit mobile number"
                        >
                    </div>

                    <div>
                        <label for="newEmail">Email</label>

                        <input
                            type="email"
                            name="Email"
                            id="newEmail"
                            maxlength="15"
                            placeholder="Email (max 15 characters)"
                        >
                    </div>

                    <div>
                        <label for="newAddress">Address</label>

                        <input
                            type="text"
                            name="Address"
                            id="newAddress"
                            maxlength="15"
                            placeholder="Address"
                        >
                    </div>

                </div>

                <p class="hint">
                    If the mobile number already exists, that customer
                    record will be reused.
                </p>

            </div>

            <div id="walkinCustomer" hidden>

                <p>
                    No customer name, phone number, email, or address
                    is required. The sale will be recorded under Walk-in.
                </p>

            </div>

            <div class="grid">

                <div>
                    <label for="SaleDate">Sale Date</label>

                    <input
                        type="date"
                        name="SaleDate"
                        id="SaleDate"
                        value="<?= e($today) ?>"
                        max="<?= e($today) ?>"
                        required
                    >
                </div>

                <div>
                    <label for="PaymentMethod">Payment Method</label>

                    <select
                        name="PaymentMethod"
                        id="PaymentMethod"
                        required
                    >
                        <option value="">
                            -- Select payment --
                        </option>

                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>

            </div>

        </div>

        <div class="card">

            <h2>Medicine Details</h2>

            <div id="medicineRows">

                <div class="medicine-row">

                    <div class="medicine-select">

                        <label>Medicine</label>

                        <select
                            name="MedicineID[]"
                            class="medicine"
                            required
                        >

                            <option value="">
                                -- Select medicine --
                            </option>

                            <?php foreach ($medicines as $m): ?>

                                <option
                                    value="<?= (int)$m['MedicineID'] ?>"
                                    data-price="<?= e($m['SellingPrice']) ?>"
                                    data-stock="<?= (int)$m['StockQty'] ?>"
                                >
                                    <?= e($m['MedicineName']) ?>

                                    (Batch: <?= e($m['BatchNo']) ?>,
                                    Stock: <?= (int)$m['StockQty'] ?>,
                                    ₹<?= e($m['SellingPrice']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div>

                        <label>Quantity</label>

                        <input
                            type="number"
                            name="Quantity[]"
                            class="quantity"
                            min="1"
                            value="1"
                            required
                        >

                        <small class="stock-hint"></small>

                    </div>

                    <div>

                        <label>&nbsp;</label>

                        <button
                            type="button"
                            class="danger remove-row"
                        >
                            Remove
                        </button>

                    </div>

                </div>

            </div>

            <button
                type="button"
                class="secondary"
                id="addMedicine"
            >
                + Add Another Medicine
            </button>

            <div class="total">
                Total: ₹<span id="totalAmount">0.00</span>
            </div>

            <div class="actions">

                <button
                    type="submit"
                    name="complete_sale"
                    value="1"
                    class="primary"
                >
                    Complete Sale
                </button>

                <a
                    href="javascript:history.back()"
                    class="back-link secondary"
                >
                    Cancel
                </a>

            </div>

        </div>

    </form>

</div>

<script>
const radios = document.querySelectorAll(
    'input[name="customer_mode"]'
);

const existingBox = document.getElementById('existingCustomer');
const newBox = document.getElementById('newCustomer');
const walkinBox = document.getElementById('walkinCustomer');
const customerSelect = document.getElementById('CustomerID');
const customerSearch = document.getElementById('customerSearch');

function updateCustomerMode() {

    const mode = document.querySelector(
        'input[name="customer_mode"]:checked'
    ).value;

    existingBox.hidden = mode !== 'existing';
    newBox.hidden = mode !== 'new';
    walkinBox.hidden = mode !== 'walkin';

    customerSelect.required = mode === 'existing';

    [
        'newName',
        'newPhone',
        'newEmail',
        'newAddress'
    ].forEach(id => {
        document.getElementById(id).required = mode === 'new';
    });
}

radios.forEach(radio => {
    radio.addEventListener('change', updateCustomerMode);
});

updateCustomerMode();

// Search customers by name or mobile number.
customerSearch.addEventListener('input', function () {

    const query = this.value.trim().toLowerCase();

    Array.from(customerSelect.options).forEach((option, index) => {

        if (index === 0) {
            return;
        }

        const text = (
            (option.dataset.name || '') + ' ' +
            (option.dataset.phone || '')
        ).toLowerCase();

        option.hidden = !text.includes(query);
    });

    if (customerSelect.selectedOptions[0]?.hidden) {
        customerSelect.value = '';
    }
});

const rowsContainer = document.getElementById('medicineRows');

function updateTotal() {

    let total = 0;

    rowsContainer.querySelectorAll('.medicine-row').forEach(row => {

        const select = row.querySelector('.medicine');
        const quantity = row.querySelector('.quantity');
        const option = select.selectedOptions[0];

        const price = parseFloat(option?.dataset.price || '0');
        const stock = parseInt(option?.dataset.stock || '0', 10);
        const qty = parseInt(quantity.value || '0', 10);

        quantity.max = stock > 0 ? stock : '';

        row.querySelector('.stock-hint').textContent =
            option && option.value
                ? 'Available: ' + stock
                : '';

        total += price * (qty > 0 ? qty : 0);
    });

    document.getElementById('totalAmount').textContent =
        total.toFixed(2);
}

function addMedicineRow() {

    const first = rowsContainer.querySelector('.medicine-row');
    const clone = first.cloneNode(true);

    clone.querySelector('.medicine').value = '';
    clone.querySelector('.quantity').value = '1';

    clone.querySelector('.quantity').removeAttribute('max');

    clone.querySelector('.stock-hint').textContent = '';

    rowsContainer.appendChild(clone);

    updateTotal();
}

document.getElementById('addMedicine').addEventListener(
    'click',
    addMedicineRow
);

rowsContainer.addEventListener('change', function (event) {

    if (event.target.matches('.medicine, .quantity')) {
        updateTotal();
    }
});

rowsContainer.addEventListener('input', function (event) {

    if (event.target.matches('.quantity')) {
        updateTotal();
    }
});

rowsContainer.addEventListener('click', function (event) {

    if (event.target.matches('.remove-row')) {

        const rows = rowsContainer.querySelectorAll('.medicine-row');

        if (rows.length > 1) {

            event.target.closest('.medicine-row').remove();

            updateTotal();

        } else {

            alert('At least one medicine row is required.');
        }
    }
});

document.getElementById('saleForm').addEventListener(
    'submit',
    function (event) {

        const mode = document.querySelector(
            'input[name="customer_mode"]:checked'
        ).value;

        if (mode === 'existing' && !customerSelect.value) {

            event.preventDefault();

            alert('Please search for and select a customer.');

            return;
        }

        const selectedIDs = [];
        let valid = true;

        rowsContainer.querySelectorAll('.medicine-row').forEach(row => {

            const select = row.querySelector('.medicine');
            const quantity = row.querySelector('.quantity');
            const option = select.selectedOptions[0];

            const id = select.value;
            const qty = parseInt(quantity.value || '0', 10);
            const stock = parseInt(option?.dataset.stock || '0', 10);

            if (
                !id ||
                qty < 1 ||
                qty > stock ||
                selectedIDs.includes(id)
            ) {
                valid = false;
            }

            if (id) {
                selectedIDs.push(id);
            }
        });

        if (!valid) {

            event.preventDefault();

            alert(
                'Check the selected medicines, quantities, stock, and duplicate entries.'
            );

            return;
        }

        if (!confirm('Complete this sale?')) {
            event.preventDefault();
        }
    }
);

updateTotal();
</script>

</body>
</html>
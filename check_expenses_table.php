<?php
include("connect.php");

// First, check if the notes column exists
$check_query = "SHOW COLUMNS FROM expenses LIKE 'notes'";
$result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($result) == 0) {
    // Notes column doesn't exist, so add it
    $alter_query = "ALTER TABLE expenses ADD COLUMN notes TEXT NULL";
    if (mysqli_query($conn, $alter_query)) {
        echo "Notes column added successfully";
    } else {
        echo "Error adding notes column: " . mysqli_error($conn);
    }
} else {
    echo "Notes column already exists";
}

// Check if receipt_image column exists
$check_query = "SHOW COLUMNS FROM expenses LIKE 'receipt_image'";
$result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($result) == 0) {
    // receipt_image column doesn't exist, so add it
    $alter_query = "ALTER TABLE expenses ADD COLUMN receipt_image VARCHAR(255) NULL";
    if (mysqli_query($conn, $alter_query)) {
        echo "\nReceipt image column added successfully";
    } else {
        echo "\nError adding receipt_image column: " . mysqli_error($conn);
    }
} else {
    echo "\nReceipt image column already exists";
}

// Show the current table structure
$desc_query = "DESCRIBE expenses";
$result = mysqli_query($conn, $desc_query);
echo "\n\nCurrent expenses table structure:\n";
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

mysqli_close($conn);
?> 
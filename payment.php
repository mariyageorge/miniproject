<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Include connection file
include_once 'connect.php';

// Create payments table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    payment_id VARCHAR(255) NOT NULL,
    order_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === FALSE) {
    echo "Error creating table: " . $conn->error;
}

// Get username from session
$username = $_SESSION['username'];

// Fetch user details directly from the database based on the schema provided
$user_id = 0;
$email = '';
$phone = '';
$name = '';
$role = '';
$profile_pic = null;

$query = "SELECT user_id, email, phone, username, role, profile_pic FROM users WHERE username = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $email, $phone, $username, $role, $profile_pic);
    
    if ($stmt->fetch()) {
        // Use username as name if no separate name field exists
        $name = $username;
    } else {
        // Handle case where user isn't found
        echo "User not found!";
        exit();
    }
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
    exit();
}

// Set default values for premium subscription
$amount = 199.00; // Default amount from upgrade page

// Fetch user's currency preference
$currency_query = "SELECT currency_symbol FROM user_currency_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $currency_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$currency_result = mysqli_stmt_get_result($stmt);
$currency_pref = mysqli_fetch_assoc($currency_result);
$currency_symbol = $currency_pref['currency_symbol'] ?? '₹'; // Default to ₹ if no preference set

// Razorpay credentials
$razorpay_key_id = "rzp_test_j6ZARUQlnkvesy";
$razorpay_key_secret = "o4vFaPvOvANpqF9X8FcyqGka";

// Convert amount to paise
$order_amount = $amount * 100;

// Order Data
$orderData = [
    'receipt'   => 'receipt_' . rand(1000, 9999),
    'amount'    => $order_amount, // Amount in paise
    'currency'  => 'INR'
];

// Create Razorpay Order
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ":" . $razorpay_key_secret);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    error_log('cURL Error: ' . curl_error($ch));
}
else {
    // Log the response for debugging
    error_log("Razorpay Response: " . $response);
}

curl_close($ch);

// Decode API response
$order = json_decode($response);
$orderId = $order->id ?? '';

if ($http_code !== 200 || empty($orderId)) {
    echo "<pre>Error Response from Razorpay: ";
    print_r($response);
    error_log($http_code . " " . $response); // Log the error for debugging
    echo "</pre>";
    die("Error creating Razorpay order.");
}

// Save order details to session
$_SESSION['order_id'] = $orderId;
$_SESSION['amount'] = $amount;
$_SESSION['user_id'] = $user_id;

// Get subscription details (can be expanded later)
$plan_name = "LifeSync Premium";
$duration = "1 Month";
$benefits = ["Analyze health journey", "Unlimited Todos", "Advanced Analytics", "Priority Support"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - LifeSync Premium</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
       :root {
    --primary-color: #6F4E37;
    --secondary-color: #8B4513;
    --accent-color: #D2691E;
    --bg-color: #F4ECD8;
    --card-bg: #F5DEB3;
    --text-primary: #3E2723;
    --text-secondary: #5D4037;
    --success-color: #4CAF50;
    --info-color: #2196F3;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #F4ECD8, #E6D5B8);
    margin: 0;
    padding: 0;
    min-height: 100vh;
}

.main-header {
    background-color: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1rem 0;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    box-shadow: 0 2px 8px rgba(111, 78, 55, 0.5);
}

.logo-text {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0;
}

.page-content {
    padding: 40px 0;
}

.payment-container {
    background-color: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 40px;
}

.payment-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 25px;
    text-align: center;
}

.payment-title {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.payment-subtitle {
    font-size: 1rem;
    opacity: 0.8;
}

.payment-body {
    padding: 30px;
}

.order-summary {
    background-color: #f8f9fa;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

.summary-heading {
    color: var(--primary-color);
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.order-details {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.order-details th {
    background-color: #f0f0f0;
    color: var(--text-primary);
    padding: 15px;
    text-align: left;
    font-weight: 600;
}

.order-details td {
    padding: 15px;
    border-top: 1px solid #eee;
}

.price-row {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.benefits-list {
    list-style-type: none;
    padding: 0;
    margin: 0;
}

.benefits-list li {
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.benefits-list li i {
    color: var(--success-color);
}

.payment-form {
    background-color: #fff;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

.form-heading {
    color: var(--primary-color);
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    color: var(--text-primary);
    font-weight: 500;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 15px;
    width: 100%;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(111, 78, 55, 0.2);
    outline: none;
}

.input-icon-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    top: 50%;
    left: 15px;
    transform: translateY(-50%);
    color: #aaa;
}

.icon-input {
    padding-left: 45px;
}

.pay-button {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    font-size: 1.2rem;
    font-weight: 600;
    padding: 14px 20px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: 0.3s;
    width: 100%;
    margin-top: 20px;
    box-shadow: 0 4px 15px rgba(111, 78, 55, 0.4);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.pay-button:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(111, 78, 55, 0.5);
}

.back-button {
    padding: 8px 16px;
    background: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.back-button:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.secure-payment {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 30px;
    color: var(--text-secondary);
    gap: 10px;
}

.payment-methods {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 15px;
}

.payment-method-icon {
    font-size: 1.8rem;
    color: #666;
}

.footer {
    background-color: rgba(255, 255, 255, 0.8);
    padding: 20px 0;
    text-align: center;
    color: var(--text-secondary);
    border-top: 1px solid #eee;
    margin-top: 40px;
}

/* Additional styles for the accordion */
.accordion {
    margin-top: 30px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

.accordion-item {
    border: none;
    background-color: white;
}

.accordion-button {
    padding: 15px 20px;
    font-weight: 500;
    color: var(--text-primary);
    background-color: white;
}

.accordion-button:not(.collapsed) {
    color: var(--primary-color);
    background-color: rgba(111, 78, 55, 0.05);
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: rgba(111, 78, 55, 0.1);
}

.accordion-button::after {
    background-size: 16px;
    color: var(--primary-color);
}

.accordion-body {
    padding: 15px 20px;
    color: var(--text-secondary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .payment-container {
        margin: 20px;
    }
    
    .payment-body {
        padding: 20px;
    }
    
    .order-summary, .payment-form {
        padding: 15px;
    }
    
    .payment-methods {
        flex-wrap: wrap;
    }
}

/* Additional features */
.premium-badge {
    display: inline-block;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 10px;
    box-shadow: 0 2px 5px rgba(255, 165, 0, 0.3);
}

/* Visual improvements */
.payment-container {
    position: relative;
    overflow: visible;
}

.payment-container::before {
    content: '';
    position: absolute;
    top: -10px;
    right: -10px;
    bottom: -10px;
    left: -10px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    z-index: -1;
    border-radius: 25px;
    opacity: 0.1;
}

/* Animated elements */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.pay-button {
    animation: pulse 2s infinite;
}

.pay-button:hover {
    animation: none;
}

/* Form focus effects */
.form-control:focus + .input-icon {
    color: var(--primary-color);
}

/* Progress indicator */
.payment-progress {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding: 0 20px;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 1;
}

.progress-step::before {
    content: '';
    position: absolute;
    top: 15px;
    left: calc(-50% + 15px);
    right: calc(50% + 15px);
    height: 2px;
    background-color: #ddd;
    z-index: 1;
}

.progress-step:first-child::before {
    display: none;
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #ddd;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-bottom: 5px;
    position: relative;
    z-index: 2;
}

.step-label {
    font-size: 0.8rem;
    color: #777;
}

.progress-step.active .step-number {
    background-color: var(--primary-color);
}

.progress-step.active .step-label {
    color: var(--primary-color);
    font-weight: 600;
}

.progress-step.completed .step-number {
    background-color: var(--success-color);
}

.progress-step.completed::before {
    background-color: var(--success-color);
}
    </style>
</head>
<body>

    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-container">
                <a class="logo" href="index.php">
                    <div class="logo-icon">
                        <i class="fas fa-infinity"></i>
                    </div>
                    <span class="logo-text">LIFE-SYNC</span>
                </a>
                <div>
                    <a href="upgrade.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> 
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="page-content">
        <div class="container">
            <div class="payment-container">
                <!-- Payment Header -->
                <div class="payment-header">
                    <h1 class="payment-title">Complete Your Payment</h1>
                    <p class="payment-subtitle">You're just one step away from unlocking premium features!</p>
                </div>
                
                <!-- Payment Body -->
                <div class="payment-body">
                    <div class="row">
                        <!-- Left Column - Order Summary -->
                        <div class="col-lg-6 mb-4 mb-lg-0">
                            <div class="order-summary">
                                <h3 class="summary-heading">
                                    <i class="fas fa-clipboard-list"></i> Order Summary
                                </h3>
                                <table class="order-details">
                                    <tr>
                                        <th>Plan</th>
                                        <td><?php echo $plan_name; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Duration</th>
                                        <td><?php echo $duration; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Order ID</th>
                                        <td><?php echo $orderId; ?></td>
                                    </tr>
                                    <tr class="price-row">
                                        <th>Amount</th>
                                        <td><?php echo $currency_symbol; ?> <?php echo number_format($amount, 2); ?></td>
                                    </tr>
                                </table>
                                
                                <div class="mt-4">
                                    <h4 class="mb-3">What's included:</h4>
                                    <ul class="benefits-list">
                                        <?php foreach ($benefits as $benefit): ?>
                                            <li><i class="fas fa-check-circle"></i> <?php echo $benefit; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column - Payment Form -->
                        <div class="col-lg-6">
                            <div class="payment-form">
                                <h3 class="form-heading">
                                    <i class="fas fa-credit-card"></i> Payment Information
                                </h3>
                                <form id="payment-form" method="POST" action="confirm_payment.php">
                                    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                    <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="name">Full Name</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-user input-icon"></i>
                                            <input type="text" class="form-control icon-input" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="email">Email Address</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-envelope input-icon"></i>
                                            <input type="email" class="form-control icon-input" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="phone">Phone Number</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-phone input-icon"></i>
                                            <input type="tel" class="form-control icon-input" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="pay-button" id="razorpay-button">
                                        <i class="fas fa-lock"></i> Pay <?php echo $currency_symbol; ?><?php echo number_format($amount, 2); ?>
                                    </button>
                                </form>
                                
                                <div class="secure-payment">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Secure Payment Processing</span>
                                </div>
                                
                                <div class="payment-methods">
                                    <i class="fab fa-cc-visa payment-method-icon"></i>
                                    <i class="fab fa-cc-mastercard payment-method-icon"></i>
                                    <i class="fab fa-cc-amex payment-method-icon"></i>
                                    <i class="fab fa-cc-discover payment-method-icon"></i>
                                    <i class="fab fa-google-pay payment-method-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <div class="accordion" id="paymentFAQ">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading1">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="false" aria-controls="collapse1">
                            How is my payment information secured?
                        </button>
                    </h2>
                    <div id="collapse1" class="accordion-collapse collapse" aria-labelledby="heading1" data-bs-parent="#paymentFAQ">
                        <div class="accordion-body">
                            Your payment information is secured using industry-standard encryption. We use Razorpay, a PCI DSS compliant payment gateway, which ensures that your card details are never stored on our servers.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading2">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                            When will I be charged?
                        </button>
                    </h2>
                    <div id="collapse2" class="accordion-collapse collapse" aria-labelledby="heading2" data-bs-parent="#paymentFAQ">
                        <div class="accordion-body">
                            You will be charged immediately when you complete the payment. Your subscription will start as soon as the payment is confirmed.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading3">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                            What is your refund policy?
                        </button>
                    </h2>
                    <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="heading3" data-bs-parent="#paymentFAQ">
                        <div class="accordion-body">
                            We offer a 7-day money-back guarantee. If you're not satisfied with our premium features, you can request a refund within 7 days of your purchase.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 LifeSync. All rights reserved.</p>
            <div class="mt-2">
                <a href="terms.php" class="text-decoration-none me-3">Terms of Service</a>
                <a href="privacy.php" class="text-decoration-none me-3">Privacy Policy</a>
                <a href="contact.php" class="text-decoration-none">Contact Us</a>
            </div>
        </div>
    </footer>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('razorpay-button').onclick = function(e) {
            var options = {
                "key": "<?php echo $razorpay_key_id; ?>",
                "amount": "<?php echo $order_amount; ?>", // Amount in smallest currency unit
                "currency": "INR",
                "name": "LifeSync Premium",
                "description": "Premium Subscription",
                "image": "https://yourdomain.com/logo.png", // Replace with your logo URL
                "order_id": "<?php echo $orderId; ?>",
                "handler": function (response) {
                    // Add payment ID to form and submit
                    var hiddenInput = document.createElement('input');
                    hiddenInput.setAttribute('type', 'hidden');
                    hiddenInput.setAttribute('name', 'payment_id');
                    hiddenInput.setAttribute('value', response.razorpay_payment_id);
                    document.getElementById('payment-form').appendChild(hiddenInput);
                    
                    // Submit form to confirm payment
                    document.getElementById('payment-form').submit();
                },
                "prefill": {
                    "name": "<?php echo $name; ?>",
                    "email": "<?php echo $email; ?>",
                    "contact": "<?php echo $phone; ?>"
                },
                "theme": {
                    "color": "#6F4E37"
                }
            };
            var rzp = new Razorpay(options);
            rzp.open();
            e.preventDefault();
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
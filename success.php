<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
include 'header.php'; 

// Handle receipt view
if (isset($_POST['view_receipt'])) {
    $receipt_no = 'LSP-' . date('Ymd') . '-' . rand(1000, 9999);
    $date = date('F d, Y');
    
    // Output receipt HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>LifeSync Premium Receipt</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                font-family: "Segoe UI", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                margin: 0;
                padding: 20px;
            }
            .receipt-container {
                max-width: 800px;
                margin: 20px auto;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .receipt-header {
                background: #fff;
                padding: 30px;
                border-bottom: 2px solid #8b4513;
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .logo-icon {
                color: #FF9800;
                font-size: 32px;
            }
            .brand {
                flex-grow: 1;
            }
            .brand-name {
                font-size: 28px;
                color: #8b4513;
                margin: 0;
                font-weight: 600;
            }
            .receipt-label {
                color: #666;
                font-size: 16px;
                margin: 5px 0 0 0;
            }
            .receipt-body {
                padding: 30px;
            }
            .receipt-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 30px;
                gap: 20px;
            }
            .info-block {
                background: #f8f8f8;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #8b4513;
            }
            .info-block p {
                margin: 5px 0;
            }
            .success-badge {
                display: inline-block;
                background: #4CAF50;
                color: white;
                padding: 5px 15px;
                border-radius: 15px;
                font-size: 14px;
                margin-top: 10px;
            }
            .success-badge i {
                margin-right: 5px;
            }
            .receipt-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                margin: 20px 0;
            }
            .receipt-table th {
                background: #8b4513;
                color: white;
                padding: 15px;
                text-align: left;
            }
            .receipt-table td {
                padding: 15px;
                border-bottom: 1px solid #eee;
            }
            .receipt-table tr:last-child td {
                border-bottom: none;
                background: #f8f8f8;
                font-weight: bold;
            }
            .amount-col {
                text-align: right;
            }
            .receipt-footer {
                text-align: center;
                padding: 30px;
                background: #f8f8f8;
                border-top: 1px solid #eee;
            }
            .thank-you {
                color: #8b4513;
                font-size: 18px;
                margin-bottom: 10px;
            }
            .footer-note {
                color: #666;
                font-size: 14px;
            }
            .actions {
                margin-top: 20px;
                display: flex;
                justify-content: center;
                gap: 10px;
            }
            .action-btn {
                background: #8b4513;
                color: white;
                border: none;
                padding: 12px 25px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            }
            .action-btn:hover {
                background: #6d3611;
            }
            .action-btn.secondary {
                background: #666;
            }
            .action-btn.secondary:hover {
                background: #555;
            }
            @media print {
                .actions { display: none; }
                body { background: white; }
                .receipt-container { box-shadow: none; }
            }
        </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    </head>
    <body>
        <div class="receipt-container" id="receipt">
            <div class="receipt-header">
                <div class="logo-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="brand">
                    <h1 class="brand-name">LifeSync Premium</h1>
                    <p class="receipt-label">Payment Receipt</p>
                </div>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-info">
                    <div class="info-block">
                        <p><strong>Receipt No:</strong> ' . $receipt_no . '</p>
                        <p><strong>Date:</strong> ' . $date . '</p>
                        <div class="success-badge">
                            <i class="fas fa-check-circle"></i> Payment Successful
                        </div>
                    </div>
                    <div class="info-block">
                        <p><strong>Customer Information</strong></p>
                        <p><i class="fas fa-user"></i> ' . htmlspecialchars($_SESSION['username']) . '</p>
                        <p><i class="fas fa-crown"></i> Premium Member</p>
                    </div>
                </div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>LifeSync Premium Subscription</strong><br>
                                <span style="color: #666;">1 Year Access to Premium Features</span>
                            </td>
                            <td class="amount-col">Rs 199</td>
                        </tr>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td class="amount-col"><strong>Rs 199</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="receipt-footer">
                <div class="thank-you">Thank you for choosing LifeSync Premium!</div>
                <div class="footer-note">
                    This is a computer-generated receipt and requires no signature.<br>
                    For support, contact us at support@lifesync.com
                </div>
            </div>
        </div>
        
        <div class="actions">
            <button onclick="downloadPDF()" class="action-btn">
                <i class="fas fa-download"></i> Download PDF
            </button>
            <button onclick="window.close()" class="action-btn secondary">
                <i class="fas fa-times"></i> Close
            </button>
        </div>

        <script>
            function downloadPDF() {
                const element = document.getElementById("receipt");
                const opt = {
                    margin: 0.5,
                    filename: "LifeSync_Receipt_' . $receipt_no . '.pdf",
                    image: { type: "jpeg", quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: "mm", format: "a4", orientation: "portrait" }
                };

                // Hide buttons during PDF generation
                document.querySelector(".actions").style.display = "none";
                
                html2pdf().set(opt).from(element).save().then(function() {
                    // Show buttons after PDF is generated
                    document.querySelector(".actions").style.display = "flex";
                });
            }

            document.title = "LifeSync_Receipt_' . $receipt_no . '";
        </script>
    </body>
    </html>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5D4037;
            --secondary-color: #8D6E63;
            --accent-color: #FF9800;
            --bg-color: #F5F5F5;
            --text-primary: #3E2723;
            --text-secondary: #6D4C41;
        }
        
        body {
            background-color: #F8F5F2;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-container {
            max-width: 500px;
            margin: 50px auto;
            text-align: center;
            padding: 30px;
            background: #FFFFFF;
            border-radius: 15px;
            box-shadow: 0px 10px 30px rgba(93, 64, 55, 0.15);
            position: relative;
            overflow: hidden;
            border-top: 5px solid var(--accent-color);
        }
        
        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 15px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .success-container h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .success-container p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .success-container strong {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .dashboard-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 10px rgba(93, 64, 55, 0.2);
        }
        
        .dashboard-btn:hover {
            background-color: #4E342E;
            transform: translateY(-3px);
            box-shadow: 0px 6px 15px rgba(93, 64, 55, 0.3);
        }
        
        .premium-badge {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 15px;
            background-color: rgba(255, 152, 0, 0.15);
            color: var(--accent-color);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .premium-badge i {
            margin-right: 5px;
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f2d74e;
            opacity: 0;
        }
        
        @keyframes confetti-fall {
            0% {
                opacity: 1;
                top: -10%;
                transform: translateY(0) rotate(0deg);
            }
            100% {
                opacity: 0.2;
                top: 100%;
                transform: translateY(1000px) rotate(720deg);
            }
        }
        
        .thank-you-msg {
            margin-top: 20px;
            font-style: italic;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .receipt-btn {
            background-color: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            margin-left: 10px;
            text-decoration: none;
        }
        
        .receipt-btn:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0px 6px 15px rgba(93, 64, 55, 0.3);
        }

        .buttons-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <br><br><br>
    <div class="container">
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="mt-3">Payment Successful!</h2>
            <p>Thank you for subscribing to <strong>LifeSync Premium</strong>. Your payment has been processed successfully.</p>
            <div class="premium-badge">
                <i class="fas fa-crown"></i> Premium Member
            </div>
            <div class="buttons-container">
                <a href="dashboard.php" class="btn dashboard-btn">
                    <i class="fas fa-home me-2"></i>Go to Dashboard
                </a>
                <form method="POST" style="display: inline;" target="_blank">
                    <button type="submit" name="view_receipt" class="btn receipt-btn">
                        <i class="fas fa-file-pdf me-2"></i>Download Receipt
                    </button>
                </form>
            </div>
            <p class="thank-you-msg">We appreciate your trust in LifeSync!</p>
            
            <!-- Confetti Elements will be added here by JS -->
        </div>
    </div>
    
    <script>
        // Create confetti effect when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.success-container');
            const colors = ['#5D4037', '#8D6E63', '#FF9800', '#FFCC80', '#4CAF50', '#A1887F'];
            
            // Create multiple confetti elements
            for (let i = 0; i < 100; i++) {
                createConfetti(container, colors);
            }
            
            // Initial burst of confetti
            burstConfetti();
        });
        
        function createConfetti(container, colors) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            
            // Random size between 5px and 10px
            const size = Math.floor(Math.random() * 6) + 5;
            confetti.style.width = `${size}px`;
            confetti.style.height = `${size}px`;
            
            // Random color from the colors array
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            
            // Random rotation
            confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
            
            // Random position horizontally
            confetti.style.left = `${Math.random() * 100}%`;
            
            // Set up the animation but don't start it yet
            confetti.style.position = 'absolute';
            confetti.style.top = '-10%';
            
            // Add to container
            container.appendChild(confetti);
            
            return confetti;
        }
        
        function burstConfetti() {
            const confettis = document.querySelectorAll('.confetti');
            
            confettis.forEach((confetti, index) => {
                // Stagger the animations
                setTimeout(() => {
                    // Random animation duration between 2s and 5s
                    const duration = (Math.random() * 3) + 2;
                    
                    confetti.style.animation = `confetti-fall ${duration}s ease forwards`;
                    
                    // Remove after animation is complete
                    setTimeout(() => {
                        confetti.remove();
                    }, duration * 1000);
                }, index * 20); // Stagger effect
            });
        }
    </script>
</body>
</html>
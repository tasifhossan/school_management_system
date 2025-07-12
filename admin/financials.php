<?php
session_start();
include_once('../lib/connection.php');

// Check if user is logged in and is a student.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check for a message passed through the session after a redirect
$message = '';
$message_type = 'info';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get student ID
$stmt_student_id = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt_student_id->bind_param("i", $user_id);
$stmt_student_id->execute();
$result_student_id = $stmt_student_id->get_result();
$student = $result_student_id->fetch_assoc();
$student_id = $student['id'];
$stmt_student_id->close();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    if ($amount && $amount > 0) {
        $sql_insert_payment = "INSERT INTO student_payments (student_id, amount, payment_date, status) VALUES (?, ?, NOW(), 'pending')";
        $stmt_insert = $conn->prepare($sql_insert_payment);
        $stmt_insert->bind_param("id", $student_id, $amount);
        if ($stmt_insert->execute()) {
            $_SESSION['message'] = "Your payment of $" . number_format($amount, 2) . " has been submitted and is pending approval.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "There was an error submitting your payment.";
            $_SESSION['message_type'] = 'danger';
        }
        $stmt_insert->close();
    } else {
        $_SESSION['message'] = "Please enter a valid payment amount.";
        $_SESSION['message_type'] = 'warning';
    }
    header("Location: financials.php");
    exit();
}

// --- Data Fetching for Display ---

// FIX: Get TOTAL historical charges for the summary card
$sql_total_charges = "SELECT COALESCE(SUM(amount_due), 0) as total FROM financial_accounts WHERE student_id = ?";
$stmt_total_charges = $conn->prepare($sql_total_charges);
$stmt_total_charges->bind_param("i", $student_id);
$stmt_total_charges->execute();
$total_debits = $stmt_total_charges->get_result()->fetch_assoc()['total'];
$stmt_total_charges->close();

// FIX: Get only UNPAID charges for the list of required payments
$sql_outstanding_charges = "SELECT description, amount_due, due_date FROM financial_accounts WHERE student_id = ? AND paid = 0 ORDER BY due_date ASC";
$stmt_outstanding_charges = $conn->prepare($sql_outstanding_charges);
$stmt_outstanding_charges->bind_param("i", $student_id);
$stmt_outstanding_charges->execute();
$outstanding_charges = $stmt_outstanding_charges->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_outstanding_charges->close();

// Fetch all payments and separate them by status
$sql_payments = "SELECT amount, payment_date, processed_date, status FROM student_payments WHERE student_id = ? ORDER BY payment_date DESC";
$stmt_payments = $conn->prepare($sql_payments);
$stmt_payments->bind_param("i", $student_id);
$stmt_payments->execute();
$all_payments = $stmt_payments->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_payments->close();

$pending_payments = [];
$processed_payments = [];
$total_credits = 0;

foreach ($all_payments as $payment) {
    if ($payment['status'] === 'pending') {
        $pending_payments[] = $payment;
    } else {
        $processed_payments[] = $payment;
        if ($payment['status'] === 'approved') {
            $total_credits += $payment['amount'];
        }
    }
}

// The current balance calculation is now correct based on ALL historical debits and credits
$current_balance = $total_debits - $total_credits;

// Standard includes for name/photo
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Student';
$defaultAvatar = 'img/avatars/avatar.jpg';
$imageSrc = $defaultAvatar;
?>

<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">My Financials</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Charges</h5>
                        <h1 class="mt-1 mb-3">$<?php echo number_format($total_debits, 2); ?></h1>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Paid</h5>
                        <h1 class="mt-1 mb-3 text-success">$<?php echo number_format($total_credits, 2); ?></h1>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Current Balance</h5>
                        <h1 class="mt-1 mb-3 <?php echo $current_balance > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                            $<?php echo number_format($current_balance, 2); ?>
                        </h1>
                         <div class="mb-1">
                            <span class="text-muted"><?php echo $current_balance > 0 ? 'Amount Due' : ($current_balance < 0 ? 'Credit Balance' : 'Settled'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Outstanding Charges (Payment Required)</h5></div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead><tr><th>Description</th><th>Due Date</th><th>Amount</th></tr></thead>
                            <tbody>
                                <?php if (empty($outstanding_charges)): ?>
                                    <tr><td colspan="3" class="text-center">You have no outstanding charges.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($outstanding_charges as $charge): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($charge['description']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($charge['due_date'])); ?></td>
                                        <td>$<?php echo number_format($charge['amount_due'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                 <div class="card">
                    <div class="card-header"><h5 class="card-title">Pending Payments</h5></div>
                    <div class="card-body">
                         <table class="table table-striped">
                            <thead><tr><th>Submission Date</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($pending_payments)): ?>
                                    <tr><td colspan="3" class="text-center">You have no payments pending approval.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><span class="badge bg-warning">Pending</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title">Payment History (Confirmed)</h5></div>
                    <div class="card-body">
                         <table class="table table-striped">
                            <thead><tr><th>Processed Date</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($processed_payments)): ?>
                                     <tr><td colspan="3" class="text-center">You have no processed payments.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($processed_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($payment['processed_date'] ?? $payment['payment_date'])); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <?php if($payment['status'] === 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif($payment['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                 <div class="card">
                    <div class="card-header"><h5 class="card-title">Make a Payment</h5></div>
                    <div class="card-body">
                        <form method="POST" action="financials.php">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Payment Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" placeholder="Enter amount" required>
                                </div>
                            </div>
                            <button type="submit" name="submit_payment" class="btn btn-primary">Submit Payment for Approval</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
$conn->close();
include "footer.php"; 
?>
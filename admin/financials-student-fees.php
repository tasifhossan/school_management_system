<?php
session_start();
include_once('../lib/connection.php');

// --- Start of Setup and POST Handling ---

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Admin';

// PRG PATTERN: Check for a message from the session after a redirect
$message = '';
$message_type = 'info';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Handle Approving/Rejecting a Pending Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_id = $_POST['payment_id'];
    $status = $_POST['status'];
    if ($status === 'rejected') {
        $sql_reject = "UPDATE student_payments SET status = 'rejected', approved_by = ?, processed_date = NOW() WHERE id = ?";
        $stmt_reject = $conn->prepare($sql_reject);
        $stmt_reject->bind_param("ii", $admin_id, $payment_id);
        if ($stmt_reject->execute()) {
            $_SESSION['message'] = "Payment has been rejected.";
            $_SESSION['message_type'] = 'success';
        }
    } elseif ($status === 'approved') {
        $conn->begin_transaction();
        try {
            $stmt_get_payment = $conn->prepare("SELECT student_id, amount FROM student_payments WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt_get_payment->bind_param("i", $payment_id);
            $stmt_get_payment->execute();
            $payment_result = $stmt_get_payment->get_result();
            if ($payment_result->num_rows === 0) {
                throw new Exception("Payment not found or already processed.");
            }
            $payment = $payment_result->fetch_assoc();
            $student_id = $payment['student_id'];
            $payment_amount_to_allocate = $payment['amount'];

            $stmt_get_charges = $conn->prepare("SELECT id, amount_due, description FROM financial_accounts WHERE student_id = ? AND paid = 0 ORDER BY due_date ASC");
            $stmt_get_charges->bind_param("i", $student_id);
            $stmt_get_charges->execute();
            $charges = $stmt_get_charges->get_result()->fetch_all(MYSQLI_ASSOC);
            
            foreach ($charges as $charge) {
                if ($payment_amount_to_allocate <= 0) break;
                $charge_id = $charge['id'];
                $charge_amount = $charge['amount_due'];

                if ($payment_amount_to_allocate >= $charge_amount) {
                    $stmt_pay_full = $conn->prepare("UPDATE financial_accounts SET paid = 1 WHERE id = ?");
                    $stmt_pay_full->bind_param("i", $charge_id);
                    $stmt_pay_full->execute();
                    $stmt_pay_full->close();
                    $payment_amount_to_allocate -= $charge_amount;
                } else {
                    $paid_description = "Partial payment for: " . $charge['description'];
                    $stmt_insert_paid_part = $conn->prepare("INSERT INTO financial_accounts (student_id, amount_due, description, due_date, paid) VALUES (?, ?, ?, CURDATE(), 1)");
                    $stmt_insert_paid_part->bind_param("ids", $student_id, $payment_amount_to_allocate, $paid_description);
                    $stmt_insert_paid_part->execute();
                    $stmt_insert_paid_part->close();

                    $remaining_charge = $charge_amount - $payment_amount_to_allocate;
                    $stmt_update_unpaid_part = $conn->prepare("UPDATE financial_accounts SET amount_due = ? WHERE id = ?");
                    $stmt_update_unpaid_part->bind_param("di", $remaining_charge, $charge_id);
                    $stmt_update_unpaid_part->execute();
                    $stmt_update_unpaid_part->close();
                    $payment_amount_to_allocate = 0;
                }
            }

            $stmt_approve = $conn->prepare("UPDATE student_payments SET status = 'approved', approved_by = ?, processed_date = NOW() WHERE id = ?");
            $stmt_approve->bind_param("ii", $admin_id, $payment_id);
            $stmt_approve->execute();
            $stmt_approve->close();

            $conn->commit();
            $_SESSION['message'] = "Payment approved and allocated successfully.";
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error during payment allocation: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    }
    header("Location: financials-student-fees.php");
    exit();
}

// Handle Adding a New Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date'];
    $description = $_POST['description'];
    $target_group = $_POST['target_group'];
    $course_offering_id = $_POST['course_offering_id'] ?? null;
    $student_id_single = $_POST['student_id'] ?? null;
    $student_ids = [];

    if ($target_group === 'all_students') {
        $result = $conn->query("SELECT id FROM students");
        while($row = $result->fetch_assoc()) { $student_ids[] = $row['id']; }
    } elseif ($target_group === 'by_course' && $course_offering_id) {
        $stmt = $conn->prepare("SELECT student_id FROM student_course_enrollments WHERE course_offering_id = ?");
        $stmt->bind_param("i", $course_offering_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) { $student_ids[] = $row['student_id']; }
        $stmt->close();
    } elseif ($target_group === 'single_student' && $student_id_single) {
        $student_ids[] = $student_id_single;
    }

    if (!empty($student_ids)) {
        $sql_insert = "INSERT INTO financial_accounts (student_id, amount_due, description, due_date, paid) VALUES (?, ?, ?, ?, 0)";
        $stmt_insert = $conn->prepare($sql_insert);
        $success_count = 0;
        foreach ($student_ids as $student_id) {
            $stmt_insert->bind_param("idss", $student_id, $amount, $description, $due_date);
            if($stmt_insert->execute()) { $success_count++; }
        }
        $stmt_insert->close();
        $_SESSION['message'] = "Successfully added fees for " . $success_count . " students.";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "No students found for the selected criteria.";
        $_SESSION['message_type'] = 'warning';
    }
    header("Location: financials-student-fees.php");
    exit();
}
// --- End of POST Handling ---


// --- Start of Data Fetching for Display ---
$sql_pending = "SELECT sp.id, u.name as student_name, sp.amount, sp.payment_date FROM student_payments sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE sp.status = 'pending' ORDER BY sp.payment_date ASC";
$pending_payments = $conn->query($sql_pending);

$sql_balances_all = "SELECT s.id, u.name, (SELECT COALESCE(SUM(amount_due), 0) FROM financial_accounts WHERE student_id = s.id) as total_debits, (SELECT COALESCE(SUM(amount), 0) FROM student_payments WHERE student_id = s.id AND status = 'approved') as total_credits FROM students s JOIN users u ON s.user_id = u.id WHERE u.role = 'student'";
$balances_all = $conn->query($sql_balances_all)->fetch_all(MYSQLI_ASSOC);

$filter_course_id = $_GET['course_offering_id'] ?? null;
$balances_filtered = [];
if ($filter_course_id && is_numeric($filter_course_id)) {
    $sql_balances_filtered = "SELECT s.id, u.name, (SELECT COALESCE(SUM(amount_due), 0) FROM financial_accounts WHERE student_id = s.id) as total_debits, (SELECT COALESCE(SUM(amount), 0) FROM student_payments WHERE student_id = s.id AND status = 'approved') as total_credits FROM students s JOIN users u ON s.user_id = u.id JOIN student_course_enrollments sce ON s.id = sce.student_id WHERE u.role = 'student' AND sce.course_offering_id = ?";
    $stmt_filtered = $conn->prepare($sql_balances_filtered);
    $stmt_filtered->bind_param("i", $filter_course_id);
    $stmt_filtered->execute();
    $balances_filtered = $stmt_filtered->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_filtered->close();
}

$total_paid = array_sum(array_column($balances_all, 'total_credits'));
$total_fees = array_sum(array_column($balances_all, 'total_debits'));
$total_outstanding = $total_fees - $total_paid;
$chart_data_paid_vs_unpaid = [$total_paid, $total_outstanding > 0 ? $total_outstanding : 0];

$sql_revenue_by_course = "SELECT c.name as course_name, SUM(sp.amount) as total_received FROM student_payments sp JOIN student_course_enrollments sce ON sp.student_id = sce.student_id JOIN course_offerings co ON sce.course_offering_id = co.id JOIN courses c ON co.course_id = c.id WHERE sp.status = 'approved' GROUP BY c.id ORDER BY total_received DESC";
$revenue_by_course_result = $conn->query($sql_revenue_by_course);
$chart_labels_revenue_by_course = [];
$chart_data_revenue_by_course = [];
while($row = $revenue_by_course_result->fetch_assoc()) {
    $chart_labels_revenue_by_course[] = $row['course_name'];
    $chart_data_revenue_by_course[] = $row['total_received'];
}

$sql_courses = "SELECT co.id, c.name as course_name, co.semester FROM course_offerings co JOIN courses c ON co.course_id = c.id ORDER BY c.name, co.semester";
$courses_result = $conn->query($sql_courses);
$sql_all_students = "SELECT s.id, u.name FROM students s JOIN users u ON s.user_id = u.id WHERE u.role = 'student' ORDER BY u.name ASC";
$all_students_result = $conn->query($sql_all_students);
?>

<?php include "dashboard-top.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Financials Dashboard</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">Financial Overview</h5></div>
                    <div class="card-body d-flex"><div class="align-self-center w-100"><canvas id="paidVsUnpaidChart"></canvas></div></div>
                </div>
            </div>
            <div class="col-lg-6">
                 <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Revenue by Course</h5></div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:400px; width:100%">
                            <canvas id="revenueByCourseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title">Pending Student Payments for Approval</h5></div>
            <div class="card-body">
                <table class="table table-hover my-0">
                    <thead><tr><th>Student</th><th>Amount</th><th>Payment Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($pending_payments->num_rows > 0): while($p = $pending_payments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                            <td>$<?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($p['payment_date'])); ?></td>
                            <td>
                                <form method="POST" action="financials-student-fees.php" style="display:inline;">
                                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" name="process_payment" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" action="financials-student-fees.php" style="display:inline;">
                                     <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                     <input type="hidden" name="status" value="rejected">
                                    <button type="submit" name="process_payment" class="btn btn-sm btn-danger">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center">No pending payments.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title">Add Student Fee / Charge</h5></div>
            <div class="card-body">
                <form method="POST" action="financials-student-fees.php">
                     <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="target_group" class="form-label">Target</label>
                            <select id="target_group" name="target_group" class="form-select" onchange="toggleTargetSelects()">
                                <option value="single_student" selected>Single Student</option>
                                <option value="by_course">By Course</option>
                                <option value="all_students">All Students</option>
                            </select>
                        </div>
                        <div id="student_select_div" class="col-md-8 mb-3">
                            <label for="student_id" class="form-label">Select Student</label>
                            <select id="student_id" name="student_id" class="form-select">
                                <?php while($student = $all_students_result->fetch_assoc()): ?>
                                    <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                         <div id="course_select_div" class="col-md-8 mb-3" style="display:none;">
                            <label for="course_offering_id" class="form-label">Select Course</label>
                            <select id="course_offering_id" name="course_offering_id" class="form-select">
                                <?php mysqli_data_seek($courses_result, 0); ?>
                                <?php while($course = $courses_result->fetch_assoc()): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name'] . ' - ' . $course['semester']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Fee Description</label>
                            <input type="text" class="form-control" id="description" name="description" placeholder="e.g., Spring Tuition, Library Fine" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                    </div>
                    <button type="submit" name="add_fee" class="btn btn-primary">Add Fee</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Student Accounts</h5>
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php if(!$filter_course_id) echo 'active'; ?>" id="all-students-tab" data-bs-toggle="tab" data-bs-target="#all-students" type="button" role="tab">All Students</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php if($filter_course_id) echo 'active'; ?>" id="by-course-tab" data-bs-toggle="tab" data-bs-target="#by-course" type="button" role="tab">By Course</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade <?php if(!$filter_course_id) echo 'show active'; ?>" id="all-students" role="tabpanel">
                        <div class="mb-3">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary active" onclick="filterTable(this, 'all')">All</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="filterTable(this, 'due')">Payment Required</button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="filterTable(this, 'settled')">Settled</button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="filterTable(this, 'credit')">Credit Balance</button>
                            </div>
                        </div>
                        <table id="students-balance-table" class="table table-hover my-0 sortable">
                            <thead><tr><th>Student Name</th><th>Total Fees</th><th>Total Paid</th><th>Current Balance</th></tr></thead>
                            <tbody>
                                <?php foreach($balances_all as $b):
                                    $balance = $b['total_debits'] - $b['total_credits'];
                                    $status_class = 'all';
                                    if ($balance > 0) $status_class = 'due';
                                    elseif ($balance < 0) $status_class = 'credit';
                                    else $status_class = 'settled';
                                ?>
                                <tr data-status="<?php echo $status_class; ?>">
                                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                                    <td>$<?php echo number_format($b['total_debits'], 2); ?></td>
                                    <td class="text-success">$<?php echo number_format($b['total_credits'], 2); ?></td>
                                    <td class="<?php echo $balance > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">$<?php echo number_format($balance, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade <?php if($filter_course_id) echo 'show active'; ?>" id="by-course" role="tabpanel">
                        <form method="GET" action="financials-student-fees.php#by-course">
                            <div class="row align-items-end">
                                <div class="col-md-6">
                                    <label for="course_filter" class="form-label">Select a course to view enrolled students:</label>
                                    <select name="course_offering_id" id="course_filter" class="form-select">
                                        <option value="">-- Select Course --</option>
                                        <?php mysqli_data_seek($courses_result, 0); ?>
                                        <?php while($course = $courses_result->fetch_assoc()): ?>
                                            <option value="<?php echo $course['id']; ?>" <?php if($filter_course_id == $course['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($course['course_name'] . ' - ' . $course['semester']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                     <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </div>
                        </form>
                        <hr>
                        <?php if($filter_course_id): ?>
                            <h5>Students in Selected Course</h5>
                            <table class="table table-hover my-0">
                                <thead><tr><th>Student Name</th><th>Total Fees</th><th>Total Paid</th><th>Current Balance</th></tr></thead>
                                <tbody>
                                    <?php if(empty($balances_filtered)): ?>
                                        <tr><td colspan="4" class="text-center">No students found with financial records in this course.</td></tr>
                                    <?php else: foreach($balances_filtered as $b):
                                        $balance = $b['total_debits'] - $b['total_credits'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($b['name']); ?></td>
                                        <td>$<?php echo number_format($b['total_debits'], 2); ?></td>
                                        <td class="text-success">$<?php echo number_format($b['total_credits'], 2); ?></td>
                                        <td class="<?php echo $balance > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">$<?php echo number_format($balance, 2); ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Paid vs Unpaid Chart
    new Chart(document.getElementById("paidVsUnpaidChart"), {
        type: "doughnut", data: { labels: ["Total Paid", "Total Outstanding"], datasets: [{ data: <?php echo json_encode($chart_data_paid_vs_unpaid); ?>, backgroundColor: ["#28a745", "#dc3545"], borderColor: "transparent" }] }, options: { maintainAspectRatio: false, cutout: '80%' }
    });
    
    // Revenue by Course Chart
    new Chart(document.getElementById("revenueByCourseChart"), {
        type: "bar", data: { labels: <?php echo json_encode($chart_labels_revenue_by_course); ?>, datasets: [{ label: 'Total Received ($)', data: <?php echo json_encode($chart_data_revenue_by_course); ?>, backgroundColor: 'rgba(54, 162, 235, 0.6)' }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

    // Toggle for Add Fee form
    window.toggleTargetSelects = function() {
        var tg = document.getElementById('target_group').value;
        document.getElementById('course_select_div').style.display = (tg === 'by_course') ? 'block' : 'none';
        document.getElementById('student_select_div').style.display = (tg === 'single_student') ? 'block' : 'none';
    };
    toggleTargetSelects();

    // Filtering function for student list
    window.filterTable = function(btn, status) {
        document.querySelectorAll('#all-students .btn-group .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tableRows = document.querySelectorAll("#students-balance-table tbody tr");
        tableRows.forEach(row => {
            row.style.display = (status === 'all' || row.dataset.status === status) ? "" : "none";
        });
    }

    // Table Sorting function
    document.querySelectorAll(".sortable th").forEach(headerCell => {
        headerCell.addEventListener("click", () => {
            const tableElement = headerCell.closest("table");
            const headerIndex = Array.from(headerCell.parentElement.children).indexOf(headerCell);
            const isAsc = headerCell.classList.contains("sorted-asc");
            sortTableByColumn(tableElement, headerIndex, !isAsc);
        });
    });

    function sortTableByColumn(table, column, asc = true) {
        const dirModifier = asc ? 1 : -1;
        const tBody = table.tBodies[0];
        const rows = Array.from(tBody.querySelectorAll("tr"));
        const sortedRows = rows.sort((a, b) => {
            const aCol = a.querySelector(`td:nth-child(${column + 1})`);
            const bCol = b.querySelector(`td:nth-child(${column + 1})`);
            const aValText = (aCol.textContent || '').trim().replace(/[^0-9.-]+/g, "");
            const bValText = (bCol.textContent || '').trim().replace(/[^0-9.-]+/g, "");
            const aVal = isNaN(parseFloat(aValText)) ? aCol.textContent.trim() : parseFloat(aValText);
            const bVal = isNaN(parseFloat(bValText)) ? bCol.textContent.trim() : parseFloat(bValText);
            if (aVal < bVal) return -1 * dirModifier;
            if (aVal > bVal) return 1 * dirModifier;
            return 0;
        });
        tBody.append(...sortedRows);
        table.querySelectorAll("th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
        table.querySelector(`th:nth-child(${column + 1})`).classList.toggle(asc ? "sorted-asc" : "sorted-desc");
    }
});
</script>

<?php
$conn->close();
include "footer.php";
?>
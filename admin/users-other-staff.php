<?php  


session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_ad.php" ?>
<main class="content">
				<div class="container-fluid p-0">

					<h1 class="h3 mb-3">This Page is Coming Soon!</h1>

					<h5 class="card-title mb-0">We're currently putting the finishing touches on this section of our website. Please check back again shortly. 
					<!-- <br><br> In the meantime, you can head back to our <a href="dashboard.php" class="btn px-0">Dashboard</a> or visit our Blog for our latest updates. -->
											
					</h5>

					<!-- <div class="row">
						<div class="col-12">
							<div class="card">
								<div class="card-header">
									<h5 class="card-title mb-0">Card Header
											
									</h5>
								</div>
								<div class="card-body">
								</div>
							</div>
						</div>
					</div> -->

				</div>
			</main>

			<?php include "footer.php" ?>

</body>

</html>
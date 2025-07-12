<?php
// sidebar.php

// Get the current page's filename
$current_page = basename($_SERVER['REQUEST_URI']);
$current_page_clean = strtok($current_page, '?'); // Clean any query parameters

// A helper function to check if a link is active
function is_active($link_hrefs, $current_page_clean) {
    if (is_array($link_hrefs)) {
        foreach ($link_hrefs as $href) {
            if (basename($href) === $current_page_clean) {
                return 'active';
            }
        }
        return '';
    }

    // If a single string is passed
    return (basename($link_hrefs) === $current_page_clean) ? 'active' : '';
}
?>
<style>
	/* CSS for the hover effect */
        .logout-link:hover {
            color: rgba(255, 0, 0, 1); /* Red color on hover */
        }
    /* If the icon color also needs to change, you might need to target the i tag inside or ensure color propagates */
        .logout-link i:hover {
            color: rgba(255, 0, 0, 1);
        }
    /* padding bottom for after logout*/    
        .pdb{
			padding-bottom: 30px;
		}
	
</style>

<body>
	<div class="wrapper">
		<nav id="sidebar" class="sidebar js-sidebar">
			<div class="sidebar-content js-simplebar">
				<a class="sidebar-brand" href="index.html">
		          <span class="align-middle">First Step Preschool</span>
		        </a>

				<ul class="sidebar-nav">
					<li class="sidebar-header">
						Student Dashboard
					</li>

					<li class="sidebar-item <?php echo is_active('dashboard-student.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard-student.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Overview</span>
						</a>
					</li>

			<!-- shortcut for now -->
				    <!-- <li class="sidebar-item <?php //echo is_active('dashboard.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Admin Dashboard</span>
						</a>
					</li>

				    <li class="sidebar-item <?php //echo is_active('dashboard-teacher.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard-teacher.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Teacher Dashboard</span>
						</a>
					</li> -->
			<!-- -----		 -->

					<li class="sidebar-item <?php echo is_active(['my_profile.php', 'edit-my-profile.php'], $current_page_clean); ?>">
						<a class="sidebar-link" href="my_profile.php">
							<i class="align-middle" data-feather="user"></i> <span class="align-middle">My Profile</span>
						</a>
					</li>

					<li class="sidebar-header">
						Academics
					</li>

					<li class="sidebar-item <?php echo is_active('student-my_class_section.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="student-my_class_section.php">
							<i class="align-middle" data-feather="clipboard"></i> <span class="align-middle">My Class Section</span>
						</a>
					</li>

					<li class="sidebar-item <?php echo is_active('student-my_courses.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="student-my_courses.php">
							<i class="align-middle" data-feather="book-open"></i> <span class="align-middle">My Courses</span>
						</a>
					</li>

					<li class="sidebar-item <?php echo is_active('student-my_assignments.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="student-my_assignments.php">
							<i class="align-middle" data-feather="edit-2"></i> <span class="align-middle">My Assignments</span>
						</a>
					</li>

					<li class="sidebar-item <?php echo is_active('student-my_exam_results.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="student-my_exam_results.php">
							<i class="align-middle" data-feather="file-text"></i> <span class="align-middle">My Exam Results</span>
						</a>
					</li>

					<li class="sidebar-item <?php echo is_active('student-my_attendance.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="student-my_attendance.php">
							<i class="align-middle" data-feather="check-square"></i> <span class="align-middle">My Attendance</span>
						</a>
					</li>
					
					<li class="sidebar-item <?php echo is_active('financials.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="financials.php">
							<i class="align-middle" data-feather="dollar-sign"></i> <span class="align-middle">Fees / Financials</span>
						</a>
					</li>

					<li class="sidebar-header">
					    Connect 
					</li>

					<li class="sidebar-item <?php echo is_active('messages_group.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="messages_group.php">
					        <i class="align-middle" data-feather="users"></i> <span class="align-middle">My Groups</span>
					    </a>
					</li>
					<li class="sidebar-item <?php echo is_active('messages_private.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="messages_private.php">
					        <i class="align-middle" data-feather="message-square"></i> <span class="align-middle">Direct Messages</span>
					    </a>
					</li>
					<li class="sidebar-item <?php echo is_active('student-announcements.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="student-announcements.php">
					        <i class="align-middle" data-feather="volume-2"></i> <span class="align-middle">Announcements</span>
					    </a>
					</li>


					<li class="sidebar-header">
					    Campus Life
					</li>

					<li class="sidebar-item <?php echo is_active('student-school_programs.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="student-school_programs.php">
					        <i class="align-middle" data-feather="book-open"></i> <span class="align-middle">Academic Programs</span>
					    </a>
					</li>
					<li class="sidebar-item <?php echo is_active('student-school_activities.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="student-school_activities.php">
					        <i class="align-middle" data-feather="calendar"></i> <span class="align-middle">Events & Activities</span>
					    </a>
					</li>
					<li class="sidebar-item <?php echo is_active('student-school_gallery.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="student-school_gallery.php">
					        <i class="align-middle" data-feather="image"></i> <span class="align-middle">Gallery</span>
					    </a>
					</li>
					<li class="sidebar-item <?php echo is_active('student-contact.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="student-contact.php">
					        <i class="align-middle" data-feather="map-pin"></i> <span class="align-middle">Get in Touch</span>
					    </a>
					</li>


					<li class="sidebar-header"></li>

					<li class="sidebar-item pdb <?php echo is_active('logout.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="logout.php">
					        <i class="align-middle" data-feather="log-out"></i> <span class="align-middle">Logout</span>
					    </a>
					</li>
				</ul>
			</div>
		</nav>

		<div class="main">
			<nav class="navbar navbar-expand navbar-light navbar-bg">
				<a class="sidebar-toggle js-sidebar-toggle">
		          <i class="hamburger align-self-center"></i>
		        </a>

				<div class="navbar-collapse collapse">
					<ul class="navbar-nav navbar-align">
						<li class="nav-item dropdown">
							<a class="nav-icon dropdown-toggle" href="#" id="messagesDropdown" data-bs-toggle="dropdown">
								<div class="position-relative">
									<i class="align-middle" data-feather="message-square"></i>
								</div>
							</a>
							<div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="messagesDropdown">
								<div class="dropdown-menu-header">
									<div class="position-relative">
										4 New Messages
									</div>
								</div>
								<div class="list-group">
									<a href="#" class="list-group-item">
										<div class="row g-0 align-items-center">
											<div class="col-2">
												<img src="img/avatars/avatar-5.jpg" class="avatar img-fluid rounded-circle" alt="Vanessa Tucker">
											</div>
											<div class="col-10 ps-2">
												<div class="text-dark">Vanessa Tucker</div>
												<div class="text-muted small mt-1">Nam pretium turpis et arcu. Duis arcu tortor.</div>
												<div class="text-muted small mt-1">15m ago</div>
											</div>
										</div>
									</a>
									<a href="#" class="list-group-item">
										<div class="row g-0 align-items-center">
											<div class="col-2">
												<img src="img/avatars/avatar-2.jpg" class="avatar img-fluid rounded-circle" alt="William Harris">
											</div>
											<div class="col-10 ps-2">
												<div class="text-dark">William Harris</div>
												<div class="text-muted small mt-1">Curabitur ligula sapien euismod vitae.</div>
												<div class="text-muted small mt-1">2h ago</div>
											</div>
										</div>
									</a>
								</div>
								<div class="dropdown-menu-footer">
									<a href="#" class="text-muted">Show all messages</a>
								</div>
							</div>
						</li>
						<li class="nav-item">
						  <a class="nav-link" aria-current="page" href="my_profile.php">
						  	
						  	<img src="<?php echo htmlspecialchars($imageSrc); ?>"  class="avatar img-fluid rounded-circle me-3"  alt="<?php echo $name; ?>"><span class="text-dark"><?php echo $name; ?></span>
						  </a>
						</li>
						
					</ul>
				</div>
			</nav>

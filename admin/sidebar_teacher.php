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
						Teacher Dashboard
					</li>

					<li class="sidebar-item <?php echo is_active('dashboard-teacher.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard-teacher.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Overview</span>
						</a>
					</li>

			<!-- shortcut for now -->
				    <!-- <li class="sidebar-item <?php //echo is_active('dashboard.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Admin Dashboard</span>
						</a>
					</li>

				    <li class="sidebar-item <?php //echo is_active('dashboard-student.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard-student.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Student Dashboard</span>
						</a>
					</li> -->
			<!-- -----		 -->	

					<li class="sidebar-item <?php echo is_active('teacher-my_profile.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="teacher-my_profile.php">
							<i class="align-middle" data-feather="user"></i> <span class="align-middle">My Profile</span>
						</a>
					</li>

					<li class="sidebar-header">
						Academics
					</li>

					<li class="sidebar-item <?php echo is_active('my_class_sections.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="my_class_sections.php">
							<i class="align-middle" data-feather="clipboard"></i> <span class="align-middle">My Class Sections</span>
						</a>
					</li>

					<li class="sidebar-item <?php echo is_active('my_course_offerings.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="my_course_offerings.php">
							<i class="align-middle" data-feather="book-open"></i> <span class="align-middle">My Course Offerings</span>
						</a>
					</li>

					<li class="sidebar-item <?php echo is_active('my_students.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="my_students.php">
							<i class="align-middle" data-feather="users"></i> <span class="align-middle">My Students</span>
						</a>
					</li>

					<li class="sidebar-item">
						<a data-bs-target="#assignments" data-bs-toggle="collapse" class="sidebar-link collapsed">
							<i class="align-middle" data-feather="edit"></i> <span class="align-middle">Assignments</span>
						</a>
						<ul id="assignments" class="sidebar-dropdown list-unstyled collapse">
							<li class="sidebar-item"><a class="sidebar-link" href="create_assignment.php">Create Assignment</a></li>
							<li class="sidebar-item"><a class="sidebar-link" href="view_assignments.php">View Assignments</a></li>
						</ul>
					</li>

					<li class="sidebar-item">
						<a data-bs-target="#results" data-bs-toggle="collapse" class="sidebar-link collapsed">
							<i class="align-middle" data-feather="file-text"></i> <span class="align-middle">Exam Results</span>
						</a>
						<ul id="results" class="sidebar-dropdown list-unstyled collapse">
							<li class="sidebar-item"><a class="sidebar-link" href="enter_marks.php">Enter Marks</a></li>
							<li class="sidebar-item"><a class="sidebar-link" href="view_results.php">View Results</a></li>
						</ul>
					</li>

					<li class="sidebar-item">
						<a data-bs-target="#attendance" data-bs-toggle="collapse" class="sidebar-link collapsed">
							<i class="align-middle" data-feather="check-square"></i> <span class="align-middle">Attendance</span>
						</a>
						<ul id="attendance" class="sidebar-dropdown list-unstyled collapse">
							<li class="sidebar-item"><a class="sidebar-link" href="mark_attendance.php">Mark Attendance</a></li>
							<li class="sidebar-item"><a class="sidebar-link" href="view_attendance.php">View Attendance</a></li>
						</ul>
					</li>
					
					<li class="sidebar-header">
					    Connect & Manage
					</li>

					<li class="sidebar-item <?php echo is_active('messages_group.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="messages_group.php">
					        <i class="align-middle" data-feather="users"></i> <span class="align-middle">Message Groups</span>
					    </a>
					</li>

					<li class="sidebar-item <?php echo is_active('messages_private.php', $current_page_clean); ?>">
					    <a class="sidebar-link" href="messages_private.php">
					        <i class="align-middle" data-feather="message-square"></i> <span class="align-middle">Direct Messages</span>
					    </a>
					</li>

					<li class="sidebar-item <?php echo is_active(['teacher-announcements.php', 'teacher-add-announcement.php', 'teacher-edit-announcement.php'], $current_page_clean); ?>">
					    <a class="sidebar-link" href="teacher-announcements.php">
					        <i class="align-middle" data-feather="volume-2"></i> <span class="align-middle">Post Announcements</span>
					    </a>
					</li>

					<li class="sidebar-item pdb">
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
						  <a class="nav-link" aria-current="page" href="teacher-my_profile.php">
						  	<img src="<?php echo htmlspecialchars($imageSrc); ?>"  class="avatar img-fluid rounded-circle me-3"  alt="<?php echo $name; ?>"><span class="text-dark"><?php echo $name; ?></span>
						  </a>
						</li>
					</ul>
				</div>
			</nav>

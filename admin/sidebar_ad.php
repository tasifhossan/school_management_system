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
				        Admin Dashboard 
				    </li>

				    <li class="sidebar-item  <?php //echo is_active('dashboard.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="../index.php">
				            <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Homepage</span>
				        </a>
				    </li>
				    <li class="sidebar-item  <?php echo is_active('dashboard.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="dashboard.php">
				            <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Overview</span>
				        </a>
				    </li>

			<!-- shortcut for now -->
				    <!-- <li class="sidebar-item <?php //echo is_active('dashboard-teacher.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard-teacher.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Teacher Dashboard</span>
						</a>
					</li>

				    <li class="sidebar-item <?php //echo is_active('dashboard-student.php', $current_page_clean); ?>">
						<a class="sidebar-link" href="dashboard-student.php">
							<i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Student Dashboard</span>
						</a>
					</li> -->
			<!-- -----		 -->
				    <li class="sidebar-header">
				        User Management
				    </li>
				    <li class="sidebar-item <?php echo is_active('users-admins.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="users-admins.php">
				            <i class="align-middle" data-feather="user-plus"></i> <span class="align-middle">Admins</span>
				        </a>
				    </li>
				    <li class="sidebar-item  <?php echo is_active('users-teachers.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="users-teachers.php">
				            <i class="align-middle" data-feather="user-check"></i> <span class="align-middle">Teachers</span>
				        </a>
				    </li>
				    <li class="sidebar-item  <?php echo is_active('users-students.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="users-students.php">
				            <i class="align-middle" data-feather="users"></i> <span class="align-middle">Students</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active('users-other-staff.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="users-other-staff.php">
				            <i class="align-middle" data-feather="user"></i> <span class="align-middle">Other Staff</span>
				        </a>
				    </li>

				    

				    <li class="sidebar-header">
				        Academics
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-classes.php', 'academics-edit-class.php', 'academics-add-class.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-classes.php">
				            <i class="align-middle" data-feather="book-open"></i> <span class="align-middle">Classes</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-courses.php', 'academics-edit-course.php', 'academics-add-course.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-courses.php">
				            <i class="align-middle" data-feather="bookmark"></i> <span class="align-middle">Courses</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-course-offerings.php', 'academics-edit-course-offering.php', 'academics-add-course-offering.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-course-offerings.php">
				            <i class="align-middle" data-feather="calendar"></i> <span class="align-middle">Course Offerings</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-student-enrollment.php', 'academics-edit-student-enrollment.php', 'academics-add-student-enrollment.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-student-enrollment.php">
				            <i class="align-middle" data-feather="user-check"></i> <span class="align-middle">Student Enrollment</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-assignments.php', 'academics-edit-assignment.php', 'academics-add-assignment.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-assignments.php">
				            <i class="align-middle" data-feather="clipboard"></i> <span class="align-middle">Assignments</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-exam-results.php', 'academics-edit-exam-result.php', 'academics-add-exam-result.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-exam-results.php">
				            <i class="align-middle" data-feather="award"></i> <span class="align-middle">Exam Results</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['academics-attendance.php', 'academics-edit-attendance.php', 'academics-add-attendance.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="academics-attendance.php">
				            <i class="align-middle" data-feather="user-plus"></i> <span class="align-middle">Attendance</span>
				        </a>
				    </li>

				    

				    <li class="sidebar-header">
				        School Operations
				    </li>
				    <li class="sidebar-item <?php echo is_active(['school-operations-announcements.php', 'edit-announcement.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="school-operations-announcements.php">
				            <i class="align-middle" data-feather="bell"></i> <span class="align-middle">Announcements</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['school-operations-activities.php', 'school-operations-add-activities.php', 'school-operations-edit-activities.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="school-operations-activities.php">
				            <i class="align-middle" data-feather="book-open"></i> <span class="align-middle">School Activities</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['school-operations-programs.php', 'edit-program.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="school-operations-programs.php">
				            <i class="align-middle" data-feather="activity"></i> <span class="align-middle">School Programs</span>
				        </a>
				    </li>

				    

				    <li class="sidebar-header">
				        Activities
				    </li>
				    <li class="sidebar-item <?php echo is_active(['activities-photo-gallery.php', 'activities-edit-photo-gallery.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="activities-photo-gallery.php">
				            <i class="align-middle" data-feather="image"></i> <span class="align-middle">Photo Gallery</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active(['activities-contact-info.php', 'activities-edit-contact-info.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="activities-contact-info.php">
				            <i class="align-middle" data-feather="phone"></i> <span class="align-middle">Contact Info</span>
				        </a>
				    </li>

				    

				    <li class="sidebar-header">
				        Financials
				    </li>
				    <li class="sidebar-item <?php //echo is_active(['school-operations-announcements.php', 'edit-announcement.php'], $current_page_clean); ?>">
				        <a class="sidebar-link" href="financials-teacher-salaries.html">
				            <i class="align-middle" data-feather="dollar-sign"></i> <span class="align-middle">Teacher Salaries</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php //echo is_active(['school-operations-announcements.php', 'edit-announcement.php'], $current_page_clean); ?> <?php echo is_active('financials-student-fees.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="financials-student-fees.php">
				            <i class="align-middle" data-feather="credit-card"></i> <span class="align-middle">Student Fees</span>
				        </a>
				    </li>

				    

				    <li class="sidebar-header">
				        Communication
				    </li>
				    <li class="sidebar-item <?php echo is_active('messages_group.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="messages_group.php">
				            <i class="align-middle" data-feather="message-circle"></i> <span class="align-middle">Message Groups</span>
				        </a>
				    </li>
				    <li class="sidebar-item <?php echo is_active('messages_private.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="messages_private.php">
				            <i class="align-middle" data-feather="send"></i> <span class="align-middle">Personal Messages</span>
				        </a>
				    </li>

				    

				    <li class="sidebar-header">
				        Others
				    </li>
				    <li class="sidebar-item <?php echo is_active('others-settings.php', $current_page_clean); ?>">
				        <a class="sidebar-link" href="others-settings.php">
				            <i class="align-middle" data-feather="settings"></i> <span class="align-middle">Settings</span>
				        </a>
				    </li>
				    <!-- <li class="sidebar-item">
				        <a class="sidebar-link" href="others-my-profile.html">
				            <i class="align-middle" data-feather="user"></i> <span class="align-middle">My Profile</span>
				        </a>
				    </li> -->

				    <li class="sidebar-item pdb">
				        <a class="sidebar-link logout-link" href="logout.php">
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
						  <a class="nav-link" aria-current="page" href="dashboard.php">
						  	<img src="img/avatars/avatar.jpg" class="avatar img-fluid rounded me-1" alt="Charles Hall" /> <span class="text-dark"><?php echo $name; ?></span>
						  </a>
						</li>
					</ul>
				</div>
			</nav>

<?php
// Start the session to check for logged-in users
session_start();

// Include the database connection
include 'lib/connection.php';

// --- Fetch Dynamic Data for the Landing Page ---

// 1. Get Key Statistics
$total_students_result = $conn->query("SELECT COUNT(*) as count FROM students");
$total_students = $total_students_result ? $total_students_result->fetch_assoc()['count'] : 0;

$total_teachers_result = $conn->query("SELECT COUNT(*) as count FROM teachers");
$total_teachers = $total_teachers_result ? $total_teachers_result->fetch_assoc()['count'] : 0;

$total_courses_result = $conn->query("SELECT COUNT(*) as count FROM courses");
$total_courses = $total_courses_result ? $total_courses_result->fetch_assoc()['count'] : 0;


// 2. Fetch All Teachers with more details
$sql_teachers = "SELECT u.name, u.email, t.photo, t.specialization 
                 FROM teachers t 
                 JOIN users u ON t.user_id = u.id";
$featured_teachers = $conn->query($sql_teachers);

// 3. Fetch All School Courses
$sql_courses_for_landing = "SELECT name, course_code, description FROM courses";
$school_programs = $conn->query($sql_courses_for_landing);


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Step Preschool - Nurturing Bright Futures</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        :root {
            --bg-color: #0c0a1d;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: #f0f0f0;
            overflow-x: hidden;
        }
        .font-display {
            font-family: 'Space Grotesk', sans-serif;
        }
        .background-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(45deg, #240046, #5a189a, #9d4edd, #c77dff, #e0aaff);
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
        }
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
        }
        .header-scrolled {
             background: rgba(12, 10, 29, 0.8);
             backdrop-filter: blur(10px);
             border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        /* Horizontal Scroll Section - DESKTOP ONLY */
        @media (min-width: 1024px) {
            .horizontal-scroll-wrapper {
                position: relative;
                width: 100%;
            }
            .sticky-wrapper {
                position: sticky;
                top: 0;
                height: 100vh;
                width: 100%;
                overflow: hidden;
            }
            .horizontal-scroll-track {
                height: 100%;
                display: flex;
                align-items: center;
                will-change: transform;
            }
            .scroll-card {
                flex-shrink: 0;
                width: clamp(350px, 40vw, 500px); /* Responsive card width */
                /* FIX: Explicitly set height to ensure all cards have uniform dimensions */
                height: 70vh; 
                max-height: 550px;
                margin: 0 2vw;
            }
        }
    </style>
</head>
<body class="text-white">

    <div class="background-gradient"></div>

    <!-- Header & Navigation -->
    <header id="header" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="#" class="font-display text-3xl font-bold">First Step</a>
            <div class="hidden md:flex items-center space-x-8 text-sm">
                <a href="#about" class="tracking-wider hover:text-purple-300 transition">About</a>
                <a href="#programs" class="tracking-wider hover:text-purple-300 transition">Courses</a>
                <a href="#team" class="tracking-wider hover:text-purple-300 transition">Our Team</a>
                <a href="#contact" class="tracking-wider hover:text-purple-300 transition">Contact</a>
            </div>
            <!-- Conditional Login/Dashboard Button -->
            <div class="hidden md:block">
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['name'])): 
                    $dashboard_url = 'admin/dashboard.php'; // Default
                    if ($_SESSION['role'] === 'student') $dashboard_url = 'admin/dashboard-student.php';
                    if ($_SESSION['role'] === 'teacher') $dashboard_url = 'admin/dashboard-teacher.php';
                ?>
                    <a href="<?php echo $dashboard_url; ?>" class="bg-purple-600 text-white px-6 py-2 rounded-full font-semibold hover:bg-purple-700 transition shadow-lg text-sm">
                        <i data-feather="arrow-right-circle" class="inline-block -mt-1 mr-1 w-4 h-4"></i> Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>
                    </a>
                <?php else: ?>
                    <a href="admin/login.php" class="bg-white text-black px-6 py-2 rounded-full font-semibold hover:bg-purple-200 transition shadow-lg text-sm">Portal Login</a>
                <?php endif; ?>
            </div>
            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="menu-btn" class="text-white focus:outline-none">
                    <i data-feather="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </nav>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 glass-card m-2">
                 <a href="#about" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-white/10">About</a>
                 <a href="#programs" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-white/10">Courses</a>
                 <a href="#team" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-white/10">Our Team</a>
                 <a href="#contact" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-white/10">Contact</a>
                 <?php if (isset($_SESSION['user_id']) && isset($_SESSION['name'])): ?>
                     <a href="<?php echo $dashboard_url; ?>" class="block px-3 py-2 rounded-md text-base font-medium bg-purple-600 mt-2 text-center">Dashboard</a>
                 <?php else: ?>
                    <a href="admin/login.php" class="block px-3 py-2 rounded-md text-base font-medium bg-purple-600 mt-2 text-center">Portal Login</a>
                 <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="min-h-screen flex items-center pt-24">
            <div class="container mx-auto px-6 text-center">
                <h1 class="font-display text-5xl md:text-8xl font-bold mb-6 leading-tight reveal">
                    Where Curiosity<br>Takes Its First Step
                </h1>
                <p class="text-lg md:text-xl max-w-3xl mx-auto mb-10 text-gray-300 reveal" style="transition-delay: 0.2s;">
                    A modern preschool experience in Chittagong, Bangladesh, designed to ignite a lifelong passion for discovery in a nurturing, creative, and safe environment.
                </p>
                <a href="#contact" class="bg-purple-600 text-white px-8 py-3 rounded-full font-semibold hover:bg-purple-700 transition shadow-xl transform hover:scale-105 reveal" style="transition-delay: 0.4s;">Enroll Today</a>
            </div>
        </section>

        <!-- Dynamic Stats Section -->
        <section class="container mx-auto px-6 -mt-24 relative z-10">
            <div class="glass-card grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-white/10 text-center">
                <div class="p-8 reveal">
                    <h2 class="font-display text-5xl font-bold"><?php echo $total_students; ?>+</h2>
                    <p class="text-purple-300 tracking-widest mt-1">Happy Students</p>
                </div>
                <div class="p-8 reveal" style="transition-delay: 0.2s;">
                    <h2 class="font-display text-5xl font-bold"><?php echo $total_teachers; ?>+</h2>
                    <p class="text-purple-300 tracking-widest mt-1">Qualified Teachers</p>
                </div>
                <div class="p-8 reveal" style="transition-delay: 0.4s;">
                    <h2 class="font-display text-5xl font-bold"><?php echo $total_courses; ?>+</h2>
                    <p class="text-purple-300 tracking-widest mt-1">Core Courses</p>
                </div>
            </div>
        </section>


        <!-- Our Programs Section -->
        <section id="programs" class="py-24">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16 reveal">
                    <h2 class="font-display text-4xl md:text-5xl font-bold">Our Core Curriculum</h2>
                    <p class="text-lg text-gray-400 mt-2">A look at the courses we offer.</p>
                </div>
                <!-- MOBILE: Vertical Grid -->
                <div id="courses-mobile-grid" class="grid md:hidden grid-cols-1 gap-8">
                    <?php if ($school_programs && $school_programs->num_rows > 0): mysqli_data_seek($school_programs, 0); $count = 0; while($program = $school_programs->fetch_assoc()): ?>
                    <div class="glass-card p-8 text-center reveal course-card-mobile" <?php if ($count >= 10) echo 'style="display: none;"'; ?>>
                        <i data-feather="book-open" class="w-12 h-12 text-purple-300 mx-auto mb-4"></i>
                        <h3 class="font-display text-2xl font-semibold mb-3"><?php echo htmlspecialchars($program['name']); ?></h3>
                        <p class="text-purple-200 text-sm mb-3">Course Code: <?php echo htmlspecialchars($program['course_code']); ?></p>
                        <p class="text-gray-300 leading-relaxed"><?php echo htmlspecialchars($program['description']); ?></p>
                    </div>
                    <?php $count++; endwhile; endif; ?>
                </div>
                <!-- DESKTOP: Horizontal Scroll for Courses -->
                <div class="hidden md:block horizontal-scroll-wrapper" style="height: <?php echo min(10, $school_programs->num_rows) * 50; ?>vh;">
                    <div class="sticky-wrapper">
                        <div id="courses-desktop-track" class="horizontal-scroll-track">
                            <?php if ($school_programs && $school_programs->num_rows > 0): mysqli_data_seek($school_programs, 0); $count = 0; while($program = $school_programs->fetch_assoc()): ?>
                            <div class="scroll-card course-card-desktop" <?php if ($count >= 10) echo 'style="display: none;"'; ?>>
                                <div class="glass-card p-8 h-full flex flex-col justify-center text-center">
                                    <i data-feather="book-open" class="w-12 h-12 text-purple-300 mx-auto mb-4"></i>
                                    <h3 class="font-display text-2xl font-semibold mb-3"><?php echo htmlspecialchars($program['name']); ?></h3>
                                    <p class="text-purple-200 text-sm mb-3">Course Code: <?php echo htmlspecialchars($program['course_code']); ?></p>
                                    <p class="text-gray-300 leading-relaxed"><?php echo htmlspecialchars($program['description']); ?></p>
                                </div>
                            </div>
                            <?php $count++; endwhile; endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($school_programs->num_rows > 10): ?>
                <div class="text-center mt-12">
                    <button id="view-more-courses" class="bg-white/10 border border-white/20 backdrop-blur-sm text-white px-8 py-3 rounded-full font-semibold hover:bg-white/20 transition shadow-lg">View More Courses</button>
                </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Meet Our Team Section -->
        <section id="team" class="py-24">
             <div class="container mx-auto px-6 mb-16 reveal text-center">
                <h2 class="font-display text-4xl md:text-5xl font-bold">Meet Our Educators</h2>
                <p class="text-lg text-gray-400 mt-2">The heart of our school, dedicated to your child's growth.</p>
            </div>
            <!-- MOBILE: Vertical Grid -->
            <div class="grid md:hidden grid-cols-2 gap-8">
                <?php if ($featured_teachers && $featured_teachers->num_rows > 0): mysqli_data_seek($featured_teachers, 0); while($teacher = $featured_teachers->fetch_assoc()):
                    $photo_url = (!empty($teacher['photo']) && file_exists('admin/'.$teacher['photo'])) ? 'aadmin/'.$teacher['photo'] : 'https://placehold.co/400x400/e0aaff/0f0c29?text='.substr($teacher['name'], 0, 1);
                ?>
                <div class="glass-card p-4 text-center reveal">
                     <img src="<?php echo $photo_url; ?>" class="rounded-full w-24 h-24 mx-auto mb-4 object-cover border-4 border-white/10">
                     <h4 class="font-display text-lg font-semibold"><?php echo htmlspecialchars($teacher['name']); ?></h4>
                     <p class="text-purple-300 text-sm"><?php echo htmlspecialchars($teacher['specialization'] ?? 'Lead Teacher'); ?></p>
                </div>
                <?php endwhile; endif; ?>
            </div>
            <!-- DESKTOP: Horizontal Scroll for Teachers -->
             <div class="hidden md:block horizontal-scroll-wrapper" style="height: <?php echo ($featured_teachers->num_rows) * 50; ?>vh;">
                <div class="sticky-wrapper">
                    <div class="horizontal-scroll-track">
                        <?php if ($featured_teachers && $featured_teachers->num_rows > 0): mysqli_data_seek($featured_teachers, 0); while($teacher = $featured_teachers->fetch_assoc()):
                            $photo_url = (!empty($teacher['photo']) && file_exists('admin/'.$teacher['photo'])) ? 'admin/'.$teacher['photo'] : 'https://placehold.co/400x400/e0aaff/0f0c29?text='.substr($teacher['name'], 0, 1);
                        ?>
                        <div class="scroll-card">
                            <div class="glass-card p-6 text-center h-full flex flex-col justify-center">
                                <img src="<?php echo $photo_url; ?>" class="rounded-full w-32 h-32 mx-auto mb-4 object-cover border-4 border-white/10">
                                <h4 class="font-display text-xl font-semibold"><?php echo htmlspecialchars($teacher['name']); ?></h4>
                                <p class="text-purple-300 mb-2"><?php echo htmlspecialchars($teacher['specialization'] ?? 'Lead Teacher'); ?></p>
                                <a href="mailto:<?php echo htmlspecialchars($teacher['email']); ?>" class="text-sm text-gray-400 hover:text-purple-300 transition"><?php echo htmlspecialchars($teacher['email']); ?></a>
                            </div>
                        </div>
                        <?php endwhile; endif; ?>
                    </div>
                </div>
            </div>
        </section>


        <!-- Footer -->
        <footer id="contact" class="mt-24">
             <div class="container mx-auto px-6 py-16">
                <div class="glass-card p-6 md:p-10 grid md:grid-cols-2 gap-10 items-center reveal">
                    <div>
                        <h2 class="font-display text-3xl md:text-4xl font-bold mb-4">Take the First Step</h2>
                        <p class="text-gray-300 mb-6 max-w-md">Ready to join our family? Schedule a tour or reach out with any questions. We're excited to meet you and your little one.</p>
                         <ul class="space-y-4 text-gray-300">
                            <li class="flex items-center"><i data-feather="map-pin" class="w-5 h-5 mr-3 text-purple-300"></i><span>123 Learning Lane, Gopal Union, Chittagong</span></li>
                            <li class="flex items-center"><i data-feather="phone" class="w-5 h-5 mr-3 text-purple-300"></i><span>+880 123 456 7890</span></li>
                            <li class="flex items-center"><i data-feather="mail" class="w-5 h-5 mr-3 text-purple-300"></i><span>contact@firststep.edu</span></li>
                        </ul>
                    </div>
                    <div class="bg-white/10 p-8 rounded-lg">
                         <form action="#" method="POST">
                             <h3 class="font-display text-2xl font-semibold mb-4">Send us a Message</h3>
                             <div class="mb-4">
                                 <label for="name" class="block text-sm font-medium mb-1 text-gray-300">Your Name</label>
                                 <input type="text" id="name" name="name" class="w-full bg-white/10 border border-white/20 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-purple-400">
                             </div>
                              <div class="mb-4">
                                 <label for="email" class="block text-sm font-medium mb-1 text-gray-300">Your Email</label>
                                 <input type="email" id="email" name="email" class="w-full bg-white/10 border border-white/20 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-purple-400">
                             </div>
                              <div class="mb-4">
                                 <label for="message" class="block text-sm font-medium mb-1 text-gray-300">Message</label>
                                 <textarea id="message" name="message" rows="4" class="w-full bg-white/10 border border-white/20 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-purple-400"></textarea>
                             </div>
                             <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-md font-semibold hover:bg-purple-700 transition">Send Message</button>
                         </form>
                    </div>
                </div>
                 <div class="mt-12 text-center text-gray-400 text-sm">
                    <p>&copy; <?php echo date("Y"); ?> First Step Preschool. All Rights Reserved.</p>
                </div>
            </div>
        </footer>
    </main>

    <script>
      feather.replace();

      // Mobile Menu Toggle
      const menuBtn = document.getElementById('menu-btn');
      const mobileMenu = document.getElementById('mobile-menu');
      menuBtn.addEventListener('click', () => {
          mobileMenu.classList.toggle('hidden');
      });

      // Header scroll effect
      const header = document.getElementById('header');
      window.addEventListener('scroll', function() {
          if (window.scrollY > 10) {
              header.classList.add('header-scrolled');
          } else {
              header.classList.remove('header-scrolled');
          }
      });

      // Scroll-triggered animations
      const observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
              if (entry.isIntersecting) {
                  entry.target.classList.add('is-visible');
              }
          });
      }, { threshold: 0.1 });

      document.querySelectorAll('.reveal').forEach(el => {
          observer.observe(el);
      });

      // "View More" for Courses
      const viewMoreBtn = document.getElementById('view-more-courses');
      if (viewMoreBtn) {
          viewMoreBtn.addEventListener('click', () => {
              const hiddenMobileCourses = document.querySelectorAll('.course-card-mobile[style*="display: none"]');
              const hiddenDesktopCourses = document.querySelectorAll('.course-card-desktop[style*="display: none"]');
              let isShowingMore = viewMoreBtn.textContent === 'Show Less';

              // Toggle Mobile Courses
              hiddenMobileCourses.forEach(card => card.style.display = isShowingMore ? 'none' : 'block');
              
              // Toggle Desktop Courses
              hiddenDesktopCourses.forEach(card => card.style.display = isShowingMore ? 'none' : 'flex'); // Use flex for desktop cards
              
              // Adjust horizontal scroll height for desktop
              const desktopWrapper = document.querySelector('#programs .hidden.md\\:block.horizontal-scroll-wrapper');
              if(desktopWrapper) {
                 const totalCourses = <?php echo $school_programs->num_rows; ?>;
                 if (isShowingMore) {
                    desktopWrapper.style.height = `${Math.min(10, totalCourses) * 50}vh`;
                 } else {
                    desktopWrapper.style.height = `${totalCourses * 50}vh`;
                 }
              }

              // Change button text
              viewMoreBtn.textContent = isShowingMore ? 'View More Courses' : 'Show Less';
          });
      }


      // Horizontal Scroll Logic - DESKTOP ONLY
      if (window.innerWidth >= 768) { // md breakpoint
        document.querySelectorAll('.horizontal-scroll-wrapper').forEach(wrapper => {
            const track = wrapper.querySelector('.horizontal-scroll-track');
            if (track) {
                window.addEventListener('scroll', () => {
                    const wrapperRect = wrapper.getBoundingClientRect();
                    const wrapperTop = wrapperRect.top;
                    const wrapperHeight = wrapperRect.height;
                    const windowHeight = window.innerHeight;

                    if (wrapperTop <= 0 && wrapperTop > -wrapperHeight + windowHeight) {
                        const scrollableDist = track.scrollWidth - track.clientWidth;
                        const scrollProgress = -wrapperTop / (wrapperHeight - windowHeight);
                        const clampedProgress = Math.min(Math.max(scrollProgress, 0), 1);
                        
                        track.style.transform = `translateX(-${clampedProgress * scrollableDist}px)`;
                    }
                });
            }
        });
      }
    </script>
</body>
</html>

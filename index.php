<?php
include "header.php";  

// Initialise statistics
$studentsEnrolled = 0;
$coursesAvailable = 0;
$expertInstructors = 0;
$averageRating = 0.0;
$topCourses = [];

try {
	// Fetch total number of students enrolled
	$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) AS total_students FROM User_Enrollments");
	$stmt->execute();
	$studentsEnrolled = $stmt->fetchColumn();

	// Fetch total number of courses available
	$stmt = $conn->prepare("SELECT COUNT(*) AS total_courses FROM Courses");
	$stmt->execute();
	$coursesAvailable = $stmt->fetchColumn();

	// Fetch total number of instructors
	$stmt = $conn->prepare("SELECT COUNT(DISTINCT instructor_id) AS total_instructors FROM Courses");
	$stmt->execute();
	$expertInstructors = $stmt->fetchColumn();

	// Fetch average course rating
	$stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM User_Ratings");
	$stmt->execute();
	$averageRating = $stmt->fetchColumn();
	// Fallback if no ratings are available
	$averageRating = $averageRating ?: 0;

	// Fetch top 5 courses by highest ratings
	$stmt = $conn->prepare("
		SELECT c.course_id, c.course_name, c.course_cover, AVG(ur.rating) AS average_rating
		FROM Courses c
		JOIN User_Ratings ur ON c.course_id = ur.course_id
		GROUP BY c.course_id
		ORDER BY average_rating DESC
		LIMIT 5
		");
	$stmt->execute();
	$topCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
	// Handle errors if any
	echo "Error: " . $e->getMessage();
}
?>

<!-- Hero Section -->
<section class="hero-section text-center text-white d-flex align-items-center" style="background-image: url('images/header.jpg'); background-size: cover; height: 70vh;">
	<div class="container">
		<h1 class="display-4 font-weight-bold">Welcome to LearnBuddy</h1>
		<p class="lead">Your Personalised Learning Journey Begins Here</p>
		<a href="courses.php" class="btn btn-lg btn-primary mt-3">Explore Courses</a>
	</div>
</section>

<!-- Top 5 Courses -->
<section class="top-courses py-5">
	<div class="container">
		<h2 class="text-center mb-4">Top 5 Courses</h2>
		<div class="row">
			<?php if (!empty($topCourses)): ?>
				<?php foreach ($topCourses as $course): ?>
					<div class="col-md-4 mb-4">
						<div class="card shadow-sm h-100">
							<img src="<?php echo htmlspecialchars($course['course_cover']); ?>" class="card-img-top course-card-img" alt="<?php echo htmlspecialchars($course['course_name']); ?>">
							<div class="card-body">
								<h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
								<p class="card-text">Average Rating: <?php echo number_format($course['average_rating'], 1); ?>/5</p>
								<a href="course_details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary">View Course</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else: ?>
				<p class="text-center">No top-rated courses found.</p>
			<?php endif; ?>
		</div>
	</div>
</section>

<!-- Dynamic Statistics Section -->
<section class="statistics-section bg-light py-5">
	<div class="container">
		<div class="row text-center">
			<div class="col-md-3 mb-4">
				<h3 class="display-4 font-weight-bold"><?php echo number_format($studentsEnrolled); ?>+</h3>
				<p>Students Enrolled</p>
			</div>
			<div class="col-md-3 mb-4">
				<h3 class="display-4 font-weight-bold"><?php echo number_format($coursesAvailable); ?>+</h3>
				<p>Courses Available</p>
			</div>
			<div class="col-md-3 mb-4">
				<h3 class="display-4 font-weight-bold"><?php echo number_format($expertInstructors); ?>+</h3>
				<p>Expert Instructors</p>
			</div>
			<div class="col-md-3 mb-4">
				<h3 class="display-4 font-weight-bold"><?php echo number_format($averageRating, 1); ?>/5</h3>
				<p>Average Rating</p>
			</div>
		</div>
	</div>
</section>

<!-- CEO Message Section -->
<section class="ceo-message py-5">
	<div class="container text-center">
		<h2>Message from Our CEO</h2>
		<p class="lead mt-4">"At LearnBuddy, we aim to provide everyone with the knowledge to make it in life. We strive to ensure that every learner can receive a quality education that is personalised to their requirements."</p>
		<p class="mt-3 font-italic">- Muhammad, CEO of LearnBuddy</p>
	</div>
</section>

<?php include "footer.php"; ?>
<?php 
include "header.php"; 
include "db_connect.php";

// Check if user is logged in 
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$user_id = $_SESSION['user_id'];

// Initialise statistics
$totalEnrolledCourses = 0;
$totalCreatedCourses = 0;
$totalEnrollments = 0;
$totalRatingsGiven = 0;
$averageRatingGiven = 0;
$totalRatingsReceived = 0;
$averageRatingReceived = 0;

// Fetch statistics for the user
try {
	// Fetch total enrolled courses
	$stmt = $conn->prepare("SELECT COUNT(*) FROM User_Enrollments WHERE user_id = :user_id");
	$stmt->execute([':user_id' => $user_id]);
	$totalEnrolledCourses = $stmt->fetchColumn();

	// Fetch total created courses (if user is also an instructor)
	$stmt = $conn->prepare("SELECT COUNT(*) FROM Courses WHERE instructor_id = :user_id");
	$stmt->execute([':user_id' => $user_id]);
	$totalCreatedCourses = $stmt->fetchColumn();

	// Fetch total enrollments in courses created by the user (as an instructor)
	$stmt = $conn->prepare("
		SELECT COUNT(*) 
		FROM User_Enrollments ue
		JOIN Courses c ON ue.course_id = c.course_id
		WHERE c.instructor_id = :user_id
		");
	$stmt->execute([':user_id' => $user_id]);
	$totalEnrollments = $stmt->fetchColumn();

	// Fetch total ratings given by the user (as a learner)
	$stmt = $conn->prepare("SELECT COUNT(rating), AVG(rating) FROM User_Ratings WHERE user_id = :user_id");
	$stmt->execute([':user_id' => $user_id]);
	$ratingsDataGiven = $stmt->fetch(PDO::FETCH_ASSOC);
	$totalRatingsGiven = $ratingsDataGiven['COUNT(rating)'];
	$averageRatingGiven = $ratingsDataGiven['AVG(rating)'] ?: 0;

	// Fetch total ratings received on courses created by the user (as an instructor)
	$stmt = $conn->prepare("
		SELECT COUNT(rating), AVG(rating) 
		FROM User_Ratings ur
		JOIN Courses c ON ur.course_id = c.course_id
		WHERE c.instructor_id = :user_id
		");
	$stmt->execute([':user_id' => $user_id]);
	$ratingsDataReceived = $stmt->fetch(PDO::FETCH_ASSOC);
	$totalRatingsReceived = $ratingsDataReceived['COUNT(rating)'];
	$averageRatingReceived = $ratingsDataReceived['AVG(rating)'] ?: 0;

} catch (PDOException $e) {
	$errors['database'] = "Error fetching statistics: " . $e->getMessage();
}
?>

<!-- Dashboard Content -->
<div class="container dashboard-section">
	<div class="row">
		<!-- Statistics Card: Total Enrolled Courses -->
		<div class="col-md-4 mb-4">
			<div class="card dashboard-card shadow-sm">
				<div class="card-header d-flex align-items-center">
					<span class="mr-2 statistics-icon"><i class="fas fa-book-open"></i></span>
					<span>Total Enrolled Courses</span>
				</div>
				<div class="card-body text-center">
					<p><?php echo $totalEnrolledCourses; ?></p>
				</div>
			</div>
		</div>

		<!-- Statistics Card: Total Created Courses -->
		<div class="col-md-4 mb-4">
			<div class="card dashboard-card shadow-sm">
				<div class="card-header d-flex align-items-center">
					<span class="mr-2 statistics-icon"><i class="fas fa-chalkboard-teacher"></i></span>
					<span>Total Created Courses</span>
				</div>
				<div class="card-body text-center">
					<p><?php echo $totalCreatedCourses; ?></p>
				</div>
			</div>
		</div>

		<!-- Statistics Card: Total Enrollments in Created Courses -->
		<div class="col-md-4 mb-4">
			<div class="card dashboard-card shadow-sm">
				<div class="card-header d-flex align-items-center">
					<span class="mr-2 statistics-icon"><i class="fas fa-users"></i></span>
					<span>Total Enrollments in Created Courses</span>
				</div>
				<div class="card-body text-center">
					<p><?php echo $totalEnrollments; ?></p>
				</div>
			</div>
		</div>

		<!-- Statistics Card: Average Rating Given -->
		<div class="col-md-4 mb-4">
			<div class="card dashboard-card shadow-sm">
				<div class="card-header d-flex align-items-center">
					<span class="mr-2 statistics-icon"><i class="fas fa-star"></i></span>
					<span>Average Rating Given</span>
				</div>
				<div class="card-body text-center">
					<p><?php echo number_format($averageRatingGiven, 1); ?> / 5</p>
				</div>
			</div>
		</div>

		<!-- Statistics Card: Total Ratings Received -->
		<div class="col-md-4 mb-4">
			<div class="card dashboard-card shadow-sm">
				<div class="card-header d-flex align-items-center">
					<span class="mr-2 statistics-icon"><i class="fas fa-star-half-alt"></i></span>
					<span>Total Ratings Received</span>
				</div>
				<div class="card-body text-center">
					<p><?php echo $totalRatingsReceived; ?></p>
				</div>
			</div>
		</div>

		<!-- Statistics Card: Average Rating Received -->
		<div class="col-md-4 mb-4">
			<div class="card dashboard-card shadow-sm">
				<div class="card-header d-flex align-items-center">
					<span class="mr-2 statistics-icon"><i class="fas fa-star"></i></span>
					<span>Average Rating Received</span>
				</div>
				<div class="card-body text-center">
					<p><?php echo number_format($averageRatingReceived, 1); ?> / 5</p>
				</div>
			</div>
		</div>
	</div>

	<!-- List of Enrolled or Created Courses -->
	<div class="row">
		<div class="col-md-12">
			<h4 class="mb-4">Your Courses</h4>
			<a href="create_lecture.php" class="btn btn-secondary btn-lg mb-4">Add Lectures</a>
			<div class="list-group shadow-sm">
				<?php 
				// Fetch enrolled courses
				$stmt = $conn->prepare("
					SELECT c.course_name, 'Enrolled' AS course_status
					FROM Courses c
					JOIN User_Enrollments ue ON c.course_id = ue.course_id
					WHERE ue.user_id = :user_id
					");
				$stmt->execute([':user_id' => $user_id]);
				$enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

				// Fetch created courses
				$stmt = $conn->prepare("SELECT course_name, 'Created' AS course_status FROM Courses WHERE instructor_id = :user_id");
				$stmt->execute([':user_id' => $user_id]);
				$createdCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

				// Display enrolled courses
				foreach ($enrolledCourses as $course): ?>
					<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
						<?php echo htmlspecialchars($course['course_name']); ?>
						<span class="badge badge-primary badge-pill">
							<?php echo $course['course_status']; ?>
						</span>
					</a>
				<?php endforeach; ?>

				<!-- Display created courses -->
				<?php foreach ($createdCourses as $course): ?>
					<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
						<?php echo htmlspecialchars($course['course_name']); ?>
						<span class="badge badge-success badge-pill">
							<?php echo $course['course_status']; ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<?php include "footer.php"; ?>

<?php
include "header.php";  

// Only logged-in users can access this page 
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php"); 
	exit();
}

$user_id = $_SESSION['user_id'];

// Initialise recommended courses arrays
$skillBasedCourses = [];
$collaborativeCourses = [];
$contentBasedCourses = [];

try {
	// Skill-Based Matching Courses
	$stmt = $conn->prepare("
		SELECT c.course_id, c.course_name, c.course_cover, COUNT(cs.skill_id) AS skill_match_count
		FROM Courses c
		JOIN Course_Skills cs ON c.course_id = cs.course_id
		JOIN User_Interests ui ON cs.skill_id = ui.skill_id
		WHERE ui.user_id = :user_id
		GROUP BY c.course_id
		ORDER BY skill_match_count DESC
		LIMIT 5
		");
	$stmt->execute([':user_id' => $user_id]);
	$skillBasedCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Collaborative Filtering Courses
	$stmt = $conn->prepare("
		SELECT c.course_id, c.course_name, c.course_cover, COUNT(ue.user_id) AS similar_enrollment_count
		FROM Courses c
		JOIN User_Enrollments ue ON c.course_id = ue.course_id
		WHERE ue.user_id IN (
			SELECT ue2.user_id
			FROM User_Enrollments ue2
			WHERE ue2.course_id IN (SELECT course_id FROM User_Enrollments WHERE user_id = :user_id)
			AND ue2.user_id != :user_id
			)
		GROUP BY c.course_id
		ORDER BY similar_enrollment_count DESC
		LIMIT 5
		");
	$stmt->execute([':user_id' => $user_id]);
	$collaborativeCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Content-Based Filtering Courses
	$stmt = $conn->prepare("
		SELECT DISTINCT c.course_id, c.course_name, c.course_cover
		FROM Courses c
		JOIN Course_Skills cs ON c.course_id = cs.course_id
		WHERE cs.skill_id IN (
			SELECT cs2.skill_id 
			FROM User_Enrollments ue
			JOIN Course_Skills cs2 ON ue.course_id = cs2.course_id
			WHERE ue.user_id = :user_id
			)
		AND c.course_id NOT IN (SELECT course_id FROM User_Enrollments WHERE user_id = :user_id)
		LIMIT 5
		");
	$stmt->execute([':user_id' => $user_id]);
	$contentBasedCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
	echo "Error: " . $e->getMessage();
}
?>

<!-- Recommendation Page -->
<div class="container recommendation-section mt-5">
	<h2 class="mb-4">Recommended Courses For You</h2>

	<!-- Skill-Based Recommendations -->
	<section class="mb-5">
		<h3 class="section-heading">Courses Based on Your Skills</h3>
		<div class="row">
			<?php if (!empty($skillBasedCourses)): ?>
				<?php foreach ($skillBasedCourses as $course): ?>
					<div class="col-md-4 mb-4">
						<div class="card shadow-sm h-100">
							<img src="<?php echo htmlspecialchars($course['course_cover']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['course_name']); ?>">
							<div class="card-body">
								<h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
								<a href="course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary">View Course</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else: ?>
				<p class="empty-state">No courses found matching your skills.</p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Divider -->
	<hr class="section-divider">

	<!-- Collaborative Filtering Recommendations -->
	<section class="mb-5">
		<h3 class="section-heading">Courses Taken by Similar Learners</h3>
		<div class="row">
			<?php if (!empty($collaborativeCourses)): ?>
				<?php foreach ($collaborativeCourses as $course): ?>
					<div class="col-md-4 mb-4">
						<div class="card shadow-sm h-100">
							<img src="images/<?php echo htmlspecialchars($course['course_cover']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['course_name']); ?>">
							<div class="card-body">
								<h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
								<a href="course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary">View Course</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else: ?>
				<p class="empty-state">No collaborative courses found.</p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Divider -->
	<hr class="section-divider">

	<!-- Content-Based Filtering Recommendations -->
	<section class="mb-5">
		<h3 class="section-heading">Courses Similar to Your Enrollments</h3>
		<div class="row">
			<?php if (!empty($contentBasedCourses)): ?>
				<?php foreach ($contentBasedCourses as $course): ?>
					<div class="col-md-4 mb-4">
						<div class="card shadow-sm h-100">
							<img src="images/<?php echo htmlspecialchars($course['course_cover']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['course_name']); ?>">
							<div class="card-body">
								<h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
								<a href="course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary">View Course</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else: ?>
				<p class="empty-state">No content-based recommendations found.</p>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php include "footer.php"; ?>
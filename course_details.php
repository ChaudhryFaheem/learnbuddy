<?php 

include "header.php";  

// Only allow access if 'id' is present in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
	header("Location: courses.php"); 
	exit();
}

// Fetch the course ID from the URL
$course_id = intval($_GET['id']);

// Initialise variables
$course = [];
$feedbacks = [];
$userFeedback = null;
$lectures = [];
$skills = [];
$errors = [];
$successMessage = '';
$averageRating = 0.0;
$totalRatings = 0;
$instructorName = '';
$instructorPhoto = 'default.jpg'; 
$isEnrolled = false;  

// Check if the user is logged in 
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rating']) && isset($_POST['feedbackText']) && $isLoggedIn) {
	$rating = intval($_POST['rating']);
	$feedbackText = trim($_POST['feedbackText']);

	if ($rating < 1 || $rating > 5) {
		$errors['rating'] = "Please provide a valid rating between 1 and 5.";
	}

	if (empty($feedbackText)) {
		$errors['feedbackText'] = "Please provide your feedback.";
	}

	if (empty($errors)) {
		try {
			// Insert or update the feedback in the database
			if ($userFeedback) {
				// Update existing feedback
				$stmt = $conn->prepare("
					UPDATE User_Ratings 
					SET rating = :rating, feedback = :feedback, updated_at = NOW() 
					WHERE user_id = :user_id AND course_id = :course_id
					");
				$stmt->execute([
					':rating' => $rating,
					':feedback' => $feedbackText,
					':user_id' => $userId,
					':course_id' => $course_id
				]);
				$successMessage = "Your feedback has been updated successfully!";
			} else {
				// Insert new feedback
				$stmt = $conn->prepare("
					INSERT INTO User_Ratings (user_id, course_id, rating, feedback, rated_at) 
					VALUES (:user_id, :course_id, :rating, :feedback, NOW())
					");
				$stmt->execute([
					':user_id' => $userId,
					':course_id' => $course_id,
					':rating' => $rating,
					':feedback' => $feedbackText
				]);
				$successMessage = "Your feedback has been submitted successfully!";
			}

			// Refresh user feedback after submitting
			$stmt = $conn->prepare("
				SELECT * FROM User_Ratings 
				WHERE course_id = :course_id AND user_id = :user_id
				");
			$stmt->execute([':course_id' => $course_id, ':user_id' => $userId]);
			$userFeedback = $stmt->fetch(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			$errors['feedback'] = "Error submitting feedback: " . $e->getMessage();
		}
	}
}

try {
	// Fetch course details and instructor information
	$stmt = $conn->prepare("
		SELECT c.*, u.username AS instructor_name, u.profile_photo AS instructor_photo, u.description AS instructor_description
		FROM Courses c 
		JOIN Users u ON c.instructor_id = u.user_id 
		WHERE c.course_id = :course_id
		");
	$stmt->execute([':course_id' => $course_id]);
	$course = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$course) {
		throw new Exception("Course not found");
	}

	$instructorName = $course['instructor_name'];
	$instructorPhoto = $course['instructor_photo'] ?: 'default.png'; 
	$instructorDescription = $course['instructor_description'];

	// Fetch user feedbacks for the course
	$stmt = $conn->prepare("
		SELECT ur.*, u.username 
		FROM User_Ratings ur 
		JOIN Users u ON ur.user_id = u.user_id 
		WHERE ur.course_id = :course_id
		");
	$stmt->execute([':course_id' => $course_id]);
	$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Fetch lectures for the course
	$stmt = $conn->prepare("SELECT lecture_id, title, duration FROM Lectures WHERE course_id = :course_id");
	$stmt->execute([':course_id' => $course_id]);
	$lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Fetch skills related to the course
	$stmt = $conn->prepare("
		SELECT s.skill_name 
		FROM Skills s 
		JOIN Course_Skills cs ON s.skill_id = cs.skill_id 
		WHERE cs.course_id = :course_id
		");
	$stmt->execute([':course_id' => $course_id]);
	$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Calculate average rating and total number of ratings
	if (!empty($feedbacks)) {
		$totalRatings = count($feedbacks);
		$sumRatings = array_sum(array_column($feedbacks, 'rating'));
		$averageRating = $totalRatings ? $sumRatings / $totalRatings : 0.0;
	}

	// Check if the user is enrolled in the course
	if ($isLoggedIn) {
		$stmt = $conn->prepare("SELECT * FROM User_Enrollments WHERE course_id = :course_id AND user_id = :user_id");
		$stmt->execute([':course_id' => $course_id, ':user_id' => $userId]);
		$isEnrolled = $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;

		// Check if the user has already submitted feedback
		$stmt = $conn->prepare("
			SELECT * FROM User_Ratings 
			WHERE course_id = :course_id AND user_id = :user_id
			");
		$stmt->execute([':course_id' => $course_id, ':user_id' => $userId]);
		$userFeedback = $stmt->fetch(PDO::FETCH_ASSOC);
	}

} catch (Exception $e) {
	$errors['database'] = "Error: " . $e->getMessage();
}

// Handle the enrollment button click
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll']) && $isLoggedIn && !$isEnrolled) {
	try {
		// Enroll the user in the course
		$stmt = $conn->prepare("INSERT INTO User_Enrollments (user_id, course_id, enrolled_at) VALUES (:user_id, :course_id, NOW())");
		$stmt->execute([':user_id' => $userId, ':course_id' => $course_id]);
		// Set enrollment status to true after successful enrollment
		$isEnrolled = true; 
		$successMessage = "You have been successfully enrolled in the course!";
	} catch (PDOException $e) {
		$errors['enroll'] = "Error enrolling in the course: " . $e->getMessage();
	}
}


?>

<div class="container mt-5">
	<div class="row">
		<!-- Course Information -->
		<div class="col-lg-8">
			<!-- Course Title and Description -->
			<div class="card mb-4">
				<?php if (!empty($errors)): ?>
					<div class="alert alert-danger">
						<ul>
							<?php foreach ($errors as $error): ?>
								<li><?php echo $error; ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				
				<?php if (!empty($successMessage)): ?>
					<div class="alert alert-success">
						<?php echo $successMessage; ?>
					</div>
				<?php endif; ?>

				<img src="<?php echo htmlspecialchars($course['course_cover']); ?>" class="card-img-top" alt="Course Cover">
				<div class="card-body">
					<h2 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h2>
					<p class="text-muted">By <?php echo htmlspecialchars($instructorName); ?></p>
					<div class="d-flex align-items-center mb-3">
						<span class="badge badge-success mr-2"><?php echo number_format($averageRating, 1); ?></span>
						<span class="text-muted"><i class="fas fa-star text-warning"></i> <?php echo number_format($averageRating, 1); ?> (<?php echo $totalRatings; ?> Ratings)</span>
					</div>
					<p class="card-text"><?php echo htmlspecialchars($course['description']); ?></p>

					<?php if ($isLoggedIn): ?>
						<!-- Enroll Now Button -->
						<form method="POST" action="">
							<?php if ($isEnrolled): ?>
								<button class="btn btn-success btn-lg btn-block" disabled>Enrolled</button>
							<?php else: ?>
								<button class="btn btn-primary btn-lg btn-block" type="submit" name="enroll">Enroll Now</button>
							<?php endif; ?>
						</form>
					<?php else: ?>
						<p class="text-danger">Please <a href="login.php">log in</a> to enroll in this course.</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Skills Tags Section -->
			<div class="card mb-4">
				<div class="card-header bg-primary text-white">
					<h4>Skills Covered in This Course</h4>
				</div>
				<div class="card-body">
					<div class="d-flex flex-wrap">
						<?php foreach ($skills as $skill): ?>
							<span class="badge badge-pill badge-info mr-2 mb-2"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Course Content (Lectures) -->
			<div class="card mb-4">
				<div class="card-header bg-primary text-white">
					<h4>Course Content</h4>
				</div>
				<div class="card-body">
					<ul class="list-group list-group-flush">
						<?php foreach ($lectures as $lecture): ?>
							<a href="lecture.php?lecture_id=<?php echo htmlspecialchars($lecture['lecture_id']); ?>"><li class="list-group-item d-flex justify-content-between align-items-center">
								<?php echo htmlspecialchars($lecture['title']); ?> <span class="text-muted"><?php echo $lecture['duration']; ?> mins</span>
							</li></a>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<!-- Feedback Section -->
			<?php if ($isLoggedIn): ?>
				<div class="card mb-4">
					<div class="card-header bg-primary text-white">
						<h4>Provide Your Feedback</h4>
					</div>
					<div class="card-body"> 
						<?php if ($userFeedback): ?>
							<!-- Display user's feedback -->
							<p>Your Rating: <?php echo $userFeedback['rating']; ?>/5</p>
							<p>Your Feedback: <?php echo htmlspecialchars($userFeedback['feedback']); ?></p>
						<?php else: ?>
							<!-- Feedback form -->
							<form method="POST" action="">
								<div class="form-group">
									<label for="rating">Your Rating</label>
									<select class="form-control" name="rating" required>
										<option value="" disabled selected>Select your rating</option>
										<option value="5">5 - Excellent</option>
										<option value="4">4 - Very Good</option>
										<option value="3">3 - Good</option>
										<option value="2">2 - Fair</option>
										<option value="1">1 - Poor</option>
									</select>
								</div>
								<div class="form-group">
									<label for="feedbackText">Your Feedback</label>
									<textarea class="form-control" name="feedbackText" rows="4" placeholder="Write your feedback here..." required></textarea>
								</div>
								<button type="submit" class="btn btn-primary btn-block">Submit Feedback</button>
							</form>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Instructor Section -->
			<div class="card mb-4">
				<div class="card-header bg-primary text-white">
					<h4>About the Instructor</h4>
				</div>
				<div class="card-body d-flex">
					<img src="<?php echo htmlspecialchars($instructorPhoto); ?>" alt="Instructor" class="img-thumbnail mr-3" style="width: 100px; height: 100px;">
					<div>
						<h5><?php echo htmlspecialchars($instructorName); ?></h5>
						<p><?php echo htmlspecialchars($instructorName); echo htmlspecialchars($instructorDescription); ?>      </p>
						<a href="#" class="btn btn-outline-primary">View Profile</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Sidebar with Course Actions -->
		<div class="col-lg-4">
			<!-- Course Summary Card -->
			<div class="card mb-4">
				<div class="card-body">
					<h4 class="mb-3">Course Summary</h4>
					<p><strong>Instructor:</strong> <?php echo htmlspecialchars($instructorName); ?> </p>
					<p><strong>Duration:</strong> 3 hours</p>
					<p><strong>Lectures:</strong> <?php echo count($lectures); ?></p>
					<p><strong>Skill Level:</strong> <?php echo htmlspecialchars($course['difficulty_level']); ?></p>
					<p><strong>Language:</strong> English</p>
					<p><strong>Ratings:</strong> <?php echo number_format($averageRating, 1); ?> (<?php echo $totalRatings; ?> Ratings)</p>
					<?php if ($isLoggedIn && $isEnrolled): ?>
						<button class="btn btn-success btn-block mt-3" disabled>Enrolled</button>
					<?php elseif ($isLoggedIn): ?>
						<form method="POST" action="">
							<button class="btn btn-primary btn-block mt-3" type="submit" name="enroll">Enroll Now</button>
						</form>
					<?php else: ?>
						<p class="text-danger">Please <a href="login.php">log in</a> to enroll in this course.</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Ratings & Reviews Section -->
			<div class="card mb-4">
				<div class="card-header bg-primary text-white">
					<h4>Ratings & Reviews</h4>
				</div>
				<div class="card-body">
					<?php foreach ($feedbacks as $feedback): ?>
						<div class="media mb-3">
							<img src="<?php echo htmlspecialchars($instructorPhoto); ?>" class="mr-3 rounded-circle" alt="User" style="width: 50px; height: 50px;">
							<div class="media-body">
								<h5 class="mt-0"><?php echo htmlspecialchars($feedback['username']); ?> <small class="text-muted"><?php echo htmlspecialchars($feedback['rating']); ?>/5</small></h5>
								<p><?php echo htmlspecialchars($feedback['feedback']); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
					<a href="#" class="btn btn-outline-primary btn-block">Read All Reviews</a>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include "footer.php"; ?>
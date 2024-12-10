<?php 

include "header.php"; 

// Only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php"); 
	exit();
}

// Initialise variables and error messages
$courseName = $courseDescription = $difficultyLevel = $courseCover = '';
$errors = [];
$successMessage = '';

// Fetch predefined skills from the database
$predefinedSkills = [];
try {
	$stmt = $conn->prepare("SELECT skill_id, skill_name FROM Skills WHERE is_custom = 0");
	$stmt->execute();
	$predefinedSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$errors['database'] = "Error fetching skills: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// Validate Course Name
	if (empty(trim($_POST['courseName']))) {
		$errors['courseName'] = 'Course name is required.';
	} else {
		$courseName = htmlspecialchars(trim($_POST['courseName']));
	}

	// Validate Course Description
	if (empty(trim($_POST['courseDescription']))) {
		$errors['courseDescription'] = 'Course description is required.';
	} elseif (strlen(trim($_POST['courseDescription'])) < 20) {
		$errors['courseDescription'] = 'Course description must be at least 20 characters.';
	} else {
		$courseDescription = htmlspecialchars(trim($_POST['courseDescription']));
	}

	// Validate Difficulty Level
	if (empty($_POST['difficultyLevel'])) {
		$errors['difficultyLevel'] = 'Please select a difficulty level.';
	} else {
		$difficultyLevel = htmlspecialchars($_POST['difficultyLevel']);
	}

	// Handle Course Cover Image
	if (isset($_FILES['courseCover']) && $_FILES['courseCover']['error'] == 0) {
		$courseCover = 'uploads/' . basename($_FILES['courseCover']['name']);
		move_uploaded_file($_FILES['courseCover']['tmp_name'], $courseCover);  
	} else {
		$errors['courseCover'] = 'Course cover image is required.';
	}

	// Validate Skills (at least one predefined or custom skill)
	if (empty($_POST['predefinedSkills']) && empty(array_filter($_POST['skills'], fn($skill) => !empty(trim($skill))))) {
		$errors['skills'] = 'At least one skill (predefined or custom) is required.';
	}

	// If no errors, insert data
	if (empty($errors)) {
		try {
			// Insert course data into Courses table
			$stmt = $conn->prepare("INSERT INTO Courses (course_name, description, difficulty_level, instructor_id, course_cover) VALUES (:course_name, :description, :difficulty_level, :instructor_id, :course_cover)");
			$stmt->execute([
				':course_name' => $courseName,
				':description' => $courseDescription,
				':difficulty_level' => $difficultyLevel,
				':instructor_id' => $_SESSION['user_id'],
				':course_cover' => $courseCover
			]);

			// Get the last inserted course ID
			$course_id = $conn->lastInsertId();

			// Handle Predefined Skills
			if (isset($_POST['predefinedSkills']) && is_array($_POST['predefinedSkills'])) {
				foreach ($_POST['predefinedSkills'] as $skill_id) {
					$stmt = $conn->prepare("INSERT INTO Course_Skills (course_id, skill_id) VALUES (:course_id, :skill_id)");
					$stmt->execute([
						':course_id' => $course_id,
						':skill_id' => $skill_id
					]);
				}
			}

			// Handle Custom Skills
			if (isset($_POST['skills']) && is_array($_POST['skills'])) {
				foreach ($_POST['skills'] as $customSkill) {
					$customSkill = htmlspecialchars(trim($customSkill));
					if (!empty($customSkill)) {
						// Insert custom skill into Skills table
						$stmt = $conn->prepare("INSERT INTO Skills (skill_name, is_custom) VALUES (:skill_name, 1)");
						$stmt->execute([':skill_name' => $customSkill]);
						$skill_id = $conn->lastInsertId();

						// Insert into Course_Skills table
						$stmt = $conn->prepare("INSERT INTO Course_Skills (course_id, skill_id) VALUES (:course_id, :skill_id)");
						$stmt->execute([
							':course_id' => $course_id,
							':skill_id' => $skill_id
						]);
					}
				}
			}

			$successMessage = "Course created successfully!";
		} catch (PDOException $e) {
			$errors['database'] = "Error: " . $e->getMessage();
		}
	}
}
?>

<div class="container mt-5">
	<div class="row justify-content-center">
		<div class="col-md-8">
			<div class="card shadow-lg">
				<div class="card-header text-center bg-primary text-white">
					<h3>Create Course</h3>
				</div>
				<div class="card-body">
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

					<form id="createCourseForm" method="POST" action="create_course.php" enctype="multipart/form-data">
						<!-- Course Information -->
						<div class="form-group">
							<label for="courseName">Course Name</label>
							<input type="text" class="form-control" name="courseName" value="<?php echo htmlspecialchars($courseName); ?>" placeholder="Enter course name" required>
							<div class="error-message" id="courseNameError"></div>
						</div>
						<div class="form-group">
							<label for="courseDescription">Course Description</label>
							<textarea class="form-control" name="courseDescription" rows="4" placeholder="Enter course description" required><?php echo htmlspecialchars($courseDescription); ?></textarea>
							<div class="error-message" id="courseDescriptionError"></div>
						</div>
						<div class="form-group">
							<label for="courseCover">Course Cover Image</label>
							<input type="file" class="form-control-file" name="courseCover" required>
							<div class="error-message" id="courseCoverError"></div>
						</div>
						<div class="form-group">
							<label for="difficultyLevel">Difficulty Level</label>
							<select class="form-control" name="difficultyLevel">
								<option value="">Select Difficulty Level</option>
								<option value="Beginner" <?php echo ($difficultyLevel == 'Beginner') ? 'selected' : ''; ?>>Beginner</option>
								<option value="Intermediate" <?php echo ($difficultyLevel == 'Intermediate') ? 'selected' : ''; ?>>Intermediate</option>
								<option value="Advanced" <?php echo ($difficultyLevel == 'Advanced') ? 'selected' : ''; ?>>Advanced</option>
							</select>
							<div class="error-message" id="difficultyLevelError"></div>
						</div>

						<!-- Predefined Skills Selection -->
						<div class="form-group">
							<label for="predefinedSkills">Select Predefined Skills/Interests</label>
							<div id="predefinedSkills" class="mb-3">
								<?php foreach ($predefinedSkills as $skill): ?>
									<div class="form-check">
										<input class="form-check-input" type="checkbox" name="predefinedSkills[]" value="<?php echo $skill['skill_id']; ?>" id="skill<?php echo $skill['skill_id']; ?>">
										<label class="form-check-label" for="skill<?php echo $skill['skill_id']; ?>"><?php echo $skill['skill_name']; ?></label>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="error-message" id="skillsError"></div>
						</div>

						<!-- Custom Skills Input -->
						<div class="form-group">
							<label for="customSkill">Add Custom Skills/Interests</label>
							<div id="dynamicSkills">
								<div class="input-group mb-2">
									<input type="text" class="form-control" name="skills[]" placeholder="Enter a custom skill">
									<div class="input-group-append">
										<button class="btn btn-danger removeSkill" type="button">&times;</button>
									</div>
								</div>
							</div>
							<button type="button" class="btn btn-secondary btn-sm" id="addSkill">Add Custom Skill</button>
						</div>

						<!-- Submit Button -->
						<button type="submit" class="btn btn-primary btn-block">Create Course</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>


<script>
	$(document).ready(function () {
		// Add custom skill input dynamically
		$('#addSkill').click(function () {
			$('#dynamicSkills').append(`
				<div class="input-group mb-2">
				<input type="text" class="form-control" name="skills[]" placeholder="Enter a custom skill">
				<div class="input-group-append">
				<button class="btn btn-danger removeSkill" type="button">&times;</button>
				</div>
				</div>
				`);
		});

		// Remove custom skill input
		$(document).on('click', '.removeSkill', function () {
			$(this).closest('.input-group').remove();
		});

		// Real-time input validation
		function validateForm() {
			let isValid = true;

			// Validate Course Name
			if ($('input[name="courseName"]').val().trim() === '') {
				$('#courseNameError').text('Course name is required.');
				isValid = false;
			} else {
				$('#courseNameError').text('');
			}

			// Validate Course Description
			if ($('textarea[name="courseDescription"]').val().trim() === '') {
				$('#courseDescriptionError').text('Course description is required.');
				isValid = false;
			} else if ($('textarea[name="courseDescription"]').val().trim().length < 20) {
				$('#courseDescriptionError').text('Course description must be at least 20 characters.');
				isValid = false;
			} else {
				$('#courseDescriptionError').text('');
			}

			// Validate Difficulty Level
			if ($('select[name="difficultyLevel"]').val() === '') {
				$('#difficultyLevelError').text('Please select a difficulty level.');
				isValid = false;
			} else {
				$('#difficultyLevelError').text('');
			}

			// Validate Course Cover
			if ($('input[name="courseCover"]').get(0).files.length === 0) {
				$('#courseCoverError').text('Course cover image is required.');
				isValid = false;
			} else {
				$('#courseCoverError').text('');
			}

			// Validate Skills (at least one predefined or custom skill)
			if ($('#predefinedSkills input:checked').length === 0 && $('#dynamicSkills input').filter(function() { return $(this).val().trim() !== ''; }).length === 0) {
				$('#skillsError').text('At least one skill (predefined or custom) is required.');
				isValid = false;
			} else {
				$('#skillsError').text('');
			}

			return isValid;
		}

		// Check inputs on form submission
		$('#createCourseForm').on('submit', function (e) {
			if (!validateForm()) {
				e.preventDefault();
				console.log('Form is invalid. Please correct the errors and try again.');
			}
		});

		// Real-time validation on input change
		$('input[name="courseName"], textarea[name="courseDescription"], select[name="difficultyLevel"], input[name="courseCover"]').on('input change', function () {
			validateForm();
		});
	});
</script>

<?php include "footer.php"; ?>
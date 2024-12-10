<?php 

include "header.php"; 

// Only logged-in users with at least one course can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if the user has at least one course
$user_id = $_SESSION['user_id'];
$userCourses = [];
try {
    $stmt = $conn->prepare("SELECT course_id, course_name FROM Courses WHERE instructor_id = :instructor_id");
    $stmt->execute([':instructor_id' => $user_id]);
    $userCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($userCourses) == 0) {
        // Redirect if the user has no courses
        // In-case of a user that have no courses
        header("Location: dashboard.php");  
        exit();
    }
} catch (PDOException $e) {
    $errors['database'] = "Error fetching courses: " . $e->getMessage();
}

// Initialise variables and error messages
$lectureTitle = $lectureDescription = $lectureDuration = $videoUrl = '';
$errors = [];
$successMessage = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate Course Selection
    if (empty($_POST['selectCourse'])) {
        $errors['selectCourse'] = 'Please select a course.';
    } else {
        $selectedCourse = htmlspecialchars($_POST['selectCourse']);
    }

    // Validate Lecture Title
    if (empty(trim($_POST['lectureTitle']))) {
        $errors['lectureTitle'] = 'Lecture title is required.';
    } else {
        $lectureTitle = htmlspecialchars(trim($_POST['lectureTitle']));
    }

    // Validate Lecture Description
    if (empty(trim($_POST['lectureDescription']))) {
        $errors['lectureDescription'] = 'Lecture description is required.';
    } elseif (str_word_count(trim($_POST['lectureDescription'])) < 20) {
        $errors['lectureDescription'] = 'Lecture description must be at least 20 words.';
    } else {
        $lectureDescription = htmlspecialchars(trim($_POST['lectureDescription']));
    }

    // Validate Lecture Duration
    if (empty($_POST['lectureDuration']) || $_POST['lectureDuration'] <= 0) {
        $errors['lectureDuration'] = 'Please enter a valid duration.';
    } else {
        $lectureDuration = htmlspecialchars($_POST['lectureDuration']);
    }

    // Validate Video URL
    $videoUrl = htmlspecialchars(trim($_POST['videoUrl']));
    if (empty($videoUrl)) {
        $errors['videoUrl'] = 'Video URL is required.';
    } elseif (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        $errors['videoUrl'] = 'Please enter a valid URL.';
    }

    // If no errors, insert data
    if (empty($errors)) {
        try {
            // Insert lecture data into Lectures table
            $stmt = $conn->prepare("INSERT INTO Lectures (course_id, title, description, duration, video_url) VALUES (:course_id, :title, :description, :duration, :video_url)");
            $stmt->execute([
                ':course_id' => $selectedCourse,
                ':title' => $lectureTitle,
                ':description' => $lectureDescription,
                ':duration' => $lectureDuration,
                ':video_url' => $videoUrl
            ]);

            $successMessage = "Lecture created successfully!";
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
                    <h3>Create Lecture</h3>
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

                    <form id="createLectureForm" method="POST" action="create_lecture.php">
                        <div class="form-group">
                            <label for="selectCourse">Select Course</label>
                            <select class="form-control" name="selectCourse" id="selectCourse" required>
                                <option value="">Select a course</option>
                                <?php foreach ($userCourses as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>"><?php echo $course['course_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="error-message text-danger" id="selectCourseError"></div>
                        </div>
                        <div class="form-group">
                            <label for="lectureTitle">Lecture Title</label>
                            <input type="text" class="form-control" name="lectureTitle" id="lectureTitle" placeholder="Enter lecture title" required>
                            <div class="error-message text-danger" id="lectureTitleError"></div>
                        </div>
                        <div class="form-group">
                            <label for="lectureDescription">Lecture Description</label>
                            <textarea class="form-control" name="lectureDescription" id="lectureDescription" rows="4" placeholder="Enter lecture description"></textarea>
                            <div class="error-message text-danger" id="lectureDescriptionError"></div>
                        </div>
                        <div class="form-group">
                            <label for="lectureDuration">Lecture Duration (minutes)</label>
                            <input type="number" class="form-control" name="lectureDuration" id="lectureDuration" placeholder="Enter duration" required>
                            <div class="error-message text-danger" id="lectureDurationError"></div>
                        </div>
                        <div class="form-group">
                            <label for="videoUrl">Video URL (YouTube URL)</label>
                            <input type="text" class="form-control" name="videoUrl" id="videoUrl" placeholder="Enter video URL" required>
                            <div class="error-message text-danger" id="videoUrlError"></div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Create Lecture</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Real-time input validation
        function validateForm() {
            let isValid = true;

            // Validate Course Selection
            if ($('#selectCourse').val() === '') {
                $('#selectCourseError').text('Please select a course.');
                isValid = false;
            } else {
                $('#selectCourseError').text('');
            }

            // Validate Lecture Title
            if ($('#lectureTitle').val().trim() === '') {
                $('#lectureTitleError').text('Lecture title is required.');
                isValid = false;
            } else {
                $('#lectureTitleError').text('');
            }

            // Validate Lecture Description
            if ($('#lectureDescription').val().trim() === '') {
                $('#lectureDescriptionError').text('Lecture description is required.');
                isValid = false;
            } else if ($('#lectureDescription').val().trim().split(/\s+/).length < 20) {
                $('#lectureDescriptionError').text('Lecture description must be at least 20 words.');
                isValid = false;
            } else {
                $('#lectureDescriptionError').text('');
            }

            // Validate Lecture Duration
            if ($('#lectureDuration').val().trim() === '' || $('#lectureDuration').val() <= 0) {
                $('#lectureDurationError').text('Please enter a valid duration.');
                isValid = false;
            } else {
                $('#lectureDurationError').text('');
            }

            // Validate Video URL
            const videoUrl = $('#videoUrl').val().trim();
            if (videoUrl === '') {
                $('#videoUrlError').text('Video URL is required.');
                isValid = false;
            } else if (!isValidURL(videoUrl)) {
                $('#videoUrlError').text('Please enter a valid URL.');
                isValid = false;
            } else {
                $('#videoUrlError').text('');
            }

            return isValid;
        }

        // URL validation function
        function isValidURL(url) {
            const pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
                '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.?)+[a-z]{2,}|'+ // domain name
                '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
                '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
                '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
                '(\\#[-a-z\\d_]*)?$','i'); // fragment locator
            return !!pattern.test(url);
        }

        // Check inputs on form submission
        $('#createLectureForm').on('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                console.log('Form is invalid. Please correct the errors and try again.');
            }
        });

        // Real-time validation on input change
        $('#selectCourse, #lectureTitle, #lectureDescription, #lectureDuration, #videoUrl').on('input change', function () {
            validateForm();
        });
    });
</script>

<?php include "footer.php"; ?>

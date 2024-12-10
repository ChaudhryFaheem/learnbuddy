<?php 

include "header.php"; 

// Only allow access if 'id' is present in the URL
if (!isset($_GET['lecture_id']) || empty($_GET['lecture_id'])) {
    header("Location: courses.php"); 
    exit();
}

// Fetch the lecture ID from the URL
$lecture_id = intval($_GET['lecture_id']);

// Initialise variables
$lecture = [];
$comments = [];
$relatedLectures = [];
$errors = [];
$successMessage = '';

// Check if the user is logged in 
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;


// Handle comment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $isLoggedIn) {
    $commentText = htmlspecialchars(trim($_POST['commentText']));

    // Validate inputs
    if (empty($commentText)) {
        $errors['commentText'] = "Comment cannot be empty.";
    }

    // If no errors, insert comment
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO Lecture_Comments (lecture_id, user_id, comment, commented_at) 
                VALUES (:lecture_id, :user_id, :comment, NOW())
                ");
            $stmt->execute([
                ':lecture_id' => $lecture_id,
                ':user_id' => $userId,
                ':comment' => $commentText
            ]);
            $successMessage = "Your comment has been posted!";
        } catch (PDOException $e) {
            $errors['database'] = "Error submitting comment: " . $e->getMessage();
        }
    }
}

try {
    // Fetch lecture details
    $stmt = $conn->prepare("SELECT * FROM Lectures WHERE lecture_id = :lecture_id");
    $stmt->execute([':lecture_id' => $lecture_id]);
    $lecture = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecture) {
        throw new Exception("Lecture not found");
    }

    // Fetch comments for the lecture
    $stmt = $conn->prepare("
        SELECT lc.*, u.username, u.profile_photo
        FROM Lecture_Comments lc 
        JOIN Users u ON lc.user_id = u.user_id 
        WHERE lc.lecture_id = :lecture_id 
        ORDER BY lc.commented_at DESC
        ");
    $stmt->execute([':lecture_id' => $lecture_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch related lectures from the same course
    $stmt = $conn->prepare("
        SELECT lecture_id, title 
        FROM Lectures 
        WHERE course_id = :course_id AND lecture_id != :lecture_id
        ");
    $stmt->execute([':course_id' => $lecture['course_id'], ':lecture_id' => $lecture_id]);
    $relatedLectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $errors['database'] = "Error: " . $e->getMessage();
}

?>

<!-- Single Video Section -->
<div class="container mt-5">
    <div class="row">
        <!-- Video Player and Details -->
        <div class="col-lg-8">
            <!-- Video Player -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title">Lecture: <?php echo htmlspecialchars($lecture['title']); ?></h3>
                    <div class="embed-responsive embed-responsive-16by9 mb-3">
                        <iframe width="560" height="315" src="<?php echo htmlspecialchars($lecture['video_url']); ?>&amp;controls=0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    <p class="text-muted">Duration: <?php echo $lecture['duration']; ?> mins</p>
                    <p class="card-text"><?php echo htmlspecialchars($lecture['description']); ?></p>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Comments</h4>
                </div>
                <div class="card-body">
                    <!-- Comment Form -->
                    <?php if ($isLoggedIn): ?>
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

                        <form method="POST" id="commentForm" class="mb-4">
                            <div class="form-group">
                                <textarea class="form-control" name="commentText" rows="3" placeholder="Add your comment..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    <?php else: ?>
                        <p class="text-danger">Please <a href="login.php">log in</a> to post comments.</p>
                    <?php endif; ?>

                    <!-- Display Comments -->
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="media mb-3">
                                <img src="<?php echo htmlspecialchars($comment['profile_photo']); ?>" class="mr-3 rounded-circle" alt="User" style="width: 50px; height: 50px;">
                                <div class="media-body">
                                    <h5 class="mt-0"><?php echo htmlspecialchars($comment['username']); ?> <small class="text-muted"><?php echo date('F j, Y, g:i a', strtotime($comment['commented_at'])); ?></small></h5>
                                    <p><?php echo htmlspecialchars($comment['comment']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No comments yet. Be the first to comment!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar: Lecture Navigation -->
        <div class="col-lg-4">
            <!-- Lecture Navigation -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Course Lectures</h4>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <!-- Display related lectures -->
                        <?php foreach ($relatedLectures as $relatedLecture): ?>
                            <li class="list-group-item">
                                <a href="lecture.php?lecture_id=<?php echo $relatedLecture['lecture_id']; ?>">
                                    <?php echo htmlspecialchars($relatedLecture['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <!-- In case there are no related lectures -->
                        <?php if (empty($relatedLectures)): ?>
                            <li class="list-group-item">No related lectures found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
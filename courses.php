<?php 

include "header.php"; 


// Initialise variables
$courses = [];
$predefinedSkills = [];
$customSkills = [];
$errors = [];

try {
    // Fetch all courses with average rating
    $stmt = $conn->prepare("
        SELECT c.*, IFNULL(AVG(ur.rating), 0) AS avg_rating
        FROM Courses c
        LEFT JOIN User_Ratings ur ON c.course_id = ur.course_id
        GROUP BY c.course_id
        ");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch predefined skills (non-custom skills)
    $stmt = $conn->prepare("SELECT skill_id, skill_name FROM Skills WHERE is_custom = 0");
    $stmt->execute();
    $predefinedSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch custom skills
    $stmt = $conn->prepare("SELECT skill_id, skill_name FROM Skills WHERE is_custom = 1");
    $stmt->execute();
    $customSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors['database'] = "Error fetching data: " . $e->getMessage();
}
?>

<div class="container mt-5">
    <div class="row">
        <!-- Filter Sidebar -->
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Filter Courses</h4>
                </div>
                <div class="card-body">
                    <!-- Course Name Filter -->
                    <div class="form-group">
                        <label for="courseName">Course Name</label>
                        <input type="text" class="form-control" id="courseName" placeholder="Enter course name">
                    </div>
                    <!-- Skill Level Filter -->
                    <div class="form-group">
                        <label for="skillLevel">Skill Level</label>
                        <select class="form-control" id="skillLevel">
                            <option value="all">All Levels</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                    <!-- Category Filter (Predefined Skills) -->
                    <div class="form-group">
                        <label for="category">Category (Predefined Skills)</label>
                        <select class="form-control" id="category">
                            <option value="all">All Categories</option>
                            <?php foreach ($predefinedSkills as $skill): ?>
                                <option value="<?php echo htmlspecialchars($skill['skill_name']); ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Custom Skills Filter -->
                    <div class="form-group">
                        <label for="customSkills">Custom Skills</label>
                        <select class="form-control" id="customSkills">
                            <option value="all">All Custom Skills</option>
                            <?php foreach ($customSkills as $skill): ?>
                                <option value="<?php echo htmlspecialchars($skill['skill_name']); ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Rating Filter -->
                    <div class="form-group">
                        <label for="rating">Rating</label>
                        <select class="form-control" id="rating">
                            <option value="all">All Ratings</option>
                            <option value="4">4 & Up</option>
                            <option value="3">3 & Up</option>
                            <option value="2">2 & Up</option>
                            <option value="1">1 & Up</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-block" id="filterButton">Apply Filters</button>
                    <button class="btn btn-secondary btn-block mt-2" id="clearFiltersButton">Clear Filters</button>
                </div>
            </div>
        </div>

        <!-- Courses Display -->
        <div class="col-lg-9">
            <!-- Recommendation Button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Courses</h2>
                <a href="recommend_course.php"><button class="btn btn-success" id="recommendButton"><i class="fas fa-magic"></i> Recommend Courses</button>
                </a>
            </div>

            <!-- Courses List -->
            <div class="row" id="coursesList">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm course-card h-100">
                                <img src="<?php echo htmlspecialchars($course['course_cover']); ?>" class="card-img-top course-card-img" alt="Course Cover">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                    <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <span class="badge badge-success"><?php echo number_format($course['avg_rating'], 1); ?></span>
                                        <a href="course_details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary btn-sm">View More</a>
                                    </div>
                                    <!-- Hidden elements to facilitate filtering -->
                                    <span class="course-difficulty" style="display: none;"><?php echo strtolower($course['difficulty_level']); ?></span>
                                    <span class="course-category" style="display: none;"><?php echo strtolower($course['course_name']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-warning">No courses found.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Apply filters on button click
        $('#filterButton').click(function () {
            const courseName = $('#courseName').val().trim().toLowerCase();
            const skillLevel = $('#skillLevel').val().toLowerCase();
            const category = $('#category').val().toLowerCase();
            const customSkill = $('#customSkills').val().toLowerCase();
            const rating = $('#rating').val();

            $('#coursesList .col-md-4').each(function () {
                const card = $(this);
                const title = card.find('.card-title').text().toLowerCase();
                const difficulty = card.find('.course-difficulty').text().toLowerCase();
                const courseCategory = card.find('.course-category').text().toLowerCase();
                const courseRating = parseFloat(card.find('.badge').text());

                let show = true;

                if (courseName && !title.includes(courseName)) {
                    show = false;
                }

                if (skillLevel !== 'all' && difficulty !== skillLevel) {
                    show = false;
                }

                if (category !== 'all' && !courseCategory.includes(category)) {
                    show = false;
                }

                if (customSkill !== 'all' && !courseCategory.includes(customSkill)) {
                    show = false;
                }

                if (rating !== 'all' && courseRating < parseFloat(rating)) {
                    show = false;
                }

                if (show) {
                    card.show();
                } else {
                    card.hide();
                }
            });
        });

        // Clear filters on button click
        $('#clearFiltersButton').click(function () {
            $('#courseName').val('');
            $('#skillLevel').val('all');
            $('#category').val('all');
            $('#customSkills').val('all');
            $('#rating').val('all');
            $('#coursesList .col-md-4').show();  
        });
        
    });
</script>

<?php include "footer.php"; ?>

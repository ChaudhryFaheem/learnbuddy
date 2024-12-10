<?php 

include "header.php"; 

// Initialise variables and error messages
$username = $email = $password = $confirm_password = $profilePhoto = '';
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
    // Validate Username
    if (empty(trim($_POST['username']))) {
        $errors['username'] = 'Username is required.';
    } else {
        $username = htmlspecialchars(trim($_POST['username']));
    }

    // Validate Email
    if (empty(trim($_POST['email']))) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    } else {
        $email = htmlspecialchars(trim($_POST['email']));
    }

    // Validate Password
    if (empty(trim($_POST['password']))) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen(trim($_POST['password'])) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    } else {
        $password = trim($_POST['password']);
    }

    // Confirm Password
    if (empty(trim($_POST['confirm_password']))) {
        $errors['confirm_password'] = 'Confirm your password.';
    } elseif ($_POST['confirm_password'] !== $_POST['password']) {
        $errors['confirm_password'] = 'Passwords do not match.';
    } else {
        $confirm_password = trim($_POST['confirm_password']);
    }

    // Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Handle Profile Photo
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] == 0) {
        $profilePhoto = 'uploads/' . basename($_FILES['profilePhoto']['name']);
        move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $profilePhoto);  
    }

    // If no errors, insert data
    if (empty($errors)) {
        try {
            // Insert user data into Users table
            $stmt = $conn->prepare("INSERT INTO Users (username, email, password, profile_photo) VALUES (:username, :email, :password, :profile_photo)");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashed_password,
                ':profile_photo' => $profilePhoto
            ]);

            // Get the last inserted user ID
            $user_id = $conn->lastInsertId();

            // Handle Predefined Skills
            if (isset($_POST['predefinedSkills']) && is_array($_POST['predefinedSkills'])) {
                foreach ($_POST['predefinedSkills'] as $skill_id) {
                    $stmt = $conn->prepare("INSERT INTO User_Interests (user_id, skill_id) VALUES (:user_id, :skill_id)");
                    $stmt->execute([
                        ':user_id' => $user_id,
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

                        // Insert into User_Interests table
                        $stmt = $conn->prepare("INSERT INTO User_Interests (user_id, skill_id) VALUES (:user_id, :skill_id)");
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':skill_id' => $skill_id
                        ]);
                    }
                }
            }

            $successMessage = "Registration successful!";
        } catch (PDOException $e) {
            $errors['database'] = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg">
                <div class="card-header text-center bg-primary text-white">
                    <h3>Register</h3>
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

                    <form id="registrationForm" method="POST" action="registration.php" enctype="multipart/form-data">
                        <!-- Basic Information -->
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Enter your username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Confirm your password" required>
                        </div>
                        <div class="form-group">
                            <label for="profilePhoto">Profile Photo</label>
                            <input type="file" class="form-control-file" name="profilePhoto">
                        </div>

                        <!-- Predefined Skills Selection -->
                        <div class="form-group">
                            <label for="predefinedSkills">Select Predefined Skills/Interests</label>
                            <div id="predefinedSkills" class="mb-3">
                                <!-- Predefined skills -->
                                <?php foreach ($predefinedSkills as $skill): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="predefinedSkills[]" value="<?php echo $skill['skill_id']; ?>" id="skill<?php echo $skill['skill_id']; ?>">
                                        <label class="form-check-label" for="skill<?php echo $skill['skill_id']; ?>"><?php echo $skill['skill_name']; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Custom Skills Input -->
                        <div class="form-group">
                            <label for="customSkill">Add Custom Skills/Interests</label>
                            <div id="dynamicSkills"></div>
                            <button type="button" class="btn btn-secondary btn-sm" id="addSkill">Add Custom Skill</button>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Custom skill input dynamically
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
        $('input[name="username"], input[name="email"], input[name="password"], input[name="confirm_password"]').on('input', function () {
            let fieldName = $(this).attr('name');
            let errorMessage = '';
            
            if (fieldName === 'username' && $(this).val().trim() === '') {
                errorMessage = 'Username is required.';
            } else if (fieldName === 'email') {
                let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test($(this).val().trim())) {
                    errorMessage = 'Invalid email format.';
                }
            } else if (fieldName === 'password' && $(this).val().length < 6) {
                errorMessage = 'Password must be at least 6 characters.';
            } else if (fieldName === 'confirm_password' && $(this).val() !== $('input[name="password"]').val()) {
                errorMessage = 'Passwords do not match.';
            }

            $(this).next('.error-message').remove();
            if (errorMessage !== '') {
                $(this).after(`<div class="text-danger error-message">${errorMessage}</div>`);
            }
        });

        // Prevent form submission if there are errors
        $('#registrationForm').on('submit', function (e) {
            $('.error-message').remove();
            let hasError = false;

            $('input[name="username"], input[name="email"], input[name="password"], input[name="confirm_password"]').trigger('input');

            if ($('.error-message').length > 0) {
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });
    });
</script>

<?php include "footer.php"; ?>
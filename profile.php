<?php 
include "header.php";

// Check if user is logged in 
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");  
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$profileSuccessMessage = '';
$passwordSuccessMessage = '';   
$skillsSuccessMessage = '';  

// Fetch user profile data and skills from the database
try {
    // Fetch user details
    $stmt = $conn->prepare("SELECT username, email, profile_photo FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch all predefined (non-custom) skills
    $stmt = $conn->prepare("SELECT * FROM Skills WHERE is_custom = 0");
    $stmt->execute();
    $nonCustomSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user's selected skills (both custom and non-custom)
    $stmt = $conn->prepare("SELECT skill_id FROM User_Interests WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $userSkills = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Fetch custom skills (added by user)
    $stmt = $conn->prepare("
        SELECT s.skill_id, s.skill_name 
        FROM Skills s 
        JOIN User_Interests ui ON s.skill_id = ui.skill_id 
        WHERE s.is_custom = 1 AND ui.user_id = :user_id
        ");
    $stmt->execute([':user_id' => $user_id]);
    $customSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors['database'] = "Error fetching profile data: " . $e->getMessage();
}

// Handle skill deletion (for non-custom skills)
if (isset($_POST['deleteSkill'])) {
    $skill_id = intval($_POST['deleteSkill']);
    
    try {
        // Begin transaction
        $conn->beginTransaction();

        // Delete from User_Interests
        $stmt = $conn->prepare("DELETE FROM User_Interests WHERE user_id = :user_id AND skill_id = :skill_id");
        $stmt->execute([':user_id' => $user_id, ':skill_id' => $skill_id]);

        // Commit the transaction
        $conn->commit();
        $skillsSuccessMessage = "Skill deleted successfully!";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors['database'] = "Error deleting skill: " . $e->getMessage();
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updatePassword'])) {
    // Get POST data
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Check if new passwords match
    if ($newPassword !== $confirmPassword) {
        $errors['password_mismatch'] = "New passwords do not match.";
    }

    // Fetch the current password hash from the database
    try {
        $stmt = $conn->prepare("SELECT password FROM Users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $errors['user_not_found'] = "User not found.";
        } else {
            // Verify the current password using password_verify
            if (!password_verify($currentPassword, $user_data['password'])) {
                $errors['invalid_password'] = "The current password you entered is incorrect.";
            }
        }
    } catch (PDOException $e) {
        $errors['database'] = "Error fetching user data: " . $e->getMessage();
    }

    // If no errors, proceed to update the password
    if (empty($errors)) {
        // Hash the new password using password_hash()
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            // Update the password in the database
            $stmt = $conn->prepare("UPDATE Users SET password = :password WHERE user_id = :user_id");
            $stmt->execute([
                ':password' => $hashedPassword,
                ':user_id' => $user_id
            ]);

            $passwordSuccessMessage = "Your password has been updated successfully!";
        } catch (PDOException $e) {
            $errors['database'] = "Error updating password: " . $e->getMessage();
        }
    }
}

// Handle form submission for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateProfile'])) {
    $updatedUsername = $_POST['username'];
    $updatedEmail = $_POST['email'];
    $profilePhoto = $user['profile_photo'];

    // Validate and handle the profile photo upload
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
        $targetDirectory = "uploads/";
        $profilePhotoName = basename($_FILES['profilePhoto']['name']);
        $targetFilePath = $targetDirectory . $profilePhotoName;
        $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        // Check if the file is a valid image
        $check = getimagesize($_FILES['profilePhoto']['tmp_name']);
        if ($check === false) {
            $errors['photo'] = "File is not an image.";
        }

        // Check file size (max 2MB)
        if ($_FILES['profilePhoto']['size'] > 2000000) {
            $errors['photo'] = "Profile photo must be less than 2MB.";
        }

        // Allow only certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $errors['photo'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }

        // If no errors, move the uploaded file
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $targetFilePath)) {
                $profilePhoto = $profilePhotoName;  
            } else {
                $errors['photo'] = "There was an error uploading the file.";
            }
        }
    }

    // If no errors, update the profile in the database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE Users SET username = :username, email = :email, profile_photo = :profile_photo WHERE user_id = :user_id");
            $stmt->execute([
                ':username' => $updatedUsername,
                ':email' => $updatedEmail,
                ':profile_photo' => $profilePhoto,
                ':user_id' => $user_id
            ]);
            $profileSuccessMessage = "Your profile has been updated successfully!";
            header("Location: profile.php"); // Redirect to the profile page to refresh data
            exit();
        } catch (PDOException $e) {
            $errors['database'] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle form submission (for updating skills)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateSkills'])) {
    try {
        // Begin a transaction
        $conn->beginTransaction();

        // Fetch submitted predefined skills
        $selectedSkills = isset($_POST['predefinedSkills']) ? $_POST['predefinedSkills'] : [];
        $customSkillNames = isset($_POST['customSkills']) ? $_POST['customSkills'] : [];

        // Remove all current non-custom skills for the user
        $stmt = $conn->prepare("
            DELETE FROM User_Interests 
            WHERE user_id = :user_id 
            AND skill_id IN (SELECT skill_id FROM Skills WHERE is_custom = 0)
            ");
        $stmt->execute([':user_id' => $user_id]);

        // Re-insert the selected predefined skills (non-custom)
        foreach ($selectedSkills as $skill_id) {
            $stmt = $conn->prepare("INSERT INTO User_Interests (user_id, skill_id) VALUES (:user_id, :skill_id)");
            $stmt->execute([':user_id' => $user_id, ':skill_id' => $skill_id]);
        }

        // Handle custom skills
        // Fetch current custom skills from the database
        $existingCustomSkills = array_column($customSkills, 'skill_name', 'skill_id');

        // Add new custom skills
        foreach ($customSkillNames as $customSkill) {
            $customSkill = trim($customSkill);
            if (!empty($customSkill) && !in_array($customSkill, $existingCustomSkills)) {
                // Insert new custom skill into the Skills table
                $stmt = $conn->prepare("INSERT INTO Skills (skill_name, is_custom) VALUES (:skill_name, 1)");
                $stmt->execute([':skill_name' => $customSkill]);
                $skill_id = $conn->lastInsertId();

                // Insert into User_Interests table
                $stmt = $conn->prepare("INSERT INTO User_Interests (user_id, skill_id) VALUES (:user_id, :skill_id)");
                $stmt->execute([':user_id' => $user_id, ':skill_id' => $skill_id]);
            }
        }

        // Remove custom skills not present in the form
        foreach ($existingCustomSkills as $skill_id => $skill_name) {
            if (!in_array($skill_name, $customSkillNames)) {
                // Delete from User_Interests
                $stmt = $conn->prepare("DELETE FROM User_Interests WHERE user_id = :user_id AND skill_id = :skill_id");
                $stmt->execute([':user_id' => $user_id, ':skill_id' => $skill_id]);

                // Optionally, delete from Skills table
                $stmt = $conn->prepare("DELETE FROM Skills WHERE skill_id = :skill_id AND is_custom = 1");
                $stmt->execute([':skill_id' => $skill_id]);
            }
        }

        // Commit the transaction
        $conn->commit();
        $skillsSuccessMessage = "Skills updated successfully!";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors['database'] = "Error updating skills: " . $e->getMessage();
    }
}

?>

<!-- User Profile Section -->
<div class="container mt-5">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($profileSuccessMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $profileSuccessMessage; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($passwordSuccessMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $passwordSuccessMessage; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($skillsSuccessMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $skillsSuccessMessage; ?>
                </div>
            <?php endif; ?>

            <!-- Basic Information Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>User Profile</h4>
                </div>
                <div class="card-body">
                    <form id="updateProfileForm" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="profilePhoto">Profile Photo</label>
                            <input type="file" class="form-control-file" id="profilePhoto" name="profilePhoto">
                            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo" class="img-thumbnail mt-2" style="width: 100px; height: 100px;">
                        </div>
                        <button type="submit" name="updateProfile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Password Update Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Change Password</h4>
                </div>
                <div class="card-body">
                    <form id="updatePasswordForm" method="POST">
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" class="form-control" name="currentPassword" id="currentPassword" required>
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" class="form-control" name="newPassword" id="newPassword" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" required>
                        </div>
                        <button type="submit" name="updatePassword" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Skills and Interests Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Skills and Interests</h4>
                </div>
                <div class="card-body">
                    <!-- Display Predefined (Non-Custom) Skills with Checkboxes -->
                    <form id="updateSkillsForm" method="POST">
                        <div class="form-group">
                            <h5>Select Predefined Skills</h5>
                            <div id="skillsList" class="mb-3">
                                <!-- Display predefined skills in checkboxes -->
                                <?php if (!empty($nonCustomSkills)): ?>
                                    <?php foreach ($nonCustomSkills as $skill): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="skill<?php echo $skill['skill_id']; ?>" name="predefinedSkills[]" value="<?php echo $skill['skill_id']; ?>" 
                                            <?php echo in_array($skill['skill_id'], $userSkills) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="skill<?php echo $skill['skill_id']; ?>">
                                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No predefined skills found.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Custom Skills Input -->
                            <label for="customSkill">Add Custom Skills/Interests</label>
                            <div id="dynamicSkills">
                                <?php foreach ($customSkills as $customSkill): ?>
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" name="customSkills[]" value="<?php echo htmlspecialchars($customSkill['skill_name']); ?>" placeholder="Enter a custom skill">
                                        <div class="input-group-append">
                                            <button class="btn btn-danger removeSkill" type="button" value="<?php echo $customSkill['skill_id']; ?>">Delete</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="addSkill">Add Custom Skill</button>
                        </div>
                        <button type="submit" name="updateSkills" class="btn btn-primary mt-3">Update Skills</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Custom JavaScript for handling form submissions -->
<script>
    $(document).ready(function () {
        // Custom skill input dynamically
        $('#addSkill').click(function () {
            $('#dynamicSkills').append(`
                <div class="input-group mb-2">
                <input type="text" class="form-control" name="customSkills[]" placeholder="Enter a custom skill">
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

        // Handle skills update form submission
        $('#updateSkillsForm').submit(function () {
            // This form is submitted via standard POST, no need for preventDefault()
            var selectedSkills = [];
            $('#skillsList input:checked').each(function () {
                selectedSkills.push($(this).val());
            });

            var customSkills = [];
            $('input[name="customSkills[]"]').each(function () {
                if ($(this).val().trim() !== '') {
                    customSkills.push($(this).val().trim());
                }
            });

            console.log('Updating skills with:', selectedSkills, customSkills);
            // Form will now submit naturally to the server since preventDefault() is removed
        });
    });
</script>


<?php include"footer.php"; ?>
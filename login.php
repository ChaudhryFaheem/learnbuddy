<?php 

include "header.php"; 

// Initialise variables and error messages
$email = $password = '';
$errors = [];
$successMessage = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
	} else {
		$password = trim($_POST['password']);
	}

	// If no errors, check login credentials
	if (empty($errors)) {
		try {
			// Check if user exists
			$stmt = $conn->prepare("SELECT user_id, password FROM Users WHERE email = :email");
			$stmt->execute([':email' => $email]);
			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($user && password_verify($password, $user['password'])) {
				// Password is correct, start a new session
				session_start();
				$_SESSION['username'] = $user['username'];
				$_SESSION['user_id'] = $user['user_id'];
				$_SESSION['email'] = $email;  
				header("Location: dashboard.php");  
				exit();
			} else {
				$errors['login'] = 'Invalid email or password.';
			}
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
					<h3>Login</h3>
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

					<form id="loginForm" method="POST" action="login.php">
						<div class="form-group">
							<label for="email">Email</label>
							<input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
						</div>
						<div class="form-group">
							<label for="password">Password</label>
							<input type="password" class="form-control" name="password" placeholder="Enter your password" required>
						</div>
						<button type="submit" class="btn btn-primary btn-block">Login</button>
						<p class="text-center mt-3">Don't have an account? <a href="registration.php">Register here</a></p>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	$(document).ready(function () {
		// Real-time input validation
		$('input[name="email"], input[name="password"]').on('input', function () {
			let fieldName = $(this).attr('name');
			let errorMessage = '';

			if (fieldName === 'email') {
				let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailPattern.test($(this).val().trim())) {
					errorMessage = 'Invalid email format.';
				}
			} else if (fieldName === 'password' && $(this).val().trim() === '') {
				errorMessage = 'Password is required.';
			}

			$(this).next('.error-message').remove();
			if (errorMessage !== '') {
				$(this).after(`<div class="text-danger error-message">${errorMessage}</div>`);
			}
		});

		// Prevent form submission if there are errors
		$('#loginForm').on('submit', function (e) {
			$('.error-message').remove();
			let hasError = false;

			$('input[name="email"], input[name="password"]').trigger('input');

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
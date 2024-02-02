<?php
	session_start();
	session_destroy();

	$user_name = "h";
	$password  = "123";
	$user_name = $password = "";
?>

<script>
	if (localStorage.getItem('block')) {
		window.location.reload(true);
	}
</script>

<!doctype html>
<html lang="en">

<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link href="https://fonts.googleapis.com/css?family=Roboto:400,700,900&display=swap" rel="stylesheet">

	<link rel="stylesheet" href="fonts/icomoon/style.css">

	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="css/bootstrap.min.css">

	<!-- Style -->
	<link rel="stylesheet" href="css/style.css">

	<title>Login</title>
</head>

<body>
	<div class="content">
		<div class="container">
			<div class="row align-items-stretch justify-content-center">
				<div class="col-lg-6 mb-lg-0 mb-5">
					<div class="form h-100 contact-wrap p-5">
						<h3 class="text-center">تسجيل الدخول</h3>
						<form class="mb-5 text-right" method="post" id="loginForm" autocomplete="off">
							<div class="row">
								<div class="col-md-12 form-group mb-3">
									<label for="name" class="col-form-label">الإسم *</label>
									<input type="text" class="form-control" name="name" id="name" value="<?= $user_name; ?>" placeholder="الإسم" autofocus autocomplete="off">
								</div>
							</div>

							<div class="row mb-5">
								<div class="col-md-12 form-group mb-3">
									<label for="password" class="col-form-label"> كلمة المرور *</label>
									<input type="password" class="form-control" name="password" value="<?= $password; ?>" id="password" placeholder=" كلمة المرور">
								</div>
							</div>
							<div class="row justify-content-center">
								<div class="col-md-6 form-group text-center">
									<button type="submit" class="btn btn-block btn-primary rounded-0 py-2 px-4">Login</button>
								</div>
							</div>

							<div id="messages"></div>
						</form>
						<p class="text-muted" style="text-align: center; font-size: 15px;">Welcome To Our Project</p>
					</div>
				</div>
			</div>
		</div>
	</div>


	<script src="js/jquery-3.3.1.min.js"></script>
	<script src="js/popper.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/jquery.validate.min.js"></script>

	<script>
		$(function() {
			localStorage.removeItem('login-time');

			$("#loginForm").validate({
				rules: {
					name: {required: true},
					password: {required: true},
				},
				messages: {
					name: "Please enter name",
					password: "Please enter password",
				},
				submitHandler: function (form, e) {
					e.preventDefault();
					form = $(form);
					$('#messages').empty();
					$.ajax({
						type: "POST",
						url: "php/login.php",
						data: form.serialize(),
						beforeSend: function () {form.find('button').attr("disabled", "disabled")},
						success: function (response) {
							response = JSON.parse(response);

							if (response.status == 0) {
								localStorage.setItem('block', true);
								window.location.reload(true);
								return;
							}

							if (response.status !== 200) {
								$('#messages').append(`<p class="alert alert-danger">${response.message}</p>`);
								form.find('button').removeAttr("disabled");
							} else {
								window.location.href = 'home.php';
							}
						},
						error: function(response) {
							$('#messages').append(`<p class="alert alert-danger text-right">Something is wrong</p>`);
						}
					});
				},
			});
		});
	</script>
</body>

</html>

<?php
	session_start(); 

	if (! isset( $_SESSION['api_token'] ) || empty( $_SESSION['api_token'] )) {
        header('Location:../index.php');
        exit();
    }
?>

<!doctype html>
<html lang="en">

<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link href="https://fonts.googleapis.com/css?family=Roboto:400,700,900&display=swap" rel="stylesheet">

	<link rel="stylesheet" href="../fonts/icomoon/style.css">

	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="../css/bootstrap.min.css">

	<!-- Style -->
	<link rel="stylesheet" href="../css/style.css">

	<title>Form</title>
</head>

<body>
	<div class="content pb-5">
		<div class="container">
			<div class="row align-items-stretch justify-content-center">
				<div class="col-lg-6 mb-lg-0 mb-5">
					<div class="form h-100 contact-wrap py-5 px-2">
						<h3 class="text-center py-1 bg-info text-white">النقل السريع</h3>
						<form class="mb-5 px-5 text-right" dir="rtl" method="post" id="contactForm" name="contactForm">
							<div class="row">
								<div class="col-md-12 form-group mb-3">
									<label for="unit_code" class="col-form-label">كود الوحدة *</label>
									<input type="text" class="form-control" name="unit_code" id="unit_code" placeholder="كود الوحدة">
								</div>
							</div>

							<div class="row mb-5">
								<div class="col-md-12 form-group mb-3">
									<label for="authentication_code" class="col-form-label">رمز الدخول *</label>
									<textarea class="form-control" name="authentication_code" id="authentication_code"
										cols="30" rows="4" placeholder="رمز الدخول"></textarea>
								</div>
							</div>
							<div class="row justify-content-center">
								<div class="col-md-12 form-group text-center">
									<input type="submit" value="حجز" style="width: 40%;" class="btn btn-primary rounded-0 py-2 px-4">
									<button type="button" id="reset-form" style="width: 40%;" class="btn btn-danger rounded-0 py-2 px-4"> حذف البيانات </button>
									<span class="submitting">Wait...</span>
								</div>
							</div>
						</form>

						<div id="form-message-warning mt-4"></div>
						<div id="form-message-success" style="text-align: center;"></div>

					</div>
				</div>

				<div class="col-lg-6">
					<div class="form h-100 contact-wrap pt-5 pb-2 px-2">
						<h3 class="text-center py-1 bg-warning text-white">الرسائل</h3>
						<ul id="load-messages" dir="rtl" class="text-right" style="overflow-y: scroll; max-height: 440px;"></ul>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		login_time = "<?= $_SESSION['time'] ?>";

		if (localStorage.getItem('login-time')) {
			window.location.href = '../index.php';
		} else {
			localStorage.setItem('login-time', "<?= $_SESSION['time'] ?>")
		}
	</script>
	<script src="../js/jquery-3.3.1.min.js"></script>
	<script src="../js/popper.min.js"></script>
	<script src="../js/bootstrap.min.js"></script>
	<script src="../js/jquery.validate.min.js"></script>
	<script src="main.js"></script>
</body>

</html>
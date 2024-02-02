<?php
	session_start(); 

	if (! isset( $_SESSION['api_token'] ) || empty( $_SESSION['api_token'] )) {
        header('Location:../index.php');
        exit();
    }

	$token   = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJkYXRhIjp7InVzZXJfaWQiOjM0MDM2MjIsInJvbGUiOiJiZW5lZmljaWFyeSIsIm5hdGlvbmFsX2lkX251bWJlciI6IjIzRjk3NjUxQjFCMjAzQ0UwRTg3NTI2RkI4QTEyMUZCIn0sImV4cCI6MTcwNjgyMjkwNywibG9naW5faGFzaCI6ImZiZjc5MGE0NzJhNWFjZWRlOWIwYjYyZTQ4MmJkNWQ4In0.XWz4qsCMvJHBX8otV42EaB91t685QButk5jBwBuFMZPhKuWirhaaVr0LX4AxbZ7kydWK56I-2psnTaqnDEtrfu8O2gZMYyqMJeEpHu8JJzXVhg-lxeoSt_VwKMRguT8iUJB809gkH7tgoZkUNhXwQ3u6R0itpCT7YhFWPnzIpUXXjbTlG8xzhRN9yjJTkslyTpdzJUBFEGZ2lkaElq0lZWg2QXIGq_EhVi5jq96I6VxqrEFJr-XtX9rDHlQvRQyp0Megoxo26nkh_xrgsRIf-pVcKHxGljSWM-v40cyZYj0V_5254UOql6Wk_w6e7oswGCEf8CV5lZwDqrySj9cZww";
	$project = "619";
	$unit    = "807079";
	// $unit    = $token = $project = "";
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
		<div class="container-fluid">
			<div class="row align-items-stretch justify-content-center">
				<div class="col-lg-6 mb-lg-0 mb-5">
					<div class="form h-100 contact-wrap py-5 px-2">
						<h3 class="text-center py-1 bg-info text-white">نظام البيشي</h3>
						<form class="mb-5 px-5 text-right" dir="rtl" method="post" id="contactForm" name="contactForm">
							<div class="row">
								<div class="col-md-12 form-group mb-3">
									<label for="unit_code" class="col-form-label">رقم المشروع</label>
									<input type="text" class="form-control" name="project_number" value="<?= $project; ?>" id="project_number" placeholder="رقم المشروع">
								</div>
							</div>

							<div class="row">
								<div class="col-md-12 form-group mb-3">
									<label for="unit_code" class="col-form-label">رقم الوحدة *</label>
									<input type="text" class="form-control" name="unit_code" id="unit_code" value="<?= $unit; ?>" autofocus placeholder="رقم الوحدة">
								</div>
							</div>

							<div class="row mb-5">
								<div class="col-md-12 form-group mb-3">
									<label for="authentication_code" class="col-form-label">
										رمز الدخول *
									</label>
									<button type="button" class="btn-sm btn-info" id="add-new-key" style="float: left; padding: 0px 20px; font-weight: bold; font-size: 15px;">+</button>
									<div class="clone-row">
										<textarea class="form-control" name="authentication_code[]" id="authentication_code" cols="30" rows="4" placeholder="رمز الدخول"><?= $token; ?></textarea>
										<button type="button" class="btn-sm btn-danger my-2 remove-clone" style="float: left; padding: 0px 20px; font-weight: bold; font-size: 15px;">-</button>
									</div>
								</div>
							</div>
							<div class="row justify-content-center">
								<div class="col-md-6 form-group text-center">
									<input type="submit" value="Send Message"
										class="btn btn-block btn-primary rounded-0 py-2 px-4">
									<span class="submitting">Wait...</span>
								</div>
							</div>
						</form>

						<div id="form-message-warning mt-4"></div>
						<div id="form-message-success" style="text-align: center;"></div>

					</div>
				</div>

				<div class="col-lg-3 mb-5">
					<div class="form h-100 contact-wrap pt-5 pb-2 px-2">
						<h3 class="text-center py-1 bg-warning text-white">الرسائل</h3>
						<ul id="load-messages" dir="rtl" class="text-right" style="overflow-y: scroll; max-height: 440px;"></ul>
					</div>
				</div>

				<div class="col-lg-3">
					<div class="form h-100 contact-wrap pt-5 pb-2 px-2">
						<h3 class="text-center py-1 bg-success text-white">الحجوزات</h3>
						<ul id="load-success" dir="rtl" class="text-right" style="overflow-y: scroll; max-height: 440px;"></ul>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		login_time = "<?= $_SESSION['time'] ?>";

		if (localStorage.getItem('login-time') && "<?= $token == ''; ?>") {
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
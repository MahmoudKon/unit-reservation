<?php
	session_start(); 

	if (! isset( $_SESSION['api_token'] ) || empty( $_SESSION['api_token'] )) {
        header('Location:../index.php');
        exit();
    }

	$token   = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJkYXRhIjp7InVzZXJfaWQiOjM5OTIwOSwicm9sZSI6ImJlbmVmaWNpYXJ5IiwibmF0aW9uYWxfaWRfbnVtYmVyIjoiMDlGNjYyRDM5RDdBQUY0NkJDM0YxQzkwMDI5OUFBREMifSwiZXhwIjoxNzA2ODk5MjAwLCJsb2dpbl9oYXNoIjoiOWIwYjZiZTM4N2UwNGEwNjI3OTJjYmNhNWVlZDcwNzEifQ.ZLWK1H2QwXXhTUY8WG5_t0Z1FLNGw4jvs6a-gWql5Nsx3nF5TnXgAE2fG7fHIKIzm6ev40nih3m3k-3Dr_FJPCF6GTsaNwbjO5TN9_C7zXA16FCPFQ6-NA5onimksCiEKFxei6zREXcLYCro3V8on1iEm-JcYxUXw-eCtMKu3s6edU43-vp9W7vPVEhTcjZAWsmuD4CsFDsrVgzNuqBeFBQk_gxKG38uRWTQ_ePU7DoL8kkTq8t_EjYz-fTgTSziCp4rZeN0xVJHCcz8VKFYzx3tXIgUMFVtxfWye91U86QQMOIVd-zUqClqBtNHcqEHqhwkAdOvcLRMwQHw6sKl8g";
	$project = "619";
	// $token   = $project = "";
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
						<h3 class="text-center py-1 bg-info text-white">التكبيس التلقائى</h3>
						<form class="mb-5 px-5 text-right" dir="rtl" method="post" id="contactForm" name="contactForm">
							<div class="row mb-3">
								<div class="col-md-12 form-group mb-3">
									<label for="unit_code" class="col-form-label">رقم المشروع *</label>
									<button type="button" class="btn-sm btn-info add-new-key" style="float: left; padding: 0px 20px; font-weight: bold; font-size: 15px;">+</button>

									<div class="row clone-body">
										<div class="clone-row col-md-3">
											<input type="text" class="form-control clone-input project_number" name="project_number[]" autofocus value="<?= $project; ?>" placeholder="رقم المشروع">
											<button type="button" class="btn-sm btn-danger my-2 remove-clone" style="float: left; padding: 0px 20px; font-weight: bold; font-size: 15px;">-</button>
										</div>
									</div>
								</div>
							</div>

							<hr class="mb-3">

							<div class="row mb-5">
								<div class="col-md-12 form-group mb-3">
									<label for="authentication_code" class="col-form-label">
										رمز الدخول *
									</label>
									<button type="button" class="btn-sm btn-info add-new-key" style="float: left; padding: 0px 20px; font-weight: bold; font-size: 15px;">+</button>
									
									<div class="clone-body">
										<div class="clone-row">
											<textarea class="form-control clone-input" name="authentication_code[]" cols="30" rows="4" placeholder="رمز الدخول"><?= $token; ?></textarea>
											<button type="button" class="btn-sm btn-danger my-2 remove-clone" style="float: left; padding: 0px 20px; font-weight: bold; font-size: 15px; display: none">-</button>
										</div>
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
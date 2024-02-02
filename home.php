<?php
	session_start(); 

	if (! isset( $_SESSION['api_token'] ) || empty( $_SESSION['api_token'] )) {
        header('Location:index.php');
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

	<link rel="stylesheet" href="fonts/icomoon/style.css">


	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="css/bootstrap.min.css">

	<!-- Style -->
	<link rel="stylesheet" href="css/style.css">

	<title>Form</title>
</head>

<body>
	<div class="content pb-5">
		<div class="container">
			<div class="row align-items-stretch justify-content-center">
				<div class="col-lg-6 mb-lg-0 mb-5">
					<div class="form h-100 contact-wrap py-5 px-2">
						<h3 class="text-center py-1 bg-info text-white">اختر الطريقة</h3>
						<div class="text-center">
							<a href="v1" class="redirect btn btn-success btn-lg d-block w-50 mx-auto mt-3 mb-3" style="font-size: 18px;"> نقل سريع </a>
							<a href="v2" class="redirect btn btn-success btn-lg d-block w-50 mx-auto mb-3" style="font-size: 18px;"> الدقاق </a>
							<a href="v3" class="redirect btn btn-success btn-lg d-block w-50 mx-auto mb-3" style="font-size: 18px;"> القشاش علي مخطط </a>
							<a href="v4" class="redirect btn btn-success btn-lg d-block w-50 mx-auto mb-3" style="font-size: 18px;"> القشاش علي مخططات </a>
							<a href="v5" class="redirect btn btn-success btn-lg d-block w-50 mx-auto mb-3" style="font-size: 18px;"> نظام البيشي </a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		login_time = "<?= $_SESSION['time'] ?>";

		if (localStorage.getItem('login-time') && false) {
			window.location.href = 'index.php';
		} else {
			localStorage.setItem('login-time', "<?= $_SESSION['time'] ?>")
		}
	</script>

	<script src="js/jquery-3.3.1.min.js"></script>
	<script src="js/popper.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/jquery.validate.min.js"></script>
	<script>
		$(function() {
			$('.redirect').on('click', function(e) {
				e.preventDefault();
				localStorage.removeItem('login-time');
				window.location.href = $(this).attr('href');
			});
		});
	</script>
</body>

</html>
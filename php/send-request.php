<?php

session_start();

$not_avilable = [];

if (! isset( $_SESSION['tokens_in_waiting'] ) ) {
   $_SESSION['tokens_in_waiting'] = [];
}

function dd(...$data)
{
   foreach ($data as $value) {
      echo "<pre>";
      var_dump($value);
      echo "</pre>";
   }
   die;
}

function url()
{
   return sprintf(
      "%s://%s",
      isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
      $_SERVER['SERVER_NAME']
   );
}

if ($_POST) {
   // $unit_code = trim(stripslashes($_POST['unit_code']));
   // $authentication_code = trim(stripslashes($_POST['authentication_code']));

   if ( ! checkLogin() ) {
      echo "goto login";
      exit();
   }

   echo checkUnit();

   // if ( ! checkUnit() ) {
   //    echo 'هذه الوحدة غير موجودة في هذا المشروع';
   //    exit();
   // }

   // echo unitsRserve($unit_code, $authentication_code);
}


function checkLogin()
{
   return true;
   if (! isset( $_SESSION['name'] ) || empty( $_SESSION['name'] )) return false;

   $data = ["name" => $_SESSION['name'] ?? '', "password" => $_SESSION['password'] ?? '', 'api_token' => $_SESSION['api_token']];
   
   $curl = curl_init("https://sakani.asia/login/php/api.php");
   curl_setopt($curl, CURLOPT_POST, 1);
   curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   $response = curl_exec($curl);
   curl_close($curl);
   
   $response = json_decode($response);

   return $response->status == 200;
}

function checkUnit()
{
   if (! isset( $_POST['project_number'] ) || empty( $_POST['project_number'] )) return false;

   $curl = curl_init("https://sakani.sa/sakani-queries-service/search/v1/projects/{$_POST['project_number']}/available-units");
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   $response = curl_exec($curl);
   curl_close($curl);
   $response = json_decode($response);
   $units    = isset($response->data) ? $response->data : [];

   $tokens = array_diff($_POST['authentication_code'], $_SESSION['tokens_in_waiting']);

   if ( $units && isset($_POST['authentication_code']) && $tokens ) {
      foreach ($tokens as $index => $token) {
         if (isset( $units[$index]->attributes->unit_code )) {
            return json_encode([
                     'token'     => $token,
                     'unit_code' => $units[$index]->attributes->unit_code,
                     'message'   => unitsRserve($units[$index]->attributes->unit_code, $token)
                  ], true);
         }
      }
      // foreach ($response->data as $key => $row) {
      //    if ( $row->attributes->unit_code == $_POST['unit_code'] ) {
      //       return true;
      //    }
      // }
   }

   return json_encode([
            'token'     => '',
            'message'   => "لا يوجد وحدات متاحة في هذا المشروع"
         ], true);
}


function unitsRserve($unit_code, $authentication_code)
{
   $data = ["data" => ["id" => "", "type" => "units", "attributes" => ["unit_code" => $unit_code]]];

   $headers = array(
      'Content-Type: application/json; charset=utf-8',
      'authentication: ' . $authentication_code,
   );

   $curl = curl_init("https://sakani.sa/mainIntermediaryApi/v3/units/reserve");
   curl_setopt($curl, CURLOPT_POST, 1);
   curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
   curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36");
   $response = curl_exec($curl);
   curl_close($curl);

   if (strpos($response, '"status":401')) {
      return 'كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)';
   } elseif (strpos($response, '"status":403')) {
      return 'الوحدة المحددة غير متاحة للحجز.';
   } elseif (strpos($response, '"status":400')) {
      return 'عذرا! يوجد لديك حجز مسبقا';
   } elseif (strpos($response, '<!DOCTYPE html>')) {
      return '(كود العميل غير صحيح (برجاء ادخال كود صحيح';
   }

   return checkEligibilityForLandBooking($authentication_code);
}

function checkEligibilityForLandBooking($authentication_code)
{
   $headers = array(
      'Content-Type: application/json; charset=utf-8',
      'authentication: ' . $authentication_code,
   );

   $curl = curl_init("https://sakani.sa/eligibilityEngineServiceApi/v3/beneficiary_applications/check_eligibility_for_land_booking");
   curl_setopt($curl, CURLOPT_POST, 1);
   curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36");
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   $response = curl_exec($curl);
   curl_close($curl);

   if (strpos($response, '"code": "existing_active_booking"')) {
      if (! in_array($authentication_code, $_SESSION['tokens_in_waiting'])) $_SESSION['tokens_in_waiting'] = $authentication_code;
      return 'تم حجز الوحدة من قبل';
   } elseif (strpos($response, '"request_id":')) {
      if (! in_array($authentication_code, $_SESSION['tokens_in_waiting'])) $_SESSION['tokens_in_waiting'] = $authentication_code;
      return 'تم حجز الوحدة بنجاح.';
   } else {
      return 'حدث خطأ أثناء حجز الوحدة.';
   }
}

function checkEligibilityForLandBookingRequest($authentication_code, $request_id)
{
   $headers = array(
      'Content-Type: application/json; charset=utf-8',
      'authentication: ' . $authentication_code,
   );

   $curl = curl_init("https://sakani.sa/sakani-queries-service/cqrs-res?topic=check_eligibility_for_land_booking&request_id=$request_id");
   curl_setopt($curl, CURLOPT_POST, 0);
   curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36");
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   $response = curl_exec($curl);
   curl_close($curl);

   return $response;
}

function beneficiaryBookings($authentication_code, $code)
{
   $headers = array(
      'Content-Type: application/json; charset=utf-8',
      'authentication: ' . $authentication_code,
   );

   $curl = curl_init("https://sakani.sa/mainIntermediaryApi/v4/beneficiary/bookings/$code?include=project");
   curl_setopt($curl, CURLOPT_POST, 1);
   curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36");
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   $response = curl_exec($curl);
   curl_close($curl);

   return $response;
}

function bookingsLands($authentication_code, $code)
{
   $headers = array(
      'Content-Type: application/json; charset=utf-8',
      'authentication: ' . $authentication_code,
   );

   $curl = curl_init("https://sakani.sa/mainIntermediaryApi/v4/bookings/lands/$code/sign_contract_later");
   curl_setopt($curl, CURLOPT_POST, 1);
   curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36");
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   $response = curl_exec($curl);
   curl_close($curl);

   return $response;
}

?>

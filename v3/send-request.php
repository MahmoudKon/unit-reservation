<?php

session_start();

include_once "../php/UnitRserve.php";

if (!isset($_SESSION['tokens_in_waiting_time'])) {
    $_SESSION['tokens_in_waiting_time'] = [];
}

if (!isset($_SESSION['un_avilable_units'])) {
    $_SESSION['un_avilable_units'] = [];
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
    if (!checkLogin()) {
        echo "goto login";
        exit();
    }

    logger("\n\n_______________________________ START __________________________");
    echo checkUnit();
}

function checkLogin()
{
    return true;
    if (!isset($_SESSION['name']) || empty($_SESSION['name'])) return false;

    $data = ["name" => $_SESSION['name'] ?? '', "password" => $_SESSION['password'] ?? '', 'api_token' => $_SESSION['api_token']];

    $curl = curl_init("http://localhost/login/php/api.php");
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($response);

    return $response->status == 200;
}

function getUnits($project)
{
    $curl = curl_init("https://sakani.sa/sakani-queries-service/search/v1/projects/{$project}/available-units");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response);
    return isset($response->data) ? $response->data : [];
}

function checkUnit()
{
    try {
        $project = $_POST['project_number'] ?? 0;

        if (!$project) {
            throw new Exception("ادخل رقم مشروع صحيح", 404);
        }

        if (!isset($_POST['authentication_code'])) {
            throw new Exception("لا يوجد تكونات", 404);
        }

        // للتحقق من ان اذا كان هناك اي توكن قد مر عليه اكثر من نصف ساعه لحذفه من السيشن
        unsetTokensFromSession();

        $waiting_token = array_values($_SESSION['tokens_in_waiting_time']);
        $tokens = $response = [];

        foreach ($_POST['authentication_code'] as $index => $token) {
            if (! in_array($token, $waiting_token)) {
                $tokens[] = $token;
            }
        }

        logger( "All Tokens Count : " . count( $_POST['authentication_code'] ) );
        logger( "Waiting Tokens Count : " . count( $waiting_token ) );
        logger( "Send Tokens Count : " . count( $tokens ) );
        
        if (count($tokens) == 0) {
            throw new Exception("كل التوكانات تم الحجز لها", 404);
        }

        $units = getUnits($project);
        logger( "Units Count : " . count( $units ) );
        if (count($units) == 0) {
            throw new Exception("لا يوجد وحدات متاحة في المشروع $project", 404);
        }

        foreach ($tokens as $index => $token) {
            foreach ($units as $unit) {
                $unit_code = $unit->attributes->unit_code;

                if ( isset($_SESSION['un_avilable_units'][$unit_code]) ) {
                    if ((time() - $_SESSION['un_avilable_units'][$unit_code]) > (90 * 60)) {
                        continue;
                    }
                    unset($_SESSION['un_avilable_units'][$unit_code]);
                }

                $unitRserve = new UnitRserve();
                $result = $unitRserve->setToken($token)
                                        ->setProject($project)
                                        ->setUnit($unit_code)
                                        ->handle($unit->id, false)
                                        ->getMessage();

                $row = [
                    'token'     => $token,
                    'unit_code' => $unit_code,
                    'details'   => $result
                ];

                logger("RESULT => " . json_encode($row));
                $response[] = $row;
                $un_avilable_units[] = $unit_code;

                if (in_array($result['status'], [200, 201, 202, 401])) {
                    $_SESSION['un_avilable_units'][$unit_code] = time();

                    if (stripos($result['message'], 'تم حجز الوحدة') !== false) {
                        setTokensSession($unit_code, $token);
                        continue 2;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $response[] = [
            'token'   => '',
            "details" => [
                'message' => $e->getMessage(),
                'status'  => $e->getCode(),
            ]
        ];
    }

    logger("_______________________________ END __________________________");
    // clearSessions();
    return json_encode($response, true);
}

function logger($log)
{
    file_put_contents('./log.log', "[ ".date('Y-m-d H:i:s A')." ] $log \n", FILE_APPEND);
}

function clearSessions()
{
    foreach ($_SESSION['tokens_in_waiting_time'] as $time => $token) {
        unset ($_SESSION['tokens_in_waiting_time'][$time]);
    }
}

function unsetTokensFromSession()
{
    foreach ($_SESSION['tokens_in_waiting_time'] as $time => $token) {
        $end = time() - $time;
        if ( $end >= ((60 * 30)+5)) {
            unset(  $_SESSION['tokens_in_waiting_time'][$time] );
        }
    }
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
    
    $response = json_decode($response);
    $status   = 200;
    $message  = null;

    if (isset($response->errors) && isset($response->errors[0]))
        $status   = $response->errors[0]->status;

    if ($status == 401) {
        $message =  $response->errors[0]->detail == 'Token is expired'
            ? 'كود العميل غير صالح (تم انتهاء صلاحية الكود برجاء ادخال كود أخر)'
            : '(كود العميل غير صحيح (برجاء ادخال كود صحيح';
    } elseif ($status == 403) {
        $message = 'الوحدة المحددة غير متاحة للحجز.';
    } elseif ($status == 400) {
        setTokensSession($unit_code, $authentication_code);
        $message = 'عذرا! يوجد لديك حجز مسبقا';
    }

    logger( "Method  :  unitsRserve    |   message : $message");
    logger( "Response : " . json_encode($response));

    if ($message) {
        return ['message' => $message, 'status' => $status];
    }

    return checkEligibilityForLandBooking($authentication_code, $response);
}

function checkEligibilityForLandBooking($authentication_code, $prev_response)
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
        $message = 'تم حجز الوحدة من قبل';
    } elseif (strpos($response, '"request_id":')) {
        setTokensSession($prev_response->data->attributes->unit_code, $authentication_code);
        $message = "تم حجز الوحدة {$prev_response->data->attributes->unit_code} بنجاح. عن طريق المستخدم {$prev_response->included[0]->attributes->name} في الوقت " . date('Y-m-d H:i:s');
    } else {
        $message = 'حدث خطأ أثناء حجز الوحدة.';
    }

    logger( "Method  :  checkEligibilityForLandBooking    |   message : $message");
    logger( "Response : " . json_encode($response));

    return ['message' => $message, 'status' => 200];
}

function setTokensSession($unit_code, $token)
{
    logger( "unit code  '". $unit_code ."'  | Token : $token ");
    if (!in_array($token, $_SESSION['tokens_in_waiting_time']) || true) {
        $_SESSION['tokens_in_waiting_time'][ time() ] = $token;
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

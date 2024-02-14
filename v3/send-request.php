<?php

session_start();

include_once "../php/UnitRserve.php";

if (!isset($_SESSION['tokens_in_waiting_time'])) {
    $_SESSION['tokens_in_waiting_time'] = [];
}

if (!isset($_SESSION['un_avilable_units'])) {
    $_SESSION['un_avilable_units'] = [];
}

if (!isset($_SESSION['national_id_number'])) {
    $_SESSION['national_id_number'] = [];
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

        foreach ($tokens as $token) {
            if (! isset($_SESSION['national_id_number'][$token]))
                (new UnitRserve())->setToken($token)->beneficiary_application(true);
        }

        $units = getUnits($project);
        logger( "Units Count : " . count( $units ) );
        logger( "Units : " . json_encode( $units ) );
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

                if ($message = (new UnitRserve())->checkUnitIsAvilable($unit->id)) {
                    logger("RESULT checkUnitIsAvilable => $message");
                    $response[] = [
                        'token'     => $token,
                        'unit_code' => $unit_code,
                        'details'   => [
                            'message' => $message,
                            'status'  => 404,
                        ]
                    ];

                    continue;
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

                if ($result['message'] == "كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)") {
                    $_SESSION['tokens_in_waiting_time'][time()] = $token;
                    continue 2;
                }

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

function setTokensSession($unit_code, $token)
{
    logger( "unit code  '". $unit_code ."'  | Token : $token ");
    if (!in_array($token, $_SESSION['tokens_in_waiting_time']) || true) {
        $_SESSION['tokens_in_waiting_time'][ time() ] = $token;
    }
}

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

function checkUnit()
{
    if (!isset($_POST['project']) || empty($_POST['project'])) return false;

    // للتحقق من ان اذا كان هناك اي توكن قد مر عليه اكثر من نصف ساعه لحذفه من السيشن
    unsetTokensFromSession();

    $waiting_token = array_values($_SESSION['tokens_in_waiting_time']);
    $tokens = [];

    foreach ($_POST['authentication_code'] as $index => $token) {
        if (! in_array($token, $waiting_token)) {
            $tokens[] = $token;
        }
    }

    logger( "All Tokens Count : " . count( $_POST['authentication_code'] ) );
    logger( "Waiting Tokens Count : " . count( $waiting_token ) );
    logger( "Send Tokens Count : " . count( $tokens ) );

    $response = [];

    try {
        if (!isset($_POST['authentication_code']) && count($tokens) == 0) {
            throw new Exception("كل التوكانات تم الحجز لها", 404);
        }

        if (count($tokens) == 0) {
            throw new Exception("لا يوجد تكونات متاحة", 404);
        }

        foreach ($tokens as $token) {
            if (! isset($_SESSION['national_id_number'][$token]))
                (new UnitRserve())->setToken($token)->beneficiary_application(true);
        }

        $projects = [$_POST['project']];
        $projects = $_POST['project_number'];

        foreach ($projects as $project) {
            $units   = getUnits($project);
            logger( "Send Units Count  For Project {$project}: " . count( $units ) );
            logger( "Units : " . json_encode( $units ) );

            if (count($units) == 0) {
                $response[] = [
                    'token'     => $token,
                    'unit_code' => '',
                    'details'   => [
                        'message' => "لا يوجد وحدات متاحة في المشروع {$project}",
                        'status'  => 404,
                    ]
                ];
                continue;
            }

            
            foreach ($tokens as $token) {
                $max_units_count = count($units) > 10 ? 10 : count($units);
                for ($i=0; $i < $max_units_count; $i++) {
                    $unit = $units[$i];
                    $unit_code = $unit->attributes->unit_code;
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

                    if ( isset($_SESSION['un_avilable_units'][$unit_code]) ) {
                        logger( "This Unit $unit_code In Ignore List" );
                        if ((time() - $_SESSION['un_avilable_units'][$unit_code]) > (90 * 60)) {
                            logger( "This Unit $unit_code Still In Ignore List" );
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
    
                    if ($result['message'] == "كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)") {
                        continue 2;
                    }

                    if (in_array($result['status'], [200, 401])) {
                        $_SESSION['un_avilable_units'][$unit_code] = time();
                        
                        if (stripos($result['message'], 'تم حجز الوحدة') !== false) {
                            setTokensSession($unit_code, $token);
                            continue 2;
                        }
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
    return json_encode($response, true);
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

function logger($log)
{
    file_put_contents('./log.log', "[ ".date('Y-m-d H:i:s A')." ] $log \n", FILE_APPEND);
}

function unsetTokensFromSession()
{
    foreach ($_SESSION['tokens_in_waiting_time'] as $time => $token) {
        $end = time() - $time;
        if ( $end >= (60 * 30)) {
            unset(  $_SESSION['tokens_in_waiting_time'][$time] );
        }
    }
}

function setTokensSession($unit_code, $token)
{
    if (!in_array($token, $_SESSION['tokens_in_waiting_time']) || true) {
        logger( "unit code  '". $unit_code ."'  | Token : $token ");
        $_SESSION['tokens_in_waiting_time'][ time() ] = $token;
    }
}

<?php

session_start(); 

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    return '';
    exit();
}

$curl = curl_init("https://sakani.asia/login/php/api.php");
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $_POST);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($curl);
curl_close($curl);

$response = json_decode($response);

if ($response->status == 200) {
    $user = json_decode($response->message);
    $_SESSION['id'] = $user->id;
    $_SESSION['name'] = $user->name;
    $_SESSION['password'] = $_POST['password'];
    $_SESSION['api_token'] = $user->api_token;
    $_SESSION['time'] = time();
}

echo json_encode(['status' => $response->status, 'message' => $response->message]);

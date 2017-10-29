<?php

include_once('lib/SafeMySQL.php');
include_once('lib/Reviews.php');

//$db = SafeMySQL::getInstance();

$method = (isset($_GET['method']) && $_GET['method']) ? $_GET['method'] : null;
$applicationId = (isset($_GET['application_id']) && (int) $_GET['application_id']) ? (int) $_GET['application_id'] : null;
$countryCode = (isset($_GET['country_code']) && $_GET['country_code']) ? $_GET['country_code'] : null;

$countryCodes = [
    'us',
    'ru',
];

if (!$method || !function_exists($method) || !in_array($countryCode, $countryCodes))
    die(json_encode([]));

$result = $method($applicationId, $countryCode);
echo json_encode($result);


function application_info($applicationId, $countryCode)
{
    return [
        'status' => 'success',
        'data'   => Reviews::getApplicationCountryInfo($applicationId, $countryCode),
    ];
}

function get_review_info($applicationId, $countryCode)
{
    $application = Reviews::getApplicationInfoById($applicationId, $countryCode);

    return [
        'status' => 'success',
        'data'   => $application,
    ];
}

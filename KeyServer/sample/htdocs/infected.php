<?php
require_once('../vendor/autoload.php');
require_once('shared.php');

if (!isValidBearer($db)) {
    sendJsonErrorResponse(401, 'Invalid bearer token');
    exit;
}

$useBinary = false;

if (array_key_exists('HTTP_ACCEPT', $_SERVER)) {
    $accept = $_SERVER['HTTP_ACCEPT'];
    $useBinary = strpos($accept, 'msgpack') !== false;
}

$offset = 0;
$limit  = 0;

if (array_key_exists('offset', $_GET)) {
    $offset = max(0, (int) $_GET['offset']);
}

if (array_key_exists('limit', $_GET)) {
    $limit = max(0, (int) $_GET['limit']);
}

// https://github.com/rybakit/msgpack.php
use MessagePack\Packer;

$minTime = time() - (86400 * 14);
$time = $minTime;

if (array_key_exists('since', $_GET)) {
    try {
        $dateTime = new DateTime($_GET['since']);
        $time = max($minTime, $dateTime->getTimestamp());
    }
    catch (Exception $e) {
	
    }
}

$query = 'SELECT infected_key, rolling_start_number, risk_level FROM infected_keys WHERE status = :s AND status_updated >= :t ORDER BY status_updated';

if ($limit > 0) {
    $query .= sprintf(' LIMIT %d, %d', $offset, $limit);
}

$stmt = $db->prepare($query);
$stmt->bindValue(':t', $time, SQLITE3_INTEGER);
$stmt->bindValue(':s', 'A', SQLITE3_TEXT);

$result = $stmt->execute();

$keys = array();

$packer = new Packer();

$config = array(
    'minimum_risk_score' => 6,
    'attenuation' => array(
	'weight' => 50,
	'scores' => [ 1, 2, 3, 4, 5, 6, 7, 8 ]
    ),
    'days_since_last_exposure' => array(
        'weight' => 50,
        'scores' => [ 1, 2, 3, 4, 5, 6, 7, 8 ]
    ),
    'duration' => array(
        'weight' => 50,
        'scores' => [ 1, 2, 3, 4, 5, 6, 7, 8 ]
    ),
    'transmission_risk' => array(
        'weight' => 50,
        'scores' => [ 1, 2, 3, 4, 5, 6, 7, 8 ]
    )
);


if ($useBinary) {
    while (($row = $result->fetchArray(SQLITE3_NUM))) {
        $keys[] = array(
            'd' => base64_decode($row[0]),
	    'r' => (int) $row[1],
	    'l' => (int) $row[2]
        );
    }
}
else {
    while (($row = $result->fetchArray(SQLITE3_NUM))) {
        $keys[] = array(
	    'd' => $row[0],
	    'r' => (int) $row[1],
	    'l' => (int) $row[2]
        );
    }
}

$date = new DateTime();

$retryDate = new DateTime();
$retryDate->add(new DateInterval('PT1H'));

$json = array(
    'status' => 'OK',
    'date' => $date->format(DateTimeInterface::ISO8601),
    'keys' => $keys,
    'deleted_keys' => array(),
    'min_retry_date' => $retryDate->format(DateTimeInterface::ISO8601),
    'config' => $config
);

if ($time > $minTime) {
}
else {
    $json['list_type'] = 'FULL';
}

if ($useBinary) {
    $packed = $packer->pack($json);

    header('Content-type: application/x-msgpack');
    header(sprintf('Content-length: %d', strlen($packed)));

    echo $packed;
}
else {
    sendJsonResponse(200, $json);
}


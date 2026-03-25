<?php
/**
 * APICK API 프록시 (cafe24 서버용)
 * kegos70.mycafe24.com/apick_proxy.php
 *
 * 사용법:
 *   POST apick_proxy.php?action=view    { pin: "고유번호" }  → { ic_id: 123 }
 *   POST apick_proxy.php?action=download { ic_id: 123 }      → PDF binary
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

define('APICK_KEY', '644e422dc7602340523efc59c70f53e4');
$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = [];

// ── 열람 요청
if ($action === 'view') {
    $pin = isset($input['pin']) ? $input['pin'] : '';
    if (!$pin) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'pin required']);
        exit;
    }

    // 고유번호 하이픈 포맷: 14자리 숫자 → XXXX-XXXX-XXXXXX
    $rawPin = preg_replace('/[^0-9]/', '', $pin);
    if (strlen($rawPin) === 14 && strpos($pin, '-') === false) {
        $pin = substr($rawPin, 0, 4) . '-' . substr($rawPin, 4, 4) . '-' . substr($rawPin, 8);
    }

    $ch = curl_init('https://apick.app/rest/iros/1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['unique_num' => $pin, 'option' => '현행'],
        CURLOPT_HTTPHEADER => ['CL_AUTH_KEY: ' . APICK_KEY],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    header('Content-Type: application/json; charset=utf-8');

    if ($curlErr) {
        http_response_code(500);
        echo json_encode(['error' => 'APICK connection failed: ' . $curlErr]);
        exit;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['data']['ic_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'view request failed', 'detail' => $data]);
        exit;
    }

    $success = isset($data['data']['success']) ? $data['data']['success'] : 0;
    if ($success === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'view failed - check pin number']);
        exit;
    }
    if ($success === 3) {
        http_response_code(504);
        echo json_encode(['error' => 'registry timeout']);
        exit;
    }

    echo json_encode([
        'ic_id' => $data['data']['ic_id'],
        'cost'  => isset($data['api']['cost']) ? $data['api']['cost'] : 0,
    ]);
    exit;
}

// ── PDF 다운로드
if ($action === 'download') {
    $icId = isset($input['ic_id']) ? $input['ic_id'] : (isset($_GET['ic_id']) ? $_GET['ic_id'] : '');
    if (!$icId) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'ic_id required']);
        exit;
    }

    $ch = curl_init('https://apick.app/rest/iros_download/1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['ic_id' => $icId, 'format' => 'pdf'],
        CURLOPT_HTTPHEADER => ['CL_AUTH_KEY: ' . APICK_KEY],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HEADER => true,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'APICK connection failed: ' . $curlErr]);
        exit;
    }

    $body = substr($response, $headerSize);
    if (!$contentType) $contentType = '';

    // JSON response = still processing or error
    if (strpos($contentType, 'json') !== false || strpos($contentType, 'text/') !== false) {
        $data = json_decode($body, true);
        header('Content-Type: application/json; charset=utf-8');

        if (isset($data['data']['result']) && $data['data']['result'] == 2) {
            http_response_code(202);
            echo json_encode(['status' => 'processing', 'message' => 'PDF generating...']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'download failed', 'detail' => $data]);
        exit;
    }

    // PDF binary response
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="registry_' . $icId . '.pdf"');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

// ── 건축물대장 목록 조회
if ($action === 'building_list') {
    $address = isset($input['address']) ? $input['address'] : '';
    if (!$address) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'address required']);
        exit;
    }

    $ch = curl_init('https://apick.app/rest/get_building_register_list');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['address' => $address],
        CURLOPT_HTTPHEADER => ['CL_AUTH_KEY: ' . APICK_KEY],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    header('Content-Type: application/json; charset=utf-8');

    if ($curlErr) {
        http_response_code(500);
        echo json_encode(['error' => 'APICK connection failed: ' . $curlErr]);
        exit;
    }

    $data = json_decode($response, true);
    if (!$data) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid response', 'raw' => substr($response, 0, 500)]);
        exit;
    }

    echo json_encode($data);
    exit;
}

// ── 건축물대장 열람 (PDF)
if ($action === 'building_view') {
    $address = isset($input['address']) ? $input['address'] : '';

    if (!$address) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'address required']);
        exit;
    }

    // APICK API에 전달할 필드: address 필수, 나머지는 클라이언트에서 온 값 그대로
    $allowedFields = ['address', 'b_name', 'dong', 'ho', 'b_code', 'idx', 'seq', 'no', 'type', 'id'];
    $postFields = ['address' => $address];
    foreach ($input as $key => $val) {
        if ($key !== 'address' && in_array($key, $allowedFields) && $val !== '') {
            $postFields[$key] = $val;
        }
    }

    $ch = curl_init('https://apick.app/rest/building_register');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => ['CL_AUTH_KEY: ' . APICK_KEY],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HEADER => true,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'APICK connection failed: ' . $curlErr]);
        exit;
    }

    $body = substr($response, $headerSize);
    if (!$contentType) $contentType = '';

    // JSON 응답 = 에러 또는 처리중
    if (strpos($contentType, 'json') !== false || strpos($contentType, 'text/') !== false) {
        $data = json_decode($body, true);
        header('Content-Type: application/json; charset=utf-8');

        if ($data && isset($data['data']['result']) && $data['data']['result'] == 2) {
            http_response_code(202);
            echo json_encode(['status' => 'processing', 'message' => 'Building register generating...']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'building register failed', 'detail' => $data]);
        exit;
    }

    // PDF 바이너리 응답
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="building_register.pdf"');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

// Unknown action
header('Content-Type: application/json; charset=utf-8');
http_response_code(400);
echo json_encode([
    'error' => 'action parameter required',
    'usage' => [
        'POST apick_proxy.php?action=view   body: {"pin":"14digit"}',
        'POST apick_proxy.php?action=download body: {"ic_id":123}',
        'POST apick_proxy.php?action=building_list body: {"address":"도로명주소"}',
        'POST apick_proxy.php?action=building_view body: {"address":"도로명주소","b_name":"건물명","dong":"동","ho":"호"}',
    ],
]);

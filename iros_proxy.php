<?php
/**
 * 인터넷등기소 고유번호 조회 API (PHP)
 * cafe24 서버용: kegos70.mycafe24.com/iros_proxy.php
 *
 * 사용법:
 *   iros_proxy.php?address=부산광역시 부산진구 서전로 9
 *   iros_proxy.php?sido=부산광역시&sigungu=부산진구&road=서전로&no=9
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('BASE_URL', 'https://www.iros.go.kr');
define('USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

// 세션 쿠키 획득
function getSessionCookies() {
    // 1) 메인 페이지 → PM10SESSIONID
    $ch = curl_init(BASE_URL . '/PMainJ.jsp');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_HTTPHEADER => ['User-Agent: ' . USER_AGENT],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = substr($response, 0, $headerSize);
    curl_close($ch);

    $cookies = [];
    preg_match_all('/Set-Cookie:\s*([^;]+)/i', $headerStr, $matches);
    foreach ($matches[1] as $cookie) {
        $cookies[] = $cookie;
    }
    $cookieStr = implode('; ', $cookies);

    // 2) PR20SESSIONID 획득
    $ch = curl_init(BASE_URL . '/biz/Pr20ComBusinessBaseCtrl/retrieveSessionId.do?IS_NMBR_LOGIN__=null');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['websquare_param' => new stdClass()]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=UTF-8',
            'User-Agent: ' . USER_AGENT,
            'Referer: ' . BASE_URL . '/index.jsp',
            'Cookie: ' . $cookieStr,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = substr($response, 0, $headerSize);
    curl_close($ch);

    preg_match_all('/Set-Cookie:\s*([^;]+)/i', $headerStr, $matches);
    foreach ($matches[1] as $cookie) {
        $name = explode('=', $cookie)[0];
        $found = false;
        foreach ($cookies as $i => $existing) {
            if (strpos($existing, $name . '=') === 0) {
                $cookies[$i] = $cookie;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $cookies[] = $cookie;
        }
    }

    return implode('; ', $cookies);
}

// 간편검색 (집합건물 포함 전체 검색)
function searchSimple($cookieStr, $sido, $swrd, $kindCls = 'all', $page = 1) {
    // kind_cls: "all" = 전체, "coll" = 집합건물, "land" = 토지, "buld" = 건물
    $body = [
        'websquare_param' => [
            'conn_menu_cls_cd' => '01',
            'prgs_mode_cls_cd' => '01',
            'inet_srch_cls_cd' => 'PR01',
            'prgs_stg_cd' => '',
            'move_cls' => 'P',
            'swrd' => ' ' . $swrd,
            'addr_cls' => '3',
            'kind_cls' => $kindCls,
            'land_bing_yn' => '',
            'rgs_rec_stat' => '현행',
            'admin_regn1' => $sido,
            'admin_regn2' => '',
            'admin_regn3' => '',
            'lot_no' => '',
            'buld_name' => '',
            'buld_no_buld' => '',
            'buld_no_room' => '',
            'rd_name' => '',
            'rd_buld_no' => '',
            'rd_buld_no2' => '',
            'issue_cls' => '5',
            'pageIndex' => $page > 1 ? (string)$page : '',
            'pageUnit' => 10,
            'cmort_flag' => '',
            'kap_seq_flag' => '',
            'trade_seq_flag' => '',
            'etdoc_sel_yn' => '',
            'show_cls' => '',
            'real_pin_con' => '',
            'svc_cls_con' => '',
            'item_cls_con' => '',
            'judge_enr_cls_con' => '',
            'cmort_cls_con' => '',
            'trade_cls_con' => '',
            'extend_srch' => '',
            'usg_cls_con' => '',
        ],
    ];

    $ch = curl_init(BASE_URL . '/biz/Pr20ViaRlrgSrchCtrl/retrieveSmplSrchList.do?IS_NMBR_LOGIN__=null');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
            'User-Agent: ' . USER_AGENT,
            'Referer: ' . BASE_URL . '/index.jsp',
            'Origin: ' . BASE_URL,
            'Cookie: ' . $cookieStr,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) throw new Exception('curl error: ' . $error);
    if ($httpCode !== 200) throw new Exception('iros search failed: HTTP ' . $httpCode);

    return json_decode($response, true);
}

// 도로명주소 검색 (토지+건물 전용)
function searchByRoadAddress($cookieStr, $sido, $sigungu, $roadName, $buildingNo, $buildingNo2 = '', $kindCls = '4') {
    // kind_cls: "4" = 토지+건물, "1" = 집합건물, "2" = 토지, "3" = 건물
    $body = [
        'websquare_param' => [
            'conn_menu_cls_cd' => '01',
            'prgs_mode_cls_cd' => '01',
            'inet_srch_cls_cd' => 'PR03',
            'prgs_stg_cd' => '',
            'move_cls' => '',
            'swrd' => '',
            'addr_cls' => '2',
            'kind_cls' => $kindCls,
            'land_bing_yn' => '',
            'rgs_rec_stat' => '1',
            'admin_regn1' => $sido,
            'admin_regn2' => $sigungu,
            'admin_regn3' => '',
            'lot_no' => '',
            'buld_name' => '',
            'buld_no_buld' => '',
            'buld_no_room' => '',
            'rd_name' => $roadName,
            'rd_buld_no' => $buildingNo,
            'rd_buld_no2' => $buildingNo2,
            'issue_cls' => '4',
            'pageIndex' => '',
            'pageUnit' => '',
            'cmort_flag' => '',
            'kap_seq_flag' => '',
            'trade_seq_flag' => '',
            'etdoc_sel_yn' => '',
            'show_cls' => '',
            'real_pin_con' => '',
            'svc_cls_con' => '',
            'item_cls_con' => '',
            'judge_enr_cls_con' => '',
            'cmort_cls_con' => '',
            'trade_cls_con' => '',
            'extend_srch' => '',
            'usg_cls_con' => '',
        ],
    ];

    $ch = curl_init(BASE_URL . '/biz/Pr20ViaRlrgSrchCtrl/retrieveRdAddrSrchCont.do?IS_NMBR_LOGIN__=null');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
            'User-Agent: ' . USER_AGENT,
            'Referer: ' . BASE_URL . '/index.jsp',
            'Origin: ' . BASE_URL,
            'Cookie: ' . $cookieStr,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('curl error: ' . $error);
    }
    if ($httpCode !== 200) {
        throw new Exception('iros search failed: HTTP ' . $httpCode);
    }

    return json_decode($response, true);
}

// 주소 파싱
function parseAddressWithSido($address) {
    $sidoList = [
        '서울특별시', '부산광역시', '대구광역시', '인천광역시',
        '광주광역시', '대전광역시', '울산광역시', '세종특별자치시',
        '경기도', '강원특별자치도', '충청북도', '충청남도',
        '전북특별자치도', '전라남도', '경상북도', '경상남도', '제주특별자치도',
    ];

    $sido = '';
    $rest = trim($address);
    foreach ($sidoList as $s) {
        if (mb_strpos($rest, $s) === 0) {
            $sido = $s;
            $rest = trim(mb_substr($rest, mb_strlen($s)));
            break;
        }
    }

    return [$sido, $rest];
}

function formatPin($pin) {
    $raw = preg_replace('/[^0-9]/', '', (string)$pin);
    if (strlen($raw) !== 14) {
        return (string)$pin;
    }
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8);
}

function normalizeSimpleResultItem($item) {
    $pin = $item['pin'] ?? ($item['real_pin'] ?? '');
    $type = trim($item['real_cls_name'] ?? ($item['real_cls_cd'] ?? ''));

    if (!$type) {
        $hasRoom = trim($item['buld_no_room'] ?? '') !== '';
        $hasDong = trim($item['buld_no_buld'] ?? '') !== '';
        if ($hasRoom || $hasDong) {
            $type = '집합건물';
        }
    }

    return [
        'pin' => $pin,
        'pinFormatted' => formatPin($pin),
        'roadAddress' => $item['rd_addr'] ?? '',
        'jibunAddress' => $item['real_indi_cont'] ?? '',
        'detail' => trim($item['add_item'] ?? ''),
        'type' => $type,
        'buildingName' => trim($item['buld_name'] ?? ''),
        'dong' => trim($item['buld_no_buld'] ?? ''),
        'roomNo' => trim($item['buld_no_room'] ?? ''),
        'owner' => $item['nomprs_name'] ?? '',
        'status' => $item['rgsbk_use_cls_name'] ?? ($item['use_cls_cd'] ?? '현행'),
    ];
}

function isComplexItem($item) {
    $type = trim($item['real_cls_name'] ?? ($item['real_cls_cd'] ?? ''));
    if (mb_strpos($type, '집합') !== false) {
        return true;
    }

    $hasRoom = trim($item['buld_no_room'] ?? '') !== '';
    $hasDong = trim($item['buld_no_buld'] ?? '') !== '';
    return $hasRoom || $hasDong;
}

function buildSimpleSearchKeyword($baseAddress, $dong = '', $ho = '') {
    $swrd = trim($baseAddress);
    if ($dong !== '') $swrd .= ' ' . $dong . '동';
    if ($ho !== '') $swrd .= ' ' . $ho . '호';
    return trim($swrd);
}

// === 메인 ===
try {
    $address = $_GET['address'] ?? '';
    $jibun = trim($_GET['jibun'] ?? '');
    $sido = $_GET['sido'] ?? '';
    $sigungu = $_GET['sigungu'] ?? '';
    $road = $_GET['road'] ?? '';
    $no = $_GET['no'] ?? '';
    $no2 = $_GET['no2'] ?? '';
    $type = $_GET['type'] ?? 'all';  // all, complex, land, building
    $dong = trim($_GET['dong'] ?? '');  // 동 (집합건물)
    $ho = trim($_GET['ho'] ?? '');      // 호 (집합건물)

    // type → 간편검색 kind_cls 매핑
    $kindClsMap = [
        'all'      => 'all',
        'complex'  => 'coll',
        'land'     => 'land',
        'building' => 'buld',
    ];
    $kindCls = $kindClsMap[$type] ?? 'all';

    $baseAddress = $jibun;

    // 하위호환: jibun 미지정 시 기존 address를 지번 검색 키워드로 사용
    if ($baseAddress === '' && $address) {
        $baseAddress = trim($address);
    }

    if ($baseAddress === '' && $sido && $sigungu) {
        if ($road && $no) {
            $baseAddress = trim($sigungu . ' ' . $road . ' ' . $no . ($no2 ? '-' . $no2 : ''));
        } else {
            $baseAddress = trim($sigungu . ' ' . ($_GET['dong'] ?? '') . ' ' . ($_GET['beonji'] ?? ''));
        }
    }

    if ($baseAddress !== '' && !$sido) {
        list($parsedSido, $restAddress) = parseAddressWithSido($baseAddress);
        if ($parsedSido) {
            $sido = $parsedSido;
            $baseAddress = $restAddress;
        }
    }

    if (!$sido || !$baseAddress) {
        http_response_code(400);
        echo json_encode([
            'error' => '주소 파라미터 필요',
            'usage' => [
                'iros_proxy.php?jibun=부산광역시 동구 초량동 1200-2',
                'iros_proxy.php?jibun=부산광역시 동구 초량동 1200-2&type=land',
                'iros_proxy.php?jibun=부산광역시 해운대구 우동 1436&type=complex&dong=101&ho=1201',
            ],
            'type_values' => [
                'all'      => '토지+건물 (기본값)',
                'complex'  => '집합건물 (아파트, 오피스텔 등)',
                'land'     => '토지',
                'building' => '건물',
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cookieStr = getSessionCookies();
    $page = max(1, intval($_GET['page'] ?? 1));
    $swrd = buildSimpleSearchKeyword($baseAddress, $dong, $ho);

    $data = searchSimple($cookieStr, $sido, $swrd, $kindCls, $page);
    $dataList = $data['dataList'] ?? [];
    $totalCount = $data['paginationInfo']['totalRecordCount'] ?? count($dataList);

    $results = [];
    foreach ($dataList as $item) {
        if ($type === 'complex' && !isComplexItem($item)) {
            continue;
        }
        $results[] = normalizeSimpleResultItem($item);
    }

    echo json_encode([
        'count' => count($results),
        'totalCount' => $totalCount,
        'page' => $page,
        'searchBase' => $baseAddress,
        'searchKeyword' => $swrd,
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

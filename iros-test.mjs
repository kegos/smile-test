/**
 * 인터넷등기소 고유번호 조회 테스트
 * - 로그인 없이 도로명주소 검색 → 고유번호 리스트 추출
 * - 테스트 주소: 부산광역시 부산진구 서전로 9
 */

const BASE_URL = 'https://www.iros.go.kr';

// 1단계: 세션 쿠키 획득
async function getSessionCookies() {
  const mainRes = await fetch(`${BASE_URL}/PMainJ.jsp`, {
    headers: {
      'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    },
    redirect: 'follow',
  });

  const cookies = [];
  for (const [key, value] of mainRes.headers.entries()) {
    if (key === 'set-cookie') {
      cookies.push(value.split(';')[0]);
    }
  }

  // PR20SESSIONID 획득
  const contentRes = await fetch(`${BASE_URL}/biz/Pr20ComBusinessBaseCtrl/retrieveSessionId.do?IS_NMBR_LOGIN__=null`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json; charset=UTF-8',
      'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
      'Referer': `${BASE_URL}/index.jsp`,
      'Cookie': cookies.join('; '),
    },
    body: JSON.stringify({ websquare_param: {} }),
  });

  for (const [key, value] of contentRes.headers.entries()) {
    if (key === 'set-cookie') {
      const cookiePart = value.split(';')[0];
      if (!cookies.some(c => c.startsWith(cookiePart.split('=')[0]))) {
        cookies.push(cookiePart);
      }
    }
  }

  console.log('[세션] 쿠키 획득:', cookies.length, '개');
  return cookies.join('; ');
}

// 2단계: 도로명주소로 부동산 검색
async function searchByRoadAddress(cookieStr, { sido, sigungu, roadName, buildingNo, buildingNo2 = '' }) {
  // kind_cls: "4" = 토지+건물, "1" = 집합건물, "2" = 토지, "3" = 건물
  const body = {
    websquare_param: {
      conn_menu_cls_cd: '01',
      prgs_mode_cls_cd: '01',
      inet_srch_cls_cd: 'PR03',    // 도로명주소검색
      prgs_stg_cd: '',
      move_cls: '',
      swrd: '',
      addr_cls: '2',               // 도로명주소
      kind_cls: '4',               // 토지+건물
      land_bing_yn: '',
      rgs_rec_stat: '1',           // 현행
      admin_regn1: sido,
      admin_regn2: sigungu,
      admin_regn3: '',
      lot_no: '',
      buld_name: '',
      buld_no_buld: '',
      buld_no_room: '',
      rd_name: roadName,
      rd_buld_no: buildingNo,
      rd_buld_no2: buildingNo2,
      issue_cls: '4',
      pageIndex: '',
      pageUnit: '',
      cmort_flag: '',
      kap_seq_flag: '',
      trade_seq_flag: '',
      etdoc_sel_yn: '',
      show_cls: '',
      real_pin_con: '',
      svc_cls_con: '',
      item_cls_con: '',
      judge_enr_cls_con: '',
      cmort_cls_con: '',
      trade_cls_con: '',
      extend_srch: '',
      usg_cls_con: '',
    },
  };

  const res = await fetch(
    `${BASE_URL}/biz/Pr20ViaRlrgSrchCtrl/retrieveRdAddrSrchCont.do?IS_NMBR_LOGIN__=null`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Accept': 'application/json',
        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        'Referer': `${BASE_URL}/index.jsp`,
        'Origin': BASE_URL,
        'Cookie': cookieStr,
      },
      body: JSON.stringify(body),
    },
  );

  if (!res.ok) {
    throw new Error(`검색 실패: ${res.status} ${res.statusText}`);
  }

  return await res.json();
}

// 3단계: 결과 파싱
function parseResults(json) {
  const { dataList } = json;

  if (!dataList || dataList.length === 0) {
    console.log('[결과] 검색 결과 없음');
    return [];
  }

  console.log(`[결과] 총 ${dataList.length}건`);
  console.log('---');

  return dataList.map((item, i) => ({
    번호: i + 1,
    고유번호: item.real_pin,
    도로명주소: item.rd_addr,
    지번주소: item.real_indi_cont,
    구분: item.real_cls_name,
    건물명: item.buld_name?.trim() || '-',
    상태: item.rgsbk_use_cls_name,
  }));
}

// 도로명주소 파싱 (예: "부산광역시 부산진구 서전로 9")
function parseRoadAddress(address) {
  const sidoList = [
    '서울특별시', '부산광역시', '대구광역시', '인천광역시',
    '광주광역시', '대전광역시', '울산광역시', '세종특별자치시',
    '경기도', '강원특별자치도', '충청북도', '충청남도',
    '전북특별자치도', '전라남도', '경상북도', '경상남도', '제주특별자치도',
  ];

  let sido = '', rest = address;
  for (const s of sidoList) {
    if (address.startsWith(s)) {
      sido = s;
      rest = address.slice(s.length).trim();
      break;
    }
  }

  const parts = rest.split(' ');
  const sigungu = parts[0];                    // 부산진구
  const roadName = parts[1];                   // 서전로
  const buildingNoParts = (parts[2] || '').split('-');
  const buildingNo = buildingNoParts[0];       // 9
  const buildingNo2 = buildingNoParts[1] || ''; // (부번)

  return { sido, sigungu, roadName, buildingNo, buildingNo2 };
}

// 실행
async function main() {
  const address = process.argv[2] || '부산광역시 부산진구 서전로 9';
  console.log(`\n=== 인터넷등기소 고유번호 조회 (도로명주소) ===`);
  console.log(`검색 주소: ${address}\n`);

  try {
    const parsed = parseRoadAddress(address);
    console.log(`[파싱] 시도: ${parsed.sido}, 시군구: ${parsed.sigungu}, 도로명: ${parsed.roadName}, 건물번호: ${parsed.buildingNo}${parsed.buildingNo2 ? '-' + parsed.buildingNo2 : ''}\n`);

    const cookieStr = await getSessionCookies();
    const data = await searchByRoadAddress(cookieStr, parsed);
    const results = parseResults(data);

    if (results.length > 0) {
      console.table(results);

      console.log('\n[고유번호 리스트]');
      results.forEach(r => {
        console.log(`  ${r.고유번호}  ${r.도로명주소} (${r.구분}) ${r.건물명 !== '-' ? r.건물명 : ''}`);
      });
    }
  } catch (err) {
    console.error('오류:', err.message);
  }
}

main();

/**
 * 인터넷등기소 고유번호 조회 API
 *
 * GET /api/iros?address=부산광역시 부산진구 서전로 9
 * GET /api/iros?sido=부산광역시&sigungu=부산진구&road=서전로&no=9
 */

const BASE_URL = 'https://www.iros.go.kr';

const SIDO_LIST = [
  '서울특별시', '부산광역시', '대구광역시', '인천광역시',
  '광주광역시', '대전광역시', '울산광역시', '세종특별자치시',
  '경기도', '강원특별자치도', '충청북도', '충청남도',
  '전북특별자치도', '전라남도', '경상북도', '경상남도', '제주특별자치도',
];

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

  return cookies.join('; ');
}

function parseRoadAddress(address) {
  let sido = '', rest = address;
  for (const s of SIDO_LIST) {
    if (address.startsWith(s)) {
      sido = s;
      rest = address.slice(s.length).trim();
      break;
    }
  }

  const parts = rest.split(' ');
  const sigungu = parts[0] || '';
  const roadName = parts[1] || '';
  const buildingNoParts = (parts[2] || '').split('-');
  const buildingNo = buildingNoParts[0] || '';
  const buildingNo2 = buildingNoParts[1] || '';

  return { sido, sigungu, roadName, buildingNo, buildingNo2 };
}

async function searchByRoadAddress(cookieStr, { sido, sigungu, roadName, buildingNo, buildingNo2 = '' }) {
  const body = {
    websquare_param: {
      conn_menu_cls_cd: '01',
      prgs_mode_cls_cd: '01',
      inet_srch_cls_cd: 'PR03',
      prgs_stg_cd: '',
      move_cls: '',
      swrd: '',
      addr_cls: '2',
      kind_cls: '4',
      land_bing_yn: '',
      rgs_rec_stat: '1',
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
    throw new Error(`등기소 검색 실패: ${res.status}`);
  }

  return await res.json();
}

export default async function handler(req, res) {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'GET') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  try {
    const { address, sido, sigungu, road, no, no2 } = req.query;

    let params;

    if (address) {
      // 풀 주소: ?address=부산광역시 부산진구 서전로 9
      params = parseRoadAddress(address);
    } else if (sido && sigungu && road && no) {
      // 분리 파라미터: ?sido=부산광역시&sigungu=부산진구&road=서전로&no=9
      params = { sido, sigungu, roadName: road, buildingNo: no, buildingNo2: no2 || '' };
    } else {
      return res.status(400).json({
        error: '주소 파라미터 필요',
        usage: [
          '/api/iros?address=부산광역시 부산진구 서전로 9',
          '/api/iros?sido=부산광역시&sigungu=부산진구&road=서전로&no=9',
        ],
      });
    }

    if (!params.sido || !params.sigungu || !params.roadName || !params.buildingNo) {
      return res.status(400).json({ error: '주소 파싱 실패. 시도/시군구/도로명/건물번호를 확인하세요.' });
    }

    const cookieStr = await getSessionCookies();
    const data = await searchByRoadAddress(cookieStr, params);
    const { dataList } = data;

    if (!dataList || dataList.length === 0) {
      return res.status(200).json({ count: 0, results: [] });
    }

    const results = dataList.map(item => ({
      pin: item.real_pin,
      pinFormatted: `${item.real_pin.slice(0, 4)}-${item.real_pin.slice(4, 8)}-${item.real_pin.slice(8)}`,
      roadAddress: item.rd_addr,
      jibunAddress: item.real_indi_cont,
      detail: item.add_item || '',
      type: item.real_cls_name,
      buildingName: item.buld_name?.trim() || '',
      owner: item.nomprs_name || '',
      status: item.rgsbk_use_cls_name,
    }));

    return res.status(200).json({ count: results.length, results });

  } catch (err) {
    console.error('iros API error:', err);
    return res.status(500).json({ error: err.message });
  }
}

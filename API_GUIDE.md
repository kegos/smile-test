# 인터넷등기소 고유번호 조회 API

인터넷등기소(iros.go.kr)에서 도로명주소로 부동산 고유번호를 조회하는 API입니다.
로그인 없이 사용 가능하며, cafe24 서버를 통해 프록시 방식으로 동작합니다.

---

## 엔드포인트

```
GET https://kegos70.mycafe24.com/iros_proxy.php
```

---

## 요청 파라미터

### 방법 1: 풀 주소 (권장)

| 파라미터 | 필수 | 설명 | 예시 |
|---------|------|------|------|
| `address` | O | 도로명주소 전체 | `부산광역시 부산진구 서전로 9` |

```
GET /iros_proxy.php?address=부산광역시 부산진구 서전로 9
GET /iros_proxy.php?address=부산광역시 해운대구 마린시티2로 33&type=complex
```

### 방법 2: 분리 파라미터

| 파라미터 | 필수 | 설명 | 예시 |
|---------|------|------|------|
| `sido` | O | 시/도 | `부산광역시` |
| `sigungu` | O | 시/군/구 | `부산진구` |
| `road` | O | 도로명 | `서전로` |
| `no` | O | 건물번호 (본번) | `9` |
| `no2` | X | 건물번호 (부번) | `3` |
| `type` | X | 부동산 구분 (기본: `all`) | `complex` |
| `page` | X | 페이지 번호 (집합건물, 기본: 1) | `2` |

```
GET /iros_proxy.php?sido=부산광역시&sigungu=부산진구&road=서전로&no=9
GET /iros_proxy.php?sido=부산광역시&sigungu=해운대구&road=마린시티2로&no=33&type=complex
```

### type 파라미터

| 값 | 설명 | 내부 동작 |
|---|------|----------|
| `all` | 토지+건물 (기본값) | 도로명주소검색 API 사용 |
| `complex` | 집합건물 (아파트, 오피스텔 등) | 간편검색 API 사용, 동/호 지정 시 등기소가 직접 필터링 |

### 집합건물 검색 예시

```
# 동+호 지정 → 1건 정확 조회 (권장)
GET /iros_proxy.php?address=부산광역시 해운대구 마린시티2로 33&type=complex&dong=101&ho=301

# 동만 지정 → 해당 동 전체 호실
GET /iros_proxy.php?address=부산광역시 해운대구 마린시티2로 33&type=complex&dong=101

# 미지정 → 전체 목록 (페이지네이션)
GET /iros_proxy.php?address=부산광역시 해운대구 마린시티2로 33&type=complex&page=2
```

> `dong`, `ho` 파라미터에는 숫자만 입력합니다 (예: `101`, `301`). "동", "호" 글자를 붙이지 않습니다.

---

## 응답 형식

### 성공 - 토지+건물 (HTTP 200)

```json
{
  "count": 3,
  "results": [
    {
      "pin": "18411996148563",
      "pinFormatted": "1841-1996-148563",
      "roadAddress": "부산광역시 부산진구 서전로 9",
      "jibunAddress": "부산광역시 부산진구 부전동 142-14",
      "detail": "부전동 142-14",
      "type": "건물",
      "buildingName": "",
      "owner": "강**",
      "status": "현행"
    }
  ]
}
```

### 응답 필드 설명

| 필드 | 타입 | 설명 |
|------|------|------|
| `count` | number | 검색 결과 건수 |
| `results` | array | 부동산 목록 |
| `results[].pin` | string | 고유번호 (14자리, 하이픈 없음) |
| `results[].pinFormatted` | string | 고유번호 (하이픈 포함: `1841-1996-148563`) |
| `results[].roadAddress` | string | 도로명주소 |
| `results[].jibunAddress` | string | 지번주소 |
| `results[].detail` | string | 상세정보 (지번 + 건물명 등) |
| `results[].type` | string | 부동산 구분: `건물`, `토지`, `집합건물` |
| `results[].buildingName` | string | 건물명 (없으면 빈 문자열) |
| `results[].owner` | string | 소유자 (마스킹 처리됨) |
| `results[].status` | string | 등기 상태: `현행`, `전산폐쇄` |

### 성공 - 집합건물 (HTTP 200)

```json
{
  "count": 10,
  "totalCount": 2185,
  "page": 1,
  "results": [
    {
      "pin": "18112011008990",
      "pinFormatted": "1811-2011-008990",
      "roadAddress": "부산광역시 해운대구 마린시티2로 33",
      "jibunAddress": "부산광역시 해운대구 우동 1407",
      "detail": "해운대두산위브더제니스 301",
      "type": "집합건물",
      "buildingName": "해운대두산위브더제니스",
      "roomNo": "301",
      "owner": "",
      "status": "현행"
    }
  ]
}
```

집합건물 추가 필드:

| 필드 | 타입 | 설명 |
|------|------|------|
| `totalCount` | number | 전체 호실 수 |
| `page` | number | 현재 페이지 |
| `results[].roomNo` | string | 호실 번호 |

### 에러 (HTTP 400 / 500)

```json
{
  "error": "주소 파라미터 필요",
  "usage": [
    "iros_proxy.php?address=부산광역시 부산진구 서전로 9",
    "iros_proxy.php?sido=부산광역시&sigungu=부산진구&road=서전로&no=9"
  ]
}
```

---

## 사용 예시

### JavaScript (fetch)

```js
async function getRegistryPins(address) {
  const url = `https://kegos70.mycafe24.com/iros_proxy.php?address=${encodeURIComponent(address)}`;
  const res = await fetch(url);
  const data = await res.json();
  return data.results; // [{ pin, pinFormatted, roadAddress, ... }]
}

// 사용
const results = await getRegistryPins('부산광역시 부산진구 서전로 9');
console.log(results[0].pin);          // "18411996148563"
console.log(results[0].pinFormatted); // "1841-1996-148563"
```

### Python

```python
import requests
from urllib.parse import quote

address = "부산광역시 부산진구 서전로 9"
url = f"https://kegos70.mycafe24.com/iros_proxy.php?address={quote(address)}"
data = requests.get(url).json()

for item in data["results"]:
    print(f'{item["pinFormatted"]}  {item["roadAddress"]}  ({item["type"]})')
```

### curl

```bash
curl -s "https://kegos70.mycafe24.com/iros_proxy.php?address=%EB%B6%80%EC%82%B0%EA%B4%91%EC%97%AD%EC%8B%9C%20%EB%B6%80%EC%82%B0%EC%A7%84%EA%B5%AC%20%EC%84%9C%EC%A0%84%EB%A1%9C%209"
```

---

## 시/도 목록

`address` 파라미터 사용 시 아래 시/도명으로 시작해야 합니다.

| 시/도 |
|-------|
| 서울특별시 |
| 부산광역시 |
| 대구광역시 |
| 인천광역시 |
| 광주광역시 |
| 대전광역시 |
| 울산광역시 |
| 세종특별자치시 |
| 경기도 |
| 강원특별자치도 |
| 충청북도 |
| 충청남도 |
| 전북특별자치도 |
| 전라남도 |
| 경상북도 |
| 경상남도 |
| 제주특별자치도 |

---

## 참고사항

- **로그인 불필요**: 인터넷등기소 비회원 검색 기능을 사용합니다.
- **CORS 허용**: 모든 도메인에서 호출 가능 (`Access-Control-Allow-Origin: *`)
- **검색 방식**: 인터넷등기소 도로명주소검색 탭과 동일한 방식 (토지+건물)
- **응답 시간**: 세션 획득 + 검색으로 보통 2~5초 소요
- **소유자 정보**: 등기소에서 마스킹 처리된 상태 그대로 반환 (예: `강**`)
- **서버 위치**: cafe24 한국 서버 (kegos70.mycafe24.com) → 등기소 IP 제한 없음
- **서비스 시간**: 인터넷등기소 운영시간에 따름 (24시간, 점검 시 중단)

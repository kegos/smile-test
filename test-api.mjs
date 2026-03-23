// api/iros.js를 로컬에서 빠르게 테스트하는 스크립트
import handler from './api/iros.js';

const address = process.argv[2] || '부산광역시 부산진구 서전로 9';

// req/res mock
const req = {
  method: 'GET',
  query: { address },
};

let statusCode;
const res = {
  setHeader: () => {},
  status: (code) => { statusCode = code; return res; },
  json: (data) => { console.log(`\nHTTP ${statusCode}`); console.log(JSON.stringify(data, null, 2)); },
  end: () => {},
};

console.log(`테스트: /api/iros?address=${address}\n`);
await handler(req, res);

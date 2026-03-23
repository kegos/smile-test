/**
 * APICK API 등기부 열람 요청
 * POST /api/iros-view  { pin: "고유번호14자리" }
 * → { ic_id: 12345 }
 */
export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') return res.status(405).json({ error: 'POST only' });

  const { pin } = req.body;
  if (!pin) return res.status(400).json({ error: 'pin required' });

  const apiKey = process.env.APICK_API_KEY;
  if (!apiKey) return res.status(500).json({ error: 'APICK_API_KEY not configured' });

  try {
    const formData = new FormData();
    formData.append('unique_num', pin);

    const resp = await fetch('https://apick.app/rest/iros/1', {
      method: 'POST',
      headers: { 'CL_AUTH_KEY': apiKey },
      body: formData,
    });

    const data = await resp.json();

    if (!data.data || !data.data.ic_id) {
      return res.status(400).json({
        error: '열람 요청 실패',
        detail: data,
      });
    }

    if (data.data.success === 0) {
      return res.status(400).json({ error: '열람 실패 — 고유번호를 확인하세요.' });
    }

    if (data.data.success === 3) {
      return res.status(504).json({ error: '등기소 응답 타임아웃. 잠시 후 다시 시도하세요.' });
    }

    return res.status(200).json({
      ic_id: data.data.ic_id,
      cost: data.api?.cost || 0,
    });
  } catch (err) {
    console.error('iros-view error:', err);
    return res.status(500).json({ error: err.message });
  }
}

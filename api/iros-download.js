/**
 * APICK API 등기부 PDF 다운로드
 * GET /api/iros-download?ic_id=12345
 * → PDF binary (or { status: "processing" } if not ready)
 */
export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  if (req.method === 'OPTIONS') return res.status(200).end();

  const { ic_id } = req.query;
  if (!ic_id) return res.status(400).json({ error: 'ic_id required' });

  const apiKey = process.env.APICK_API_KEY;
  if (!apiKey) return res.status(500).json({ error: 'APICK_API_KEY not configured' });

  try {
    const formData = new FormData();
    formData.append('ic_id', ic_id);
    formData.append('format', 'pdf');

    const resp = await fetch('https://apick.app/rest/iros_download/1', {
      method: 'POST',
      headers: { 'CL_AUTH_KEY': apiKey },
      body: formData,
    });

    const contentType = resp.headers.get('content-type') || '';

    // JSON 응답 = 아직 처리 중이거나 에러
    if (contentType.includes('application/json') || contentType.includes('text/')) {
      const data = await resp.json();
      if (data.data && data.data.result === 2) {
        return res.status(202).json({ status: 'processing', message: 'PDF 생성 중...' });
      }
      return res.status(400).json({ error: '다운로드 실패', detail: data });
    }

    // PDF 바이너리 응답
    const buffer = Buffer.from(await resp.arrayBuffer());
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', 'attachment; filename="registry.pdf"');
    return res.status(200).send(buffer);
  } catch (err) {
    console.error('iros-download error:', err);
    return res.status(500).json({ error: err.message });
  }
}

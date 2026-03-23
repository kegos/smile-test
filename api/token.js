export default async function handler(req, res) {
  if (req.method !== 'POST') return res.status(405).end();

  const { code, redirect_uri } = req.body;
  if (!code || !redirect_uri) return res.status(400).json({ error: 'code and redirect_uri required' });

  const params = new URLSearchParams({
    code,
    client_id:     process.env.GOOGLE_CLIENT_ID,
    client_secret: process.env.GOOGLE_CLIENT_SECRET,
    redirect_uri,
    grant_type:    'authorization_code',
  });

  const r = await fetch('https://oauth2.googleapis.com/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params,
  });

  const data = await r.json();
  if (!r.ok) return res.status(400).json({ error: data.error_description || 'token exchange failed' });

  return res.status(200).json({ access_token: data.access_token });
}

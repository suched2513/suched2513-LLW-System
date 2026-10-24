/**
 * sw.js — LLW System Progressive Web App Service Worker
 * ======================================================
 * - Cache-First (Stale-While-Revalidate) สำหรับไฟล์ Static & CDN (ฟอนต์, ไอคอน, CSS/JS)
 * - Network-Only สำหรับ API (/api/, POST requests) เพื่อความถูกต้องของข้อมูลแบบ Real-time
 * - Network-First พร้อมหน้า Offline ภาษาไทยสำหรับหน้าเว็บหลัก
 */
const CACHE_NAME = 'llw-static-v2';

const PRECACHE_URLS = [
    '/manifest.json',
    '/assets/icons/icon-192.svg',
    '/assets/icons/icon-512.svg'
];

const CDN_HOSTS = [
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com'
];

const OFFLINE_HTML = `<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ไม่มีการเชื่อมต่ออินเทอร์เน็ต | LLW System</title>
<style>
  body { margin:0; font-family:'Prompt',sans-serif; background:#0b1426; color:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:24px; box-sizing:border-box; }
  .card { max-width:420px; width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:28px; padding:36px 28px; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); }
  .icon { width:68px; height:68px; border-radius:20px; background:linear-gradient(135deg,#2563eb,#4f46e5); display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:18px; }
  h1 { font-size:20px; margin:0 0 8px; font-weight:800; }
  p { font-size:14px; color:#94a3b8; line-height:1.6; margin:0 0 24px; }
  button { background:#2563eb; color:#fff; border:none; padding:12px 28px; border-radius:14px; font-size:14px; font-weight:700; cursor:pointer; width:100%; }
  button:hover { background:#1d4ed8; }
</style>
</head>
<body>
  <div class="card">
    <div class="icon">📡</div>
    <h1>ไม่สามารถเชื่อมต่ออินเทอร์เน็ตได้</h1>
    <p>อุปกรณ์ของคุณอยู่ในโหมดออฟไลน์ กรุณาตรวจสอบสัญญาณ Wi-Fi หรืออินเทอร์เน็ตมือถือแล้วลองใหม่อีกครั้ง</p>
    <button onclick="window.location.reload()">ลองเชื่อมต่ออีกครั้ง</button>
  </div>
</body>
</html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') {
        return;
    }

    const url = new URL(req.url);

    // 1. ห้าม Cache API หรือหน้า Logout/Auth เด็ดขาด
    if (
        url.pathname.includes('/api/') ||
        url.pathname.endsWith('api.php') ||
        url.pathname.endsWith('logout.php') ||
        url.pathname.endsWith('auth.php') ||
        url.pathname.endsWith('log_action.php')
    ) {
        return;
    }

    // 2. ไฟล์ CDN และ Static Assets (/assets/) -> Stale-While-Revalidate (โหลดไวทันทีจากเครื่อง)
    const isCdn = CDN_HOSTS.includes(url.hostname);
    const isStaticAsset = url.origin === self.location.origin && (
        url.pathname.startsWith('/assets/') ||
        url.pathname === '/manifest.json'
    );

    if (isCdn || isStaticAsset) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(req).then((cachedResponse) => {
                    const fetchPromise = fetch(req).then((networkResponse) => {
                        if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
                            cache.put(req, networkResponse.clone());
                        }
                        return networkResponse;
                    }).catch(() => cachedResponse);

                    return cachedResponse || fetchPromise;
                })
            )
        );
        return;
    }

    // 3. หน้าเว็บหลัก (Navigation) -> Network-First พร้อมหน้า Offline Fallback
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() =>
                new Response(OFFLINE_HTML, {
                    status: 503,
                    headers: { 'Content-Type': 'text/html; charset=utf-8' }
                })
            )
        );
    }
});

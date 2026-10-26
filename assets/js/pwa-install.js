/**
 * pwa-install.js — Unified PWA Installation & Helper for LLW System
 */
(function () {
    // 1. Inject standalone CSS rule so install buttons never flash when running inside installed app
    const style = document.createElement('style');
    style.textContent = `
        @media all and (display-mode: standalone) {
            #llw-pwa-install-btn, .llw-pwa-install-trigger { display: none !important; }
        }
    `;
    if (document.head) {
        document.head.appendChild(style);
    } else {
        document.addEventListener('DOMContentLoaded', () => document.head.appendChild(style));
    }

    // 2. Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    // 3. Detect if currently running as an installed standalone app
    window.isLlwStandalone = function () {
        return (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.navigator.standalone === true
        );
    };

    // 4. Show/hide all install buttons across the page
    window.updateLlwInstallButtons = function (forceHide) {
        const hide = forceHide === true || window.isLlwStandalone();
        document.querySelectorAll('#llw-pwa-install-btn, .llw-pwa-install-trigger').forEach((el) => {
            if (hide) {
                el.style.setProperty('display', 'none', 'important');
            } else {
                const defaultDisplay = el.getAttribute('data-pwa-display') || '';
                if (defaultDisplay) {
                    el.style.display = defaultDisplay;
                } else {
                    el.style.removeProperty('display');
                }
            }
        });
    };

    // 5. Capture beforeinstallprompt immediately (before DOMContentLoaded)
    window.deferredPwaPrompt = window.deferredPwaPrompt || null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.deferredPwaPrompt = e;
        window.updateLlwInstallButtons(false);
    });

    window.addEventListener('appinstalled', () => {
        window.deferredPwaPrompt = null;
        window.updateLlwInstallButtons(true);
    });

    // 6. Global install trigger
    window.installLlwApp = function () {
        if (window.deferredPwaPrompt) {
            window.deferredPwaPrompt.prompt();
            window.deferredPwaPrompt.userChoice.then((choice) => {
                if (choice && choice.outcome === 'accepted') {
                    window.deferredPwaPrompt = null;
                    window.updateLlwInstallButtons(true);
                }
            });
            return;
        }

        const ua = navigator.userAgent || '';
        const isInApp = /Line\/|FBAN|FBAV|Instagram/i.test(ua);
        const isIOS =
            /iPad|iPhone|iPod/.test(ua) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        let htmlContent = '';
        if (isInApp) {
            htmlContent = `
                <div style="text-align:left;font-size:0.9rem;line-height:1.65;color:#334155;">
                    <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 14px;border-radius:12px;font-weight:700;margin-bottom:12px;">
                        ⚠️ คุณกำลังเปิดผ่านแอป LINE หรือ Facebook
                    </div>
                    <p style="margin:0 0 8px;">1. กดปุ่มเมนู <b>3 จุด (⋮ หรือ ⋯)</b> ที่มุมขวาบนหรือขวาล่างของจอ</p>
                    <p style="margin:0 0 8px;">2. เลือก <b>"เปิดในเบราว์เซอร์เริ่มต้น"</b> (Open in Safari / Chrome)</p>
                    <p style="margin:0;">3. เมื่อเปิดในเบราว์เซอร์หลักแล้ว กดปุ่ม <b>"ติดตั้งแอป"</b> อีกครั้ง</p>
                </div>`;
        } else if (isIOS) {
            htmlContent = `
                <div style="text-align:left;font-size:0.9rem;line-height:1.65;color:#334155;">
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:10px 14px;border-radius:12px;font-weight:700;margin-bottom:12px;">
                        📱 สำหรับ iPhone / iPad (Safari)
                    </div>
                    <p style="margin:0 0 8px;">1. แตะปุ่ม <b>แชร์ (Share)</b> <span style="display:inline-block;padding:1px 7px;background:#e2e8f0;border-radius:6px;font-weight:700;">⬆️</span> ที่แถบด้านล่างของหน้าจอ</p>
                    <p style="margin:0 0 8px;">2. เลื่อนลงมาแล้วเลือก <b>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</b></p>
                    <p style="margin:0;">3. แตะปุ่ม <b>"เพิ่ม" (Add)</b> ที่มุมขวาบน</p>
                </div>`;
        } else {
            htmlContent = `
                <div style="text-align:left;font-size:0.9rem;line-height:1.65;color:#334155;">
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:10px 14px;border-radius:12px;font-weight:700;margin-bottom:12px;">
                        📲 วิธีติดตั้งแอป LLW System ลงอุปกรณ์
                    </div>
                    <p style="margin:0 0 10px;"><b>• Android / คอมพิวเตอร์ (Chrome / Edge):</b><br>กดเมนู <b>3 จุด (⋮)</b> ที่มุมขวาบนของเบราว์เซอร์ แล้วเลือก <b>"ติดตั้งแอป" (Install App)</b> หรือ <b>"เพิ่มลงในหน้าจอหลัก"</b></p>
                    <p style="margin:0;"><b>• iPhone / iPad (Safari):</b><br>กดปุ่ม <b>แชร์ (Share)</b> ด้านล่าง แล้วเลือก <b>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</b></p>
                </div>`;
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'ติดตั้งแอป LLW System',
                html: htmlContent,
                confirmButtonText: 'เข้าใจแล้ว',
                confirmButtonColor: '#2563eb'
            });
        } else {
            alert('วิธีติดตั้งแอป LLW System:\n- Android (Chrome): กดเมนู 3 จุดมุมขวาบน > ติดตั้งแอป (Install App)\n- iPhone/iPad (Safari): กดปุ่มแชร์ > เพิ่มไปยังหน้าจอโฮม (Add to Home Screen)');
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.updateLlwInstallButtons();
    });
})();

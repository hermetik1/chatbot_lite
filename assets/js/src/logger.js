import { Config } from './config.js';

const BUCKET = { tokens: 5, last: Date.now() };
function allow() {
  const now = Date.now();
  const dt = (now - BUCKET.last) / 1000;
  BUCKET.last = now;
  BUCKET.tokens = Math.min(5, BUCKET.tokens + dt * 2);
  if (BUCKET.tokens >= 1) { BUCKET.tokens -= 1; return true; }
  return false;
}

export async function clientLog(level, message, meta = {}) {
  if (!Config.debugEnabled || !Config.logRestUrl) return;
  if (!allow()) return;
  try {
    const payload = { level: String(level||'info'), message: String(message||''), url: String(location && location.href || ''), ...meta };
    await fetch(Config.logRestUrl, { method: 'POST', headers: { 'Content-Type':'application/json', 'X-WP-Nonce': Config.nonce }, body: JSON.stringify(payload) });
  } catch(_e) {}
}

export const logger = {
  debug: (...a) => { if (Config.debugEnabled) { try { console.debug(...a); } catch(_e){} } },
  info:  (...a) => { if (Config.debugEnabled) { try { console.info(...a); } catch(_e){} } },
  warn:  (...a) => { if (Config.debugEnabled) { try { console.warn(...a); } catch(_e){} } },
  error: (...a) => { try { console.error(...a); } catch(_e){}; try { clientLog('error', a && a[0] ? String(a[0]) : 'error', { stack: (a && a[1] && a[1].stack) ? String(a[1].stack) : '' }); } catch(_e){} },
};

// Global capture when debug on
export function attachGlobalErrorHandlers() {
  if (!Config.debugEnabled) return;
  try {
    window.addEventListener('error', function(e){
      try { clientLog('error', e && e.message ? e.message : 'window.onerror', { stack: e && e.error && e.error.stack ? String(e.error.stack) : '', line: e && e.lineno ? Number(e.lineno) : 0, col: e && e.colno ? Number(e.colno) : 0 }); } catch(_){ }
    });
    window.addEventListener('unhandledrejection', function(e){
      try {
        const msg = (e && e.reason) ? (typeof e.reason === 'string' ? e.reason : (e.reason && e.reason.message ? e.reason.message : 'unhandledrejection')) : 'unhandledrejection';
        const stk = (e && e.reason && e.reason.stack) ? String(e.reason.stack) : '';
        clientLog('error', msg, { stack: stk });
      } catch(_){ }
    });
  } catch(_){ }
}

// Expose for debugging
if (typeof window !== 'undefined') {
  window.DCB = window.DCB || {};
  window.DCB.logger = logger;
  window.DCB.clientLog = clientLog;
}


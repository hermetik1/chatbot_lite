/* global DualChatbotConfig */
// Frontend script for the Dual Chatbot Plugin.

(function(){
  // Prevent duplicate initialization if the script is injected twice
  if (window.__DualChatbotInitialized) {
    try { console.warn('Dual Chatbot already initialized; skipping duplicate init'); } catch(_) {}
    return;
  }
  window.__DualChatbotInitialized = true;
  // Small helper: is an element actually visible on screen?
  function _isVisible(el){
    try {
      if (!el) return false;
      const s = getComputedStyle(el);
      if (s.display === 'none' || s.visibility === 'hidden' || Number(s.opacity) === 0) return false;
      const rect = el.getBoundingClientRect();
      return (rect.width > 0 && rect.height > 0);
    } catch(_e) { return false; }
  }
  // Does the element intersect with the current viewport?
  function _isInViewport(el){
    try {
      if (!el) return false;
      const rect = el.getBoundingClientRect();
      const vw = (window.innerWidth || document.documentElement.clientWidth || 0);
      const vh = (window.innerHeight || document.documentElement.clientHeight || 0);
      if (!rect || rect.width <= 0 || rect.height <= 0) return false;
      return (rect.bottom > 0 && rect.right > 0 && rect.top < vh && rect.left < vw);
    } catch(_) { return false; }
  }
  // Is pointer-events disabled on the element or any ancestor?
  function _hasPointerEventsNone(el){
    try {
      let n = el;
      while (n && n.nodeType === 1) {
        const pe = getComputedStyle(n).pointerEvents;
        if (pe === 'none') return true;
        n = n.parentElement;
      }
    } catch(_){}
    return false;
  }
  // Is the element covered by another element (basic heuristic using elementFromPoint at center)?
  function _isCoveredByOverlay(el){
    try {
      const rect = el.getBoundingClientRect();
      if (!rect || rect.width <= 0 || rect.height <= 0) return true;
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height / 2;
      // If center is outside viewport, treat as covered/not interactable
      const vw = (window.innerWidth || document.documentElement.clientWidth || 0);
      const vh = (window.innerHeight || document.documentElement.clientHeight || 0);
      if (cx < 0 || cy < 0 || cx > vw || cy > vh) return true;
      const topEl = document.elementFromPoint(cx, cy);
      if (!topEl) return true;
      return !(topEl === el || el.contains(topEl));
    } catch(_) { return true; }
  }
  // Combined check: element is visible, in viewport and can be interacted with
  // Note: isElementInteractable function removed as it was unused
  
  // Robust usability check with comprehensive viewport, visibility and interaction validation
  function isTrulyUsable(el) {
    try {
      if (!el || !el.nodeType) return false;
      
      // Check if element intersects with viewport (BoundingClientRect)
      const rect = el.getBoundingClientRect();
      if (!rect || rect.width <= 0 || rect.height <= 0) return false;
      
      const vw = window.innerWidth || document.documentElement.clientWidth || 0;
      const vh = window.innerHeight || document.documentElement.clientHeight || 0;
      
      // Element must have some intersection with viewport
      if (rect.bottom <= 0 || rect.right <= 0 || rect.top >= vh || rect.left >= vw) return false;
      
      // Check visibility and clickability (display/visibility/opacity, pointer-events)
      const style = getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden') return false;
      if (Number(style.opacity) === 0) return false;
      
      // Check pointer-events on element and ancestors
      let node = el;
      while (node && node.nodeType === 1) {
        const nodeStyle = getComputedStyle(node);
        if (nodeStyle.pointerEvents === 'none') return false;
        node = node.parentElement;
      }
      
      // Check if disabled
      if (el.hasAttribute && (el.hasAttribute('disabled') || el.getAttribute('aria-disabled') === 'true')) {
        return false;
      }
      
      // Optional: elementFromPoint heuristic to detect overlay coverage
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height / 2;
      
      // If center point is outside viewport, consider unusable
      if (cx < 0 || cy < 0 || cx >= vw || cy >= vh) return false;
      
      const topElement = document.elementFromPoint(cx, cy);
      if (!topElement) return false;
      
      // Element is covered by overlay if center point hits a different element
      if (topElement !== el && !el.contains(topElement)) return false;
      
      return true;
    } catch(_) {
      return false;
    }
  }
  function makeSessionId(){
    try { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); } catch(e){}
    const s4=()=>Math.floor((1+Math.random())*0x10000).toString(16).substring(1);
    return `${s4()}${s4()}-${s4()}-${s4()}-${s4()}-${s4()}${s4()}${s4()}`;
  }
  function wpFetch(url, opts = {}) {
    opts.headers = opts.headers || {};
    opts.headers['X-WP-Nonce'] = DualChatbotConfig.nonce;
    return fetch(url, opts);
  }
  async function safeFetch(url, opts = {}, _meta = { tries: 0, tried429: false }) {
    const sleep = (ms) => new Promise(r => setTimeout(r, ms));
    const countdown = async (secs) => {
      try {
        const end = Date.now() + Math.min(30000, (secs||0)*1000);
        const id = setInterval(() => {
          const left = Math.max(0, end - Date.now());
          const s = Math.ceil(left/1000);
          if (window.DCB && typeof window.DCB.showToast === 'function') {
            window.DCB.showToast(`Zu viele Anfragen. Warte ${s}s…`);
          }
          if (left <= 0) clearInterval(id);
        }, 1000);
      } catch(_){}
    };
    try {
      opts.headers = opts.headers || {};
      if (!('X-WP-Nonce' in opts.headers)) {
        opts.headers['X-WP-Nonce'] = DualChatbotConfig.nonce;
      }
      const res = await fetch(url, opts);
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch {
        try { console.error('Non-JSON from REST:', text); } catch(_){ }
        const e = new Error('REST_ERROR_GENERIC');
        e.status = res.status;
        throw e;
      }
      if (!res.ok) {
        const status = res.status;
        const code = (data && (data.code || (data.data && data.data.status))) || status;
        const msg = (data && data.message) ? data.message : 'REST Fehler';
        const err = new Error(`${status}:${msg}`);
        err.status = status;
        err.code = code;
        // Retry on 5xx up to 3 times with backoff
        if (status >= 500 && status < 600 && _meta.tries < 3) {
          const backoff = Math.min(1200, 300 * Math.pow(2, _meta.tries));
          await sleep(backoff);
          return safeFetch(url, opts, { tries: _meta.tries + 1, tried429: _meta.tried429 });
        }
        // Retry once on 429 after retry_after hint
        if (status === 429 && !_meta.tried429) {
          let ra = 0;
          try { ra = Number((data && data.retry_after) ? data.retry_after : 0); } catch(_){ ra = 0; }
          if (!Number.isFinite(ra) || ra <= 0) { ra = 3; }
          countdown(ra);
          await sleep(Math.min(ra * 1000, 30000));
          return safeFetch(url, opts, { tries: _meta.tries, tried429: true });
        }
        throw err;
      }
      return data;
    } catch (err) { throw err; }
  }
  if (typeof window !== 'undefined') { window.DCB = window.DCB || {}; window.DCB.safeFetch = safeFetch; }
  // ===== Logging helpers =====
  const debugEnabled = !!(window.DualChatbotConfig && DualChatbotConfig.debugEnabled);
  const logEndpoint = (window.DualChatbotConfig && DualChatbotConfig.logRestUrl) ? String(DualChatbotConfig.logRestUrl) : '';
  const MAX_MSG_LEN = (window.DualChatbotConfig && Number(DualChatbotConfig.maxMessageLength)) ? Number(DualChatbotConfig.maxMessageLength) : 2000;

  function clampText(t) {
    try {
      let s = (t == null) ? '' : String(t);
      s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
      s = s.trim();
      if (!s) return '';
      if (s.length > MAX_MSG_LEN) s = s.slice(0, MAX_MSG_LEN);
      return s;
    } catch(_){ return ''; }
  }

  // simple throttle to avoid log floods
  const __logBucket = { tokens: 5, last: Date.now() };
  function allowLog(){
    const now = Date.now();
    const dt = (now - __logBucket.last) / 1000;
    __logBucket.last = now;
    __logBucket.tokens = Math.min(5, __logBucket.tokens + dt * 2);
    if (__logBucket.tokens >= 1) { __logBucket.tokens -= 1; return true; }
    return false;
  }
  async function clientLog(level, message, meta){
    try{
      if (!debugEnabled || !logEndpoint) return;
      if (!allowLog()) return;
      const payload = Object.assign({
        level: String(level||'info'),
        message: String(message||''),
        url: String(location && location.href || ''),
      }, meta || {});
      await wpFetch(logEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
    } catch(_e){}
  }
  // Logger functionality moved inline to reduce unused variable warnings

  // Global error capture (only when debug is enabled)
  if (debugEnabled) {
    try {
      window.addEventListener('error', function(e){
        try { clientLog('error', e && e.message ? e.message : 'window.onerror', { stack: e && e.error && e.error.stack ? String(e.error.stack) : '', line: e && e.lineno ? Number(e.lineno) : 0, col: e && e.colno ? Number(e.colno) : 0, url: String(location && location.href || '') }); } catch(_){ }
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
  // ===== Analytics helpers (privacy-friendly) =====
  const analyticsEnabled = !!(window.DualChatbotConfig && DualChatbotConfig.analyticsEnabled);
  const analyticsBase = (window.DualChatbotConfig && DualChatbotConfig.analyticsRestUrl) ? String(DualChatbotConfig.analyticsRestUrl).replace(/\/$/, '') : '';
  
  // Debug analytics configuration
  if (debugEnabled) {
    try {
      console.log('[ANALYTICS INIT] Analytics configuration:');
      console.log('[ANALYTICS INIT] - DualChatbotConfig:', window.DualChatbotConfig);
      console.log('[ANALYTICS INIT] - analyticsEnabled:', analyticsEnabled);
      console.log('[ANALYTICS INIT] - analyticsBase:', analyticsBase);
    } catch(_) {}
  }
  function getCookie(name){
    try{ const m = document.cookie.match(new RegExp('(?:^|; )'+name.replace(/([.$?*|{}\(\)\[\]\\\/\+^])/g,'\\$1')+'=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; }catch(_){ return ''; }
  }
  function setCookie(name, value, days){
    try{
      const d = new Date(); d.setTime(d.getTime() + (days*24*60*60*1000));
      document.cookie = `${name}=${encodeURIComponent(value)}; expires=${d.toUTCString()}; path=/; SameSite=Lax`;
    }catch(_){}
  }
  function ensureClientId(){
    let cid = getCookie('dual_chatbot_cid');
    if(!cid){ cid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(16)+Math.random().toString(16).slice(2,10)); setCookie('dual_chatbot_cid', cid, 365); }
    return cid;
  }
  const analyticsState = { clientId: ensureClientId(), startedFor: {}, inactivityTimer: null, lastActivityAt: 0 };
  function trackEvent(type, payload){
    try{
      if (debugEnabled) {
        try {
          console.log('[ANALYTICS DEBUG] trackEvent called:', type, payload);
          console.log('[ANALYTICS DEBUG] analyticsEnabled:', analyticsEnabled);
          console.log('[ANALYTICS DEBUG] analyticsBase:', analyticsBase);
        } catch(_){}
      }
      
      if(!analyticsEnabled || !analyticsBase) {
        if (debugEnabled) { try { console.log('[ANALYTICS DEBUG] Analytics disabled or no base URL - skipping'); } catch(_){} }
        return Promise.resolve();
      }
      
      const body = Object.assign({ type }, payload||{});
      if (debugEnabled) { try { console.log('[ANALYTICS DEBUG] Sending analytics event:', body); } catch(_){} }
      
      return fetch(`${analyticsBase}/analytics/track`, {
        method:'POST',
        headers: { 'Content-Type':'application/json','X-WP-Nonce': (DualChatbotConfig && DualChatbotConfig.nonce) ? DualChatbotConfig.nonce : '' },
        body: JSON.stringify(body)
      }).then(response => {
        if (debugEnabled) { try { console.log('[ANALYTICS DEBUG] Analytics response status:', response.status); } catch(_){} }
        if (!response.ok) {
          return response.text().then(text => {
            if (debugEnabled) { try { console.error('[ANALYTICS DEBUG] Analytics error response:', text); } catch(_){} }
            try {
              const errorData = JSON.parse(text);
              if (debugEnabled) { try { console.error('[ANALYTICS DEBUG] Parsed error:', errorData); } catch(_){} }
            } catch(_e) {
              if (debugEnabled) { try { console.error('[ANALYTICS DEBUG] Could not parse error response as JSON'); } catch(_){} }
            }
            throw new Error(`HTTP ${response.status}: ${text}`);
          });
        }
        return response.json().then(data => {
          if (debugEnabled) { try { console.log('[ANALYTICS DEBUG] Success response:', data); } catch(_){} }
          return data;
        });
      }).catch(error => {
        if (debugEnabled) { try { console.error('[ANALYTICS DEBUG] Analytics error:', error); } catch(_){} }
      });
    }catch(e){ 
      if (debugEnabled) { try { console.error('[ANALYTICS DEBUG] Exception in trackEvent:', e); } catch(_){} }
      return Promise.resolve(); 
    }
  }
  function ensureSessionStart(sessionId){
    console.log('[ANALYTICS DEBUG] ensureSessionStart called with sessionId:', sessionId);
    if(!sessionId) {
      console.log('[ANALYTICS DEBUG] No session ID provided');
      return;
    }
    if(analyticsState.startedFor[sessionId]) {
      console.log('[ANALYTICS DEBUG] Session already started for:', sessionId);
      return;
    }
    console.log('[ANALYTICS DEBUG] Starting new session:', sessionId);
    analyticsState.startedFor[sessionId] = true;
    trackEvent('session_start', { session_id: sessionId, client_id: analyticsState.clientId, ts: String(Date.now()) });
  }
  function scheduleInactivityEnd(getSession){
    try{ if(analyticsState.inactivityTimer) { clearTimeout(analyticsState.inactivityTimer); analyticsState.inactivityTimer = null; } }catch(_){}
    analyticsState.lastActivityAt = Date.now();
    analyticsState.inactivityTimer = setTimeout(() => {
      try{
        const sid = (typeof getSession === 'function') ? getSession() : null;
        if (sid) { trackEvent('session_end', { session_id: sid, client_id: analyticsState.clientId, ts: String(Date.now()) }); }
      }catch(_){}
    }, 30*60*1000); // 30 minutes
  }
  function showChatbotTrigger(){
    try {
      const wrapper = window.chatbotWrapper || document.getElementById('dual-chatbot-widget') || document.querySelector('#dual-chatbot-widget');
      if (wrapper) {
        // Remove inline/display-based hiding and common hidden classes
        wrapper.hidden = false;
        wrapper.style.removeProperty('display');
        wrapper.style.removeProperty('visibility');
        wrapper.classList.remove('hidden','is-hidden','d-none');
        // Fallback: force a visible display if still none
        if (getComputedStyle(wrapper).display === 'none') {
          wrapper.style.display = 'block';
        }

        const btn = wrapper.querySelector('#open-chatbot');
        if (btn) {
          btn.hidden = false;
          btn.style.removeProperty('display');
          btn.style.removeProperty('visibility');
          btn.classList.remove('hidden','is-hidden','d-none');
          if (getComputedStyle(btn).display === 'none') {
            btn.style.display = 'inline-flex';
          }
        // Keep a reference for later
        window.chatbotWrapper = wrapper;
      }

      }

      // Also ensure our own floating icon (if present) is visible
      const icon = document.querySelector('.dual-chatbot-icon');
      if (icon) {
        icon.hidden = false;
        icon.style.removeProperty('display');
        icon.classList.remove('hidden','is-hidden','d-none');
        // Ensure visibility even if CSS hides it on mobile
        try { if (getComputedStyle(icon).display === 'none') { icon.style.display = 'inline-flex'; } } catch(_){ icon.style.display = 'inline-flex'; }
      }
    } catch (e) {
      // no-op: visibility best-effort only
    }
  }
  if (!window.DualChatbotConfig || (!DualChatbotConfig.faqEnabled && !DualChatbotConfig.advisorEnabled)) {
    console.warn('Kein Chatbot-Modus aktiviert!');
    // Trotzdem versuchen wir, einen evtl. vorhandenen Theme-Button sichtbar zu machen
    try { document.addEventListener('DOMContentLoaded', showChatbotTrigger); } catch(_) {}
    return;
  }
  // Advisor features are only active for logged-in members
  const advisorMode = !!(DualChatbotConfig.advisorEnabled && DualChatbotConfig.isLoggedIn);
  const faqMode = !!DualChatbotConfig.faqEnabled;
  const restBase = DualChatbotConfig.restUrl.replace(/\/$/, '');
  // Central width check for responsive behavior (feature detection, not UA)
  const smallMql = window.matchMedia('(max-width: 800px)');
  const isSmallScreen = () => smallMql.matches;
  let currentSession = null;
  let firstBotChunkAtByClientMsg = Object.create(null);
  let userMsgStartAtByClientMsg = Object.create(null);
  let advisorMinimized = false;
  let searchTimeoutId = null;
  // Prevent race conditions when loading history (e.g., double click)
  let historyLoadSeq = 0;
  let advisorFullscreenEl = null;
  function createElement(tag, classNames = '', children = [], id = null) {
    const el = document.createElement(tag);
    if (classNames) el.className = classNames;
    children.forEach(child => {
      if (typeof child === 'string') el.appendChild(document.createTextNode(child));
      else if (child instanceof Node) el.appendChild(child);
    });
    if (id) el.id = id;
    return el;
  }

  // Accessible typing indicator (three-dot wave)
  function createTypingIndicator() {
    const msg = createElement('div', 'dual-chatbot-message dual-chatbot-bot dual-chatbot-typing is-typing');
    const wrap = createElement('div', 'dual-typing');
    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-live', 'polite');
    wrap.setAttribute('aria-label', 'Tippt …');
    wrap.appendChild(createElement('span', 'dual-typing-dot'));
    wrap.appendChild(createElement('span', 'dual-typing-dot'));
    wrap.appendChild(createElement('span', 'dual-typing-dot'));
    msg.appendChild(wrap);
    return msg;
  }
  function removeTypingIndicators(containerEl) {
    try { containerEl.querySelectorAll('.dual-chatbot-typing').forEach(n => n.remove()); } catch(_) {}
  }
  function showGreetingOnce(containerEl, mode) {
    try {
      const key = (mode === 'members' || mode === 'advisor') ? 'members' : 'faq';
      const storageKey = 'dual_chatbot_greeted_' + key;
      
      // Debug logging
      console.log('[GREETING DEBUG] Mode:', mode, 'Key:', key, 'StorageKey:', storageKey);
      console.log('[GREETING DEBUG] Already greeted?', sessionStorage.getItem(storageKey));
      console.log('[GREETING DEBUG] Container element:', containerEl);
      console.log('[GREETING DEBUG] DualChatbotConfig:', window.DualChatbotConfig);
      
      // ÄNDERUNG: Kommentieren Sie die nächsten 4 Zeilen aus, um Begrüßung bei jedem Öffnen zu zeigen
      // if (sessionStorage.getItem(storageKey) === '1') {
      //   console.log('[GREETING DEBUG] Already greeted this session, skipping');
      //   return;
      // }
      
      // Only show when chat is empty to avoid duplicates
      const hasMsgs = !!containerEl.querySelector('.dual-chatbot-message');
      console.log('[GREETING DEBUG] Has existing messages?', hasMsgs);
      if (hasMsgs) {
        console.log('[GREETING DEBUG] Chat already has messages, skipping greeting');
        return;
      }
      
      // Insert typing indicator first
      removeTypingIndicators(containerEl);
      const indicator = createTypingIndicator();
      containerEl.appendChild(indicator);
      containerEl.scrollTop = containerEl.scrollHeight;
      console.log('[GREETING DEBUG] Typing indicator added, waiting 900ms...');
      
      const delay = 900;
      setTimeout(() => {
        // Remove indicator and append greeting based on mode
        removeTypingIndicators(containerEl);
        const text = (key === 'members')
          ? (window.DualChatbotConfig && DualChatbotConfig.greetingMembers ? String(DualChatbotConfig.greetingMembers) : '')
          : (window.DualChatbotConfig && DualChatbotConfig.greetingFaq ? String(DualChatbotConfig.greetingFaq) : '');
        
        console.log('[GREETING DEBUG] Greeting text for', key + ':', text);
        
        if (text && text.trim()) {
          console.log('[GREETING DEBUG] Displaying greeting message');
          try {
            if (typeof appendMessageToContainer === 'function') {
              appendMessageToContainer(containerEl, 'bot', text);
            } else {
              // Fallback: directly append a bot message
              const msgEl = createElement('div', 'dual-chatbot-message dual-chatbot-bot');
              const content = createElement('div', 'dual-chatbot-message-content');
              if (typeof setMessageContent === 'function') { setMessageContent(content, text); }
              else { content.textContent = text; }
              msgEl.appendChild(content);
              containerEl.appendChild(msgEl);
            }
            try { normalizeActionBars(containerEl); } catch(_){}
            containerEl.scrollTop = containerEl.scrollHeight;
          } catch(e) { 
            console.error('[GREETING DEBUG] Error displaying greeting:', e);
          }
          try { 
            sessionStorage.setItem(storageKey, '1'); 
            console.log('[GREETING DEBUG] Marked as greeted in session storage');
          } catch(e) {
            console.error('[GREETING DEBUG] Error setting session storage:', e);
          }
        } else {
          console.log('[GREETING DEBUG] No greeting text configured or text is empty');
        }
        // if no greeting text, do not set greeted flag
      }, delay);
    } catch(e) {
      console.error('[GREETING DEBUG] Error in showGreetingOnce:', e);
    }
  }
  
  // Debug-Funktion für Begrüßungsnachrichten (temporär)
  window.debugChatbotGreeting = function() {
    console.log('=== CHATBOT GREETING DEBUG ===');
    console.log('DualChatbotConfig:', window.DualChatbotConfig);
    console.log('FAQ Greeting:', window.DualChatbotConfig?.greetingFaq);
    console.log('Members Greeting:', window.DualChatbotConfig?.greetingMembers);
    console.log('FAQ Mode enabled:', window.DualChatbotConfig?.faqEnabled);
    console.log('Advisor Mode enabled:', window.DualChatbotConfig?.advisorEnabled);
    
    // Session Storage Status
    console.log('Session Storage:');
    console.log('- FAQ greeted:', sessionStorage.getItem('dual_chatbot_greeted_faq'));
    console.log('- Members greeted:', sessionStorage.getItem('dual_chatbot_greeted_members'));
    
    // Container Elements
    console.log('Chat containers found:');
    const faqArea = document.querySelector('.dual-chatbot-chat-area');
    const advisorArea = document.querySelector('.dual-chatbot-main-chat');
    console.log('- FAQ area:', faqArea);
    console.log('- Advisor area:', advisorArea);
    
    if (faqArea) {
      console.log('- FAQ area has messages:', !!faqArea.querySelector('.dual-chatbot-message'));
    }
    if (advisorArea) {
      console.log('- Advisor area has messages:', !!advisorArea.querySelector('.dual-chatbot-message'));
    }
    
    // Reset session storage
    console.log('Resetting session storage...');
    sessionStorage.removeItem('dual_chatbot_greeted_faq');
    sessionStorage.removeItem('dual_chatbot_greeted_members');
    
    // Force greeting if container is available
    if (faqArea && window.DualChatbotConfig?.faqEnabled) {
      console.log('Forcing FAQ greeting...');
      showGreetingOnce(faqArea, 'faq');
    }
    if (advisorArea && window.DualChatbotConfig?.advisorEnabled) {
      console.log('Forcing Members greeting...');
      showGreetingOnce(advisorArea, 'members');
    }
  };

  // Safe content renderer used by streaming; ensures it's always defined
  if (typeof setMessageContent !== 'function') {
    var setMessageContent = function(el, text) {
      try {
        const isContent = el && el.classList && el.classList.contains('dual-chatbot-message-content');
        if (isContent) {
          el.innerHTML = '';
          // Render plain text first (parser may replace later)
          el.textContent = text || '';
          return;
        }
        // Wrap into content container when not provided
        el.innerHTML = '';
        const content = document.createElement('div');
        content.className = 'dual-chatbot-message-content';
        content.textContent = text || '';
        el.appendChild(content);
      } catch (e) {
        try { el.textContent = text || ''; } catch(_) {}
      }
    };
  }

  // Note: buildMsgActions is defined later in this file. Avoid fallback stubs to prevent double bars.
  function createHeader(title, { className = 'dual-chatbot-header', controlsLeft = false } = {}) {
    const header = createElement('div', className);
    const titleEl = createElement('span', 'dual-chatbot-header-title', [title]);
    const controls = createElement('div', 'dual-chatbot-controls');
    const closeBtn = createElement('button', 'dual-chatbot-close');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Fenster schließen');
    controls.appendChild(closeBtn);
    if (controlsLeft) {
      header.appendChild(controls);
      header.appendChild(titleEl);
    } else {
      header.appendChild(titleEl);
      header.appendChild(controls);
    }
    return { header, titleEl, closeBtn };
  }

  // Append a message into the FAQ popup chat-area (left/right alignment via CSS)
  function appendMessage(sender, text) {
    try {
      const chatArea = document.querySelector('.dual-chatbot-chat-area');
      if (!chatArea) return;
      const msg = createElement('div', `dual-chatbot-message dual-chatbot-${sender}`);
      msg.textContent = text;
      chatArea.appendChild(msg);
      chatArea.scrollTop = chatArea.scrollHeight;
    } catch (e) {
      // no-op
    }
  }

  // Nachricht senden (Advisor ODER FAQ)
  async function sendMessage(text, containerEl) {
    let safe = clampText(text);
    if (!safe) return;
    if (!advisorMode) {
      appendMessage('user', safe);
    }
    // Show typing indicator while waiting for response
    let target = containerEl
      || document.querySelector('.dual-chatbot-chat-area')
      || document.querySelector('.dual-chatbot-main-chat')
      || null;
    let typingEl = null;
    try {
      if (target) {
        removeTypingIndicators(target);
        typingEl = createTypingIndicator();
        target.appendChild(typingEl);
        target.scrollTop = target.scrollHeight;
      }
    } catch(_) {}
    const payload = {
      message: safe,
      context: advisorMode ? 'advisor' : 'faq',
    };
    if (currentSession) payload.session_id = currentSession;
    // Analytics: generate a client message id and mark start time
    const clientMsgId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(16)+Math.random().toString(16).slice(2,10));
    userMsgStartAtByClientMsg[clientMsgId] = Date.now();
    scheduleInactivityEnd(() => currentSession);
    try {
      const data = await safeFetch(`${restBase}/submit_message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      currentSession = data.session_id;
      // Analytics
      ensureSessionStart(currentSession);
      const userTs = userMsgStartAtByClientMsg[clientMsgId] || Date.now();
      const botTs = Date.now();
      trackEvent('message_user', { session_id: currentSession, client_id: analyticsState.clientId, msg_id: clientMsgId, ts: String(userTs) });
      trackEvent('message_bot', { session_id: currentSession, client_id: analyticsState.clientId, reply_to: clientMsgId, kb_hit: !advisorMode, latency_ms: Math.max(0, botTs - userTs), ts: String(botTs) });
      scheduleInactivityEnd(() => currentSession);
      try { if (typingEl && typingEl.remove) typingEl.remove(); } catch(_) {}
      if (advisorMode && containerEl) {
        const msg = createElement('div', 'dual-chatbot-message dual-chatbot-bot');
        msg.textContent = data.response;
        containerEl.appendChild(msg);
        containerEl.scrollTop = containerEl.scrollHeight;
      } else {
        appendMessage('bot', data.response);
      }
    } catch (err) {
      try {
        if (err && (err.status === 429)) {
          if (window.DCB && typeof window.DCB.showToast === 'function') window.DCB.showToast('Zu viele Anfragen. Bitte kurz warten.');
          else console.warn('Zu viele Anfragen. Bitte kurz warten.');
        } else if (err && (err.status === 403)) {
          if (window.DCB && typeof window.DCB.showToast === 'function') window.DCB.showToast('Keine Schreibrechte (nur SOLO/TEAMS).');
          else console.warn('Keine Schreibrechte (nur SOLO/TEAMS).');
        }
      } catch(_){ }
      try { if (typingEl && typingEl.remove) typingEl.remove(); } catch(_) {}
      if (advisorMode && containerEl) {
        const msg = createElement('div', 'dual-chatbot-message dual-chatbot-bot');
        msg.textContent = err.message;
        containerEl.appendChild(msg);
      } else {
        appendMessage('bot', err.message);
      }
    }
  }

  
  function scrollChatToBottom() {
    const chatArea = document.querySelector('.dual-chatbot-chat-area');
    if (chatArea) chatArea.scrollTop = chatArea.scrollHeight;
  }


// Nachricht mit Extras (z.B. Websuche im Advisor)
async function sendMessageWithExtras(text, containerEl, webSearch, options = {}) {
    const replacingExistingBot = !!(options && options.replaceBotEl);
    let safe = clampText(text);
    if (!safe) return;
    const payload = { message: safe, context: 'advisor' };
    // Ensure a single session id from the very beginning (avoids duplicates)
    if (!currentSession) currentSession = makeSessionId();
    let streamSessionId = currentSession;
    payload.session_id = currentSession;
    // Generate idempotency id for this user message
    const clientMsgId = (options && options.clientMsgId)
      ? options.clientMsgId
      : ((window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(16)+Math.random().toString(16).slice(2,10)));
    payload.client_msg_id = clientMsgId;
    // Analytics: record user message start and ensure session
    userMsgStartAtByClientMsg[clientMsgId] = Date.now();
    ensureSessionStart(currentSession);
    scheduleInactivityEnd(() => currentSession);
    if (webSearch) payload.web_search = true;

    // Bot element handling: create placeholder unless replacing an existing bot message
    const replaceBotEl = options && options.replaceBotEl ? options.replaceBotEl : null;
    const replaceBotId = options && options.replaceBotId ? options.replaceBotId : null;
    if (replaceBotId || replaceBotEl) {
      // Prevent creating a new user row in history when regenerating
      payload.no_user_insert = true;
      payload.target_bot_id = replaceBotId || 0;
    }
    // Also allow callers to force no user insert (e.g. editing a user message without an existing bot reply)
    if (options && options.noUserInsert) {
      payload.no_user_insert = true;
    }
    let botEl, contentEl;
    let typingEl = null; // separate typing message when not replacing
    let inlineTypingEl = null; // inline dots when replacing existing bot
    let botAppended = false;
    if (replaceBotEl) {
      botEl = replaceBotEl;
      botEl.classList.add('is-typing');
      contentEl = botEl.querySelector('.dual-chatbot-message-content');
      if (!contentEl) { contentEl = createElement('div','dual-chatbot-message-content'); botEl.innerHTML=''; botEl.appendChild(contentEl); }
      contentEl.innerHTML = '';
      // Inline typing dots inside existing bot message
      inlineTypingEl = createElement('div','dual-typing');
      inlineTypingEl.setAttribute('role','status');
      inlineTypingEl.setAttribute('aria-live','polite');
      inlineTypingEl.setAttribute('aria-label','Tippt …');
      inlineTypingEl.appendChild(createElement('span','dual-typing-dot'));
      inlineTypingEl.appendChild(createElement('span','dual-typing-dot'));
      inlineTypingEl.appendChild(createElement('span','dual-typing-dot'));
      botEl.appendChild(inlineTypingEl);
    } else {
      if (replacingExistingBot) { return; } // niemals extra Bot unten anhängen
      botEl = createElement('div', 'dual-chatbot-message dual-chatbot-bot is-typing');
      contentEl = createElement('div','dual-chatbot-message-content');
      botEl.appendChild(contentEl);
      // Show separate typing indicator until first content arrives
      try {
        removeTypingIndicators(containerEl);
        typingEl = createTypingIndicator();
        containerEl.appendChild(typingEl);
        containerEl.scrollTop = containerEl.scrollHeight;
      } catch(_) {}
    }
    const nearBottom = () => (containerEl.scrollTop + containerEl.clientHeight >= containerEl.scrollHeight - 40);
    const scrollIfNeeded = () => { if (nearBottom()) containerEl.scrollTop = containerEl.scrollHeight; };
    const ensureBotAppended = () => {
      try {
        if (inlineTypingEl && inlineTypingEl.remove) { inlineTypingEl.remove(); inlineTypingEl = null; }
        if (!replaceBotEl && !botAppended) {
          if (typingEl && typingEl.remove) { typingEl.remove(); typingEl = null; }
          containerEl.appendChild(botEl);
          botAppended = true;
        }
      } catch(_) {}
      scrollIfNeeded();
    };

    // Letter-by-letter typewriter similar to ChatGPT
    let full = '';
    let pending = '';
    let typeTimer = null;
    let sawDone = false;
    const cps = 60; // characters per second (faster)
    const typeInterval = Math.max(10, Math.floor(1000 / cps));
    let finalized = false;
    const finalizeNow = async () => {
      if (finalized) return; finalized = true;
      if (typeTimer) { clearInterval(typeTimer); typeTimer = null; }
      if (pending && pending.length) { full += pending; pending = ''; }
      botEl.classList.remove('is-typing');
      const holder = botEl.querySelector('.dual-chatbot-message-content') || botEl;
      setMessageContent(holder, full);
      // Build immediately and ensure presence with re-checks
        const ensureActions = () => {
          try {
            const bar = botEl.querySelector('.dual-msg-actions');
            if (!bar) { normalizeActionBars(containerEl || botEl.parentElement); return; }
            // If bar exists but has no buttons yet (fallback or race), rebuild
            if (!bar.querySelector('.dual-msg-btn')) {
              bar.remove();
              normalizeActionBars(containerEl || botEl.parentElement);
            }
          } catch(_) {}
        };
      ensureActions();
        requestAnimationFrame(() => { ensureActions(); scrollIfNeeded(); });
        setTimeout(() => { ensureActions(); scrollIfNeeded(); }, 120);
        setTimeout(() => { ensureActions(); scrollIfNeeded(); }, 400);
        setTimeout(() => { ensureActions(); scrollIfNeeded(); }, 800);
      actionsAdded = true;
      try {
        if (replaceBotId) {
          await wpFetch(`${restBase}/edit_bot_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: replaceBotId, session_id: streamSessionId || currentSession, content: full })});
        } else {
          await wpFetch(`${restBase}/append_history`, {
            method:'POST', headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ session_id: streamSessionId || currentSession, sender:'bot', context:'advisor', message: full, reply_to_client_msg_id: clientMsgId })
          });
        }
      } catch(_){}
      // After persisting, if we replaced an existing bot answer, ensure there is
      // only one bot message between this and the next user message.
      try {
        if (replaceBotEl || replaceBotId) {
          const container = containerEl || botEl.parentElement;
          const removeIfExtra = async (node) => {
            if (!node || node === botEl) return;
            if (node.classList && node.classList.contains('dual-chatbot-bot')) {
              const rid = node.dataset && node.dataset.id ? Number(node.dataset.id) : null;
              try { if (rid) { await wpFetch(`${restBase}/delete_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: rid, session_id: streamSessionId || currentSession })}); } } catch(_e){}
              try { node.remove(); } catch(_e){}
            }
          };
          // forward cleanup until next user
          let n = botEl.nextElementSibling;
          while (n && !n.classList.contains('dual-chatbot-user')) { const cur=n; n=n.nextElementSibling; await removeIfExtra(cur); }
          // backward cleanup until previous user
          let p = botEl.previousElementSibling;
          while (p && !p.classList.contains('dual-chatbot-user')) { const cur=p; p=p.previousElementSibling; await removeIfExtra(cur); }
          try { normalizeActionBars(container); } catch(_e){}
        }
      } catch(_e){}
    };
    const finishIfComplete = () => {
      if (sawDone && pending.length === 0 && !typeTimer) { finalizeNow(); }
    };
    const typeTick = () => {
      if (!pending.length) { clearInterval(typeTimer); typeTimer = null; finishIfComplete(); return; }
      const ch = pending[0];
      pending = pending.slice(1);
      full += ch;
      const holder = botEl.querySelector('.dual-chatbot-message-content') || botEl;
      holder.textContent = full;
      scrollIfNeeded();
    };
    let actionsAdded = false;
    const startTypewriter = () => { if (typeTimer) return; typeTimer = setInterval(typeTick, typeInterval); };
    const enqueueText = (t) => { if (!t) return; pending += t; startTypewriter(); };
    // Append deltas verbatim to preserve exact spacing/characters
    const enqueueDelta = (d) => {
      if (!d) return;
      enqueueText(d);
    };

    // Try streaming first
    try {
      const controller = new AbortController();
      const resp = await fetch(`${restBase}/stream_message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': DualChatbotConfig.nonce },
        body: JSON.stringify(payload),
        signal: controller.signal
      });
      if (!resp.ok || !resp.body) throw new Error('Streaming nicht verfügbar');
      const reader = resp.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let gotAnyChunk = false;
      let watchdog = setTimeout(() => { try { if (!gotAnyChunk) controller.abort(); } catch(_){} }, 25000);
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        let idx;
        while ((idx = buffer.indexOf('\n')) >= 0) {
          // Keep whitespace inside payloads; only drop trailing CR
          let line = buffer.slice(0, idx);
          buffer = buffer.slice(idx + 1);
          if (line.endsWith('\r')) line = line.slice(0, -1);
          if (!line) continue;
          // Try NDJSON first (our backend)
          try {
            const obj = JSON.parse(line);
            if (obj.type === 'meta' && obj.session_id) { currentSession = obj.session_id; streamSessionId = obj.session_id; try { ensureSessionStart(currentSession); } catch(_){} }
            if (obj.type === 'delta' && obj.content) {
              const d = String(obj.content);
              if (d && d.length > 0) {
                gotAnyChunk = true;
                if (!firstBotChunkAtByClientMsg[clientMsgId]) { firstBotChunkAtByClientMsg[clientMsgId] = Date.now(); }
                ensureBotAppended();
                enqueueDelta(d);
                if(!actionsAdded){ buildMsgActions(botEl,'bot',null,''); actionsAdded=true; }
              }
            }
            if (obj.type === 'error') {
              botEl.textContent = obj.message || 'Fehler beim Streamen.';
            }
            if (obj.type === 'done') { sawDone = true; finalizeNow(); }
            continue;
          } catch(_) {
            // Maybe raw SSE from OpenAI proxied through (lines starting with 'data:')
            if (line.startsWith('data:')) {
              // Do not trim end: payload may be a JSON with whitespace tokens
              const payload = line.slice(5).replace(/^\s+/, '');
              if (payload === '[DONE]') { sawDone = true; finalizeNow(); continue; }
              try {
                const j = JSON.parse(payload);
                const d = (((j || {}).choices || [])[0] || {}).delta || {};
                if (typeof d.content === 'string' && d.content) {
                  const s = d.content;
                  gotAnyChunk = true;
                  if (!firstBotChunkAtByClientMsg[clientMsgId]) { firstBotChunkAtByClientMsg[clientMsgId] = Date.now(); }
                  ensureBotAppended();
                  enqueueDelta(s);
                  if(!actionsAdded){ buildMsgActions(botEl,'bot',null,''); actionsAdded=true; }
                }
              } catch(__) { /* ignore malformed chunks */ }
            }
          }
        }
      }
      clearTimeout(watchdog);
      // ensure scrolled at the end if user was following
      scrollIfNeeded();
      finishIfComplete();
      if (full) {
        ensureBotAppended();
        // ensure any remaining buffered characters are included
        if (typeTimer) { clearInterval(typeTimer); typeTimer = null; }
        if (pending && pending.length) { full += pending; pending = ''; }
        const holder = botEl.querySelector('.dual-chatbot-message-content') || botEl;
        setMessageContent(holder, full);
        // Remove typing indicator and rebuild actions to ensure single bar sits beneath final content
        botEl.classList.remove('is-typing');
        buildMsgActions(botEl, 'bot', replaceBotId || null, full);
        // Persist edited or new bot message
        try {
          if (replaceBotId) {
            await wpFetch(`${restBase}/edit_bot_message`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: replaceBotId, session_id: streamSessionId || currentSession, content: full })
            });
          } else {
            await wpFetch(`${restBase}/append_history`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ session_id: streamSessionId || currentSession, sender: 'bot', context: 'advisor', message: full, reply_to_client_msg_id: clientMsgId })
            });
          }
        } catch(_){}
        // Analytics for advisor stream: user and bot metrics (kb_hit=false)
        try {
          const userTs = userMsgStartAtByClientMsg[clientMsgId] || Date.now();
          const botFirstTs = firstBotChunkAtByClientMsg[clientMsgId] || Date.now();
          trackEvent('message_user', { session_id: currentSession, client_id: analyticsState.clientId, msg_id: clientMsgId, ts: String(userTs) });
          trackEvent('message_bot', { session_id: currentSession, client_id: analyticsState.clientId, reply_to: clientMsgId, kb_hit: false, latency_ms: Math.max(0, botFirstTs - userTs), ts: String(botFirstTs) });
          scheduleInactivityEnd(() => currentSession);
        } catch(_){}
          try { normalizeActionBars(containerEl || botEl.parentElement); } catch(_){}
      }
    } catch (err) {
      // Fallback: non-streaming â€“ replace, do not append
      try {
        const data = await safeFetch(`${restBase}/submit_message`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        currentSession = data.session_id;
        streamSessionId = data.session_id;
        // stop any typewriter and render once
        if (typeTimer) { clearInterval(typeTimer); typeTimer = null; }
        pending = '';
        full = data.response || '';
        ensureBotAppended();
        const holder = botEl.querySelector('.dual-chatbot-message-content') || botEl;
        setMessageContent(holder, full);
        if(!actionsAdded){ buildMsgActions(botEl,'bot',replaceBotId || null,full); actionsAdded=true; }
        try {
          if (replaceBotId) {
            await wpFetch(`${restBase}/edit_bot_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: replaceBotId, session_id: streamSessionId || currentSession, content: full })});
          }
        } catch(_){}
        // Analytics fallback (non-streaming path)
        try {
          const userTs = userMsgStartAtByClientMsg[clientMsgId] || Date.now();
          const botTs = Date.now();
          ensureSessionStart(currentSession);
          trackEvent('message_user', { session_id: currentSession, client_id: analyticsState.clientId, msg_id: clientMsgId, ts: String(userTs) });
          trackEvent('message_bot', { session_id: currentSession, client_id: analyticsState.clientId, reply_to: clientMsgId, kb_hit: false, latency_ms: Math.max(0, botTs - userTs), ts: String(botTs) });
          scheduleInactivityEnd(() => currentSession);
        } catch(_){}
          try { normalizeActionBars(containerEl || botEl.parentElement); } catch(_){}
        sawDone = true; finishIfComplete();
      } catch (e2) {
        ensureBotAppended();
        botEl.classList.remove('is-typing');
        botEl.textContent = e2.message || 'Fehler';
      }
    }
  }

  // Initialisiere das richtige Widget je nach Modus
function initChatWidget(openDirect = false) {
  console.log('initChatWidget() wurde aufgerufen');
  // Avoid building twice (e.g., multiple event hooks)
  if (window.__DualChatbotWidgetMounted) {
    try { console.debug('Chat widget already mounted; skipping'); } catch(_) {}
    return;
  }
  // Do not force open on small screens; allow bubble icon on mobile too
  if (advisorMode) {
      buildAdvisorWidget(openDirect);
      return;
    }
    if (faqMode) {
      buildFaqWidget(openDirect);
      return;
    }
    console.warn('Kein Chatbot-Modus aktiviert!');
  }

  // FAQ Widget
  function buildFaqWidget(openDirect = false) {    
    const uiStart = (performance && performance.now) ? performance.now() : 0;
    let container = document.getElementById('dual-chatbot-container');
    if (container) {
      container.classList.add('dual-chatbot-container');
      container.innerHTML = '';
      // Force visible in case theme hid it
      container.hidden = false;
      container.style.removeProperty('display');
      container.style.removeProperty('visibility');
      container.classList.remove('hidden','is-hidden','d-none');
    } else {
      container = createElement('div', 'dual-chatbot-container', [], 'dual-chatbot-container');
    }
    document.body.appendChild(container);

    let icon = null;
    if (!openDirect) {
      icon = createElement('button', 'dual-chatbot-icon');
      icon.setAttribute('title', 'Chat');
      // ensure icon is visible (override theme/viewport CSS)
      icon.hidden = false;
      icon.style.removeProperty('display');
      icon.style.removeProperty('visibility');
      icon.classList.remove('hidden','is-hidden','d-none');
      // If CSS hides it (e.g., mobile rules), force inline-flex
      try { if (getComputedStyle(icon).display === 'none') { icon.style.display = 'inline-flex'; } } catch(_){ icon.style.display = 'inline-flex'; }
      container.appendChild(icon);
    }

    let open = openDirect;
    const popup = createElement('div', 'dual-chatbot-popup');
    popup.style.display = 'none';
    const { header, closeBtn } = createHeader((window.DualChatbotConfig && DualChatbotConfig.faqHeaderTitle) ? DualChatbotConfig.faqHeaderTitle : 'FAQ');
    try { closeBtn.setAttribute('aria-label', (DualChatbotConfig && DualChatbotConfig.labelClose) ? DualChatbotConfig.labelClose : 'Close'); } catch(_) {}
    closeBtn.addEventListener('click', () => {
      // End session for FAQ on close
      if (currentSession) { try { trackEvent('session_end', { session_id: currentSession, client_id: analyticsState.clientId, ts: String(Date.now()) }); } catch(_){} }
      open = false;
      popup.style.display = 'none';
      // Reset fullscreen styles if they were applied
      popup.style.removeProperty('position');
      popup.style.removeProperty('top');
      popup.style.removeProperty('left');
      popup.style.removeProperty('right');
      popup.style.removeProperty('bottom');
      popup.style.removeProperty('width');
      popup.style.removeProperty('height');
      popup.style.removeProperty('border-radius');
      popup.style.removeProperty('z-index');
      document.documentElement.classList.remove('dual-chatbot-modal-open');
      document.body.classList.remove('dual-chatbot-modal-open');
      if (icon) {
        icon.style.display = '';
      } else {
        container.remove();
      }
      showChatbotTrigger();
      window.widgetOpened = false;
    });

    // --- Chat-Area + Footer (Fixierung) ---
    const chatArea = createElement('div', 'dual-chatbot-chat-area');
    try { chatArea.setAttribute('role','log'); chatArea.setAttribute('aria-live','polite'); chatArea.setAttribute('aria-relevant','additions'); chatArea.setAttribute('aria-label', (DualChatbotConfig && DualChatbotConfig.ariaChatLog) ? DualChatbotConfig.ariaChatLog : 'Chat log'); } catch(_) {}
    const footer = createElement('div', 'dual-chatbot-footer');
    footer.style.position = 'sticky';
    footer.style.bottom = '0';
    footer.style.background = 'inherit';
    footer.style.zIndex = '10';
      const input = createElement('textarea', 'dual-chatbot-input');
      input.setAttribute('rows', 1);
    if (window.DualChatbotConfig && DualChatbotConfig.inputPlaceholder) {
      input.placeholder = DualChatbotConfig.inputPlaceholder;
    }
    input.placeholder = 'Nachricht…';
    const sendBtn = createElement('button', 'dual-chatbot-send');
    try { sendBtn.setAttribute('aria-label', (DualChatbotConfig && DualChatbotConfig.labelSend) ? DualChatbotConfig.labelSend : 'Send'); } catch(_) {}
    const sendIconMask = createElement('span', 'dual-chatbot-icon-mask');
    sendBtn.appendChild(sendIconMask);
    sendBtn.type = 'button';
    try { sendBtn.setAttribute('aria-label', (DualChatbotConfig && DualChatbotConfig.labelSend) ? DualChatbotConfig.labelSend : 'Send'); } catch(_) {}

    // Wrap input + send in a single row so width can be centered like ChatGPT
    const inputRow = createElement('div', 'dual-chatbot-input-wrapper');
    inputRow.appendChild(input);
    inputRow.appendChild(sendBtn);
    footer.appendChild(inputRow);

      // Enter to send (FAQ input); Shift+Enter for newline
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          // avoid double send while already sending
          if (!sendBtn.dataset.sending) {
            sendBtn.click();
          }
        }
      });
      // Auto-resize up to ~220px then scroll internally
      (function(){
        const ta = input;
        const baseH = 44;
        const maxH  = 220; // match CSS
        const fit = () => {
          try {
            ta.style.height = baseH + 'px';
            const h = Math.min(ta.scrollHeight, maxH);
            ta.style.height = h + 'px';
            ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
          } catch(_e){}
        };
        ['input','change'].forEach(ev => ta.addEventListener(ev, fit));
        requestAnimationFrame(fit);
      })();

      const triggerPress = () => {
        sendBtn.classList.add('is-sending');
        setTimeout(() => {
          if (!sendBtn.dataset.sending) sendBtn.classList.remove('is-sending');
        }, 120);
      };
      sendBtn.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') triggerPress();
      });
      sendBtn.addEventListener('mousedown', triggerPress);

      // Aufbau
      popup.appendChild(header);
      popup.appendChild(chatArea);
      popup.appendChild(footer);
      container.appendChild(popup);

      if (openDirect) {
        popup.style.display = 'flex';
        // show greeting once per session
        showGreetingOnce(chatArea, 'faq');
      }

      if (icon) {
        icon.addEventListener('click', () => {
          // On small screens, force fullscreen for FAQ as well
          if (isSmallScreen()) {
            popup.style.display = 'flex';
            popup.style.position = 'fixed';
            popup.style.top = '0';
            popup.style.left = '0';
            popup.style.right = '0';
            popup.style.bottom = '0';
            popup.style.width = '100vw';
            popup.style.height = '100vh';
            popup.style.borderRadius = '0';
            popup.style.zIndex = '999999';
            icon.style.display = 'none';
            document.documentElement.classList.add('dual-chatbot-modal-open');
            document.body.classList.add('dual-chatbot-modal-open');
            showGreetingOnce(chatArea, 'faq');
          } else {
            open = !open;
            popup.style.display = open ? 'flex' : 'none';
            icon.style.display = open ? 'none' : '';
        if (open) { showGreetingOnce(chatArea, 'faq'); try { input && input.focus(); } catch(_){} }
      }
    });
      }

      sendBtn.addEventListener('click', async () => {
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        // ensure any auto-grow/resize listeners update
        input.dispatchEvent(new Event('input'));
        try {
          sendBtn.classList.add('is-sending');
          sendBtn.dataset.sending = 'true';
          await sendMessage(text);
        } catch (err) {
          console.error(err);
      } finally {
        delete sendBtn.dataset.sending;
        sendBtn.classList.remove('is-sending');
        try { input && input.focus(); } catch(_) {}
      }
    });

      // Perf: widget build time
      try {
        if (uiStart) {
          const ms = Math.round((performance.now() - uiStart));
          trackEvent('perf_ui', { session_id: currentSession || 'ui', latency_ms: ms, ts: String(Date.now()) });
        }
      } catch(_){}
  }

  // Advisor Widget
  function buildAdvisorWidget(openDirect = false) {
    const uiStart = (performance && performance.now) ? performance.now() : 0;
    let container = document.getElementById('dual-chatbot-container');
    if (container) {
      container.classList.add('dual-chatbot-container');
      container.innerHTML = '';
      // Force visible in case theme hid it
      container.hidden = false;
      container.style.removeProperty('display');
      container.style.removeProperty('visibility');
      container.classList.remove('hidden','is-hidden','d-none');
    } else {
      container = createElement('div', 'dual-chatbot-container', [], 'dual-chatbot-container');
    }
    document.body.appendChild(container);
    if (!openDirect) {
      const icon = createElement('button', 'dual-chatbot-icon');
      icon.setAttribute('title', 'Chat');
      if (window.DualChatbotConfig && DualChatbotConfig.bubbleIconUrl) {
        icon.style.backgroundImage = 'url(' + DualChatbotConfig.bubbleIconUrl + ')';
        icon.style.backgroundRepeat = 'no-repeat';
        icon.style.backgroundPosition = 'center';
        icon.style.backgroundSize = 'contain';
      }
      // Ensure icon visible regardless of theme CSS
      icon.hidden = false;
      icon.style.removeProperty('display');
      icon.style.removeProperty('visibility');
      icon.classList.remove('hidden','is-hidden','d-none');
      // Force visible if CSS hides on mobile
      try { if (getComputedStyle(icon).display === 'none') { icon.style.display = 'inline-flex'; } } catch(_){ icon.style.display = 'inline-flex'; }
      container.appendChild(icon);
      icon.addEventListener('click', () => {
        if (advisorMinimized && advisorFullscreenEl) {
          advisorFullscreenEl.style.display = 'flex';
          advisorMinimized = false;
        } else {
          initAdvisorView();
        }
      });
    } else {
      initAdvisorView();
    }
  }

  // Vollbild-Advisor-View
  async function initAdvisorView() {
    const existing = document.querySelector('.dual-chatbot-fullscreen');
    if (existing) existing.remove();
    const fs = createElement('div', 'dual-chatbot-fullscreen');
    advisorFullscreenEl = fs;

    // External handle to open the sidebar (lives on fs, outside the sidebar)
    const openHandle = createElement('button', 'dual-chatbot-sidebar-handle');
    openHandle.type = 'button';
    try { openHandle.setAttribute('aria-label', 'Sidebar öffnen'); } catch(_) {}
    openHandle.addEventListener('click', () => setSidebar(true));
    fs.appendChild(openHandle);

    // Sidebar mit Profil, Suche, neue Chats, Liste
    const sidebar = createElement('div', 'dual-chatbot-sidebar');
    sidebar.id = 'dcb-sidebar';
    // Backdrop for mobile slide-over; visibility controlled via CSS on minimized state
    const backdrop = createElement('div', 'dual-chatbot-backdrop');
    if (DualChatbotConfig.profileUrl) {
      const profileLink = document.createElement('a');
      profileLink.className = 'dual-chatbot-profile';
      profileLink.href = DualChatbotConfig.profileUrl;
      profileLink.rel = 'noopener noreferrer';
      const label = DualChatbotConfig.userName && DualChatbotConfig.userName.trim() !== ''
        ? DualChatbotConfig.userName
        : (DualChatbotConfig.isLoggedIn ? 'Profil' : 'Anmelden');
      if (DualChatbotConfig.userAvatar) {
        const avatar = createElement('img');
        avatar.src = DualChatbotConfig.userAvatar;
        avatar.alt = label;
        profileLink.appendChild(avatar);
      }
      const nameSpan = createElement('span', '', [label]);
      profileLink.appendChild(nameSpan);
      sidebar.appendChild(profileLink);
    }
    const sbHeader = createElement('div', 'dual-chatbot-sidebar-header');
    const sbTitle  = createElement('span', 'dual-chatbot-sidebar-title', ['Unterhaltungen']);
    // Sidebar collapse/expand toggle
    const sidebarToggle = createElement('button', 'dual-chatbot-sidebar-toggle');
    sidebarToggle.type = 'button';
    sidebarToggle.setAttribute('aria-label', 'Sidebar schließen');
    sidebarToggle.setAttribute('aria-expanded', 'true');
    sidebarToggle.setAttribute('aria-controls', 'dcb-sidebar');
    const sidebarToggleIcon = createElement('span', 'dual-chatbot-sidebar-toggle-icon');
    sidebarToggleIcon.setAttribute('aria-hidden', 'true');
    sidebarToggle.innerHTML = '';
    sidebarToggle.appendChild(sidebarToggleIcon);
    // Use CSS mask for toggle icon
    try { sidebarToggleIcon.innerHTML = ''; } catch(_) {}
    const sidebarToggleMask = createElement('span', 'dual-chatbot-icon-mask');
    sidebarToggleMask.setAttribute('aria-hidden', 'true');
    sidebarToggleIcon.appendChild(sidebarToggleMask);
    // Ensure no legacy toggle remains inside the sidebar header
    try {
      const legacy = sbHeader.querySelector('.dual-chatbot-sidebar-toggle');
      if (legacy && legacy !== sidebarToggle) { legacy.remove(); }
    } catch(_) {}
    // Title and toggle: append exactly once to the sidebar header
    sbHeader.appendChild(sbTitle);
    sbHeader.appendChild(sidebarToggle);
    const searchWrapper = createElement('div', 'dual-chatbot-search-wrapper');
    const searchInput = createElement('input', 'dual-chatbot-search');
    searchInput.type = 'text';
    searchInput.placeholder = 'Suchen…';
    const newChatBtn = createElement('button', 'dual-chatbot-new-chat-btn', ['+ Neuer Chat']);
    const list = createElement('ul', 'dual-chatbot-sidebar-list');
    searchWrapper.appendChild(searchInput);
    // removed decorative search icon

    sidebar.appendChild(sbHeader);
    // On small screens, start minimized so chat has priority
    if (isSmallScreen()) {
      try {
        fs.classList.add('dual-chatbot-minimized');
        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarToggle.setAttribute('aria-label', 'Sidebar öffnen');
        advisorMinimized = true;
      } catch(_) {}
    }
    sidebar.appendChild(searchWrapper);
    sidebar.appendChild(newChatBtn);
    sidebar.appendChild(list);

    // Main Chat Area
    const main = createElement('div', 'dual-chatbot-main');
    const mainChat = createElement('div', 'dual-chatbot-main-chat');
    try { mainChat.setAttribute('role','log'); mainChat.setAttribute('aria-live','polite'); mainChat.setAttribute('aria-relevant','additions'); mainChat.setAttribute('aria-label', (DualChatbotConfig && DualChatbotConfig.ariaChatLog) ? DualChatbotConfig.ariaChatLog : 'Chat log'); } catch(_) {}
    const mainFooter = createElement('div', 'dual-chatbot-main-footer');
    const { header: mainHeader, titleEl: headerTitle, closeBtn } = createHeader((window.DualChatbotConfig && DualChatbotConfig.advisorHeaderTitle) ? DualChatbotConfig.advisorHeaderTitle : 'Berater-Chat', { className: 'dual-chatbot-main-header' });

    // Helper to set sidebar state programmatically
    function setSidebar(open){
      fs.classList.toggle('dual-chatbot-minimized', !open);
      sidebarToggle.setAttribute('aria-expanded', String(open));
      const lbl = open ? 'Sidebar schließen' : 'Sidebar öffnen';
      sidebarToggle.setAttribute('aria-label', lbl);
      sidebarToggle.setAttribute('title', lbl);
      advisorMinimized = !open;
    }

    

    
    const micIcon = createElement('button', 'dual-chatbot-microphone');
    micIcon.type = 'button';
    try { micIcon.setAttribute('aria-label', (DualChatbotConfig && DualChatbotConfig.labelMicrophone) ? DualChatbotConfig.labelMicrophone : 'Microphone'); } catch(_) {}
    micIcon.setAttribute('aria-pressed','false');
    const micMask = createElement('span', 'dual-chatbot-icon-mask');
    micIcon.appendChild(micMask);

    // Aufnahme-Aktionsleiste (Vorschau + Abbrechen + Ãœbernehmen)
    const recordActions = createElement('div', 'dual-chatbot-record-actions');
    const recordPreview = createElement('span', 'dual-chatbot-record-preview');
    const cancelRecordBtn = createElement('button', 'dual-chatbot-record-btn dual-chatbot-record-cancel');
    cancelRecordBtn.type = 'button';
    cancelRecordBtn.setAttribute('aria-label','Aufnahme abbrechen');
    const acceptRecordBtn = createElement('button', 'dual-chatbot-record-btn dual-chatbot-record-accept');
    acceptRecordBtn.type = 'button';
    acceptRecordBtn.setAttribute('aria-label','Transkript übernehmen');
    recordActions.appendChild(recordPreview);
    recordActions.appendChild(cancelRecordBtn);
    recordActions.appendChild(acceptRecordBtn);
    let searchToggle = null;
    let searchEnabled = false;
    if (DualChatbotConfig.webSearchEnabled) {
      searchToggle = createElement('button', 'dual-chatbot-search-toggle');
      searchToggle.type = 'button';
      searchToggle.setAttribute('aria-label', 'Websuche');
      searchToggle.setAttribute('title', 'Websuche');
      const searchMask = createElement('span', 'dual-chatbot-icon-mask');
      searchMask.setAttribute('aria-hidden', 'true');
      searchToggle.appendChild(searchMask);
      searchToggle.addEventListener('click', () => {
        searchEnabled = !searchEnabled;
        searchToggle.classList.toggle('active', searchEnabled);
      });
    }
    const inputWrapper = createElement('div', 'dual-chatbot-input-wrapper');
    const input = createElement('textarea', 'dual-chatbot-input');
    input.setAttribute('rows', 1);
    input.setAttribute('placeholder', 'Nachricht schreiben...');
    const sendBtn = createElement('button', 'dual-chatbot-send');
    const sendMask = createElement('span', 'dual-chatbot-icon-mask');
    sendBtn.appendChild(sendMask);
    sendBtn.type = 'button';

    const triggerPress = () => {
      sendBtn.classList.add('is-sending');
      setTimeout(() => {
        if (!sendBtn.dataset.sending) sendBtn.classList.remove('is-sending');
      }, 120);
    };
    sendBtn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') triggerPress();
    });
    sendBtn.addEventListener('mousedown', triggerPress);

      input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendBtn.click();
        }
      });
      // Auto-resize up to ~220px then scroll
      (function(){
        const ta = input;
        const baseH = 44;
        const maxH  = 220; // keep in sync with CSS
        const fit = () => {
          try {
            ta.style.height = baseH + 'px';
            const h = Math.min(ta.scrollHeight, maxH);
            ta.style.height = h + 'px';
            ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
          } catch(_e){}
        };
        ['input','change'].forEach(ev => ta.addEventListener(ev, fit));
        requestAnimationFrame(fit);
      })();
inputWrapper.appendChild(input);
inputWrapper.appendChild(sendBtn);
inputWrapper.prepend(micIcon);
// Aktionsleiste direkt nach dem Mic einfügen
inputWrapper.insertBefore(recordActions, micIcon.nextSibling);

    // ===== AUDIO-RECORDING + TIMER + STOP-BUTTON =====  
let mediaRecorder;
let audioChunks = [];
let isRecording = false;
let recordStartTime = null;
let recordTimerInterval = null;
let pendingTranscript = '';
let discardRecording = false; // wenn true -> onstop verwirft Aufzeichnung

// Timer-Element rechts neben dem Mic-Icon einfügen
const timerEl = document.createElement('span');
timerEl.className = 'dual-chatbot-record-timer';
timerEl.style.marginLeft = '10px';
timerEl.style.fontSize = '0.95em';
timerEl.style.color = '#e04a2f';
timerEl.style.display = 'none';

// Timer hinter das Mic-Icon setzen
inputWrapper.insertBefore(timerEl, micIcon.nextSibling);

micIcon.addEventListener('click', async function () {
  // wenn aktuell Aufnahme läuft -> stoppen
  if (isRecording && mediaRecorder) {
    mediaRecorder.stop();
    stopRecordingUI();
    return;
  }

  // Browser-Unterstützung checken
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    alert('Audioaufnahme nicht unterstützt.');
    return;
  }

  try {
    // Mikrofon freigeben
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(stream);

    // Aufnahme starten
    audioChunks = [];
    isRecording = true;
    discardRecording = false;
    pendingTranscript = '';
    micIcon.classList.add('recording');
    timerEl.style.display = '';
    showRecordActions('recording');
    recordStartTime = Date.now();
    updateTimer();
    recordTimerInterval = setInterval(updateTimer, 250);

    mediaRecorder.ondataavailable = (e) => {
      audioChunks.push(e.data);
    };

mediaRecorder.onstop = async () => {
      try { if (mediaRecorder && mediaRecorder.stream) { mediaRecorder.stream.getTracks().forEach(t => t.stop()); } } catch(e) {}
      stopRecordingUI();

      // Wenn abgebrochen, nichts weiter tun
      if (discardRecording) {
        audioChunks = [];
        hideRecordActions();
        discardRecording = false;
        return;
      }

      // Blob erstellen und zu Whisper senden
      const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });

      recordPreview.textContent = 'Transkribiere ...';
      showRecordActions('processing');
      input.disabled = true;

      const formData = new FormData();
      formData.append('audio', audioBlob, 'recording.webm');
      formData.append('lang', 'de');

      try {
          const res = await wpFetch(DualChatbotConfig.whisperApiUrl, {
            method: 'POST',
            body: formData,
          });
          const data = await res.json().catch(()=>({}));
          if (res.ok && data && (data.transcript || data.text)) {
            pendingTranscript = (data.transcript || data.text || '').trim();
            recordPreview.textContent = pendingTranscript.length > 64 ? (pendingTranscript.slice(0, 64) + '...') : pendingTranscript;
            showRecordActions('review');
          } else {
            pendingTranscript = '';
            const msg = (data && (data.error || data.message)) ? (data.error || data.message) : 'Transkription fehlgeschlagen.';
            alert(msg);
            hideRecordActions();
          }
        } catch (err) {
          pendingTranscript = '';
          alert('Fehler beim Transkribieren: ' + err.message);
          hideRecordActions();
        } finally {
          input.disabled = false;
          input.focus();
        }};

    mediaRecorder.start();

    // Safety-Stop nach 30s
    setTimeout(() => {
      if (isRecording && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        stopRecordingUI();
      }
    }, 30000);

  } catch (e) {
    stopRecordingUI();
    try { if (mediaRecorder && mediaRecorder.stream) { mediaRecorder.stream.getTracks().forEach(t => t.stop()); } } catch(_e) {}
    alert('Audiozugriff verweigert: ' + e.message);
  }
});

// Aufnahme abbrechen (während Aufnahme) oder verwerfen (nach Transkription)
cancelRecordBtn.addEventListener('click', () => {
  if (isRecording && mediaRecorder && mediaRecorder.state !== 'inactive') {
    discardRecording = true;
    try { mediaRecorder.stop(); } catch (e) {}
    try { if (mediaRecorder && mediaRecorder.stream) { mediaRecorder.stream.getTracks().forEach(t => t.stop()); } } catch(_e) {}
    stopRecordingUI();
  } else {
    pendingTranscript = '';
    hideRecordActions();
  }
});

// Transkript übernehmen
acceptRecordBtn.addEventListener('click', () => {
  if (!pendingTranscript) return;
  input.value = pendingTranscript;
  input.dispatchEvent(new Event('input'));
  pendingTranscript = '';
  hideRecordActions();
  input.focus();
});

function updateTimer() {
  if (!isRecording) {
    timerEl.textContent = '';
    timerEl.style.display = 'none';
    return;
  }
  const elapsed = Math.floor((Date.now() - recordStartTime) / 1000);
  const min = String(Math.floor(elapsed / 60)).padStart(2, '0');
  const sec = String(elapsed % 60).padStart(2, '0');
  timerEl.textContent = `${min}:${sec}`;
}

  function stopRecordingUI() {
  isRecording = false;
  micIcon.classList.remove('recording');
  clearInterval(recordTimerInterval);
  timerEl.textContent = '';
  timerEl.style.display = 'none';
}

// Anzeige der Aufnahme-Aktionen steuern
function showRecordActions(mode) {
  // mode: 'recording' | 'processing' | 'review'
  recordActions.style.display = 'inline-flex';
  recordActions.classList.toggle('is-recording', mode === 'recording');
  recordActions.classList.toggle('is-processing', mode === 'processing');
  recordActions.classList.toggle('is-review', mode === 'review');
  // Timer nur im Recording zeigen
  if (mode === 'recording') {
    timerEl.style.display = 'inline-block';
  } else {
    timerEl.style.display = 'none';
  }
  // Buttons schalten
  if (mode === 'recording' || mode === 'processing') {
    acceptRecordBtn.style.display = 'none';
    cancelRecordBtn.style.display = '';
    recordPreview.style.display = mode === 'processing' ? '' : 'none';
  } else if (mode === 'review') {
    acceptRecordBtn.style.display = '';
    cancelRecordBtn.style.display = '';
    recordPreview.style.display = '';
  }
}
function hideRecordActions() {
  recordActions.style.display = 'none';
  recordActions.classList.remove('is-recording','is-processing','is-review');
  recordPreview.textContent = '';
  acceptRecordBtn.style.display = 'none';
  cancelRecordBtn.style.display = 'none';
  timerEl.style.display = 'none';
}

// Senden-Button
  sendBtn.addEventListener('click', async () => {
  const text = input.value.trim();
  const safeText = clampText(text);
  if (!safeText) return;
  input.value = '';
  input.dispatchEvent(new Event('input'));
  try {
    sendBtn.classList.add('is-sending');
    sendBtn.dataset.sending = 'true';
    // Pre-generate a client message id so DOM + server share the same id
    const plannedClientMsgId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(16)+Math.random().toString(16).slice(2,10));
    appendMessageToContainer(mainChat, 'user', safeText, null, { clientMsgId: plannedClientMsgId });
    await sendMessageWithExtras(safeText, mainChat, searchEnabled, { clientMsgId: plannedClientMsgId });
    await loadSessions(searchInput.value.trim());
  } catch (err) {
    console.error(err);
  } finally {
    delete sendBtn.dataset.sending;
    sendBtn.classList.remove('is-sending');
    try { input && input.focus(); } catch(_) {}
  }
});

// Footer zusammensetzen (robust, falls mainFooter unerwartet fehlt)
const footerEl = (typeof mainFooter !== 'undefined' && mainFooter) ? mainFooter : createElement('div', 'dual-chatbot-main-footer');
if (searchToggle) footerEl.appendChild(searchToggle);
footerEl.appendChild(inputWrapper);

main.appendChild(mainHeader);
main.appendChild(mainChat);
main.appendChild(footerEl);
    fs.appendChild(sidebar);
    fs.appendChild(main);
    // Insert backdrop as the last child to sit above main but below sidebar via z-index
    fs.appendChild(backdrop);
    document.body.appendChild(fs);
// Mark as mounted to prevent duplicate UI builds
window.__DualChatbotWidgetMounted = true;
// Lock page scroll so only the chatbot shows a scrollbar
document.documentElement.classList.add('dual-chatbot-modal-open');
document.body.classList.add('dual-chatbot-modal-open');
    // Show initial greeting for members/advisor once per session
    showGreetingOnce(mainChat, 'members');
    (function () {
      const ta = document.querySelector('.dual-chatbot-input');
      if (!ta) return;

      const baseH = 44;   // muss zur CSS-Höhe passen
      const maxH  = 160;  // muss zur CSS max-height passen

      const fit = () => {
        ta.style.height = baseH + 'px';
        const h = Math.min(ta.scrollHeight, maxH);
        ta.style.height = h + 'px';
        ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
      };

      ['input','change'].forEach(ev => ta.addEventListener(ev, fit));
      // Initial
      requestAnimationFrame(fit);
    })();

    // Ensure initial auto-resize aligns with 220px cap even if earlier code used a lower cap
    (function(){
      try {
        const ta = document.querySelector('.dual-chatbot-input');
        if (!ta) return;
        const baseH = 44; // match CSS min-height
        const maxH  = 220; // match CSS max-height
        const fit = () => {
          ta.style.height = baseH + 'px';
          const h = Math.min(ta.scrollHeight, maxH);
          ta.style.height = h + 'px';
          ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
        };
        requestAnimationFrame(fit);
      } catch(_e) {}
    })();

    // Sessions und Chatverlauf laden
    // Perf: widget build end
    try {
      if (uiStart) {
        const ms = Math.round((performance.now() - uiStart));
        trackEvent('perf_ui', { session_id: currentSession || 'ui', latency_ms: ms, ts: String(Date.now()) });
      }
    } catch(_){}

    async function loadSessions(query = '') {
      try {
        const url = `${restBase}/search_sessions?query=${encodeURIComponent(query)}&context=advisor`;
        let data = { sessions: [] };
        try {
          const res = await wpFetch(url, { method: 'GET' });
          const txt = await res.text();
          try { data = JSON.parse(txt); } catch (pe) { if (debugEnabled) console.error('[SESSIONS] parse error', pe, txt.slice(0,200)); }
        } catch (fe) {
          if (debugEnabled) console.error('[SESSIONS] fetch error', fe);
        }
        list.innerHTML = '';
        if (data.sessions && data.sessions.length) {
          data.sessions.forEach(sess => {
            const title = sess.title || sess.session_id;
            const li = createElement('li', 'dual-chatbot-session-item');
            const titleSpan = createElement('span', 'dual-chatbot-session-title', [title]);
            li.appendChild(titleSpan);
            const delBtn = createElement('button', 'dual-chatbot-delete-btn');
            delBtn.type = 'button';
            delBtn.setAttribute('aria-label', 'Löschen');
            delBtn.setAttribute('title', 'Löschen');
            // Ensure delete a11y text uses UTF-8
            try { delBtn.setAttribute('aria-label', 'Löschen'); delBtn.setAttribute('title', 'Löschen'); } catch(_) {}
            // Rename button: dedicated class + SVG icon (currentColor)
            const renameBtn = createElement('button', 'dual-chatbot-rename-btn');
            renameBtn.type = 'button';
            renameBtn.setAttribute('aria-label', 'Umbenennen');
            renameBtn.setAttribute('title', 'Umbenennen');
            renameBtn.innerHTML = `
  <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
    <path d="M3 17.25V21h3.75L17.81 9.94a1 1 0 0 0 0-1.41l-3.34-3.34a1 1 0 0 0-1.41 0L3 17.25zM14.06 5.19l3.34 3.34"
          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
`;
            // Replace inline SVG with CSS mask icon
            try { renameBtn.innerHTML = ''; } catch(_) {}
            const renameIcon = createElement('span', 'dual-chatbot-icon-mask');
            renameIcon.setAttribute('aria-hidden', 'true');
            renameBtn.appendChild(renameIcon);
            li.appendChild(renameBtn);
            li.appendChild(delBtn);
            li.dataset.sessionId = sess.session_id;
            // Selecting a session (click on text OR anywhere on the item)
            const openSession = () => {
              currentSession = sess.session_id;
              list.querySelectorAll('li').forEach(l => { l.classList.remove('active'); l.removeAttribute('aria-current'); });
              li.classList.add('active');
              li.setAttribute('aria-current', 'true');
              loadHistory(sess.session_id);
            };
            // Mark active session on initial render
            if (currentSession && currentSession === sess.session_id) {
              li.classList.add('active');
              li.setAttribute('aria-current', 'true');
            }
            // Avoid double-trigger via bubbling: handle either target
            titleSpan.addEventListener('click', (e) => { e.stopPropagation(); openSession(); });
            li.addEventListener('click', openSession);
            // Double click to rename
            titleSpan.addEventListener('dblclick', () => {
              const currentText = titleSpan.textContent;
              const inputEdit = createElement('input', 'dual-chatbot-rename-input');
              inputEdit.type = 'text';
              inputEdit.value = currentText;
              li.replaceChild(inputEdit, titleSpan);
              li.classList.add('is-renaming');
              inputEdit.focus();
              const finishRename = async () => {
                const newTitle = inputEdit.value.trim();
                if (newTitle && newTitle !== currentText) {
                  await wpFetch(`${restBase}/rename_session`, {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ session_id: sess.session_id, title: newTitle })
                  });
                  titleSpan.textContent = newTitle;
                }
                li.replaceChild(titleSpan, inputEdit);
                li.classList.remove('is-renaming');
              };
              inputEdit.addEventListener('blur', finishRename);
              inputEdit.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  finishRename();
                }
              });
            });
            renameBtn.addEventListener('click', e => {
              e.stopPropagation();
              const currentText = titleSpan.textContent;
              const inputEdit = createElement('input', 'dual-chatbot-rename-input');
              inputEdit.type = 'text';
              inputEdit.value = currentText;
              li.replaceChild(inputEdit, titleSpan);
              li.classList.add('is-renaming');
              inputEdit.focus();
              const finishRename = async () => {
                const newTitle = inputEdit.value.trim();
                if (newTitle && newTitle !== currentText) {
                  await wpFetch(`${restBase}/rename_session`, {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ session_id: sess.session_id, title: newTitle })
                  });
                  titleSpan.textContent = newTitle;
                }
                li.replaceChild(titleSpan, inputEdit);
                li.classList.remove('is-renaming');
              };
              inputEdit.addEventListener('blur', finishRename);
              inputEdit.addEventListener('keydown', ev => {
                if (ev.key === 'Enter') {
                  ev.preventDefault();
                  finishRename();
                }
              });
            });
            delBtn.addEventListener('click', async e => {
              e.stopPropagation();
              if (confirm('Gespräch löschen?')) {
                await wpFetch(`${restBase}/delete_session`, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ session_id: sess.session_id })
                });
                if (currentSession === sess.session_id) {
                  currentSession = null;
                  mainChat.innerHTML = '';
                }
                await loadSessions(searchInput.value.trim());
              }
            });
            list.appendChild(li);
          });
        }
      } catch (err) {
        if (debugEnabled) console.error('[SESSIONS] render error', err);
      }
    }

    async function loadHistory(sessionId) {
      const seq = ++historyLoadSeq;
      try {
        let data = { history: [] };
        try {
          const res = await wpFetch(`${restBase}/get_history?session_id=${encodeURIComponent(sessionId)}`, { method: 'GET' });
          const txt = await res.text();
          try { data = JSON.parse(txt); } catch (pe) { if (debugEnabled) console.error('[HISTORY] parse error', pe, txt.slice(0,200)); }
        } catch (fe) {
          if (debugEnabled) console.error('[HISTORY] fetch error', fe);
        }
        // Only the latest invocation may render
        if (seq !== historyLoadSeq) return;
        mainChat.innerHTML = '';
        if (data.history) {
          const seenUser = new Set();
          const seenId = new Set();
          let lastUserText = null;
          let lastBotText = null;
          // Precompute: keep only the most recent bot message per reply_to_client_msg_id
          const lastBotByRid = new Map();
          for (const it of data.history) {
            if (it && it.sender === 'bot' && it.reply_to_client_msg_id && it.id != null) {
              const ridKey = String(it.reply_to_client_msg_id);
              const cur = lastBotByRid.get(ridKey);
              if (!cur || Number(it.id) > Number(cur)) lastBotByRid.set(ridKey, Number(it.id));
            }
          }
          data.history.forEach(item => {
            const id = item.id || null;
            const sender = item.sender;
            const cmid = item.client_msg_id || null;
            const rid = item.reply_to_client_msg_id || null;
            const txt = (item.message_content || '').trim();
            // Ignore invalid/empty bot placeholders and invalid reply ids
            if (sender === 'bot') {
              if (!txt) return;
              if (item.reply_to_client_msg_id === '0' || item.reply_to_client_msg_id === 0) return;
              // If multiple bot answers exist for the same parent, render only the newest
              if (rid && id != null) {
                const lastId = lastBotByRid.get(String(rid));
                if (lastId != null && Number(id) !== Number(lastId)) return;
              }
            }
            // Dedupe by row id if present
            if (id != null) {
              const sid = String(id);
              if (seenId.has(sid)) return; seenId.add(sid);
            }
            if (sender === 'user' && cmid) {
              if (seenUser.has(cmid)) return; seenUser.add(cmid);
            }
            // Fallback dedupe by adjacent identical text (covers legacy rows without IDs)
            if (sender === 'user') {
              if (cmid == null && lastUserText !== null && txt === lastUserText) return;
              lastUserText = txt;
            } else {
              if (rid == null && lastBotText !== null && txt === lastBotText) return;
              lastBotText = txt;
            }
            appendMessageToContainer(mainChat, sender, item.message_content, id, { clientMsgId: cmid, replyToClientMsgId: rid });
          });
            // After rendering the full history, normalize bars once
            try {
              // Prune any empty bot messages or stray bars before normalizing
              pruneEmptyMessages(mainChat);
              normalizeActionBars(mainChat);
            } catch(_){}
        }
        mainChat.scrollTop = mainChat.scrollHeight;
      } catch (err) {
        console.error(err);
      }
    }

  // --- Message rendering helpers (actions, code copy) ---
  function detectLanguage(snippet) {
    const s = snippet.trim();
    if (/^\{[\s\S]*\}$/.test(s) || /"\w+"\s*:/.test(s)) return 'json';
    if (/^<\/?[a-z!]/i.test(s)) return 'html';
    if (/function\s+|=>|const\s+|let\s+|console\./.test(s)) return 'javascript';
    if (/def\s+\w+\(|import\s+\w+|print\(/.test(s)) return 'python';
    if (/^\$|\becho\b|\bgrep\b|\bcat\b/.test(s)) return 'bash';
    if (/\$[a-z_]+\s*=|->|echo\s+|function\s*\(/i.test(s)) return 'php';
    if (/^\.|\#[^{]+\{|\bcolor:|display:/.test(s)) return 'css';
    return 'text';
  }
  function setMessageContent(el, text) {
    // Render rich message content into an existing container. If the provided
    // element is already the content container, render directly into it to
    // avoid nested wrappers (which could visually push action bars above).
    let content;
    if (el.classList && el.classList.contains('dual-chatbot-message-content')) {
      el.innerHTML = '';
      content = el;
    } else {
      el.innerHTML = '';
      content = createElement('div', 'dual-chatbot-message-content');
      el.appendChild(content);
    }
    // parse triple backticks
    const regex = /```(\w+)?\n([\s\S]*?)```/g;
    let lastIndex = 0; let m;
    while ((m = regex.exec(text)) !== null) {
      const before = text.slice(lastIndex, m.index);
      if (before) content.appendChild(document.createTextNode(before));
      const lang = (m[1] || detectLanguage(m[2])).toLowerCase();
      const pre = createElement('pre', 'dual-codeblock');
      const code = createElement('code', `lang-${lang}`);
      code.textContent = m[2];
      const copyBtn = createElement('button', 'dual-code-copy icon-copy');
      copyBtn.setAttribute('aria-label','Code kopieren');
      copyBtn.setAttribute('data-tip','Kopieren');
      copyBtn.type = 'button';
      copyBtn.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(m[2]); copyBtn.setAttribute('data-tip','Kopiert'); setTimeout(()=>copyBtn.setAttribute('data-tip','Kopieren'), 1200);} catch(_e) {}
      });
      const label = createElement('span', 'dual-code-lang', [lang]);
      pre.appendChild(copyBtn);
      pre.appendChild(label);
      pre.appendChild(code);
      content.appendChild(pre);
      lastIndex = regex.lastIndex;
    }
    const tail = text.slice(lastIndex);
    if (tail) content.appendChild(document.createTextNode(tail));
    // if el was not the content container, it already has the child appended
  }
function buildMsgActions(msgRoot, sender, id, originalText) {
  // Idempotent: ensure a single persistent action bar per message
  // Only attach actions when there is meaningful content. If no content,
  // remove any existing bars and bail.
  try {
    const contentText = (typeof originalText === 'string' && originalText.length)
      ? originalText
      : (msgRoot.querySelector('.dual-chatbot-message-content')?.innerText || msgRoot.innerText || '');
    const hasText = !!(contentText && contentText.trim().length);
    if (!hasText) {
      try { msgRoot.querySelectorAll('.dual-msg-actions').forEach(b => b.remove()); } catch(_){}
      return;
    }
  } catch(_) { /* proceed with best-effort */ }
  const stableId = (id != null && id !== undefined) ? String(id) : (msgRoot && msgRoot.dataset && msgRoot.dataset.id ? String(msgRoot.dataset.id) : '');
  let bar = null;
  try {
    if (stableId) {
      bar = msgRoot.querySelector(`.dual-msg-actions[data-for-id="${CSS && CSS.escape ? CSS.escape(stableId) : stableId}"]`);
    }
  } catch(_e) {}
  if (!bar) bar = msgRoot.querySelector('.dual-msg-actions');
  const allBars = msgRoot.querySelectorAll('.dual-msg-actions');
  if (allBars.length > 1) {
    for (let i = 1; i < allBars.length; i++) allBars[i].remove();
    bar = allBars[0];
  }
  if (bar && bar.dataset && bar.dataset.initialized === '1') {
    if (bar !== msgRoot.lastElementChild) msgRoot.appendChild(bar);
    // Even if already initialized, adjust Edit visibility for user messages
    if (sender === 'user') {
      let isLastUser = false;
      try {
        const list = msgRoot.closest('.dual-chatbot-main-chat, .dual-chatbot-chat-area, .dual-chatbot-messages, .dual-chat-history, .dual-chatbot, body');
        if (list) {
          let users = [];
          try {
            users = Array.from(list.querySelectorAll(':scope > .dual-chatbot-message.dual-chatbot-user'));
          } catch(_s) {
            users = Array.from(list.querySelectorAll('.dual-chatbot-message.dual-chatbot-user')).filter(u => u.parentElement === list);
          }
          isLastUser = users.length ? (users[users.length - 1] === msgRoot) : true;
        }
      } catch(_) { isLastUser = false; }
      if (isLastUser) {
        if (!bar.querySelector('.msg-edit')) {
          const btnEdit = createElement('button', 'dual-msg-btn msg-edit icon-edit');
          btnEdit.setAttribute('aria-label','Bearbeiten'); btnEdit.setAttribute('data-tip','Bearbeiten');
          btnEdit.addEventListener('click', () => {
            const bubble = msgRoot.querySelector('.dual-chatbot-bubble');
            const content = msgRoot.querySelector('.dual-chatbot-message-content');
            const old = content ? content.innerText : originalText || '';
            const wrap = createElement('div', 'dual-chatbot-input-wrapper dual-inline-edit');
            const ta = createElement('textarea', 'dual-chatbot-input');
            ta.setAttribute('rows', 1);
            ta.value = old;
            try { msgRoot.classList.add('is-editing'); } catch(_) {}
            const cancelBtn = createElement('button', 'msg-edit-cancel');
            cancelBtn.type = 'button';
            cancelBtn.setAttribute('aria-label','Abbrechen');
            cancelBtn.setAttribute('data-tip','Abbrechen');
            cancelBtn.setAttribute('title','Abbrechen');
            const saveBtn = createElement('button', 'dual-chatbot-send'); saveBtn.type = 'button';
            const mask = createElement('span', 'dual-chatbot-icon-mask'); saveBtn.appendChild(mask);
            wrap.appendChild(ta);
            wrap.appendChild(cancelBtn);
            wrap.appendChild(saveBtn);
            if (bubble) bubble.replaceWith(wrap); else if (content) content.replaceWith(wrap);
            const baseH = 44; const maxH = 220;
            const fit = () => { try { ta.style.height = baseH + 'px'; const h = Math.min(ta.scrollHeight, maxH); ta.style.height = h + 'px'; ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden'; } catch(_){} };
            ['input','change'].forEach(ev => ta.addEventListener(ev, fit)); requestAnimationFrame(fit); setTimeout(fit, 60); ta.focus();
            const restoreView = (text) => {
              const newBubble = createElement('div','dual-chatbot-bubble');
              const cont = createElement('div','dual-chatbot-message-content'); cont.textContent = text;
              newBubble.appendChild(cont);
              wrap.replaceWith(newBubble);
              try { msgRoot.classList.remove('is-editing'); } catch(_) {}
              buildMsgActions(msgRoot, 'user', id, text);
              try { const editBtn = msgRoot.querySelector('.msg-edit'); if (editBtn) editBtn.focus(); } catch(_){}
            };
            const doSave = async () => {
              const val = ta.value.trim();
              if (!val || val === old) { restoreView(old); return; }
              try { await wpFetch(`${restBase}/edit_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, session_id: currentSession, content: val })}); } catch(_) {}
              restoreView(val);
              let next = msgRoot.nextElementSibling;
              while (next && !next.classList.contains('dual-chatbot-bot') && !next.classList.contains('dual-chatbot-user')) next = next.nextElementSibling;
              if (next && next.classList.contains('dual-chatbot-bot')) {
                const botId = next.dataset && next.dataset.id ? Number(next.dataset.id) : null;
                let rem = next.nextElementSibling;
                while (rem && !rem.classList.contains('dual-chatbot-user')) {
                  if (rem.classList.contains('dual-chatbot-bot')) {
                    try { const rid = rem.dataset && rem.dataset.id ? Number(rem.dataset.id) : null; if (rid) await wpFetch(`${restBase}/delete_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: rid, session_id: currentSession })}); } catch(_){}
                    rem.remove();
                  }
                  rem = rem.nextElementSibling;
                }
                sendMessageWithExtras(val, msgRoot.parentElement, false, { replaceBotEl: next, replaceBotId: botId });
              } else {
                const existingCmid = (msgRoot && msgRoot.dataset && msgRoot.dataset.clientMsgId) ? msgRoot.dataset.clientMsgId : undefined;
                sendMessageWithExtras(val, msgRoot.parentElement, false, { noUserInsert: true, clientMsgId: existingCmid });
              }
            };
            const doCancel = () => restoreView(old);
            saveBtn.addEventListener('click', doSave);
            cancelBtn.addEventListener('click', doCancel);
            wrap.addEventListener('keydown', e => { if (e.key === 'Escape') { e.preventDefault(); doCancel(); } }, true);
            ta.addEventListener('keydown', e=>{ if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); doSave(); } if (e.key==='Escape') { e.preventDefault(); doCancel(); } });
          });
          bar.appendChild(btnEdit);
        }
      } else {
        try { bar.querySelectorAll('.msg-edit').forEach(el => el.remove()); } catch(_){}
      }
    }
    return;
  }
  if (!bar) bar = createElement('div', 'dual-msg-actions');
    const btnCopy = createElement('button', 'dual-msg-btn msg-copy icon-copy');
    btnCopy.setAttribute('aria-label','Kopieren');
    btnCopy.setAttribute('data-tip','Kopieren');
    btnCopy.addEventListener('click', async () => {
      const txt = originalText || msgRoot.querySelector('.dual-chatbot-message-content')?.innerText || msgRoot.innerText;
      try { await navigator.clipboard.writeText(txt); btnCopy.setAttribute('data-tip','Kopiert'); setTimeout(()=>btnCopy.setAttribute('data-tip','Kopieren'), 1200);} catch(_e){}
    });
    // reset or initialize content once
    bar.innerHTML = '';
    bar.appendChild(btnCopy);
    if (sender === 'bot') {
      const btnRegen = createElement('button', 'dual-msg-btn msg-regen icon-regen');
      btnRegen.setAttribute('aria-label','Neu generieren');
      btnRegen.setAttribute('data-tip','Neu generieren');
      btnRegen.addEventListener('click', () => {
        // find previous user message text in container
        let prev = msgRoot.previousElementSibling;
        while (prev && !prev.classList.contains('dual-chatbot-user')) prev = prev.previousElementSibling;
        const prompt = prev ? (prev.querySelector('.dual-chatbot-message-content')?.innerText || prev.innerText) : '';
        if (prompt) {
          const botId = id || (msgRoot.dataset && msgRoot.dataset.id ? Number(msgRoot.dataset.id) : null);
          sendMessageWithExtras(prompt, msgRoot.parentElement, false, { replaceBotEl: msgRoot, replaceBotId: botId });
        }
      });
      bar.appendChild(btnRegen);
      const up = createElement('button', 'dual-msg-btn msg-up icon-up');
      up.setAttribute('aria-label','Hilfreich'); up.setAttribute('data-tip','Hilfreich');
      const down = createElement('button', 'dual-msg-btn msg-down icon-down');
      down.setAttribute('aria-label','Nicht hilfreich'); down.setAttribute('data-tip','Nicht hilfreich');
      const react = async (r) => {
        let feedback = '';
        if (r === 'down') feedback = window.prompt('Was war nicht hilfreich? (optional)') || '';
        try { await wpFetch(`${restBase}/react_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: id || 0, reaction: r, feedback })}); } catch(_e) {}
      };
      up.addEventListener('click', ()=>react('up'));
      down.addEventListener('click', ()=>react('down'));
      bar.appendChild(up); bar.appendChild(down);
      // Delete bot message (DOM + history)
        const btnDelBot = createElement('button', 'dual-msg-btn msg-del icon-del');
      btnDelBot.setAttribute('aria-label','Löschen'); btnDelBot.setAttribute('data-tip','Löschen');
      btnDelBot.addEventListener('click', async () => {
        const parent = msgRoot.parentElement;
        if (!confirm('Antwort löschen?')) return;
        const rowId = id || (msgRoot && msgRoot.dataset && msgRoot.dataset.id ? Number(msgRoot.dataset.id) : null);
        try { if (rowId) { await wpFetch(`${restBase}/delete_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: rowId, session_id: currentSession })}); } } catch(_e) {}
        try { msgRoot.querySelectorAll('.dual-msg-actions').forEach(b => b.remove()); msgRoot.remove(); } catch(_e) {}
          try { normalizeActionBars(parent); } catch(_){}
      });
      bar.appendChild(btnDelBot);
    } else {
      const btnEdit = createElement('button', 'dual-msg-btn msg-edit icon-edit');
      btnEdit.setAttribute('aria-label','Bearbeiten'); btnEdit.setAttribute('data-tip','Bearbeiten');
      const btnDel = createElement('button', 'dual-msg-btn msg-del icon-del');
      btnDel.setAttribute('aria-label','Löschen'); btnDel.setAttribute('data-tip','Löschen');
      btnEdit.addEventListener('click', () => {
        const bubble = msgRoot.querySelector('.dual-chatbot-bubble');
        const content = msgRoot.querySelector('.dual-chatbot-message-content');
        const old = content ? content.innerText : originalText || '';
        // Build inline editor styled like input pill
        const wrap = createElement('div', 'dual-chatbot-input-wrapper dual-inline-edit');
        const ta = createElement('textarea', 'dual-chatbot-input');
        ta.setAttribute('rows', 1);
        ta.value = old;
        // Add editing state class on the message root for styling hooks
        try { msgRoot.classList.add('is-editing'); } catch(_) {}
        // Icon-only cancel button (accessible, tooltip)
        const cancelBtn = createElement('button', 'msg-edit-cancel');
        cancelBtn.type = 'button';
        cancelBtn.setAttribute('aria-label','Abbrechen');
        cancelBtn.setAttribute('data-tip','Abbrechen');
        cancelBtn.setAttribute('title','Abbrechen');
        const saveBtn = createElement('button', 'dual-chatbot-send'); saveBtn.type = 'button';
        const mask = createElement('span', 'dual-chatbot-icon-mask'); saveBtn.appendChild(mask);
        wrap.appendChild(ta);
        // place cancel icon inside wrapper (top-right via CSS)
        wrap.appendChild(cancelBtn);
        wrap.appendChild(saveBtn);
        if (bubble) bubble.replaceWith(wrap); else if (content) content.replaceWith(wrap);
        // Auto-grow the textarea so full text stays visible
          const baseH = 44;
          const maxH = 220;
          const fit = () => {
            try {
              ta.style.height = baseH + 'px';
              const h = Math.min(ta.scrollHeight, maxH);
              ta.style.height = h + 'px';
              ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
            } catch(_e){}
          };
        ['input','change'].forEach(ev => ta.addEventListener(ev, fit));
        requestAnimationFrame(fit);
        setTimeout(fit, 60);
        ta.focus();

        const restoreView = (text) => {
          const newBubble = createElement('div','dual-chatbot-bubble');
          const cont = createElement('div','dual-chatbot-message-content'); cont.textContent = text;
          newBubble.appendChild(cont);
          wrap.replaceWith(newBubble);
          try { msgRoot.classList.remove('is-editing'); } catch(_) {}
          buildMsgActions(msgRoot, 'user', id, text);
          // return focus to the Edit button for seamless navigation
          try {
            const editBtn = msgRoot.querySelector('.msg-edit');
            if (editBtn) editBtn.focus();
          } catch(_){}
        };

        const doSave = async () => {
          const val = ta.value.trim();
          if (!val || val === old) { restoreView(old); return; }
          try { await wpFetch(`${restBase}/edit_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, session_id: currentSession, content: val })}); } catch(_e) {}
          restoreView(val);
          // Replace the following bot message instead of adding a new one
          let next = msgRoot.nextElementSibling;
          while (next && !next.classList.contains('dual-chatbot-bot') && !next.classList.contains('dual-chatbot-user')) next = next.nextElementSibling;
          if (next && next.classList.contains('dual-chatbot-bot')) {
            const botId = next.dataset && next.dataset.id ? Number(next.dataset.id) : null;
            // Remove any further bot messages until the next user message (clean duplicates)
            let rem = next.nextElementSibling;
            while (rem && !rem.classList.contains('dual-chatbot-user')) {
              if (rem.classList.contains('dual-chatbot-bot')) {
                try { const rid = rem.dataset && rem.dataset.id ? Number(rem.dataset.id) : null; if (rid) await wpFetch(`${restBase}/delete_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: rid, session_id: currentSession })}); } catch(_){}
                rem.remove();
              }
              rem = rem.nextElementSibling;
            }
            sendMessageWithExtras(val, msgRoot.parentElement, false, { replaceBotEl: next, replaceBotId: botId });
          } else {
            // If no bot answer exists yet, request one freshly but do not create a duplicate user row
            const existingCmid = (msgRoot && msgRoot.dataset && msgRoot.dataset.clientMsgId) ? msgRoot.dataset.clientMsgId : undefined;
            sendMessageWithExtras(val, msgRoot.parentElement, false, { noUserInsert: true, clientMsgId: existingCmid });
          }
        };
        const doCancel = () => restoreView(old);

        saveBtn.addEventListener('click', doSave);
        cancelBtn.addEventListener('click', doCancel);
        // ESC anywhere within the edit wrapper cancels editing
        wrap.addEventListener('keydown', e => { if (e.key === 'Escape') { e.preventDefault(); doCancel(); } }, true);
        ta.addEventListener('keydown', e=>{
          if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); doSave(); }
          if (e.key==='Escape') { e.preventDefault(); doCancel(); }
        });
      });
      btnDel.addEventListener('click', async () => {
        if (!id) { msgRoot.remove(); return; }
        if (!confirm('Nachricht löschen?')) return;
        try { await wpFetch(`${restBase}/delete_message`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, session_id: currentSession })}); msgRoot.remove(); } catch(_e) {}
      });
      // === Only allow editing the LAST user message ===
      let isLastUser = false;
      try {
        const list = msgRoot.closest('.dual-chatbot-main-chat, .dual-chatbot-chat-area, .dual-chatbot-messages, .dual-chat-history, .dual-chatbot, body');
        if (list) {
          const users = Array.from(list.querySelectorAll('.dual-chatbot-message.dual-chatbot-user')).filter(u => u.parentElement === list);
          isLastUser = users.length ? (users[users.length - 1] === msgRoot) : true;
        }
      } catch (_) { isLastUser = false; }
      if (isLastUser) {
        bar.appendChild(btnEdit);
      } else {
        try { bar.querySelectorAll('.msg-edit').forEach(el => el.remove()); } catch(_){}
      }
      bar.appendChild(btnDel);
    }
  if (bar !== msgRoot.lastElementChild) msgRoot.appendChild(bar);
  if (bar.dataset) {
    bar.dataset.initialized = '1';
    if (stableId) bar.dataset.forId = stableId;
  }
}
  // Removed legacy ensureAssistantActions; normalization covers all cases idempotently.

  // Failsafe cleanup: remove empty bot messages and stray action bars
  function pruneEmptyMessages(containerEl) {
    try {
      // Remove bot messages with no visible content (skip live typing placeholders)
      const bots = containerEl.querySelectorAll('.dual-chatbot-message.dual-chatbot-bot');
      bots.forEach(msg => {
        if (msg.classList && msg.classList.contains('is-typing')) return;
        const txt = (msg.querySelector('.dual-chatbot-message-content')?.innerText || msg.innerText || '').trim();
        const rid = (msg.dataset && msg.dataset.replyToClientMsgId) ? msg.dataset.replyToClientMsgId : null;
        if (!txt || rid === '0' || rid === 0) {
          try { msg.querySelectorAll('.dual-msg-actions').forEach(b => b.remove()); } catch(_){}
          try { msg.remove(); } catch(_){}
        }
      });
      // Remove any action bar inside a message without content
      containerEl.querySelectorAll('.dual-chatbot-message .dual-msg-actions').forEach(bar => {
        try {
          const parent = bar.parentElement;
          if (!parent) return;
          const t = (parent.querySelector('.dual-chatbot-message-content')?.innerText || parent.innerText || '').trim();
          if (!t) bar.remove();
        } catch(_){}
      });
      // Deduplicate by data-id within this container: keep first instance
      const seen = new Set();
      containerEl.querySelectorAll('.dual-chatbot-message[data-id]').forEach(msg => {
        const mid = (msg.dataset && msg.dataset.id) ? String(msg.dataset.id) : '';
        if (!mid) return;
        if (seen.has(mid)) { try { msg.remove(); } catch(_){} } else { seen.add(mid); }
      });
    } catch(_){}
  }

  // Stronger normalization: ensure at most one bar per message across container
  function normalizeActionBars(containerEl) {
    try {
      const msgs = containerEl.querySelectorAll('.dual-chatbot-message');
      msgs.forEach(msg => {
        const contentText = (msg.querySelector('.dual-chatbot-message-content')?.innerText || msg.innerText || '');
        const hasText = !!(contentText && contentText.trim().length);
        // Always dedupe bars
        const bars = msg.querySelectorAll('.dual-msg-actions');
        if (bars.length > 1) { for (let i = 0; i < bars.length - 1; i++) bars[i].remove(); }
        // If no content: remove any stray bar and skip
        if (!hasText) {
          try { msg.querySelectorAll('.dual-msg-actions').forEach(b => b.remove()); } catch(_){}
          return;
        }
        let bar = msg.querySelector('.dual-msg-actions');
        if (bar) {
          if (bar !== msg.lastElementChild) msg.appendChild(bar);
          if (bar.dataset) bar.dataset.initialized = '1';
          return;
        }
        // If missing and content exists, create lazily for each sender
        if (msg.classList.contains('dual-chatbot-bot')) {
          try { buildMsgActions(msg, 'bot', null, contentText); } catch(_) {}
        } else if (msg.classList.contains('dual-chatbot-user')) {
          try { buildMsgActions(msg, 'user', null, contentText); } catch(_) {}
        }
      });
      // Enforce: only the LAST user message has an Edit button
      try {
        const list = containerEl.querySelector('.dual-chatbot-messages, .dual-chat-history') || containerEl;
        let users = [];
        try {
          users = Array.from(list.querySelectorAll(':scope > .dual-chatbot-message.dual-chatbot-user'));
        } catch (_selErr) {
          users = Array.from(list.querySelectorAll('.dual-chatbot-message.dual-chatbot-user')).filter(u => u.parentElement === list);
        }
        if (!users.length) return;
        const last = users[users.length - 1];
        // Remove edit button from all older user messages
        users.forEach(u => {
          if (u !== last) {
            try { u.querySelectorAll('.dual-msg-actions .msg-edit').forEach(btn => btn.remove()); } catch(_){}
          }
        });
        // Ensure last user message has an Edit button
        if (!last.querySelector('.dual-msg-actions .msg-edit')) {
          const id = last.dataset && last.dataset.id ? Number(last.dataset.id) : null;
          const txt = last.querySelector('.dual-chatbot-message-content')?.innerText || '';
          try { buildMsgActions(last, 'user', id, txt); } catch(_){}
        }
      } catch(_e){}
    } catch(_){}
  }

function appendMessageToContainer(containerEl, sender, text, id=null, opts={}) {
        // Idempotent rendering: skip if message already exists by id/client_msg_id/reply_to_client_msg_id
        try {
          if (id && containerEl.querySelector(`[data-id="${CSS.escape(String(id))}"]`)) {
            return; // already present
          }
          if (opts && opts.clientMsgId) {
            const cm = String(opts.clientMsgId);
            if (containerEl.querySelector(`[data-client-msg-id="${CSS.escape(cm)}"]`)) return;
          }
          if (opts && opts.replyToClientMsgId) {
            const rid = String(opts.replyToClientMsgId);
            if (containerEl.querySelector(`[data-reply-to-client-msg-id="${CSS.escape(rid)}"]`)) return;
          }
        } catch(_e) {}
        // Skip invalid/empty bot messages and invalid reply ids
        try {
          const t = (text || '').trim();
          if (sender === 'bot') {
            if (!t) return;
            const r = opts && (opts.replyToClientMsgId ?? null);
            if (r === '0' || r === 0) return;
          }
        } catch(_) {}
        const msgEl = createElement('div', `dual-chatbot-message dual-chatbot-${sender}`);
        if (id) msgEl.dataset.id = String(id);
        if (opts && opts.clientMsgId) msgEl.dataset.clientMsgId = String(opts.clientMsgId);
        if (opts && opts.replyToClientMsgId) msgEl.dataset.replyToClientMsgId = String(opts.replyToClientMsgId);
      const contentHolder = createElement('div','dual-chatbot-message-content');
      if (sender === 'bot') {
        setMessageContent(contentHolder, text);
        msgEl.appendChild(contentHolder);
        // Do not attach actions here for history; will be added to latest only
      } else {
        // Wrap user content in bubble so actions can sit outside
        const bubble = createElement('div','dual-chatbot-bubble');
        contentHolder.textContent = text;
        bubble.appendChild(contentHolder);
        msgEl.appendChild(bubble);
        // Ensure user action bar is present under the user message
        buildMsgActions(msgEl, 'user', id, text);
      }
      containerEl.appendChild(msgEl);
        // Guarantee a single, idempotent action bar per message
        // For user messages, normalize immediately so actions show; bot handled by stream/history observer.
        if (sender === 'user') normalizeActionBars(containerEl);
      containerEl.scrollTop = containerEl.scrollHeight;
    }

    newChatBtn.addEventListener('click', () => {
      // End current session (analytics)
      if (currentSession) { try { trackEvent('session_end', { session_id: currentSession, client_id: analyticsState.clientId, ts: String(Date.now()) }); } catch(_){} }
      currentSession = null;
      mainChat.innerHTML = '';
      list.querySelectorAll('li').forEach(l => l.classList.remove('active'));
      try { sessionStorage.removeItem('dual_chatbot_greeted_members'); } catch(_) {}
      // Show greeting again for a new chat
      showGreetingOnce(mainChat, 'members');
    });

    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeoutId);
      searchTimeoutId = setTimeout(() => {
        loadSessions(searchInput.value.trim());
      }, 300);
    });


    // Observe the main chat for new assistant messages and attach actions
      try {
        const actionsObserver = new MutationObserver((mutations) => {
          try { normalizeActionBars(mainChat); } catch(_) {}
        });
        // Persist observer on the fullscreen container so we can disconnect on close
        fs.__actionsObserver = actionsObserver;
        actionsObserver.observe(mainChat, { childList: true, subtree: true });
        // initial normalize after history render
        requestAnimationFrame(() => { try { normalizeActionBars(mainChat); } catch(_) {} });
        setTimeout(() => { try { normalizeActionBars(mainChat); } catch(_) {} }, 120);
      } catch(_){}

    // Sidebar toggle and close button (advisor)
    sidebarToggle.addEventListener('click', () => {
      const open = !(sidebarToggle.getAttribute('aria-expanded') === 'true');
      setSidebar(open);
      return;
      fs.classList.toggle('dual-chatbot-minimized', !open);
      sidebarToggle.setAttribute('aria-expanded', String(open));
      sidebarToggle.setAttribute('aria-label', !open ? 'Sidebar öffnen' : 'Sidebar schließen');
      const label = open ? 'Sidebar schließen' : 'Sidebar öffnen';
      sidebarToggle.setAttribute('aria-label', label);
      sidebarToggle.setAttribute('title', label);
      advisorMinimized = !open;
    });
    // Backdrop: click to minimize (CSS transitions handle slide + backdrop)
    try {
      const backdrop = fs.querySelector('.dual-chatbot-backdrop');
      if (backdrop) {
        backdrop.addEventListener('click', () => {
          setSidebar(false);
          return;
          if (false) {
            fs.classList.add('dual-chatbot-minimized');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            sidebarToggle.setAttribute('aria-label', 'Sidebar öffnen');
            advisorMinimized = true;
          }
        });
      }
    } catch(_){}
    // ESC key minimizes the sidebar as well
    try {
      fs.addEventListener('keydown', (e) => {
        if (e && (e.key === 'Escape' || e.key === 'Esc')) { setSidebar(false); return;
          if (!fs.classList.contains('dual-chatbot-minimized')) {
            fs.classList.add('dual-chatbot-minimized');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            sidebarToggle.setAttribute('aria-label', 'Sidebar fnen');
            advisorMinimized = true;
          }
        }
      });
    } catch(_){}
      closeBtn.addEventListener('click', () => {
        // End session for advisor on close
        if (currentSession) { try { trackEvent('session_end', { session_id: currentSession, client_id: analyticsState.clientId, ts: String(Date.now()) }); } catch(_){} }
        try { if (searchTimeoutId) { clearTimeout(searchTimeoutId); searchTimeoutId = null; } } catch(_) {}
        try { if (fs.__actionsObserver) { fs.__actionsObserver.disconnect(); delete fs.__actionsObserver; } } catch(_) {}
        fs.remove();
        advisorMinimized = false;
        // restore page scroll
        document.documentElement.classList.remove('dual-chatbot-modal-open');
        document.body.classList.remove('dual-chatbot-modal-open');
        // If we opened directly (no bubble icon), remove the empty container as well
        const cont = document.getElementById('dual-chatbot-container');
        if (cont && !cont.querySelector('.dual-chatbot-icon')) {
          cont.remove();
        }
        showChatbotTrigger();
        window.widgetOpened = false;
        window.__DualChatbotWidgetMounted = false;
      });
    loadSessions();
  }

    if (!window.__DualChatbotBootBound) {
      window.__DualChatbotBootBound = true;
      document.addEventListener('DOMContentLoaded', () => {
      if ('visualViewport' in window) { visualViewport.addEventListener('resize', scrollChatToBottom); } else { window.addEventListener('resize', scrollChatToBottom); }
      console.log('DOMContentLoaded Event');
      // Failsafe: prune any empty bot messages or orphaned action bars on load
      try {
        const areas = document.querySelectorAll('.dual-chatbot-chat-area');
        areas.forEach(a => pruneEmptyMessages(a));
      } catch(_){}
      const footerBtn = document.getElementById('open-chatbot');
      if (footerBtn) {
        footerBtn.classList.add('dual-chatbot-open');
      // Ensure wrapper/button are visible on load in case theme hides them
      const wrapper = footerBtn.closest('#dual-chatbot-widget');
      if (wrapper) {
        wrapper.hidden = false;
        wrapper.style.removeProperty('display');
        wrapper.classList.remove('hidden','is-hidden','d-none');
        window.chatbotWrapper = wrapper;
      }
      footerBtn.hidden = false;
      footerBtn.style.removeProperty('display');
      footerBtn.classList.remove('hidden','is-hidden','d-none');

        window.widgetOpened = false;
        // Avoid binding click twice
        if (!footerBtn.__dualClickBound) {
          footerBtn.__dualClickBound = true;
          footerBtn.addEventListener('click', function() {
            if (!window.widgetOpened && !document.querySelector('.dual-chatbot-fullscreen') && !document.querySelector('.dual-chatbot-popup')) {
              initChatWidget(true);
              window.widgetOpened = true;
              const wrapper = footerBtn.closest('#dual-chatbot-widget');
              if (wrapper) {
                wrapper.style.display = 'none';
                window.chatbotWrapper = wrapper;
              }
            }
          });
        }
        // Fallback: If the theme button is not truly usable, spawn our floating icon (also on mobile)
        setTimeout(() => {
          try {
            const hasAnyWidget = !!(document.querySelector('.dual-chatbot-icon') || document.querySelector('.dual-chatbot-fullscreen') || document.querySelector('.dual-chatbot-popup'));
            if (!hasAnyWidget) {
              // Use robust isTrulyUsable check for desktop fallback
              if (!isTrulyUsable(footerBtn)) {
                initChatWidget(false); // creates floating bubble icon
              }
            }
          } catch(_){}
        }, 300);
      } else {
        initChatWidget();
      }
      });
    }

  // Debounced responsive switcher for <=/> 800px: popup â†” fullscreen
  (function setupResponsiveSwitcher(){
    if (window.__DualChatbotResizeBound) return;
    window.__DualChatbotResizeBound = true;
    let lastSmall = isSmallScreen();
    let timer = null;
    const onResize = () => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => {
        const nowSmall = isSmallScreen();
        if (nowSmall === lastSmall) return;
        lastSmall = nowSmall;
        if (nowSmall) {
          // To small: close popup if visible and open fullscreen
          try {
            const popup = document.querySelector('.dual-chatbot-popup');
            if (popup && getComputedStyle(popup).display !== 'none') {
              // Convert FAQ popup to fullscreen on mobile
              popup.style.position = 'fixed';
              popup.style.top = '0';
              popup.style.left = '0';
              popup.style.right = '0';
              popup.style.bottom = '0';
              popup.style.width = '100vw';
              popup.style.height = '100vh';
              popup.style.borderRadius = '0';
              popup.style.zIndex = '999999';
              document.documentElement.classList.add('dual-chatbot-modal-open');
              document.body.classList.add('dual-chatbot-modal-open');
            }
          } catch(_) {}
          if (!document.querySelector('.dual-chatbot-fullscreen') && advisorMode) {
            initAdvisorView();
          }
        } else {
          // To desktop: reset popup to normal style and close fullscreen
          try {
            const popup = document.querySelector('.dual-chatbot-popup');
            if (popup) {
              // Reset FAQ popup styles
              popup.style.removeProperty('position');
              popup.style.removeProperty('top');
              popup.style.removeProperty('left');
              popup.style.removeProperty('right');
              popup.style.removeProperty('bottom');
              popup.style.removeProperty('width');
              popup.style.removeProperty('height');
              popup.style.removeProperty('border-radius');
              popup.style.removeProperty('z-index');
              document.documentElement.classList.remove('dual-chatbot-modal-open');
              document.body.classList.remove('dual-chatbot-modal-open');
            }
            const fs = document.querySelector('.dual-chatbot-fullscreen');
            if (fs) {
              const closeBtn = fs.querySelector('.dual-chatbot-close');
              if (closeBtn) closeBtn.click(); else {
                fs.remove();
                document.documentElement.classList.remove('dual-chatbot-modal-open');
                document.body.classList.remove('dual-chatbot-modal-open');
                window.__DualChatbotWidgetMounted = false;
              }
            }
          } catch(_) {}
          // Ensure an icon/popup can be used again
          try {
            const hasContainer = !!document.getElementById('dual-chatbot-container');
            const hasIcon = !!document.querySelector('.dual-chatbot-icon');
            if (!window.__DualChatbotWidgetMounted && (!hasContainer || !hasIcon)) {
              initChatWidget(false);
            }
          } catch(_) {}
        }
      }, 150);
    };
    window.addEventListener('resize', onResize);
  })();

})();


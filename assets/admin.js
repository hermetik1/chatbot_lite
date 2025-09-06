(() => {
  function $(sel) { return document.querySelector(sel); }
  document.addEventListener('DOMContentLoaded', () => {
    const btn = $('#fl-localize-now');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      btn.textContent = 'Arbeite...';
      const resEl = $('#fl-localize-result');
      resEl.textContent = '';
      try {
        const resp = await fetch((window.FLLite && FLLite.ajax_url) || ajaxurl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'fl_localize_now',
            nonce: (window.FLLite && FLLite.nonce) || '',
          }).toString()
        });
        const data = await resp.json();
        if (!data || !data.success) throw new Error('Fehler');
        const results = data.data && data.data.results ? data.data.results : {};
        let ok = 0, err = 0;
        Object.values(results).forEach(v => {
          if (v && v.status === 'local') ok++; else err++;
        });
        resEl.innerHTML = `<p><strong>Fertig:</strong> <span class="fl-result-ok">${ok} lokal</span>, <span class="fl-result-err">${err} fehlgeschlagen</span></p>`;
        location.reload();
      } catch (e) {
        resEl.textContent = 'Fehler beim Lokalisieren';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Fonts lokal speichern';
      }
    });
  });
})();


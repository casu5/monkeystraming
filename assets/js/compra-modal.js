// assets/js/compra-modal.js
(function () {
  const API_COMPRAR = 'comprar.php';

  function getUser() {
    const u = window.__USER__ || {};
    return {
      isLogged: !!u.isLogged,
      saldo: Number(u.saldo || 0),
      redirectAfterLogin: String(u.redirectAfterLogin || 'productos.php'),
    };
  }

  function money(n) {
    const x = Number(n || 0);
    return 'S/ ' + x.toFixed(2);
  }

  function closeModal(modal) {
    if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
  }

  function createModalShell() {
    const modal = document.createElement('div');
    modal.style.cssText = `
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.85);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 99999;
      backdrop-filter: blur(6px);
      padding: 18px;
    `;
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal(modal);
    });
    return modal;
  }

  function cardHtml({ title, bodyHtml, buttonsHtml }) {
    return `
      <div style="
        width: min(520px, 96vw);
        background: linear-gradient(135deg,#12151d,#0d0f14);
        border: 1px solid rgba(18,170,255,.22);
        border-radius: 18px;
        padding: 28px;
        box-shadow: 0 20px 55px rgba(0,0,0,.55);
        color: #e5e5e5;
        text-align: center;
      ">
        <div style="font-size: 2.7rem; color:#12aaff; margin-bottom: 12px;">
          <i class="fas fa-shopping-cart"></i>
        </div>
        <h2 style="margin:0 0 12px; color:#12aaff; font-size: 1.25rem;">${title}</h2>
        <div style="
          background: rgba(255,255,255,.05);
          border: 1px solid rgba(255,255,255,.06);
          border-radius: 12px;
          padding: 16px;
          margin: 14px 0 18px;
          text-align: left;
        ">
          ${bodyHtml}
        </div>

        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
          ${buttonsHtml}
        </div>

        <div style="margin-top: 16px; color:#8c8c8c; font-size:.85rem;">
          <i class="fas fa-shield-alt"></i> Compra segura • Soporte 24/7
        </div>
      </div>
    `;
  }

  async function postComprar(productId) {
    const body = new URLSearchParams();
    body.append('action', 'buy');
    body.append('product_id', String(productId));

    const r = await fetch(API_COMPRAR, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });

    const text = await r.text();
    let j;
    try {
      j = JSON.parse(text);
    } catch {
      return { ok: false, message: 'Respuesta inválida del servidor (no es JSON).', raw: text };
    }

    if (!r.ok) {
      return { ok: false, message: j.message || 'Error en la compra.', redirect: j.redirect };
    }

    return j;
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function openCompraModal({ id, nombre, precio }) {
    const user = getUser();

    const modal = createModalShell();
    document.body.appendChild(modal);

    if (!id || Number(id) <= 0) {
      modal.innerHTML = cardHtml({
        title: 'Producto inválido',
        bodyHtml: `
          <div style="font-weight:800; font-size:1.05rem; margin-bottom:6px;">${escapeHtml(nombre)}</div>
          <div style="color:#0de0c9; font-size:1.4rem; font-weight:900;">${money(precio)}</div>
          <div style="margin-top:10px; color:#bcbcbc;">No se puede comprar: ID inválido.</div>
        `,
        buttonsHtml: `
          <button type="button" class="ms-close" style="
            padding:12px 20px; border:0; border-radius:10px; cursor:pointer;
            background:#2f2f2f; color:#fff; font-weight:900;
          ">Cerrar</button>
        `,
      });
      modal.querySelector('.ms-close').addEventListener('click', () => closeModal(modal));
      return;
    }

    const bodyHtml = `
      <div style="font-weight:900; font-size:1.05rem; margin-bottom:6px;">${escapeHtml(nombre)}</div>
      <div style="color:#0de0c9; font-size:1.6rem; font-weight:1000;">${money(precio)}</div>
      <div style="margin-top:10px; color:#aaa; font-size:.92rem;">
        Confirma antes de realizar la compra.
      </div>
    `;

    if (!user.isLogged) {
      modal.innerHTML = cardHtml({
        title: 'Inicia sesión para continuar',
        bodyHtml,
        buttonsHtml: `
          <button type="button" class="ms-cancel" style="
            padding:12px 20px; border:0; border-radius:10px; cursor:pointer;
            background:#2f2f2f; color:#fff; font-weight:900;
          ">Cancelar</button>

          <a href="login.php?redirect=${encodeURIComponent(user.redirectAfterLogin)}" style="
            padding:12px 20px; border-radius:10px; text-decoration:none; display:inline-flex;
            align-items:center; gap:8px;
            background: linear-gradient(135deg,#0de0c9,#12aaff);
            font-weight:1000; color:#0d0f14;
          ">
            <i class="fas fa-sign-in-alt"></i> Iniciar sesión
          </a>
        `,
      });

      modal.querySelector('.ms-cancel').addEventListener('click', () => closeModal(modal));
      return;
    }

    if (user.saldo < Number(precio)) {
      modal.innerHTML = cardHtml({
        title: 'Saldo insuficiente',
        bodyHtml: `
          ${bodyHtml}
          <div style="margin-top:12px; color:#ffb3b3; font-weight:900;">
            No tienes saldo suficiente.
          </div>
          <div style="margin-top:8px; color:#bcbcbc;">
            Tu saldo: <b style="color:#0de0c9">${money(user.saldo)}</b>
          </div>
        `,
        buttonsHtml: `
          <button type="button" class="ms-ok" style="
            padding:12px 20px; border:0; border-radius:10px; cursor:pointer;
            background:#2f2f2f; color:#fff; font-weight:900;
          ">OK</button>

          <a href="recargar.php" style="
            padding:12px 20px; border-radius:10px; text-decoration:none; display:inline-flex;
            align-items:center; gap:8px;
            background: linear-gradient(135deg,#0de0c9,#12aaff);
            font-weight:1000; color:#0d0f14;
          ">
            <i class="fas fa-coins"></i> Ir a recargar
          </a>
        `,
      });

      modal.querySelector('.ms-ok').addEventListener('click', () => closeModal(modal));
      return;
    }

    modal.innerHTML = cardHtml({
      title: 'Confirmar compra',
      bodyHtml: `
        ${bodyHtml}
        <div style="margin-top:10px; color:#bcbcbc;">
          Saldo disponible: <b style="color:#0de0c9">${money(user.saldo)}</b>
        </div>
      `,
      buttonsHtml: `
        <button type="button" class="ms-cancel" style="
          padding:12px 20px; border:0; border-radius:10px; cursor:pointer;
          background:#2f2f2f; color:#fff; font-weight:1000;
        ">Cancelar</button>

        <button type="button" class="ms-buy" style="
          padding:12px 20px; border:0; border-radius:10px; cursor:pointer;
          background: linear-gradient(135deg,#0de0c9,#12aaff);
          font-weight:1000; color:#0d0f14; display:inline-flex; gap:8px; align-items:center;
        ">
          <i class="fas fa-credit-card"></i> Comprar ahora
        </button>
      `,
    });

    modal.querySelector('.ms-cancel').addEventListener('click', () => closeModal(modal));

    const buyBtn = modal.querySelector('.ms-buy');
    buyBtn.addEventListener('click', async () => {
      buyBtn.disabled = true;
      buyBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Procesando...`;

      try {
        const j = await postComprar(id);

        if (!j.ok) {
          if (j.redirect) {
            window.location.href = j.redirect;
            return;
          }
          alert(j.message || 'No se pudo completar la compra.');
          buyBtn.disabled = false;
          buyBtn.innerHTML = `<i class="fas fa-credit-card"></i> Comprar ahora`;
          return;
        }

        modal.innerHTML = cardHtml({
          title: '¡Compra exitosa!',
          bodyHtml: `
            <div style="display:flex; justify-content:center; margin-bottom:10px;">
              <div style="
                width:64px; height:64px; border-radius:50%;
                background: rgba(13,224,201,.15);
                display:flex; align-items:center; justify-content:center;
                border: 1px solid rgba(13,224,201,.35);
                animation: pop .35s ease;
              ">
                <i class="fas fa-check" style="color:#0de0c9; font-size: 28px;"></i>
              </div>
            </div>
            <div style="color:#bcbcbc;">${escapeHtml(j.message || 'Compra registrada correctamente.')}</div>
            ${(j.compra_id || j.purchase_id) ? `<div style="margin-top:10px; color:#aaa;">N° compra: <b>${escapeHtml(String(j.compra_id || j.purchase_id))}</b></div>` : ''}
            <style>
              @keyframes pop { from{ transform:scale(.75); opacity:.2 } to{ transform:scale(1); opacity:1 } }
            </style>
          `,
          buttonsHtml: `
            <button type="button" class="ms-close" style="
              padding:12px 20px; border:0; border-radius:10px; cursor:pointer;
              background:#2f2f2f; color:#fff; font-weight:1000;
            ">Cerrar</button>

            <a href="${escapeHtml(j.redirect || 'user/dashboard.php')}" style="
              padding:12px 20px; border-radius:10px; text-decoration:none; display:inline-flex;
              align-items:center; gap:8px;
              background: linear-gradient(135deg,#0de0c9,#12aaff);
              font-weight:1000; color:#0d0f14;
            ">
              <i class="fas fa-th-large"></i> Ir a mi cuenta
            </a>
          `,
        });

        modal.querySelector('.ms-close').addEventListener('click', () => closeModal(modal));

      } catch (e) {
        alert('Error de red o servidor.');
        buyBtn.disabled = false;
        buyBtn.innerHTML = `<i class="fas fa-credit-card"></i> Comprar ahora`;
      }
    });
  }

  // ✅ Delegación: funciona aunque los botones se generen por PHP
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-buy');
    if (!btn) return;

    const id = Number(btn.dataset.id || 0);
    const nombre = btn.dataset.nombre || '';
    const precio = Number(btn.dataset.precio || 0);

    openCompraModal({ id, nombre, precio });
  });
})();

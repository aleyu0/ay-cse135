(function () {
  const THEME_KEY = "aae_theme";
  const root = document.documentElement;

  /* ---------- Theme (OS default + persisted) ---------- */
  function getPreferredTheme(){
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === "light" || saved === "dark") return saved;
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  function applyTheme(theme){
    root.setAttribute("data-theme", theme);
    localStorage.setItem(THEME_KEY, theme);
    const btn = document.querySelector("[data-theme-toggle]");
    if (btn) btn.setAttribute("aria-label", theme === "dark" ? "Switch to light theme" : "Switch to dark theme");
    updateShopImagesForTheme(); // update images live
  }

  applyTheme(getPreferredTheme());

  const themeBtn = document.querySelector("[data-theme-toggle]");
  if (themeBtn) {
    themeBtn.addEventListener("click", () => {
      const cur = root.getAttribute("data-theme") || "light";
      applyTheme(cur === "dark" ? "light" : "dark");
    });
  }

  function themeVariant(){
    // Per your rule:
    // light theme -> use -white assets
    // dark theme  -> use -black assets
    return (root.getAttribute("data-theme") || "light") === "dark" ? "black" : "white";
  }

  /* ---------- Mobile nav ---------- */
  const menuBtn = document.querySelector('[data-menu]');
  const navLinks = document.querySelector('[data-navlinks]');
  if (menuBtn && navLinks) {
    menuBtn.addEventListener('click', () => navLinks.classList.toggle('open'));
  }

  /* ---------- Toast ---------- */
  const toast = document.querySelector('[data-toast]');
  function showToast(msg){
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    window.clearTimeout(showToast._t);
    showToast._t = window.setTimeout(() => toast.classList.remove('show'), 1400);
  }

  /* ---------- Cart (icon only) ---------- */
  const cartBtn = document.querySelector("[data-cart]");
  if (cartBtn) cartBtn.addEventListener("click", () => showToast("Cart is not enabled yet."));

  /* ---------- Image helpers (preview vs fullsize) ---------- */
  function previewSrc(imageBase){
    if (!imageBase) return "";
    return `assets/product-images/previews/${imageBase}-${themeVariant()}.jpg`;
  }
  function fullsizeSrc(imageBase){
    if (!imageBase) return "";
    return `assets/product-images/fullsize/${imageBase}-${themeVariant()}.png`;
  }

  function updateShopImagesForTheme(){
    // Update all rendered shop images (previews)
    document.querySelectorAll("img[data-imgbase][data-kind='preview']").forEach(img => {
      const base = img.getAttribute("data-imgbase");
      const next = previewSrc(base);
      if (next && img.src !== next) img.src = next;
    });

    // Update modal image (fullsize) if open
    const modalImg = document.querySelector("img[data-modal-img]");
    if (modalImg && modalImg.getAttribute("data-imgbase")) {
      const base = modalImg.getAttribute("data-imgbase");
      const next = fullsizeSrc(base);
      if (next && modalImg.src !== next) modalImg.src = next;
    }
  }

  /* ---------- Modal ---------- */
  const overlay = document.querySelector('[data-modal-overlay]');
  const modalTitle = document.querySelector('[data-modal-title]');
  const modalTag = document.querySelector('[data-modal-tag]');
  const modalPrice = document.querySelector('[data-modal-price]');
  const modalDesc = document.querySelector('[data-modal-desc]');
  const modalImg = document.querySelector('img[data-modal-img]');
  const modalAddBtn = document.querySelector('[data-modal-add]');
  const modalSource = document.querySelector('[data-modal-source]');
  let modalCurrent = null;

  function openModal(p){
    if (!overlay) return;
    modalCurrent = p;

    modalTitle.textContent = p.name;
    modalTag.textContent = p.category;
    modalPrice.textContent = `$${Number(p.price).toFixed(2)}`;
    modalDesc.textContent = p.desc;

    if (modalImg) {
      modalImg.alt = p.name;
      modalImg.setAttribute("data-imgbase", p.imageBase || "");
      modalImg.src = fullsizeSrc(p.imageBase);
    }

    if (modalSource) {
        modalSource.textContent = p.source ? `Source: ${p.source}` : "";
    }

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    modalAddBtn?.focus();
  }

  function closeModal(){
    if (!overlay) return;
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    modalCurrent = null;
  }

  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target.matches('[data-modal-overlay]') || e.target.matches('[data-modal-close]')) closeModal();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
    });
  }

  if (modalAddBtn) {
    modalAddBtn.addEventListener('click', () => {
      if (!modalCurrent) return;
      showToast(`Added: ${modalCurrent.name}`);
      closeModal();
    });
  }

  /* ---------- Shop rendering ---------- */
  const shopGrid = document.querySelector('[data-shop-grid]');
  let cachedProducts = null;

  function escapeHtml(str){
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  async function loadProducts(){
    if (cachedProducts) return cachedProducts;
    const r = await fetch("assets/products.json");
    cachedProducts = await r.json();
    return cachedProducts;
  }

  async function renderShop(){
    if (!shopGrid) return;

    let products;
    try {
      products = await loadProducts();
    } catch {
      shopGrid.innerHTML = `<div class="card panel"><p>Unable to load products.json</p></div>`;
      return;
    }

    shopGrid.innerHTML =
      products.map(p => `
        <article class="card shopCard" tabindex="0" role="button" aria-label="Open ${escapeHtml(p.name)}" data-open="${escapeHtml(p.id)}">
          <div class="imgBox">
            <img
              data-kind="preview"
              data-imgbase="${escapeHtml(p.imageBase || "")}"
              src="${escapeHtml(previewSrc(p.imageBase))}"
              alt="${escapeHtml(p.name)}"
              loading="lazy"
              onerror="this.remove(); this.parentElement.textContent='Image';"
            />
          </div>

          <div class="metaRow">
            <div><p class="itemTitle">${escapeHtml(p.name)}</p></div>
            <span class="tag">${escapeHtml(p.category)}</span>
          </div>

          <p class="itemDesc">${escapeHtml(p.desc)}</p>

          <div class="cardSpacer"></div>

          <div class="priceRow">
            <span class="price">$${Number(p.price).toFixed(2)}</span>
            <button class="btn primary" type="button" data-add="${escapeHtml(p.id)}">Add</button>
          </div>
        </article>
      `).join("") +
      `
        <a class="card shopCard requestCard" href="contact.html" aria-label="Request procurement">
          <div class="imgBox"><span style="color: var(--muted);">Request</span></div>
          <div class="metaRow">
            <p class="itemTitle">Procurement Request</p>
            <span class="tag">Request</span>
          </div>
          <p class="itemDesc">Need something that is absolutely essential? Request it now.</p>
          <div class="cardSpacer"></div>
          <div class="priceRow">
            <span class="price">—</span>
            <span class="btn">Open</span>
          </div>
        </a>
      `;

    // click handlers (single bind)
    shopGrid.addEventListener("click", (e) => {
    const addBtn = e.target.closest("button[data-add]");
    if (addBtn) {
        e.stopPropagation();
        const id = addBtn.getAttribute("data-add");
        const p = products.find(x => x.id === id);
        if (p) showToast(`Added: ${p.name}`);
        return;
    }

    const card = e.target.closest("[data-open]");
    if (!card) return;
    const id = card.getAttribute("data-open");
    const p = products.find(x => x.id === id);
    if (p) openModal(p);
    });

    shopGrid.addEventListener("keydown", (e) => {
    if (e.key !== "Enter" && e.key !== " ") return;
    const card = e.target.closest("[data-open]");
    if (!card) return;
    e.preventDefault();
    const id = card.getAttribute("data-open");
    const p = products.find(x => x.id === id);
    if (p) openModal(p);
    });
  }

  if (shopGrid) renderShop();
})();
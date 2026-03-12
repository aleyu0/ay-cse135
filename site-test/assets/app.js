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
    window.dispatchEvent(new CustomEvent("ae_theme", { detail: { theme } })); // collector 
    const btn = document.querySelector("[data-theme-toggle]");
    if (btn) btn.setAttribute("aria-label", theme === "dark" ? "Switch to light theme" : "Switch to dark theme");
    const themeImg = document.querySelector("[data-theme-toggle] .themeIcon");
    if (themeImg) {
      themeImg.src = theme === "dark"
        ? "assets/icons/light-mode.svg"
        : "assets/icons/dark-mode.svg";
    }
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

  /* ---------- Cart ---------- */
  const CART_KEY = "aae_cart";

  function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
    catch { return []; }
  }

  function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartBadge();
  }

  function addToCart(product) {
    const cart = getCart();
    const existing = cart.find(i => i.id === product.id);
    if (existing) {
      existing.qty++;
    } else {
      cart.push({ id: product.id, name: product.name, price: product.price, qty: 1 });
    }
    saveCart(cart);
    showToast(`Added: ${product.name}`);

    // collector event
    window.dispatchEvent(new CustomEvent("ae_cart", {
      detail: { action: "add_to_cart", productId: product.id, name: product.name, price: product.price }
    }));
  }

  function removeFromCart(productId) {
    let cart = getCart();
    const item = cart.find(i => i.id === productId);
    cart = cart.filter(i => i.id !== productId);
    saveCart(cart);
    if (item) {
      window.dispatchEvent(new CustomEvent("ae_cart", {
        detail: { action: "remove_from_cart", productId: item.id, name: item.name }
      }));
    }
    renderCartPanel();
  }

  function updateCartBadge() {
    const badge = document.querySelector("[data-cart-count]");
    const cart = getCart();
    const count = cart.reduce((sum, i) => sum + i.qty, 0);
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? "inline-flex" : "none";
    }
  }

  function cartTotal() {
    return getCart().reduce((sum, i) => sum + (i.price * i.qty), 0);
  }

  function renderCartPanel() {
    const panel = document.querySelector("[data-cart-panel]");
    if (!panel) return;
    const cart = getCart();

    if (!cart.length) {
      panel.innerHTML = `
        <div class="cart-empty">
          <p>Your cart is empty.</p>
          <a class="btn primary" href="shop.html">Browse shop</a>
        </div>`;
      return;
    }

    panel.innerHTML = `
      <div class="cart-items">
        ${cart.map(i => `
          <div class="cart-item">
            <div class="cart-item-info">
              <span class="cart-item-name">${escapeHtml(i.name)}</span>
              <span class="cart-item-meta">$${(i.price).toFixed(2)} × ${i.qty}</span>
            </div>
            <button class="btn cart-remove" data-remove="${escapeHtml(i.id)}">Remove</button>
          </div>
        `).join("")}
      </div>
      <div class="cart-footer">
        <div class="cart-total">
          <span>Total</span>
          <span class="price">$${cartTotal().toFixed(2)}</span>
        </div>
        <div class="cart-checkout-row" style="flex-direction:column;">
          <input type="text" id="checkout-name" placeholder="Your name" class="cart-pid-input" />
          <input type="email" id="checkout-email" placeholder="Email address" class="cart-pid-input" />
          <input type="text" id="checkout-pid" placeholder="Your PID (e.g. A12345678)" class="cart-pid-input" />
          <button class="btn primary" id="checkout-btn" style="width:100%;">Checkout</button>
        </div>
        <div id="cart-feedback" class="form-feedback" style="display:none;"></div>
      </div>`;

    // remove handlers
    panel.querySelectorAll("[data-remove]").forEach(btn => {
      btn.addEventListener("click", () => removeFromCart(btn.dataset.remove));
    });

    // checkout handler
    const checkoutBtn = document.getElementById("checkout-btn");
    if (checkoutBtn) {
      checkoutBtn.addEventListener("click", handleCheckout);
    }
  }

  async function handleCheckout() {
  const feedback = document.getElementById("cart-feedback");
  const nameInput = document.getElementById("checkout-name");
  const emailInput = document.getElementById("checkout-email");
  const pidInput = document.getElementById("checkout-pid");
  const name = nameInput?.value.trim();
  const email = emailInput?.value.trim();
  const pid = pidInput?.value.trim();

  if (!name) {
    feedback.textContent = "Please enter your name.";
    feedback.className = "form-feedback error";
    feedback.style.display = "block";
    return;
  }
  if (!email || !email.includes("@")) {
    feedback.textContent = "Please enter a valid email.";
    feedback.className = "form-feedback error";
    feedback.style.display = "block";
    return;
  }
  if (!pid) {
    feedback.textContent = "Please enter your PID.";
    feedback.className = "form-feedback error";
    feedback.style.display = "block";
    return;
  }

  const cart = getCart();
  if (!cart.length) return;

  const checkoutBtn = document.getElementById("checkout-btn");
  checkoutBtn.disabled = true;
  checkoutBtn.textContent = "Processing…";

  window.dispatchEvent(new CustomEvent("ae_cart", {
    detail: { action: "begin_checkout", pid, items: cart, total: cartTotal() }
  }));

  try {
    const r = await fetch("api/checkout.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        session_id: localStorage.getItem("cse135_session_id") || null,
        name,
        email,
        pid,
        items: cart,
        total: cartTotal()
      }),
    });
    const res = await r.json();

    if (res.ok) {
      window.dispatchEvent(new CustomEvent("ae_cart", {
        detail: { action: "checkout_complete", orderId: res.id, pid, total: cartTotal() }
      }));
      saveCart([]);
      feedback.textContent = "Order placed! Order #" + res.id;
      feedback.className = "form-feedback success";
      feedback.style.display = "block";
      setTimeout(() => renderCartPanel(), 1500);
    } else {
      feedback.textContent = res.error || "Checkout failed.";
      feedback.className = "form-feedback error";
      feedback.style.display = "block";
    }
  } catch {
    feedback.textContent = "Network error. Please try again.";
    feedback.className = "form-feedback error";
    feedback.style.display = "block";
  } finally {
    checkoutBtn.disabled = false;
    checkoutBtn.textContent = "Checkout";
  }
}

  // Cart panel toggle
  const cartBtn = document.querySelector("[data-cart]");
  if (cartBtn) {
    cartBtn.addEventListener("click", () => {
      const cartOverlay = document.querySelector("[data-cart-overlay]");
      if (!cartOverlay) return;
      cartOverlay.classList.toggle("open");
      if (cartOverlay.classList.contains("open")) renderCartPanel();
    });
    document.addEventListener("click", (e) => {
      if (e.target.matches("[data-cart-overlay]")) {
        e.target.classList.remove("open");
      }
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        const co = document.querySelector("[data-cart-overlay]");
        if (co) co.classList.remove("open");
      }
    });
  }

  updateCartBadge();

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
      addToCart(modalCurrent);
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
        if (p) addToCart(p);
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

  /* ---------- Contact form ---------- */
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const feedback = document.getElementById('form-feedback');
      const submitBtn = contactForm.querySelector('button[type="submit"]');
      
      // Gather data
      const body = {
        name: contactForm.querySelector('#name').value.trim(),
        email: contactForm.querySelector('#email').value.trim(),
        item: contactForm.querySelector('#item').value.trim(),
        priority: contactForm.querySelector('#priority').value,
        justification: contactForm.querySelector('#justification').value.trim(),
      };

      // Client-side check
      if (!body.name || !body.email || !body.item || !body.priority || !body.justification) {
        feedback.textContent = 'Please fill in all fields.';
        feedback.className = 'form-feedback error';
        feedback.style.display = 'block';
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting…';

      try {
        const r = await fetch('api/contact.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });
        const res = await r.json();

        if (res.ok) {
          feedback.textContent = 'Request submitted! We\'ll review it soon.';
          feedback.className = 'form-feedback success';
          feedback.style.display = 'block';
          contactForm.reset();
        } else {
          const msg = res.errors ? res.errors.join(', ') : (res.error || 'Submission failed.');
          feedback.textContent = msg;
          feedback.className = 'form-feedback error';
          feedback.style.display = 'block';
        }
      } catch (err) {
        feedback.textContent = 'Network error. Please try again.';
        feedback.className = 'form-feedback error';
        feedback.style.display = 'block';
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit';
      }
    });
  }

  // Cart slide-out panel
  const cartHTML = document.createElement("div");
  cartHTML.className = "cartOverlay";
  cartHTML.setAttribute("data-cart-overlay", "");
  cartHTML.setAttribute("aria-hidden", "true");
  cartHTML.innerHTML = `
    <div class="cartDrawer">
      <div class="cartDrawerHeader">
        <h2>Your Cart</h2>
        <button class="btn" data-cart-close>Close</button>
      </div>
      <div data-cart-panel></div>
    </div>
  `;
  document.body.appendChild(cartHTML);

  // close button
  const closeBtn = cartHTML.querySelector("[data-cart-close]");
  if (closeBtn) {
    closeBtn.addEventListener("click", () => cartHTML.classList.remove("open"));
  }
})();
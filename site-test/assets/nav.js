(function() {
    const currentPage = location.pathname.split('/').pop() || 'index.html';

    function isActive(page) {
        return currentPage === page ? 'active' : '';
    }

    const headerHTML = `
        <div class="container nav">
        <a class="brand" href="index.html">The Absolute Essential</a>

        <button class="menuBtn" data-menu aria-label="Open menu">Menu</button>

        <nav class="navlinks" data-navlinks>
            <a class="${isActive('index.html')}" href="index.html">Home</a>
            <a class="${isActive('shop.html')}" href="shop.html">Shop</a>
            <a class="${isActive('contact.html')}" href="contact.html">Request</a>
        </nav>

        <div class="navRight">
            <button class="iconBtn" type="button" data-theme-toggle aria-label="Switch theme" title="Toggle theme">
            <img class="themeIcon" src="assets/icons/dark-mode.svg" alt="" width="18" height="18" />
            </button>

            <button class="iconBtn" type="button" data-cart aria-label="Cart" title="Cart">
            <img src="assets/icons/cart.svg" alt="" width="18" height="18" />
            </button>
        </div>
        </div>
    `;

    const header = document.querySelector('header');
    if (header) header.innerHTML = headerHTML;

    const footer = document.querySelector('footer');
    if (footer) {
    footer.innerHTML = `
        <div class="container">
        <div>&copy; The Absolute Essential</div>
        </div>
    `;
    }
})();


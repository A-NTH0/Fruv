const form = document.getElementById("loginForm");
const loginBtn = document.querySelector(".login-btn");

if (form && loginBtn) {
    form.addEventListener("submit", () => {
        loginBtn.textContent = loginBtn.dataset.loadingLabel || "Procesando...";
        loginBtn.disabled = true;
        loginBtn.setAttribute("aria-busy", "true");
    });
}

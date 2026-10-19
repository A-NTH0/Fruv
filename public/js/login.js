const form = document.getElementById("loginForm");
const loginBtn = document.querySelector(".login-btn");
const googleBtn = document.querySelector(".google-btn");

if (form && loginBtn) {
    form.addEventListener("submit", (e) => {
        e.preventDefault();

        loginBtn.textContent = "Logging In...";
        loginBtn.disabled = true;
        setTimeout(() => {
            loginBtn.textContent = "Login";
            loginBtn.disabled = false;
            alert("Login Successful");
        }, 1200);
    });
}

if (googleBtn) {
    googleBtn.addEventListener("click", () => {
        window.open("https://accounts.google.com/", "_blank", "noopener,noreferrer");
    });
}

(function () {
  const onReady = (cb) => {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", cb, { once: true });
    } else cb();
  };

  onReady(() => {
    const loader = document.querySelector(".page-loader");
    const loaderText = loader ? loader.querySelector(".loader-text") : null;
    const defaultLoaderMsg = loaderText ? loaderText.textContent : "";

    const form = document.querySelector("#login-form");
    const submitButton = form ? form.querySelector(".btn-submit") : null;
    const submitIcon = submitButton ? submitButton.querySelector(".btn-icon i") : null;
    const submitLabel = submitButton ? submitButton.querySelector(".btn-label") : null;

    const pwdToggle = document.querySelector(".password-toggle");
    const pwdInput  = document.querySelector("#password");

    const showLoader = (message) => {
      if (!loader) return;
      if (message && loaderText) loaderText.textContent = message;
      loader.classList.remove("is-hidden");
    };
    const hideLoader = () => {
      if (!loader || loader.classList.contains("is-hidden")) return;
      loader.classList.add("is-hidden");
      if (loaderText) loaderText.textContent = defaultLoaderMsg || "Cargando portal";
    };

    // Loader al terminar de cargar la página (y fallback)
    if (loader) {
      window.addEventListener("load", () => {
        window.setTimeout(hideLoader, 420);
      }, { once: true });
      window.setTimeout(hideLoader, 2800);
    }

    // Toggle de contraseña
    if (pwdToggle && pwdInput) {
      pwdToggle.addEventListener("click", () => {
        const visible = pwdToggle.getAttribute("data-visible") === "true";
        pwdToggle.setAttribute("data-visible", String(!visible));
        pwdInput.type = visible ? "password" : "text";

        const icon = pwdToggle.querySelector(".material-symbols-outlined");
        if (icon) icon.textContent = visible ? "visibility" : "visibility_off";

        pwdToggle.setAttribute("aria-label", visible ? "Mostrar contraseña" : "Ocultar contraseña");
      });
    }

    // Ripple en botón
    if (submitButton) {
      const ripple = submitButton.querySelector(".btn-ripple");
      const triggerRipple = (evt) => {
        if (!ripple) return;
        let x = 50, y = 50;

        if (typeof evt.clientX === "number" && typeof evt.clientY === "number") {
          const rect = submitButton.getBoundingClientRect();
          x = ((evt.clientX - rect.left) / rect.width) * 100;
          y = ((evt.clientY - rect.top) / rect.height) * 100;
        }

        submitButton.style.setProperty("--ripple-x", `${x}%`);
        submitButton.style.setProperty("--ripple-y", `${y}%`);
        submitButton.classList.add("is-pressed");

        ripple.style.transition = "none";
        void ripple.offsetWidth; // reflow
        ripple.style.transition = "";

        window.setTimeout(() => submitButton.classList.remove("is-pressed"), 320);
      };

      submitButton.addEventListener("pointerdown", triggerRipple);
      submitButton.addEventListener("keydown", (e) => {
        if (e.code === "Space" || e.code === "Enter") triggerRipple(e);
      });
    }

    // Submit: bloquear, cambiar icono, mostrar loader
    if (form && submitButton) {
      form.addEventListener("submit", () => {
        showLoader("Validando credenciales...");
        submitButton.classList.add("is-loading");
        submitButton.disabled = true;

        if (submitIcon) {
          submitIcon.dataset.iconDefault = submitIcon.dataset.iconDefault || submitIcon.className;
          submitIcon.className = "fas fa-spinner fa-spin";
        }
        if (submitLabel) submitLabel.textContent = "Validando acceso...";
      });
    }

    // Si volvemos desde bfcache (navegador atrás), restaurar estado
    window.addEventListener("pageshow", (evt) => {
      if (!evt.persisted) return;
      hideLoader();
      if (submitButton) {
        submitButton.classList.remove("is-loading");
        submitButton.disabled = false;
      }
      if (submitIcon && submitIcon.dataset.iconDefault) {
        submitIcon.className = submitIcon.dataset.iconDefault;
      }
      if (submitLabel) submitLabel.textContent = "Acceder";
    }, { once: true });
  });
})();

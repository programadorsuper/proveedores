// assets/js/citas.js
(function () {
  const root = document.getElementById("appointments-index-app");
  if (!root) return;

  let config = {};
  try {
    config = JSON.parse(root.dataset.config || "{}");
  } catch (e) {
    console.error("Config citas.js inválida", e);
    return;
  }

  const createUrl = config.createUrl || null;
  const newBtn = root.querySelector("[data-new-appointment]");
  const modalEl = document.getElementById("newAppointmentModal");
  const form = document.getElementById("newAppointmentForm");

  if (!newBtn || !modalEl || !form || !createUrl) {
    console.warn("citas.js: faltan elementos o createUrl");
    return;
  }

  const modal = new bootstrap.Modal(modalEl);

  newBtn.addEventListener("click", function () {
    modal.show();
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(form);

    fetch(createUrl, {
      method: "POST",
      body: formData,
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.success) {
          alert(data.message || "No se pudo crear la cita.");
          return;
        }
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        } else {
          window.location.reload();
        }
      })
      .catch((err) => {
        console.error(err);
        alert("Error al crear la cita.");
      });
  });
})();

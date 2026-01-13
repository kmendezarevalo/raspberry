document.addEventListener("DOMContentLoaded", () => {
  const syncForm = document.getElementById("syncForm");
  const logContent = document.getElementById("logContent");
  const btnSync = document.getElementById("btnSync");
  const collapsibleTrigger = document.querySelector(".collapsible-trigger");
  const collapsibleContent = document.querySelector(".collapsible-content");

  // UI: Collapsible
  let isUnlocked = false;
  collapsibleTrigger.addEventListener("click", async () => {
    const isOpen = collapsibleContent.style.display === "block";

    if (!isOpen && !isUnlocked) {
            const { value: password } = await Swal.fire({
                title: 'Acceso Protegido',
                input: 'password',
                inputLabel: 'Ingrese la contraseña para opciones avanzadas',
                inputPlaceholder: 'Contraseña',
                showCancelButton: true,
                background: 'transparent',
                color: '#fff',
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: 'rgba(255,255,255,0.1)',
                customClass: {
                    popup: 'glass-popup',
                    title: 'glass-title',
                    input: 'glass-input'
                }
            });

      if (password === "$upertex#123") {
        isUnlocked = true;
        addLog("Opciones avanzadas desbloqueadas", "success");
      } else if (password !== undefined) {
        Swal.fire({
          icon: "error",
          title: "Contraseña incorrecta",
          text: "No tienes permiso para acceder a esta sección.",
          background: "#1e1b4b",
          color: "#fff",
        });
        return;
      } else {
        return; // Cancelled
      }
    }

    const nowOpen = !isOpen;
    collapsibleContent.style.display = nowOpen ? "block" : "none";
    collapsibleTrigger.textContent = nowOpen
      ? "Ocultar Opciones Avanzadas"
      : "Opciones Avanzadas (Protegido)";
  });

  // Logger util
  function addLog(message, type = "info") {
    const entry = document.createElement("div");
    entry.className = `log-entry ${type}`;
    const time = new Date().toLocaleTimeString();
    entry.textContent = `[${time}] ${message}`;
    logContent.prepend(entry);
  }

  // Clear logs
  document.getElementById("clearLogs").addEventListener("click", () => {
    logContent.innerHTML = "Esperando actividad...";
  });

  // Form submission
  syncForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const data = {
      ip: document.getElementById("ip").value,
      celda: document.getElementById("celda").value,
      password: "raspberry",
      glpi: document.getElementById("glpi").checked,
      hostname: document.getElementById("hostname").value,
      mac: document.getElementById("mac").value,
    };

    // Loading state
    btnSync.disabled = true;
    btnSync.classList.add("loading");
    btnSync.querySelector(".btn-text").textContent = "PROCESANDO...";
    logContent.innerHTML = "";
    addLog(`Iniciando sincronización con ${data.ip}...`);

    try {
      const response = await fetch("api/sync", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ip: data.ip,
          mfgLine: data.celda,
          password: data.password,
          installGlpi: data.glpi,
          newHostname: data.hostname,
          macFilter: data.mac,
        }),
      });

      const text = await response.text();
      let result;
      try {
        result = JSON.parse(text);
      } catch (e) {
        // Si no es JSON, mostrar los primeros 200 caracteres de la respuesta (error de servidor)
        throw new Error(
          "Respuesta inválida del servidor (posible tiempo de espera agotado). Detalle: " +
            text.substring(0, 200)
        );
      }

      if (result.success) {
        result.messages.forEach((msg) => addLog(msg, "success"));
        Swal.fire({
          icon: "success",
          title: "¡Sincronización Exitosa!",
          text: "Se han completado todas las tareas.",
          background: "#1e1b4b",
          color: "#fff",
          confirmButtonColor: "#4f46e5",
        });
      } else {
        addLog(`ERROR: ${result.error}`, "error");
        Swal.fire({
          icon: "error",
          title: "Error en la operación",
          text: result.error,
          background: "#1e1b4b",
          color: "#fff",
        });
      }
    } catch (error) {
      addLog(`ERROR CRITICO: ${error.message}`, "error");
    } finally {
      btnSync.disabled = false;
      btnSync.classList.remove("loading");
      btnSync.querySelector(".btn-text").textContent = "SINCRONIZAR";
    }
  });
});

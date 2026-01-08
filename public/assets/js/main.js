document.addEventListener('DOMContentLoaded', () => {
    const syncForm = document.getElementById('syncForm');
    const logContent = document.getElementById('logContent');
    const btnSync = document.getElementById('btnSync');
    const collapsibleTrigger = document.querySelector('.collapsible-trigger');
    const collapsibleContent = document.querySelector('.collapsible-content');

    // UI: Collapsible
    collapsibleTrigger.addEventListener('click', () => {
        const isOpen = collapsibleContent.style.display === 'block';
        collapsibleContent.style.display = isOpen ? 'none' : 'block';
        collapsibleTrigger.textContent = isOpen ? 'Opciones de Hostname (Opcional)' : 'Ocultar opciones avanzada';
    });

    // Logger util
    function addLog(message, type = 'info') {
        const entry = document.createElement('div');
        entry.className = `log-entry ${type}`;
        const time = new Date().toLocaleTimeString();
        entry.textContent = `[${time}] ${message}`;
        logContent.prepend(entry);
    }

    // Clear logs
    document.getElementById('clearLogs').addEventListener('click', () => {
        logContent.innerHTML = 'Esperando actividad...';
    });

    // Form submission
    syncForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const data = {
            ip: document.getElementById('ip').value,
            celda: document.getElementById('celda').value,
            password: 'raspberry',
            glpi: document.getElementById('glpi').checked,
            hostname: document.getElementById('hostname').value,
            mac: document.getElementById('mac').value
        };

        // Loading state
        btnSync.disabled = true;
        btnSync.classList.add('loading');
        btnSync.querySelector('.btn-text').textContent = 'PROCESANDO...';
        logContent.innerHTML = '';
        addLog(`Iniciando sincronización con ${data.ip}...`);

        try {
            const response = await fetch('api/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ip: data.ip,
                    mfgLine: data.celda,
                    password: data.password,
                    installGlpi: data.glpi,
                    newHostname: data.hostname,
                    macFilter: data.mac
                })
            });

            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                // Si no es JSON, mostrar los primeros 200 caracteres de la respuesta (error de servidor)
                throw new Error("Respuesta inválida del servidor (posible tiempo de espera agotado). Detalle: " + text.substring(0, 200));
            }

            if (result.success) {
                result.messages.forEach(msg => addLog(msg, 'success'));
                Swal.fire({
                    icon: 'success',
                    title: '¡Sincronización Exitosa!',
                    text: 'Se han completado todas las tareas.',
                    background: '#1e1b4b',
                    color: '#fff',
                    confirmButtonColor: '#4f46e5'
                });
            } else {
                addLog(`ERROR: ${result.error}`, 'error');
                Swal.fire({
                    icon: 'error',
                    title: 'Error en la operación',
                    text: result.error,
                    background: '#1e1b4b',
                    color: '#fff'
                });
            }
        } catch (error) {
            addLog(`ERROR CRITICO: ${error.message}`, 'error');
        } finally {
            btnSync.disabled = false;
            btnSync.classList.remove('loading');
            btnSync.querySelector('.btn-text').textContent = 'SINCRONIZAR';
        }
    });
});

const { contextBridge, ipcRenderer } = require('electron');

function normalizeError(error, fallbackMessage) {
    return {
        ok: false,
        message: error instanceof Error ? error.message : fallbackMessage
    };
}

contextBridge.exposeInMainWorld('P360Desktop', {
    app: {
        async getInfo() {
            try {
                const result = await ipcRenderer.invoke('p360:app:get-info');
                return result;
            } catch (error) {
                return normalizeError(error, 'No se pudo obtener la informacion de la aplicacion.');
            }
        }
    },

    env: {
        isDesktop: true,
        platform: process.platform
    },

    storage: {
        async setConfig(payload) {
            try {
                return await ipcRenderer.invoke('p360:storage:set-config', payload);
            } catch (error) {
                return normalizeError(error, 'No se pudo guardar la configuracion local.');
            }
        },

        async getConfig() {
            try {
                return await ipcRenderer.invoke('p360:storage:get-config');
            } catch (error) {
                return normalizeError(error, 'No se pudo leer la configuracion local.');
            }
        },

        async clearConfig() {
            try {
                return await ipcRenderer.invoke('p360:storage:clear-config');
            } catch (error) {
                return normalizeError(error, 'No se pudo limpiar la configuracion local.');
            }
        }
    },

    session: {
        async reloadView() {
            try {
                return await ipcRenderer.invoke('p360:app:reload-session-view');
            } catch (error) {
                return normalizeError(error, 'No se pudo recargar la vista de sesion.');
            }
        }
    },

    navigation: {
        goToDashboard() {
            window.location.href = './index.html';
        },

        goToLogin() {
            window.location.href = './login.html';
        }
    }
});
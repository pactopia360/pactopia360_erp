const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('P360Desktop', {
    app: {
        name: 'PACTOPIA360 SAT Desktop',
        version: '0.1.0',
        platform: process.platform
    },

    env: {
        isDesktop: true
    },

    storage: {
        async setConfig(payload) {
            try {
                return await ipcRenderer.invoke('p360:storage:set-config', payload);
            } catch (error) {
                return {
                    ok: false,
                    message: error instanceof Error ? error.message : 'No se pudo guardar la configuración local.'
                };
            }
        },

        async getConfig() {
            try {
                return await ipcRenderer.invoke('p360:storage:get-config');
            } catch (error) {
                return {
                    ok: false,
                    message: error instanceof Error ? error.message : 'No se pudo leer la configuración local.'
                };
            }
        },

        async clearConfig() {
            try {
                return await ipcRenderer.invoke('p360:storage:clear-config');
            } catch (error) {
                return {
                    ok: false,
                    message: error instanceof Error ? error.message : 'No se pudo limpiar la configuración local.'
                };
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
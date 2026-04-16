const { app, BrowserWindow, shell, ipcMain } = require('electron');
const path = require('path');
const Store = require('electron-store').default;

const isDev = !app.isPackaged;

const store = new Store({
    name: 'p360-sat-desktop',
    clearInvalidConfig: true,
    defaults: {
        session: {
            email: '',
            erpUrl: '',
            isLoggedIn: false
        }
    }
});

let mainWindow = null;
let isQuitting = false;

app.setName('PACTOPIA360 SAT Desktop');

const gotSingleInstanceLock = app.requestSingleInstanceLock();

if (!gotSingleInstanceLock) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (!mainWindow) {
            createMainWindow();
            return;
        }

        if (mainWindow.isMinimized()) {
            mainWindow.restore();
        }

        mainWindow.show();
        mainWindow.focus();
    });
}

function getRendererPath(fileName) {
    return path.join(__dirname, 'renderer', fileName);
}

function getSavedSession() {
    const session = store.get('session', {});

    return {
        email: String(session.email || '').trim(),
        erpUrl: String(session.erpUrl || '').trim().replace(/\/+$/, ''),
        isLoggedIn: Boolean(session.isLoggedIn)
    };
}

function getInitialView() {
    const session = getSavedSession();

    if (session.isLoggedIn && session.erpUrl) {
        return {
            type: 'remote',
            url: session.erpUrl + '/cliente/sat'
        };
    }

    return {
        type: 'local',
        file: 'login.html'
    };
}

function isHttpUrl(url) {
    return /^https?:\/\//i.test(String(url || ''));
}

function normalizeOrigin(url) {
    try {
        return new URL(url).origin;
    } catch (_error) {
        return '';
    }
}

function isAllowedInternalUrl(url) {
    if (!isHttpUrl(url)) {
        return false;
    }

    const session = getSavedSession();

    if (!session.erpUrl) {
        return false;
    }

    return normalizeOrigin(url) === normalizeOrigin(session.erpUrl);
}

function openExternalSafely(url) {
    if (!isHttpUrl(url)) {
        return;
    }

    shell.openExternal(url).catch((error) => {
        console.error('No se pudo abrir URL externa:', {
            url,
            message: error?.message || error
        });
    });
}

function loadView(view) {
    if (!mainWindow || !view) {
        return;
    }

    if (view.type === 'remote' && view.url) {
        mainWindow.loadURL(view.url).catch((error) => {
            console.error('Error al cargar vista remota:', {
                url: view.url,
                message: error?.message || error
            });

            if (mainWindow) {
                mainWindow.loadFile(getRendererPath('login.html')).catch((fallbackError) => {
                    console.error('Error al cargar vista local de respaldo:', {
                        message: fallbackError?.message || fallbackError
                    });
                });
            }
        });

        return;
    }

    if (view.type === 'local' && view.file) {
        mainWindow.loadFile(getRendererPath(view.file)).catch((error) => {
            console.error('Error al cargar vista local:', {
                file: view.file,
                message: error?.message || error
            });
        });
    }
}

function handleNavigationInsideDesktop(targetUrl) {
    if (!mainWindow || !targetUrl) {
        return;
    }

    if (isAllowedInternalUrl(targetUrl)) {
        const currentUrl = mainWindow.webContents.getURL();

        if (currentUrl !== targetUrl) {
            mainWindow.loadURL(targetUrl).catch((error) => {
                console.error('Error al navegar dentro de la app:', {
                    targetUrl,
                    message: error?.message || error
                });
            });
        }

        return;
    }

    openExternalSafely(targetUrl);
}

function createMainWindow() {
    if (mainWindow && !mainWindow.isDestroyed()) {
        return mainWindow;
    }

    mainWindow = new BrowserWindow({
        width: 1366,
        height: 860,
        minWidth: 1200,
        minHeight: 760,
        backgroundColor: '#0b1220',
        autoHideMenuBar: true,
        show: false,
        title: 'PACTOPIA360 SAT Desktop',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
            devTools: isDev
        }
    });

    loadView(getInitialView());

    mainWindow.once('ready-to-show', () => {
        if (!mainWindow || mainWindow.isDestroyed()) {
            return;
        }

        mainWindow.show();
        mainWindow.focus();
    });

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (isAllowedInternalUrl(url)) {
            handleNavigationInsideDesktop(url);
            return { action: 'deny' };
        }

        if (isHttpUrl(url)) {
            openExternalSafely(url);
        }

        return { action: 'deny' };
    });

    mainWindow.webContents.on('will-navigate', (event, url) => {
        if (isAllowedInternalUrl(url)) {
            return;
        }

        if (isHttpUrl(url)) {
            event.preventDefault();
            openExternalSafely(url);
        }
    });

    mainWindow.webContents.on('did-fail-load', (_event, errorCode, errorDescription, validatedURL) => {
        console.error('Error al cargar URL:', {
            errorCode,
            errorDescription,
            validatedURL
        });
    });

    mainWindow.on('close', () => {
        if (isQuitting) {
            return;
        }
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    return mainWindow;
}

ipcMain.handle('p360:storage:set-config', async (_event, payload = {}) => {
    const safePayload = {
        email: String(payload.email || '').trim(),
        erpUrl: String(payload.erpUrl || '').trim().replace(/\/+$/, ''),
        isLoggedIn: Boolean(payload.isLoggedIn)
    };

    store.set('session', safePayload);

    return {
        ok: true,
        data: safePayload
    };
});

ipcMain.handle('p360:storage:get-config', async () => {
    return {
        ok: true,
        data: getSavedSession()
    };
});

ipcMain.handle('p360:storage:clear-config', async () => {
    store.set('session', {
        email: '',
        erpUrl: '',
        isLoggedIn: false
    });

    return {
        ok: true
    };
});

ipcMain.handle('p360:app:reload-session-view', async () => {
    if (!mainWindow || mainWindow.isDestroyed()) {
        return { ok: false };
    }

    loadView(getInitialView());

    return { ok: true };
});

app.whenReady().then(() => {
    createMainWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createMainWindow();
            return;
        }

        if (mainWindow) {
            mainWindow.show();
            mainWindow.focus();
        }
    });
});

app.on('before-quit', () => {
    isQuitting = true;
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('web-contents-created', (_event, contents) => {
    contents.on('before-input-event', (event, input) => {
        const key = String(input.key || '').toLowerCase();

        const isReload =
            key === 'f5' ||
            (input.control && key === 'r');

        const isDevTools =
            key === 'f12' ||
            ((input.control || input.meta) && input.shift && (key === 'i' || key === 'j' || key === 'c'));

        if (isReload || isDevTools) {
            event.preventDefault();
        }
    });
});

process.on('uncaughtException', (error) => {
    console.error('uncaughtException:', error);
});

process.on('unhandledRejection', (reason) => {
    console.error('unhandledRejection:', reason);
});
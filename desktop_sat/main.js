const { app, BrowserWindow, shell, ipcMain } = require('electron');
const path = require('path');
const Store = require('electron-store').default;

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
    } catch (error) {
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

function handleNavigationInsideDesktop(targetUrl) {
    if (!mainWindow || !targetUrl) {
        return;
    }

    if (isAllowedInternalUrl(targetUrl)) {
        if (mainWindow.webContents.getURL() !== targetUrl) {
            mainWindow.loadURL(targetUrl);
        }
        return;
    }

    if (isHttpUrl(targetUrl)) {
        shell.openExternal(targetUrl);
    }
}

function createMainWindow() {
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
            devTools: true
        }
    });

    const view = getInitialView();

    if (view.type === 'remote') {
        mainWindow.loadURL(view.url);
    } else {
        mainWindow.loadFile(getRendererPath(view.file));
    }

    mainWindow.once('ready-to-show', () => {
        if (!mainWindow) {
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
            shell.openExternal(url);
        }

        return { action: 'deny' };
    });

    mainWindow.webContents.on('will-navigate', (event, url) => {
        if (isAllowedInternalUrl(url)) {
            return;
        }

        if (isHttpUrl(url)) {
            event.preventDefault();
            shell.openExternal(url);
        }
    });

    mainWindow.webContents.on('did-fail-load', (_event, errorCode, errorDescription, validatedURL) => {
        if (!mainWindow) {
            return;
        }

        console.error('Error al cargar URL:', {
            errorCode,
            errorDescription,
            validatedURL
        });
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
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

app.whenReady().then(() => {
    createMainWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createMainWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('web-contents-created', (_event, contents) => {
    contents.on('before-input-event', (event, input) => {
        const key = (input.key || '').toLowerCase();

        const isReload =
            key === 'f5' ||
            (input.control && key === 'r');

        if (isReload) {
            event.preventDefault();
        }
    });
});
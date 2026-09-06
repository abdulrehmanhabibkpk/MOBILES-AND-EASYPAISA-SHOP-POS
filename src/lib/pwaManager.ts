/**
 * PWA Service Worker & Offline Capability Manager
 * Enables full offline loading, offline caching, and PWA installation on Mobile & Desktop.
 */

import { registerSW } from 'virtual:pwa-register';

export interface PWAState {
  isOnline: boolean;
  isInstallable: boolean;
  isInstalled: boolean;
  needRefresh: boolean;
  offlineReady: boolean;
}

type PWAStateListener = (state: PWAState) => void;

let deferredInstallPrompt: any = null;
const listeners = new Set<PWAStateListener>();

let currentState: PWAState = {
  isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
  isInstallable: false,
  isInstalled: false,
  needRefresh: false,
  offlineReady: false,
};

function emitState() {
  listeners.forEach((fn) => fn({ ...currentState }));
}

export function onPWAStateChange(listener: PWAStateListener): () => void {
  listeners.add(listener);
  listener({ ...currentState });
  return () => listeners.delete(listener);
}

let updateSW: ((reloadPage?: boolean) => Promise<void>) | null = null;

export function initPWAManager(): void {
  if (typeof window === 'undefined') return;

  // 1. Detect if app is already running standalone / installed
  const isStandalone = 
    window.matchMedia('(display-mode: standalone)').matches ||
    (window.navigator as any).standalone === true ||
    document.referrer.includes('android-app://');
  
  currentState.isInstalled = isStandalone;

  // 2. Online / Offline listeners
  window.addEventListener('online', () => {
    currentState.isOnline = true;
    emitState();
  });

  window.addEventListener('offline', () => {
    currentState.isOnline = false;
    emitState();
  });

  // 3. PWA Install Prompt Listener
  window.addEventListener('beforeinstallprompt', (e: any) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    currentState.isInstallable = true;
    emitState();
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    currentState.isInstallable = false;
    currentState.isInstalled = true;
    emitState();
  });

  // 4. Register Service Worker via vite-plugin-pwa
  try {
    updateSW = registerSW({
      immediate: true,
      onNeedRefresh() {
        currentState.needRefresh = true;
        emitState();
      },
      onOfflineReady() {
        currentState.offlineReady = true;
        emitState();
      },
    });
  } catch (err) {
    console.warn('PWA SW register error:', err);
  }
}

/**
 * Triggers native browser app installation dialog (Chromium, Android, Edge, Desktop Chrome)
 */
export async function promptPWAInstall(): Promise<{ success: boolean; outcome?: string }> {
  if (!deferredInstallPrompt) {
    return { success: false, outcome: 'no_prompt' };
  }

  try {
    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    currentState.isInstallable = false;
    if (outcome === 'accepted') {
      currentState.isInstalled = true;
    }
    emitState();
    return { success: true, outcome };
  } catch (err) {
    console.error('Install prompt error:', err);
    return { success: false, outcome: 'error' };
  }
}

/**
 * Reloads page with latest updated service worker
 */
export async function applyPWAUpdate(): Promise<void> {
  if (updateSW) {
    await updateSW(true);
  } else {
    window.location.reload();
  }
}

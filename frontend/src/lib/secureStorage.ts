// Token storage abstraction.
//
// - On native (Capacitor), uses the Preferences plugin (backed by the OS
//   keychain / encrypted prefs) for secure token storage.
// - On the web, falls back to localStorage.
//
// Written without a static `@capacitor/*` import so the web bundle builds
// even when the native deps aren't installed: the plugin is reached via the
// runtime global `Capacitor.Plugins.Preferences` that Capacitor injects on
// device.

interface PreferencesPlugin {
  get(options: { key: string }): Promise<{ value: string | null }>;
  set(options: { key: string; value: string }): Promise<void>;
  remove(options: { key: string }): Promise<void>;
}

function nativePreferences(): PreferencesPlugin | null {
  const cap = (globalThis as unknown as { Capacitor?: { isNativePlatform?: () => boolean; Plugins?: Record<string, unknown> } }).Capacitor;
  if (cap?.isNativePlatform?.() && cap.Plugins?.Preferences) {
    return cap.Plugins.Preferences as PreferencesPlugin;
  }
  return null;
}

/** Async storage compatible with zustand's `createJSONStorage`. */
export const secureStorage = {
  async getItem(key: string): Promise<string | null> {
    const prefs = nativePreferences();
    if (prefs) return (await prefs.get({ key })).value;
    return localStorage.getItem(key);
  },
  async setItem(key: string, value: string): Promise<void> {
    const prefs = nativePreferences();
    if (prefs) return void prefs.set({ key, value });
    localStorage.setItem(key, value);
  },
  async removeItem(key: string): Promise<void> {
    const prefs = nativePreferences();
    if (prefs) return void prefs.remove({ key });
    localStorage.removeItem(key);
  },
};

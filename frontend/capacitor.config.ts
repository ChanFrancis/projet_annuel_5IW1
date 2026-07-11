import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'local.copot.app',
  appName: 'CoPot',
  webDir: 'dist',
  // For dev with live reload against the running Vite server, uncomment and
  // point to your machine's LAN IP:
  // server: { url: 'http://192.168.1.10:5173', cleartext: true },
  plugins: {
    // Secure storage for the auth token (see src/lib/secureStorage.ts).
    Preferences: {},
  },
};

export default config;

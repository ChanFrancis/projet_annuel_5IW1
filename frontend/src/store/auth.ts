import { create } from 'zustand';
import { persist } from 'zustand/middleware';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

export interface AuthUser {
  id: string;
  email: string;
  roles: string[];
  passwordExpired?: boolean;
}

interface AuthState {
  token: string | null;
  refreshToken: string | null;
  user: AuthUser | null;
  setSession: (token: string, refreshToken: string, user: AuthUser) => void;
  refresh: () => Promise<string | null>;
  logout: () => void;
  isAuthenticated: () => boolean;
  isAdmin: () => boolean;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      refreshToken: null,
      user: null,

      setSession: (token, refreshToken, user) => set({ token, refreshToken, user }),

      refresh: async () => {
        const rt = get().refreshToken;
        if (!rt) return null;
        try {
          const res = await fetch(`${baseURL}/api/auth/token/refresh`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: rt }),
          });
          if (!res.ok) throw new Error('refresh failed');
          const data = await res.json();
          set({ token: data.token, refreshToken: data.refresh_token ?? rt });
          return data.token as string;
        } catch {
          set({ token: null, refreshToken: null, user: null });
          return null;
        }
      },

      logout: () => set({ token: null, refreshToken: null, user: null }),

      isAuthenticated: () => Boolean(get().token),

      isAdmin: () => Boolean(get().user?.roles?.includes('ROLE_ADMIN')),
    }),
    { name: 'copot-auth' },
  ),
);

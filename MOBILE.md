# Application mobile (Capacitor) — CoPot

CoPot est packageable en application **native Android / iOS** via
[Capacitor](https://capacitorjs.com/), à partir du même code React.

## Prérequis (sur ta machine, pas dans Docker)

- **Android** : Android Studio + SDK, un JDK 17
- **iOS** : macOS + Xcode (obligatoire, iOS ne se build que sur Mac)

## 1. Installer les dépendances

Les dépendances Capacitor sont déjà déclarées dans `frontend/package.json` :
`@capacitor/core`, `@capacitor/cli`, `@capacitor/android`, `@capacitor/ios`,
`@capacitor/preferences`.

```bash
cd frontend
pnpm install
```

## 2. Ajouter les plateformes (une seule fois)

```bash
pnpm build                 # génère dist/
npx cap add android
npx cap add ios            # macOS uniquement
```

## 3. Synchroniser après chaque build web

```bash
pnpm build && npx cap sync
```

## 4. Ouvrir / lancer dans l'IDE natif

```bash
npx cap open android       # Android Studio -> Run
npx cap open ios           # Xcode -> Run
```

> Pour cibler le backend depuis l'app native, builde le front avec
> `VITE_API_URL=https://ton-domaine.fr` (le `localhost` ne pointe pas vers ton
> serveur depuis un téléphone).

## Stockage sécurisé du token

L'abstraction [`src/lib/secureStorage.ts`](frontend/src/lib/secureStorage.ts)
utilise le plugin **Preferences** (keychain iOS / prefs chiffrées Android) sur
mobile, et `localStorage` sur le web — sans import statique, donc le bundle web
reste inchangé.

Pour l'activer sur le store d'authentification (`src/store/auth.ts`), remplace
le stockage par défaut de `persist` :

```ts
import { createJSONStorage } from 'zustand/middleware';
import { secureStorage } from '@/lib/secureStorage';

persist(/* ...store... */, {
  name: 'copot-auth',
  storage: createJSONStorage(() => secureStorage),
});
```

> Note : `secureStorage` est **asynchrone**. Sur le web, garde le stockage
> synchrone par défaut si tu veux éviter le ré-hydratation asynchrone ; sur
> mobile, l'async est requis par le plugin Preferences. Gérer l'état
> `useAuthStore.persist.hasHydrated()` pour afficher un écran de chargement le
> temps de l'hydratation.

## Publication sur les stores (facultatif)

- **Google Play** : générer un AAB signé depuis Android Studio (Build > Generate Signed Bundle).
- **App Store** : archiver depuis Xcode (Product > Archive) puis distribuer via App Store Connect.

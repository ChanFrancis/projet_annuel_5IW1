import { FormEvent, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from '@/lib/api';
import { twoFactorApi } from '@/api/resources';
import { useDocumentTitle } from '@/lib/useDocumentTitle';

interface Me {
  email: string;
  totpEnabled: boolean;
}

export function SecuritySettingsPage() {
  useDocumentTitle('Sécurité — CoPot');
  const { data: me, refetch } = useQuery({
    queryKey: ['me'],
    queryFn: () => api.get<Me>('/api/auth/me'),
  });

  return (
    <div className="max-w-xl space-y-6">
      <h1 className="text-2xl font-bold">Sécurité</h1>

      <section className="rounded-xl border bg-white p-6">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h2 className="font-semibold">Authentification à deux facteurs (2FA)</h2>
            <p className="text-sm text-slate-500">
              Ajoutez un code à usage unique (TOTP) à votre connexion par mot de passe.
            </p>
          </div>
          <span
            className={`rounded px-2 py-1 text-xs font-medium ${
              me?.totpEnabled ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'
            }`}
          >
            {me?.totpEnabled ? 'Activée' : 'Désactivée'}
          </span>
        </div>

        {me?.totpEnabled ? (
          <DisableTwoFactor onDone={refetch} />
        ) : (
          <EnableTwoFactor onDone={refetch} />
        )}
      </section>
    </div>
  );
}

function EnableTwoFactor({ onDone }: { onDone: () => void }) {
  const [setup, setSetup] = useState<{ secret: string; provisioningUri: string } | null>(null);
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  async function startSetup() {
    setError(null);
    setSetup(await twoFactorApi.setup());
  }

  async function onEnable(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await twoFactorApi.enable(code);
      setDone(true);
      onDone();
    } catch (err) {
      setError(err instanceof ApiError ? 'Code invalide.' : 'Une erreur est survenue.');
    }
  }

  if (done) {
    return <p className="text-sm text-green-600">2FA activée. Elle sera demandée à la prochaine connexion.</p>;
  }

  if (!setup) {
    return (
      <button
        onClick={startSetup}
        className="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
      >
        Activer la 2FA
      </button>
    );
  }

  return (
    <form onSubmit={onEnable} className="space-y-4">
      <ol className="list-decimal space-y-3 pl-5 text-sm text-slate-600">
        <li>
          Scannez ce QR code avec votre application (Google Authenticator, Authy…) :
          <div className="mt-2 inline-block rounded bg-white p-3 ring-1 ring-slate-200">
            <QRCodeSVG value={setup.provisioningUri} size={160} />
          </div>
        </li>
        <li>
          Ou saisissez la clé manuellement :
          <code className="ml-1 break-all rounded bg-slate-100 px-1 font-mono text-xs">{setup.secret}</code>
        </li>
        <li>Entrez le code à 6 chiffres généré pour confirmer :</li>
      </ol>
      <input
        value={code}
        onChange={(e) => setCode(e.target.value)}
        inputMode="numeric"
        autoComplete="one-time-code"
        placeholder="123456"
        className="w-40 rounded border border-slate-300 bg-white px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
      />
      {error && <p className="text-sm text-red-600">{error}</p>}
      <div>
        <button
          type="submit"
          disabled={code.length < 6}
          className="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        >
          Confirmer et activer
        </button>
      </div>
    </form>
  );
}

function DisableTwoFactor({ onDone }: { onDone: () => void }) {
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function onDisable(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await twoFactorApi.disable(code);
      onDone();
    } catch (err) {
      setError(err instanceof ApiError ? 'Code invalide.' : 'Une erreur est survenue.');
    }
  }

  return (
    <form onSubmit={onDisable} className="space-y-3">
      <p className="text-sm text-slate-600">
        Entrez un code de votre application pour désactiver la 2FA.
      </p>
      <input
        value={code}
        onChange={(e) => setCode(e.target.value)}
        inputMode="numeric"
        autoComplete="one-time-code"
        placeholder="123456"
        className="w-40 rounded border border-slate-300 bg-white px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
      />
      {error && <p className="text-sm text-red-600">{error}</p>}
      <div>
        <button
          type="submit"
          disabled={code.length < 6}
          className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
        >
          Désactiver la 2FA
        </button>
      </div>
    </form>
  );
}

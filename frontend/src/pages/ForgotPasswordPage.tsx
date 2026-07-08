import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { AuthShell, Field } from './LoginPage';

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    // Always succeeds client-side; backend never reveals whether the email exists.
    await api.post('/api/auth/password/forgot', { email }, { auth: false }).catch(() => {});
    setSent(true);
  }

  return (
    <AuthShell title="Mot de passe oublié">
      {sent ? (
        <p className="text-center text-sm text-slate-600">
          Si un compte existe pour <span className="font-medium">{email}</span>, un email de
          réinitialisation a été envoyé.
        </p>
      ) : (
        <form onSubmit={onSubmit} className="space-y-4">
          <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
          <button className="w-full rounded bg-brand-600 py-2 font-medium text-white hover:bg-brand-700">
            Envoyer le lien
          </button>
        </form>
      )}
      <p className="mt-4 text-center text-sm text-slate-500">
        <Link to="/login" className="text-brand-600 hover:underline">
          Retour à la connexion
        </Link>
      </p>
    </AuthShell>
  );
}

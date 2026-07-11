import { FormEvent, useState } from 'react';
import { LegalLayout } from './LegalLayout';

// NOTE: legal templates for an academic project. Have them reviewed before any
// real production/commercial use.

export function TermsPage() {
  return (
    <LegalLayout title="Conditions Générales d'Utilisation">
      <p>
        Les présentes conditions générales d'utilisation (« CGU ») régissent l'accès et
        l'utilisation de l'application CoPot (« le Service »), plateforme de gestion et de
        partage de comptes bancaires.
      </p>
      <h2>1. Objet</h2>
      <p>
        CoPot permet à ses utilisateurs de créer des comptes, d'y enregistrer des opérations,
        de définir des budgets, de consulter des statistiques et de partager la gestion d'un
        compte avec d'autres utilisateurs selon des rôles définis.
      </p>
      <h2>2. Accès au service</h2>
      <p>
        L'accès nécessite la création d'un compte avec une adresse email valide et un mot de
        passe respectant notre politique de sécurité (12 caractères minimum, incluant
        majuscules, minuscules, chiffres et symboles).
      </p>
      <h2>3. Obligations de l'utilisateur</h2>
      <ul>
        <li>Fournir des informations exactes lors de l'inscription ;</li>
        <li>Préserver la confidentialité de ses identifiants ;</li>
        <li>Ne pas détourner le Service à des fins illicites ;</li>
        <li>Respecter les droits des autres utilisateurs.</li>
      </ul>
      <h2>4. Responsabilité</h2>
      <p>
        CoPot est un projet académique fourni « en l'état », sans garantie. Les données
        financières saisies sont fictives ou sous la responsabilité de l'utilisateur ; les
        IBAN générés sont factices et ne correspondent à aucun compte bancaire réel.
      </p>
      <h2>5. Résiliation</h2>
      <p>
        L'utilisateur peut demander la suppression de son compte à tout moment. CoPot se
        réserve le droit de suspendre un compte en cas de non-respect des présentes CGU.
      </p>
    </LegalLayout>
  );
}

export function SalesPage() {
  return (
    <LegalLayout title="Conditions Générales de Vente">
      <p>
        CoPot est proposé dans le cadre d'un projet académique et n'est associé à aucune
        commercialisation. Les présentes conditions générales de vente (« CGV ») sont fournies
        à titre indicatif pour une éventuelle exploitation future.
      </p>
      <h2>1. Prix</h2>
      <p>Le Service est actuellement gratuit et ne fait l'objet d'aucune facturation.</p>
      <h2>2. Abonnements (à titre indicatif)</h2>
      <p>
        En cas de mise en place d'offres payantes, les tarifs, la durée d'engagement et les
        modalités de paiement seraient précisés lors de la souscription, conformément aux
        articles L.221-1 et suivants du Code de la consommation.
      </p>
      <h2>3. Droit de rétractation</h2>
      <p>
        Conformément à la loi, l'utilisateur consommateur disposerait d'un délai de 14 jours
        pour exercer son droit de rétractation sur tout achat de service en ligne.
      </p>
      <h2>4. Remboursement</h2>
      <p>
        Toute demande de remboursement serait traitée dans un délai de 14 jours à compter de
        la réception de la demande, par le moyen de paiement initial.
      </p>
    </LegalLayout>
  );
}

export function PrivacyPage() {
  return (
    <LegalLayout title="Politique de confidentialité">
      <p>
        La présente politique décrit comment CoPot collecte, utilise et protège vos données
        personnelles, conformément au Règlement Général sur la Protection des Données (RGPD).
      </p>
      <h2>1. Responsable du traitement</h2>
      <p>Le responsable du traitement est l'équipe projet CoPot (ESGI). Contact : dpo@copot.local.</p>
      <h2>2. Données collectées</h2>
      <ul>
        <li>Données d'identification : adresse email ;</li>
        <li>Données de sécurité : mot de passe (haché), secret 2FA, journaux de connexion ;</li>
        <li>Données d'usage : comptes, opérations, budgets et catégories que vous créez.</li>
      </ul>
      <h2>3. Finalités</h2>
      <p>
        Ces données servent uniquement à fournir le Service (authentification, gestion des
        comptes) et à en assurer la sécurité (journal d'audit, détection d'abus).
      </p>
      <h2>4. Base légale</h2>
      <p>Le traitement repose sur l'exécution du contrat (CGU) et votre consentement.</p>
      <h2>5. Durée de conservation</h2>
      <p>
        Vos données sont conservées tant que votre compte est actif, puis supprimées ou
        anonymisées dans un délai raisonnable après sa fermeture.
      </p>
      <h2>6. Vos droits</h2>
      <p>
        Vous disposez d'un droit d'accès, de rectification, d'effacement, de portabilité et
        d'opposition. Pour les exercer, écrivez à <a href="mailto:dpo@copot.local">dpo@copot.local</a>.
        Vous pouvez également saisir la CNIL.
      </p>
      <h2>7. Sécurité</h2>
      <p>
        Les mots de passe sont hachés, les échanges chiffrés (HTTPS), l'accès protégé par JWT
        et 2FA optionnelle, et les tentatives de connexion sont limitées (anti-brute-force).
      </p>
    </LegalLayout>
  );
}

export function CookiesPage() {
  return (
    <LegalLayout title="Politique de cookies">
      <p>
        Cette page explique l'usage des cookies et traceurs sur CoPot et comment vous pouvez
        les contrôler.
      </p>
      <h2>1. Cookies strictement nécessaires</h2>
      <p>
        CoPot utilise le stockage local de votre navigateur pour conserver votre session
        (jeton d'authentification). Ces traceurs sont indispensables au fonctionnement du
        Service et ne nécessitent pas de consentement.
      </p>
      <h2>2. Cookies de mesure d'audience</h2>
      <p>
        Sous réserve de votre consentement, nous utilisons Matomo (analytique respectueuse de
        la vie privée) pour comprendre l'usage du Service. Aucun cookie de mesure d'audience
        n'est déposé tant que vous n'y avez pas consenti via la bannière prévue à cet effet.
      </p>
      <h2>3. Gestion du consentement</h2>
      <p>
        Vous pouvez accepter ou refuser les cookies de mesure d'audience à tout moment via la
        bannière de consentement. Votre choix est conservé et peut être modifié en effaçant
        les données du site dans votre navigateur.
      </p>
    </LegalLayout>
  );
}

export function ContactPage() {
  const [name, setName] = useState('');
  const [message, setMessage] = useState('');

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    const subject = encodeURIComponent(`[CoPot] Contact de ${name || 'un utilisateur'}`);
    const body = encodeURIComponent(message);
    window.location.href = `mailto:contact@copot.local?subject=${subject}&body=${body}`;
  }

  return (
    <LegalLayout title="Contact">
      <p>
        Une question, une remarque ? Écrivez-nous à{' '}
        <a href="mailto:contact@copot.local">contact@copot.local</a> ou utilisez le formulaire
        ci-dessous.
      </p>
      <form onSubmit={onSubmit} className="mt-4 space-y-4">
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-slate-700">Votre nom</span>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full rounded border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500"
          />
        </label>
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-slate-700">Message</span>
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            required
            rows={5}
            className="w-full rounded border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500"
          />
        </label>
        <button
          type="submit"
          className="rounded bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700"
        >
          Envoyer
        </button>
      </form>
    </LegalLayout>
  );
}

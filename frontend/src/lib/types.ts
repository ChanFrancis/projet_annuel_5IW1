export type AccountType = 'courant' | 'commun' | 'livret' | 'epargne';
export type AccountRole = 'owner' | 'co_owner' | 'viewer';
export type InvitationStatus = 'pending' | 'accepted' | 'declined' | 'expired';

export interface Category {
  id: string;
  name: string;
  parentId: string | null;
}

export interface Member {
  id: string;
  userId: string;
  email: string;
  role: AccountRole;
}

export interface Account {
  id: string;
  label: string;
  type: AccountType;
  typeLabel: string;
  currency: string;
  iban: string;
  balance: string;
  createdAt: string;
  members?: Member[];
}

export interface Transaction {
  id: string;
  accountId: string;
  date: string;
  amount: string;
  label: string;
  category: Category | null;
  attachmentUrl: string | null;
  createdAt: string;
}

export interface Invitation {
  id: string;
  accountId: string;
  email: string;
  role: AccountRole;
  status: InvitationStatus;
  expiresAt: string;
}

export const ACCOUNT_TYPES: { value: AccountType; label: string }[] = [
  { value: 'courant', label: 'Compte courant' },
  { value: 'commun', label: 'Compte commun' },
  { value: 'livret', label: 'Livret' },
  { value: 'epargne', label: 'Épargne personnalisée' },
];

export const ROLE_LABELS: Record<AccountRole, string> = {
  owner: 'Propriétaire',
  co_owner: 'Co-titulaire',
  viewer: 'Lecteur',
};

export function formatMoney(amount: string | number, currency = 'EUR'): string {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(n);
}

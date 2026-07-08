import { api } from '@/lib/api';
import type { Account, AccountRole, Category, Invitation, Transaction } from '@/lib/types';

// ---- Accounts ----
export const accountsApi = {
  list: () => api.get<{ accounts: Account[] }>('/api/accounts').then((r) => r.accounts),
  get: (id: string) => api.get<Account>(`/api/accounts/${id}`),
  create: (body: { label: string; type: string; currency?: string }) =>
    api.post<Account>('/api/accounts', body),
  update: (id: string, body: { label?: string; type?: string }) =>
    api.patch<Account>(`/api/accounts/${id}`, body),
  remove: (id: string) => api.delete(`/api/accounts/${id}`),
  updateMember: (accountId: string, memberId: string, role: AccountRole) =>
    api.patch(`/api/accounts/${accountId}/members/${memberId}`, { role }),
  removeMember: (accountId: string, memberId: string) =>
    api.delete(`/api/accounts/${accountId}/members/${memberId}`),
};

// ---- Transactions ----
export interface TransactionInput {
  date: string;
  label: string;
  amount: string;
  categoryId?: string | null;
}

export const transactionsApi = {
  listForAccount: (accountId: string) =>
    api.get<{ account: Account; transactions: Transaction[] }>(
      `/api/accounts/${accountId}/transactions`,
    ),
  create: (accountId: string, body: TransactionInput) =>
    api.post<Transaction>(`/api/accounts/${accountId}/transactions`, body),
  update: (txId: string, body: Partial<TransactionInput>) =>
    api.patch<Transaction>(`/api/transactions/${txId}`, body),
  remove: (txId: string) => api.delete(`/api/transactions/${txId}`),
  exportUrl: (accountId: string) =>
    `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000'}/api/accounts/${accountId}/transactions/export`,
};

// ---- Categories ----
export const categoriesApi = {
  list: () => api.get<{ categories: Category[] }>('/api/categories').then((r) => r.categories),
  create: (body: { name: string; parentId?: string | null }) =>
    api.post<Category>('/api/categories', body),
  update: (id: string, body: { name?: string; parentId?: string | null }) =>
    api.patch<Category>(`/api/categories/${id}`, body),
  remove: (id: string) => api.delete(`/api/categories/${id}`),
};

// ---- Invitations ----
export const invitationsApi = {
  listForAccount: (accountId: string) =>
    api
      .get<{ invitations: Invitation[] }>(`/api/accounts/${accountId}/invitations`)
      .then((r) => r.invitations),
  create: (accountId: string, body: { email: string; role: AccountRole }) =>
    api.post<Invitation>(`/api/accounts/${accountId}/invitations`, body),
  show: (token: string) =>
    api.get<{ accountLabel: string; email: string; role: AccountRole }>(
      `/api/invitations/${token}`,
    ),
  accept: (token: string) => api.post<Account>('/api/invitations/accept', { token }),
  decline: (token: string) => api.post('/api/invitations/decline', { token }),
};

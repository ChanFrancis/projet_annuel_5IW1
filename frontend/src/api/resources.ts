import { api } from '@/lib/api';
import type {
  Account,
  AccountRole,
  AdminUser,
  AuditLog,
  Budget,
  Category,
  CategoryBreakdown,
  Invitation,
  MonthlyPoint,
  StatsSummary,
  Transaction,
} from '@/lib/types';

/** Build a query string from a params object, dropping empty values. */
function withQuery(path: string, params: Record<string, string | number | undefined>): string {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') qs.set(key, String(value));
  }
  const str = qs.toString();
  return str ? `${path}?${str}` : path;
}

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

// ---- Budgets ----
export const budgetsApi = {
  listForAccount: (accountId: string, month: string) =>
    api
      .get<{ budgets: Budget[]; month: string }>(withQuery(`/api/accounts/${accountId}/budgets`, { month }))
      .then((r) => r.budgets),
  create: (accountId: string, body: { categoryId: string; month: string; amount: string }) =>
    api.post<Budget>(`/api/accounts/${accountId}/budgets`, body),
  update: (accountId: string, budgetId: string, body: { amount: string }) =>
    api.patch<Budget>(`/api/accounts/${accountId}/budgets/${budgetId}`, body),
  remove: (accountId: string, budgetId: string) =>
    api.delete(`/api/accounts/${accountId}/budgets/${budgetId}`),
};

// ---- Statistics ----
export const statsApi = {
  summary: (accountId: string, from: string, to: string) =>
    api.get<StatsSummary>(withQuery(`/api/accounts/${accountId}/stats/summary`, { from, to })),
  monthly: (accountId: string, from: string, to: string) =>
    api
      .get<{ points: MonthlyPoint[] }>(withQuery(`/api/accounts/${accountId}/stats/monthly`, { from, to }))
      .then((r) => r.points),
  byCategory: (accountId: string, from: string, to: string) =>
    api
      .get<{ categories: CategoryBreakdown[] }>(
        withQuery(`/api/accounts/${accountId}/stats/by-category`, { from, to }),
      )
      .then((r) => r.categories),
};

// ---- Two-factor (TOTP) ----
export const twoFactorApi = {
  setup: () => api.post<{ secret: string; provisioningUri: string }>('/api/auth/2fa/setup'),
  enable: (code: string) => api.post<{ message: string }>('/api/auth/2fa/enable', { code }),
  disable: (code: string) => api.post<{ message: string }>('/api/auth/2fa/disable', { code }),
};

// ---- Admin ----
export const adminApi = {
  listUsers: () => api.get<{ users: AdminUser[] }>('/api/admin/users').then((r) => r.users),
  updateUser: (id: string, body: { banned?: boolean; admin?: boolean }) =>
    api.patch<AdminUser>(`/api/admin/users/${id}`, body),
  reset2fa: (id: string) => api.post<AdminUser>(`/api/admin/users/${id}/2fa-reset`),
  auditLogs: (params: {
    action?: string;
    userId?: string;
    from?: string;
    to?: string;
    limit?: number;
  }) =>
    api
      .get<{ logs: AuditLog[] }>(withQuery('/api/admin/audit-logs', params))
      .then((r) => r.logs),
};

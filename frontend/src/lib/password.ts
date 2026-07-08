// Mirror of the backend StrongPassword policy:
// ≥12 chars, one lowercase, one uppercase, one digit, one symbol.
export function passwordIssues(pw: string): string[] {
  const issues: string[] = [];
  if (pw.length < 12) issues.push('12 caractères minimum');
  if (!/[a-z]/.test(pw)) issues.push('une minuscule');
  if (!/[A-Z]/.test(pw)) issues.push('une majuscule');
  if (!/[0-9]/.test(pw)) issues.push('un chiffre');
  if (!/[^A-Za-z0-9]/.test(pw)) issues.push('un symbole');
  return issues;
}

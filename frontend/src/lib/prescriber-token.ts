const STORAGE_KEY = "sanitaire_prescriber_token";

export function getPrescriberToken(): string | null {
  return localStorage.getItem(STORAGE_KEY);
}

export function setPrescriberToken(token: string): void {
  localStorage.setItem(STORAGE_KEY, token);
}

export function clearPrescriberToken(): void {
  localStorage.removeItem(STORAGE_KEY);
}

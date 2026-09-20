const STORAGE_KEY = "sanitaire_platform_token";

export function getPlatformToken(): string | null {
  return localStorage.getItem(STORAGE_KEY);
}

export function setPlatformToken(token: string): void {
  localStorage.setItem(STORAGE_KEY, token);
}

export function clearPlatformToken(): void {
  localStorage.removeItem(STORAGE_KEY);
}

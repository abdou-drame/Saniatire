const STORAGE_KEY = "sanitaire_patient_token";

export function getPatientToken(): string | null {
  return localStorage.getItem(STORAGE_KEY);
}

export function setPatientToken(token: string): void {
  localStorage.setItem(STORAGE_KEY, token);
}

export function clearPatientToken(): void {
  localStorage.removeItem(STORAGE_KEY);
}

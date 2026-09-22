import axios from "axios";
import { clearPatientToken, getPatientToken } from "@/lib/patient-token";

export const patientApi = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? (import.meta.env.PROD ? "https://saliha-health-api.duckdns.org/api" : "http://127.0.0.1:8000/api"),
  headers: {
    Accept: "application/json",
  },
});

patientApi.interceptors.request.use((config) => {
  const token = getPatientToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

patientApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearPatientToken();
      if (window.location.pathname !== "/portail/login") {
        window.location.assign("/portail/login");
      }
    }
    return Promise.reject(error);
  },
);

import axios from "axios";
import { clearPrescriberToken, getPrescriberToken } from "@/lib/prescriber-token";

export const prescriberApi = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? (import.meta.env.PROD ? "https://saliha-health-api.duckdns.org/api" : "http://127.0.0.1:8000/api"),
  headers: {
    Accept: "application/json",
  },
});

prescriberApi.interceptors.request.use((config) => {
  const token = getPrescriberToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

prescriberApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearPrescriberToken();
      if (window.location.pathname !== "/portail-prescripteur/login") {
        window.location.assign("/portail-prescripteur/login");
      }
    }
    return Promise.reject(error);
  },
);

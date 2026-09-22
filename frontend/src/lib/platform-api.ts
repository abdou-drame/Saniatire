import axios from "axios";
import { clearPlatformToken, getPlatformToken } from "@/lib/platform-token";

export const platformApi = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? (import.meta.env.PROD ? "https://saliha-health-api.duckdns.org/api" : "http://127.0.0.1:8000/api"),
  headers: {
    Accept: "application/json",
  },
});

platformApi.interceptors.request.use((config) => {
  const token = getPlatformToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

platformApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearPlatformToken();
      if (window.location.pathname !== "/platform/login") {
        window.location.assign("/platform/login");
      }
    }
    return Promise.reject(error);
  },
);

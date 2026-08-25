import { Check, Download } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";

export interface ExportButtonProps {
  label: string;
  url: string;
  params?: Record<string, string | number | undefined>;
  filename: string;
}

type ExportState = "idle" | "loading" | "done" | "error";

/** Triggers the backend's own CSV generation (StreamedResponse) and hands the browser the file — no CSV building on the frontend. */
export function ExportButton({ label, url, params, filename }: ExportButtonProps) {
  const [state, setState] = useState<ExportState>("idle");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function handleExport() {
    setState("loading");
    setErrorMessage(null);
    try {
      const response = await api.get(url, { params, responseType: "blob" });
      const blobUrl = URL.createObjectURL(response.data as Blob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(blobUrl);
      setState("done");
      setTimeout(() => setState("idle"), 3000);
    } catch (error) {
      setErrorMessage(apiErrorMessage(error));
      setState("error");
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <Button variant="secondary" size="sm" onClick={handleExport} disabled={state === "loading"}>
        {state === "done" ? <Check size={14} className="text-success" /> : <Download size={14} />}
        {state === "loading" ? "Génération…" : state === "done" ? "Téléchargé" : label}
      </Button>
      {state === "error" && errorMessage && <p className="text-xs text-danger">{errorMessage}</p>}
    </div>
  );
}

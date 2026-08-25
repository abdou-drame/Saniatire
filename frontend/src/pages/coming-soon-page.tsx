import { Construction } from "lucide-react";
import { EmptyState } from "@/components/ui/empty-state";

export function ComingSoonPage({ title }: { title: string }) {
  return (
    <div className="flex h-full items-center justify-center">
      <EmptyState
        icon={Construction}
        title={`${title} — écran à venir`}
        description="Ce module sera construit lors d'une prochaine étape, sur la base du même système de design."
        className="w-full max-w-md py-24"
      />
    </div>
  );
}

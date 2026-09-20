import type { BadgeProps } from "@/components/ui/badge";
import type { WorkScheduleType } from "@/types/api";

/**
 * Display-only mapping for the garde/astreinte distinction on the on-call
 * screen. No shared color map for WorkScheduleType existed elsewhere in the
 * codebase at the time this was written (searched for a plannings module —
 * none has been built yet), so this one is local to gardes-astreintes and
 * intentionally reused by both the "now" cards and the history list so the
 * two sections stay visually consistent.
 *
 * "garde" (on-site duty) gets the strongest, most attention-grabbing color
 * (danger/red) since that person is physically present and the fastest to
 * reach in an emergency. "astreinte" (reachable on-call, not on-site) gets a
 * calmer but still distinct accent color. "normal" is excluded — the on-call
 * endpoints never return it.
 */
export const ON_CALL_TYPE_LABEL: Record<Extract<WorkScheduleType, "garde" | "astreinte">, string> = {
  garde: "Garde (sur place)",
  astreinte: "Astreinte",
};

export const ON_CALL_TYPE_BADGE: Record<Extract<WorkScheduleType, "garde" | "astreinte">, NonNullable<BadgeProps["status"]>> = {
  garde: "danger",
  astreinte: "accent",
};

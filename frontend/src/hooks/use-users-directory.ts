import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";
import { api } from "@/lib/api";
import type { Paginated, StaffUser } from "@/types/api";

/**
 * The full staff roster (not just page 1) is needed to resolve user_id
 * references on RH pages (employee profiles, work schedules, leave
 * requests, on-call) into names/roles — so this walks `links.next` like
 * fetchAllAppointments in use-appointments.ts, unlike usePatientsDirectory
 * which settles for the first page.
 *
 * `GET /users` is gated by the `users.view` permission (only rh, direction,
 * administrateur roles have it). Hooks in this codebase don't call
 * useAuth() themselves — callers must pass `enabled` based on
 * `hasPermission("users.view")` so the request isn't fired for users who
 * lack the permission.
 */
async function fetchAllUsers(): Promise<StaffUser[]> {
  const results: StaffUser[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<StaffUser>>("/users", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function useUsersDirectory(enabled: boolean) {
  const query = useQuery({
    queryKey: ["users", "directory"],
    queryFn: fetchAllUsers,
    enabled,
    staleTime: 5 * 60_000,
  });

  const byId = useMemo(() => {
    const map = new Map<number, StaffUser>();
    for (const user of query.data ?? []) {
      map.set(user.id, user);
    }
    return map;
  }, [query.data]);

  return { ...query, byId };
}

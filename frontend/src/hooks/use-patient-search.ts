import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { api } from "@/lib/api";
import type { Paginated, Patient } from "@/types/api";

const MIN_QUERY_LENGTH = 2;

export function usePatientSearch() {
  const [query, setQuery] = useState("");
  const debounced = useDebouncedValue(query.trim(), 300);
  const enabled = debounced.length >= MIN_QUERY_LENGTH;

  const { data, isFetching, isError } = useQuery({
    queryKey: ["patients", "search", debounced],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Patient>>("/patients", {
        params: { search: debounced },
      });
      return data.data;
    },
    enabled,
    placeholderData: (previous) => previous,
  });

  return {
    query,
    setQuery,
    isOpen: query.trim().length > 0,
    isSearching: enabled && isFetching,
    isError: enabled && isError,
    results: enabled ? (data ?? []) : [],
    tooShort: query.trim().length > 0 && !enabled,
  };
}

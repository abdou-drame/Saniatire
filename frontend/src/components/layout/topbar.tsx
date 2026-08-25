import { Bell, LogOut, Search, User as UserIcon } from "lucide-react";
import { useState, type ReactNode } from "react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { Patient } from "@/types/api";

export interface TopbarProps {
  title: string;
  breadcrumb?: ReactNode;
  notificationCount?: number;
  userName?: string;
  userRole?: string;
  onLogout?: () => void;
  searchQuery?: string;
  onSearchQueryChange?: (value: string) => void;
  searchResults?: Patient[];
  isSearching?: boolean;
  isSearchOpen?: boolean;
  searchTooShort?: boolean;
  onSelectPatient?: (patient: Patient) => void;
}

export function Topbar({
  title,
  breadcrumb,
  notificationCount = 0,
  userName = "Utilisateur",
  userRole = "—",
  onLogout,
  searchQuery = "",
  onSearchQueryChange,
  searchResults = [],
  isSearching = false,
  isSearchOpen = false,
  searchTooShort = false,
  onSelectPatient,
}: TopbarProps) {
  const [isFocused, setIsFocused] = useState(false);
  const dropdownVisible = isFocused && isSearchOpen;

  return (
    <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-border bg-bg px-6">
      <div className="min-w-0">
        {breadcrumb ?? (
          <h1 className="truncate font-heading text-base font-semibold text-text">{title}</h1>
        )}
      </div>

      <div className="relative flex max-w-md flex-1 items-center">
        <div className="relative w-full">
          <Search
            size={15}
            strokeWidth={2}
            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle"
          />
          <input
            type="search"
            placeholder="Rechercher un patient (nom, n° dossier)..."
            value={searchQuery}
            onChange={(e) => onSearchQueryChange?.(e.target.value)}
            onFocus={() => setIsFocused(true)}
            onBlur={() => setTimeout(() => setIsFocused(false), 150)}
            className="h-9 w-full rounded-md border border-border bg-surface pl-9 pr-3 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
          />
        </div>

        {dropdownVisible && (
          <div className="absolute left-0 top-11 z-50 w-full overflow-hidden rounded-md border border-border bg-surface shadow-lg">
            {searchTooShort && (
              <p className="px-3 py-2.5 text-xs text-text-subtle">Tapez au moins 2 caractères.</p>
            )}
            {!searchTooShort && isSearching && (
              <p className="px-3 py-2.5 text-xs text-text-muted">Recherche…</p>
            )}
            {!searchTooShort && !isSearching && searchResults.length === 0 && (
              <p className="px-3 py-2.5 text-xs text-text-muted">Aucun patient trouvé.</p>
            )}
            {!searchTooShort && !isSearching && searchResults.length > 0 && (
              <ul className="max-h-72 overflow-y-auto py-1">
                {searchResults.map((patient) => (
                  <li key={patient.id}>
                    <button
                      type="button"
                      onMouseDown={(e) => e.preventDefault()}
                      onClick={() => onSelectPatient?.(patient)}
                      className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-surface-hover"
                    >
                      <span className="text-sm text-text">
                        {patient.first_name} {patient.last_name}
                      </span>
                      <span className="font-tabular text-xs text-text-subtle">{patient.patient_number}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>

      <div className="flex shrink-0 items-center gap-3">
        <button
          className="relative rounded-md p-2 text-text-muted hover:bg-surface-hover hover:text-text"
          aria-label="Notifications"
        >
          <Bell size={18} strokeWidth={2} />
          {notificationCount > 0 && (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
              {notificationCount > 9 ? "9+" : notificationCount}
            </span>
          )}
        </button>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button className="flex items-center gap-2.5 rounded-md py-1 pl-1 pr-2 hover:bg-surface-hover">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-surface-hover text-text-muted">
                <UserIcon size={16} />
              </div>
              <div className="text-left leading-tight">
                <p className="text-sm font-medium text-text">{userName}</p>
                <p className="text-xs text-text-subtle">{userRole}</p>
              </div>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent>
            <DropdownMenuLabel>Mon compte</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={onLogout} className="text-danger focus:bg-danger/10">
              <LogOut size={15} />
              Déconnexion
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}

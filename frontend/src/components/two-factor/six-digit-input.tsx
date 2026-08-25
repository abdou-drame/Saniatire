import { useState } from "react";
import { Input } from "@/components/ui/input";

interface SixDigitInputProps {
  onComplete: (code: string) => void;
  disabled?: boolean;
  autoFocus?: boolean;
}

/** Single field for a 6-digit TOTP code; calls onComplete as soon as 6 digits are entered. */
export function SixDigitInput({ onComplete, disabled, autoFocus = true }: SixDigitInputProps) {
  const [value, setValue] = useState("");

  function handleChange(raw: string) {
    const digits = raw.replace(/\D/g, "").slice(0, 6);
    setValue(digits);
    if (digits.length === 6) {
      onComplete(digits);
    }
  }

  return (
    <Input
      inputMode="numeric"
      autoComplete="one-time-code"
      pattern="[0-9]*"
      maxLength={6}
      autoFocus={autoFocus}
      disabled={disabled}
      value={value}
      onChange={(e) => handleChange(e.target.value)}
      placeholder="000000"
      className="text-center text-lg tracking-[0.5em] font-mono"
    />
  );
}

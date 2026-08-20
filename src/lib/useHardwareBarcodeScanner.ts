import { useEffect, useRef } from 'react';

/**
 * Custom Hook to listen for physical hardware barcode scanners (keyboard emulation / HID devices).
 * Detects rapid sequences of keystrokes (usually under 35ms between keys) and automatically
 * captures the complete barcode without requiring manual text field focus.
 */
export const useHardwareBarcodeScanner = (
  onScan: (barcode: string) => void,
  options: { enabled: boolean } = { enabled: true }
) => {
  const bufferRef = useRef<string[]>([]);
  const lastKeyTimeRef = useRef<number>(0);

  useEffect(() => {
    if (!options.enabled) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      // Ignore functional/control keys except Enter
      if (e.key.length > 1 && e.key !== 'Enter') {
        return;
      }

      const now = Date.now();
      const diff = now - lastKeyTimeRef.current;
      lastKeyTimeRef.current = now;

      // Physical barcode scanners typically type at a rate of 10-30ms per character.
      // Humans cannot type faster than 60-80ms per character reliably.
      // If the delay is larger than 50ms, reset the buffer as it is likely manual human typing.
      if (diff > 50) {
        bufferRef.current = [];
      }

      if (e.key === 'Enter') {
        // Most scanners send an 'Enter' key at the end of the scanned string.
        if (bufferRef.current.length >= 3) {
          const barcode = bufferRef.current.join('').trim();
          bufferRef.current = [];
          
          if (barcode) {
            // If focused on an input element, clear the text to avoid duplicate inputs or messy text
            const activeEl = document.activeElement;
            if (activeEl instanceof HTMLInputElement || activeEl instanceof HTMLTextAreaElement) {
              activeEl.value = '';
              activeEl.blur(); // Unfocus to avoid typing leftover carriage returns
            }

            onScan(barcode);
            e.preventDefault();
            e.stopPropagation();
          }
        }
      } else {
        bufferRef.current.push(e.key);
      }
    };

    window.addEventListener('keydown', handleKeyDown, true);
    return () => {
      window.removeEventListener('keydown', handleKeyDown, true);
    };
  }, [onScan, options.enabled]);
};

import React from 'react';
import { Cloud, RefreshCw } from 'lucide-react';

interface LoadingOverlayProps {
  theme?: 'light' | 'dark';
  message?: string;
  isInitialLoading?: boolean;
}

export const LoadingOverlay: React.FC<LoadingOverlayProps> = ({
  theme = 'light',
  message = 'Syncing shop records with Firestore...',
  isInitialLoading = false,
}) => {
  const isLight = theme === 'light';

  return (
    <div 
      className="fixed top-3 right-3 z-50 transition-all duration-300 pointer-events-none"
      id="global-loading-indicator"
    >
      <div className={`px-3.5 py-2 rounded-2xl border shadow-lg backdrop-blur-md flex items-center gap-2.5 text-xs font-semibold animate-fade-in ${
        isLight
          ? 'bg-white/95 border-emerald-300 text-emerald-900 shadow-emerald-500/10'
          : 'bg-slate-900/95 border-emerald-500/40 text-emerald-200 shadow-emerald-950/40'
      }`}>
        <div className="relative flex items-center justify-center">
          <RefreshCw className="w-4 h-4 text-emerald-500 animate-spin" />
        </div>
        <span>{message}</span>
      </div>
    </div>
  );
};

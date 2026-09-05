import React from 'react';
import { Cloud, RefreshCw } from 'lucide-react';

interface GlobalLoadingSkeletonProps {
  theme?: 'light' | 'dark';
  message?: string;
  onSkip?: () => void;
}

export const GlobalLoadingSkeleton: React.FC<GlobalLoadingSkeletonProps> = ({
  theme = 'light',
  message = 'Connecting to Firestore & fetching shop records...',
  onSkip
}) => {
  const isLight = theme === 'light';

  const cardBg = isLight ? 'bg-white border-slate-200 shadow-sm' : 'bg-slate-900/90 border-slate-800';
  const shimmerBg = isLight ? 'bg-slate-200' : 'bg-slate-800';
  const shimmerLight = isLight ? 'bg-slate-100' : 'bg-slate-850';

  return (
    <div className="w-full space-y-6 animate-pulse" id="global-loading-skeleton">
      {/* Top Floating Status Indicator / Sync Banner */}
      <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${isLight ? 'bg-emerald-50/80 border-emerald-200/80 text-emerald-900' : 'bg-emerald-950/40 border-emerald-800/60 text-emerald-200'}`}>
        <div className="flex items-center gap-3">
          <div className={`p-2 rounded-xl flex items-center justify-center ${isLight ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-900/50 text-emerald-300'}`}>
            <Cloud className="w-5 h-5 animate-bounce" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
              <p className="text-sm font-bold">Synchronizing Live Database</p>
            </div>
            <p className={`text-xs ${isLight ? 'text-emerald-700' : 'text-emerald-300/80'} mt-0.5`}>
              {message}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
            <span>Fetching...</span>
          </div>
          {onSkip && (
            <button
              onClick={onSkip}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-colors cursor-pointer ${
                isLight 
                  ? 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 shadow-xs' 
                  : 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700'
              }`}
            >
              Skip to Cached Data
            </button>
          )}
        </div>
      </div>

      {/* Header Skeleton */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="space-y-2">
          <div className={`h-7 w-48 rounded-lg ${shimmerBg}`} />
          <div className={`h-4 w-72 rounded-md ${shimmerBg} opacity-60`} />
        </div>
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className={`h-10 w-36 rounded-xl ${shimmerBg}`} />
          <div className={`h-10 w-32 rounded-xl ${shimmerBg}`} />
        </div>
      </div>

      {/* 4 Stat Metric Cards Skeleton */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((i) => (
          <div
            key={i}
            className={`p-5 rounded-2xl border ${cardBg} space-y-3`}
          >
            <div className="flex items-center justify-between">
              <div className={`h-4 w-24 rounded-md ${shimmerBg}`} />
              <div className={`w-8 h-8 rounded-xl ${shimmerBg}`} />
            </div>
            <div className={`h-8 w-36 rounded-lg ${shimmerBg}`} />
            <div className="flex items-center gap-2">
              <div className={`h-3 w-16 rounded-sm ${shimmerBg} opacity-70`} />
              <div className={`h-3 w-20 rounded-sm ${shimmerBg} opacity-50`} />
            </div>
          </div>
        ))}
      </div>

      {/* Quick Action Pills Skeleton */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className={`h-9 w-28 rounded-xl shrink-0 ${shimmerBg} opacity-80`} />
        ))}
      </div>

      {/* Main Table / Ledger Card Skeleton */}
      <div className={`rounded-2xl border ${cardBg} overflow-hidden`}>
        {/* Table Top Toolbar */}
        <div className={`p-4 border-b ${isLight ? 'border-slate-100' : 'border-slate-850'} flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3`}>
          <div className={`h-10 w-full sm:w-64 rounded-xl ${shimmerBg}`} />
          <div className="flex items-center gap-2">
            <div className={`h-10 w-24 rounded-xl ${shimmerBg}`} />
            <div className={`h-10 w-28 rounded-xl ${shimmerBg}`} />
          </div>
        </div>

        {/* Skeleton Table Rows */}
        <div className="divide-y divide-slate-100 dark:divide-slate-800">
          {[1, 2, 3, 4, 5].map((row) => (
            <div key={row} className="p-4 flex items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl shrink-0 ${shimmerBg}`} />
                <div className="space-y-1.5">
                  <div className={`h-4 w-32 rounded-md ${shimmerBg}`} />
                  <div className={`h-3 w-24 rounded-md ${shimmerBg} opacity-60`} />
                </div>
              </div>

              <div className="hidden sm:flex items-center gap-3">
                <div className={`h-6 w-20 rounded-full ${shimmerBg}`} />
                <div className={`h-4 w-16 rounded-md ${shimmerBg} opacity-70`} />
              </div>

              <div className="flex items-center gap-4">
                <div className="text-right space-y-1">
                  <div className={`h-5 w-24 rounded-md ${shimmerBg}`} />
                  <div className={`h-3 w-16 rounded-md ${shimmerBg} opacity-60 ml-auto`} />
                </div>
                <div className={`w-8 h-8 rounded-lg ${shimmerBg}`} />
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

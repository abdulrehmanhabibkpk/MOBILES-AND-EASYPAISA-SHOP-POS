import React from 'react';
import { ShieldCheck } from 'lucide-react';

export const Footer: React.FC = () => {
  return (
    <footer className="mt-12 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs py-6 px-4 transition-colors duration-200">
      <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-center md:text-left">
        <div>
          <p className="text-slate-800 dark:text-slate-200 font-semibold flex items-center justify-center md:justify-start gap-1.5">
            <ShieldCheck className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            <span>Protected by Abdul Rahman Habib (Passcode: 6242)</span>
          </p>
          <p className="text-slate-500 dark:text-slate-400 text-[11px] mt-1">
            EasyPaisa & Cash Exchange Ledger System for Daily Profit, Loss & PDF Reports
          </p>
        </div>

        <div className="text-slate-600 dark:text-slate-400 font-medium">
          <p className="text-slate-800 dark:text-slate-300">
            Copyright © <strong className="text-emerald-600 dark:text-emerald-400">Abdul Rahman Habib</strong>
          </p>
          <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">All Rights Reserved</p>
        </div>
      </div>
    </footer>
  );
};

import React, { useState } from 'react';
import { 
  Lock, 
  Mail, 
  ShieldCheck, 
  AlertCircle, 
  LogIn, 
  Smartphone,
  RefreshCw,
  Eye,
  EyeOff
} from 'lucide-react';
import { AllowedAccount } from '../types';
import { loginWithEmailAndPassword } from '../lib/firebase';

interface LoginScreenProps {
  shopName: string;
  allowedAccounts: AllowedAccount[];
  onLoginSuccess: (userEmail: string, accountName: string) => void;
  onSwitchToPin?: () => void;
}

export const LoginScreen: React.FC<LoginScreenProps> = ({
  shopName,
  allowedAccounts = [],
  onLoginSuccess,
  onSwitchToPin,
}) => {
  // Email Form State
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  // Default fallback accounts if none provided
  const activeAccounts = allowedAccounts.length > 0 ? allowedAccounts : [
    {
      id: 'acc-1',
      email: 'owner@mobile.com',
      password: 'mobile123',
      name: 'Umer Ali Owner',
      role: 'Owner' as const
    },
    {
      id: 'acc-2',
      email: 'manager@mobile.com',
      password: '123456',
      name: 'Umer Ali Manager',
      role: 'Manager' as const
    }
  ];

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');

    const cleanEmail = email.trim().toLowerCase();
    const cleanPassword = password.trim();

    if (!cleanEmail || !cleanPassword) {
      setErrorMsg('Tammam fields (Email & Password) bharein!');
      return;
    }

    setIsSubmitting(true);

    try {
      // 1. Check local allowed accounts list
      const matchedLocalAccount = activeAccounts.find(
        acc => acc.email.toLowerCase() === cleanEmail && acc.password === cleanPassword
      );

      if (matchedLocalAccount) {
        try {
          await loginWithEmailAndPassword(cleanEmail, cleanPassword);
        } catch {
          // Ignore firebase fallback if account is in local shop allowed list
        }
        setIsSubmitting(false);
        onLoginSuccess(matchedLocalAccount.email, matchedLocalAccount.name);
        return;
      }

      // 2. Try Firebase Auth
      const firebaseRes = await loginWithEmailAndPassword(cleanEmail, cleanPassword);
      if (firebaseRes && firebaseRes.user) {
        setIsSubmitting(false);
        onLoginSuccess(firebaseRes.user.email || cleanEmail, firebaseRes.user.displayName || 'Authorized User');
        return;
      }

      // 3. If no match
      setIsSubmitting(false);
      setErrorMsg('Ghalat Email ya Password! Access Denied. Faqat ijazat shuda accounts login ho sakty hain.');
    } catch (err: any) {
      setIsSubmitting(false);
      setErrorMsg('Ghalat Email ya Password! Faqat ijazat shuda accounts login ho sakty hain.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-100/90 backdrop-blur-md p-3 sm:p-6 overflow-y-auto font-sans">
      
      {/* Soft Emerald/White Background Accents */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[30rem] h-[30rem] bg-emerald-200/40 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-1/4 left-1/2 -translate-x-1/2 w-96 h-96 bg-teal-100/50 rounded-full blur-3xl pointer-events-none" />

      <div className="w-full max-w-md bg-white border border-emerald-100 rounded-3xl shadow-2xl shadow-emerald-950/10 overflow-hidden text-slate-900 my-auto flex flex-col relative z-10">
        
        {/* Header Bar */}
        <div className="pt-7 pb-5 px-6 text-center border-b border-emerald-100 relative bg-gradient-to-b from-emerald-50 via-slate-50/50 to-white">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-600 text-white shadow-lg shadow-emerald-600/25 mb-3 border border-emerald-400/20">
            <Smartphone className="w-7 h-7 text-white" />
          </div>
          
          <h2 className="text-xl font-black text-slate-900 tracking-tight leading-snug">
            {shopName || 'Mobiles and EasyPaisa Shop POS'}
          </h2>
          
          <p className="text-xs text-emerald-700 font-bold mt-1 tracking-wide">
            MANAGEMENT SYSTEM & CASH LEDGER
          </p>

          <div className="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold">
            <ShieldCheck className="w-3.5 h-3.5 shrink-0 text-emerald-600" />
            <span>Authorized Accounts Login Only</span>
          </div>
        </div>

        {/* Form Body */}
        <div className="p-5 sm:p-6 space-y-5">
          
          {errorMsg && (
            <div className="flex items-start gap-2.5 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold leading-relaxed animate-shake">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
              <span>{errorMsg}</span>
            </div>
          )}

          <form onSubmit={handleEmailLogin} className="space-y-4">
            
            {/* Email Input */}
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                <span>Email Address:</span>
                <span className="text-[10px] text-slate-400 font-normal">Registered Shop Email</span>
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Mail className="w-4 h-4" />
                </div>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="e.g. owner@mobile.com"
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder-slate-400 text-sm font-medium focus:outline-none focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 transition-all"
                />
              </div>
            </div>

            {/* Password Input */}
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                <span>Password:</span>
                <span className="text-[10px] text-slate-400 font-normal">Account Password</span>
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Lock className="w-4 h-4" />
                </div>
                <input
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter password..."
                  className="w-full pl-10 pr-10 py-3 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder-slate-400 text-sm font-medium focus:outline-none focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 transition-all"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-700"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-60"
            >
              {isSubmitting ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" />
                  <span>Logging in...</span>
                </>
              ) : (
                <>
                  <LogIn className="w-4 h-4" />
                  <span>Login (Login Karein)</span>
                </>
              )}
            </button>

            {onSwitchToPin && (
              <div className="pt-2 text-center">
                <button
                  type="button"
                  onClick={onSwitchToPin}
                  className="text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:underline cursor-pointer"
                >
                  Use 4-Digit Security PIN instead
                </button>
              </div>
            )}

          </form>

          {/* Footer Note */}
          <div className="pt-3 text-center border-t border-slate-100 space-y-1">
            <p className="text-[10px] text-slate-500 font-medium">
              Mobiles and EasyPaisa Shop POS Security & Cloud System
            </p>
            <p className="text-[9px] text-slate-400">
              Only authorized staff credentials can access this system.
            </p>
          </div>

        </div>

      </div>
    </div>
  );
};

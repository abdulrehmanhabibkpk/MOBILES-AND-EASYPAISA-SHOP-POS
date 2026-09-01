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
  EyeOff,
  KeyRound,
  ArrowLeft,
  CheckCircle2
} from 'lucide-react';
import { loginWithEmailAndPassword, loginWithGoogle, sendPasswordResetEmail } from '../lib/firebase';

interface LoginScreenProps {
  shopName: string;
  onLoginSuccess: (userEmail: string, accountName: string) => void;
}

export const LoginScreen: React.FC<LoginScreenProps> = ({
  shopName,
  onLoginSuccess,
}) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isGoogleSubmitting, setIsGoogleSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [isForgotMode, setIsForgotMode] = useState(false);
  const [resetEmail, setResetEmail] = useState('');
  const [isResetting, setIsResetting] = useState(false);

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    const cleanEmail = email.trim().toLowerCase();
    const cleanPassword = password.trim();

    if (!cleanEmail || !cleanPassword) {
      setErrorMsg('Please enter both Email and Password!');
      return;
    }

    setIsSubmitting(true);

    try {
      const firebaseRes = await loginWithEmailAndPassword(cleanEmail, cleanPassword);
      if (firebaseRes && firebaseRes.user) {
        setIsSubmitting(false);
        onLoginSuccess(
          firebaseRes.user.email || cleanEmail, 
          firebaseRes.user.displayName || 'Authorized User'
        );
        return;
      }
      setIsSubmitting(false);
      setErrorMsg('Invalid Email or Password! Only Firebase registered accounts can log in.');
    } catch (err: any) {
      setIsSubmitting(false);
      console.error('Login error:', err);
      let msg = 'Login failed! Invalid email/password or account is not registered.';
      if (err.code === 'auth/invalid-credential' || err.code === 'auth/wrong-password' || err.code === 'auth/user-not-found') {
        msg = 'Invalid Email or Password! Please use your correct Firebase account.';
      } else if (err.code === 'auth/too-many-requests') {
        msg = 'Too many failed attempts. Please try again later.';
      }
      setErrorMsg(msg);
    }
  };

  const handleGoogleLogin = async () => {
    setErrorMsg('');
    setSuccessMsg('');
    setIsGoogleSubmitting(true);

    try {
      const result = await loginWithGoogle();
      if (result && result.user) {
        setIsGoogleSubmitting(false);
        onLoginSuccess(
          result.user.email || 'google-user@shop.com',
          result.user.displayName || 'Google Authorized User'
        );
        return;
      }
      setIsGoogleSubmitting(false);
      setErrorMsg('Google login failed.');
    } catch (err: any) {
      setIsGoogleSubmitting(false);
      console.error('Google login error:', err);
      let msg = 'An error occurred with Google login. Please try again.';
      if (err.code === 'auth/popup-closed-by-user') {
        msg = 'Google login popup was closed.';
      }
      setErrorMsg(msg);
    }
  };

  const handleForgotPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    const cleanResetEmail = resetEmail.trim().toLowerCase();
    if (!cleanResetEmail) {
      setErrorMsg('Please enter your registered email.');
      return;
    }

    setIsResetting(true);
    try {
      await sendPasswordResetEmail(cleanResetEmail);
      setIsResetting(false);
      setSuccessMsg('Password reset link has been sent to your email! Please check your inbox.');
    } catch (err: any) {
      setIsResetting(false);
      console.error('Password reset error:', err);
      let msg = 'Failed to send password reset email. Please check your email.';
      if (err.code === 'auth/user-not-found') {
        msg = 'No Firebase account is registered with this email.';
      }
      setErrorMsg(msg);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-white p-3 sm:p-6 overflow-y-auto font-sans">
      
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[32rem] h-[32rem] bg-emerald-600/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-1/4 left-1/2 -translate-x-1/2 w-96 h-96 bg-teal-600/5 rounded-full blur-3xl pointer-events-none" />

      <div className="w-full max-w-md bg-white border border-emerald-100 rounded-3xl shadow-2xl shadow-emerald-950/30 overflow-hidden text-slate-900 my-auto flex flex-col relative z-10">
        
        {/* Header Bar */}
        <div className="pt-7 pb-5 px-6 text-center border-b border-emerald-100 relative bg-gradient-to-b from-emerald-50 via-slate-50/50 to-white">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-600 text-white shadow-lg shadow-emerald-600/25 mb-3 border border-emerald-400/20">
            <Smartphone className="w-7 h-7 text-white" />
          </div>
          
          <h2 className="text-xl font-black text-slate-900 tracking-tight leading-snug">
            {shopName || 'Mobiles and EasyPaisa Shop POS'}
          </h2>
          
          <p className="text-xs text-emerald-700 font-bold mt-1 tracking-wide">
            CLOUD LIVE SYSTEM & SECURE LEDGER
          </p>

          <div className="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold">
            <ShieldCheck className="w-3.5 h-3.5 shrink-0 text-emerald-600" />
            <span>Firebase Registered Accounts Only</span>
          </div>
        </div>

        {/* Form Body */}
        <div className="p-5 sm:p-6 space-y-4">
          
          {errorMsg && (
            <div className="flex items-start gap-2.5 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold leading-relaxed animate-shake">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
              <span>{errorMsg}</span>
            </div>
          )}

          {successMsg && (
            <div className="flex items-start gap-2.5 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold leading-relaxed">
              <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5 text-emerald-600" />
              <span>{successMsg}</span>
            </div>
          )}

          {!isForgotMode ? (
            <div className="space-y-4">
              
              {/* Google Sign-In Button */}
              <button
                type="button"
                onClick={handleGoogleLogin}
                disabled={isGoogleSubmitting || isSubmitting}
                className="w-full py-3 px-4 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 font-bold text-xs sm:text-sm shadow-sm flex items-center justify-center gap-3 transition-all cursor-pointer disabled:opacity-60"
              >
                {isGoogleSubmitting ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin text-emerald-600" />
                    <span>Connecting Google...</span>
                  </>
                ) : (
                  <>
                    <svg className="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                      <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                      <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.13 0-5.78-2.11-6.73-4.96H1.19v3.15C3.17 21.36 7.23 24 12 24z"/>
                      <path fill="#FBBC05" d="M5.27 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.6H1.19C.43 8.13 0 9.87 0 11.7s.43 3.57 1.19 5.1l4.08-2.56z"/>
                      <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.23 0 3.17 2.64 1.19 6.6l4.08 3.15c.95-2.85 3.6-4.96 6.73-4.96z"/>
                    </svg>
                    <span>Sign in with Google</span>
                  </>
                )}
              </button>

              <div className="flex items-center my-3">
                <div className="flex-1 border-t border-slate-200" />
                <span className="px-3 text-[11px] text-slate-400 font-bold uppercase tracking-wider">or email</span>
                <div className="flex-1 border-t border-slate-200" />
              </div>

              <form onSubmit={handleEmailLogin} className="space-y-4">
                
                {/* Email Input */}
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                    <span>Email Address:</span>
                    <span className="text-[10px] text-slate-400 font-normal">Firebase Auth Email</span>
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
                  <div className="flex items-center justify-between mb-1.5">
                    <label className="text-xs font-bold text-slate-700">Password:</label>
                    <button
                      type="button"
                      onClick={() => {
                        setIsForgotMode(true);
                        setErrorMsg('');
                        setSuccessMsg('');
                        setResetEmail(email);
                      }}
                      className="text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:underline cursor-pointer"
                    >
                      Forgot Password?
                    </button>
                  </div>
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
                  disabled={isSubmitting || isGoogleSubmitting}
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
                      <span>Login</span>
                    </>
                  )}
                </button>

              </form>
            </div>
          ) : (
            <form onSubmit={handleForgotPassword} className="space-y-4 animate-fade-in">
              <div>
                <h3 className="text-sm font-bold text-slate-800 mb-1">Reset Password</h3>
                <p className="text-xs text-slate-500 mb-3">
                  Enter your registered email. We will send you a password reset link.
                </p>
                <label className="block text-xs font-bold text-slate-700 mb-1.5">Email Address:</label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <Mail className="w-4 h-4" />
                  </div>
                  <input
                    type="email"
                    required
                    value={resetEmail}
                    onChange={(e) => setResetEmail(e.target.value)}
                    placeholder="e.g. owner@mobile.com"
                    className="w-full pl-10 pr-3.5 py-3 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder-slate-400 text-sm font-medium focus:outline-none focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 transition-all"
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={isResetting}
                className="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-60"
              >
                {isResetting ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    <span>Sending Reset Link...</span>
                  </>
                ) : (
                  <>
                    <KeyRound className="w-4 h-4" />
                    <span>Send Password Reset Link</span>
                  </>
                )}
              </button>

              <div className="pt-2 text-center">
                <button
                  type="button"
                  onClick={() => {
                    setIsForgotMode(false);
                    setErrorMsg('');
                    setSuccessMsg('');
                  }}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 hover:text-emerald-700 cursor-pointer"
                >
                  <ArrowLeft className="w-3.5 h-3.5" />
                  <span>Back to Login</span>
                </button>
              </div>
            </form>
          )}

          {/* Footer Note */}
          <div className="pt-3 text-center border-t border-slate-100 space-y-1">
            <p className="text-[10px] text-slate-500 font-medium">
              Mobiles and EasyPaisa Shop POS Security & Cloud System
            </p>
            <p className="text-[9px] text-slate-400">
              Only authorized Firebase registered accounts can access this system.
            </p>
          </div>

        </div>

      </div>
    </div>
  );
};

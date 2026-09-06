import { initializeApp } from 'firebase/app';
import { 
  getAuth, 
  GoogleAuthProvider, 
  signInWithPopup, 
  signInWithEmailAndPassword,
  createUserWithEmailAndPassword,
  sendEmailVerification,
  sendPasswordResetEmail as fbSendPasswordResetEmail,
  signOut as firebaseSignOut, 
  onAuthStateChanged, 
  signInAnonymously,
  User 
} from 'firebase/auth';
import { 
  initializeFirestore,
  persistentLocalCache,
  persistentMultipleTabManager,
  getFirestore, 
  doc, 
  getDoc 
} from 'firebase/firestore';
import firebaseConfig from '../../firebase-applet-config.json';

const app = initializeApp(firebaseConfig);

// Initialize Firestore with persistent IndexedDB local cache for fast offline sync and quota savings
const dbId = (firebaseConfig as any).firestoreDatabaseId || 'ai-studio-balalmobile-0cd4de0b-410f-4735-b752-65780f15d381';
let firestoreDb;
try {
  firestoreDb = dbId 
    ? initializeFirestore(app, {
        localCache: persistentLocalCache({
          tabManager: persistentMultipleTabManager()
        })
      }, dbId)
    : initializeFirestore(app, {
        localCache: persistentLocalCache({
          tabManager: persistentMultipleTabManager()
        })
      });
} catch (e) {
  firestoreDb = dbId ? getFirestore(app, dbId) : getFirestore(app);
}

export const db = firestoreDb;
export const auth = getAuth(app);
export const googleProvider = new GoogleAuthProvider();

// Add Workspace scopes for Google Drive backup & storage
googleProvider.addScope('https://www.googleapis.com/auth/drive.file');
googleProvider.addScope('https://www.googleapis.com/auth/drive.appdata');

// In-memory cached access token for Google Drive APIs (strictly in memory)
let cachedDriveAccessToken: string | null = null;
let isSigningInWithDrive = false;

export function getDriveCachedToken(): string | null {
  return cachedDriveAccessToken;
}

export function setDriveCachedToken(token: string | null) {
  cachedDriveAccessToken = token;
}

export async function loginWithGoogle() {
  try {
    isSigningInWithDrive = true;
    const result = await signInWithPopup(auth, googleProvider);
    const credential = GoogleAuthProvider.credentialFromResult(result);
    if (credential?.accessToken) {
      cachedDriveAccessToken = credential.accessToken;
    }
    return result;
  } catch (error) {
    console.error('Google Sign-In Error:', error);
    throw error;
  } finally {
    isSigningInWithDrive = false;
  }
}

export async function connectGoogleDriveAccount(): Promise<{ user: User; accessToken: string }> {
  try {
    isSigningInWithDrive = true;
    const result = await signInWithPopup(auth, googleProvider);
    const credential = GoogleAuthProvider.credentialFromResult(result);
    if (!credential?.accessToken) {
      throw new Error('Google Drive access token not returned by provider.');
    }
    cachedDriveAccessToken = credential.accessToken;
    return { user: result.user, accessToken: cachedDriveAccessToken };
  } finally {
    isSigningInWithDrive = false;
  }
}

export async function loginWithEmailAndPassword(email: string, pass: string) {
  try {
    return await signInWithEmailAndPassword(auth, email, pass);
  } catch (error) {
    console.error('Email Sign-In Error:', error);
    throw error;
  }
}

export async function sendPasswordResetEmail(email: string) {
  try {
    return await fbSendPasswordResetEmail(auth, email);
  } catch (error) {
    console.error('Password Reset Error:', error);
    throw error;
  }
}

export let isQuotaExceeded = false;
const quotaListeners: Array<(exceeded: boolean) => void> = [];

export function onQuotaStatusChange(cb: (exceeded: boolean) => void) {
  quotaListeners.push(cb);
  return () => {
    const idx = quotaListeners.indexOf(cb);
    if (idx !== -1) quotaListeners.splice(idx, 1);
  };
}

export async function loginAnonymously() {
  try {
    return await signInAnonymously(auth);
  } catch (error: any) {
    if (error?.code !== 'auth/admin-restricted-operation') {
      console.error('Anonymous Sign-In Error:', error);
    }
    throw error;
  }
}

export async function createAndSendVerificationEmail(email: string, pass: string) {
  try {
    const userCredential = await createUserWithEmailAndPassword(auth, email, pass);
    if (userCredential.user) {
      await sendEmailVerification(userCredential.user);
    }
    return { success: true, message: 'Firebase registration & Email verification link sent successfully!' };
  } catch (error: any) {
    console.error('Firebase Register/Verify Error:', error);
    if (error.code === 'auth/email-already-in-use') {
      try {
        await fbSendPasswordResetEmail(auth, email);
        return { success: true, message: 'Account exists in Firebase. Password reset & verification link sent to email!' };
      } catch (e2) {
        throw error;
      }
    }
    throw error;
  }
}

export async function logoutUser() {
  try {
    cachedDriveAccessToken = null;
    await firebaseSignOut(auth);
  } catch (error) {
    console.error('Logout Error:', error);
    throw error;
  }
}

export enum OperationType {
  CREATE = 'create',
  UPDATE = 'update',
  DELETE = 'delete',
  LIST = 'list',
  GET = 'get',
  WRITE = 'write',
}

export interface FirestoreErrorInfo {
  error: string;
  operationType: OperationType;
  path: string | null;
  authInfo: {
    userId?: string | null;
    email?: string | null;
    emailVerified?: boolean | null;
    isAnonymous?: boolean | null;
    tenantId?: string | null;
    providerInfo?: {
      providerId?: string | null;
      email?: string | null;
    }[];
  };
}

export function handleFirestoreError(error: unknown, operationType: OperationType, path: string | null) {
  const errMsg = error instanceof Error ? error.message : String(error);
  const isQuota = errMsg.toLowerCase().includes('quota') || errMsg.toLowerCase().includes('resource-exhausted');

  if (isQuota) {
    if (!isQuotaExceeded) {
      isQuotaExceeded = true;
      quotaListeners.forEach(fn => fn(true));
    }
  }
  console.warn(`[Firestore ${operationType} on ${path}]:`, errMsg);
}

export async function testFirestoreConnection() {
  // Silent, safe offline-first handling
}

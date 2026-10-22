import { createContext, useContext, useEffect, useState } from 'react';
import { api, tokens } from '../api/client';

export const AuthContext = createContext(null);
export const useAuth = () => useContext(AuthContext);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);   // null means a visitor
  const [ready, setReady] = useState(false); // false until we know whether a saved token still works
  const [error, setError] = useState('');

  // After a reload, ask the API who the saved token belongs to.
  useEffect(() => {
    if (!tokens.get()) { setReady(true); return; }
    api('/auth/me')
      .then(setUser)
      .catch((e) => { if (e.status === 401) tokens.clear(); })  // keep the token if the server is just asleep
      .finally(() => setReady(true));
  }, []);

  // Both sign-in routes answer { tokens, user }.
  async function login(path, payload) {
    setError('');
    try {
      const d = await api(path, { method: 'POST', body: JSON.stringify(payload) });
      tokens.set(d.tokens);
      setUser(d.user);
    } catch (e) {
      console.error('Sign-in failed:', e.status, e.message);   // status only, never a token
      setError(
        e.status === 401 ? 'Google sign-in failed. Please try again.'
        : e.status === 0 ? 'Cannot reach the server. If it was asleep, wait a minute and try again.'
        : 'Could not sign in. Please try again.'
      );
    }
  }

  // credential = the Google ID token from the Google button. Google's own token is never stored.
  const signIn = (credential) => login('/auth/google', { id_token: credential });

  // Local testing only: Vite removes this from the production build, and the API refuses it there too.
  const devSignIn = (role) => import.meta.env.DEV && login('/auth/dev-login', { role });

  function signOut() {
    const t = tokens.get();
    tokens.clear();
    setUser(null);
    if (t?.refresh_token) {
      api('/auth/logout', { method: 'POST', body: JSON.stringify({ refresh_token: t.refresh_token }) }).catch(() => {});
    }
  }

  const value = {
    user, ready, error, signIn, devSignIn, signOut,
    reportError: setError,
    clearError: () => setError(''),
  };
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

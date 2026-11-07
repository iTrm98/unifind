import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { fieldError } from '../api.js';
import { homeFor, useAuth } from '../auth.jsx';
import { Button, ButtonLink, Field, Hero, useToast } from '../components/ui.jsx';

// Demo sign-in details shown under the form. Edit or empty this list to change them.
const DEMO_ACCOUNTS = [
  { label: 'Admin', email: 'admin@uqu.edu.sa', password: 'asd' },
  { label: 'Student', email: 'student@uqu.edu.sa', password: 'asd' },
];

function allowedFor(user, path) {
  if (!path) return false;
  return user.role === 'admin' ? path.startsWith('/admin') : path.startsWith('/app');
}

export function Login() {
  const { user, login, loading } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const toast = useToast();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [busy, setBusy] = useState(false);

  const from = location.state?.from;

  if (!loading && user) return <Navigate to={allowedFor(user, from) ? from : homeFor(user)} replace />;

  async function signIn(credentials) {
    setBusy(true);
    setErrors({});
    setFormError('');
    try {
      const signedIn = await login(credentials.email, credentials.password);
      toast.success(`Signed in as ${signedIn.name}.`);
      navigate(allowedFor(signedIn, from) ? from : homeFor(signedIn), { replace: true });
    } catch (error) {
      if (error.status === 422 && error.errors) {
        setErrors({ email: fieldError(error.errors, 'email'), password: fieldError(error.errors, 'password') });
      } else {
        setFormError(error.message);
      }
      toast.error(error.message);
      setBusy(false);
    }
  }

  function submit(event) {
    event.preventDefault();
    const local = {};
    if (!email.trim()) local.email = 'Enter your university email.';
    if (!password) local.password = 'Enter your password.';
    if (local.email || local.password) { setErrors(local); return; }
    signIn({ email, password });
  }

  // The sign-in page as it was before the prototype redesign: a navy story panel and a sign-in card.
  const input = 'mt-1.5 min-h-11 rounded-[6px] border-[#bdccd4] px-3';

  return (
    <div className="grid min-h-screen bg-[#f2f5f7] min-[861px]:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
      <section className="on-navy flex flex-col justify-between gap-4 bg-navy p-6 text-[#d3e8ed] min-[861px]:gap-8 min-[861px]:px-[clamp(24px,5vw,72px)] min-[861px]:py-8">
        <Link to="/login" className="inline-flex items-center gap-2 self-start text-[1.45rem] font-extrabold tracking-[-0.02em] text-white no-underline">
          <svg viewBox="0 0 32 32" aria-hidden="true" className="size-7">
            <circle cx="14" cy="14" r="8" fill="none" stroke="currentColor" strokeWidth="3" />
            <path d="M20 20l6.5 6.5" stroke="#5fc3cf" strokeWidth="3.6" strokeLinecap="round" />
          </svg>
          UniFind
        </Link>
        <div className="flex max-w-[34rem] flex-col gap-4">
          <p className="text-[clamp(2.6rem,6vw,5rem)] leading-[0.95] font-extrabold tracking-[-0.045em] text-white">Find what matters.</p>
          <p className="text-card">The campus Lost &amp; Found Office, online. Check what has been handed in, report what you lost, and get told when it turns up.</p>
        </div>
        <p className="hidden text-sm text-[#9fc4ce] min-[861px]:block">Student Affairs building, room 12</p>
      </section>

      <main id="main" className="grid place-items-center px-6 py-8">
        <div className="flex w-full max-w-[400px] flex-col gap-4">
          <h1 className="text-[2.25rem] leading-[1.15] font-extrabold tracking-[-0.01em]">Sign in</h1>
          <p className="text-sm text-[#445d69]">Use your university account. Accounts are created by the Lost &amp; Found Office.</p>

          <form onSubmit={submit} noValidate className="flex flex-col gap-4">
            {formError && <p className="rounded-[6px] bg-[#fdecec] p-3 text-sm text-[#7d1f1f]" role="alert">{formError}</p>}
            <Field label="Email" error={errors.email}>
              {(p) => <input {...p} className={input} type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="name@uqu.edu.sa" />}
            </Field>
            <Field label="Password" error={errors.password}>
              {(p) => <input {...p} className={input} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />}
            </Field>
            <Button type="submit" className="min-h-[42px] w-full rounded-[6px] text-sm font-semibold" busy={busy} disabled={busy}>Sign in</Button>
          </form>

          {DEMO_ACCOUNTS.length > 0 && (
            <section aria-labelledby="demo-accounts" className="rounded-[6px] border border-[#d5e0e5] bg-white p-3">
              <h2 id="demo-accounts" className="text-xs font-semibold tracking-[0.04em] text-[#445d69] uppercase">Demo accounts</h2>
              <p className="mt-0.5 text-xs text-[#5b7280]">Choose one to fill in the form, then press Sign in.</p>
              <ul className="mt-2 flex flex-col gap-2">
                {DEMO_ACCOUNTS.map((account) => (
                  <li key={account.email}>
                    <button type="button"
                      onClick={() => { setEmail(account.email); setPassword(account.password); setErrors({}); setFormError(''); }}
                      className="flex min-h-11 w-full cursor-pointer flex-wrap items-center justify-between gap-x-3 gap-y-0.5 rounded-[6px] border border-[#d5e0e5] bg-[#f8fafb] px-3 py-2 text-left text-sm transition-colors hover:border-[#bdccd4] hover:bg-[#eef3f5]">
                      <span className="font-semibold">{account.label}</span>
                      <span className="min-w-0 break-all text-[#445d69]">{account.email} <span aria-hidden="true">/</span><span className="sr-only">, password</span> {account.password}</span>
                    </button>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      </main>
    </div>
  );
}

function StatusPage({ code, title, children }) {
  const { user } = useAuth();
  return (
    <Hero title={title} text={children} eyebrow={code}
      action={<ButtonLink to={homeFor(user)}>{user ? 'Go to my home page' : 'Go to sign in'}</ButtonLink>} />
  );
}

export function Forbidden() {
  return (
    <StatusPage code="403" title="This page is for the Lost & Found Office">
      Your account does not have access to it. If you think it should, contact the office at Student Affairs.
    </StatusPage>
  );
}

export function NotFound() {
  return (
    <StatusPage code="404" title="We could not find that page">
      The link may be old or mistyped.
    </StatusPage>
  );
}

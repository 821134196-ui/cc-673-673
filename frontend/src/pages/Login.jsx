import { useState } from 'react';
import { useAuth } from '../auth.jsx';

export default function Login() {
  const { login } = useAuth();
  const [email, setEmail] = useState('tech@lab.local');
  const [password, setPassword] = useState('password');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await login(email, password);
    } catch (err) {
      setError(err.data?.errors?.email?.[0] || err.message);
    } finally {
      setBusy(false);
    }
  }

  function quick(role) {
    setEmail(role === 'lead' ? 'lead@lab.local' : 'tech@lab.local');
    setPassword('password');
  }

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={submit}>
        <h1>🧫 培养皿污染隔离与观察系统</h1>
        <p className="muted">实验室培养箱污染样本隔离 · 同箱暴露追溯 · 复检观察</p>

        <label>账号邮箱</label>
        <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />

        <label>密码</label>
        <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required />

        {error && <div className="alert alert-error">{error}</div>}

        <button className="btn btn-primary btn-block" disabled={busy}>
          {busy ? '登录中…' : '登 录'}
        </button>

        <div className="quick-login">
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => quick('tech')}>实验员演示账号</button>
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => quick('lead')}>负责人演示账号</button>
        </div>
        <div className="muted small">演示密码均为 password</div>
      </form>
    </div>
  );
}

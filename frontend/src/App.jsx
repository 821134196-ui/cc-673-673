import { NavLink, Route, Routes, Navigate } from 'react-router-dom';
import { useAuth } from './auth.jsx';
import Login from './pages/Login.jsx';
import Samples from './pages/Samples.jsx';
import SampleDetail from './pages/SampleDetail.jsx';
import Reports from './pages/Reports.jsx';
import ReportDetail from './pages/ReportDetail.jsx';
import Reminders from './pages/Reminders.jsx';

export default function App() {
  const { user, loading, logout } = useAuth();

  if (loading) return <div className="center-loading">加载中…</div>;
  if (!user) return <Login />;

  const navItem = ({ isActive }) => (isActive ? 'nav-item active' : 'nav-item');

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">🧫 污染隔离观察</div>
        <nav>
          <NavLink to="/samples" className={navItem}>样本与培养箱</NavLink>
          <NavLink to="/reports" className={navItem}>污染登记与判定</NavLink>
          <NavLink to="/reminders" className={navItem}>复检提醒</NavLink>
        </nav>
        <div className="user-box">
          <div className="user-name">{user.display_name || user.name}</div>
          <div className="user-role">{user.role === 'supervisor' ? '负责人' : '实验员'}</div>
          <button className="btn btn-ghost btn-sm" onClick={logout}>退出登录</button>
        </div>
      </aside>

      <main className="content">
        <Routes>
          <Route path="/" element={<Navigate to="/samples" replace />} />
          <Route path="/samples" element={<Samples />} />
          <Route path="/samples/:id" element={<SampleDetail />} />
          <Route path="/reports" element={<Reports />} />
          <Route path="/reports/:id" element={<ReportDetail />} />
          <Route path="/reminders" element={<Reminders />} />
          <Route path="*" element={<Navigate to="/samples" replace />} />
        </Routes>
      </main>
    </div>
  );
}

import { useEffect, useState } from 'react'
import { Link, NavLink, Route, Routes } from 'react-router-dom'
import { api, getActorId, setActorId } from './api.js'
import SamplesPage from './pages/SamplesPage.jsx'
import SampleDetailPage from './pages/SampleDetailPage.jsx'
import IncubatorsPage from './pages/IncubatorsPage.jsx'
import IsolationsPage from './pages/IsolationsPage.jsx'
import IsolationDetailPage from './pages/IsolationDetailPage.jsx'
import RemindersPage from './pages/RemindersPage.jsx'

export default function App() {
  const [actors, setActors] = useState([])
  const [actorId, setActorIdState] = useState(getActorId())

  useEffect(() => {
    api('/actors').then((r) => {
      setActors(r.data)
      if (!actorId && r.data.length) {
        setActorId(r.data[0].id)
        setActorIdState(String(r.data[0].id))
      }
    }).catch(() => {})
  }, [])

  const current = actors.find((a) => String(a.id) === String(actorId))

  return (
    <div className="app">
      <header className="topbar">
        <div className="brand">
          <Link to="/">🧫 培养皿污染隔离与观察系统</Link>
        </div>
        <nav className="nav">
          <NavLink to="/" end>样本</NavLink>
          <NavLink to="/incubators">培养箱与存放</NavLink>
          <NavLink to="/isolations">隔离登记</NavLink>
          <NavLink to="/reminders">复检提醒</NavLink>
        </nav>
        <label className="actor">
          当前身份
          <select
            value={actorId}
            onChange={(e) => { setActorId(e.target.value); setActorIdState(e.target.value) }}
          >
            {actors.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}（{a.role === 'manager' ? '负责人' : '实验员'}）
              </option>
            ))}
          </select>
        </label>
      </header>

      {current?.role === 'manager' && (
        <div className="role-hint">负责人模式：可在隔离登记详情中判定同箱暴露样本</div>
      )}

      <main className="content">
        <Routes>
          <Route path="/" element={<SamplesPage />} />
          <Route path="/samples/:id" element={<SampleDetailPage />} />
          <Route path="/incubators" element={<IncubatorsPage />} />
          <Route path="/isolations" element={<IsolationsPage />} />
          <Route path="/isolations/:id" element={<IsolationDetailPage />} />
          <Route path="/reminders" element={<RemindersPage />} />
        </Routes>
      </main>
    </div>
  )
}

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api.js'
import { StatusBadge, fmtDate } from '../components/badges.jsx'

export default function RemindersPage() {
  const [items, setItems] = useState([])
  const [cmd] = useState('php artisan lab:retest-remind')

  useEffect(() => {
    api('/reminders').then((r) => setItems(r.data))
  }, [])

  return (
    <div>
      <h2>复检提醒（本地模拟）</h2>
      <div className="card">
        <p className="muted small">
          外部通知通道未接入，本系统只做本地模拟：页面轮询 <code>GET /api/reminders</code>，
          并提供本地命令输出相同内容：
        </p>
        <pre className="cmd">$ {cmd}</pre>
      </div>

      <table className="table card">
        <thead><tr><th>样本</th><th>状态</th><th>复检截止日</th><th>剩余/逾期</th><th>提醒内容</th></tr></thead>
        <tbody>
          {items.map((r) => (
            <tr key={r.sample_id} className={r.overdue ? 'row-overdue' : ''}>
              <td><Link to={`/samples/${r.sample_id}`}>{r.code}</Link></td>
              <td><StatusBadge status={r.status} label={r.status_label} /></td>
              <td>{fmtDate(r.retest_due_at)}</td>
              <td className={r.overdue ? 'overdue' : ''}>
                {r.overdue ? `逾期 ${Math.abs(r.days_left)} 天` : `剩 ${r.days_left} 天`}
              </td>
              <td className="small">{r.message}</td>
            </tr>
          ))}
          {items.length === 0 && <tr><td colSpan="5" className="empty">暂无临近截止的样本</td></tr>}
        </tbody>
      </table>
    </div>
  )
}

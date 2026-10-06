import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api.js'
import { StatusBadge, fmtDate } from '../components/badges.jsx'

const STATUS_FILTERS = [
  ['', '全部'],
  ['in_stock', '在库'],
  ['quarantined', '隔离中'],
  ['frozen', '冻结'],
  ['released', '已放行'],
  ['discarded', '已废弃'],
]

export default function SamplesPage() {
  const [samples, setSamples] = useState([])
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ code: '', batch_no: '', name: '', description: '' })
  const [error, setError] = useState('')

  const load = () => {
    const params = new URLSearchParams()
    if (q) params.set('q', q)
    if (status) params.set('status', status)
    api(`/samples?${params}`).then((r) => setSamples(r.data))
  }

  useEffect(load, [status])

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    try {
      await api('/samples', { method: 'POST', body: form })
      setForm({ code: '', batch_no: '', name: '', description: '' })
      setShowForm(false)
      load()
    } catch (err) {
      setError(err.errors ? Object.values(err.errors).flat().join('；') : err.message)
    }
  }

  return (
    <div>
      <div className="page-head">
        <h2>样本（培养皿）</h2>
        <button className="btn primary" onClick={() => setShowForm((v) => !v)}>
          {showForm ? '收起' : '＋ 登记样本'}
        </button>
      </div>

      {showForm && (
        <form className="card form-grid" onSubmit={submit}>
          <label>样本编号 *<input value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="P-006" /></label>
          <label>原批次号 *<input value={form.batch_no} onChange={(e) => setForm({ ...form, batch_no: e.target.value })} placeholder="B20261003" /></label>
          <label>名称<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
          <label className="span2">说明<input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
          {error && <div className="error span2">{error}</div>}
          <div className="span2"><button className="btn primary" type="submit">保存</button></div>
        </form>
      )}

      <div className="toolbar">
        <input className="search" placeholder="搜索编号 / 名称 / 批次" value={q}
          onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} />
        <button className="btn" onClick={load}>搜索</button>
        <div className="filters">
          {STATUS_FILTERS.map(([v, t]) => (
            <button key={v} className={`chip ${status === v ? 'on' : ''}`} onClick={() => setStatus(v)}>{t}</button>
          ))}
        </div>
      </div>

      <div className="grid">
        {samples.map((s) => (
          <Link key={s.id} to={`/samples/${s.id}`} className={`sample-card status-${s.status}`}>
            <div className="sample-head">
              <strong>{s.code}</strong>
              <StatusBadge status={s.status} label={s.status_label} />
            </div>
            <div className="muted">{s.name || '—'}</div>
            <div className="sample-meta">
              <span>批次 {s.batch_no}</span>
              <span>观察记录 {s.observations_count}</span>
            </div>
            {(s.status === 'quarantined' || s.status === 'frozen') && (
              <div className={`retest ${s.retest_overdue ? 'overdue' : ''}`}>
                ⏳ 复检截止日：{fmtDate(s.retest_due_at)}
                {s.retest_overdue && ' · 已逾期'}
              </div>
            )}
          </Link>
        ))}
        {samples.length === 0 && <div className="empty">暂无样本</div>}
      </div>
    </div>
  )
}

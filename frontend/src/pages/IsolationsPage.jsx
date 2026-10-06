import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api.js'
import { StatusBadge, fmt } from '../components/badges.jsx'

export default function IsolationsPage() {
  const navigate = useNavigate()
  const [events, setEvents] = useState([])
  const [samples, setSamples] = useState([])
  const [incubators, setIncubators] = useState([])
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({
    source_sample_id: '', incubator_id: '',
    suspicious_started_at: '', suspicious_ended_at: '',
    reason: '', retest_due_at: '',
  })
  const [error, setError] = useState('')

  const load = () => {
    api('/isolations').then((r) => setEvents(r.data))
    api('/samples').then((r) => setSamples(r.data))
    api('/incubators').then((r) => setIncubators(r.data))
  }
  useEffect(load, [])

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    try {
      const r = await api('/isolations', { method: 'POST', body: form })
      setShowForm(false)
      setForm({ source_sample_id: '', incubator_id: '', suspicious_started_at: '', suspicious_ended_at: '', reason: '', retest_due_at: '' })
      load()
      navigate(`/isolations/${r.data.id}`)
    } catch (err) {
      setError(err.errors ? Object.values(err.errors).flat().join('；') : err.message)
    }
  }

  return (
    <div>
      <div className="page-head">
        <h2>隔离登记（实验员）</h2>
        <button className="btn primary" onClick={() => setShowForm((v) => !v)}>
          {showForm ? '收起' : '＋ 发现疑似污染，登记隔离'}
        </button>
      </div>

      {showForm && (
        <form className="card form-grid" onSubmit={submit}>
          <label>疑似污染样本 *
            <select value={form.source_sample_id} onChange={(e) => setForm({ ...form, source_sample_id: e.target.value })}>
              <option value="">请选择</option>
              {samples.map((s) => <option key={s.id} value={s.id}>{s.code}（{s.batch_no}）</option>)}
            </select>
          </label>
          <label>所在培养箱 *
            <select value={form.incubator_id} onChange={(e) => setForm({ ...form, incubator_id: e.target.value })}>
              <option value="">请选择</option>
              {incubators.map((i) => <option key={i.id} value={i.id}>{i.code} {i.name}</option>)}
            </select>
          </label>
          <label>可疑时间段开始 *<input type="datetime-local" value={form.suspicious_started_at} onChange={(e) => setForm({ ...form, suspicious_started_at: e.target.value })} /></label>
          <label>可疑时间段结束 *<input type="datetime-local" value={form.suspicious_ended_at} onChange={(e) => setForm({ ...form, suspicious_ended_at: e.target.value })} /></label>
          <label className="span2">复检截止日（留空取系统默认 7 天）<input type="datetime-local" value={form.retest_due_at} onChange={(e) => setForm({ ...form, retest_due_at: e.target.value })} /></label>
          <label className="span2">污染迹象说明<textarea rows="2" value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></label>
          {error && <div className="error span2">{error}</div>}
          <div className="span2"><button className="btn primary" type="submit">登记并立即隔离、筛同箱样本</button></div>
        </form>
      )}

      <table className="table card">
        <thead>
          <tr><th>#</th><th>来源样本</th><th>培养箱</th><th>可疑时间段</th><th>同箱候选</th><th>登记人</th><th></th></tr>
        </thead>
        <tbody>
          {events.map((e) => (
            <tr key={e.id}>
              <td>{e.id}</td>
              <td>{e.source_sample?.code} <StatusBadge status={e.source_sample?.status} label={e.source_sample?.status_label} /></td>
              <td>{e.incubator?.code}</td>
              <td className="small">{fmt(e.suspicious_started_at)}<br />～ {fmt(e.suspicious_ended_at)}</td>
              <td>{e.exposure_records_count}</td>
              <td>{e.registered_by}</td>
              <td><Link className="btn small" to={`/isolations/${e.id}`}>查看 / 判定</Link></td>
            </tr>
          ))}
          {events.length === 0 && <tr><td colSpan="7" className="muted">尚无隔离登记</td></tr>}
        </tbody>
      </table>
    </div>
  )
}

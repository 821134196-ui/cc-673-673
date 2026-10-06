import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { fmt } from '../components/badges.jsx'

export default function IncubatorsPage() {
  const [incubators, setIncubators] = useState([])
  const [samples, setSamples] = useState([])
  const [incForm, setIncForm] = useState({ code: '', name: '', location: '' })
  const [evForm, setEvForm] = useState({ sample_id: '', incubator_id: '', started_at: '', ended_at: '' })
  const [error, setError] = useState('')

  const load = () => {
    api('/incubators').then((r) => setIncubators(r.data))
    api('/samples').then((r) => setSamples(r.data))
  }
  useEffect(load, [])

  const addIncubator = async (e) => {
    e.preventDefault()
    setError('')
    try {
      await api('/incubators', { method: 'POST', body: incForm })
      setIncForm({ code: '', name: '', location: '' })
      load()
    } catch (err) {
      setError(err.errors ? Object.values(err.errors).flat().join('；') : err.message)
    }
  }

  const addEvent = async (e) => {
    e.preventDefault()
    setError('')
    try {
      await api('/storage-events', { method: 'POST', body: evForm })
      setEvForm({ sample_id: '', incubator_id: '', started_at: '', ended_at: '' })
      setError('存放记录已登记')
    } catch (err) {
      setError(err.errors ? Object.values(err.errors).flat().join('；') : err.message)
    }
  }

  return (
    <div>
      <h2>培养箱与存放记录</h2>
      {error && <div className="error box">{error}</div>}

      <div className="two-col">
        <section className="card">
          <h3>培养箱</h3>
          <form onSubmit={addIncubator} className="form-grid">
            <label>编号 *<input value={incForm.code} onChange={(e) => setIncForm({ ...incForm, code: e.target.value })} placeholder="INC-C3" /></label>
            <label>名称 *<input value={incForm.name} onChange={(e) => setIncForm({ ...incForm, name: e.target.value })} /></label>
            <label className="span2">位置<input value={incForm.location} onChange={(e) => setIncForm({ ...incForm, location: e.target.value })} /></label>
            <div className="span2"><button className="btn primary">新增培养箱</button></div>
          </form>
          <ul className="plain-list">
            {incubators.map((i) => (
              <li key={i.id}><b>{i.code}</b> {i.name} <span className="muted small">{i.location}</span></li>
            ))}
          </ul>
        </section>

        <section className="card">
          <h3>登记存放时间段</h3>
          <p className="muted small">
            系统按「样本在某培养箱的实际存放区间」判断同箱暴露。结束时间留空表示仍在箱中。
            <b>可疑时间段结束后才放入的样本不会被算作同箱暴露。</b>
          </p>
          <form onSubmit={addEvent} className="form-grid">
            <label>样本 *
              <select value={evForm.sample_id} onChange={(e) => setEvForm({ ...evForm, sample_id: e.target.value })}>
                <option value="">请选择</option>
                {samples.map((s) => <option key={s.id} value={s.id}>{s.code}</option>)}
              </select>
            </label>
            <label>培养箱 *
              <select value={evForm.incubator_id} onChange={(e) => setEvForm({ ...evForm, incubator_id: e.target.value })}>
                <option value="">请选择</option>
                {incubators.map((i) => <option key={i.id} value={i.id}>{i.code}</option>)}
              </select>
            </label>
            <label>放入时间 *<input type="datetime-local" value={evForm.started_at} onChange={(e) => setEvForm({ ...evForm, started_at: e.target.value })} /></label>
            <label>取出时间<input type="datetime-local" value={evForm.ended_at} onChange={(e) => setEvForm({ ...evForm, ended_at: e.target.value })} /></label>
            <div className="span2"><button className="btn primary">登记存放</button></div>
          </form>
        </section>
      </div>
    </div>
  )
}

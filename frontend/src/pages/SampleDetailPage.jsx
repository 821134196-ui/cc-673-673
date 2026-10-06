import { useCallback, useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api, toLocalInput } from '../api.js'
import { StatusBadge, fmt } from '../components/badges.jsx'

const TYPE_LABEL = {
  observation: '观察',
  retest_pass: '复检通过',
  retest_fail: '复检未通过',
  discard: '废弃',
}

export default function SampleDetailPage() {
  const { id } = useParams()
  const [sample, setSample] = useState(null)
  const [error, setError] = useState('')
  const [ok, setOk] = useState('')
  const [type, setType] = useState('observation')
  const [notes, setNotes] = useState('')
  const [due, setDue] = useState('')
  const [method, setMethod] = useState('高压灭菌 121℃ 20min')
  const [disposedBy, setDisposedBy] = useState('')
  const [experiment, setExperiment] = useState('')
  const fileRef = useRef(null)

  const load = useCallback(() => {
    api(`/samples/${id}`).then((r) => setSample(r.data))
  }, [id])
  useEffect(load, [load])

  if (!sample) return <div className="empty">加载中…</div>

  const submitConclusion = async (e) => {
    e.preventDefault()
    setError(''); setOk('')
    const fd = new FormData()
    fd.append('type', type)
    fd.append('notes', notes)
    if (type === 'retest_fail' || type === 'observation') fd.append('retest_due_at', due)
    if (type === 'discard') {
      fd.append('disposal_method', method)
      fd.append('disposed_by', disposedBy)
    }
    if (fileRef.current?.files) {
      [...fileRef.current.files].forEach((f) => fd.append('photos[]', f))
    }
    try {
      await api(`/samples/${id}/observations`, { method: 'POST', body: fd })
      setNotes(''); setDue(''); setDisposedBy('')
      if (fileRef.current) fileRef.current.value = ''
      setOk('结论已登记')
      load()
    } catch (err) {
      setError(err.errors ? Object.values(err.errors).flat().join('；') : err.message)
    }
  }

  const checkout = async () => {
    setError(''); setOk('')
    try {
      await api(`/samples/${id}/checkout`, { method: 'POST', body: { experiment_name: experiment } })
      setOk(`已领用至「${experiment}」`)
      setExperiment('')
      load()
    } catch (err) {
      // 409 = 隔离/冻结/废弃拦截
      setError(err.status === 409 ? `⛔ 领用被拦截：${err.message}` : err.message)
    }
  }

  const frozen = sample.status === 'quarantined' || sample.status === 'frozen'

  return (
    <div>
      <div className="page-head">
        <div>
          <h2>{sample.code} <StatusBadge status={sample.status} label={sample.status_label} /></h2>
          <div className="muted">{sample.name || '未命名'} · 原批次 <b>{sample.batch_no}</b>（批次永久保留）</div>
        </div>
      </div>

      {/* 隔离状态与复检截止日突出显示 */}
      <div className={`isolation-banner status-${sample.status}`}>
        {sample.status === 'in_stock' && <span>✅ 样本在库，可正常领用</span>}
        {sample.status === 'released' && <span>✅ 复检通过，已放行，可正常领用</span>}
        {sample.status === 'quarantined' && (
          <span>⛔ 隔离观察中 —— 禁止领用到后续实验。复检截止日：
            <b>{fmt(sample.retest_due_at)}</b>{sample.retest_overdue && <em className="overdue">（已逾期）</em>}
          </span>
        )}
        {sample.status === 'frozen' && (
          <span>🧊 复检未通过，继续冻结 —— 禁止领用。下次复检截止日：
            <b>{fmt(sample.retest_due_at)}</b>{sample.retest_overdue && <em className="overdue">（已逾期）</em>}
          </span>
        )}
        {sample.status === 'discarded' && (
          <span>🗑 样本已废弃，观察历史与原批次记录保留；不可再登记结论或领用</span>
        )}
      </div>

      {error && <div className="error box">{error}</div>}
      {ok && <div className="success box">{ok}</div>}

      <div className="two-col">
        <section className="card">
          <h3>存放历史</h3>
          <table className="table">
            <thead><tr><th>培养箱</th><th>放入</th><th>取出</th></tr></thead>
            <tbody>
              {sample.storage_events.map((e) => (
                <tr key={e.id}>
                  <td>{e.incubator.code} {e.incubator.name}</td>
                  <td>{fmt(e.started_at)}</td>
                  <td>{fmt(e.ended_at)} {!e.ended_at && <span className="tag">在箱中</span>}</td>
                </tr>
              ))}
              {sample.storage_events.length === 0 && <tr><td colSpan="3" className="muted">暂无存放记录</td></tr>}
            </tbody>
          </table>

          <h3>领用（后续实验）</h3>
          <div className="inline">
            <input placeholder="实验名称，如 蛋白表达批次 12" value={experiment}
              onChange={(e) => setExperiment(e.target.value)} disabled={!sample.usable} />
            <button className="btn primary" onClick={checkout} disabled={!sample.usable || !experiment}>
              领用
            </button>
          </div>
          {!sample.usable && <div className="muted small">当前不可领用：{sample.block_reason}</div>}
          <ul className="usage-list">
            {sample.usages.map((u) => (
              <li key={u.id}>{fmt(u.used_at)} → {u.experiment_name}</li>
            ))}
          </ul>
        </section>

        <section className="card">
          <h3>登记本样本结论（相互独立）</h3>
          {sample.status === 'discarded' ? (
            <div className="muted">样本已废弃，仅可查看历史。</div>
          ) : (
            <form onSubmit={submitConclusion}>
              <div className="type-switch">
                {Object.entries(TYPE_LABEL).map(([v, t]) => (
                  <button type="button" key={v} className={`chip ${type === v ? 'on' : ''}`} onClick={() => setType(v)}>{t}</button>
                ))}
              </div>
              <label>备注<textarea rows="2" value={notes} onChange={(e) => setNotes(e.target.value)} /></label>

              {(type === 'retest_fail' || (type === 'observation' && frozen)) && (
                <label>
                  {type === 'retest_fail' ? '下次复检截止日 *（复检未通过须继续冻结）' : '更新复检截止日'}
                  <input type="datetime-local" value={due}
                    onChange={(e) => setDue(e.target.value)} />
                </label>
              )}

              {type === 'discard' && (
                <>
                  <label>处理方式 *<input value={method} onChange={(e) => setMethod(e.target.value)} /></label>
                  <label>执行人 *<input value={disposedBy} onChange={(e) => setDisposedBy(e.target.value)} placeholder="执行废弃处理的人员" /></label>
                </>
              )}

              <label>照片（本地保存，可多选）<input ref={fileRef} type="file" accept="image/*" multiple /></label>
              <button className="btn primary" type="submit">提交结论</button>
            </form>
          )}
        </section>
      </div>

      <section className="card">
        <h3>观察 / 复检 / 废弃历史</h3>
        <div className="timeline">
          {sample.observations.map((o) => (
            <div key={o.id} className="timeline-item">
              <div className="timeline-head">
                <span className={`badge type-${o.type}`}>{o.type_label}</span>
                <span className="muted small">{fmt(o.created_at)} · {o.operator}</span>
              </div>
              {o.notes && <div>{o.notes}</div>}
              {o.retest_due_at && <div className="small">复检截止日：{fmt(o.retest_due_at)}</div>}
              {o.type === 'discard' && (
                <div className="small">处理方式：{o.disposal_method} · 执行人：{o.disposed_by}</div>
              )}
              {o.photos.length > 0 && (
                <div className="photos">
                  {o.photos.map((p) => (
                    <a key={p.id} href={p.url} target="_blank" rel="noreferrer">
                      <img src={p.url} alt={p.original_name} title={p.original_name} />
                    </a>
                  ))}
                </div>
              )}
            </div>
          ))}
          {sample.observations.length === 0 && <div className="muted">暂无历史记录</div>}
        </div>
      </section>
    </div>
  )
}

import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api.js'
import { DecisionBadge, StatusBadge, fmt } from '../components/badges.jsx'

export default function IsolationDetailPage() {
  const { id } = useParams()
  const [event, setEvent] = useState(null)
  const [actor, setActor] = useState(null)
  const [error, setError] = useState('')
  const [dueByRecord, setDueByRecord] = useState({})

  const load = useCallback(() => {
    api(`/isolations/${id}`).then((r) => setEvent(r.data))
    api('/actors').then((r) => {
      const saved = localStorage.getItem('lab_actor_id')
      setActor(r.data.find((a) => String(a.id) === String(saved)))
    })
  }, [id])
  useEffect(load, [load])

  if (!event) return <div className="empty">加载中…</div>

  const isManager = actor?.role === 'manager'

  const decide = async (record, decision) => {
    setError('')
    try {
      await api(`/exposures/${record.id}/decide`, {
        method: 'POST',
        body: {
          decision,
          retest_due_at: dueByRecord[record.id] || undefined,
        },
      })
      load()
    } catch (err) {
      setError(err.status === 403 ? '⛔ 仅负责人可判定，请在右上角切换为负责人身份' : (err.errors ? Object.values(err.errors).flat().join('；') : err.message))
    }
  }

  const rescan = async () => {
    setError('')
    try {
      await api(`/isolations/${id}/rescan`, { method: 'POST' })
      load()
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h2>隔离登记 #{event.id}</h2>
          <div className="muted">
            来源样本 <Link to={`/samples/${event.source_sample.id}`}>{event.source_sample.code}</Link>{' '}
            <StatusBadge status={event.source_sample.status} label={event.source_sample.status_label} />
            {' '}· 培养箱 <b>{event.incubator.code}</b>
          </div>
        </div>
        <button className="btn" onClick={rescan}>↻ 重新扫描同箱样本</button>
      </div>

      <div className="card">
        <h3>可疑时间段</h3>
        <p>{fmt(event.suspicious_started_at)} ～ {fmt(event.suspicious_ended_at)}</p>
        {event.reason && <p className="muted">污染迹象：{event.reason}</p>}
        <p className="muted small">登记人：{event.registered_by} · 登记时间 {fmt(event.created_at)}</p>
      </div>

      {error && <div className="error box">{error}</div>}
      {!isManager && <div className="warn box">当前为实验员身份：同箱候选由系统按实际存放时间自动筛出，最终判定请切换到<b>负责人</b>身份。</div>}

      <h3>实际共同存放的培养皿（{event.exposure_records?.length ?? 0}）</h3>
      <p className="muted small">
        筛选规则：同培养箱且存放区间与可疑时间段严格重叠。窗口结束后才放入、窗口开始前已取出、或不在同一培养箱的样本不会出现。
      </p>

      <table className="table card">
        <thead>
          <tr><th>样本</th><th>批次</th><th>共同存放交集（快照）</th><th>当前状态</th><th>判定</th><th>负责人操作</th></tr>
        </thead>
        <tbody>
          {event.exposure_records?.map((r) => (
            <tr key={r.id} className={r.decision !== 'pending' ? 'decided' : ''}>
              <td><Link to={`/samples/${r.sample_id}`}>{r.sample_code}</Link></td>
              <td>{r.sample_batch_no}</td>
              <td className="small">{fmt(r.overlap_started_at)}<br />～ {fmt(r.overlap_ended_at)}</td>
              <td><StatusBadge status={r.sample_status} label={r.sample_status_label} /></td>
              <td><DecisionBadge decision={r.decision} /></td>
              <td>
                {r.decision === 'pending' ? (
                  <div className="decide-cell">
                    {isManager ? (
                      <>
                        <input type="datetime-local" className="due-input"
                          value={dueByRecord[r.id] || ''}
                          onChange={(e) => setDueByRecord({ ...dueByRecord, [r.id]: e.target.value })}
                          title="判为受影响时的复检截止日（留空默认 7 天）" />
                        <button className="btn danger small" onClick={() => decide(r, 'affected')}>受影响·隔离</button>
                        <button className="btn small" onClick={() => decide(r, 'not_affected')}>未受影响</button>
                      </>
                    ) : <span className="muted small">待负责人</span>}
                  </div>
                ) : <span className="muted small">{fmt(r.decided_at)}</span>}
              </td>
            </tr>
          ))}
          {(event.exposure_records?.length ?? 0) === 0 && (
            <tr><td colSpan="6" className="empty">没有与可疑时间段重叠的同箱样本</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth, isSupervisor } from '../auth.jsx';
import { EXPOSURE_META, STATUS_META, fmtDateTime } from '../ui.js';

export default function ReportDetail() {
  const { id } = useParams();
  const { user } = useAuth();
  const lead = isSupervisor(user);
  const [report, setReport] = useState(null);
  const [scanResult, setScanResult] = useState(null);
  const [busy, setBusy] = useState(false);
  // 每行独立的备注/截止日编辑
  const [notes, setNotes] = useState({});
  const [deadlines, setDeadlines] = useState({});
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  async function reload(showScan = false) {
    const d = await api.get(`/contamination-reports/${id}`);
    setReport(d);
    const n = {}, dl = {};
    for (const e of d.exposures || []) {
      n[e.id] = e.decision_note || '';
      dl[e.id] = e.recheck_deadline || '';
    }
    setNotes(n); setDeadlines(dl);
    if (showScan) setScanResult(null);
  }
  useEffect(() => { reload(); /* eslint-disable-next-line */ }, [id]);

  function flash(m, isErr = false) {
    if (isErr) { setErr(m); setMsg(''); } else { setMsg(m); setErr(''); }
    setTimeout(() => { setMsg(''); setErr(''); }, 4000);
  }

  async function scan() {
    setBusy(true);
    setErr('');
    try {
      const d = await api.post(`/contamination-reports/${id}/scan`);
      setScanResult(d);
      flash(`筛选完成：检查 ${d.checked} 个培养皿记录样本，${d.present_count} 个在可疑时间段内确实共同存放`);
      reload();
    } catch (e) { flash(e.message, true); } finally { setBusy(false); }
  }

  async function decide(exposureId, decision) {
    try {
      await api.post(`/exposures/${exposureId}/decision`, {
        decision,
        decision_note: notes[exposureId] || undefined,
        recheck_deadline: decision === 'exposed' ? deadlines[exposureId] || undefined : undefined,
      });
      flash(decision === 'exposed' ? '已判定受影响，该样本已隔离' : '已判定不受影响');
      reload();
    } catch (e) {
      if (e.status === 403) flash('仅负责人可做出判定', true);
      else flash(e.message, true);
    }
  }

  async function finishReview() {
    try {
      await api.patch(`/contamination-reports/${id}/reviewed`, {});
      flash('登记单已标记为完成判定');
      reload();
    } catch (e) { flash(e.status === 403 ? '仅负责人可关闭登记单' : e.message, true); }
  }

  if (!report) return <div className="muted">加载中…</div>;

  const exposures = (report.exposures || []).slice().sort((a, b) => (b.was_present - a.was_present) || a.id - b.id);
  const undecided = exposures.filter((e) => e.decision === 'pending' && e.was_present).length;

  return (
    <div>
      <div className="page-head">
        <div>
          <Link to="/reports" className="muted small">← 返回登记单列表</Link>
          <h2>污染登记单 {report.report_no}</h2>
        </div>
      </div>

      {msg && <div className="alert alert-ok">{msg}</div>}
      {err && <div className="alert alert-error">{err}</div>}

      <div className="report-summary">
        <div><span className="muted">培养箱：</span><strong>{report.incubator?.code} · {report.incubator?.name}</strong></div>
        <div><span className="muted">可疑样本：</span><strong>{report.suspect_sample?.code}</strong>
          <span className={`badge ${STATUS_META[report.suspect_sample?.status]?.cls || ''}`}>
            {STATUS_META[report.suspect_sample?.status]?.label}
          </span>
        </div>
        <div><span className="muted">可疑时间段：</span>{fmtDateTime(report.suspected_start_at)} ~ {fmtDateTime(report.suspected_end_at)}</div>
        <div><span className="muted">登记人：</span>{report.reporter?.name}</div>
        {report.description && <div className="full"><span className="muted">现象：</span>{report.description}</div>}
      </div>

      <div className="scan-bar">
        <button className="btn btn-primary" onClick={scan} disabled={busy}>
          {busy ? '筛选中…' : '🔍 按实际存放时间筛选同箱培养皿'}
        </button>
        <span className="muted small">
          判定规则：入箱时间早于窗口结束，且出箱时间晚于窗口开始（仍在箱也算）。
          <strong> 窗口结束后才放入、或窗口开始前已取出的样本不算同箱暴露。</strong>
        </span>
      </div>

      {scanResult && (
        <div className="alert alert-ok">
          本次检查 <strong>{scanResult.checked}</strong> 个样本，命中共同存放 <strong>{scanResult.present_count}</strong> 个。
        </div>
      )}

      <table className="table">
        <thead>
          <tr>
            <th>培养皿</th><th>批次</th><th>是否确实同箱</th><th>共同存放交集（证据）</th>
            <th>判定结论</th><th>负责人判定操作</th>
          </tr>
        </thead>
        <tbody>
          {exposures.map((e) => (
            <tr key={e.id} className={e.was_present ? '' : 'row-excluded'}>
              <td>
                <Link to={`/samples/${e.sample_id}`}><strong>{e.sample?.code}</strong></Link>
                <div className={`badge ${STATUS_META[e.sample?.status]?.cls || ''}`} style={{ marginTop: 4 }}>
                  {STATUS_META[e.sample?.status]?.label}
                </div>
              </td>
              <td className="small">{e.sample?.batch_no}</td>
              <td>
                {e.was_present
                  ? <span className="text-danger"><strong>✓ 窗口内同箱</strong></span>
                  : <span className="muted">✗ 无时间交集</span>}
              </td>
              <td className="small">
                {(e.evidence || []).map((h, i) => (
                  <div key={i} className="evidence-box">
                    存放：{fmtDateTime(h.stored_at)} → {h.removed_at ? fmtDateTime(h.removed_at) : '仍在箱'}
                    <br /><span className="muted">交集：{fmtDateTime(h.overlap_start)} ~ {fmtDateTime(h.overlap_end)}</span>
                  </div>
                ))}
                {!e.was_present && <span className="muted">—</span>}
              </td>
              <td>
                <span className={`badge ${EXPOSURE_META[e.decision]?.cls || ''}`}>
                  {EXPOSURE_META[e.decision]?.label}
                </span>
                {e.decider && <div className="muted small" style={{ marginTop: 4 }}>{e.decider.name} {e.decided_at?.slice(0, 10)}</div>}
              </td>
              <td>
                {lead ? (
                  e.decision === 'pending' ? (
                    <div className="decision-box">
                      <input
                        className="input-sm"
                        placeholder="判定备注"
                        value={notes[e.id] || ''}
                        onChange={(ev) => setNotes({ ...notes, [e.id]: ev.target.value })}
                      />
                      {e.was_present && (
                        <input
                          className="input-sm"
                          type="date"
                          value={deadlines[e.id] || ''}
                          onChange={(ev) => setDeadlines({ ...deadlines, [e.id]: ev.target.value })}
                          title="受影响样本复检截止日"
                        />
                      )}
                      <div className="decision-btns">
                        {e.was_present && (
                          <button className="btn btn-danger btn-sm" onClick={() => decide(e.id, 'exposed')}>
                            受影响·隔离
                          </button>
                        )}
                        <button className="btn btn-sm" onClick={() => decide(e.id, 'cleared')}>
                          不受影响·排除
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="small">
                      <div>{e.decision_note || '（无备注）'}</div>
                      {e.recheck_deadline && <div className="muted">复检截止：{e.recheck_deadline}</div>}
                    </div>
                  )
                ) : (
                  <span className="muted small">需负责人账号判定</span>
                )}
              </td>
            </tr>
          ))}
          {exposures.length === 0 && (
            <tr><td colSpan={6} className="muted center">尚未执行筛选。请负责人（或实验员预览）点击上方筛选按钮。</td></tr>
          )}
        </tbody>
      </table>

      {lead && (
        <div className="finish-bar">
          <button className="btn btn-primary" onClick={finishReview} disabled={report.status !== 'open'}>
            {report.status === 'open' ? `完成整单判定${undecided ? `（还有 ${undecided} 个命中样本待判定）` : ''}` : '登记单已完成判定'}
          </button>
        </div>
      )}
    </div>
  );
}

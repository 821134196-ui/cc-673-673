import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { STATUS_META, fmtDate, fmtDateTime, daysUntil } from '../ui.js';

const TYPE_LABEL = { observation: '日常观察', recheck: '复检结论', disposal: '废弃登记', release: '解除隔离' };

export default function SampleDetail() {
  const { id } = useParams();
  const { user } = useAuth();
  const [sample, setSample] = useState(null);
  const [obs, setObs] = useState('');
  const [obsPhoto, setObsPhoto] = useState(null);
  const [recheckResult, setRecheckResult] = useState('passed');
  const [recheckNote, setRecheckNote] = useState('');
  const [nextDeadline, setNextDeadline] = useState('');
  const [discard, setDiscard] = useState({ disposal_method: '', executed_by_name: user?.name || '', content: '' });
  const [experiment, setExperiment] = useState('');
  const [usageResult, setUsageResult] = useState(null);
  const [photos, setPhotos] = useState([]);
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  async function reload() {
    const d = await api.get(`/samples/${id}`);
    setSample(d);
    setPhotos(d.photos || []);
  }
  useEffect(() => { reload(); /* eslint-disable-next-line */ }, [id]);

  function flash(m, isErr = false) {
    if (isErr) { setErr(m); setMsg(''); } else { setMsg(m); setErr(''); }
    setTimeout(() => { setMsg(''); setErr(''); }, 4000);
  }

  async function addObservation(e) {
    e.preventDefault();
    if (!obs.trim()) return;
    try {
      const created = await api.post(`/samples/${id}/observations`, { content: obs });
      if (obsPhoto) {
        const fd = new FormData();
        fd.append('photo', obsPhoto);
        fd.append('photoable_type', 'observation');
        fd.append('photoable_id', created.id);
        await api.upload('/photos', fd);
      }
      setObs('');
      setObsPhoto(null);
      flash('观察记录已追加' + (obsPhoto ? '（含照片）' : ''));
      reload();
    } catch (e) { flash(e.message, true); }
  }

  async function doRecheck() {
    try {
      const payload = { result: recheckResult, content: recheckNote };
      if (recheckResult === 'failed' && nextDeadline) payload.next_recheck_deadline = nextDeadline;
      const d = await api.post(`/samples/${id}/recheck`, payload);
      flash(recheckResult === 'passed' ? '复检通过，已解除隔离' : '复检未通过，样本继续冻结');
      setRecheckNote(''); setNextDeadline('');
      setSample({ ...sample, ...d.sample });
      reload();
    } catch (e) { flash(e.message, true); }
  }

  async function doDiscard() {
    if (!discard.disposal_method || !discard.executed_by_name) {
      return flash('废弃必须填写处理方式与执行人', true);
    }
    if (!window.confirm('确认废弃该样本？废弃后原批次号与观察历史保留，但样本不可再领用。')) return;
    try {
      await api.post(`/samples/${id}/discard`, discard);
      setDiscard({ disposal_method: '', executed_by_name: user?.name || '', content: '' });
      flash('废弃登记完成');
      reload();
    } catch (e) { flash(e.message, true); }
  }

  async function requestUsage() {
    if (!experiment.trim()) return flash('请填写后续实验名称', true);
    setUsageResult(null);
    try {
      const d = await api.post(`/samples/${id}/usage-requests`, { experiment_name: experiment });
      setUsageResult({ ok: true, text: `允许领用，已记录领用申请（实验：${d.experiment_name}）` });
      setExperiment('');
    } catch (e) {
      if (e.status === 423) {
        setUsageResult({ ok: false, text: `⛔ 领用被拦截：${e.data.block_reason}` });
      } else {
        setUsageResult({ ok: false, text: e.message });
      }
    }
  }

  async function uploadPhoto(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('photo', file);
    fd.append('photoable_type', 'sample');
    fd.append('photoable_id', id);
    try {
      await api.upload('/photos', fd);
      flash('照片已保存到本地');
      reload();
    } catch (e2) { flash(e2.message, true); }
    e.target.value = '';
  }

  if (!sample) return <div className="muted">加载中…</div>;
  const meta = STATUS_META[sample.status] || { label: sample.status, cls: '' };
  const frozen = sample.usage_blocked;
  const dLeft = daysUntil(sample.recheck_deadline);
  const deadlineCls = dLeft < 0 ? 'deadline-overdue' : dLeft === 0 ? 'deadline-today' : 'deadline-future';

  return (
    <div>
      <div className="page-head">
        <div>
          <Link to="/samples" className="muted small">← 返回样本列表</Link>
          <h2>{sample.code} <span className={`badge ${meta.cls}`}>{meta.label}</span></h2>
        </div>
      </div>

      {msg && <div className="alert alert-ok">{msg}</div>}
      {err && <div className="alert alert-error">{err}</div>}

      {/* 隔离状态与复检截止日 —— 观察页最突出位置 */}
      <div className={`status-hero ${frozen ? 'status-hero-blocked' : 'status-hero-ok'}`}>
        <div className="hero-left">
          <div className="hero-title">
            {sample.status === 'discarded' ? '样本已废弃（记录与批次保留）'
              : sample.status === 'frozen' ? '复检未通过 · 样本继续冻结'
              : sample.status === 'quarantined' ? '样本隔离观察中 · 禁止领用'
              : sample.status === 'released' ? '已解除隔离 · 可正常领用'
              : '样本状态正常 · 可领用'}
          </div>
          {sample.recheck_deadline ? (
            <div className={deadlineCls} style={{ fontSize: 16, fontWeight: 600, marginTop: 6 }}>
              复检截止日：{fmtDate(sample.recheck_deadline)}
              （{dLeft < 0 ? `已逾期 ${-dLeft} 天` : dLeft === 0 ? '今日截止' : `还剩 ${dLeft} 天`}）
            </div>
          ) : <div className="muted" style={{ marginTop: 6 }}>无复检截止日</div>}
          <div className="muted small" style={{ marginTop: 6 }}>
            批次号：{sample.batch_no}
            {sample.quarantined_at && ` · 隔离开始：${fmtDateTime(sample.quarantined_at)}`}
            {sample.released_at && ` · 解除时间：${fmtDateTime(sample.released_at)}`}
          </div>
        </div>

        {/* 领用拦截面板 */}
        <div className="hero-right">
          <div className="usage-box">
            <div className="small muted">后续实验领用</div>
            <div className="usage-row">
              <input
                placeholder="拟用于的实验名称"
                value={experiment}
                onChange={(e) => setExperiment(e.target.value)}
                disabled={sample.status === 'discarded'}
              />
              <button className="btn btn-primary btn-sm" onClick={requestUsage}>申请领用</button>
            </div>
            {frozen && <div className="block-note">⛔ {sample.block_reason}</div>}
            {usageResult && (
              <div className={usageResult.ok ? 'usage-ok' : 'usage-block'}>{usageResult.text}</div>
            )}
          </div>
        </div>
      </div>

      <div className="detail-grid">
        <div className="col">
          {/* 日常观察 */}
          <section className="form-card">
            <h3>日常观察记录</h3>
            <form onSubmit={addObservation}>
              <textarea rows={3} placeholder="记录菌落形态、气味、颜色等观察…" value={obs} onChange={(e) => setObs(e.target.value)} />
              <label className="small">附带照片（可选，本地保存）</label>
              <input type="file" accept="image/*" onChange={(e) => setObsPhoto(e.target.files?.[0] || null)} />
              <button className="btn btn-primary">追加观察</button>
            </form>
          </section>

          {/* 复检结论 */}
          {sample.status !== 'discarded' && (
            <section className="form-card">
              <h3>复检结论（独立于其他样本）</h3>
              <div className="radio-row">
                <label><input type="radio" checked={recheckResult === 'passed'} onChange={() => setRecheckResult('passed')} /> 复检通过（解除隔离）</label>
                <label><input type="radio" checked={recheckResult === 'failed'} onChange={() => setRecheckResult('failed')} /> 复检未通过（继续冻结）</label>
              </div>
              <input placeholder="复检说明" value={recheckNote} onChange={(e) => setRecheckNote(e.target.value)} />
              {recheckResult === 'failed' && (
                <>
                  <label className="small">新的复检截止日</label>
                  <input type="date" value={nextDeadline} onChange={(e) => setNextDeadline(e.target.value)} />
                </>
              )}
              <button className="btn btn-primary" onClick={doRecheck}>提交复检结论</button>
            </section>
          )}

          {/* 废弃登记 */}
          {sample.status !== 'discarded' && (
            <section className="form-card danger-card">
              <h3>废弃登记</h3>
              <label>处理方式 *（如高压蒸汽灭菌、焚烧、化学消毒）</label>
              <input value={discard.disposal_method} onChange={(e) => setDiscard({ ...discard, disposal_method: e.target.value })} />
              <label>执行人 *</label>
              <input value={discard.executed_by_name} onChange={(e) => setDiscard({ ...discard, executed_by_name: e.target.value })} />
              <label>备注</label>
              <textarea rows={2} value={discard.content} onChange={(e) => setDiscard({ ...discard, content: e.target.value })} />
              <button className="btn btn-danger" onClick={doDiscard}>确认废弃</button>
            </section>
          )}

          {/* 照片 */}
          <section className="form-card">
            <h3>培养皿照片（本地保存）</h3>
            <input type="file" accept="image/*" onChange={uploadPhoto} />
            <div className="photo-grid">
              {photos.map((p) => (
                <a key={p.id} href={p.path?.startsWith('/storage') ? p.path : `/storage/${p.path}`} target="_blank" rel="noreferrer">
                  <img src={p.path?.startsWith('/storage') ? p.path : `/storage/${p.path}`} alt={p.original_name || 'photo'} />
                </a>
              ))}
              {photos.length === 0 && <span className="muted small">暂无照片</span>}
            </div>
          </section>
        </div>

        <div className="col">
          {/* 观察历史（废弃后仍保留） */}
          <section className="form-card">
            <h3>观察与处置历史</h3>
            <ul className="timeline">
              {(sample.observations || []).map((o) => (
                <li key={o.id} className={`tl-item tl-${o.type}`}>
                  <div className="tl-head">
                    <span className="tl-type">{TYPE_LABEL[o.type] || o.type}</span>
                    <span className="muted small">{fmtDateTime(o.created_at)}</span>
                  </div>
                  {o.type === 'recheck' && (
                    <div>
                      结论：
                      <strong className={o.result === 'passed' ? 'text-ok' : 'text-danger'}>
                        {o.result === 'passed' ? '复检通过，解除隔离' : '复检未通过，继续冻结'}
                      </strong>
                    </div>
                  )}
                  {o.content && <div>{o.content}</div>}
                  {o.type === 'disposal' && (
                    <div className="small">
                      处理方式：<strong>{o.disposal_method}</strong> · 执行人：<strong>{o.executed_by_name}</strong> · {fmtDateTime(o.executed_at)}
                    </div>
                  )}
                  <div className="photo-grid photo-grid-sm">
                    {(o.photos || []).map((p) => (
                      <a key={p.id} href={`/storage/${p.path}`} target="_blank" rel="noreferrer">
                        <img src={`/storage/${p.path}`} alt="" />
                      </a>
                    ))}
                  </div>
                </li>
              ))}
              {(sample.observations || []).length === 0 && <li className="muted">暂无历史记录</li>}
            </ul>
          </section>

          {/* 存放历史 */}
          <section className="form-card">
            <h3>培养箱存放历史</h3>
            <ul className="plain-list">
              {(sample.storage_records || []).map((r) => (
                <li key={r.id}>
                  <strong>{r.incubator?.code || `培养箱#${r.incubator_id}`}</strong>
                  ：{fmtDateTime(r.stored_at)} → {r.removed_at ? fmtDateTime(r.removed_at) : <span className="text-ok">仍在箱</span>}
                </li>
              ))}
              {(sample.storage_records || []).length === 0 && <li className="muted">暂无存放记录</li>}
            </ul>
          </section>
        </div>
      </div>
    </div>
  );
}

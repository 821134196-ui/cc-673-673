import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { STATUS_META, fmtDate, daysUntil } from '../ui.js';

function Badge({ status }) {
  const meta = STATUS_META[status] || { label: status, cls: '' };
  return <span className={`badge ${meta.cls}`}>{meta.label}</span>;
}

function Deadline({ sample }) {
  if (!sample.recheck_deadline || !['quarantined', 'frozen'].includes(sample.status)) {
    return <span className="muted">—</span>;
  }
  const d = daysUntil(sample.recheck_deadline);
  const cls = d < 0 ? 'deadline-overdue' : d === 0 ? 'deadline-today' : 'deadline-future';
  const text = d < 0 ? `已逾期 ${-d} 天` : d === 0 ? '今日截止' : `还剩 ${d} 天`;
  return <span className={cls}>{fmtDate(sample.recheck_deadline)}（{text}）</span>;
}

export default function Samples() {
  const [samples, setSamples] = useState([]);
  const [incubators, setIncubators] = useState([]);
  const [filter, setFilter] = useState('');
  const [tab, setTab] = useState('list');

  // 表单
  const [sForm, setSForm] = useState({ code: '', name: '', batch_no: '', description: '' });
  const [iForm, setIForm] = useState({ code: '', name: '', location: '' });
  const [inForm, setInForm] = useState({ sample_id: '', incubator_id: '', stored_at: '', note: '' });
  const [outList, setOutList] = useState([]);
  const [outAt, setOutAt] = useState({});
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  async function reload() {
    const [ss, ii] = await Promise.all([api.get('/samples'), api.get('/incubators')]);
    setSamples(ss);
    setIncubators(ii);
    // 找出“仍在箱”的样本供出箱登记
    const open = [];
    for (const s of ss) {
      const detail = await api.get(`/samples/${s.id}`);
      const openRec = detail.storage_records?.find((r) => !r.removed_at);
      if (openRec) open.push({ sample: s, rec: openRec });
    }
    setOutList(open);
  }

  useEffect(() => { reload(); }, []);

  function flash(m, isErr = false) {
    if (isErr) { setErr(m); setMsg(''); } else { setMsg(m); setErr(''); }
    setTimeout(() => { setMsg(''); setErr(''); }, 3500);
  }

  async function createSample(e) {
    e.preventDefault();
    try {
      await api.post('/samples', sForm);
      setSForm({ code: '', name: '', batch_no: '', description: '' });
      flash('样本登记成功');
      reload();
    } catch (e) { flash(e.message, true); }
  }

  async function createIncubator(e) {
    e.preventDefault();
    try {
      await api.post('/incubators', iForm);
      setIForm({ code: '', name: '', location: '' });
      flash('培养箱登记成功');
      reload();
    } catch (e) { flash(e.message, true); }
  }

  async function checkIn(e) {
    e.preventDefault();
    try {
      await api.post('/storage-records', {
        ...inForm,
        stored_at: inForm.stored_at.replace('T', ' ') + ':00',
      });
      setInForm({ sample_id: '', incubator_id: '', stored_at: '', note: '' });
      flash('入箱登记成功');
      reload();
    } catch (e) {
      flash(e.data?.errors?.sample_id?.[0] || e.data?.errors?.stored_at?.[0] || e.message, true);
    }
  }

  async function checkOut(recId) {
    const val = outAt[recId];
    if (!val) return flash('请选择出箱时间', true);
    try {
      await api.patch(`/storage-records/${recId}/remove`, { removed_at: val.replace('T', ' ') + ':00' });
      flash('出箱登记成功');
      reload();
    } catch (e) { flash(e.message, true); }
  }

  const shown = samples.filter((s) => !filter || s.status === filter);

  return (
    <div>
      <div className="page-head">
        <h2>样本与培养箱</h2>
        <div className="tabs">
          <button className={tab === 'list' ? 'tab active' : 'tab'} onClick={() => setTab('list')}>样本列表</button>
          <button className={tab === 'register' ? 'tab active' : 'tab'} onClick={() => setTab('register')}>登记样本</button>
          <button className={tab === 'incubator' ? 'tab active' : 'tab'} onClick={() => setTab('incubator')}>培养箱</button>
          <button className={tab === 'storage' ? 'tab active' : 'tab'} onClick={() => setTab('storage')}>入箱/出箱</button>
        </div>
      </div>

      {msg && <div className="alert alert-ok">{msg}</div>}
      {err && <div className="alert alert-error">{err}</div>}

      {tab === 'list' && (
        <>
          <div className="filter-bar">
            <select value={filter} onChange={(e) => setFilter(e.target.value)}>
              <option value="">全部状态</option>
              <option value="normal">正常</option>
              <option value="quarantined">隔离中</option>
              <option value="frozen">复检未通过·冻结</option>
              <option value="released">已解除隔离</option>
              <option value="discarded">已废弃</option>
            </select>
          </div>
          <table className="table">
            <thead>
              <tr><th>编号</th><th>名称</th><th>批次号</th><th>状态</th><th>复检截止日</th><th></th></tr>
            </thead>
            <tbody>
              {shown.map((s) => (
                <tr key={s.id} className={s.status === 'discarded' ? 'row-discarded' : ''}>
                  <td><strong>{s.code}</strong></td>
                  <td>{s.name || '—'}</td>
                  <td>{s.batch_no}</td>
                  <td><Badge status={s.status} /></td>
                  <td><Deadline sample={s} /></td>
                  <td><Link className="btn btn-ghost btn-sm" to={`/samples/${s.id}`}>观察页</Link></td>
                </tr>
              ))}
              {shown.length === 0 && <tr><td colSpan={6} className="muted center">暂无样本</td></tr>}
            </tbody>
          </table>
        </>
      )}

      {tab === 'register' && (
        <form className="form-card" onSubmit={createSample}>
          <h3>登记新培养皿样本</h3>
          <label>样本编号 *</label>
          <input value={sForm.code} onChange={(e) => setSForm({ ...sForm, code: e.target.value })} required placeholder="如 P-005" />
          <label>样本名称</label>
          <input value={sForm.name} onChange={(e) => setSForm({ ...sForm, name: e.target.value })} />
          <label>原始批次号 *（废弃后仍保留）</label>
          <input value={sForm.batch_no} onChange={(e) => setSForm({ ...sForm, batch_no: e.target.value })} required />
          <label>描述</label>
          <textarea value={sForm.description} onChange={(e) => setSForm({ ...sForm, description: e.target.value })} />
          <button className="btn btn-primary">登记样本</button>
        </form>
      )}

      {tab === 'incubator' && (
        <div className="two-col">
          <form className="form-card" onSubmit={createIncubator}>
            <h3>登记培养箱</h3>
            <label>培养箱编号 *</label>
            <input value={iForm.code} onChange={(e) => setIForm({ ...iForm, code: e.target.value })} required placeholder="如 INC-B" />
            <label>名称 *</label>
            <input value={iForm.name} onChange={(e) => setIForm({ ...iForm, name: e.target.value })} required />
            <label>放置位置</label>
            <input value={iForm.location} onChange={(e) => setIForm({ ...iForm, location: e.target.value })} />
            <button className="btn btn-primary">登记培养箱</button>
          </form>
          <div className="form-card">
            <h3>已有培养箱（{incubators.length}）</h3>
            <ul className="plain-list">
              {incubators.map((i) => (
                <li key={i.id}><strong>{i.code}</strong> · {i.name} <span className="muted">{i.location || ''}</span></li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {tab === 'storage' && (
        <div className="two-col">
          <form className="form-card" onSubmit={checkIn}>
            <h3>样本入箱登记</h3>
            <label>样本 *</label>
            <select value={inForm.sample_id} onChange={(e) => setInForm({ ...inForm, sample_id: e.target.value })} required>
              <option value="">请选择</option>
              {samples.filter((s) => s.status !== 'discarded').map((s) => <option key={s.id} value={s.id}>{s.code}（{s.batch_no}）</option>)}
            </select>
            <label>培养箱位置 *</label>
            <select value={inForm.incubator_id} onChange={(e) => setInForm({ ...inForm, incubator_id: e.target.value })} required>
              <option value="">请选择</option>
              {incubators.map((i) => <option key={i.id} value={i.id}>{i.code} · {i.name}</option>)}
            </select>
            <label>入箱时间 *</label>
            <input type="datetime-local" value={inForm.stored_at} onChange={(e) => setInForm({ ...inForm, stored_at: e.target.value })} required />
            <label>备注</label>
            <input value={inForm.note} onChange={(e) => setInForm({ ...inForm, note: e.target.value })} />
            <button className="btn btn-primary">确认入箱</button>
          </form>

          <div className="form-card">
            <h3>在箱样本出箱登记（{outList.length}）</h3>
            {outList.length === 0 && <div className="muted">当前没有在箱样本</div>}
            {outList.map(({ sample, rec }) => (
              <div key={rec.id} className="storage-row">
                <div>
                  <strong>{sample.code}</strong> 在 <strong>{rec.incubator?.code || `培养箱#${rec.incubator_id}`}</strong>
                  <div className="muted small">入箱：{fmtDate(rec.stored_at)} {String(rec.stored_at).slice(11, 16)}</div>
                </div>
                <div className="storage-actions">
                  <input type="datetime-local" value={outAt[rec.id] || ''} onChange={(e) => setOutAt({ ...outAt, [rec.id]: e.target.value })} />
                  <button className="btn btn-sm" onClick={() => checkOut(rec.id)}>登记出箱</button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

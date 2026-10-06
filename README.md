# 培养皿污染隔离与观察系统

实验室培养皿疑似污染时：**实验员先隔离样本 → 负责人按实际共同存放时间筛选同箱培养皿并逐样本判定 → 各样本独立记录观察/复检/废弃结论**。

- 后端：PHP 8.2 + Laravel 10，Eloquent + SQLite
- 前端：React 18 + Vite + React Router
- 照片本地保存（`storage/app/public/photos`），提醒接口本地模拟（`reminders` 表 + artisan 命令）

## 一条命令启动

```bash
./start.sh
```

自动完成：本地 PHP 运行环境（缺失时通过 `tools/install-php-local.sh` 免 root 安装到 `~/php-local`）、数据库迁移、首次演示数据填充、依赖检查，同时启动

| 服务 | 地址 |
| --- | --- |
| 前端页面 | http://127.0.0.1:5173 |
| 后端 API | http://127.0.0.1:8000/api |

演示账号（密码均为 `password`）：

- 实验员：`tech@lab.local` —— 登记样本/培养箱/可疑时间段、观察、复检、废弃
- 负责人：`lead@lab.local` —— 同箱暴露筛选、逐样本判定是否受影响

## 运行验证测试

```bash
./run-tests.sh
```

13 个 Feature 测试（72 个断言），覆盖题目要求的三类验证：

1. **同箱时间范围筛选**：窗口内同箱命中；窗口结束后才放入、窗口开始前已取出不命中；边界点（半开区间）不命中；仍在箱命中。
2. **隔离期间领用拦截**：隔离中/复检未通过冻结/已废弃返回 `423 Locked` 并记录原因；复检通过解除隔离后放行。
3. **不同样本独立结论**：负责人可对 A 判“受影响（隔离）”、对 B 判“不受影响”，互不牵连；废弃仅改状态，`batch_no` 与全部观察历史保留。

## 业务规则说明

### 同箱暴露筛选（核心算法）

对同一培养箱，比较每个样本的存放区间 `[stored_at, removed_at)`（未出箱视为至今）与可疑窗口 `[S, E)`：

```
命中条件：stored_at < E  且  (removed_at IS NULL 或 removed_at > S)
```

- 窗口结束后才放入（`stored_at >= E`）→ **不算暴露**
- 窗口开始前已取出（`removed_at <= S`）→ **不算暴露**
- 命中时同时保存交集区间作为证据，展示在判定页。

### 样本状态机

```
normal ──登记疑似污染/判定暴露──▶ quarantined
quarantined ──复检通过──▶ released（可领用）
quarantined ──复检未通过──▶ frozen（继续冻结，截止日顺延）
frozen ──复检通过──▶ released
任何状态 ──废弃──▶ discarded（批次号与观察历史保留，不可领用）
```

`quarantined` / `frozen` / `discarded` 三个状态下领用一律拦截。

### 本地模拟提醒

- 接口：`GET /api/reminders`（打开提醒页即生成当天到期/逾期提醒）
- 命令：`php artisan reminders:send [--date=YYYY-MM-DD]`（可指定模拟日期）
- 当天同样本同类型只生成一次（`dedupe_key` 唯一），不外发任何渠道。

## 主要 API

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/api/auth/login` | 登录获取 token |
| POST | `/api/samples` | 登记样本（批次号） |
| POST | `/api/incubators` | 登记培养箱 |
| POST | `/api/storage-records` | 入箱登记（位置+时间） |
| PATCH | `/api/storage-records/{id}/remove` | 出箱登记 |
| POST | `/api/contamination-reports` | 实验员登记疑似污染（可疑样本立即隔离） |
| POST | `/api/contamination-reports/{id}/scan` | 按时间区间筛选同箱培养皿 |
| POST | `/api/exposures/{id}/decision` | **负责人**判定 exposed/cleared |
| POST | `/api/samples/{id}/observations` | 日常观察 |
| POST | `/api/samples/{id}/recheck` | 复检结论 passed/failed |
| POST | `/api/samples/{id}/discard` | 废弃（处理方式+执行人） |
| POST | `/api/samples/{id}/usage-requests` | 领用（冻结返回 423） |
| POST | `/api/photos` | 照片上传（multipart，本地保存） |
| GET | `/api/reminders` | 本地模拟提醒列表 |

## 目录

```
backend/   Laravel 应用（app/Services 为核心业务逻辑）
  app/Services/ContaminationService.php   同箱筛选、隔离、判定
  app/Services/ObservationService.php     观察/复检/废弃/领用拦截
  app/Services/ReminderService.php        本地提醒模拟
  tests/Feature/ContainmentWorkflowTest.php
frontend/  React + Vite 应用
start.sh       一条命令启动前后端
run-tests.sh   运行自动化测试
```

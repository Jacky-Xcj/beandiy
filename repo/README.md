# 拼豆工坊（BeanDIY）

扫码登记、计时使用、到时提醒的拼豆店小程序。**混合部署**：自有服务器托管管理网页并做鉴权代理，GitHub 仅作数据库。

> 核心安全改进：Fine-grained PAT **只存在服务器**，不再进入浏览器/小程序包（小程序因微信域名备案限制，暂用一把仅限 `repo/data/**` 的最小权限 PAT 直连，见下文）。

## 项目矩阵

| 部分 | 位置 | 技术 | 职责 |
|------|------|------|------|
| **用户端小程序** | `miniapp/` | 微信小程序 | 扫码登记、计时、到时提醒（弱绑定微信昵称，不收集手机） |
| **电脑管理网页** | `admin/index.html` + `index.html` | 纯 HTML/JS | 数据概览、台位管理、使用中会话、历史、二维码、设置 |
| **服务器代理** | `api/*.php` | PHP 8+ | 持有 PAT-A，校验请求，经 GitHub Contents API 读写数据 |
| **后端数据存储** | `repo/data/*.json` + GitHub | GitHub 仓库即数据库 | 数据以 JSON 文件存于仓库 `repo/` 子目录 |

> 客户数据采用**弱绑定**：仅记录微信昵称（授权时获取），不收集手机号。扫码即绑定桌号，授权微信登录即可开始计时。

## 目录结构

```
repo/                          # ← 本目录即提交到 GitHub 的全部可部署内容（miniapp/ 仅本地、不入库）
├── index.html              # 入口页，跳转管理后台（由服务器托管）
├── admin/
│   ├── index.html          # 电脑管理网页（单文件，调用同源 /api/*.php）
│   └── beep.wav            # 到时提示音（经 jsDelivr CDN 提供给小程序）
├── api/                    # 服务器 PHP 代理（需部署到你的服务器）
│   ├── bootstrap.php       # 公共：配置加载 / GitHub 客户端 / 校验 / 限流
│   ├── stations.php        # GET/PUT  repo/data/stations.json
│   ├── sessions.php        # GET/PUT  repo/data/sessions.json
│   ├── settings.php        # GET/PUT  repo/data/settings.json
│   ├── config.sample.php   # 配置模板（提交到 Git）
│   └── config.php          # 真实配置（gitignore，应放 web 根之外）
├── data/                   # 业务数据库（提交到 GitHub 版本管理，GitHub 路径为 repo/data/）
│   ├── stations.json       # 台位列表
│   ├── sessions.json       # 使用记录
│   └── settings.json       # 营业设置
├── docs/
│   └── 混合架构方案.md       # 架构设计与安全分析（详细）
└── README.md               # 本文件
```

> 注意：微信小程序源码 `miniapp/` **仅存于本地、不提交 GitHub**（属客户端代码，且内含 PAT-B，不应公开）。本 `repo/` 目录才是入库内容；GitHub 仓库的树即 `repo/` 之下的这些文件。部署服务器时，把 `repo/` 内的 `admin/`、`api/`、`data/` 复制到 web 根即可（见下文步骤 3）。

## 部署步骤

### 1. 创建 GitHub 仓库并推送（作为数据库 + 源码）

```bash
git init
git add .
git commit -m "init beandiy"
git branch -M main
git remote add origin https://github.com/你的用户名/beandiy.git
git push -u origin main
```

### 2. 准备两个 Fine-grained PAT（关键）

仓库 → **Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate**：

| 名称 | Repository access | Permissions → Contents | Path 限制 | 存放位置 |
|------|-------------------|------------------------|-----------|----------|
| **PAT-A（服务器）** | 仅本仓库 `beandiy` | Read and write | 无（全仓库，含 `repo/data/**` 与 `repo/admin/`） | 服务器 `config.php`（**切勿进前端**） |
| **PAT-B（小程序）** | 仅本仓库 `beandiy` | Read and write | **`repo/data/**`** | 小程序 `app.js` 的 `githubToken` |

> PAT-B 仅能读写 `repo/data/`，即便被反编译抠出也动不了 `stations`/`settings`，爆破半径被锁死。

### 3. 部署服务器（PHP 代理 + 管理网页）

1. 服务器安装 PHP 8.x + Web 服务器（nginx + php-fpm，或 `php -S` 仅测试）。
2. 把本仓库 `repo/` 下的 `admin/` 与 `api/` 放到 web 根下（如 `/var/www/bean/`），使：
   - 管理网页地址：`https://你的域名或IP/admin/`
   - 接口地址：`https://你的域名或IP/api/stations.php` 等
3. 配置 `config.php`：
   - 复制 `api/config.sample.php` 为 `api/config.php`（或被环境变量 `BEAN_CONFIG` 指向的路径）。
   - 填写 `owner` / `repo` / `pat_a`（即 **PAT-A**）。
   - **生产建议**：把真实 `config.php` 移到 web 根之外（如 `/etc/bean/config.php`），并 `chmod 600`、属主为 Web 进程用户，避免被直接下载。
   - （可选）`admin_key` 填一个随机串；启用后所有写操作需在请求头带 `X-Admin-Key: 该串`（管理网页在 403 时会自动弹窗索取）。
4. 确认 `api/config.php` 不被 Web 直接访问（浏览器访问应 404/403，而非下载）。

> 服务器是否需备案：管理网页通过浏览器访问，**不强制备案**即可用（公网 IP 直连亦可，仅自签/无证书时有浏览器警告）。但为稳定与信任，建议仍配备案域名 + HTTPS 证书。

### 4. 配置小程序（PAT-B 直连 GitHub）

1. 微信开发者工具导入 `miniapp/` 目录。
2. 修改 `miniapp/app.js` 的 **3 个字段**（唯一需要改的地方）：

```js
globalData: {
  githubOwner: '你的GitHub用户名',
  githubRepo: 'beandiy',
  githubToken: 'github_pat_小程序专用_仅data读写'   // PAT-B（repo/data/** 仅读写）
}
```

3. 配置**合法域名**（小程序后台 → 开发管理 → 开发设置 → 服务器域名）：
   - request 合法域名添加：`https://api.github.com`、`https://cdn.jsdelivr.net`
4. 编译预览。

> 备案完成后（可选升级）：把小程序 `githubToken` 改为服务器代理凭证、并把 `utils/api.js` 的基地址从 `api.github.com` 切到你的服务器 `/api`，即可彻底移除客户端令牌。

## 使用流程

1. 管理员在 `repo/data/stations.json` 建好台位（也可在管理网页添加）
2. 管理网页「二维码」页打印台位二维码，张贴在桌上
3. 顾客打开小程序 → 点「扫码登记使用」→ 扫台位码 → 微信授权昵称 → 选时长 → 开始计时
4. 倒计时页面实时显示剩余时间，到时震动 + 提示音 + 弹窗，可「延长」或「结束」
5. 管理员在网页查看概览、使用中会话、历史记录

## 二维码说明

台位二维码内容为台位编码（如 `BEANDIY-STATION-01`），顾客**在小程序内扫码**即可自动识别台位。生成/打印在管理网页的「二维码」页完成（客户端生成，无需服务器）。

## 数据模型

**台位** `repo/data/stations.json`：
```json
{ "id": 1, "name": "A1 拼豆台位", "code": "BEANDIY-STATION-01", "status": "idle|in_use", "created_at": "..." }
```

**使用记录** `repo/data/sessions.json`：
```json
{
  "id": 1, "station_id": 1, "station_name": "A1 拼豆台位",
  "nickname": "微信用户昵称", "avatar": null,
  "start_time": "ISO时间", "end_time": null,
  "planned_minutes": 60, "actual_minutes": null, "status": "active|completed"
}
```

**设置** `repo/data/settings.json`：
```json
{ "price_per_hour": "30", "default_minutes": "60", "reminder_minutes": "10" }
```

## 安全小结

- **PAT 永不出浏览器**：管理后台的所有写操作经服务器 `/api`，PAT-A 只在服务器。
- **最小权限**：小程序 PAT-B 仅限 `repo/data/**`，泄露影响可控。
- **服务端校验**：`api/*.php` 校验字段、限流；可选 Admin Key 闸门。
- **无状态代理**：服务器不落本地库，需保护面更小。

详细威胁分析与部署/安全清单见 `docs/混合架构方案.md`。

# 拼豆工坊（BeanDIY）

扫码登记、计时使用、到时提醒的拼豆店小程序。**GitHub 仓库即数据库**；管理网页是单文件静态页面，支持两种部署：**方案 A 无服务器**（GitHub Pages，浏览器直连 GitHub API）或**方案 B 自备 PHP 代理**（令牌不下发到浏览器）。

> 安全底线：小程序与管理网页都只用一把 **仅限 `repo/data/**` 读写**的 fine-grained PAT（PAT-B）。
> 采纳方案 A 时**不需要** PAT-A；采纳方案 B 时 PAT-A 只存在服务器，永不进入浏览器。

## 项目矩阵

| 部分 | 位置 | 技术 | 职责 |
|------|------|------|------|
| **用户端小程序** | `miniapp/` | 微信小程序 | 扫码登记、计时、到时提醒（弱绑定微信昵称，不收集手机） |
| **电脑管理网页** | `repo/admin/index.html` | 纯 HTML/JS（单文件，零依赖） | 数据概览、台位占用总览、台位增删改、使用中会话与**续时（延长）**、强制结束、历史筛选与 CSV 导出、二维码打印、营业设置 |
| **数据后端** | ① GitHub API 直连（默认）<br>② `repo/api/*.php`（可选） | 浏览器 / PHP 8+ | 读写 `repo/data/*.json`；PHP 代理仅在你选择方案 B 时启用 |
| **后端数据存储** | `repo/data/*.json` + GitHub | GitHub 仓库即数据库 | 数据以 JSON 文件存于仓库 `repo/` 子目录 |

> 客户数据采用**弱绑定**：仅记录微信昵称（授权时获取），不收集手机号。扫码即绑定桌号，授权微信登录即可开始计时。

## 目录结构

```
repo/                          # ← 本目录即提交到 GitHub 的全部可部署内容（miniapp/ 仅本地、不入库）
├── index.html              # 入口页，跳转管理后台（方案 A 下由 GitHub Pages 托管）
├── admin/
│   ├── index.html          # 电脑管理网页（单文件；自动探测 直连 GitHub / 同源 /api/*.php）
│   └── beep.wav            # 到时提示音（经 jsDelivr CDN 提供给小程序）
├── api/                    # 【方案 B 可选】服务器 PHP 代理，不用服务器时可忽略整个目录
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

> 注意：微信小程序源码 `miniapp/` **仅存于本地、不提交 GitHub**（属客户端代码，且内含 PAT-B，不应公开）。本 `repo/` 目录才是入库内容；GitHub 仓库的树即 `repo/` 之下的这些文件。
> **方案 A** 无需上传任何文件，开 Pages 即可；**方案 B** 才需要把 `admin/` 与 `api/` 复制到 web 根（见下文步骤 3）。

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
| **PAT-B（唯一必需）** | 仅本仓库 `beandiy` | Read and write | **`repo/data/**`** | 小程序 `app.js` 的 `githubToken`；管理网页在「⚙ 设置」里填同一把（只存本机浏览器） |
| **PAT-A（可选，仅方案 B）** | 仅本仓库 `beandiy` | Read and write | 无（全仓库） | 服务器 `config.php`（**切勿进前端**） |

> **无服务器方案只需 PAT-B**：它只能读写 `repo/data/**`，即便小程序包被反编译抠出，也动不了代码与配置，爆破半径被锁死。
> PAT-A 只有在你后面启用自备 PHP 服务器（方案 B）时才需要。

### 3. 部署管理后台（二选一）

管理后台是**单文件静态页面**，内置两套数据后端并在打开时自动探测，所以两种部署方式都不用改代码：

| | 方案 A：无服务器（推荐起步） | 方案 B：自备 PHP 服务器（可选） |
|---|---|---|
| 做法 | 开 GitHub Pages，用浏览器直连 GitHub API | 上传 `admin/` + `api/`，由 PHP 代理读写 |
| 地址 | `https://你的用户名.github.io/beandiy/repo/admin/` | `https://你的域名/admin/` |
| 令牌 | 首次打开点「⚙ 设置」填 PAT-B，**只存本机浏览器** | 存服务器 `repo/api/config.php`（PAT-A），浏览器不持有 |
| 优点 | 零成本、零运维、不用域名备案 | 令牌不下发到浏览器；可加 `admin_key` 二次校验、限流 |
| 备注 | 页面与数据同在一个仓库，**仓库为公开**时数据也可被匿名读取 | 需要一台支持 PHP 的机器（或后续备案后迁移） |

#### 方案 A：无服务器（GitHub Pages 直连）

1. 仓库 → **Settings → Pages** → Source 选 **Deploy from a branch** → Branch `main`、目录 `/ (root)` → Save。
2. 等 1 分钟左右，访问 `https://你的用户名.github.io/beandiy/repo/admin/`。
3. 打开时是**只读**（能看数据）；点右上角 **⚙ 设置** → 填入 **PAT-B** → 保存，即变为「✓ 直连可写」，可增删台位、续时、改设置。
4. 令牌只保存在该浏览器（localStorage），不会出现在页面源码里；**公用电脑用完请点「清除令牌」**。

> 也可以直接双击本地 `repo/admin/index.html` 打开（无需 Pages）。但浏览器对 `file://` 的跨域策略各版本不一致，**建议用 Pages** 更稳。

#### 方案 B：自备 PHP 代理（可选）

1. 服务器安装 PHP 8.x（需 `curl` 扩展）+ Web 服务器（nginx + php-fpm，或 `php -S` 仅测试）。
2. 把 `repo/` 下的 `admin/` 与 `api/` 放到 web 根下（如 `/var/www/bean/`），使：
   - 管理网页地址：`https://你的域名或IP/admin/`
   - 接口地址：`https://你的域名或IP/api/stations.php` 等
3. 配置 `config.php`：
   - 复制 `api/config.sample.php` 到 `/etc/bean/config.php`（或由环境变量 `BEAN_CONFIG` 指向的路径）。
   - 填写 `owner` / `repo` / `pat_a`（即 **PAT-A**）。
   - 放到 web 根之外，并 `chmod 600`、属主为 Web 进程用户，避免被直接下载。
   - （可选）`admin_key` 填一个随机串；启用后所有写操作需在请求头带 `X-Admin-Key: 该串`（管理网页在 403 时会自动弹窗索取）。
4. 确认浏览器访问 `/api/config.php` 返回 404/403 而非下载内容。

> 页面的后端探测：能访问到同源 `/api/settings.php` 就走方案 B，否则自动切方案 A。
> 也可在「⚙ 设置」前手动锁定模式（localStorage 写 `bean_mode=direct` 强制走直连）。

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
4. 倒计时页面实时显示剩余时间，到时震动 + 提示音 + 弹窗，并提示「如需继续使用请联系工作人员办理续时」
5. 顾客需要继续使用 → 工作人员在管理网页「使用中」点「**延长**」（+15/+30/+60/+90 分钟或自定义）→ 顾客切回小程序即看到新的剩余时间与新的预计结束时间
6. 结束时由工作人员「结束」，或顾客在小程序内自行结束
7. 管理员在网页查看概览、使用中会话、历史记录（可筛选、导出 CSV）

### 权限模型：谁能延长

**续时是门店的经营动作，只能由工作人员在管理后台操作**，用户端不提供自助延长：

- 小程序计时页已**移除**「延长使用」按钮，改为止时弹窗 + 遮罩提示「联系店内工作人员办理续时」。
- 顾客切回小程序时（`onShow`）会拉取最新会话：后台续时 → 自动按新时长重算剩余时间、清除「时间到」状态并提示「已续时 N 分钟」；后台强制结束 → 提示并回到首页。
- 后台每次续时都会在该条使用记录里追加一条 `extensions` 明细（分钟数 / 时间 / 操作者 / 备注），便于对账。
- 未来若上线「购买套餐自动续时」，`miniapp/utils/api.js` 的 `extendSession()` 仍作为底层能力保留，可直接复用。

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
  "planned_minutes": 60, "actual_minutes": null, "status": "active|completed",
  "extensions": [
    { "minutes": 30, "at": "ISO时间", "by": "admin", "note": "购买加时套餐" }
  ]
}
```

> `extensions` 由管理后台「延长」时追加，仅用于对账展示（列表里显示「已续 N 次」，鼠标悬停看明细）。旧记录没有该字段，读取时按空数组处理。`planned_minutes` 已是续时后的总时长。

**设置** `repo/data/settings.json`：
```json
{ "price_per_hour": 30, "default_minutes": 60, "reminder_minutes": 10 }
```

> 均为**数字**类型（管理后台保存时统一转换）。`default_minutes` 决定顾客扫码登记时的初始时长；`reminder_minutes` 是到时前提醒分钟数，**填 `0` 表示完全不提醒**。

## 安全小结

- **最小权限**：全项目只用一把 PAT-B，权限仅 **`repo/data/**` 读+写**。即便小程序包被反编译、或管理网页所在设备被翻出令牌，也**动不了代码、配置与其它文件**。
- **令牌不进仓库**：管理网页源码、小程序包里都**不含任何令牌**（管理网页的令牌存在浏览器 localStorage，需当场输入）。
- **方案 B 额外收益**：令牌只存服务器 `config.php`（web 根之外、`chmod 600`），浏览器完全不持有；服务端还可做字段校验、限流与可选 Admin Key 闸门。
- **XSS 防护**：管理网页所有数据插值均经 `esc()` 转义，避免台位名/昵称注入脚本（否则可窃取 localStorage 中的令牌）。
- **公开仓库提醒**：若仓库为 **public**，`repo/data/*.json`（含顾客昵称与使用记录）可被任何人匿名读取。如需数据私密，把数据文件放到**独立的私有仓库**，再在管理网页「⚙ 设置」里把数据仓库指向它（小程序 `app.js` 的 `githubRepo` 同步改）——注意这样会失去 Pages 直连读取的免令牌便利。

详细威胁分析与部署/安全清单见 `docs/混合架构方案.md`。

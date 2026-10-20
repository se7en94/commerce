# VELORÉ 独立站 — 开发计划（Master Plan v1.0）

> **这份文档的目标**：让任何 AI（或人类开发者）读完后，能**从零接手**并继续开发，不用回头问上下文。
> 规则：每完成一个 Task，立即在本文档对应位置打上 `[x]`，并在 CHANGELOG 写一行记录。

---

## 0. 项目概览

| 项目 | 信息 |
|---|---|
| 客户 | VELORÉ（多品牌奢侈品买手，非品牌方）|
| 主力市场 | 美国、加拿大；其次欧洲、南美 |
| 业务流 | 客户下单 → 客户付款 → 客户付款给供应商 → 供应商发货 → 物流追踪 |
| 技术栈 | **WordPress + WooCommerce**（已否决 Next.js）+ MySQL 8 + Docker |
| 支付 | PayPal PPCP（钱包 + ACDC 信用卡直收），sandbox 已通 |
| 参考站 | fashionphile.com（美国最大二手奢侈品平台）|
| 非标需求 | 邀请码注册、6 种语言、多币种、自定义订单状态「待向供应商付款」|
| 交付文档 | [deliverables/](file:///Users/se7en/Downloads/woocommerce/deliverables) 下 01–05 |

---

## 1. 仓库地图

```
/Users/se7en/Downloads/woocommerce/
├── docker-compose.yml          # WP latest + MySQL 8，端口 8080/3307
├── .env                        # 真实凭证（gitignore）
├── .env.example                # 占位符模板（可提交）
├── configure_ppcp.php          # PPCP 配置脚本，读环境变量
├── import-products.php         # 商品批量导入脚本
├── woocommerce.php             # WooCommerce 主插件文件（vendored）
├── includes/ templates/ src/   # WooCommerce 插件源码（vendored）
├── wp-content/
│   └── themes/
│       └── twentytwentyfive-luxury/   # 自定义主题（骨架）
│           ├── functions.php
│           ├── style.css
│           ├── theme.json
│           ├── parts/                  # header / footer
│           └── templates/              # archive-product / single-product / front-page
├── deliverables/               # 客户交付文档（01–05）
│   └── 05-PayPal-sandbox到prod切换清单.md
└── scripts/
    └── pre-commit-hook         # 密钥扫描 hook（已安装到 .git/hooks/）
```

---

## 2. 环境约定（任何 AI 接手都要先读这节）

| 项 | 值 |
|---|---|
| 工作目录 | `/Users/se7en/Downloads/woocommerce` |
| 启动 | `docker compose up -d` |
| 重建注入新 env | `docker compose up -d --force-recreate wordpress` |
| WP 后台 | http://localhost:8080/wp-admin |
| 进入容器 | `docker exec -it woocommerce-wordpress-1 bash` |
| 跑 PPCP 配置 | `docker exec woocommerce-wordpress-1 php /var/www/html/wp-content/plugins/woocommerce/configure_ppcp.php` |
| 查看日志 | `docker logs -f woocommerce-wordpress-1` |
| 临时公网 URL | `ngrok http 8080` |

**密钥规则**：
- ❌ 永不硬编码到 `.php` / `.yml` / `.js`
- ✅ 一律放 `.env`，由 docker-compose `env_file` 注入
- ✅ pre-commit hook 已启用，会拦截疑似密钥

**WooCommerce 主题覆盖规则**（经验 244217）：
- 在 `wp-content/themes/twentytwentyfive-luxury/woocommerce/...` 路径下做**最小化覆盖**
- ❌ 不要把 `templates/` 整个复制进主题

**支付回调原子化规则**（经验 100006067）：
- webhook 收到后 5 步（验签 → 验金额 → 更新订单 → 扣库存 → 触发邮件）必须同事务
- 不依赖前端跳转

---

## 3. 当前状态快照（截至 2026-10-20）

### 已完成 ✅

| # | 任务 | 备注 |
|---|---|---|
| A1 | docker-compose 本地环境跑通 | WP + MySQL 8 容器 26h+ 稳定 |
| A2 | WooCommerce 插件源码 vendored | `includes/ templates/ src/ woocommerce.php` |
| A3 | PayPal sandbox 下单链路通 | 测试买家付款 → WP 后台可见订单 |
| A4 | `configure_ppcp.php` 重构 | 从环境变量读凭证，含 wp-load bootstrap |
| A5 | `.env` / `.env.example` / `.gitignore` 三件套 | example 全占位符 |
| A6 | pre-commit hook 密钥扫描 | 已实测拦截 |
| A7 | git 历史扫描 | **零提交，零泄露风险** |
| A8 | 主题骨架 | `twentytwentyfive-luxury/` 仅有 functions/style/theme.json + 空 parts/templates |
| A9 | 客户回复需求清单 | [奢侈品独立站需求确认清单-回复-VELORÉ.txt](file:///Users/se7en/Downloads/woocommerce/deliverables/奢侈品独立站需求确认清单-回复-VELORÉ.txt) |

### 进行中 🔄
无

### 阻塞中 ⛔

| # | 卡点 | 等谁 | 影响 |
|---|---|---|---|
| B1 | 客户开通 PayPal 真实商户号 | 客户 + PayPal 承保审核（1–3 周）| Phase 4 全部 |
| B2 | 客户提供品牌授权材料 | 客户 | 支付通道审核 |
| B3 | 客户确认结算币种 | 客户 | 多币种插件配置 |
| B4 | 客户书面同意「21 天资金留存」风险 | 客户 | 上线签收 |
| B5 | 客户确认 WooCommerce（否决 Next.js）| 客户 | 整体方向（已默认按 Woo 推进）|

---

## 4. 开发计划（按 Phase 分组）

> **打勾规则**：完成后把 `- [ ]` 改成 `- [x]`，并在 CHANGELOG 加一条 `YYYY-MM-DD 完成 T-x.y 简短描述`。

### Phase 0 — 本地闭环（不依赖客户）⭐ 当前焦点

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| T-0.1 | 首次干净 commit 建立基线 | `git log` 有 1 条 commit，工作区干净 | 0.5h |
| T-0.2 | ngrok 隧道 + PPCP webhook URL 配置 | PayPal 后台 Simulate `PAYMENT.CAPTURE.COMPLETED` → WP 容器日志收到 HTTP 200 | 1h |
| T-0.3 | sandbox 完整订单流测试 | 下单 → pending → on-hold → processing 全自动流转 | 1h |
| T-0.4 | sandbox 退款链路测试 | 全额退款 → 订单 refunded + PayPal 买家收到 | 0.5h |
| T-0.5 | 支付失败兜底测试 | 用 `4000 0000 0000 0002` 触发拒付，前端有清晰错误提示 | 0.5h |

### Phase 1 — 主题与视觉（Fashionphile 风格）

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| ✅ T-1.1 | `front-page.html` 模板 + Hero banner | 首页展示 hero 大图 + 3 列主推类目 | 4h |
| ✅ T-1.2 | `archive-product.html` 商品网格 | 4 列网格、hover 显价格、分页 | 4h |
| ✅ T-1.3 | `single-product.html` 详情页 | 大图左 + 信息右，含 breadcrumb | 3h |
| ✅ T-1.4 | `parts/header.html` + `parts/footer.html` | 顶部 nav + 底部 4 列链接 | 2h |
| T-1.5 | `style.css` 视觉规范 | 极简白底 + 衬线标题字体 + 大留白 | 3h |
| T-1.6 | `functions.php` 注册菜单/侧边栏/缩略图 | WP 后台可见菜单编辑器 | 1h |
| T-1.7 | 移动端响应式 | iPhone 12/13 视口下布局不破 | 2h |

### Phase 2 — 商品数据

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| T-2.1 | 整理 30 个种子商品（含图/价/库存）| `sample-data/products.csv` | 4h |
| T-2.2 | `import-products.php` 完善 | 跑一遍能导入全部 30 个商品 | 2h |
| T-2.3 | 商品类目树（品牌 → 类目 → 商品）| WP 后台类目树清晰 | 1h |
| T-2.4 | 商品属性（尺寸/颜色/材质）| 详情页能选变体 | 2h |

### Phase 3 — 核心非标功能

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| T-3.1 | **自定义订单状态**「待向供应商付款」 | 注册 `wc-pending-supplier` 状态，插入 processing 之后 | 2h |
| T-3.2 | **邀请码注册**（方案 B）| Custom Registration Fields 插件 + `woocommerce_register_post` hook 校验；无效码拒绝注册 | 6h |
| T-3.3 | 邀请码管理后台 | 管理员能生成/作废/查看使用记录 | 4h |
| T-3.4 | **多语言**（Polylang 免费版）| 英/中/西/德/葡/法 6 语言切换；商品可逐语言翻译 | 8h |
| T-3.5 | **多币种**（CURCY 或 Aelia）| USD/CAD/SGD/EUR 切换，汇率自动 | 3h |
| T-3.6 | **物流追踪**（Advanced Shipment Tracking）| 输单号自动识别承运商 + 抓轨迹 | 3h |

### Phase 4 — 营销与分析

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| T-4.1 | GTM4WP 插件 + GA4 事件 | view_item/add_to_cart/begin_checkout/purchase 全埋点 | 3h |
| T-4.2 | Meta Pixel 集成 | 同上事件同步到 Meta | 1h |
| T-4.3 | 邮件模板品牌化 | 订单确认/发货/退款邮件带 VELORÉ 视觉 | 3h |
| T-4.4 | SEO 基础（Yoast）| 站点地图 + OG 标签 + meta | 2h |

### Phase 5 — 硬骨头测试（必须真实环境）

> 详见 [05-PayPal-sandbox到prod切换清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/05-PayPal-sandbox到prod切换清单.md)

| Task | 描述 | 预估 |
|---|---|---|
| T-5.1 | 3DS/SCA 真实欧洲卡测试 | 1d |
| T-5.2 | 多币种真实汇率测试 | 0.5d |
| T-5.3 | 风控触发 + 资金留存演练 | 0.5d |
| T-5.4 | 支付回调原子化压测 | 1d |

### Phase 6 — 生产部署（依赖 B1–B5）

| Task | 描述 | 预估 |
|---|---|---|
| T-6.1 | 域名 + DNS + Cloudflare | 0.5d |
| T-6.2 | 服务器（DigitalOcean / Vultr）+ Docker 部署 | 1d |
| T-6.3 | SSL + WP_HOME/SITEURL 改 https | 0.5d |
| T-6.4 | 数据库迁移 + 媒体文件迁移 | 1d |
| T-6.5 | 切换真实 PPCP 凭证 + use_sandbox=false | 0.5d |
| T-6.6 | 真实金额测试单 + 退款 | 0.5d |
| T-6.7 | 客户签收文档（3 份）| 0.5d |

---

## 5. CHANGELOG（按时间倒序）

```
2026-10-20  完成 T-1.3  single-product 详情页验收通过（breadcrumb + 55/45 两栏 + gallery + related products）
2026-10-20  完成 T-1.2  archive-product 商品网格定稿（4 列 + hover 显价格 + 图片 4/3 裁切）+ style.css 视觉规范 v1
            踩坑①：WP 数据库 wp_posts 里 post_type='wp_template' 的自定义模板会覆盖主题 .html 文件
                   （站点编辑器保存过就会生成）。改主题模板后页面无变化时，先查 DELETE FROM wp_posts
                   WHERE post_type='wp_template' AND post_name='xxx';
            踩坑②：Block theme 不会自动加载 style.css，必须在 functions.php 手动 wp_enqueue_style
2026-10-20  完成 T-1.1  front-page.html 首页模板上线（Hero/类目/精选商品/价值主张/寄卖CTA + AI 生成视觉素材）
            注意：首页类目链接指向 /product-category/{handbags,watches,jewelry}/，需在 WP 后台建好这三个类目
2026-10-20  完成 T-1.4  header.html / footer.html 定稿
2026-10-20  完成 A8     git 历史清洁确认 + GitHub 首推（c2dd0ea）+ 主题目录实时挂载（docker-compose 增加 ./wp-content/themes 卷）
2026-10-20  完成 A7  git 历史扫描确认零提交零泄露
2026-10-20  完成 A6  pre-commit hook 安装并实测拦截
2026-10-20  完成 A5  .env.example 全占位符化
2026-10-20  完成 A4  configure_ppcp.php 重构（读环境变量 + wp-load bootstrap）
2026-10-19  完成 A3  PayPal sandbox 下单链路通
2026-10-19  完成 A2  WooCommerce 插件 vendored
2026-10-19  完成 A1  docker-compose 本地环境跑通
```

---

## 6. 接手引导（新 AI / 新开发者）

> 按以下顺序读文档，再开始干活。

1. **读本文件**（你在这里）
2. 读 [deliverables/04-VELORÉ需求说明书-v1.0.md](file:///Users/se7en/Downloads/woocommerce/deliverables/04-VELORÉ需求说明书-v1.0.md) — 客户业务需求
3. 读 [deliverables/05-PayPal-sandbox到prod切换清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/05-PayPal-sandbox到prod切换清单.md) — 上线操作
4. 读 [.env.example](file:///Users/se7en/Downloads/woocommerce/.env.example) — 环境变量约定
5. 读 [configure_ppcp.php](file:///Users/se7en/Downloads/woocommerce/configure_ppcp.php) — 凭证读取方式
6. 跑 `docker compose up -d` → 访问 http://localhost:8080 确认环境正常
7. 从 Phase 0 第一个 `[ ]` 任务开始

---

## 7. 与客户沟通议程（按优先级）

| # | 议题 | 目的 | 卡点 |
|---|---|---|---|
| C1 | 技术栈确认（WooCommerce vs Next.js）| 让客户书面签收 Woo 方案 | B5 |
| C2 | 收款主体公司信息 | PayPal 商户号申请 | B1 |
| C3 | 品牌授权材料清单 | 通道承保审核 | B2 |
| C4 | 邀请码注册规则 | 谁生成 / 有效期 / 失效流程 | T-3.2 |
| C5 | 结算币种 | 多币种配置 | B3 |
| C6 | 21 天资金留存书面同意 | 风险签收 | B4 |

---

## 8. 风险登记

| 风险 | 概率 | 影响 | 缓解 |
|---|---|---|---|
| PayPal 通道审核不通过 | 中 | 高 | 备选 Stripe / Airwallex |
| 客户没品牌授权 | 高 | 高 | 提前告知客户准备 |
| 6 语言翻译成本高 | 高 | 中 | 优先做英文，其他用机器翻译+人工审 |
| 物流承运商账号缺失 | 高 | 中 | AfterShip 聚合降级方案 |
| 高客单价风控触发资金留存 | 中 | 中 | 提前书面告知客户 |

---

**文档维护**：本文件由开发过程驱动更新。每次完成一个 Task、新增一个变更、踩一个坑，都应该在 CHANGELOG 里加一条 + 把对应 `- [ ]` 改成 `- [x]`。

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

## 3. 当前状态快照（截至 2026-10-21）

### 总体进度

- **Phase 0–4 开发任务全部完成**（T-0.2 ngrok webhook 增强验证除外，见下）
- 交付文档：deliverables/ 01–07（05 切换操作 / 06 支付测试 / 07 部署验收）
- 最新 commit：2a1ee82，工作区干净，全部推送 GitHub main
- 主题版本 1.0.6；mu-plugins 九件（邀请码/订单状态/i18n/货币/像素/品牌化/邮件依赖/vendor-libs）

### 已完成 ✅

| # | 任务 | 备注 |
|---|---|---|
| A1–A9 | 环境基线 | docker-compose / vendored Woo / sandbox 下单通 / PPCP 脚本 / .env 三件套 / pre-commit / git 零泄露 / 主题骨架 / 客户需求清单 |
| Phase 1 | 主题与视觉 | 全部 8 任务：Noir 暗底 + 金色定稿、移动端响应式、搜索页 |
| Phase 2 | 商品数据 | 全部 4 任务：30 个种子商品、导入脚本、类目树、属性 |
| Phase 3 | 核心非标功能 | 全部 6 任务：pending-supplier 状态、邀请码闭环（6 位码/管理页/三档模式）、六语言、多币种、物流追踪 |
| Phase 4 | 营销与分析 | 全部 4 任务：GA4 四事件、Meta Pixel 五事件、邮件 Noir 品牌化（含 Emogrifier 修复）、Yoast SEO 全套 |

### 未完事项（本地可做，不阻塞）

| # | 任务 | 说明 |
|---|---|---|
| T-0.2 | ngrok webhook 闭环 | sandbox 订单状态自动流转依赖 webhook，本地 localhost 收不到；可用 ngrok 隧道补验，或直接并入 Phase 5 生产测试 |
| T-0.4/0.5 | sandbox 退款/拒付兜底 | 同上依赖 webhook，已在 06 清单 T-5.x 覆盖生产用例 |

### 阻塞中 ⛔（只阻塞 Phase 5/6，开发已全部完成）

| # | 卡点 | 等谁 | 影响 |
|---|---|---|---|
| B1 | 客户开通 PayPal 真实商户号 | 客户 + PayPal 承保审核（1–3 周）| Phase 5 测试 + Phase 6 上线（硬瓶颈，建议客户立即启动）|
| B2 | 客户提供品牌授权材料 | 客户 | 支付通道审核（B1 前置）|
| B3 | 客户确认结算币种 | 客户 | 生产 CURCY 配置（本地已按 USD 基准推进）|
| B4 | 客户书面同意「21 天资金留存」风险 | 客户 | 上线签收文档 |
| B5 | 客户确认 WooCommerce | 客户 | 已默认按 Woo 推进，待正式确认 |

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
| ✅ T-1.8 | `templates/search.html` 搜索结果页 | 3 列结果网格 + 无结果重搜表单 | 2h |
| ✅ T-1.5 | `style.css` 视觉规范 | Noir 暗底定稿（替代原白底方案）+ 衬线标题 + 大留白 | 3h |
| ✅ T-1.6 | `functions.php` 注册菜单/侧边栏/缩略图 | WP 后台可见菜单编辑器 | 1h |
| ✅ T-1.7 | 移动端响应式 | iPhone 12/13 视口下布局不破 | 2h |

### Phase 2 — 商品数据

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| ✅ T-2.1 | 整理 30 个种子商品（含图/价/库存）| `sample-data/products.csv` | 4h |
| ✅ T-2.2 | `import-products.php` 完善 | 跑一遍能导入全部 30 个商品 | 2h |
| ✅ T-2.3 | 商品类目树（品牌 → 类目 → 商品）| WP 后台类目树清晰 | 1h |
| ✅ T-2.4 | 商品属性（尺寸/颜色/材质）| 详情页显示 Brand + Condition（寄卖孤品无变体可选，验收标准重新定义）| 2h |

### Phase 3 — 核心非标功能

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| ✅ T-3.1 | **自定义订单状态**「待向供应商付款」 | 注册 `wc-pending-supplier` 状态，插入 processing 之后 | 2h |
| ✅ T-3.2 | **邀请码注册**（方案 B）| Custom Registration Fields 插件 + `woocommerce_register_post` hook 校验；无效码拒绝注册 | 6h |
| ✅ T-3.3 | 邀请码管理后台 | 管理员能生成/作废/查看使用记录 | 4h |
| T-3.4 | ~~**多语言**（Polylang 免费版）~~ ✅ 完成（6 语言 + 内容随语言切换，见 CHANGELOG）| 英/中/西/德/葡/法 6 语言切换；商品可逐语言翻译 | 8h |
| T-3.5 | ~~**多币种**（CURCY 或 Aelia）~~ ✅ 完成（CURCY 免费版 + velore-currency.php，见 CHANGELOG）| USD/CAD/SGD/EUR 切换，汇率自动 | 3h |
| T-3.6 | ~~**物流追踪**（Advanced Shipment Tracking）~~ ✅ 完成（AST 免费版 v4.0.4，见 CHANGELOG）| 输单号自动识别承运商 + 抓轨迹 | 3h |

### Phase 4 — 营销与分析

| Task | 描述 | 验收标准 | 预估 |
|---|---|---|---|
| T-4.1 | ~~GTM4WP 插件 + GA4 事件~~ ✅ 完成（GTM4WP v2.0.6 常量硬编码模式，见 CHANGELOG）| view_item/add_to_cart/begin_checkout/purchase 全埋点 | 3h |
| T-4.2 | ~~Meta Pixel 集成~~ ✅ 完成（velore-pixel.php v1.0.0 自研，见 CHANGELOG）| 同上事件同步到 Meta | 1h |
| T-4.3 | ~~邮件模板品牌化~~ ✅ 完成（velore-branding.php + Emogrifier 三库，见 CHANGELOG）| 订单确认/发货/退款邮件带 VELORÉ 视觉 | 3h |
| T-4.4 | ~~SEO 基础（Yoast）~~ ✅ 完成（Yoast 28.7 + indexables 全量索引，见 CHANGELOG）| 站点地图 + OG 标签 + meta | 2h |

### Phase 5 — 硬骨头测试（必须真实环境）

> 操作步骤：[05-PayPal-sandbox到prod切换清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/05-PayPal-sandbox到prod切换清单.md)
> 测试用例与验收标准：[06-Phase5-PayPal测试检查清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/06-Phase5-PayPal测试检查清单.md)

| Task | 描述 | 预估 |
|---|---|---|
| T-5.1 | 3DS/SCA 真实欧洲卡测试 | 1d |
| T-5.2 | 多币种真实汇率测试 | 0.5d |
| T-5.3 | 风控触发 + 资金留存演练 | 0.5d |
| T-5.4 | 支付回调原子化压测 | 1d |

### Phase 6 — 生产部署（依赖 B1–B5）

> 检查用例与验收标准：[07-Phase6-生产部署测试检查清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/07-Phase6-生产部署测试检查清单.md)

| Task | 描述 | 预估 |
|---|---|---|
| T-6.1 | 域名 + DNS + Cloudflare | 0.5d |
| T-6.2 | 服务器（DigitalOcean / Vultr）+ Docker 部署 | 1d |
| T-6.3 | SSL + WP_HOME/SITEURL 改 https | 0.5d |
| T-6.4 | 数据库迁移 + 媒体文件迁移 | 1d |
| T-6.5 | 切换真实 PPCP 凭证 + use_sandbox=false | 0.5d |
| T-6.6 | 真实金额测试单 + 退款 | 0.5d |
| T-6.7 | 客户签收文档（3 份）| 0.5d |
| T-6.8 | 生产检查：blog_public=1 / SMTP 插件配置 / 店铺地址补全 / OG 图换正式品牌图 | 0.5d |

---

## 5. CHANGELOG（按时间倒序）

```
2026-10-21  产出 T-6.x  部署验收文档 deliverables/07-Phase6-生产部署测试检查清单.md：
            按 T-6.1~T-6.8 展开为 40+ 可勾选检查项 + 上线后 24–48h 监控 + 退出/
            回滚标准。与 05/06 分工：05 管切换操作、06 管支付测试、07 管部署验收。
            项目特定项：Cloudflare 动态路径绕过缓存清单（cart/checkout/wp-json/
            wc-api）+ Rocket Loader 必关（破坏 PayPal 按钮 JS）；迁移完整性含
            Polylang 翻译关联、woo_shippment_provider 承运商表、邀请码表；
            测试数据清理专项（订单 #71/#442、Test Buyer、user#13、店铺地址占位）；
            生产密钥 grep 扫描（client_secret 零命中）；上线后 webhook/GA4/Pixel/
            PHP Fatal/邮件投递五项监控
2026-10-21  产出 T-5.x  测试用例文档 deliverables/06-Phase5-PayPal测试检查清单.md：
            按 T-5.1~T-5.4 展开为 23 个可勾选用例 + 前置条件 7 项 + 测试记录总表 +
            失败排查表 + 退出/回滚标准。与 05 清单的分工：05 管「怎么切」，
            06 管「测什么、怎么算过」。关键设计：①T-5.2 明确测的是 PayPal 侧
            钱包币种转换而非站内多币种结账（CURCY 结账恒 USD 是前提，避免误测）；
            ②T-5.4 原子化压测 9 用例（幂等重放/乱序/并发/中断恢复/金额篡改/
            库存原子性），幂等与库存失败列为上线阻断项（零容忍）；③T-5.3 资金
            留存演练闭环到 B4 书面签收
2026-10-21  完成 T-4.4  SEO 基础（Yoast SEO 28.7 容器内安装，不入 git）：
            ①sitemap：Yoast 接管（sitemap_index.xml → post/page/product/
            product_cat 子表，product-sitemap 181 URL 含六语言商品）；robots.txt
            合并 WC 规则 + Yoast block（含 sitemap 引用）；②OG：首页全套
            （title/desc/url/image 1840x1380/site_name/locale + 5 语言 alternate）、
            商品页自动取商品图 + og:image:width/height/type + yoast-schema-graph
            JSON-LD + WC Product schema 双结构化数据；③meta：首页手写 description
            （同步 post meta _yoast_wpseo_metadesc + indexable.description）、商品用
            wpseo_titles[metadesc-product]=%%excerpt%% 模板自动填充、tagline 补
            「Curated pre-owned luxury…」；④canonical 首页/商品/zh 页全出、
            hreflang 六语言（Yoast + Polylang 协作）；⑤twitter:card large_image。
            踩坑㉘：blog_public=0（WP「建议搜索引擎不索引」本地默认勾选）会让
            Yoast 全站 noindex 且抑制 canonical——必须先 update_option('blog_public',1)
            再重建索引，上线检查清单加此项（生产必为 1）。踩坑㉙：Yoast v28 的
            canonical/sitemap 依赖 indexables 表，激活后仅 6 行（未索引）——
            无 wp-cli 时用 YoastSEO()->classes->get(Actions\Indexing\*) 逐个调
            index()（Post/Term/PostTypeArchive/General 四动作共 108 行），PHP
            单引号传命名空间类名（双引号 \\ 转义后容器按字面量查找报 non-existent
            service）
2026-10-21  完成 T-4.3  邮件品牌化（velore-branding.php v1.0.0 + velore-email-
            dependencies.php + vendor-libs 三库捆绑）：黑底金字排版字标（table 布局
            Outlook 兼容）、金色衬线 h1/h2、品牌页脚「VELORÉ — Curated Luxury」；
            DB 邮件色板（base=#111111 / background=#F6F4EF / body=#FFFFFF /
            text=#1A1A1A）+ 启用 email_improvements 特性（WC 10.x block 邮件模板）；
            AST 追踪块蓝色硬编码（按钮 #005b9a / 追踪号 #03a9f4）经 woocommerce_mail_
            content filter（style_inline 之后执行）替换为黑底金字按钮 + 金色追踪号。
            重大隐患修复：WC 10.x 不再捆绑 Emogrifier，style_inline() 会 Fatal
            （CssInliner 类不存在）→ 生产邮件全部裸样式；捆绑 pelago/emogrifier 7.3.0
            + symfony/css-selector 6.4.0 + sabberworm/PHP-CSS-Parser 8.7.0（mu-plugins/
            vendor-libs/，PSR-4 spl 自动加载，101 文件 724K）。验收：真实发送管线
            （get_content_html → style_inline → mail_content）输出 17.2KB 全内联样式，
            chrome 截图目验黑字标/金标题/黑金按钮/象牙底/品牌页脚全过。
            踩坑㉔：字标 hook 用 woocommerce_email_header 优先级 10 会注册在 WC 回调
            之前，输出到 <!DOCTYPE> 之前破坏文档（Emogrifier 报 no HEAD element）——
            优先级必须 >10（现用 15）。踩坑㉕：Emogrifier 依赖链三库缺一不可
            （CssInliner → CssSelector → PHP-CSS-Parser），sabberworm 包名是
            sabberworm/php-css-parser 且仓库已迁移 MyIntervals/PHP-CSS-Parser（tag v8.7.0）。
            踩坑㉖：验证内联样式颜色时 strpos 大小写敏感会误判（sabberworm 序列化把
            #C9A96A 小写化为 #c9a96a）——检查必须大小写不敏感。本地无 SMTP 为已知
            部署项（需配 SMTP 插件）；站点店铺地址占位「cs, cs」待客户上线前补全
2026-10-21  完成 T-4.2  Meta Pixel 集成（velore-pixel.php v1.0.0，自研注入）：
            GTM4WP 免费版无 Pixel 通道 → mu-plugin 直挂。VELORE_META_PIXEL_ID 环境变量
            门控（.env → WORDPRESS_CONFIG_EXTRA 常量，空值零输出零报错）；五事件：
            PageView（基座）、ViewContent（商品页 SKU+价格）、AddToCart
            （woocommerce_add_to_cart → velore_pixel_before_pageview）、InitiateCheckout
            （is_checkout 非感谢页）、Purchase（order-received + hash_equals key 校验）；
            注入点 wp_head 优先级 1 + wp_body_open noscript。浏览器验证 5/5 事件
2026-10-21  完成 T-4.1  GA4 埋点（GTM4WP v2.0.6 容器内安装）：GTM4WP_HARDCODED_
            GTM_ID 常量硬编码模式（wp-config 经 WORDPRESS_CONFIG_EXTRA 注入，免 UI
            配置）；woocommerce 层开启 classic+block 双跟踪。浏览器验证四事件：
            view_item（商品页 DL ecommerce.items）、add_to_cart（AJAX 后触发）、
            begin_checkout（结账页）、purchase（新建订单 #442 感谢页）。
            踩坑㉗：gtm4wp-options 里的开关必须是 PHP 布尔 true（序列化 b:1）而非
            整数 1（i:1）——v2 用 true !== 严格比较，整数不生效；purchase 事件有
            订单新鲜度窗口+防重复（旧订单 #71 的感谢页被正确抑制属预期行为）
2026-10-21  完成 T-3.6  物流追踪（AST 免费版 woo-advanced-shipment-tracking v4.0.4，
            容器内安装，不入 git）：1084 家承运商从 TrackShip 公开 API 同步入库
            （api.trackship.com/v1/shipping_carriers/all）；后台订单页 Shipment Tracking
            面板原生兼容 HPOS。E2E 全通（订单 #71）：pending-supplier 状态订单加追踪
            （FedEx 774912345678）→ 订单自动流转 completed（add_tracking_item 的
            status_shipped=1 路径，即业务流「供应商发货→完成」）；客户 view-order 页
            渲染单号/承运商/追踪链接，My Account 订单列表出现 Track 按钮。
            能力边界：wc-shipped 状态与单号自动识别承运商是 Pro/TrackShip（付费 SaaS）
            功能，免费版为 1084 家下拉手选 + 追踪链接直达承运商官网；completed 邮件
            含追踪信息（免费）但本地无 SMTP（已知部署项）。踩坑㉓：AST 激活后的承运商
            种子是 Action Scheduler 异步 + 远程拉取，激活后立查 providers 为 0——需同步
            跑 ast_insert_shipping_providers() + ast_insert_shipping_provider()；表名是
            woo_shippment_provider（Zorem 拼写）非直觉的 shipment_providers。
            演示数据：订单 #71 保留 FedEx 追踪项（后台订单页可直接查看效果）
2026-10-21  完成 T-3.5  多币种（CURCY 免费版 2.1.1 + velore-currency.php v1.0.0）：
            5 币种 USD/EUR/CAD/SGD/BRL，基准 USD（修复遗留 HKD 基准设置）；语言→货币
            自动映射（en/zh→USD，es/de/fr→EUR，pt→BRL），手动 ?currency= 切换后写 30 天
            覆盖 cookie 优先于映射；GeoIP 自动检测关闭（与语言映射冲突）；header 货币下拉
            与语言切换器同 Noir 风格。验收：5 币种商品页价格换算正确（含 BRL 巴西格式）、
            /de/ 页 €9,016 同请求生效、手动选择跨语言保持、PayPal 网关完好、
            **Store API 带 EUR cookie 购物车总额仍 USD 980000**（pmcs_cart/checkout_
            default_currency=yes 强制结账走基准美元，CURCY will_change() 在 cart/checkout/
            account 页拒绝换汇——PayPal 安全前提，已实测）。
            踩坑㉑：验证价格时 grep 到「$0.00」误判为 CURCY 损坏商品数据——实际是空购物车
            mini-cart 小计的正常值；判断前必须看具体元素 HTML 上下文（商品 price_json 完好
            即无损坏）。踩坑㉒：模糊 DELETE LIKE "%pmcs_%" 误删 3 个 pmcs 配置项（default/
            cart_default/checkout_default）——清理语句必须枚举精确 option_name。另：CURCY
            在 wp 钩子（非 init）才解析货币，mu-plugin 同步放 init 即可当请求生效
2026-10-21  增强 T-3.2  注册表单新增确认密码字段（mu-plugin v1.3.0，客户需求）：
            渲染在密码之后、邀请码之前（优先级 9）；后端在 woocommerce_register_post
            校验一致性，不一致报「Passwords do not match」拒绝建号。仅当表单实际提交
            password2 时才校验——程序化注册路径（后台/Store API/wc_create_new_customer）
            不受影响。HTTP 层验证 3/3：字段顺序正确（password→password2→invite）、
            不一致拒绝且不建号、一致注册成功且立即可用密码登录
2026-10-21  修复 T-3.2  注册功能「无实际效果」复审（客户反馈）：E2E 三路径重测发现
            根因——woocommerce_registration_generate_password 原为 yes：注册表单无
            密码框，WC 生成临时密码发邮件，而本地 Docker 无邮件服务（sendmail 全程
            报错），用户注册后永远收不到密码、登不进账号 → 功能形同虚设。
            修复：该选项改 no，注册时站内自设密码；HTTP 层权威测试（nonce+POST+
            wp_authenticate）：注册 user#13 成功、自定义密码立即可登录、无码注册正常，
            三路径（无码/有效码/无效码）浏览器全过。
            连带修复：zh 页账户图标链接跳回英文 /my-account/——免费版 Polylang 不翻译
            WC 页面选项，且 WC 页面链接的 filter 是动态命名的
            woocommerce_get_{page}_page_permalink（踩坑⑳：不存在通用的
            woocommerce_get_page_permalink hook，第一版挂错位置全无效），已在
            velore-i18n.php 挂 myaccount/shop/cart/checkout 四个动态 filter，
            zh 账户图标现指向 /zh/my-account-zh/，EN 不受影响。
            复审结论：注册功能当前完整可用（站内密码+选填邀请码+原子核销）；
            注意本地环境无邮件服务，后续上线生产前需配 SMTP 插件（订单/密码重置
            邮件依赖），已列入部署清单
2026-10-21  修复 T-3.4  导航双份 + header 变窄 + 下拉过宽（客户反馈）：
            ①根因：自定义 PHP 渲染器调 get_header()/get_footer()，而 block 模板
            front-page.html 自带 header/footer template-part → zh 页整块 header 渲染两次。
            正确做法：WP 文件型 block 模板走 template-canvas.php + 全局
            $_wp_current_template_content（踩坑⑲：get_block_template 过滤器不在文件模板
            解析路径上，替换该全局才是标准切入点）。已删除渲染器与 header.php/footer.php
            shim，主题恢复纯 block 结构，EN/zh 均 single header、零 Deprecated；
            ②下拉过宽：主题表单样式 select{width:100%} 撑爆图标行——切换器加
            flex:0 0 auto + width:auto/max-width:96px 修复，header 布局恢复原宽
            （浏览器 4/4：单导航/下拉 96px/1600px 约束正常/zh 同样正常）
            mu-plugin velore-i18n.php v1.1.0
2026-10-21  修订 T-3.4  多语言体验升级（客户需求）：①切换器从语言码横排改为下拉选择
            （原生 select，Noir 风格化：透明底/金色文字/细下划线/自定义 chevron）；
            ②页面内容随语言真正切换，四层实现：
            a. WooCommerce/WP UI 字符串——5 locale 语言包手动安装（wp-admin 在线装 zh_CN
               成功、其余 4 个走 translations API zip 手动解包，wp-content/languages/），
               商品页 加入购物车/成色 标签已随 locale 输出
            b. 导航——每语言一个 wp_navigation post（velore_nav_ids option），mu-plugin
               pre_render_block 按 pll_current_language 换 ref
            c. 首页/footer——front-page-{lang}.html / footer-{lang}.html 变体（生成脚本
               str_replace 翻译映射），mu-plugin 分别在 template_include /
               pre_render_block(template-part) 接管；EN 始终走原文件零开销
            d. 页面——Shop/My account/Sell With Us 翻译页已建并 pll_save_post_translations
               关联；类目术语 6 语言关联；30 商品 ×5 语言翻译已建（150 个新商品，
               short desc 成色/配件词条映射翻译，meta/特色图/类目/属性全拷贝）
            ③修复：footer 导航 URL 是 block 注释属性格式 url":"/shop/"，只替换 href=" 会
            漏掉（踩坑⑰：block 变体翻译必须同时处理 href= 与注释属性 url": 两种格式）；
            类目翻译 slug 是 handbags-zh，链接必须指向 -zj 后缀 slug 而非原 slug 加前缀；
            zh 页出现「无 header.php 的主题已废弃」通知——block 主题子主题补 header.php/
            footer.php shim（block_template_part）解决（踩坑⑱）
            验收：浏览器 4/4（下拉渲染/zh 内容全中文化/footer 链接/手袋类目页中文 grid），
            curl 复核 en 页零 Deprecated、zh 页 html lang=zh-CN；30 个 zh 商品可见
            mu-plugin 新增 velore-i18n.php（v1.0.0，语言模板切换）；主题 v1.2.0
            已知边界：商品长描述与成色属性值仍是英文（词条级翻译属运营工作）；
            /zh/ 会 301 到 /zh/shop-zh/（zh 首页=Shop 翻译页，与 EN page_on_front 结构
            一致，canonical 行为，不影响体验）
2026-10-21  修订 T-3.4  多语言基础设施上线（Polylang 3.9 免费版，Docker 内手动安装激活）：
            6 语言 en(默认)/zh/es/de/pt/fr，URL 前缀模式 /{lang}/（默认语言无前缀）；
            product/product_cat/product_tag 已启用翻译；全部存量内容（30 商品、3 类目、
            77 posts）已挂 en，避免语言过滤后前台消失；清理 WC 样品遗留空类目 7 个
            （Accessories/Clothing/Decor/Hoodies/Music/Tshirts/重复 Handbags）。
            主题接入：header.html 插入 polylang/language-switcher block（codes 横排
            强制首页 force_home），style.css v1.1.0 Noir 风格化（细分隔线/金色当前语言/
            与图标组 32px 同高对齐）。浏览器验收 3/3：六码显示、EN 金色高亮、切 /zh/ 正常、
            无错位换行；各语言 URL 全部 200
            免费版能力边界（重要）：
            - hide_if_empty 遗留默认 true：没有内容的语言不显示在切换器——block 标记里
              已显式 "hide_if_empty":false 才能全量展示（block.json 未声明该属性但
              render 会透传给 Settings）
            - 未翻译商品不会回退显示：/zh/shop/ 在商品翻译完成前是空的（严格语言过滤，
              回退是 Pro 功能）——商品逐语言翻译是后续运营工作
            - 切换器已设 force_home=true：点语言先跳该语言首页，避免未翻译页 404；
              翻译覆盖后再改回默认（跟随当前页）
            踩坑⑮：Polylang 3.9 API 大改版——PLL()->model->set_post_language() 等
                   方法已移除，改用 pll_set_post_language()/pll_get_post_language()
                   等 API 函数；CLI 下操作必须 define('PLL_ADMIN', true) 且先加
                   $_SERVER['HTTP_HOST']，否则 PLL() 返回 null
            踩坑⑯：Block 主题的 block 标记属性透传——block.json 未声明的属性
                   （如 hide_if_empty）不会在编辑器 UI 出现，但手写进 HTML 注释
                   后会原样传给 render 的 $attributes，可用于解锁隐藏设置项
2026-10-21  修订 T-3.2  新增邀请码后台设置项（mu-plugin v1.2.0）：Invite Codes 页顶部
            Settings 区块——①模式三档切换：optional（选填，默认）/ required（恢复邀请制，
            空码拒绝）/ disabled（隐藏字段不校验）；②码长度 4–12 可调（影响新生成的码，
            placeholder 同步）。模式切换纯后台操作，无需改代码。CLI 7/7 验证三档模式
            与长度设置端到端生效，默认值已还原 optional/6
2026-10-21  修订 T-3.2  码格式二次缩短（客户需求）：XXXX-XXXX → 6 位纯字母数字无分隔
            （如 3FEW28），velore_ic_random_block 参数化长度，placeholder 同步 XXXXXX。
            重启清 OPcache 后格式/归一化验证通过，测试数据已清理（mu-plugin v1.1.1）
2026-10-21  修订 T-3.2  邀请码产品语义调整（客户需求）：①码格式缩短 VELORE-XXXX-XXXX-XXXX →
            XXXX-XXXX（去掉前缀，31^8 ≈ 8.5e11 空间仍不可暴力枚举）；②邀请码改为选填——
            空码直接注册，填了才校验+原子核销（无效码依旧拒绝，绕过面不变）。
            端到端复测：CLI 11/11（走真实 wc_create_new_customer 钩子链路：无码成功/
            无效码拒/有效码核销/复用回滚），浏览器 3/3（optional 标签/无码注册成功/短码拒绝）
            踩坑⑭：mu-plugin 文件改完后 Web 端可能仍跑旧代码（mod_php OPcache 持有旧编译
                   产物 + 浏览器对 HTML 的启发式缓存）而 CLI 每次起新进程永远看到新代码——
                   「CLI 测试通过但页面没变」先 docker compose restart wordpress +
                   URL 加 cache-busting 参数复测，别先怀疑代码
2026-10-21  完成 T-3.2/T-3.3  邀请码注册闭环上线：自研方案（替代 Custom Registration Fields
            三方插件）wp-content/mu-plugins/velore-invite-codes.php。自定义表
            wp_velore_invite_codes / wp_velore_invite_code_uses（dbDelta 自建）。
            后端原子化校验（硬约束）：校验挂 woocommerce_register_post（在
            wc_create_new_customer 内触发，天然覆盖 My Account/经典 checkout/Store API/
            REST 全部建户路径）；核销挂 woocommerce_created_customer，单条条件 UPDATE
            （used_count+1 WHERE status=active AND used_count<max_uses AND 未过期），
            DB 层原子无竞态，核销失败自动回滚删除刚建账号。管理后台：WooCommerce →
            Invite Codes（批量生成/多用途/有效期/内部备注、作废/激活/删除、使用记录、
            生成后明码展示一次）。码格式 VELORE-XXXX-XXXX-XXXX（31 字符表，无 0O1I）。
            验证：CLI 16/16 通过（原子性/多用途/作废/过期/大小写归一化/使用日志），
            浏览器 5/5 通过（字段渲染/无效码拒/空码拒/有效码注册成功/复用码拒绝），
            测试数据已清理
            数据库侧变更（不在 git）：woocommerce_enable_myaccount_registration=yes
            （此前站点全部自助注册路径为关闭状态，My Account 注册现为唯一公开入口）
            踩坑⑬：My Account 表单的 woocommerce_process_registration_errors 过滤器
                   只覆盖该表单路径——要全局防绕过必须挂 woocommerce_register_post
                   （它在 wc_create_new_customer 内部触发，覆盖所有建户调用方）；
                   admin 手工建户走 wp_insert_user 不触发 WC 钩子，无需豁免逻辑
            注意：若未来开启 checkout 注册，block checkout 无邀请码字段会被后端拦截，
                  需先补 Additional Checkout Fields 接线（见本文件 T-3.2 备注）
2026-10-21  完成 T-3.1  自定义订单状态「待向供应商付款」上线：新建 mu-plugin 体系
            wp-content/mu-plugins/velore-order-status.php（必载不可停用，业务逻辑与主题解耦），
            wc-pending-supplier 插入 processing 之后；计入 paid statuses 与 legacy reports
            （客户已付款语义）；订单列表琥珀金徽章；docker-compose 新增 mu-plugins 实时挂载。
            端到端验证：订单 #71 processing → pending-supplier → processing 往返成功，
            is_paid()=true，HPOS 模式确认启用
            踩坑⑫：HPOS 启用时订单列表 bulk actions 过滤器是
                   bulk_actions-woocommerce_page_wc-orders，legacy 才是
                   bulk_actions-edit-shop_order——只挂 legacy 那个在 HPOS 站点不生效；
                   mark_{slug} 动作本身两套列表都会自动处理，只需加 label
2026-10-20  完成 T-2.4  商品属性收尾：CSV 追加 Condition 属性（Excellent/Very Good/Pristine，
            从 Short description 提取），详情页 Additional Information 显示 Brand + Condition。
            验收标准重新定义：寄卖模式每件孤品库存 1，「选变体」不适用，参照 Fashionphile
            以属性展示替代（尺寸/材质已含在商品名与描述中）
2026-10-20  完成 T-2.3  类目树 + 品牌归档上线：Handbags(12)/Watches(9)/Jewelry(9) 归属正确；
            26 个品牌词项入 pa_brand，详情页显示 Brand 属性行；品牌归档基址改为 /designer/{brand}
            （/brand/chanel/ 已通）。import-products.php 修复：属性导入原来只对 variable 类型
            生效 → simple 商品全被跳过，改为全类型导入（is_variation 按类型设置）
            踩坑⑪：WooCommerce 9.4+ 原生 Brands（product_brand）默认也占 /brand/ rewrite base，
                   与自建 pa_brand 撞 slug，且原生规则优先匹配 → pa_brand 归档 404。
                   解决：woocommerce_taxonomy_args_pa_brand 过滤器把基址改成 designer
2026-10-20  完成 T-2.1/T-2.2  30 个种子商品上线：sample-data/products.csv（12 包/9 表/9 珠宝，
            寄卖逻辑每件库存 1，含 Brand 属性 + 2 个折价品 + 4 个 featured）；生成 6 张 Noir
            风格商品占位图（1840x1380，两次 sips 裁剪去水印）入主题 assets，30 商品轮换复用；
            import-products.php 修复 CLI fatal + CSV 路径；新增 scripts/seed-prepare.php
            （注册 pa_brand 全局属性 + 清理 18 个 WooCommerce 样品商品及其附件）
            踩坑⑩：CLI 跑导入时 media_handle_sideload 未定义——wp-load 不加载 wp-admin/includes，
                   需手动 require file.php / media.php / image.php
            备注：缩略图按 T-1.6 新尺寸生成 1024x768 4:3；总数 31 = 30 商品 + 1 个历史
            auto-draft（不可见，无影响）
2026-10-20  完成 T-1.7  移动端验收通过（390x844 实测）：全站零横向溢出、网格 2 列、抽屉/搜索/
            详情页正常。顺手修复 3 个发现项：①桌面端 hover 价格作用域错误（luxury-product-card
            落在整页 UL 上，hover 任意处全网格显价 → 选择器改 > li 定位到单品）
            ②汉堡/抽屉关闭/搜索图标点击热区外扩至 ≥44px（负 margin/伪元素保持视觉不变）
            ③newsletter 按钮加高至 ~44px。主题版本 bump 1.0.13
            踩坑⑨：woocommerce/product-template 的 className 渲染在包裹所有商品的 UL 上，
                   per-item 样式必须用 .luxury-product-card > li
            备注：headless 环境 PPCP client-token 500 = "Could not validate nonce"（无会话
            cookie），非站点问题，T-0.3 真实浏览器复测；Google Fonts woff2 404 为 headless
            网络环境噪音，本机直连 fonts.gstatic.com 返回 200
2026-10-20  完成 T-1.5  视觉规范定稿：Noir 暗底（#0A0A0A）+ Ivory/Gold 双强调 + Playfair Display
            （标题）/ Cormorant Garamond（斜体副文）/ Inter（正文）+ 零圆角按钮 + clamp 大留白
            （原「极简白底」方案随多轮设计迭代被 Noir 替代，规范散落 style.css v1.0.13 全文）
2026-10-20  完成 T-1.6  functions.php 主题能力补全：title-tag / custom-logo / html5、
            woocommerce 缩略图 600x450 4/3 裁切（与商品网格比例一致）、product_grid 默认 4 列；
            主题版本 bump 1.0.12
2026-10-20  完成 T-1.4.6  footer 修复：①版权行错乱（p 内嵌 site-title 块导致浏览器拆解）
            → 改纯文本 © 2026 VELORÉ · All Rights Reserved + flex space-between 对齐；
            ②订阅表单改 wp:html 静态 form（金色实心按钮 + 金线输入框，占位提交逻辑，
            后续接 newsletter 服务）；移动端表单自适应全宽
            踩坑⑧：不要在 wp:paragraph 里嵌套其他 block（site-title 等）——输出非法 HTML
2026-10-20  完成 T-1.7.1  移动端抽屉菜单可读性修复：链接强制 #FAFAFA（对比度 11.55，WCAG AAA）、
            字号 1.25rem、间距 30px、关闭按钮亮色放大 28px
            踩坑⑦：theme.json 给 core/navigation 元素 link 定义的 text color 会以更高优先级
                   覆盖 style.css 的普通规则——覆盖 theme.json 生成样式需用字面量+!important
2026-10-20  完成 T-1.4.5  header 顶部呼吸感 + 图标统一：padding-top 24px→clamp(28,3.5vw,48)、
            bottom 16px→clamp(16,2vw,28)；右侧三图标统一 32px 视觉高度（flex center）
            踩坑⑥：block 模板文件里 static block 渲染以 HTML 内联 style 为准，JSON 注释里的
                   spacing 仅供编辑器——改间距必须同步改 HTML 部分，否则前端无变化
2026-10-20  完成 T-1.4.4  header 垂直居中修复：中屏（~1000px）下图标组会 flex-wrap 掉到第二行。
            修复=压缩间距（toprow gap 32→16、左组 gap 40→24、nav gap 32→24）+
            990px 断点强制汉堡（CSS 覆盖 WP 原生 600px 断点）+ 1200px 以下 logo 缩到 1.25rem
            验证：862px 视口全部元素垂直中心 40.5px 对齐、单行
2026-10-20  完成 T-1.8  search.html 搜索结果页上线（query-title + 3 列网格卡片 + 无结果
            Cormorant 斜体提示 + 内联重搜表单 + 居中分页；商品已被确认可进主搜索）
2026-10-20  完成 T-1.4.3  搜索改为右上角放大镜图标 + 原生 <dialog> 全屏模态（Playfair 大输入框、
            ESC/点遮罩/× 关闭、autofocus）；菜单与 Logo 同行靠左（同组 flex）
            搜索图标按钮与模态用 wp:html 块直出，无需 JS 依赖
2026-10-20  完成 T-1.4.2  header 重构为经典三段式（Logo 左 / 搜索真居中 / 账户+购物车右），
            导航改 justifyContent:center 居中
            踩坑④：WooCommerce Block Hooks 会把 customer-account 块自动插到 core/navigation
                   之后——header 里未显式声明时它会错位渲染。解决：模板里显式放置
                   woocommerce/customer-account（显式存在后 hook 不再注入）
            踩坑⑤：theme.json 的全局 button element 样式（金边）会污染搜索按钮，
                   需在 style.css 显式 border:0 覆盖
2026-10-20  完成 T-1.4.1  header 修复：搜索框改透明底金色下划线极简样式；主导航重建为
            Shop/Handbags/Watches/Jewelry/Sell With Us（替换 WP 默认 page-list）
            数据库侧变更（不在 git 里）：ID=11 wp_navigation 重写；新建 product_cat
            handbags/watches/jewelry；新建 /sell-with-us/ 占位页；flush rewrite_rules
            踩坑③：浏览器 disk cache 会缓存页面和 CSS——改完样式看不到效果时先硬刷新，
                   主题版本号 bump 可强制客户端拉新 style.css（当前 1.0.1）
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

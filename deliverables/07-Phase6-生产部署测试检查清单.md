# Phase 6 — 生产部署测试检查清单

> 对应 `DEVELOPMENT_PLAN.md` Phase 6（T-6.1 ~ T-6.8）。
> 操作背景见 [05-PayPal-sandbox到prod切换清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/05-PayPal-sandbox到prod切换清单.md)（Phase 3 搭建步骤），
> 支付测试用例见 [06-Phase5-PayPal测试检查清单.md](file:///Users/se7en/Downloads/woocommerce/deliverables/06-Phase5-PayPal测试检查清单.md)。
>
> 本文档回答：**每一步部署完成后，怎么确认它真的好了。** 全部勾完才能进入上线公告。

---

## 0. 前置条件（全部满足才开始）

| # | 条件 | 依赖 | 状态 |
|---|---|---|---|
| 0.1 | Phase 5 测试全部通过（含阻断项为零） | B1–B4 | ☐ |
| 0.2 | 域名已购、所有权可控（DNS 可改） | 客户 | ☐ |
| 0.3 | 服务器已开（2C4G 起步，Docker + Compose 可用） | — | ☐ |
| 0.4 | 生产凭证齐备：PayPal live 凭证、GTM 容器 ID、Meta Pixel ID、SMTP 账号 | 客户 | ☐ |
| 0.5 | 本地 `git pull` 最新 main，工作区干净 | — | ☐ |
| 0.6 | 本地全量备份已做（DB dump + uploads + .env 离线副本） | — | ☐ |

---

## 1. T-6.1 — 域名 + DNS + Cloudflare（预估 0.5d）

| # | 检查项 | 步骤 / 命令 | 通过标准 | 结果 |
|---|---|---|---|---|
| 1.1 | DNS 解析 | `dig +short 你的域名` | 返回服务器 IP（橙云）或 Cloudflare 边缘 IP | ☐ |
| 1.2 | SSL 模式 | Cloudflare → SSL/TLS | **Full (Strict)**（Origin 有真证书，绝不选 Flexible——会产生重定向环） | ☐ |
| 1.3 | 动态路径绕过缓存 | Cloudflare → Caching → Cache Rules | `/wp-admin/*`、`/cart`、`/checkout`、`/my-account/*`、`/wc-api/*`、`/wp-json/*`、`*?add-to-cart=*` 全部 Bypass cache | ☐ |
| 1.4 | 破坏支付 JS 的功能已关 | Cloudflare → Speed → Optimization | **Rocket Loader = Off**（已知会破坏 PayPal 按钮渲染）；Email Obfuscation 建议 Off | ☐ |
| 1.5 | WAF 观察期 | Cloudflare → Security → Events | 部署后 24h 内回看：无误杀 POST /wp-json/wc-paypal/* 或结账请求 | ☐ |

---

## 2. T-6.2 — 服务器 + Docker 部署（预估 1d）

| # | 检查项 | 步骤 / 命令 | 通过标准 | 结果 |
|---|---|---|---|---|
| 2.1 | 容器健康 | `docker compose ps` | db + wordpress 均 Up (healthy) | ☐ |
| 2.2 | 生产环境变量注入 | `docker exec woocommerce-wordpress-1 env \| grep -E "PPCP\|GTM\|PIXEL"` | `PPCP_USE_SANDBOX=false`；GTM 容器 ID、Pixel ID 为生产值；**无 sandbox 密钥残留** | ☐ |
| 2.3 | mu-plugins 完整 | 容器内 `ls /var/www/html/wp-content/mu-plugins/` | velore-*.php 七件全 + vendor-libs/ 三库（Emogrifier 链）在位 | ☐ |
| 2.4 | 插件清单核对 | WP 后台 → 插件 | WooCommerce / PPCP / Polylang / CURCY / AST / GTM4WP / Yoast 全部激活且为最新版 | ☐ |
| 2.5 | 文件权限 | `docker exec woocommerce-wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/uploads` | 后台可上传图片，主题/插件编辑不报权限错 | ☐ |
| 2.6 | 生产密钥安全 | 服务器上 `grep -r "client_secret" --include="*.php" /var/www/html/wp-content/` | 零命中（密钥只在 .env，且 .env 权限 600 不入库） | ☐ |

---

## 3. T-6.3 — SSL + HTTPS 全链路（预估 0.5d）

| # | 检查项 | 步骤 / 命令 | 通过标准 | 结果 |
|---|---|---|---|---|
| 3.1 | 证书有效 | `curl -vI https://你的域名` | 证书链完整、未过期 | ☐ |
| 3.2 | WP 地址切换 | WP 后台 → 设置 → 常规 | WordPress 地址 & 站点地址均为 `https://你的域名` | ☐ |
| 3.3 | HTTP 强制跳转 | `curl -I http://你的域名` | 301 → https | ☐ |
| 3.4 | Mixed content 扫描 | 浏览器开发者工具 → Console 逐页看：首页 / 商品页 / 结账页 / my-account | 零「insecure content」警告 | ☐ |
| 3.5 | 多语言前缀正常 | 访问 `/zh/`、`/de/`、`/fr/`、`/es/`、`/pt/` | 各语言首页 200，无 https 混链 | ☐ |

---

## 4. T-6.4 — 数据库 + 媒体迁移（预估 1d）

### 4a. 迁移完整性

| # | 检查项 | 通过标准 | 结果 |
|---|---|---|---|
| 4.1 | 商品 30 个（12 包/9 表/9 珠宝） | 后台商品列表计数一致，图片全部显示 | ☐ |
| 4.2 | 六语言翻译关联 | Polylang 后台看翻译关联矩阵；前台各语言页面切换链接正确 | ☐ |
| 4.3 | 媒体文件 | 商品图/首页素材 200 加载（抽查 5 张，含 1840×1380 OG 图） | ☐ |
| 4.4 | 邀请码表 | 邀请码记录与本地一致；管理员生成/核销功能正常 | ☐ |
| 4.5 | 自定义订单状态 | `pending-supplier` 存在（后台订单状态下拉可见） | ☐ |
| 4.6 | AST 承运商表 | `woo_shippment_provider` 表承运商数量 >1000（否则同步跑 ast_insert_shipping_providers，见踩坑㉓） | ☐ |

### 4b. 测试数据清理（生产必做）

| # | 清理项 | 通过标准 | 结果 |
|---|---|---|---|
| 4.7 | 测试订单 | 订单 #71、#442 及所有 Test Buyer 订单已删除或标记测试；订单号序列不回退 | ☐ |
| 4.8 | 测试用户 | user#13 等测试账号删除；仅保留管理员 | ☐ |
| 4.9 | 占位内容 | 店铺地址「cs, cs」已改真实地址；演示 FedEx 追踪项已清 | ☐ |
| 4.10 | 搜索引擎可见 | 设置 → 阅读：**「建议搜索引擎不索引本站点」未勾选**（blog_public=1） | ☐ |

---

## 5. T-6.5 — 生产凭证切换（预估 0.5d）

| # | 检查项 | 步骤 | 通过标准 | 结果 |
|---|---|---|---|---|
| 5.1 | PPCP live 凭证 | `.env` 换 live client_id/secret → `docker compose up -d --force-recreate` → 重跑 configure_ppcp.php | 输出 `OK: environment=PRODUCTION` | ☐ |
| 5.2 | webhook 指向生产域名 | PayPal live 后台 → Webhooks | URL = `https://你的域名/wp-json/wc-paypal/v1/webhook`，状态 ACTIVE（先 GET 应返回 405） | ☐ |
| 5.3 | GTM 容器 | 页面源码搜 `GTM-` | 生产 GTM ID（非 GTM-PLACEHOLD）；GTM4WP 的 dataLayer 有 ecommerce 事件 | ☐ |
| 5.4 | Meta Pixel | `VELORE_META_PIXEL_ID` 生产值；商品页源码搜 `fbq('track'` | ViewContent 事件带 SKU+价格 | ☐ |
| 5.5 | CURCY 汇率 | CURCY 设置 → 汇率更新 | 汇率为当日值（非迁移时的旧值） | ☐ |

---

## 6. T-6.6 — 真实金额端到端测试（预估 0.5d）

> 详细用例（3DS / 汇率 / 风控 / 回调压测）已在 06 清单执行过，此处只做**生产环境冒烟回归**。

| # | 检查项 | 通过标准 | 结果 |
|---|---|---|---|
| 6.1 | 真实 $5–10 测试单 | 下单 → PayPal 付款 → 订单自动 processing → 品牌化邮件发出 | ☐ |
| 6.2 | 全额退款 | 订单 → refunded；PayPal 后台可查；退款邮件发出 | ☐ |
| 6.3 | 另一张卡复测 | 不同卡种/国家再走一遍 6.1 | ☐ |
| 6.4 | 证据归档 | 测试单 + 退款截图存档（交付给客户的 3 份文件之一） | ☐ |

---

## 7. T-6.7 — 客户签收文档（预估 0.5d）

| # | 文档 | 内容要求 | 状态 |
|---|---|---|---|
| 7.1 | 测试单记录 | 真实金额测试 + 退款截图（6.4 产出） | ☐ |
| 7.2 | 风险告知书签字版 | 通道审核不保证通过 / 新账户 21 天资金留存 / 税务合规客户负责——三条书面签收 | ☐ |
| 7.3 | 上线操作手册 | 客户怎么看订单、手动 capture、退款、下载物流凭证 | ☐ |

---

## 8. T-6.8 — 生产终检（预估 0.5d）

| # | 检查项 | 通过标准 | 结果 |
|---|---|---|---|
| 8.1 | SEO 全套（生产域名） | sitemap_index.xml 可访问且 URL 为生产域名；首页 canonical/og:image 正确；hreflang 六语言指向生产 URL；robots.txt 含 Yoast block | ☐ |
| 8.2 | 邮件真实送达 | 后台下测试单 → 客户邮箱收到（Noir 品牌样式完整，非裸样式） | ☐ |
| 8.3 | 注册闭环 | 生产站注册一个账号：设密码 + 选填邀请码 + 确认密码全链路可用 | ☐ |
| 8.4 | 多币种冒烟 | 切 EUR/SGD 展示价正常；**结账金额恒 USD**（Store API 复核） | ☐ |
| 8.5 | OG 图为正式品牌图 | 分享首页到社交调试工具（如 metatags.io）：卡片图正确、无水印、比例合规 | ☐ |
| 8.6 | 备份就位 | 每日自动备份（DB + uploads）已跑通一次且可恢复验证 | ☐ |
| 8.7 | 安全基线 | wp-admin 非默认暴露路径或有限速；管理员强密码；自动更新关闭（手动控制） | ☐ |

---

## 9. 上线后 24–48 小时监控（上线公告后执行）

| # | 监控项 | 看哪里 | 正常表现 |
|---|---|---|---|
| 9.1 | PayPal webhook | `docker logs --since 24h woocommerce-wordpress-1 2>&1 \| grep -i webhook` | 全部 200，无重试风暴 |
| 9.2 | GA4 / Pixel 数据 | GA4 实时报告 / Meta Events Manager | PageView/ViewContent/AddToCart 流入 |
| 9.3 | PHP 错误 | `docker logs --since 24h woocommerce-wordpress-1 2>&1 \| grep -iE "fatal\|error" \| wc -l` | 零 Fatal |
| 9.4 | Cloudflare 安全事件 | Security → Events | 无 WAF 误杀结账/API |
| 9.5 | 邮件投递 | SMTP 服务商后台 | 无大量退信/进垃圾箱 |

---

## 10. 退出标准

**全部勾完** → Phase 6 关闭，项目进入运维阶段。

**出现以下任一 → 回滚**：
- 2.2 生产密钥泄漏迹象（立即吊销凭证 + 排查 git）
- 6.1/6.2 生产支付链路失败（回滚：`.env` 改回 sandbox → `docker compose up -d --force-recreate`，DNS 不动）
- 4.2 多语言关联断裂（站点可用性不受影响，可修复后重新验收）

> 回滚不影响已迁数据；回滚后排查根因，修复后从失败项继续，**不需要整清单重跑**。

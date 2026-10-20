# PayPal PPCP sandbox → production 切换清单

> 你已经跑通了本地 Docker sandbox：能下单、后台能看到订单。
> 这份清单是把"localhost 里的测试跑通"变成"真实买家在你公网 HTTPS 服务器上用真实卡付款"要做的**所有事**，按执行顺序排。
>
> 核心原则：**先在本地 ngrok 隧道里测真实回调，再切服务器，最后切真实商户号**。
> 不要试图跳过中间环节直接一步到 prod——每跳一步都有坑要填。

---

## Phase 0：立即做（现在就能做，不依赖客户）

| # | 动作 | 命令 / 操作 | 状态 |
|---|---|---|---|
| 0.1 | 确认 `configure_ppcp.php` 已经不再硬编码凭证 | ✅ 已改：现在从 `.env` 读取 | ✅ 完成 |
| 0.2 | `.gitignore` 已加 `.env`，确保不再把密钥提交 | ✅ 已加 | ✅ 完成 |
| 0.3 | **清理 git 历史里残留的密钥** | `git rm --cached configure_ppcp.php` + commit + push，密钥已提交过的话联系 GitHub 做一次 secret scan | ⚠️ 重要：当前 `configure_ppcp.php` 之前硬编码过真实 sandbox client_secret 并提交过 git，建议做一次 secret scan |
| 0.4 | sandbox 环境保持正常运行 | `docker compose up -d` | 🔄 持续 |

---

## Phase 1：本地跑通真实 webhook 回调（关键！sandbox 测不到）

sandbox 里 PayPal 回调是**模拟触发**的，不会经过真实公网链路。prod 里 PayPal 服务器必须能打到你的 URL。用 ngrok 能在**服务器还没上线之前**就测通这步。

| # | 动作 | 操作 | 验收标准 |
|---|---|---|---|
| 1.1 | 装 ngrok | `brew install ngrok && ngrok http 8080` | 拿到类似 `https://xxxxxx.ngrok-free.app` 的 HTTPS URL |
| 1.2 | 填 PPCP 插件的 webhook URL | WP 后台 → WooCommerce → 设置 → Payments → PayPal → Webhook → 填 `https://xxxxxx.ngrok-free.app/wp-json/wc-paypal/v1/webhook` | PayPal 后台能看到这条 webhook，状态 ACTIVE |
| 1.3 | PayPal 后台手动触发一条测试事件 | PayPal Developer Dashboard → Webhooks → Simulate Event → 选 `PAYMENT.CAPTURE.COMPLETED` | Docker 容器 `docker logs -f woocommerce-wordpress-1` 里能看到 webhook 被打到、`HTTP 200 OK` 返回 |
| 1.4 | 跑完整的 sandbox 测试单 | 用 PayPal sandbox buyer 账号在你本地站点付一笔钱 | 订单自动从 `pending` → `on-hold`（PayPal 要求手动确认 capture）→ `processing`。这三步**必须全自动流转**，不能停在某个状态不动 |
| 1.5 | 测退款链路 | WP 后台订单页 → 全额退款 → 在本地跑 | PayPal sandbox 里买家收到退款，订单状态自动从 `processing` → `refunded` |

> **事务闭环检查**（经验 100006067）：webhook 回调里执行的代码必须是**原子化**的：验签 → 验金额 → 更新订单状态 → 扣库存 → 触发邮件，要么一起成，要么一起回滚。你现在用的是 PPCP 插件，这条链路已经帮你封装好了，但退款是反向链路，要单独测。

---

## Phase 2：硬骨头测试（sandbox 根本测不到的真实场景）

| # | 测试项 | 为什么 sandbox 测不到 | 怎么测 | 通过标准 |
|---|---|---|---|---|
| 2.1 | **3DS / SCA 强认证（欧洲卡强制）** | sandbox 测试卡跳过 3DS | 换 PPCP 的 **Advanced Card Processing** 模式（ACDC，信用卡直收），用**真实欧洲 Visa** 做测试卡，走 ACDC 路径 | 浏览器弹出真实 3DS 挑战页 → 完成挑战 → 付款成功。**不能跳过挑战直接付款** |
| 2.2 | **支付失败兜底** | sandbox 里支付几乎不会失败 | 故意用过期卡、余额不足的卡、测试卡号 `4000 0000 0000 0002`（PayPal 模拟卡被拒） | 买家看到清晰的"支付失败，请重试"提示；订单不会卡在 `pending` 不消失；支持重试 |
| 2.3 | **多币种真实汇率** | sandbox 里汇率是 mock 的 | 买家从欧洲用 EUR 买 USD 商品 | PayPal 扣买家 EUR、你账户收 USD；确认汇率损耗（PayPal 通常 2.5–4%）是否在你预期内 |
| 2.4 | **风控触发 & 资金留存** | sandbox 没有真实风控 | 用一笔高客单价（> $500）的订单测试 | 可能遇到 PayPal 把资金留存 21 天。**必须把这件事书面告知客户**，让他预留周转资金 |

---

## Phase 3：生产环境基础搭建

| # | 动作 | 操作 | 验收标准 |
|---|---|---|---|
| 3.1 | 买域名 | VELORÉ 的域名 | DNS 解析到你的服务器 IP |
| 3.2 | 服务器 + 部署 | DigitalOcean / Vultr / AWS，用 Cloudflare 做 CDN + 免费 SSL | `https://你的域名/wp-admin` 能打开，SSL 证书有效 |
| 3.3 | WordPress 基础配置 | WP 后台 → 设置 → 常规 → WordPress 地址 & 站点地址都改成 `https://你的域名` | 全站 HTTPS，没有 mixed content 警告 |
| 3.4 | WP 数据库迁移 | 把本地 Docker MySQL 的数据导出 → 导入 prod 服务器 MySQL | 所有商品、用户、订单数据正确迁移 |
| 3.5 | PPCP 插件 webhook URL 更新 | WP 后台改回 prod URL | `https://你的域名/wp-json/wc-paypal/v1/webhook` 激活 |
| 3.6 | HTTPS 全链路检查 | 浏览器开发者工具 → Security | 所有资源（图片、JS、API 回调）都是 HTTPS |

---

## Phase 4：切换真实商户号（客户那边的前置条件）

**这一步必须等客户完成，你控制不了进度**——先把 Phase 0–3 全跑完再等这一步。

| # | 客户必须提供 | 为什么需要 | 你这边的动作 |
|---|---|---|---|
| 4.1 | 公司注册主体证明 | PayPal 商户号申请前置 | 归档客户给的材料截图 |
| 4.2 | PayPal 真实商户号 + client_id + client_secret | sandbox 和 prod 凭证**完全独立**，不能混用 | 填入 `.env`：替换 `PPCP_CLIENT_ID` / `PPCP_CLIENT_SECRET`，改 `PPCP_USE_SANDBOX=false` |
| 4.3 | PayPal 承保审核通过通知 | 奢侈品 + 买手身份是高风险类目，PayPal 会额外审查 | 客户要提供：品牌授权书、进货发票、品牌官网佐证 |
| 4.4 | 客户确认 PayPal 账户已激活 | 开通后 72 小时内首次测试 | 让客户自己在 PayPal 后台确认状态 |

---

## Phase 5：正式切换 & 端到端验证

| # | 动作 | 操作 | 验收标准 |
|---|---|---|---|
| 5.1 | 重建容器加载新 `.env` | `docker compose up -d --force-recreate` | `docker exec $CONTAINER env \| grep PPCP_USE_SANDBOX` 返回 `false` |
| 5.2 | 重新运行 configure_ppcp.php | `docker exec $CONTAINER php ...configure_ppcp.php` | 输出 `OK: environment=PRODUCTION` |
| 5.3 | **真实金额测试单**（你来付） | 用你自己的 PayPal 账号/信用卡付一笔 $5–$10 的测试单 | 全链路走通：下单 → 付款成功 → WP 后台订单状态自动更新 → 触发发货邮件 → PayPal 后台能查到这笔真实资金流转 |
| 5.4 | **真实金额退款测试** | 把刚才那笔测试单全额退款 | WP 订单状态自动 → `refunded`；PayPal 后台买家收到退款 |
| 5.5 | **另一张真实卡再测一次**（避免 3DS/ACH/Diners 等冷门分支有 bug） | 用不同卡种、不同国家 | 全链路走通 |
| 5.6 | 通知客户可以收真实订单 | 把测试单和退款单截图发客户归档 | 客户确认理解风控和资金留存风险，书面签收 |

---

## 每一步卡住了怎么办（经验教训）

| 卡住的地方 | 最可能原因 | 排查路径 |
|---|---|---|
| **PayPal 后台看不到 webhook 事件** | URL 不通 / HTTPS 证书无效 | 浏览器直接打开 `https://你的域名/wp-json/wc-paypal/v1/webhook`，必须返回 405 Method Not Allowed（GET 不支持，POST 才对），说明 URL 可达 |
| **买家付款成功但订单停在 pending** | PPCP 插件没正确处理 capture | WP 后台订单页手动走一遍 Capture → 看状态能不能流转。能流转说明自动回调链路没走通；不能流转说明插件版本有 bug |
| **3DS 挑战后订单失败** | ACDC 模式下 PayPal 要求 capture 必须在 3DS 后立即执行 | 用 PPCP 插件最新版，旧版有这个 bug |
| **PayPal 承保审核被拒** | 品牌授权材料不全 / 主体不明 | 客户必须：补充材料 + 说明业务模式 + 准备第二套备选通道（Stripe / Airwallex） |
| **Sandbox 凭证泄露** | git 历史里有明文密钥 | 跑 GitHub secret scan → revoke 老凭证 → 换新 → `.gitignore` 加文件 |

---

## 交付前必须给客户的 3 份文件

1. **测试单记录**：真实金额测试 + 退款的 PayPal 后台截图，证明链路已跑通
2. **风险告知书签字版**：通道审核不保证通过、新账户 21 天资金留存、客户负责税务合规——这三条必须让客户书面签收
3. **上线操作手册**：客户后台怎么看订单、怎么手动 capture、怎么退款、怎么下载物流凭证

---

## 时间估算（从现在到上线）

| 阶段 | 理想 | 保守 | 瓶颈 |
|---|---|---|---|
| Phase 0 代码清理 | 0.5 天 | 0.5 天 | — |
| Phase 1 本地 webhook 闭环 | 1 天 | 1 天 | — |
| Phase 2 硬骨头测试 | 2 天 | 3 天 | 3DS 测试卡难搞 |
| Phase 3 生产环境搭建 | 2 天 | 3 天 | 域名 DNS 生效 |
| Phase 4 通道审核（客户侧） | **1–3 周** | **2–4 周** | ⚠️ 客户和 PayPal 的节奏 |
| Phase 5 切换 & 验证 | 1 天 | 2 天 | — |
| **合计（不含客户侧）** | **7 天** | **10 天** | |
| **合计（含客户侧）** | **3–5 周** | **4–7 周** | **通道审核是硬瓶颈** |

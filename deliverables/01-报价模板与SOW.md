# 奢侈品独立站 · 报价模板与交付范围说明（SOW）

> 本文件分三部分：
> **A** 内部计价工作表（不发给客户）· **B** 客户报价单模板（英文，可直接发送）· **C** SOW 交付范围说明书
> 所有 `[ ]` 为待填占位符，替换后即可使用。

---

## A. 内部计价工作表（不发给客户）

### A1. 计价公式

```
报价 = ( Σ 工作项人天 × 日费率 ) × ( 1 + 风险缓冲 ) + 第三方硬成本 + 运维年费
```

- **风险缓冲固定取 1.20 ~ 1.30**。这不是虚报，是对接第三方（支付通道审核、承运商 API、客户素材延迟）的行业惯例。
- **日费率**按你的目标客户市场定 `[ ]` USD/day。对海外客户一律报 USD 或 EUR。

### A2. 工作项分解（WBS）

| 阶段 | 工作项 | 人天 | 备注 |
| --- | --- | --- | --- |
| 需求与方案 | 需求确认、目标市场/合规调研、支付与物流选型 | `[ ]` | 高客单价品类要多花时间在支付风险上 |
| 设计 | 品牌视觉规范、首页/列表/详情/结账原型 | `[ ]` | |
| 主题开发 | 主题定制、响应式、多语言（含 RTL 如涉及中东） | `[ ]` | |
| 商品与内容 | 商品导入、分类属性、多币种展示 | `[ ]` | |
| **支付对接** | 通道开户协助、插件配置、sandbox 联调、支付失败态兜底 | `[ ]` | **最容易低估** |
| **物流对接** | 承运商 API、轨迹页、订单状态回写、运费与关税规则 | `[ ]` | **最容易低估** |
| 合规 | 隐私政策、Cookie、VAT/关税提示、退换货条款页 | `[ ]` | |
| 测试 | 端到端：下单 → 支付 → 发货 → 轨迹 → 签收 | `[ ]` | |
| 上线 | 部署、域名、SSL、CDN、监控、灰度发布 | `[ ]` | |
| 文档与培训 | 后台操作手册、客户培训 | `[ ]` | |
| **合计** | | `[ ]` 人天 | × 缓冲 1.25 |

### A3. 第三方硬成本（实报实销，向客户透明列出）

- 域名 / 云服务器 / CDN
- 付费主题或插件授权（WooCommerce 生态部分插件为年费订阅）
- **支付通道手续费**：通常 2.9% + $0.30 起；高风险类目通道费率更高，且有提现费与汇兑费
- 短信 / 邮件服务
- 物流面单与保价费用

> 建议：第三方成本按实报实销，或加收 10%–15% 代管费。**绝不要把这部分打包进你的固定报价**，否则涨价全由你承担。

### A4. 付款节奏

| 节点 | 比例 |
| --- | --- |
| 合同签订（定金） | 30% |
| 核心里程碑（支付/物流联调通过） | 40% |
| 验收上线前 | 30% |
| 第三方硬成本 | 全额预付 |

### A5. 报价参考锚点（仅作锚点，请按自身成本重算）

| 交付档位 | 区间（USD） |
| --- | --- |
| 套用现成模板开店，无第三方对接 | 3,000 – 8,000 |
| 定制主题 + 支付对接 + 物流 API + 多币种 | 15,000 – 45,000 |
| 含 ERP/CRM 对接、多语言多站 | 50,000+ |
| 年度运维 | 建设费的 15%–25% / 年 |

---

## B. 客户报价单模板（English — send this to the client）

> 英文是海外客户的默认商务语言。中文注释仅供你自己理解结构。

**QUOTATION — Luxury E-commerce Store (WooCommerce)**

| | |
| --- | --- |
| Prepared for | `[Client legal name]` |
| Prepared by | `[Your name / company]` |
| Date | `[ ]` |
| Quotation valid until | `[ ]` (建议 14–30 天) |

| # | Item | Scope summary | Price (USD) |
| --- | --- | --- | --- |
| 1 | One-time build | Storefront design & development, product setup, payment integration, shipping/tracking integration, compliance pages, testing, go-live | `[ ]` |
| 2 | Third-party costs | Domain, hosting/CDN, plugin licences, transactional fees (billed at cost, invoiced separately) | `[ ]` est. |
| 3 | Annual maintenance | Security updates, backups, monitoring, incident response, minor changes | `[ ]` |
| | **Total (first year)** | | `[ ]` |

**Payment schedule**
- 30% — on contract signing (non-refundable deposit)
- 40% — on successful payment & shipping integration sign-off
- 30% — before production go-live

**Third-party costs** are billed at cost and invoiced separately from this quotation.

**Exclusions** (not included unless quoted separately)
- Product photography, video, copywriting, and translation
- Marketing, advertising, and paid traffic management
- Payment gateway underwriting fees and security deposits
- Ongoing content updates beyond the maintenance scope
- Mobile app development

**Change requests** outside the agreed scope are billed at `[ ]` USD/day.

---

## C. SOW 交付范围说明书

### C1. In scope（我交付什么）

1. **需求与方案**：目标市场确认、支付/物流选型方案文档
2. **设计**：首页、分类页、商品详情页、购物车、结账、订单/物流追踪页的高保真设计
3. **开发**：WooCommerce 主题定制与响应式实现；多语言、多币种展示
4. **商品**：商品导入、分类、属性、变体、库存结构搭建
5. **支付**：支付通道开户**协助**、插件与沙箱配置、端到端支付联调、支付失败态与重试兜底
6. **物流**：运费规则、承运商对接、物流轨迹页、订单状态自动回写、发货/签收邮件通知
7. **合规**：隐私政策、Cookie 同意、退换货条款、关税/VAT 提示页
8. **测试与上线**：端到端测试、部署、SSL、CDN、监控、上线
9. **交付物**：后台操作手册 + 一次客户培训

### C2. Out of scope（明确不包含）

> 这一节是**保护你自己**的关键。不写清楚，客户会默认全都包含。

- 商品拍摄、修图、文案撰写、多语言翻译
- 品牌 VI 设计（Logo、字体、色彩体系原创设计）
- 广告投放、SEO 内容运营、社媒运营
- 支付通道的**开户资质与保证金**（由客户主体承担）
- 客服系统、ERP、CRM 的采购费用
- 上线后的内容更新与新品上架（属运维或单独报价）
- 移动 App

### C3. Client prerequisites（客户必须提供 —— 报价前置条件）

> **未确认这些之前，不要给出确定价格。** 它们会显著改变工作量，甚至决定项目能否成立。

| # | 前提 | 为什么必须先确认 |
| --- | --- | --- |
| 1 | **收款主体**（公司注册地 + 营业执照/注册证明） | 决定能否申请支付通道。PayPal / Stripe 商户号对主体与注册地有要求 |
| 2 | **奢侈品品牌授权材料** | 奢侈品属高风险类目，通道承保审核必需；缺失则可能被拒 |
| 3 | 目标市场国家清单 | 决定支付方式、税率（VAT/Sales Tax）、关税模式（DDP/DDU）、语言 |
| 4 | 商品清单与图片素材 | 决定商品导入工作量 |
| 5 | 发货方式（自发货 / 3PL / 工厂代发） | 决定物流对接复杂度 |
| 6 | 承运商账号与 API 凭证 | 决定能否做轨迹自动回传 |
| 7 | 是否需要对接 ERP / CRM / 库存系统 | 决定集成工作量级 |
| 8 | 现有域名、主机、品牌素材 | 决定上线与迁移工作量 |

### C4. Change control（变更流程）

1. 所有变更以书面形式（邮件或变更单）提出
2. 评估工作量与影响，出具**变更报价与工期影响说明**
3. 客户书面确认后，方可纳入执行
4. 未确认的变更不进入开发排期，且不计入验收范围

### C5. Acceptance criteria（验收标准）

- 端到端流程可复现通过：下单 → 支付成功 → 订单生成 → 发货出库 → 轨迹回传 → 签收通知
- 支付失败与异常路径有明确提示与重试机制，无静默失败
- 支持的目标市场货币与语言可正确展示
- 移动端（iOS Safari / Android Chrome）核心流程无阻断
- 通过约定的浏览器与设备清单测试
- 客户在 `[ ]` 个工作日内完成验收；逾期视为默认通过

### C6. Risks（风险登记 —— 提前告知客户，避免背锅）

| 风险 | 影响 | 应对 |
| --- | --- | --- |
| 支付通道审核被拒 | 项目阻塞，无法收款 | 提前准备品牌授权材料；准备 2 家备选通道 |
| 奢侈品账号资金冻结 | 新账号 + 高客单价易触发风控，资金可能被临时冻结 | 告知客户预留周转资金；分散通道；尽早建立交易记录 |
| 客户素材延迟 | 排期顺延 | 素材未齐前不启动相关环节；排期以素材就绪日为基准 |
| 多市场合规要求变化 | 增加工作量 | 变更流程处理；建议客户聘请当地税务顾问 |
| 物流轨迹 API 不稳定 | 轨迹页显示异常 | 降级为手动填写 + 聚合查询，不阻断下单 |

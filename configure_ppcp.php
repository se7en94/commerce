<?php
/**
 * WooCommerce PayPal Payments (PPCP) 配置脚本
 * 用法：
 *   # 在 WordPress 容器里执行（推荐，能直接操作 WP 数据库）：
 *   docker exec -it woocommerce-wordpress-1 php /var/www/html/wp-content/plugins/woocommerce/configure_ppcp.php
 *   # 或先 shell 进去：
 *   docker exec -it woocommerce-wordpress-1 bash
 *   cd /var/www/html/wp-content/plugins/woocommerce && php configure_ppcp.php
 *
 * 凭证一律从环境变量读取（见 .env.example），禁止硬编码。
 * 生产部署前：在 .env 填入真实凭证 → docker compose up -d --force-recreate → 运行本脚本。
 */

// 加载 WordPress 引导，使 update_option() 等函数可用
// 脚本位于 wp-content/plugins/woocommerce/ → 向上 3 级到 WordPress 根目录
$wp_load = __DIR__ . '/../../../wp-load.php';
if (!file_exists($wp_load)) {
    echo "ERROR: 找不到 wp-load.php（尝试路径：$wp_load）\n";
    echo "请在 WordPress 容器内运行本脚本。\n";
    exit(1);
}
require_once $wp_load;

function env($key, $default = null) {
    $val = getenv($key);
    if ($val === false || $val === '') return $default;
    return $val;
}

$client_id     = env('PPCP_CLIENT_ID');
$client_secret = env('PPCP_CLIENT_SECRET');
$use_sandbox   = env('PPCP_USE_SANDBOX', 'true') === 'true';
$merchant_email = env('PPCP_MERCHANT_EMAIL', '');
$merchant_id   = env('PPCP_MERCHANT_ID', '');
$merchant_country = env('PPCP_MERCHANT_COUNTRY', 'US');

if (!$client_id || !$client_secret) {
    echo "ERROR: 缺少 PPCP_CLIENT_ID 或 PPCP_CLIENT_SECRET 环境变量\n";
    echo "请参考 .env.example 创建 .env 文件并填入值\n";
    exit(1);
}

$common = [
    "use_sandbox"        => $use_sandbox,
    "use_manual_connection" => true,
    "sandbox_merchant"   => $use_sandbox,
    "merchant_connected" => true,
    "client_id"          => $client_id,
    "client_secret"      => $client_secret,
    "merchant_email"     => $merchant_email ?: ($use_sandbox ? "sb-seller@example.com" : ""),
    "merchant_id"        => $merchant_id ?: ($use_sandbox ? "TEST_SANDBOX_MERCHANT" : ""),
    "merchant_country"   => $merchant_country,
    "seller_type"        => "unknown",
    "wc_installation_path" => "DIRECT",
    "is_send_only_country" => false,
];

update_option("woocommerce-ppcp-data-common", $common);

$gateway_settings = get_option("woocommerce_ppcp-gateway_settings", []);
$gateway_settings["enabled"]     = "yes";
$gateway_settings["title"]       = "PayPal";
$gateway_settings["description"] = "Pay via PayPal.";
update_option("woocommerce_ppcp-gateway_settings", $gateway_settings);

$envLabel = $use_sandbox ? "SANDBOX" : "PRODUCTION";
echo "OK: environment={$envLabel} merchant_email={$merchant_email}\n";
echo "    client_id=" . substr($client_id, 0, 20) . "…" . " (len=" . strlen($client_id) . ")\n";

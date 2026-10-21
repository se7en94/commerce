<?php
if (!defined('ABSPATH')) {
	require '/var/www/html/wp-load.php';
}

// CLI context does not load wp-admin includes — required for media sideload
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$csv_file = ABSPATH . 'wp-content/plugins/woocommerce/sample-data/products.csv';

if (!file_exists($csv_file)) {
	echo "CSV not found\n";
	exit(1);
}

echo "=== WooCommerce Sample Products Import ===\n\n";

$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle);
$rows = array();
while (($row = fgetcsv($handle)) !== false) {
	if (count($row) !== count($headers)) continue;
	$rows[] = array_combine($headers, $row);
}
fclose($handle);

echo "Total rows to process: " . count($rows) . "\n";

$created       = 0;
$skipped       = 0;
$parent_map    = array();
$variation_rows = array();

foreach ($rows as $idx => $row) {
	$type = trim($row['Type']);
	$name = trim($row['Name']);
	$sku  = trim($row['SKU'] ?? '');

	if (empty($name)) {
		$skipped++;
		continue;
	}

	$existing_id = wc_get_product_id_by_sku($sku);
	if ($existing_id) {
		echo "[$idx] SKU '$sku' exists (ID=$existing_id), skipping\n";
		$parent_map[$sku] = $existing_id;
		$skipped++;
		continue;
	}

	if (stripos($type, 'variation') !== false) {
		$variation_rows[] = $row;
		continue;
	}

	$product_type = 'simple';
	if (stripos($type, 'variable') !== false)       $product_type = 'variable';
	elseif (stripos($type, 'grouped') !== false)   $product_type = 'grouped';
	elseif (stripos($type, 'external') !== false) $product_type = 'external';
	elseif (stripos($type, 'downloadable') !== false && stripos($type, 'virtual') !== false) $product_type = 'simple';

	$product = new WC_Product_Simple();
	if ($product_type === 'variable')       $product = new WC_Product_Variable();
	elseif ($product_type === 'grouped')   $product = new WC_Product_Grouped();
	elseif ($product_type === 'external')   $product = new WC_Product_External();

	$product->set_name($name);
	$product->set_sku($sku);
	$product->set_status(!empty($row['Published']) ? 'publish' : 'draft');
	$product->set_featured(!empty($row['Is featured?']));
	$product->set_catalog_visibility($row['Visibility in catalog'] ?? 'visible');
	$product->set_short_description($row['Short description'] ?? '');
	$product->set_description($row['Description'] ?? '');
	$product->set_regular_price($row['Regular price'] ?? '');
	$product->set_sale_price($row['Sale price'] ?? '');
	$product->set_tax_status($row['Tax status'] ?? 'taxable');
	$product->set_tax_class($row['Tax class'] ?? '');
	$product->set_weight($row['Weight (lbs)'] ?? '');
	$product->set_length($row['Length (in)'] ?? '');
	$product->set_width($row['Width (in)'] ?? '');
	$product->set_height($row['Height (in)'] ?? '');
	$product->set_reviews_allowed(!empty($row['Allow customer reviews?']));

	if (!empty($row['In stock?'])) {
		$product->set_stock_status('instock');
		if (isset($row['Stock']) && $row['Stock'] !== '') {
			$product->set_manage_stock(true);
			$product->set_stock_quantity((float) $row['Stock']);
		}
	} else {
		$product->set_stock_status('outofstock');
	}

	if ($product_type === 'external') {
		$product->set_product_url($row['External URL'] ?? '');
		$product->set_button_text($row['Button text'] ?? '');
	}

	if ($product_type === 'grouped' && !empty($row['Grouped products'])) {
		$child_skus = array_map('trim', explode(',', $row['Grouped products']));
		$child_ids  = array();
		foreach ($child_skus as $child_sku) {
			if (isset($parent_map[$child_sku])) {
				$child_ids[] = $parent_map[$child_sku];
			}
		}
		if (!empty($child_ids)) {
			$product->set_children($child_ids);
		}
	}

	$categories = array();
	if (!empty($row['Categories'])) {
		$cats = array_map('trim', explode(',', $row['Categories']));
		foreach ($cats as $cat) {
			$parts = explode(' > ', $cat);
			$slug  = sanitize_title(end($parts));
			$term  = term_exists($slug, 'product_cat');
			if (!$term) {
				$term = wp_insert_term(end($parts), 'product_cat');
			}
			if (!is_wp_error($term)) {
				$categories[] = (int) (is_array($term) ? $term['term_id'] : $term);
			}
		}
	}
	if (!empty($categories)) {
		$product->set_category_ids($categories);
	}

	$images = array();
	if (!empty($row['Images'])) {
		$image_urls = array_map('trim', explode(',', $row['Images']));
		foreach ($image_urls as $u) {
			if (!empty($u)) $images[] = $u;
		}
	}
	if (!empty($images)) {
		$product->set_image_id(woo_import_remote_image($images[0], $name));
		$gallery = array();
		for ($i = 1; $i < count($images); $i++) {
			$gallery[] = woo_import_remote_image($images[$i], $name . '-' . $i);
		}
		$product->set_gallery_image_ids(array_filter($gallery));
	}

	$new_id = $product->save();
	$parent_map[$sku] = $new_id;

	if ($new_id) {
		echo "[$idx] Created ($product_type): $name (ID=$new_id, SKU=$sku)\n";
		$created++;

		if (!empty($row['Attribute 1 name'])) {
			woo_create_attributes($new_id, $row, $product_type === 'variable');
		}
	} else {
		echo "[$idx] FAILED: $name\n";
	}
}

echo "\n--- Processing variations ---\n";
$var_count = 0;
foreach ($variation_rows as $row) {
	$sku     = trim($row['SKU']);
	$name    = trim($row['Name']);
	$parent  = trim($row['Parent']);
	$parent_id = $parent_map[$parent] ?? null;

	if (!$parent_id) {
		echo "Variation '$name' - parent '$parent' not found, skipping\n";
		continue;
	}

	$variation = new WC_Product_Variation();
	$variation->set_parent_id($parent_id);
	$variation->set_name($name);
	$variation->set_sku($sku);
	$variation->set_status(!empty($row['Published']) ? 'publish' : 'draft');
	$variation->set_regular_price($row['Regular price'] ?? '');
	$variation->set_sale_price($row['Sale price'] ?? '');
	$variation->set_weight($row['Weight (lbs)'] ?? '');
	$variation->set_length($row['Length (in)'] ?? '');
	$variation->set_width($row['Width (in)'] ?? '');
	$variation->set_height($row['Height (in)'] ?? '');

	$attrs = array();
	for ($i = 1; $i <= 2; $i++) {
		$attr_name = trim($row["Attribute $i name"] ?? '');
		$attr_val  = trim($row["Attribute $i value(s)"] ?? '');
		if (!empty($attr_name) && !empty($attr_val)) {
			$attrs[wc_attribute_taxonomy_name($attr_name)] = $attr_val;
		}
	}
	if (!empty($attrs)) {
		$variation->set_attributes($attrs);
	}

	if (!empty($row['Images'])) {
		$urls = array_map('trim', explode(',', $row['Images']));
		$variation->set_image_id(woo_import_remote_image($urls[0], $name));
	}

	$v_id = $variation->save();
	if ($v_id) {
		echo "  Variation: $name (ID=$v_id)\n";
		$var_count++;
	}
}

echo "\n=== Import Summary ===\n";
echo "Products created: $created\n";
echo "Variations created: $var_count\n";
echo "Skipped: $skipped\n";

$count = wp_count_posts('product');
echo "\nTotal products: " . array_sum((array) $count) . "\n";

function woo_import_remote_image($url, $name)
{
	if (empty($url)) return 0;

	$existing = attachment_url_to_postid($url);
	if ($existing) return $existing;

	$tmp = download_url($url, 30);
	if (is_wp_error($tmp)) return 0;

	$file_array = array(
		'name'     => sanitize_file_name(basename(parse_url($url, PHP_URL_PATH))),
		'tmp_name' => $tmp,
	);

	$id = media_handle_sideload($file_array, 0, $name);
	if (is_wp_error($id)) {
		@unlink($tmp);
		return 0;
	}
	return $id;
}

function woo_create_attributes($product_id, $row, $is_variable = false)
{
	$attrs = array();
	for ($i = 1; $i <= 2; $i++) {
		$attr_name  = trim($row["Attribute $i name"] ?? '');
		$attr_vals  = trim($row["Attribute $i value(s)"] ?? '');
		$attr_vis   = !empty($row["Attribute $i visible"]);
		$attr_glb   = !empty($row["Attribute $i global"]);
		if (empty($attr_name) || empty($attr_vals)) continue;

		$options  = array_map('trim', explode(',', $attr_vals));
		$taxonomy = wc_attribute_taxonomy_name($attr_name);

		if ($attr_glb) {
			foreach ($options as $opt) {
				if (!term_exists($opt, $taxonomy)) {
					@wp_insert_term($opt, $taxonomy);
				}
			}
			@wp_set_object_terms($product_id, $options, $taxonomy);
		}

		$attrs[] = array(
			'name'         => $attr_glb ? $taxonomy : $attr_name,
			'value'        => implode(' | ', $options),
			'is_visible'   => $attr_vis ? 1 : 0,
			'is_variation' => $is_variable ? 1 : 0,
			'is_taxonomy'  => $attr_glb ? 1 : 0,
		);
	}
	if (!empty($attrs)) {
		update_post_meta($product_id, '_product_attributes', $attrs);
	}
}

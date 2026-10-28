<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A large, realistic tech catalogue to browse and load-test the shop: phones, tablets, laptops,
 * screens, TVs, audio and sound systems, cameras, gaming, watches, networking, printers, storage,
 * accessories and security — about 3 000 products with a variant and a published Technilium offer
 * each, French and English names and descriptions, stock, warranties, some reductions.
 *
 *   php artisan db:seed --class="App\Modules\Catalog\Infrastructure\Seeders\TechCatalogSeeder"
 *   SEED_PRODUCTS=500 php artisan db:seed --class=...        (a smaller catalogue)
 *
 * Deterministic (the same catalogue every time) and safe to run again: a SKU that already exists
 * is skipped. Models, brands and spec ranges are realistic; the prices are plausible market
 * prices in F CFA, not a price list, and there are no product photos.
 */
class TechCatalogSeeder extends Seeder
{
    public const DEFAULT_COUNT = 3000;
    private const SKU_PREFIX = 'SEED';

    /** Colours as [French, English]. */
    private const COLORS = [
        ['Noir', 'Black'], ['Blanc', 'White'], ['Bleu', 'Blue'], ['Gris', 'Grey'], ['Argent', 'Silver'],
        ['Vert', 'Green'], ['Rouge', 'Red'], ['Or', 'Gold'], ['Violet', 'Purple'], ['Rose', 'Pink'],
    ];

    /** @var array<string, int> */
    private array $categoryIds = [];

    public function run(): void
    {
        $this->generate((int) env('SEED_PRODUCTS', self::DEFAULT_COUNT));
    }

    /** Creates up to $count products across the categories (by weight) and returns how many were added. */
    public function generate(int $count): int
    {
        mt_srand(20261028);
        $plan = $this->plan();
        $this->ensureCategories($plan);

        $existing = DB::table('product_variants')->where('sku', 'like', self::SKU_PREFIX.'-%')->pluck('sku')->flip()->all();
        $totalWeight = array_sum(array_column($plan, 'weight'));
        $created = 0;
        $sequence = 0;

        DB::transaction(function () use ($count, $plan, $totalWeight, &$existing, &$created, &$sequence) {
            foreach ($plan as $slug => $category) {
                $target = (int) round($count * $category['weight'] / $totalWeight);

                for ($i = 0; $i < $target; $i++) {
                    $sequence++;
                    $sku = sprintf('%s-%06d', self::SKU_PREFIX, $sequence);

                    // Always draw the product (keeps the sequence deterministic), insert only when new.
                    $item = $this->{$category['kind']}($category);

                    if (isset($existing[$sku])) {
                        continue;
                    }

                    $this->insert($slug, $sku, $item);
                    $created++;
                }
            }
        });

        return $created;
    }

    // ------------------------------------------------------------------ persistence

    /**
     * @param  array<string, mixed>  $item
     */
    private function insert(string $categorySlug, string $sku, array $item): void
    {
        $now = now();
        $published = mt_rand(1, 100) > 3; // a few drafts, as a real back office has

        $productId = DB::table('products')->insertGetId([
            'category_id' => $this->categoryIds[$categorySlug],
            'brand' => $item['brand'],
            'name' => $item['name'],
            'name_en' => $item['name_en'],
            'slug' => Str::slug($item['name'].' '.$sku),
            'description' => $item['description'],
            'description_en' => $item['description_en'],
            'attributes' => json_encode($item['attributes'], JSON_UNESCAPED_UNICODE),
            'condition_state' => $item['condition'],
            'status' => $published ? 'published' : 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => $sku,
            'ean' => '5'.str_pad((string) (100000000000 + (int) substr($sku, -6) * 7), 12, '0', STR_PAD_LEFT),
            'attributes' => json_encode($item['variant'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $price = $item['price'];
        $discounted = mt_rand(1, 100) <= 18;
        $stockRoll = mt_rand(1, 100);

        DB::table('offers')->insert([
            'product_variant_id' => $variantId,
            'vendor_id' => null,
            'price_amount' => $price,
            'currency' => 'XOF',
            // A reference price above the selling price on about one product in five.
            'compare_at_price_amount' => $discounted ? $this->roundPrice((int) ($price / (1 - mt_rand(8, 35) / 100))) : null,
            'condition_grade' => $item['condition'] === 'neuf' ? null : ['A', 'B', 'C'][mt_rand(0, 2)],
            'warranty_months' => $item['warranty'],
            'stock_quantity' => $stockRoll <= 4 ? 0 : ($stockRoll <= 14 ? mt_rand(1, 5) : mt_rand(6, 200)),
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $plan
     */
    private function ensureCategories(array $plan): void
    {
        $position = 0;

        foreach ($plan as $slug => $category) {
            $position++;
            $existing = DB::table('categories')->where('slug', $slug)->first();

            if ($existing === null) {
                $id = DB::table('categories')->insertGetId([
                    'parent_id' => null,
                    'name' => $category['name'],
                    'name_en' => $category['name_en'],
                    'slug' => $slug,
                    'description' => $category['description'],
                    'position' => $position,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $id = $existing->id;
                // A category made earlier (the demo catalogue) gets its English name if it has none.
                DB::table('categories')->where('id', $id)->whereNull('name_en')->update(['name_en' => $category['name_en']]);
            }

            $this->categoryIds[$slug] = (int) $id;
        }
    }

    // ------------------------------------------------------------------ the plan

    /**
     * @return array<string, array<string, mixed>>
     */
    private function plan(): array
    {
        return [
            'telephones' => ['kind' => 'phone', 'weight' => 20, 'name' => 'Téléphones', 'name_en' => 'Phones', 'description' => 'Smartphones et téléphones portables.'],
            'tablettes' => ['kind' => 'tablet', 'weight' => 5, 'name' => 'Tablettes', 'name_en' => 'Tablets', 'description' => 'Tablettes tactiles.'],
            'ordinateurs-portables' => ['kind' => 'laptop', 'weight' => 12, 'name' => 'Ordinateurs portables', 'name_en' => 'Laptops', 'description' => 'PC portables pour la maison, le bureau et le jeu.'],
            'ordinateurs-de-bureau' => ['kind' => 'desktop', 'weight' => 4, 'name' => 'Ordinateurs de bureau', 'name_en' => 'Desktop computers', 'description' => 'Tours, mini-PC et tout-en-un.'],
            'ecrans' => ['kind' => 'monitor', 'weight' => 5, 'name' => 'Écrans et moniteurs', 'name_en' => 'Screens and monitors', 'description' => 'Moniteurs bureautiques, gaming et professionnels.'],
            'televisions' => ['kind' => 'tv', 'weight' => 7, 'name' => 'Télévisions', 'name_en' => 'Televisions', 'description' => 'Téléviseurs LED, QLED et OLED.'],
            'casques-ecouteurs' => ['kind' => 'headphones', 'weight' => 8, 'name' => 'Casques et écouteurs', 'name_en' => 'Headphones and earbuds', 'description' => 'Casques, écouteurs sans fil et à réduction de bruit.'],
            'enceintes-bluetooth' => ['kind' => 'speaker', 'weight' => 5, 'name' => 'Enceintes Bluetooth', 'name_en' => 'Bluetooth speakers', 'description' => 'Enceintes portables et de soirée.'],
            'barres-de-son' => ['kind' => 'soundbar', 'weight' => 3, 'name' => 'Barres de son et home cinéma', 'name_en' => 'Soundbars and home cinema', 'description' => 'Barres de son, caissons et systèmes home cinéma.'],
            'sono-dj' => ['kind' => 'pa', 'weight' => 4, 'name' => 'Sono et DJ', 'name_en' => 'PA and DJ gear', 'description' => 'Enceintes actives, micros, tables de mixage et contrôleurs DJ.'],
            'photo-video' => ['kind' => 'camera', 'weight' => 4, 'name' => 'Photo et vidéo', 'name_en' => 'Photo and video', 'description' => 'Appareils photo, objectifs, caméras d\'action et drones.'],
            'jeux-video' => ['kind' => 'gaming', 'weight' => 5, 'name' => 'Jeux vidéo', 'name_en' => 'Video games', 'description' => 'Consoles, manettes et accessoires de jeu.'],
            'montres-connectees' => ['kind' => 'watch', 'weight' => 3, 'name' => 'Montres connectées', 'name_en' => 'Smartwatches', 'description' => 'Montres et bracelets connectés.'],
            'reseau' => ['kind' => 'network', 'weight' => 4, 'name' => 'Réseau et internet', 'name_en' => 'Networking', 'description' => 'Routeurs, répéteurs Wi-Fi, switchs et clés 4G.'],
            'imprimantes' => ['kind' => 'printer', 'weight' => 3, 'name' => 'Imprimantes et scanners', 'name_en' => 'Printers and scanners', 'description' => 'Imprimantes laser et jet d\'encre, multifonctions.'],
            'stockage' => ['kind' => 'storage', 'weight' => 4, 'name' => 'Stockage', 'name_en' => 'Storage', 'description' => 'SSD, disques durs, clés USB et cartes mémoire.'],
            'accessoires' => ['kind' => 'accessory', 'weight' => 6, 'name' => 'Accessoires et énergie', 'name_en' => 'Accessories and power', 'description' => 'Chargeurs, câbles, batteries externes, coques et onduleurs.'],
            'securite-maison-connectee' => ['kind' => 'security', 'weight' => 3, 'name' => 'Sécurité et maison connectée', 'name_en' => 'Security and smart home', 'description' => 'Caméras IP, sonnettes et ampoules connectées.'],
        ];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @template T
     *
     * @param  array<int, T>  $list
     * @return T
     */
    private function pick(array $list)
    {
        return $list[mt_rand(0, count($list) - 1)];
    }

    /** Rounds to a price people actually see: 500 F below 100 000, 1 000 F below 1 M, 5 000 F beyond. */
    private function roundPrice(int $amount): int
    {
        $step = $amount < 100_000 ? 500 : ($amount < 1_000_000 ? 1_000 : 5_000);

        return max($step, (int) (round($amount / $step) * $step));
    }

    /** A price inside [min, max], skewed toward the low end like a real catalogue. */
    private function priceBetween(int $min, int $max): int
    {
        $skew = (mt_rand(0, 1000) / 1000) ** 1.6;

        return $this->roundPrice((int) ($min + ($max - $min) * $skew));
    }

    /**
     * Builds the common item shape.
     *
     * @param  array<string, mixed>  $parts
     * @return array<string, mixed>
     */
    private function item(string $brand, string $fr, string $en, array $parts): array
    {
        return [
            'brand' => $brand,
            'name' => trim($brand.' '.$fr),
            'name_en' => trim($brand.' '.$en),
            'description' => $parts['description'],
            'description_en' => $parts['description_en'],
            'attributes' => $parts['attributes'] ?? [],
            'variant' => $parts['variant'] ?? [],
            'condition' => $parts['condition'] ?? 'neuf',
            'warranty' => $parts['warranty'] ?? 12,
            'price' => $parts['price'],
        ];
    }

    /** New most of the time; refurbished or used where it makes sense. */
    private function condition(bool $mayBeUsed): string
    {
        $roll = mt_rand(1, 100);
        if (! $mayBeUsed) {
            return 'neuf';
        }

        return $roll <= 80 ? 'neuf' : ($roll <= 93 ? 'reconditionne' : 'occasion');
    }

    private function warrantyFor(string $condition, int $new): int
    {
        return $condition === 'neuf' ? $new : ($condition === 'reconditionne' ? 6 : 3);
    }

    /** Lowers the price of a refurbished / used unit. */
    private function discountFor(string $condition, int $price): int
    {
        $factor = $condition === 'neuf' ? 1 : ($condition === 'reconditionne' ? 0.72 : 0.55);

        return $this->roundPrice((int) ($price * $factor));
    }

    // ------------------------------------------------------------------ kinds

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function phone(array $c): array
    {
        $lines = [
            ['Samsung', ['Galaxy A06', 'Galaxy A16', 'Galaxy A26', 'Galaxy A36', 'Galaxy A56', 'Galaxy S24', 'Galaxy S24 Ultra', 'Galaxy S25', 'Galaxy M35', 'Galaxy Z Flip6'], 55_000, 1_100_000],
            ['Apple', ['iPhone 12', 'iPhone 13', 'iPhone 14', 'iPhone 15', 'iPhone 15 Pro', 'iPhone 16', 'iPhone 16 Pro Max', 'iPhone SE'], 220_000, 1_250_000],
            ['Xiaomi', ['Redmi 13', 'Redmi Note 13', 'Redmi Note 14 Pro', 'Poco X6', 'Poco F6', 'Xiaomi 14T'], 60_000, 480_000],
            ['Tecno', ['Spark 20', 'Spark 30', 'Camon 30', 'Phantom V Flip', 'Pova 6'], 45_000, 330_000],
            ['Infinix', ['Hot 40', 'Hot 50', 'Note 40', 'Zero 40', 'Smart 8'], 40_000, 260_000],
            ['itel', ['A70', 'P55', 'S24', 'A60s'], 28_000, 85_000],
            ['Oppo', ['A58', 'A79', 'Reno 12', 'Find X7'], 75_000, 600_000],
            ['Realme', ['C65', '12 Pro', 'GT 6', 'Narzo 70'], 65_000, 340_000],
            ['Huawei', ['Nova 12', 'P60 Pro', 'Pura 70'], 120_000, 700_000],
            ['Google', ['Pixel 8', 'Pixel 8 Pro', 'Pixel 9'], 330_000, 780_000],
            ['Motorola', ['Moto G54', 'Moto G84', 'Edge 50'], 80_000, 420_000],
            ['Nokia', ['C32', 'G42', '105 4G'], 18_000, 120_000],
        ];
        [$brand, $models, $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        [$colorFr, $colorEn] = $this->pick(self::COLORS);
        $ram = $this->pick([3, 4, 6, 8, 12]);
        $storage = $this->pick([32, 64, 128, 256, 512]);
        $net = mt_rand(1, 100) <= 62 ? '5G' : '4G';
        $condition = $this->condition(true);
        $price = $this->priceBetween($min, $max) + $ram * 2_000 + $storage * 90;
        $price = $this->discountFor($condition, $this->roundPrice($price));

        return $this->item($brand, "{$model} {$net} {$ram} Go / {$storage} Go {$colorFr}", "{$model} {$net} {$ram} GB / {$storage} GB {$colorEn}", [
            'description' => "{$brand} {$model} {$net} : {$ram} Go de RAM, {$storage} Go de stockage, double SIM, coloris {$colorFr}. Livré avec chargeur et câble.",
            'description_en' => "{$brand} {$model} {$net}: {$ram} GB RAM, {$storage} GB storage, dual SIM, {$colorEn}. Comes with charger and cable.",
            'attributes' => ['ram_gb' => $ram, 'storage_gb' => $storage, 'network' => $net, 'color' => $colorFr],
            'variant' => ['color' => $colorFr, 'storage_gb' => $storage],
            'condition' => $condition,
            'warranty' => $this->warrantyFor($condition, 12),
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function tablet(array $c): array
    {
        $lines = [
            ['Apple', ['iPad 10e génération', 'iPad Air M2', 'iPad Pro 11"', 'iPad mini'], 'iPad', 260_000, 1_100_000],
            ['Samsung', ['Galaxy Tab A9', 'Galaxy Tab S9 FE', 'Galaxy Tab S10'], 'Tab', 110_000, 780_000],
            ['Lenovo', ['Tab M10', 'Tab P12', 'Legion Tab'], 'Tab', 85_000, 380_000],
            ['Xiaomi', ['Redmi Pad SE', 'Pad 6', 'Poco Pad'], 'Pad', 95_000, 320_000],
            ['Huawei', ['MatePad SE', 'MatePad 11.5', 'MatePad Pro'], 'MatePad', 105_000, 520_000],
        ];
        [$brand, $models, , $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        $storage = $this->pick([32, 64, 128, 256, 512]);
        $cell = mt_rand(1, 100) <= 40;
        [$colorFr, $colorEn] = $this->pick(self::COLORS);
        $price = $this->roundPrice($this->priceBetween($min, $max) + $storage * 150 + ($cell ? 30_000 : 0));

        return $this->item($brand, "{$model} {$storage} Go ".($cell ? '4G' : 'Wi-Fi')." {$colorFr}", "{$model} {$storage} GB ".($cell ? '4G' : 'Wi-Fi')." {$colorEn}", [
            'description' => "Tablette {$brand} {$model}, {$storage} Go, ".($cell ? 'avec connexion 4G' : 'Wi-Fi').". Idéale pour les études, les films et le travail nomade.",
            'description_en' => "{$brand} {$model} tablet, {$storage} GB, ".($cell ? 'with 4G connectivity' : 'Wi-Fi').'. Great for study, films and working on the move.',
            'attributes' => ['storage_gb' => $storage, 'connectivity' => $cell ? '4G' : 'Wi-Fi', 'color' => $colorFr],
            'variant' => ['color' => $colorFr, 'storage_gb' => $storage],
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function laptop(array $c): array
    {
        $lines = [
            ['HP', ['Pavilion 15', '250 G9', 'ProBook 450', 'EliteBook 840', 'Victus 16', 'Envy x360'], 230_000, 1_400_000],
            ['Dell', ['Inspiron 15', 'Vostro 3520', 'Latitude 5440', 'XPS 13', 'G15 Gaming'], 260_000, 1_900_000],
            ['Lenovo', ['IdeaPad 3', 'IdeaPad Slim 5', 'ThinkPad E14', 'ThinkPad X1 Carbon', 'LOQ 15', 'Legion 5'], 240_000, 2_200_000],
            ['Asus', ['Vivobook 15', 'Zenbook 14', 'TUF Gaming F15', 'ROG Strix G16'], 260_000, 2_400_000],
            ['Acer', ['Aspire 3', 'Aspire 5', 'Swift Go', 'Nitro V 15'], 215_000, 1_300_000],
            ['Apple', ['MacBook Air 13" M2', 'MacBook Air 15" M3', 'MacBook Pro 14" M3', 'MacBook Pro 16" M3 Pro'], 780_000, 3_200_000],
            ['MSI', ['Modern 14', 'Thin GF63', 'Katana 15', 'Raider GE78'], 330_000, 2_800_000],
        ];
        [$brand, $models, $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        $apple = $brand === 'Apple';
        $cpu = $apple ? $this->pick(['Apple M2', 'Apple M3', 'Apple M3 Pro']) : $this->pick(['Intel Core i3-1215U', 'Intel Core i5-1235U', 'Intel Core i5-13420H', 'Intel Core i7-13700H', 'AMD Ryzen 5 7520U', 'AMD Ryzen 7 7735HS']);
        $ram = $apple ? $this->pick([8, 16, 18, 24]) : $this->pick([4, 8, 8, 16, 16, 32]);
        $ssd = $this->pick([256, 512, 512, 1000, 2000]);
        $screen = $apple ? $this->pick(['13.6"', '14.2"', '15.3"', '16.2"']) : $this->pick(['14"', '15.6"', '15.6"', '16"', '17.3"']);
        $condition = $this->condition(true);
        $price = $this->roundPrice($this->priceBetween($min, $max) + $ram * 4_500 + $ssd * 90);
        $price = $this->discountFor($condition, $price);

        return $this->item($brand, "{$model} {$cpu} {$ram} Go {$ssd} Go SSD {$screen}", "{$model} {$cpu} {$ram} GB {$ssd} GB SSD {$screen}", [
            'description' => "Ordinateur portable {$brand} {$model} : processeur {$cpu}, {$ram} Go de RAM, SSD {$ssd} Go, écran {$screen}. Windows ou macOS selon le modèle.",
            'description_en' => "{$brand} {$model} laptop: {$cpu} processor, {$ram} GB RAM, {$ssd} GB SSD, {$screen} display. Windows or macOS depending on the model.",
            'attributes' => ['cpu' => $cpu, 'ram_gb' => $ram, 'storage_gb' => $ssd, 'screen' => $screen],
            'variant' => ['ram_gb' => $ram, 'storage_gb' => $ssd],
            'condition' => $condition,
            'warranty' => $this->warrantyFor($condition, 12),
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function desktop(array $c): array
    {
        $lines = [
            ['HP', ['ProDesk 400 G9', 'Pavilion TP01', 'All-in-One 24', 'Victus 15L'], 260_000, 1_300_000],
            ['Dell', ['OptiPlex 3000', 'Vostro 3710', 'Inspiron 24 tout-en-un', 'Alienware Aurora'], 280_000, 2_000_000],
            ['Lenovo', ['ThinkCentre M70', 'IdeaCentre AIO 3', 'Legion Tower 5'], 270_000, 1_500_000],
            ['Apple', ['Mac mini M2', 'iMac 24" M3'], 620_000, 1_600_000],
            ['Asus', ['ExpertCenter D5', 'Vivo AiO V440', 'ROG Strix G16CH'], 290_000, 1_700_000],
        ];
        [$brand, $models, $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        $ram = $this->pick([8, 16, 32]);
        $ssd = $this->pick([256, 512, 1000]);
        $price = $this->roundPrice($this->priceBetween($min, $max) + $ram * 4_000 + $ssd * 80);

        return $this->item($brand, "{$model} {$ram} Go {$ssd} Go SSD", "{$model} {$ram} GB {$ssd} GB SSD", [
            'description' => "Ordinateur de bureau {$brand} {$model}, {$ram} Go de RAM et SSD {$ssd} Go. Silencieux, fiable, prêt pour le bureau.",
            'description_en' => "{$brand} {$model} desktop computer with {$ram} GB RAM and a {$ssd} GB SSD. Quiet, reliable, office-ready.",
            'attributes' => ['ram_gb' => $ram, 'storage_gb' => $ssd],
            'variant' => ['ram_gb' => $ram, 'storage_gb' => $ssd],
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function monitor(array $c): array
    {
        $brands = ['Samsung', 'LG', 'Dell', 'AOC', 'HP', 'Asus', 'Acer', 'MSI', 'Philips'];
        $brand = $this->pick($brands);
        $size = $this->pick([19, 22, 24, 24, 27, 27, 32, 34, 49]);
        $panel = $this->pick(['IPS', 'VA', 'IPS', 'OLED']);
        $hz = $this->pick([60, 75, 100, 144, 165, 240]);
        $res = $size <= 22 ? 'Full HD' : $this->pick(['Full HD', 'QHD', '4K UHD']);
        $series = $this->pick(['Essential', 'Pro', 'Gaming', 'Office', 'Curved', 'UltraWide']);
        $price = $this->roundPrice(38_000 + $size * 4_800 + ($hz - 60) * 600 + ($res === '4K UHD' ? 90_000 : ($res === 'QHD' ? 45_000 : 0)) + mt_rand(0, 25_000) + ($panel === 'OLED' ? 220_000 : 0));

        return $this->item($brand, "Écran {$series} {$size}\" {$res} {$panel} {$hz} Hz", "{$series} monitor {$size}\" {$res} {$panel} {$hz} Hz", [
            'description' => "Moniteur {$brand} {$size} pouces, dalle {$panel}, définition {$res}, {$hz} Hz. HDMI et DisplayPort.",
            'description_en' => "{$brand} {$size}-inch monitor, {$panel} panel, {$res} resolution, {$hz} Hz. HDMI and DisplayPort.",
            'attributes' => ['screen_inches' => $size, 'panel' => $panel, 'refresh_hz' => $hz, 'resolution' => $res],
            'warranty' => 24,
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function tv(array $c): array
    {
        $brand = $this->pick(['Samsung', 'LG', 'Hisense', 'TCL', 'Sony', 'Xiaomi', 'Philips', 'Skyworth', 'Nasco']);
        $size = $this->pick([32, 40, 43, 50, 55, 55, 65, 65, 75, 85]);
        $tech = $this->pick(['LED', 'LED', 'QLED', 'QLED', 'OLED', 'Mini LED']);
        $res = $size <= 32 ? 'HD' : $this->pick(['Full HD', '4K UHD', '4K UHD']);
        $smart = $this->pick(['Google TV', 'Android TV', 'Tizen', 'webOS', 'VIDAA']);
        $price = $this->roundPrice(48_000 + $size * 7_500 + ['LED' => 0, 'QLED' => 90_000, 'Mini LED' => 160_000, 'OLED' => 320_000][$tech] + ($res === '4K UHD' ? 60_000 : 0) + mt_rand(0, 40_000));

        return $this->item($brand, "Téléviseur {$size}\" {$tech} {$res} Smart TV {$smart}", "{$size}\" {$tech} {$res} Smart TV {$smart}", [
            'description' => "Téléviseur {$brand} {$size} pouces {$tech} {$res}, Smart TV {$smart}, Wi-Fi, 3 ports HDMI, télécommande vocale.",
            'description_en' => "{$brand} {$size}-inch {$tech} {$res} television, {$smart} Smart TV, Wi-Fi, 3 HDMI ports, voice remote.",
            'attributes' => ['screen_inches' => $size, 'technology' => $tech, 'resolution' => $res, 'smart_os' => $smart],
            'warranty' => 24,
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function headphones(array $c): array
    {
        $lines = [
            ['Apple', ['AirPods 4', 'AirPods Pro 2', 'AirPods Max'], 120_000, 450_000],
            ['Samsung', ['Galaxy Buds FE', 'Galaxy Buds3', 'Galaxy Buds3 Pro'], 55_000, 220_000],
            ['Sony', ['WH-CH520', 'WH-1000XM5', 'WF-C510', 'WF-1000XM5'], 40_000, 280_000],
            ['JBL', ['Tune 520BT', 'Tune Beam', 'Live 770NC', 'Quantum 810'], 28_000, 210_000],
            ['Bose', ['QuietComfort Ultra', 'QuietComfort Earbuds II', 'SoundLink Flex'], 210_000, 420_000],
            ['Anker Soundcore', ['Life Q30', 'Space One', 'P40i', 'Liberty 4 NC'], 22_000, 120_000],
            ['Xiaomi', ['Redmi Buds 5', 'Buds 5 Pro', 'Redmi Buds 6 Active'], 10_000, 70_000],
            ['Oraimo', ['FreePods 4', 'BoomPop 2', 'Riff 2'], 8_000, 35_000],
        ];
        [$brand, $models, $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        $anc = mt_rand(1, 100) <= 45;
        [$colorFr, $colorEn] = $this->pick(self::COLORS);
        $price = $this->priceBetween($min, $max);

        return $this->item($brand, "{$model}".($anc ? ' réduction de bruit' : '')." {$colorFr}", "{$model}".($anc ? ' noise cancelling' : '')." {$colorEn}", [
            'description' => "{$brand} {$model} sans fil, Bluetooth 5.3".($anc ? ', réduction active du bruit' : '').", autonomie jusqu'à ".$this->pick([20, 24, 30, 40, 60]).' heures avec le boîtier.',
            'description_en' => "{$brand} {$model} wireless, Bluetooth 5.3".($anc ? ', active noise cancelling' : '').", up to ".$this->pick([20, 24, 30, 40, 60]).' hours of battery life with the case.',
            'attributes' => ['bluetooth' => '5.3', 'noise_cancelling' => $anc, 'color' => $colorFr],
            'variant' => ['color' => $colorFr],
            'warranty' => 12,
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function speaker(array $c): array
    {
        $lines = [
            ['JBL', ['Flip 6', 'Charge 5', 'Clip 5', 'Go 4', 'Xtreme 4', 'PartyBox 110', 'PartyBox Club 320'], 25_000, 620_000],
            ['Sony', ['SRS-XB100', 'SRS-XE200', 'SRS-XG300', 'ULT Field 7'], 30_000, 400_000],
            ['Bose', ['SoundLink Flex', 'SoundLink Max', 'Home Speaker 300'], 130_000, 480_000],
            ['Marshall', ['Emberton III', 'Willen II', 'Stanmore III', 'Woburn III'], 110_000, 700_000],
            ['Anker Soundcore', ['Motion 300', 'Boom 2', 'Rave 3'], 25_000, 160_000],
            ['Xiaomi', ['Bluetooth Speaker Mini', 'Sound Outdoor 30W'], 12_000, 45_000],
            ['Oraimo', ['Boom 3', 'Soundcore Go'], 9_000, 28_000],
        ];
        [$brand, $models, $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        $watts = $this->pick([5, 10, 20, 30, 40, 60, 100, 200]);
        [$colorFr, $colorEn] = $this->pick(self::COLORS);

        return $this->item($brand, "Enceinte {$model} {$watts} W {$colorFr}", "{$model} speaker {$watts} W {$colorEn}", [
            'description' => "Enceinte Bluetooth {$brand} {$model} de {$watts} W, étanche IPX7, autonomie jusqu'à ".$this->pick([10, 12, 20, 24]).' heures.',
            'description_en' => "{$brand} {$model} Bluetooth speaker, {$watts} W, IPX7 waterproof, up to ".$this->pick([10, 12, 20, 24]).' hours of battery life.',
            'attributes' => ['power_w' => $watts, 'waterproof' => 'IPX7', 'color' => $colorFr],
            'variant' => ['color' => $colorFr],
            'price' => $this->roundPrice($this->priceBetween($min, $max) + $watts * 150),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function soundbar(array $c): array
    {
        $brand = $this->pick(['Samsung', 'LG', 'Sony', 'JBL', 'Bose', 'Hisense', 'Yamaha', 'Polk', 'TCL']);
        $channels = $this->pick(['2.0', '2.1', '3.1', '3.1.2', '5.1', '5.1.2', '7.1.4']);
        $watts = $this->pick([60, 100, 120, 200, 300, 400, 570]);
        $sub = mt_rand(1, 100) <= 60;
        $price = $this->roundPrice(45_000 + $watts * 650 + (int) substr($channels, 0, 1) * 18_000 + mt_rand(0, 30_000));

        return $this->item($brand, "Barre de son {$channels} {$watts} W".($sub ? ' avec caisson' : ''), "{$channels} soundbar {$watts} W".($sub ? ' with subwoofer' : ''), [
            'description' => "Barre de son {$brand} {$channels} de {$watts} W".($sub ? ' avec caisson de basses sans fil' : '').', HDMI eARC, Bluetooth, Dolby Audio.',
            'description_en' => "{$brand} {$channels} soundbar, {$watts} W".($sub ? ' with a wireless subwoofer' : '').', HDMI eARC, Bluetooth, Dolby Audio.',
            'attributes' => ['channels' => $channels, 'power_w' => $watts, 'subwoofer' => $sub],
            'warranty' => 12,
            'price' => $price,
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function pa(array $c): array
    {
        $types = [
            ['Yamaha', 'Enceinte active DBR12 12"', 'DBR12 12" active speaker', 'Enceinte de sonorisation active', 'Active PA speaker', 320_000, 560_000],
            ['Behringer', 'Table de mixage Xenyx 12 voies', 'Xenyx 12-channel mixing desk', 'Table de mixage analogique', 'Analogue mixing desk', 120_000, 340_000],
            ['Shure', 'Microphone SM58', 'SM58 microphone', 'Microphone dynamique de scène', 'Dynamic stage microphone', 70_000, 140_000],
            ['Shure', 'Micro sans fil SLX-D', 'SLX-D wireless microphone', 'Micro sans fil numérique', 'Digital wireless microphone', 360_000, 800_000],
            ['Pioneer DJ', 'Contrôleur DDJ-FLX4', 'DDJ-FLX4 controller', 'Contrôleur DJ 2 voies', '2-channel DJ controller', 330_000, 450_000],
            ['Pioneer DJ', 'Console DDJ-1000', 'DDJ-1000 console', 'Console DJ 4 voies', '4-channel DJ console', 800_000, 1_150_000],
            ['Rode', 'Microphone NT-USB+', 'NT-USB+ microphone', 'Micro USB pour podcast et streaming', 'USB microphone for podcasts and streaming', 140_000, 220_000],
            ['JBL', 'Enceinte de sono EON715', 'EON715 PA speaker', 'Enceinte active 15" 1300 W', '15" 1300 W active speaker', 520_000, 760_000],
            ['Alto Professional', 'Caisson de basses TS18S', 'TS18S subwoofer', 'Caisson de basses actif 18"', 'Active 18" subwoofer', 650_000, 900_000],
            ['Audio-Technica', 'Casque studio ATH-M50x', 'ATH-M50x studio headphones', 'Casque de studio', 'Studio headphones', 130_000, 190_000],
            ['Focusrite', 'Interface audio Scarlett 2i2', 'Scarlett 2i2 audio interface', 'Interface audio USB 2 entrées', '2-input USB audio interface', 130_000, 200_000],
            ['Mackie', 'Amplificateur de sono 1000 W', '1000 W PA amplifier', 'Amplificateur de puissance', 'Power amplifier', 300_000, 600_000],
        ];
        [$brand, $fr, $en, $descFr, $descEn, $min, $max] = $this->pick($types);

        return $this->item($brand, $fr, $en, [
            'description' => "{$descFr} {$brand} : matériel de sonorisation pour églises, salles de fête, DJ et studios.",
            'description_en' => "{$brand} {$descEn}: PA gear for churches, event halls, DJs and studios.",
            'attributes' => ['usage' => 'sono'],
            'warranty' => 12,
            'price' => $this->priceBetween($min, $max),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function camera(array $c): array
    {
        $types = [
            ['Canon', 'EOS R50 + 18-45 mm', 'EOS R50 + 18-45 mm', 'Appareil photo hybride', 'Mirrorless camera', 620_000, 780_000],
            ['Canon', 'EOS 2000D + 18-55 mm', 'EOS 2000D + 18-55 mm', 'Reflex numérique', 'Digital SLR', 330_000, 450_000],
            ['Nikon', 'Z50 II + 16-50 mm', 'Z50 II + 16-50 mm', 'Appareil photo hybride', 'Mirrorless camera', 780_000, 980_000],
            ['Sony', 'Alpha a6400 + 16-50 mm', 'Alpha a6400 + 16-50 mm', 'Appareil photo hybride APS-C', 'APS-C mirrorless camera', 750_000, 950_000],
            ['Sony', 'ZV-E10 II', 'ZV-E10 II', 'Caméra vlog', 'Vlog camera', 700_000, 900_000],
            ['Fujifilm', 'Instax Mini 12', 'Instax Mini 12', 'Appareil photo instantané', 'Instant camera', 60_000, 90_000],
            ['GoPro', 'HERO13 Black', 'HERO13 Black', 'Caméra d\'action 5,3K', '5.3K action camera', 300_000, 400_000],
            ['DJI', 'Osmo Action 4', 'Osmo Action 4', 'Caméra d\'action 4K', '4K action camera', 250_000, 330_000],
            ['DJI', 'Mini 4 Pro', 'Mini 4 Pro', 'Drone compact 4K', 'Compact 4K drone', 640_000, 900_000],
            ['DJI', 'Osmo Mobile 6', 'Osmo Mobile 6', 'Stabilisateur de smartphone', 'Smartphone gimbal', 90_000, 130_000],
            ['Canon', 'Objectif RF 50 mm f/1.8', 'RF 50 mm f/1.8 lens', 'Objectif', 'Lens', 150_000, 210_000],
            ['Insta360', 'X4', 'X4', 'Caméra 360° 8K', '8K 360° camera', 450_000, 560_000],
        ];
        [$brand, $fr, $en, $descFr, $descEn, $min, $max] = $this->pick($types);

        return $this->item($brand, $fr, $en, [
            'description' => "{$descFr} {$brand} {$fr}. Livré avec batterie, chargeur et sangle.",
            'description_en' => "{$brand} {$en} {$descEn}. Comes with battery, charger and strap.",
            'attributes' => ['type' => $descEn],
            'warranty' => 12,
            'price' => $this->priceBetween($min, $max),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function gaming(array $c): array
    {
        $types = [
            ['Sony', 'PlayStation 5 Slim édition standard', 'PlayStation 5 Slim standard edition', 'Console de salon 1 To', '1 TB home console', 380_000, 470_000],
            ['Sony', 'PlayStation 5 Slim édition digitale', 'PlayStation 5 Slim digital edition', 'Console sans lecteur 1 To', '1 TB disc-free console', 340_000, 420_000],
            ['Sony', 'Manette DualSense', 'DualSense controller', 'Manette sans fil', 'Wireless controller', 45_000, 70_000],
            ['Microsoft', 'Xbox Series X 1 To', 'Xbox Series X 1 TB', 'Console 4K', '4K console', 330_000, 420_000],
            ['Microsoft', 'Xbox Series S 512 Go', 'Xbox Series S 512 GB', 'Console compacte', 'Compact console', 190_000, 250_000],
            ['Microsoft', 'Manette sans fil Xbox', 'Xbox wireless controller', 'Manette', 'Controller', 40_000, 65_000],
            ['Nintendo', 'Switch OLED', 'Switch OLED', 'Console hybride', 'Hybrid console', 210_000, 280_000],
            ['Nintendo', 'Switch Lite', 'Switch Lite', 'Console portable', 'Handheld console', 120_000, 160_000],
            ['Nintendo', 'Jeu Mario Kart 8 Deluxe', 'Mario Kart 8 Deluxe game', 'Jeu de course', 'Racing game', 28_000, 40_000],
            ['Sony', 'Jeu EA Sports FC 25 PS5', 'EA Sports FC 25 PS5 game', 'Jeu de football', 'Football game', 28_000, 45_000],
            ['Logitech', 'Volant G29 + pédalier', 'G29 steering wheel + pedals', 'Volant de course', 'Racing wheel', 190_000, 260_000],
            ['Razer', 'Casque BlackShark V2', 'BlackShark V2 headset', 'Casque gaming', 'Gaming headset', 60_000, 110_000],
            ['Logitech', 'Souris G502 Hero', 'G502 Hero mouse', 'Souris gaming', 'Gaming mouse', 40_000, 65_000],
            ['Razer', 'Clavier mécanique BlackWidow V4', 'BlackWidow V4 mechanical keyboard', 'Clavier gaming', 'Gaming keyboard', 120_000, 210_000],
        ];
        [$brand, $fr, $en, $descFr, $descEn, $min, $max] = $this->pick($types);

        return $this->item($brand, $fr, $en, [
            'description' => "{$descFr} {$brand} : {$fr}. Produit neuf, garanti.",
            'description_en' => "{$brand} {$descEn}: {$en}. New, with warranty.",
            'attributes' => ['platform' => 'gaming'],
            'warranty' => 12,
            'price' => $this->priceBetween($min, $max),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function watch(array $c): array
    {
        $lines = [
            ['Apple', ['Watch SE', 'Watch Series 9', 'Watch Series 10', 'Watch Ultra 2'], 170_000, 650_000],
            ['Samsung', ['Galaxy Watch6', 'Galaxy Watch7', 'Galaxy Fit3', 'Galaxy Watch Ultra'], 55_000, 380_000],
            ['Xiaomi', ['Smart Band 8', 'Smart Band 9', 'Watch S3', 'Redmi Watch 4'], 18_000, 90_000],
            ['Huawei', ['Watch Fit 3', 'Watch GT 4', 'Band 9', 'Watch D2'], 30_000, 320_000],
            ['Garmin', ['Forerunner 255', 'Venu 3', 'Fenix 7', 'Instinct 2'], 190_000, 700_000],
            ['Amazfit', ['Bip 5', 'GTR 4', 'Balance'], 40_000, 150_000],
        ];
        [$brand, $models, $min, $max] = $this->pick($lines);
        $model = $this->pick($models);
        $size = $this->pick([38, 40, 41, 42, 44, 45, 46, 49]);
        $cell = mt_rand(1, 100) <= 25;
        [$colorFr, $colorEn] = $this->pick(self::COLORS);

        return $this->item($brand, "{$model} {$size} mm".($cell ? ' GPS + Cellular' : ' GPS')." {$colorFr}", "{$model} {$size} mm".($cell ? ' GPS + Cellular' : ' GPS')." {$colorEn}", [
            'description' => "Montre connectée {$brand} {$model}, boîtier {$size} mm, suivi sportif et du sommeil, notifications".($cell ? ', connexion cellulaire' : '').'.',
            'description_en' => "{$brand} {$model} smartwatch, {$size} mm case, sport and sleep tracking, notifications".($cell ? ', cellular connectivity' : '').'.',
            'attributes' => ['case_mm' => $size, 'cellular' => $cell, 'color' => $colorFr],
            'variant' => ['color' => $colorFr, 'case_mm' => $size],
            'warranty' => 12,
            'price' => $this->roundPrice($this->priceBetween($min, $max) + ($cell ? 40_000 : 0)),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function network(array $c): array
    {
        $types = [
            ['TP-Link', 'Routeur Wi-Fi 6 Archer AX23', 'Archer AX23 Wi-Fi 6 router', 'Routeur Wi-Fi 6 AX1800', 'AX1800 Wi-Fi 6 router', 35_000, 55_000],
            ['TP-Link', 'Routeur Wi-Fi 6 Archer AX55', 'Archer AX55 Wi-Fi 6 router', 'Routeur Wi-Fi 6 AX3000', 'AX3000 Wi-Fi 6 router', 60_000, 90_000],
            ['TP-Link', 'Répéteur Wi-Fi RE305', 'RE305 Wi-Fi extender', 'Répéteur Wi-Fi AC1200', 'AC1200 Wi-Fi extender', 18_000, 30_000],
            ['TP-Link', 'Switch 8 ports Gigabit TL-SG108', 'TL-SG108 8-port Gigabit switch', 'Switch Gigabit non géré', 'Unmanaged Gigabit switch', 15_000, 28_000],
            ['TP-Link', 'Système Wi-Fi mesh Deco X20 (x2)', 'Deco X20 mesh Wi-Fi system (x2)', 'Wi-Fi mesh maison', 'Whole-home mesh Wi-Fi', 110_000, 160_000],
            ['Huawei', 'Routeur 4G+ B535', 'B535 4G+ router', 'Routeur 4G+ avec SIM', '4G+ router with SIM', 55_000, 90_000],
            ['Huawei', 'Clé 4G E3372', 'E3372 4G dongle', 'Clé USB 4G', 'USB 4G dongle', 18_000, 30_000],
            ['Ubiquiti', 'Point d\'accès UniFi U6+', 'UniFi U6+ access point', 'Point d\'accès Wi-Fi 6 PoE', 'PoE Wi-Fi 6 access point', 130_000, 190_000],
            ['MikroTik', 'Routeur hAP ax2', 'hAP ax2 router', 'Routeur professionnel Wi-Fi 6', 'Professional Wi-Fi 6 router', 85_000, 120_000],
            ['D-Link', 'Adaptateur CPL DHP-P601AV', 'DHP-P601AV powerline adapter', 'Kit CPL 1 000 Mbit/s', '1,000 Mbps powerline kit', 40_000, 65_000],
            ['Netgear', 'Routeur Nighthawk RAX50', 'Nighthawk RAX50 router', 'Routeur Wi-Fi 6 AX5400', 'AX5400 Wi-Fi 6 router', 140_000, 220_000],
            ['Starlink', 'Kit Starlink Standard', 'Starlink Standard kit', 'Internet par satellite haut débit', 'High-speed satellite internet', 420_000, 520_000],
        ];
        [$brand, $fr, $en, $descFr, $descEn, $min, $max] = $this->pick($types);

        return $this->item($brand, $fr, $en, [
            'description' => "{$descFr} {$brand}. Installation simple, application de gestion incluse.",
            'description_en' => "{$brand} {$descEn}. Easy setup, management app included.",
            'attributes' => ['category' => 'network'],
            'warranty' => 12,
            'price' => $this->priceBetween($min, $max),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function printer(array $c): array
    {
        $types = [
            ['HP', 'Imprimante laser LaserJet M111w', 'LaserJet M111w laser printer', 'Imprimante laser monochrome Wi-Fi', 'Wi-Fi monochrome laser printer', 90_000, 130_000],
            ['HP', 'Multifonction DeskJet 2820e', 'DeskJet 2820e all-in-one', 'Multifonction jet d\'encre', 'Inkjet all-in-one', 55_000, 85_000],
            ['HP', 'Multifonction LaserJet MFP M141w', 'LaserJet MFP M141w', 'Multifonction laser', 'Laser all-in-one', 150_000, 210_000],
            ['Epson', 'EcoTank L3250', 'EcoTank L3250', 'Multifonction à réservoirs rechargeables', 'Refillable-tank all-in-one', 130_000, 180_000],
            ['Epson', 'EcoTank L5290', 'EcoTank L5290', 'Multifonction Wi-Fi avec chargeur de documents', 'Wi-Fi all-in-one with document feeder', 190_000, 260_000],
            ['Canon', 'Pixma G3430', 'Pixma G3430', 'Multifonction à réservoirs', 'Refillable-tank all-in-one', 125_000, 170_000],
            ['Canon', 'i-SENSYS MF3010', 'i-SENSYS MF3010', 'Multifonction laser', 'Laser all-in-one', 140_000, 190_000],
            ['Brother', 'DCP-L2540DW', 'DCP-L2540DW', 'Multifonction laser recto-verso', 'Duplex laser all-in-one', 170_000, 240_000],
            ['Brother', 'HL-L2350DW', 'HL-L2350DW', 'Imprimante laser Wi-Fi', 'Wi-Fi laser printer', 100_000, 150_000],
            ['Epson', 'Scanner Perfection V39', 'Perfection V39 scanner', 'Scanner à plat', 'Flatbed scanner', 80_000, 120_000],
        ];
        [$brand, $fr, $en, $descFr, $descEn, $min, $max] = $this->pick($types);

        return $this->item($brand, $fr, $en, [
            'description' => "{$descFr} {$brand}. Idéale pour la maison, l'école et le petit bureau.",
            'description_en' => "{$brand} {$descEn}. Ideal for home, school and the small office.",
            'attributes' => ['category' => 'printer'],
            'warranty' => 12,
            'price' => $this->priceBetween($min, $max),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function storage(array $c): array
    {
        $kind = $this->pick(['ssd', 'ssd', 'hdd', 'usb', 'usb', 'sd']);

        if ($kind === 'ssd') {
            $brand = $this->pick(['Samsung', 'Kingston', 'Crucial', 'WD', 'SanDisk', 'Lexar']);
            $gb = $this->pick([250, 500, 1000, 2000, 4000]);
            $nvme = mt_rand(1, 100) <= 55;

            return $this->item($brand, "SSD ".($nvme ? 'NVMe M.2' : 'SATA 2,5"')." {$gb} Go", ($nvme ? 'NVMe M.2' : 'SATA 2.5"')." SSD {$gb} GB", [
                'description' => "Disque SSD {$brand} de {$gb} Go, format ".($nvme ? 'NVMe M.2' : 'SATA 2,5 pouces').'. Démarrage et transferts ultra rapides.',
                'description_en' => "{$brand} {$gb} GB SSD, ".($nvme ? 'NVMe M.2' : 'SATA 2.5-inch').' format. Very fast boot and transfers.',
                'attributes' => ['capacity_gb' => $gb, 'type' => 'SSD'],
                'warranty' => 36,
                'price' => $this->roundPrice(14_000 + $gb * 28 + ($nvme ? 6_000 : 0) + mt_rand(0, 8_000)),
            ]);
        }

        if ($kind === 'hdd') {
            $brand = $this->pick(['Seagate', 'WD', 'Toshiba']);
            $tb = $this->pick([1, 2, 4, 5, 8]);
            $ext = mt_rand(1, 100) <= 70;

            return $this->item($brand, ($ext ? 'Disque dur externe ' : 'Disque dur interne ')."{$tb} To", ($ext ? 'External' : 'Internal')." hard drive {$tb} TB", [
                'description' => "Disque dur ".($ext ? 'externe USB 3.0' : 'interne 3,5 pouces')." {$brand} de {$tb} To pour vos sauvegardes.",
                'description_en' => "{$brand} {$tb} TB ".($ext ? 'external USB 3.0' : 'internal 3.5-inch').' hard drive for your backups.',
                'attributes' => ['capacity_gb' => $tb * 1000, 'type' => 'HDD'],
                'warranty' => 24,
                'price' => $this->roundPrice(32_000 + $tb * 14_000 + ($ext ? 8_000 : 0) + mt_rand(0, 7_000)),
            ]);
        }

        if ($kind === 'usb') {
            $brand = $this->pick(['SanDisk', 'Kingston', 'Samsung', 'HP', 'Lexar']);
            $gb = $this->pick([16, 32, 64, 128, 256, 512]);

            return $this->item($brand, "Clé USB 3.2 {$gb} Go", "USB 3.2 flash drive {$gb} GB", [
                'description' => "Clé USB {$brand} de {$gb} Go, USB 3.2, compacte et rapide.",
                'description_en' => "{$brand} {$gb} GB USB 3.2 flash drive, compact and fast.",
                'attributes' => ['capacity_gb' => $gb, 'type' => 'USB'],
                'warranty' => 12,
                'price' => $this->roundPrice(3_500 + $gb * 85 + mt_rand(0, 2_000)),
            ]);
        }

        $brand = $this->pick(['SanDisk', 'Samsung', 'Kingston', 'Lexar']);
        $gb = $this->pick([32, 64, 128, 256, 512, 1000]);

        return $this->item($brand, "Carte microSD {$gb} Go classe A2", "microSD card {$gb} GB class A2", [
            'description' => "Carte mémoire microSD {$brand} de {$gb} Go avec adaptateur SD, pour smartphones, caméras et consoles.",
            'description_en' => "{$brand} {$gb} GB microSD memory card with SD adapter, for smartphones, cameras and consoles.",
            'attributes' => ['capacity_gb' => $gb, 'type' => 'microSD'],
            'warranty' => 24,
            'price' => $this->roundPrice(3_000 + $gb * 70 + mt_rand(0, 2_000)),
        ]);
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function accessory(array $c): array
    {
        $kind = $this->pick(['charger', 'cable', 'powerbank', 'case', 'ups', 'mouse', 'keyboard', 'hub']);
        $brand = $this->pick(['Anker', 'Baseus', 'Ugreen', 'Samsung', 'Apple', 'Belkin', 'Xiaomi', 'Oraimo', 'Logitech', 'APC']);
        [$colorFr, $colorEn] = $this->pick(self::COLORS);

        return match ($kind) {
            'charger' => (function () use ($brand) {
                $w = $this->pick([20, 25, 30, 45, 65, 100, 140]);

                return $this->item($brand, "Chargeur rapide USB-C {$w} W", "{$w} W USB-C fast charger", [
                    'description' => "Chargeur mural {$brand} {$w} W, USB-C Power Delivery, compatible smartphones, tablettes et ordinateurs portables.",
                    'description_en' => "{$brand} {$w} W wall charger, USB-C Power Delivery, works with smartphones, tablets and laptops.",
                    'attributes' => ['power_w' => $w],
                    'warranty' => 12,
                    'price' => $this->roundPrice(5_000 + $w * 420 + mt_rand(0, 4_000)),
                ]);
            })(),
            'cable' => (function () use ($brand) {
                $m = $this->pick([1, 1.5, 2, 3]);
                $type = $this->pick(['USB-C vers USB-C', 'USB-C vers Lightning', 'USB-A vers USB-C', 'HDMI 2.1', 'DisplayPort 1.4']);

                return $this->item($brand, "Câble {$type} {$m} m", "{$type} cable {$m} m", [
                    'description' => "Câble {$type} {$brand} de {$m} m, gaine tressée renforcée.",
                    'description_en' => "{$brand} {$type} cable, {$m} m, reinforced braided sheath.",
                    'attributes' => ['length_m' => $m],
                    'warranty' => 6,
                    'price' => $this->roundPrice(2_500 + (int) ($m * 1_800) + mt_rand(0, 3_000)),
                ]);
            })(),
            'powerbank' => (function () use ($brand, $colorFr, $colorEn) {
                $mah = $this->pick([10_000, 20_000, 30_000, 50_000]);

                return $this->item($brand, "Batterie externe {$mah} mAh {$colorFr}", "{$mah} mAh power bank {$colorEn}", [
                    'description' => "Batterie externe {$brand} {$mah} mAh avec charge rapide, deux sorties USB.",
                    'description_en' => "{$brand} {$mah} mAh power bank with fast charging and two USB outputs.",
                    'attributes' => ['capacity_mah' => $mah, 'color' => $colorFr],
                    'variant' => ['color' => $colorFr],
                    'warranty' => 12,
                    'price' => $this->roundPrice(6_000 + (int) ($mah * 1.1) + mt_rand(0, 6_000)),
                ]);
            })(),
            'case' => (function () use ($brand, $colorFr, $colorEn) {
                $model = $this->pick(['iPhone 15', 'iPhone 14', 'Galaxy S24', 'Galaxy A55', 'Redmi Note 13', 'Tecno Spark 20', 'Infinix Hot 40']);

                return $this->item($brand, "Coque de protection {$model} {$colorFr}", "{$model} protective case {$colorEn}", [
                    'description' => "Coque {$brand} pour {$model}, antichoc, bords surélevés pour l'écran et l'appareil photo.",
                    'description_en' => "{$brand} case for {$model}, shock-resistant, raised edges for the screen and camera.",
                    'attributes' => ['compatible' => $model, 'color' => $colorFr],
                    'variant' => ['color' => $colorFr],
                    'warranty' => 3,
                    'price' => $this->roundPrice(1_500 + mt_rand(0, 9_000)),
                ]);
            })(),
            'ups' => (function () {
                $va = $this->pick([650, 850, 1000, 1500, 2200, 3000]);

                return $this->item('APC', "Onduleur Back-UPS {$va} VA", "Back-UPS {$va} VA uninterruptible power supply", [
                    'description' => "Onduleur APC Back-UPS {$va} VA : protège ordinateurs et box internet des coupures et des surtensions.",
                    'description_en' => "APC Back-UPS {$va} VA: protects computers and internet boxes from power cuts and surges.",
                    'attributes' => ['power_va' => $va],
                    'warranty' => 24,
                    'price' => $this->roundPrice(38_000 + $va * 62 + mt_rand(0, 10_000)),
                ]);
            })(),
            'mouse' => $this->item($this->pick(['Logitech', 'HP', 'Microsoft', 'Razer']), 'Souris sans fil '.$colorFr, "Wireless mouse {$colorEn}", [
                'description' => 'Souris sans fil ergonomique, récepteur USB ou Bluetooth, pile longue durée.',
                'description_en' => 'Ergonomic wireless mouse, USB receiver or Bluetooth, long battery life.',
                'attributes' => ['color' => $colorFr],
                'variant' => ['color' => $colorFr],
                'warranty' => 12,
                'price' => $this->roundPrice(5_000 + mt_rand(0, 30_000)),
            ]),
            'keyboard' => $this->item($this->pick(['Logitech', 'HP', 'Microsoft', 'Keychron']), 'Clavier sans fil AZERTY '.$colorFr, "Wireless AZERTY keyboard {$colorEn}", [
                'description' => 'Clavier sans fil au format AZERTY français, touches silencieuses, autonomie de plusieurs mois.',
                'description_en' => 'Wireless keyboard in French AZERTY layout, quiet keys, months of battery life.',
                'attributes' => ['layout' => 'AZERTY', 'color' => $colorFr],
                'variant' => ['color' => $colorFr],
                'warranty' => 12,
                'price' => $this->roundPrice(8_000 + mt_rand(0, 55_000)),
            ]),
            default => (function () use ($brand) {
                $ports = $this->pick([4, 6, 7, 9, 11]);

                return $this->item($brand, "Hub USB-C {$ports} en 1", "{$ports}-in-1 USB-C hub", [
                    'description' => "Hub USB-C {$brand} {$ports} en 1 : HDMI 4K, USB 3.0, lecteur de cartes et charge Power Delivery.",
                    'description_en' => "{$brand} {$ports}-in-1 USB-C hub: 4K HDMI, USB 3.0, card reader and Power Delivery charging.",
                    'attributes' => ['ports' => $ports],
                    'warranty' => 12,
                    'price' => $this->roundPrice(9_000 + $ports * 4_500 + mt_rand(0, 8_000)),
                ]);
            })(),
        };
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function security(array $c): array
    {
        $types = [
            ['Hikvision', 'Caméra IP 4 MP vision nocturne', '4 MP night-vision IP camera', 'Caméra de surveillance IP PoE', 'PoE IP surveillance camera', 35_000, 80_000],
            ['Hikvision', 'Kit vidéosurveillance 4 caméras + enregistreur', '4-camera CCTV kit + recorder', 'Kit complet 4 caméras avec disque dur', 'Complete 4-camera kit with hard drive', 220_000, 420_000],
            ['Dahua', 'Caméra dôme 5 MP', '5 MP dome camera', 'Caméra dôme intérieure', 'Indoor dome camera', 38_000, 85_000],
            ['Ezviz', 'Caméra Wi-Fi C6N 2K', 'C6N 2K Wi-Fi camera', 'Caméra Wi-Fi rotative', 'Rotating Wi-Fi camera', 25_000, 45_000],
            ['Ezviz', 'Sonnette vidéo DB1C', 'DB1C video doorbell', 'Sonnette connectée avec caméra', 'Smart doorbell with camera', 60_000, 95_000],
            ['TP-Link', 'Caméra Tapo C200', 'Tapo C200 camera', 'Caméra Wi-Fi 1080p', '1080p Wi-Fi camera', 15_000, 28_000],
            ['TP-Link', 'Ampoule connectée Tapo L530E', 'Tapo L530E smart bulb', 'Ampoule LED Wi-Fi multicolore', 'Multicolour Wi-Fi LED bulb', 6_000, 12_000],
            ['Xiaomi', 'Caméra Mi 360° 2K', 'Mi 360° 2K camera', 'Caméra de sécurité 360°', '360° security camera', 22_000, 40_000],
            ['Yale', 'Serrure connectée Linus', 'Linus smart lock', 'Serrure électronique', 'Electronic lock', 120_000, 190_000],
            ['Ring', 'Alarme Alarm Pack 5 pièces', 'Alarm 5-piece pack', 'Système d\'alarme sans fil', 'Wireless alarm system', 180_000, 260_000],
        ];
        [$brand, $fr, $en, $descFr, $descEn, $min, $max] = $this->pick($types);

        return $this->item($brand, $fr, $en, [
            'description' => "{$descFr} {$brand} : protégez votre maison ou votre commerce, alertes sur smartphone.",
            'description_en' => "{$brand} {$descEn}: protect your home or shop, with smartphone alerts.",
            'attributes' => ['category' => 'security'],
            'warranty' => 12,
            'price' => $this->priceBetween($min, $max),
        ]);
    }
}

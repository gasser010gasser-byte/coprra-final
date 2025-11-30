<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏪 Seeding stores...');

        $stores = $this->getStoresData();
        $createdCount = 0;

        foreach ($stores as $storeData) {
            $store = Store::firstOrCreate(
                ['slug' => $storeData['slug']],
                $storeData
            );

            if ($store->wasRecentlyCreated) {
                ++$createdCount;
            }
        }

        $this->command->info("Stores seeded successfully! Created {$createdCount} new stores.");
    }

    /**
     * Get the stores data for seeding.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getStoresData(): array
    {
        return [
            // Main Store
            [
                'name' => 'COPRRA Main Store',
                'slug' => 'coprra-main-store',
                'description' => 'The main flagship store of COPRRA marketplace',
                'logo_url' => 'https://via.placeholder.com/200x100/007bff/ffffff?text=COPRRA+Main',
                'website_url' => 'https://www.coprra.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 100,
                'affiliate_base_url' => 'https://affiliate.coprra.com',
                'affiliate_code' => 'COPRRA_MAIN',
                'currency_id' => 1, // USD
            ],

            // Electronics Stores
            [
                'name' => 'TechWorld Electronics',
                'slug' => 'techworld-electronics',
                'description' => 'Your one-stop shop for all electronic devices and gadgets',
                'logo_url' => 'https://via.placeholder.com/200x100/28a745/ffffff?text=TechWorld',
                'website_url' => 'https://www.techworld.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 90,
                'affiliate_base_url' => 'https://affiliate.techworld.com',
                'affiliate_code' => 'TECHWORLD_ELEC',
                'currency_id' => 1, // USD
            ],
            [
                'name' => 'Digital Hub',
                'slug' => 'digital-hub',
                'description' => 'Premium digital devices and accessories',
                'logo_url' => 'https://via.placeholder.com/200x100/6f42c1/ffffff?text=Digital+Hub',
                'website_url' => 'https://www.digitalhub.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 85,
                'affiliate_base_url' => 'https://affiliate.digitalhub.com',
                'affiliate_code' => 'DIGITAL_HUB',
                'currency_id' => 1, // USD
            ],

            // Fashion Stores
            [
                'name' => 'Fashion Forward',
                'slug' => 'fashion-forward',
                'description' => 'Trendy fashion for modern lifestyle',
                'logo_url' => 'https://via.placeholder.com/200x100/e83e8c/ffffff?text=Fashion+Forward',
                'website_url' => 'https://www.fashionforward.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 80,
                'affiliate_base_url' => 'https://affiliate.fashionforward.com',
                'affiliate_code' => 'FASHION_FWD',
                'currency_id' => 1, // USD
            ],
            [
                'name' => 'Urban Style',
                'slug' => 'urban-style',
                'description' => 'Urban fashion and streetwear',
                'logo_url' => 'https://via.placeholder.com/200x100/343a40/ffffff?text=Urban+Style',
                'website_url' => 'https://www.urbanstyle.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 75,
                'affiliate_base_url' => 'https://affiliate.urbanstyle.com',
                'affiliate_code' => 'URBAN_STYLE',
                'currency_id' => 1, // USD
            ],

            // Home & Garden Stores
            [
                'name' => 'Home Essentials',
                'slug' => 'home-essentials',
                'description' => 'Everything you need for your home',
                'logo_url' => 'https://via.placeholder.com/200x100/fd7e14/ffffff?text=Home+Essentials',
                'website_url' => 'https://www.homeessentials.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 70,
                'affiliate_base_url' => 'https://affiliate.homeessentials.com',
                'affiliate_code' => 'HOME_ESS',
                'currency_id' => 1, // USD
            ],
            [
                'name' => 'Garden Paradise',
                'slug' => 'garden-paradise',
                'description' => 'Beautiful plants and garden supplies',
                'logo_url' => 'https://via.placeholder.com/200x100/20c997/ffffff?text=Garden+Paradise',
                'website_url' => 'https://www.gardenparadise.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 65,
                'affiliate_base_url' => 'https://affiliate.gardenparadise.com',
                'affiliate_code' => 'GARDEN_PAR',
                'currency_id' => 1, // USD
            ],

            // Sports & Fitness Stores
            [
                'name' => 'FitZone Sports',
                'slug' => 'fitzone-sports',
                'description' => 'Sports equipment and fitness gear',
                'logo_url' => 'https://via.placeholder.com/200x100/dc3545/ffffff?text=FitZone+Sports',
                'website_url' => 'https://www.fitzone.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 60,
                'affiliate_base_url' => 'https://affiliate.fitzone.com',
                'affiliate_code' => 'FITZONE_SPORTS',
                'currency_id' => 1, // USD
            ],

            // Books & Media Stores
            [
                'name' => 'BookWorld',
                'slug' => 'bookworld',
                'description' => 'Books, magazines, and digital media',
                'logo_url' => 'https://via.placeholder.com/200x100/17a2b8/ffffff?text=BookWorld',
                'website_url' => 'https://www.bookworld.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 55,
                'affiliate_base_url' => 'https://affiliate.bookworld.com',
                'affiliate_code' => 'BOOKWORLD',
                'currency_id' => 1, // USD
            ],

            // Beauty & Health Stores
            [
                'name' => 'Beauty Bliss',
                'slug' => 'beauty-bliss',
                'description' => 'Beauty products and cosmetics',
                'logo_url' => 'https://via.placeholder.com/200x100/f8d7da/000000?text=Beauty+Bliss',
                'website_url' => 'https://www.beautybliss.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 50,
                'affiliate_base_url' => 'https://affiliate.beautybliss.com',
                'affiliate_code' => 'BEAUTY_BLISS',
                'currency_id' => 1, // USD
            ],

            // Automotive Stores
            [
                'name' => 'Auto Parts Pro',
                'slug' => 'auto-parts-pro',
                'description' => 'Automotive parts and accessories',
                'logo_url' => 'https://via.placeholder.com/200x100/6c757d/ffffff?text=Auto+Parts+Pro',
                'website_url' => 'https://www.autopartspro.com',
                'country_code' => 'US',
                'is_active' => true,
                'priority' => 45,
                'affiliate_base_url' => 'https://affiliate.autopartspro.com',
                'affiliate_code' => 'AUTO_PARTS_PRO',
                'currency_id' => 1, // USD
            ],

            // Test/Demo Stores
            [
                'name' => 'Demo Store',
                'slug' => 'demo-store',
                'description' => 'Demo store for testing purposes',
                'logo_url' => 'https://via.placeholder.com/200x100/ffc107/000000?text=Demo+Store',
                'website_url' => 'https://demo.coprra.com',
                'country_code' => 'US',
                'is_active' => false,
                'priority' => 0,
                'affiliate_base_url' => 'https://demo.affiliate.coprra.com',
                'affiliate_code' => 'DEMO_STORE',
                'currency_id' => 1, // USD
            ],
        ];
    }
}

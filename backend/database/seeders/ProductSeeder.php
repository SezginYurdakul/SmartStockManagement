<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get default company
        $company = Company::first();
        $companyId = $company?->id;

        // Only get subcategories (categories with parent_id)
        $categories = Category::whereNotNull('parent_id')->get();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found! Please run CategorySeeder first.');
            return;
        }

        // Product templates for each category
        $productTemplates = [
            'electronics' => [
                ['name' => 'iPad Pro 12.9"', 'price' => 1099, 'brand' => 'Apple'],
                ['name' => 'Samsung Galaxy Tab S9', 'price' => 899, 'brand' => 'Samsung'],
                ['name' => 'Kindle Paperwhite', 'price' => 149, 'brand' => 'Amazon'],
                ['name' => 'GoPro Hero 12', 'price' => 399, 'brand' => 'GoPro'],
                ['name' => 'DJI Mini 4 Pro', 'price' => 759, 'brand' => 'DJI'],
                ['name' => 'Bose SoundLink Revolve', 'price' => 219, 'brand' => 'Bose'],
                ['name' => 'Anker PowerCore 26800', 'price' => 65, 'brand' => 'Anker'],
                ['name' => 'Belkin 3-in-1 Charger', 'price' => 149, 'brand' => 'Belkin'],
                ['name' => 'SanDisk 1TB SSD', 'price' => 129, 'brand' => 'SanDisk'],
                ['name' => 'Logitech Webcam C920', 'price' => 79, 'brand' => 'Logitech'],
            ],
            'tablets' => [
                ['name' => 'iPad Air', 'price' => 599, 'brand' => 'Apple'],
                ['name' => 'Galaxy Tab S8', 'price' => 699, 'brand' => 'Samsung'],
                ['name' => 'Surface Pro 9', 'price' => 999, 'brand' => 'Microsoft'],
                ['name' => 'iPad Mini', 'price' => 499, 'brand' => 'Apple'],
                ['name' => 'Lenovo Tab P11', 'price' => 299, 'brand' => 'Lenovo'],
                ['name' => 'Amazon Fire HD 10', 'price' => 149, 'brand' => 'Amazon'],
                ['name' => 'Huawei MatePad', 'price' => 399, 'brand' => 'Huawei'],
                ['name' => 'Xiaomi Pad 6', 'price' => 349, 'brand' => 'Xiaomi'],
                ['name' => 'OnePlus Pad', 'price' => 479, 'brand' => 'OnePlus'],
                ['name' => 'ASUS ZenPad', 'price' => 279, 'brand' => 'ASUS'],
            ],
            'accessories' => [
                ['name' => 'USB-C Hub', 'price' => 49, 'brand' => 'Anker'],
                ['name' => 'Wireless Charger', 'price' => 35, 'brand' => 'Belkin'],
                ['name' => 'Phone Stand', 'price' => 25, 'brand' => 'Lamicall'],
                ['name' => 'Cable Organizer', 'price' => 15, 'brand' => 'JOTO'],
                ['name' => 'Screen Protector', 'price' => 12, 'brand' => 'Spigen'],
                ['name' => 'Stylus Pen', 'price' => 29, 'brand' => 'Adonit'],
                ['name' => 'Laptop Sleeve', 'price' => 35, 'brand' => 'Tomtoc'],
                ['name' => 'Cable Pack', 'price' => 22, 'brand' => 'Anker'],
                ['name' => 'Webcam Cover', 'price' => 8, 'brand' => 'CloudValley'],
                ['name' => 'Cleaning Kit', 'price' => 18, 'brand' => 'OXO'],
            ],
            'computers-laptops' => [
                ['name' => 'MacBook Pro 16" M3 Max', 'price' => 3499, 'brand' => 'Apple'],
                ['name' => 'Dell XPS 15', 'price' => 1899, 'brand' => 'Dell'],
                ['name' => 'HP Spectre x360', 'price' => 1599, 'brand' => 'HP'],
                ['name' => 'Lenovo ThinkPad X1', 'price' => 1799, 'brand' => 'Lenovo'],
                ['name' => 'ASUS ROG Zephyrus', 'price' => 2299, 'brand' => 'ASUS'],
                ['name' => 'Microsoft Surface Laptop', 'price' => 1299, 'brand' => 'Microsoft'],
                ['name' => 'Acer Predator Helios', 'price' => 1699, 'brand' => 'Acer'],
                ['name' => 'MSI Creator Z16', 'price' => 2199, 'brand' => 'MSI'],
                ['name' => 'Razer Blade 15', 'price' => 2499, 'brand' => 'Razer'],
                ['name' => 'Samsung Galaxy Book', 'price' => 1399, 'brand' => 'Samsung'],
            ],
            'laptops' => [
                ['name' => 'MacBook Air M2', 'price' => 1199, 'brand' => 'Apple'],
                ['name' => 'Dell Inspiron 15', 'price' => 799, 'brand' => 'Dell'],
                ['name' => 'HP Pavilion 14', 'price' => 699, 'brand' => 'HP'],
                ['name' => 'Lenovo IdeaPad', 'price' => 649, 'brand' => 'Lenovo'],
                ['name' => 'ASUS VivoBook', 'price' => 599, 'brand' => 'ASUS'],
                ['name' => 'Acer Swift 3', 'price' => 749, 'brand' => 'Acer'],
                ['name' => 'MSI Modern 14', 'price' => 899, 'brand' => 'MSI'],
                ['name' => 'LG Gram 17', 'price' => 1599, 'brand' => 'LG'],
                ['name' => 'Huawei MateBook', 'price' => 999, 'brand' => 'Huawei'],
                ['name' => 'Microsoft Surface Go', 'price' => 549, 'brand' => 'Microsoft'],
            ],
            'desktops' => [
                ['name' => 'iMac 24" M3', 'price' => 1499, 'brand' => 'Apple'],
                ['name' => 'Dell OptiPlex', 'price' => 899, 'brand' => 'Dell'],
                ['name' => 'HP Pavilion Desktop', 'price' => 749, 'brand' => 'HP'],
                ['name' => 'Lenovo ThinkCentre', 'price' => 799, 'brand' => 'Lenovo'],
                ['name' => 'ASUS ROG Strix', 'price' => 1899, 'brand' => 'ASUS'],
                ['name' => 'Acer Aspire TC', 'price' => 649, 'brand' => 'Acer'],
                ['name' => 'MSI Aegis', 'price' => 1699, 'brand' => 'MSI'],
                ['name' => 'Alienware Aurora', 'price' => 2499, 'brand' => 'Dell'],
                ['name' => 'HP OMEN 45L', 'price' => 1999, 'brand' => 'HP'],
                ['name' => 'Corsair Vengeance', 'price' => 1799, 'brand' => 'Corsair'],
            ],
            'computer-accessories' => [
                ['name' => 'Magic Mouse', 'price' => 79, 'brand' => 'Apple'],
                ['name' => 'MX Keys Keyboard', 'price' => 99, 'brand' => 'Logitech'],
                ['name' => 'USB-C Dock', 'price' => 199, 'brand' => 'CalDigit'],
                ['name' => 'Webcam Pro', 'price' => 129, 'brand' => 'Logitech'],
                ['name' => 'Monitor Arm', 'price' => 149, 'brand' => 'Ergotron'],
                ['name' => 'Keyboard Tray', 'price' => 59, 'brand' => '3M'],
                ['name' => 'Mouse Pad RGB', 'price' => 39, 'brand' => 'Razer'],
                ['name' => 'USB Hub 7-Port', 'price' => 45, 'brand' => 'Anker'],
                ['name' => 'Cable Management', 'price' => 25, 'brand' => 'BlueLounge'],
                ['name' => 'Laptop Stand', 'price' => 49, 'brand' => 'Rain Design'],
            ],
            'mobile-phones' => [
                ['name' => 'iPhone 15 Pro Max', 'price' => 1199, 'brand' => 'Apple'],
                ['name' => 'Samsung Galaxy S24 Ultra', 'price' => 1299, 'brand' => 'Samsung'],
                ['name' => 'Google Pixel 8 Pro', 'price' => 999, 'brand' => 'Google'],
                ['name' => 'OnePlus 12 Pro', 'price' => 899, 'brand' => 'OnePlus'],
                ['name' => 'Xiaomi 14 Ultra', 'price' => 1099, 'brand' => 'Xiaomi'],
                ['name' => 'OPPO Find X7', 'price' => 849, 'brand' => 'OPPO'],
                ['name' => 'Vivo X100 Pro', 'price' => 799, 'brand' => 'Vivo'],
                ['name' => 'Motorola Edge 50', 'price' => 699, 'brand' => 'Motorola'],
                ['name' => 'Sony Xperia 1 VI', 'price' => 1199, 'brand' => 'Sony'],
                ['name' => 'ASUS ROG Phone 8', 'price' => 1099, 'brand' => 'ASUS'],
            ],
            'smartphones' => [
                ['name' => 'iPhone 15', 'price' => 999, 'brand' => 'Apple'],
                ['name' => 'Galaxy S24', 'price' => 899, 'brand' => 'Samsung'],
                ['name' => 'Pixel 8', 'price' => 699, 'brand' => 'Google'],
                ['name' => 'OnePlus 12', 'price' => 799, 'brand' => 'OnePlus'],
                ['name' => 'Xiaomi 14', 'price' => 699, 'brand' => 'Xiaomi'],
                ['name' => 'Nothing Phone 2', 'price' => 599, 'brand' => 'Nothing'],
                ['name' => 'Realme GT 5', 'price' => 549, 'brand' => 'Realme'],
                ['name' => 'OPPO Reno 11', 'price' => 499, 'brand' => 'OPPO'],
                ['name' => 'Vivo V30', 'price' => 449, 'brand' => 'Vivo'],
                ['name' => 'Honor Magic 6', 'price' => 699, 'brand' => 'Honor'],
            ],
            'mobile-accessories' => [
                ['name' => 'Phone Case Pro', 'price' => 49, 'brand' => 'Spigen'],
                ['name' => 'Fast Charger 65W', 'price' => 39, 'brand' => 'Anker'],
                ['name' => 'Screen Protector Glass', 'price' => 19, 'brand' => 'Spigen'],
                ['name' => 'Car Phone Mount', 'price' => 29, 'brand' => 'iOttie'],
                ['name' => 'PopSocket Grip', 'price' => 15, 'brand' => 'PopSockets'],
                ['name' => 'Wireless Car Charger', 'price' => 45, 'brand' => 'Belkin'],
                ['name' => 'Phone Ring Holder', 'price' => 12, 'brand' => 'Ringke'],
                ['name' => 'USB-C Cable 6ft', 'price' => 18, 'brand' => 'Anker'],
                ['name' => 'Selfie Stick', 'price' => 25, 'brand' => 'Mpow'],
                ['name' => 'Phone Armband', 'price' => 20, 'brand' => 'Tribe'],
            ],
            'audio-headphones' => [
                ['name' => 'Sony WH-1000XM5', 'price' => 399, 'brand' => 'Sony'],
                ['name' => 'Bose QuietComfort Ultra', 'price' => 429, 'brand' => 'Bose'],
                ['name' => 'Apple AirPods Max', 'price' => 549, 'brand' => 'Apple'],
                ['name' => 'Sennheiser Momentum 4', 'price' => 379, 'brand' => 'Sennheiser'],
                ['name' => 'JBL Live 660NC', 'price' => 199, 'brand' => 'JBL'],
                ['name' => 'Audio-Technica ATH-M50x', 'price' => 149, 'brand' => 'Audio-Technica'],
                ['name' => 'Beats Studio Pro', 'price' => 349, 'brand' => 'Beats'],
                ['name' => 'Jabra Elite 85h', 'price' => 299, 'brand' => 'Jabra'],
                ['name' => 'AKG K371', 'price' => 149, 'brand' => 'AKG'],
                ['name' => 'Shure AONIC 50', 'price' => 299, 'brand' => 'Shure'],
            ],
            'headphones' => [
                ['name' => 'AirPods Pro 2', 'price' => 249, 'brand' => 'Apple'],
                ['name' => 'Sony WF-1000XM5', 'price' => 299, 'brand' => 'Sony'],
                ['name' => 'Bose QC Earbuds II', 'price' => 279, 'brand' => 'Bose'],
                ['name' => 'Samsung Galaxy Buds2 Pro', 'price' => 229, 'brand' => 'Samsung'],
                ['name' => 'Sennheiser IE 300', 'price' => 299, 'brand' => 'Sennheiser'],
                ['name' => 'Beats Fit Pro', 'price' => 199, 'brand' => 'Beats'],
                ['name' => 'Jabra Elite 10', 'price' => 249, 'brand' => 'Jabra'],
                ['name' => 'Anker Soundcore Liberty 4', 'price' => 149, 'brand' => 'Anker'],
                ['name' => 'JBL Tour Pro 2', 'price' => 249, 'brand' => 'JBL'],
                ['name' => 'Nothing Ear 2', 'price' => 149, 'brand' => 'Nothing'],
            ],
            'speakers' => [
                ['name' => 'HomePod mini', 'price' => 99, 'brand' => 'Apple'],
                ['name' => 'Sonos One', 'price' => 219, 'brand' => 'Sonos'],
                ['name' => 'JBL Flip 6', 'price' => 129, 'brand' => 'JBL'],
                ['name' => 'Bose SoundLink Mini', 'price' => 199, 'brand' => 'Bose'],
                ['name' => 'UE Boom 3', 'price' => 149, 'brand' => 'Ultimate Ears'],
                ['name' => 'Marshall Emberton II', 'price' => 169, 'brand' => 'Marshall'],
                ['name' => 'Sony SRS-XB43', 'price' => 249, 'brand' => 'Sony'],
                ['name' => 'Anker Soundcore 3', 'price' => 79, 'brand' => 'Anker'],
                ['name' => 'Bang & Olufsen Beosound A1', 'price' => 299, 'brand' => 'B&O'],
                ['name' => 'Harman Kardon Onyx Studio', 'price' => 449, 'brand' => 'Harman Kardon'],
            ],
            'cameras-photography' => [
                ['name' => 'Canon EOS R5', 'price' => 3899, 'brand' => 'Canon'],
                ['name' => 'Sony A7R V', 'price' => 3899, 'brand' => 'Sony'],
                ['name' => 'Nikon Z9', 'price' => 5499, 'brand' => 'Nikon'],
                ['name' => 'Fujifilm X-T5', 'price' => 1699, 'brand' => 'Fujifilm'],
                ['name' => 'Panasonic Lumix GH6', 'price' => 2199, 'brand' => 'Panasonic'],
                ['name' => 'Olympus OM-1', 'price' => 2199, 'brand' => 'Olympus'],
                ['name' => 'Leica Q3', 'price' => 5995, 'brand' => 'Leica'],
                ['name' => 'Hasselblad X2D', 'price' => 8199, 'brand' => 'Hasselblad'],
                ['name' => 'Phase One XF', 'price' => 15990, 'brand' => 'Phase One'],
                ['name' => 'DJI Ronin 4D', 'price' => 6499, 'brand' => 'DJI'],
            ],
            'dslr-cameras' => [
                ['name' => 'Canon EOS 5D Mark IV', 'price' => 2499, 'brand' => 'Canon'],
                ['name' => 'Nikon D850', 'price' => 2999, 'brand' => 'Nikon'],
                ['name' => 'Canon EOS 90D', 'price' => 1199, 'brand' => 'Canon'],
                ['name' => 'Nikon D780', 'price' => 2299, 'brand' => 'Nikon'],
                ['name' => 'Canon EOS Rebel T7i', 'price' => 749, 'brand' => 'Canon'],
                ['name' => 'Nikon D7500', 'price' => 1249, 'brand' => 'Nikon'],
                ['name' => 'Canon EOS 6D Mark II', 'price' => 1399, 'brand' => 'Canon'],
                ['name' => 'Pentax K-1 Mark II', 'price' => 1799, 'brand' => 'Pentax'],
                ['name' => 'Nikon D500', 'price' => 1499, 'brand' => 'Nikon'],
                ['name' => 'Canon EOS 80D', 'price' => 999, 'brand' => 'Canon'],
            ],
            'mirrorless-cameras' => [
                ['name' => 'Sony A7 IV', 'price' => 2499, 'brand' => 'Sony'],
                ['name' => 'Canon EOS R6 Mark II', 'price' => 2499, 'brand' => 'Canon'],
                ['name' => 'Nikon Z8', 'price' => 3999, 'brand' => 'Nikon'],
                ['name' => 'Fujifilm X-H2S', 'price' => 2499, 'brand' => 'Fujifilm'],
                ['name' => 'Sony A6700', 'price' => 1399, 'brand' => 'Sony'],
                ['name' => 'Canon EOS R8', 'price' => 1499, 'brand' => 'Canon'],
                ['name' => 'Nikon Z6 III', 'price' => 2499, 'brand' => 'Nikon'],
                ['name' => 'Panasonic S5 II', 'price' => 1999, 'brand' => 'Panasonic'],
                ['name' => 'Olympus OM-5', 'price' => 1199, 'brand' => 'Olympus'],
                ['name' => 'Fujifilm X-S20', 'price' => 1299, 'brand' => 'Fujifilm'],
            ],
            'lenses' => [
                ['name' => '50mm f/1.8 Prime', 'price' => 199, 'brand' => 'Canon'],
                ['name' => '24-70mm f/2.8 Zoom', 'price' => 1799, 'brand' => 'Sony'],
                ['name' => '70-200mm f/2.8 Telephoto', 'price' => 2599, 'brand' => 'Nikon'],
                ['name' => '85mm f/1.4 Portrait', 'price' => 1599, 'brand' => 'Sigma'],
                ['name' => '16-35mm f/4 Wide', 'price' => 1099, 'brand' => 'Canon'],
                ['name' => '100-400mm f/5.6 Super Tele', 'price' => 1899, 'brand' => 'Sony'],
                ['name' => '35mm f/1.4 Art', 'price' => 899, 'brand' => 'Sigma'],
                ['name' => '24mm f/1.4 Wide Prime', 'price' => 849, 'brand' => 'Tamron'],
                ['name' => '90mm f/2.8 Macro', 'price' => 649, 'brand' => 'Tamron'],
                ['name' => '18-135mm f/3.5-5.6 Kit', 'price' => 599, 'brand' => 'Canon'],
            ],
            'gaming' => [
                ['name' => 'PlayStation 5', 'price' => 499, 'brand' => 'Sony'],
                ['name' => 'Xbox Series X', 'price' => 499, 'brand' => 'Microsoft'],
                ['name' => 'Nintendo Switch OLED', 'price' => 349, 'brand' => 'Nintendo'],
                ['name' => 'Steam Deck', 'price' => 649, 'brand' => 'Valve'],
                ['name' => 'ASUS ROG Ally', 'price' => 699, 'brand' => 'ASUS'],
                ['name' => 'Lenovo Legion Go', 'price' => 749, 'brand' => 'Lenovo'],
                ['name' => 'Meta Quest 3', 'price' => 499, 'brand' => 'Meta'],
                ['name' => 'PlayStation VR2', 'price' => 549, 'brand' => 'Sony'],
                ['name' => 'Logitech G Pro X', 'price' => 129, 'brand' => 'Logitech'],
                ['name' => 'Razer DeathAdder V3', 'price' => 69, 'brand' => 'Razer'],
            ],
            'gaming-consoles' => [
                ['name' => 'PS5 Digital Edition', 'price' => 449, 'brand' => 'Sony'],
                ['name' => 'Xbox Series S', 'price' => 299, 'brand' => 'Microsoft'],
                ['name' => 'Nintendo Switch Lite', 'price' => 199, 'brand' => 'Nintendo'],
                ['name' => 'Steam Deck OLED', 'price' => 549, 'brand' => 'Valve'],
                ['name' => 'ASUS ROG Ally Z1', 'price' => 599, 'brand' => 'ASUS'],
                ['name' => 'Retro Gaming Console', 'price' => 99, 'brand' => 'Anbernic'],
                ['name' => 'Sega Genesis Mini', 'price' => 79, 'brand' => 'Sega'],
                ['name' => 'PlayStation Classic', 'price' => 99, 'brand' => 'Sony'],
                ['name' => 'NES Classic Edition', 'price' => 59, 'brand' => 'Nintendo'],
                ['name' => 'Atari VCS', 'price' => 299, 'brand' => 'Atari'],
            ],
            'gaming-accessories' => [
                ['name' => 'DualSense Controller', 'price' => 69, 'brand' => 'Sony'],
                ['name' => 'Xbox Elite Controller', 'price' => 179, 'brand' => 'Microsoft'],
                ['name' => 'Pro Controller', 'price' => 69, 'brand' => 'Nintendo'],
                ['name' => 'Gaming Headset Wireless', 'price' => 149, 'brand' => 'SteelSeries'],
                ['name' => 'Racing Wheel', 'price' => 399, 'brand' => 'Logitech'],
                ['name' => 'Fight Stick Arcade', 'price' => 199, 'brand' => 'Razer'],
                ['name' => 'Gaming Keyboard RGB', 'price' => 129, 'brand' => 'Corsair'],
                ['name' => 'Gaming Mouse Pro', 'price' => 79, 'brand' => 'Razer'],
                ['name' => 'Controller Charging Dock', 'price' => 29, 'brand' => 'PowerA'],
                ['name' => 'Gaming Chair', 'price' => 349, 'brand' => 'Secretlab'],
            ],
            'wearables' => [
                ['name' => 'Apple Watch Series 9', 'price' => 429, 'brand' => 'Apple'],
                ['name' => 'Samsung Galaxy Watch 6', 'price' => 349, 'brand' => 'Samsung'],
                ['name' => 'Garmin Fenix 7', 'price' => 699, 'brand' => 'Garmin'],
                ['name' => 'Fitbit Sense 2', 'price' => 299, 'brand' => 'Fitbit'],
                ['name' => 'Amazfit GTR 4', 'price' => 199, 'brand' => 'Amazfit'],
                ['name' => 'Huawei Watch GT 4', 'price' => 249, 'brand' => 'Huawei'],
                ['name' => 'Polar Vantage V3', 'price' => 599, 'brand' => 'Polar'],
                ['name' => 'Withings ScanWatch 2', 'price' => 349, 'brand' => 'Withings'],
                ['name' => 'Coros Pace 3', 'price' => 229, 'brand' => 'Coros'],
                ['name' => 'Suunto 9 Peak Pro', 'price' => 569, 'brand' => 'Suunto'],
            ],
            'smartwatches' => [
                ['name' => 'Apple Watch SE', 'price' => 249, 'brand' => 'Apple'],
                ['name' => 'Galaxy Watch 5', 'price' => 279, 'brand' => 'Samsung'],
                ['name' => 'Pixel Watch 2', 'price' => 349, 'brand' => 'Google'],
                ['name' => 'TicWatch Pro 5', 'price' => 349, 'brand' => 'Mobvoi'],
                ['name' => 'Garmin Venu 3', 'price' => 449, 'brand' => 'Garmin'],
                ['name' => 'Fossil Gen 6', 'price' => 299, 'brand' => 'Fossil'],
                ['name' => 'Amazfit GTR 3 Pro', 'price' => 229, 'brand' => 'Amazfit'],
                ['name' => 'OnePlus Watch 2', 'price' => 299, 'brand' => 'OnePlus'],
                ['name' => 'Huawei Watch GT 3', 'price' => 229, 'brand' => 'Huawei'],
                ['name' => 'Withings Steel HR', 'price' => 199, 'brand' => 'Withings'],
            ],
            'fitness-trackers' => [
                ['name' => 'Fitbit Charge 6', 'price' => 159, 'brand' => 'Fitbit'],
                ['name' => 'Garmin Vivosmart 5', 'price' => 149, 'brand' => 'Garmin'],
                ['name' => 'Xiaomi Mi Band 8', 'price' => 49, 'brand' => 'Xiaomi'],
                ['name' => 'Whoop 4.0', 'price' => 239, 'brand' => 'Whoop'],
                ['name' => 'Amazfit Band 7', 'price' => 49, 'brand' => 'Amazfit'],
                ['name' => 'Polar Ignite 3', 'price' => 329, 'brand' => 'Polar'],
                ['name' => 'Oura Ring Gen3', 'price' => 299, 'brand' => 'Oura'],
                ['name' => 'Garmin Vivoactive 5', 'price' => 299, 'brand' => 'Garmin'],
                ['name' => 'Fitbit Inspire 3', 'price' => 99, 'brand' => 'Fitbit'],
                ['name' => 'Coros Pace 2', 'price' => 199, 'brand' => 'Coros'],
            ],
            'home-appliances' => [
                ['name' => 'LG C3 OLED 65"', 'price' => 1799, 'brand' => 'LG'],
                ['name' => 'Samsung QN90C QLED', 'price' => 1999, 'brand' => 'Samsung'],
                ['name' => 'Sony Bravia XR A95L', 'price' => 3499, 'brand' => 'Sony'],
                ['name' => 'Dyson V15 Detect', 'price' => 749, 'brand' => 'Dyson'],
                ['name' => 'iRobot Roomba j9+', 'price' => 899, 'brand' => 'iRobot'],
                ['name' => 'Philips Air Fryer XXL', 'price' => 299, 'brand' => 'Philips'],
                ['name' => 'Ninja Foodi Max', 'price' => 249, 'brand' => 'Ninja'],
                ['name' => 'KitchenAid Stand Mixer', 'price' => 449, 'brand' => 'KitchenAid'],
                ['name' => 'Nespresso Vertuo Next', 'price' => 179, 'brand' => 'Nespresso'],
                ['name' => 'Breville Barista Express', 'price' => 699, 'brand' => 'Breville'],
            ],
            'kitchen-appliances' => [
                ['name' => 'Instant Pot Duo Plus', 'price' => 119, 'brand' => 'Instant Pot'],
                ['name' => 'Vitamix E310', 'price' => 349, 'brand' => 'Vitamix'],
                ['name' => 'Cuisinart Food Processor', 'price' => 199, 'brand' => 'Cuisinart'],
                ['name' => 'Breville Smart Oven', 'price' => 299, 'brand' => 'Breville'],
                ['name' => 'Ninja Professional Blender', 'price' => 99, 'brand' => 'Ninja'],
                ['name' => 'KitchenAid Hand Mixer', 'price' => 79, 'brand' => 'KitchenAid'],
                ['name' => 'Hamilton Beach Slow Cooker', 'price' => 49, 'brand' => 'Hamilton Beach'],
                ['name' => 'Breville Espresso Machine', 'price' => 499, 'brand' => 'Breville'],
                ['name' => 'Oster Toaster', 'price' => 39, 'brand' => 'Oster'],
                ['name' => 'Black+Decker Rice Cooker', 'price' => 29, 'brand' => 'Black+Decker'],
            ],
            'cleaning-appliances' => [
                ['name' => 'Dyson V11 Animal', 'price' => 599, 'brand' => 'Dyson'],
                ['name' => 'Shark Navigator', 'price' => 199, 'brand' => 'Shark'],
                ['name' => 'iRobot Roomba i7+', 'price' => 799, 'brand' => 'iRobot'],
                ['name' => 'Bissell CrossWave', 'price' => 299, 'brand' => 'Bissell'],
                ['name' => 'Eufy RoboVac', 'price' => 249, 'brand' => 'Eufy'],
                ['name' => 'Hoover WindTunnel', 'price' => 149, 'brand' => 'Hoover'],
                ['name' => 'Roborock S7', 'price' => 649, 'brand' => 'Roborock'],
                ['name' => 'Tineco Floor One', 'price' => 499, 'brand' => 'Tineco'],
                ['name' => 'Miele Complete C3', 'price' => 999, 'brand' => 'Miele'],
                ['name' => 'Ecovacs Deebot', 'price' => 399, 'brand' => 'Ecovacs'],
            ],
            'smart-home' => [
                ['name' => 'Google Nest Hub Max', 'price' => 229, 'brand' => 'Google'],
                ['name' => 'Amazon Echo Show 15', 'price' => 279, 'brand' => 'Amazon'],
                ['name' => 'Apple HomePod', 'price' => 299, 'brand' => 'Apple'],
                ['name' => 'Ring Video Doorbell Pro', 'price' => 249, 'brand' => 'Ring'],
                ['name' => 'Arlo Pro 5', 'price' => 249, 'brand' => 'Arlo'],
                ['name' => 'Philips Hue Starter Kit', 'price' => 199, 'brand' => 'Philips'],
                ['name' => 'Ecobee SmartThermostat', 'price' => 249, 'brand' => 'Ecobee'],
                ['name' => 'August Smart Lock Pro', 'price' => 279, 'brand' => 'August'],
                ['name' => 'Sonos Beam Gen 2', 'price' => 499, 'brand' => 'Sonos'],
                ['name' => 'TP-Link Tapo C200', 'price' => 39, 'brand' => 'TP-Link'],
            ],
            'smart-speakers' => [
                ['name' => 'Echo Dot 5th Gen', 'price' => 49, 'brand' => 'Amazon'],
                ['name' => 'Google Nest Mini', 'price' => 49, 'brand' => 'Google'],
                ['name' => 'Echo Studio', 'price' => 199, 'brand' => 'Amazon'],
                ['name' => 'Google Nest Audio', 'price' => 99, 'brand' => 'Google'],
                ['name' => 'Sonos One SL', 'price' => 179, 'brand' => 'Sonos'],
                ['name' => 'Apple HomePod mini', 'price' => 99, 'brand' => 'Apple'],
                ['name' => 'Amazon Echo Show 8', 'price' => 129, 'brand' => 'Amazon'],
                ['name' => 'Google Nest Hub', 'price' => 99, 'brand' => 'Google'],
                ['name' => 'Bose Home Speaker 500', 'price' => 399, 'brand' => 'Bose'],
                ['name' => 'Sonos Roam', 'price' => 179, 'brand' => 'Sonos'],
            ],
            'security-cameras' => [
                ['name' => 'Wyze Cam v3', 'price' => 35, 'brand' => 'Wyze'],
                ['name' => 'Arlo Pro 4', 'price' => 199, 'brand' => 'Arlo'],
                ['name' => 'Ring Stick Up Cam', 'price' => 99, 'brand' => 'Ring'],
                ['name' => 'Google Nest Cam', 'price' => 179, 'brand' => 'Google'],
                ['name' => 'Blink Outdoor', 'price' => 99, 'brand' => 'Blink'],
                ['name' => 'Eufy Solo IndoorCam', 'price' => 39, 'brand' => 'Eufy'],
                ['name' => 'Reolink Argus 3 Pro', 'price' => 129, 'brand' => 'Reolink'],
                ['name' => 'TP-Link Kasa Spot', 'price' => 39, 'brand' => 'TP-Link'],
                ['name' => 'Logitech Circle View', 'price' => 159, 'brand' => 'Logitech'],
                ['name' => 'Arlo Essential', 'price' => 129, 'brand' => 'Arlo'],
            ],
            'office-supplies' => [
                ['name' => 'Logitech MX Master 3S', 'price' => 99, 'brand' => 'Logitech'],
                ['name' => 'Apple Magic Keyboard', 'price' => 149, 'brand' => 'Apple'],
                ['name' => 'Herman Miller Aeron', 'price' => 1495, 'brand' => 'Herman Miller'],
                ['name' => 'Steelcase Leap', 'price' => 1099, 'brand' => 'Steelcase'],
                ['name' => 'Uplift V2 Desk', 'price' => 799, 'brand' => 'Uplift'],
                ['name' => 'Epson EcoTank ET-2850', 'price' => 349, 'brand' => 'Epson'],
                ['name' => 'Brother HL-L2395DW', 'price' => 149, 'brand' => 'Brother'],
                ['name' => 'Fellowes Powershred', 'price' => 179, 'brand' => 'Fellowes'],
                ['name' => 'AmazonBasics Monitor Arm', 'price' => 119, 'brand' => 'AmazonBasics'],
                ['name' => 'Anker PowerExpand', 'price' => 89, 'brand' => 'Anker'],
            ],
            'office-furniture' => [
                ['name' => 'Herman Miller Embody', 'price' => 1795, 'brand' => 'Herman Miller'],
                ['name' => 'Steelcase Gesture', 'price' => 1149, 'brand' => 'Steelcase'],
                ['name' => 'IKEA Markus', 'price' => 199, 'brand' => 'IKEA'],
                ['name' => 'FlexiSpot Standing Desk', 'price' => 449, 'brand' => 'FlexiSpot'],
                ['name' => 'Branch Ergonomic Chair', 'price' => 349, 'brand' => 'Branch'],
                ['name' => 'VIVO Dual Monitor Desk Mount', 'price' => 34, 'brand' => 'VIVO'],
                ['name' => 'Autonomous SmartDesk', 'price' => 499, 'brand' => 'Autonomous'],
                ['name' => 'HON Ignition 2.0', 'price' => 399, 'brand' => 'HON'],
                ['name' => 'IKEA Bekant', 'price' => 249, 'brand' => 'IKEA'],
                ['name' => 'Secretlab Titan Evo', 'price' => 499, 'brand' => 'Secretlab'],
            ],
            'printers-scanners' => [
                ['name' => 'HP LaserJet Pro', 'price' => 199, 'brand' => 'HP'],
                ['name' => 'Canon PIXMA TR8620', 'price' => 179, 'brand' => 'Canon'],
                ['name' => 'Epson WorkForce', 'price' => 149, 'brand' => 'Epson'],
                ['name' => 'Brother MFC-L2750DW', 'price' => 299, 'brand' => 'Brother'],
                ['name' => 'Canon imageClass', 'price' => 249, 'brand' => 'Canon'],
                ['name' => 'HP OfficeJet Pro', 'price' => 229, 'brand' => 'HP'],
                ['name' => 'Epson Expression Premium', 'price' => 299, 'brand' => 'Epson'],
                ['name' => 'Fujitsu ScanSnap', 'price' => 495, 'brand' => 'Fujitsu'],
                ['name' => 'Brother DS-740D', 'price' => 199, 'brand' => 'Brother'],
                ['name' => 'Canon CanoScan', 'price' => 99, 'brand' => 'Canon'],
            ],
        ];

        $colors = ['Black', 'White', 'Silver', 'Gray', 'Blue', 'Red', 'Gold', 'Rose Gold', 'Green', 'Purple'];
        $conditions = ['New', 'Refurbished', 'Open Box'];
        $variants = ['Standard', 'Pro', 'Plus', 'Ultra', 'Max', 'Mini', 'Lite'];

        Product::withoutSyncingToSearch(function () use ($categories, $productTemplates, $colors, $conditions, $variants, $companyId) {
            foreach ($categories as $category) {
                $templates = $productTemplates[$category->slug] ?? [];

                if (empty($templates)) {
                    continue;
                }

                $this->command->info("Creating products for category: {$category->name}");

                $productsCreated = 0;
                $templateIndex = 0;

                // Create exactly 50 products per subcategory
                while ($productsCreated < 50) {
                    $template = $templates[$templateIndex % count($templates)];

                    // Add variation to make products unique
                    $variant = $variants[$productsCreated % count($variants)];
                    $color = $colors[$productsCreated % count($colors)];
                    $condition = $conditions[$productsCreated % count($conditions)];

                    $productName = "{$template['brand']} {$template['name']} - {$variant} ({$color})";
                    $slug = Str::slug($productName);
                    $sku = strtoupper(Str::slug($template['brand'])) . '-CAT' . $category->id . '-' . str_pad($productsCreated + 1, 4, '0', STR_PAD_LEFT);

                    // Price variation
                    $basePrice = $template['price'];
                    $priceVariation = ($productsCreated % 5) * 50; // Add $0-$200 variation
                    $price = $basePrice + $priceVariation;
                    $comparePrice = $price + ($price * 0.15); // 15% higher compare price
                    $costPrice = $price * 0.70; // 70% of selling price

                    // Stock variation
                    $stock = rand(5, 100);
                    $lowStockThreshold = rand(3, 10);

                    // Random active/featured status
                    $isActive = $productsCreated < 25 ? true : (rand(0, 1) === 1); // First 25 active, rest random
                    $isFeatured = $productsCreated < 5 ? true : (rand(0, 10) > 7); // First 5 featured, rest 30% chance

                    $product = Product::create([
                        'company_id' => $companyId,
                        'name' => $productName,
                        'slug' => $slug,
                        'sku' => $sku,
                        'description' => "High-quality {$template['name']} from {$template['brand']}. Condition: {$condition}. Color: {$color}. Perfect for professional and personal use.",
                        'short_description' => "{$template['brand']} {$template['name']} - {$variant}",
                        'price' => number_format($price, 2, '.', ''),
                        'compare_price' => number_format($comparePrice, 2, '.', ''),
                        'cost_price' => number_format($costPrice, 2, '.', ''),
                        'stock' => $stock,
                        'low_stock_threshold' => $lowStockThreshold,
                        'is_active' => $isActive,
                        'is_featured' => $isFeatured,
                        'meta_data' => [
                            'brand' => $template['brand'],
                            'color' => $color,
                            'condition' => $condition,
                            'variant' => $variant,
                        ],
                    ]);

                    // Attach category as primary
                    $product->categories()->attach($category->id, ['is_primary' => true]);

                    $productsCreated++;
                    $templateIndex++;
                }

                $this->command->info("✓ Created {$productsCreated} products for {$category->name}");
            }
        });

        $totalProducts = Product::count();
        $this->command->info("Products seeded successfully! Total: {$totalProducts} products");
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Electronic devices and accessories'],
            ['name' => 'Tablets', 'slug' => 'tablets', 'description' => 'Tablets and e-readers'],
            ['name' => 'Accessories', 'slug' => 'accessories', 'description' => 'Electronic accessories'],
            ['name' => 'Computers & Laptops', 'slug' => 'computers-laptops', 'description' => 'Desktop computers, laptops, and computer accessories'],
            ['name' => 'Laptops', 'slug' => 'laptops', 'description' => 'Portable laptops and notebooks'],
            ['name' => 'Desktops', 'slug' => 'desktops', 'description' => 'Desktop computers and workstations'],
            ['name' => 'Computer Accessories', 'slug' => 'computer-accessories', 'description' => 'Keyboards, mice, and computer peripherals'],
            ['name' => 'Mobile Phones', 'slug' => 'mobile-phones', 'description' => 'Smartphones, feature phones, and mobile accessories'],
            ['name' => 'Smartphones', 'slug' => 'smartphones', 'description' => 'High-end smartphones'],
            ['name' => 'Mobile Accessories', 'slug' => 'mobile-accessories', 'description' => 'Cases, chargers, and mobile accessories'],
            ['name' => 'Cameras & Photography', 'slug' => 'cameras-photography', 'description' => 'Digital cameras, lenses, and photography equipment'],
            ['name' => 'DSLR Cameras', 'slug' => 'dslr-cameras', 'description' => 'Professional DSLR cameras'],
            ['name' => 'Mirrorless Cameras', 'slug' => 'mirrorless-cameras', 'description' => 'Mirrorless camera systems'],
            ['name' => 'Lenses', 'slug' => 'lenses', 'description' => 'Camera lenses and optics'],
            ['name' => 'Home Appliances', 'slug' => 'home-appliances', 'description' => 'Kitchen appliances and home electronics'],
            ['name' => 'Kitchen Appliances', 'slug' => 'kitchen-appliances', 'description' => 'Kitchen tools and appliances'],
            ['name' => 'Cleaning Appliances', 'slug' => 'cleaning-appliances', 'description' => 'Vacuum cleaners and cleaning devices'],
            ['name' => 'Audio & Headphones', 'slug' => 'audio-headphones', 'description' => 'Speakers, headphones, and audio equipment'],
            ['name' => 'Headphones', 'slug' => 'headphones', 'description' => 'Over-ear and in-ear headphones'],
            ['name' => 'Speakers', 'slug' => 'speakers', 'description' => 'Bluetooth and wired speakers'],
            ['name' => 'Gaming', 'slug' => 'gaming', 'description' => 'Gaming consoles, accessories, and video games'],
            ['name' => 'Gaming Consoles', 'slug' => 'gaming-consoles', 'description' => 'PlayStation, Xbox, Nintendo consoles'],
            ['name' => 'Gaming Accessories', 'slug' => 'gaming-accessories', 'description' => 'Controllers, headsets, and gaming peripherals'],
            ['name' => 'Smart Home', 'slug' => 'smart-home', 'description' => 'Smart home devices and automation systems'],
            ['name' => 'Smart Speakers', 'slug' => 'smart-speakers', 'description' => 'Voice-controlled smart speakers'],
            ['name' => 'Security Cameras', 'slug' => 'security-cameras', 'description' => 'Home security and surveillance cameras'],
            ['name' => 'Wearables', 'slug' => 'wearables', 'description' => 'Smartwatches, fitness trackers, and wearable tech'],
            ['name' => 'Smartwatches', 'slug' => 'smartwatches', 'description' => 'Smart watches and wearable devices'],
            ['name' => 'Fitness Trackers', 'slug' => 'fitness-trackers', 'description' => 'Activity and fitness tracking devices'],
            ['name' => 'Office Supplies', 'slug' => 'office-supplies', 'description' => 'Office equipment and supplies'],
            ['name' => 'Office Furniture', 'slug' => 'office-furniture', 'description' => 'Desks, chairs, and office furniture'],
            ['name' => 'Printers & Scanners', 'slug' => 'printers-scanners', 'description' => 'Printing and scanning equipment'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        $totalCategories = Category::count();
        $this->command->info("Categories seeded successfully! Total: {$totalCategories} categories");
    }
}

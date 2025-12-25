<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get default company
        $company = Company::first();
        $companyId = $company?->id;

        // Clear existing categories
        Category::query()->forceDelete();

        // Main categories (parent categories)
        $electronics = Category::create([
            'company_id' => $companyId,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and accessories'
        ]);

        $computersLaptops = Category::create([
            'company_id' => $companyId,
            'name' => 'Computers & Laptops',
            'slug' => 'computers-laptops',
            'description' => 'Desktop computers, laptops, and computer accessories'
        ]);

        $mobilePhones = Category::create([
            'company_id' => $companyId,
            'name' => 'Mobile Phones',
            'slug' => 'mobile-phones',
            'description' => 'Smartphones, feature phones, and mobile accessories'
        ]);

        $camerasPhotography = Category::create([
            'company_id' => $companyId,
            'name' => 'Cameras & Photography',
            'slug' => 'cameras-photography',
            'description' => 'Digital cameras, lenses, and photography equipment'
        ]);

        $audioHeadphones = Category::create([
            'company_id' => $companyId,
            'name' => 'Audio & Headphones',
            'slug' => 'audio-headphones',
            'description' => 'Speakers, headphones, and audio equipment'
        ]);

        $gaming = Category::create([
            'company_id' => $companyId,
            'name' => 'Gaming',
            'slug' => 'gaming',
            'description' => 'Gaming consoles, accessories, and video games'
        ]);

        $wearables = Category::create([
            'company_id' => $companyId,
            'name' => 'Wearables',
            'slug' => 'wearables',
            'description' => 'Smartwatches, fitness trackers, and wearable tech'
        ]);

        $homeAppliances = Category::create([
            'company_id' => $companyId,
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'description' => 'Kitchen appliances and home electronics'
        ]);

        $smartHome = Category::create([
            'company_id' => $companyId,
            'name' => 'Smart Home',
            'slug' => 'smart-home',
            'description' => 'Smart home devices and automation systems'
        ]);

        $officeSupplies = Category::create([
            'company_id' => $companyId,
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
            'description' => 'Office equipment and supplies'
        ]);

        // Subcategories - Electronics
        Category::create([
            'company_id' => $companyId,
            'name' => 'Tablets',
            'slug' => 'tablets',
            'description' => 'Tablets and e-readers',
            'parent_id' => $electronics->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Accessories',
            'slug' => 'accessories',
            'description' => 'Electronic accessories',
            'parent_id' => $electronics->id
        ]);

        // Subcategories - Computers & Laptops
        Category::create([
            'company_id' => $companyId,
            'name' => 'Laptops',
            'slug' => 'laptops',
            'description' => 'Portable laptops and notebooks',
            'parent_id' => $computersLaptops->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Desktops',
            'slug' => 'desktops',
            'description' => 'Desktop computers and workstations',
            'parent_id' => $computersLaptops->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Computer Accessories',
            'slug' => 'computer-accessories',
            'description' => 'Keyboards, mice, and computer peripherals',
            'parent_id' => $computersLaptops->id
        ]);

        // Subcategories - Mobile Phones
        Category::create([
            'company_id' => $companyId,
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'description' => 'High-end smartphones',
            'parent_id' => $mobilePhones->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Mobile Accessories',
            'slug' => 'mobile-accessories',
            'description' => 'Cases, chargers, and mobile accessories',
            'parent_id' => $mobilePhones->id
        ]);

        // Subcategories - Cameras & Photography
        Category::create([
            'company_id' => $companyId,
            'name' => 'DSLR Cameras',
            'slug' => 'dslr-cameras',
            'description' => 'Professional DSLR cameras',
            'parent_id' => $camerasPhotography->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Mirrorless Cameras',
            'slug' => 'mirrorless-cameras',
            'description' => 'Mirrorless camera systems',
            'parent_id' => $camerasPhotography->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Lenses',
            'slug' => 'lenses',
            'description' => 'Camera lenses and optics',
            'parent_id' => $camerasPhotography->id
        ]);

        // Subcategories - Audio & Headphones
        Category::create([
            'company_id' => $companyId,
            'name' => 'Headphones',
            'slug' => 'headphones',
            'description' => 'Over-ear and in-ear headphones',
            'parent_id' => $audioHeadphones->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Speakers',
            'slug' => 'speakers',
            'description' => 'Bluetooth and wired speakers',
            'parent_id' => $audioHeadphones->id
        ]);

        // Subcategories - Gaming
        Category::create([
            'company_id' => $companyId,
            'name' => 'Gaming Consoles',
            'slug' => 'gaming-consoles',
            'description' => 'PlayStation, Xbox, Nintendo consoles',
            'parent_id' => $gaming->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Gaming Accessories',
            'slug' => 'gaming-accessories',
            'description' => 'Controllers, headsets, and gaming peripherals',
            'parent_id' => $gaming->id
        ]);

        // Subcategories - Wearables
        Category::create([
            'company_id' => $companyId,
            'name' => 'Smartwatches',
            'slug' => 'smartwatches',
            'description' => 'Smart watches and wearable devices',
            'parent_id' => $wearables->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Fitness Trackers',
            'slug' => 'fitness-trackers',
            'description' => 'Activity and fitness tracking devices',
            'parent_id' => $wearables->id
        ]);

        // Subcategories - Home Appliances
        Category::create([
            'company_id' => $companyId,
            'name' => 'Kitchen Appliances',
            'slug' => 'kitchen-appliances',
            'description' => 'Kitchen tools and appliances',
            'parent_id' => $homeAppliances->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Cleaning Appliances',
            'slug' => 'cleaning-appliances',
            'description' => 'Vacuum cleaners and cleaning devices',
            'parent_id' => $homeAppliances->id
        ]);

        // Subcategories - Smart Home
        Category::create([
            'company_id' => $companyId,
            'name' => 'Smart Speakers',
            'slug' => 'smart-speakers',
            'description' => 'Voice-controlled smart speakers',
            'parent_id' => $smartHome->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Security Cameras',
            'slug' => 'security-cameras',
            'description' => 'Home security and surveillance cameras',
            'parent_id' => $smartHome->id
        ]);

        // Subcategories - Office Supplies
        Category::create([
            'company_id' => $companyId,
            'name' => 'Office Furniture',
            'slug' => 'office-furniture',
            'description' => 'Desks, chairs, and office furniture',
            'parent_id' => $officeSupplies->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Printers & Scanners',
            'slug' => 'printers-scanners',
            'description' => 'Printing and scanning equipment',
            'parent_id' => $officeSupplies->id
        ]);

        $totalCategories = Category::count();
        $parentCategories = Category::whereNull('parent_id')->count();
        $childCategories = Category::whereNotNull('parent_id')->count();

        $this->command->info("Categories seeded successfully!");
        $this->command->info("Total: {$totalCategories} categories ({$parentCategories} parent + {$childCategories} subcategories)");
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Outlet;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Member;
use App\Models\DailyCash;
use App\Models\RawMaterial;
use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use Faker\Factory as Faker;

class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Clear existing data
        $this->command->info('Clearing existing data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        MaterialOrderItem::truncate();
        MaterialOrder::truncate();
        RawMaterial::truncate();
        DailyCash::truncate();
        OrderItem::truncate();
        Order::truncate();
        Member::truncate();
        Product::truncate();
        Category::truncate();
        User::truncate();
        Outlet::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Creating outlets...');
        // Create outlets
        $outlets = [
            [
                'name' => 'Seblak Sulthane Purwokerto Pusat',
                'address1' => 'Jl. Merdeka No. 123',
                'address2' => 'Kecamatan Sukajadi, Bandung',
                'phone' => '081234567890',
                'leader' => 'Ahmad Sulaiman',
                'notes' => 'Cabang Utama'
            ],
            [
                'name' => 'Seblak Sulthane Cilacap',
                'address1' => 'Jl. Cilacap No. 45',
                'address2' => 'Kecamatan Cilacap, Cilacap',
                'phone' => '081234567891',
                'leader' => 'Siti Nurhasanah',
                'notes' => 'Cabang'
            ],
            [
                'name' => 'Seblak Sulthane Purwokerto Timur',
                'address1' => 'Jl. Purwokerto Timur No. 78',
                'address2' => 'Kecamatan PWT TIMUR, Banyumas',
                'phone' => '081234567892',
                'leader' => 'Budi Santoso',
                'notes' => 'Cabang'
            ],
            [
                'name' => 'Seblak Sulthane Cimahi',
                'address1' => 'Jl. Cimahi Raya No. 56',
                'address2' => 'Kecamatan Cimahi Tengah, Cimahi',
                'phone' => '081234567893',
                'leader' => 'Dewi Anggraini',
                'notes' => 'Cabang'
            ],
        ];

        foreach ($outlets as $outlet) {
            Outlet::create($outlet);
        }

        $this->command->info('Creating users...');
        // Create users
        // Owner
        User::create([
            'name' => 'Owner Seblak',
            'email' => 'owner@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'outlet_id' => 1,
        ]);

        // Admin for each outlet
        for ($i = 1; $i <= 4; $i++) {
            User::create([
                'name' => "Admin Outlet $i",
                'email' => "admin$i@seblaksulthane.com",
                'password' => Hash::make('password'),
                'role' => 'admin',
                'outlet_id' => $i,
            ]);
        }

        // Staff for each outlet (3-5 staff per outlet)
        foreach (range(1, 4) as $outletId) {
            $staffCount = rand(3, 5);
            foreach (range(1, $staffCount) as $j) {
                User::create([
                    'name' => $faker->name,
                    'email' => $faker->unique()->safeEmail,
                    'password' => Hash::make('password'),
                    'role' => 'staff',
                    'outlet_id' => $outletId,
                ]);
            }
        }

        $this->command->info('Creating categories...');
        // Create categories
        $categories = [
            ['name' => 'Seblak'],
            ['name' => 'Minuman'],
            ['name' => 'Cemilan'],
            ['name' => 'Tambahan'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        $this->command->info('Creating products...');
        // Create products
        $seblakProducts = [
            ['name' => 'Seblak Original', 'price' => 15000, 'description' => 'Seblak dengan bumbu original, level 0-5'],
            ['name' => 'Seblak Seafood', 'price' => 20000, 'description' => 'Seblak dengan tambahan seafood, level 0-5'],
            ['name' => 'Seblak Tulang', 'price' => 18000, 'description' => 'Seblak dengan tambahan tulang ayam, level 0-5'],
            ['name' => 'Seblak Mie', 'price' => 16000, 'description' => 'Seblak dengan tambahan mie, level 0-5'],
            ['name' => 'Seblak Komplit', 'price' => 25000, 'description' => 'Seblak dengan semua topping, level 0-5'],
            ['name' => 'Seblak Makaroni', 'price' => 16000, 'description' => 'Seblak dengan tambahan makaroni, level 0-5'],
        ];

        $drinkProducts = [
            ['name' => 'Es Teh Manis', 'price' => 5000, 'description' => 'Teh manis dingin'],
            ['name' => 'Es Jeruk', 'price' => 6000, 'description' => 'Jeruk segar dingin'],
            ['name' => 'Teh Botol', 'price' => 7000, 'description' => 'Teh dalam botol'],
            ['name' => 'Air Mineral', 'price' => 4000, 'description' => 'Air mineral kemasan'],
            ['name' => 'Es Milo', 'price' => 8000, 'description' => 'Milo dingin dengan es'],
        ];

        $snackProducts = [
            ['name' => 'Cireng', 'price' => 10000, 'description' => 'Cireng dengan bumbu rujak'],
            ['name' => 'Batagor', 'price' => 12000, 'description' => '5 pcs batagor dengan bumbu kacang'],
            ['name' => 'Siomay', 'price' => 15000, 'description' => '5 pcs siomay dengan bumbu kacang'],
            ['name' => 'Kerupuk Seblak', 'price' => 8000, 'description' => 'Kerupuk dengan bumbu seblak'],
        ];

        $additionalProducts = [
            ['name' => 'Telur', 'price' => 3000, 'description' => 'Tambahan telur'],
            ['name' => 'Cikur', 'price' => 2000, 'description' => 'Tambahan cikur'],
            ['name' => 'Bakso', 'price' => 5000, 'description' => 'Tambahan bakso 3 pcs'],
            ['name' => 'Sosis', 'price' => 5000, 'description' => 'Tambahan sosis 3 pcs'],
        ];

        foreach ($seblakProducts as $product) {
            Product::create([
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'stock' => 100,
                'category_id' => 1,
                'is_favorite' => rand(0, 1),
                'status' => 1,
                'image' => 'seblak.jpg',
            ]);
        }

        foreach ($drinkProducts as $product) {
            Product::create([
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'stock' => 100,
                'category_id' => 2,
                'is_favorite' => rand(0, 1),
                'status' => 1,
                'image' => 'drink.jpg',
            ]);
        }

        foreach ($snackProducts as $product) {
            Product::create([
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'stock' => 100,
                'category_id' => 3,
                'is_favorite' => rand(0, 1),
                'status' => 1,
                'image' => 'snack.jpg',
            ]);
        }

        foreach ($additionalProducts as $product) {
            Product::create([
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'stock' => 100,
                'category_id' => 4,
                'is_favorite' => rand(0, 1),
                'status' => 1,
                'image' => 'additional.jpg',
            ]);
        }

        $this->command->info('Creating members...');
        // Create members
        foreach (range(1, 50) as $i) {
            Member::create([
                'name' => $faker->name,
                'phone' => $faker->numerify('08##########'),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => $faker->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        $this->command->info('Creating raw materials...');
        // Create raw materials
        $rawMaterials = [
            ['name' => 'Bawang Merah', 'unit' => 'Kg', 'price' => 40000, 'stock' => rand(3, 20), 'description' => 'Bawang merah segar'],
            ['name' => 'Bawang Putih', 'unit' => 'Kg', 'price' => 35000, 'stock' => rand(3, 20), 'description' => 'Bawang putih segar'],
            ['name' => 'Cabai Merah', 'unit' => 'Kg', 'price' => 50000, 'stock' => rand(3, 20), 'description' => 'Cabai merah segar'],
            ['name' => 'Cabai Rawit', 'unit' => 'Kg', 'price' => 60000, 'stock' => rand(3, 20), 'description' => 'Cabai rawit segar'],
            ['name' => 'Tepung Terigu', 'unit' => 'Kg', 'price' => 15000, 'stock' => rand(3, 20), 'description' => 'Tepung terigu protein sedang'],
            ['name' => 'Tepung Tapioka', 'unit' => 'Kg', 'price' => 12000, 'stock' => rand(3, 20), 'description' => 'Tepung tapioka premium'],
            ['name' => 'Minyak Goreng', 'unit' => 'Liter', 'price' => 25000, 'stock' => rand(3, 20), 'description' => 'Minyak goreng curah'],
            ['name' => 'Kerupuk', 'unit' => 'Kg', 'price' => 30000, 'stock' => rand(3, 20), 'description' => 'Kerupuk mentah untuk seblak'],
            ['name' => 'Makaroni', 'unit' => 'Kg', 'price' => 25000, 'stock' => rand(3, 20), 'description' => 'Makaroni kering untuk seblak'],
            ['name' => 'Telur', 'unit' => 'Kg', 'price' => 28000, 'stock' => rand(3, 20), 'description' => 'Telur ayam'],
            ['name' => 'Bakso', 'unit' => 'Kg', 'price' => 70000, 'stock' => rand(3, 20), 'description' => 'Bakso sapi kemasan'],
            ['name' => 'Sosis', 'unit' => 'Kg', 'price' => 65000, 'stock' => rand(3, 20), 'description' => 'Sosis ayam kemasan'],
            ['name' => 'Sayur Sawi', 'unit' => 'Kg', 'price' => 20000, 'stock' => rand(3, 20), 'description' => 'Sawi hijau segar'],
            ['name' => 'Mie Kuning', 'unit' => 'Kg', 'price' => 18000, 'stock' => rand(3, 20), 'description' => 'Mie kuning basah'],
            ['name' => 'Seafood Mix', 'unit' => 'Kg', 'price' => 80000, 'stock' => rand(3, 20), 'description' => 'Campuran seafood beku'],
            ['name' => 'Air Galon', 'unit' => 'Galon', 'price' => 20000, 'stock' => rand(3, 20), 'description' => 'Air minum kemasan galon'],
            ['name' => 'Gula Pasir', 'unit' => 'Kg', 'price' => 18000, 'stock' => rand(3, 20), 'description' => 'Gula pasir putih'],
            ['name' => 'Teh Celup', 'unit' => 'Box', 'price' => 15000, 'stock' => rand(3, 20), 'description' => 'Teh celup kemasan (50 pcs)'],
            ['name' => 'Jeruk Nipis', 'unit' => 'Kg', 'price' => 30000, 'stock' => rand(3, 20), 'description' => 'Jeruk nipis segar'],
            ['name' => 'Gas LPG', 'unit' => 'Tabung', 'price' => 160000, 'stock' => rand(3, 20), 'description' => 'Gas LPG 12 kg'],
        ];

        foreach ($rawMaterials as $material) {
            RawMaterial::create([
                'name' => $material['name'],
                'unit' => $material['unit'],
                'price' => $material['price'],
                'stock' => $material['stock'],
                'description' => $material['description'],
                'is_active' => 1,
            ]);
        }

        $this->command->info('Creating orders and daily cash data...');
        // Create orders and daily cash records for the past 90 days
        $startDate = Carbon::now()->subDays(89);
        $endDate = Carbon::now();

        $currentDate = $startDate->copy();
        $paymentMethods = ['cash', 'qris'];
        $orderTypes = ['dine_in', 'take_away'];

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');

            // Create daily cash records for each outlet
            foreach (range(1, 4) as $outletId) {
                DailyCash::create([
                    'outlet_id' => $outletId,
                    'user_id' => $outletId + 1, // Admin of each outlet
                    'date' => $dateString,
                    'opening_balance' => rand(500000, 1000000),
                    'expenses' => rand(100000, 300000),
                    'expenses_note' => "Pengeluaran operasional tanggal $dateString",
                ]);
            }

            // Determine number of orders for each outlet based on day of week
            // More orders on weekends, less on weekdays
            $isWeekend = $currentDate->isWeekend();

            foreach (range(1, 4) as $outletId) {
                $orderCount = $isWeekend ? rand(20, 40) : rand(10, 25);

                // Adjust order count based on recency (more recent days have more orders to show growth)
                $daysSinceStart = $currentDate->diffInDays($startDate);
                $percentageIncrease = $daysSinceStart / 90 * 30; // Up to 30% increase over 90 days
                $orderCount = round($orderCount * (1 + $percentageIncrease / 100));

                for ($i = 0; $i < $orderCount; $i++) {
                    // Randomize creation time within the day
                    $createdAt = $currentDate->copy()->addHours(rand(10, 21))->addMinutes(rand(0, 59));

                    // Randomize if the order has a member
                    $memberId = rand(0, 100) < 40 ? rand(1, 50) : null;

                    // Calculate values based on the order items that will be created
                    $orderItemCount = rand(1, 5);
                    $subtotal = 0;
                    $totalItems = 0;

                    // Build the order
                    $order = [
                        'outlet_id' => $outletId,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'transaction_time' => $createdAt->format('Y-m-d H:i:s'),
                        'payment_method' => $paymentMethods[rand(0, 1)],
                        'order_type' => $orderTypes[rand(0, 1)],
                        'id_kasir' => $outletId + 1 + rand(0, 3), // Random staff ID
                        'nama_kasir' => 'Kasir ' . rand(1, 5),
                        'member_id' => $memberId,
                    ];

                    // Create order items first to calculate the subtotal
                    $orderItems = [];
                    for ($j = 0; $j < $orderItemCount; $j++) {
                        $productId = rand(1, 19);
                        $product = Product::find($productId);

                        if ($product) {
                            $quantity = rand(1, 3);
                            $price = $product->price;
                            $subtotal += $price * $quantity;
                            $totalItems += $quantity;

                            $orderItems[] = [
                                'product_id' => $productId,
                                'quantity' => $quantity,
                                'price' => $price,
                            ];
                        }
                    }

                    // Apply tax, discount, service charge
                    $tax = round($subtotal * 0.1); // 10% tax
                    $discountAmount = rand(0, 100) < 30 ? round($subtotal * rand(5, 15) / 100) : 0; // 30% chance of discount
                    $serviceCharge = 0; // No service charge for this example
                    $total = $subtotal + $tax - $discountAmount + $serviceCharge;

                    // Complete the order data
                    $order['sub_total'] = $subtotal;
                    $order['tax'] = $tax;
                    $order['discount'] = 0; // Legacy field, kept for compatibility
                    $order['discount_amount'] = $discountAmount;
                    $order['service_charge'] = $serviceCharge;
                    $order['total'] = $total;
                    $order['payment_amount'] = $total;
                    $order['total_item'] = $totalItems;

                    // Create the order
                    $createdOrder = Order::create($order);

                    // Create the order items
                    foreach ($orderItems as $item) {
                        $item['order_id'] = $createdOrder->id;
                        OrderItem::create($item);
                    }
                }
            }

            // Create material orders (less frequent, about once every 10 days per outlet)
            foreach (range(1, 4) as $outletId) {
                // Only create if it's a specific day of the period (e.g., every 10 days or so)
                if ($currentDate->day % 10 == $outletId % 10) {
                    $createdAt = $currentDate->copy()->addHours(rand(9, 11))->addMinutes(rand(0, 59));

                    // Determine status based on date (older orders are more likely to be delivered)
                    $daysSinceStart = $currentDate->diffInDays($startDate);
                    $daysSinceNow = $currentDate->diffInDays(Carbon::now());

                    $status = 'pending';
                    $approvedAt = null;
                    $deliveredAt = null;

                    if ($daysSinceNow > 7) {
                        $status = 'delivered';
                        $approvedAt = $createdAt->copy()->addHours(rand(1, 4));
                        $deliveredAt = $approvedAt->copy()->addDays(rand(1, 3));
                    } elseif ($daysSinceNow > 3) {
                        $status = 'approved';
                        $approvedAt = $createdAt->copy()->addHours(rand(1, 24));
                    }

                    // Material orders payment methods berdasarkan migrasi 2025_03_05_071745_add_payment_method_to_material_orders_table.php
                    $materialPaymentMethods = ['cash', 'bank_transfer', 'e-wallet'];

                    // Create the material order
                    $materialOrder = MaterialOrder::create([
                        'franchise_id' => $outletId,
                        'user_id' => $outletId + 1, // Admin of each outlet
                        'status' => $status,
                        'total_amount' => 0, // Will be calculated based on items
                        'payment_method' => $materialPaymentMethods[rand(0, 2)], // Gunakan metode pembayaran yang valid
                        'notes' => "Pesanan bahan baku tanggal $dateString",
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'approved_at' => $approvedAt,
                        'delivered_at' => $deliveredAt,
                    ]);

                    // Create 3-8 items for this order
                    $materialItemCount = rand(3, 8);
                    $totalAmount = 0;

                    for ($j = 0; $j < $materialItemCount; $j++) {
                        $materialId = rand(1, 20);
                        $material = RawMaterial::find($materialId);

                        if ($material) {
                            $quantity = rand(1, 10);
                            $pricePerUnit = $material->price;
                            $subtotal = $pricePerUnit * $quantity;
                            $totalAmount += $subtotal;

                            MaterialOrderItem::create([
                                'material_order_id' => $materialOrder->id,
                                'raw_material_id' => $materialId,
                                'quantity' => $quantity,
                                'price_per_unit' => $pricePerUnit,
                                'subtotal' => $subtotal,
                                'created_at' => $createdAt,
                                'updated_at' => $createdAt,
                            ]);
                        }
                    }

                    // Update the total amount
                    $materialOrder->total_amount = $totalAmount;
                    $materialOrder->save();
                }
            }

            $currentDate->addDay();
        }

        $this->command->info('Database seeded successfully!');
    }
}

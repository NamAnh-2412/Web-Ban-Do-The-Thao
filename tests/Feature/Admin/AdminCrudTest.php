<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tổng quan');
    }

    public function test_staff_dashboard_shows_work_queues(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create(['name' => 'Khách chờ chốt']);

        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'channel' => OrderChannel::Online,
            'grand_total' => 150000,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'channel' => OrderChannel::Pos,
            'grand_total' => 80000,
        ]);

        $saleProduct = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo tồn thấp',
        ]);
        $saleVariant = ProductVariant::factory()->create([
            'product_id' => $saleProduct->id,
            'sku' => 'LOW-SALE-SKU',
            'sale_price' => 100000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $saleVariant->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);

        $rentalProduct = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt quá hạn',
        ]);
        $rentalVariant = ProductVariant::factory()->create([
            'product_id' => $rentalProduct->id,
            'sku' => 'OVERDUE-SKU',
            'sale_price' => null,
            'rental_price_per_day' => 50000,
        ]);
        $item = InventoryItem::factory()->create([
            'product_variant_id' => $rentalVariant->id,
            'status' => ItemStatus::Rented,
        ]);
        RentalBooking::factory()->create([
            'user_id' => $customer->id,
            'inventory_item_id' => $item->id,
            'product_variant_id' => $rentalVariant->id,
            'status' => BookingStatus::Active,
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Đơn online')
            ->assertDontSee('Đơn chờ thu')
            ->assertSee('Quá hạn trả')
            ->assertSee('Khách chờ chốt')
            ->assertSee('LOW-SALE-SKU')
            ->assertSee('OVERDUE-SKU')
            ->assertSee('channel=online')
            ->assertSee(route('admin.rentals.index', ['status' => 'overdue']));

        $this->get(route('admin.rentals.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee('OVERDUE-SKU');

        $this->get(route('admin.rentals.index', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee('OVERDUE-SKU');
    }

    public function test_dashboard_counts_today_orders_by_line_type(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $product = Product::factory()->create(['offer_mode' => OfferMode::Both, 'name' => 'SP đơn hôm nay']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TODAY-SKU',
        ]);
        $line = fn (Order $order, LineType $type): array => [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'line_type' => $type,
            'quantity' => 1,
            'line_total' => 100000,
        ];

        $saleOrder = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'grand_total' => 100000,
        ]);
        OrderItem::factory()->create($line($saleOrder, LineType::Sale));

        $rentalOrder = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Confirmed,
            'grand_total' => 200000,
        ]);
        OrderItem::factory()->create($line($rentalOrder, LineType::Rental));

        $mixed = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'grand_total' => 300000,
        ]);
        OrderItem::factory()->create($line($mixed, LineType::Sale));
        OrderItem::factory()->create($line($mixed, LineType::Rental));

        $cancelled = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Cancelled,
            'grand_total' => 999000,
        ]);
        OrderItem::factory()->create($line($cancelled, LineType::Sale));

        $html = $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Đơn hàng hôm nay')
            ->assertSee('Đơn mua')
            ->assertSee('Đơn thuê')
            ->assertSee('Đơn hỗn hợp')
            ->assertSee('Doanh thu 7 ngày')
            ->getContent();

        $this->assertMatchesRegularExpression('/Đơn hàng hôm nay[\s\S]{0,250}?>3</', $html);
        $this->assertMatchesRegularExpression('/Đơn mua[\s\S]{0,250}?>1</', $html);
        $this->assertMatchesRegularExpression('/Đơn thuê[\s\S]{0,250}?>1</', $html);
        $this->assertMatchesRegularExpression('/Đơn hỗn hợp[\s\S]{0,250}?>1</', $html);
    }

    public function test_admin_can_create_category_and_product_with_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('admin.categories.store'), [
            'name' => 'Dụng cụ lab',
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('name', 'Dụng cụ lab')->first();
        $this->assertNotNull($category);

        $this->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Thông tin cơ bản');

        $this->post(route('admin.products.store'), [
            'name' => 'Áo admin test',
            'category_id' => $category->id,
            'offer_mode' => 'sale',
            'description' => 'Sản phẩm CRUD admin',
            'variants' => [
                [
                    'sku' => 'ADM-AO-'.uniqid(),
                    'size' => 'M',
                    'color' => 'Đỏ',
                    'sale_price' => 199000,
                    'rental_price_per_day' => '',
                    'deposit_amount' => '',
                    'quantity' => 7,
                ],
            ],
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Áo admin test')->first();
        $this->assertNotNull($product);
        $this->assertSame(1, $product->variants()->count());

        $variant = $product->variants()->first();
        $this->assertNotNull($variant->stock);
        $this->assertSame(7, $variant->stock->quantity_on_hand);

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Áo admin test')
            ->assertSee($variant->sku);
    }

    public function test_admin_can_update_product_stock_from_form(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Giày cập nhật',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'ADM-UPD-'.uniqid(),
            'sale_price' => 350000,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => 'Giày cập nhật mới',
            'category_id' => $category->id,
            'offer_mode' => 'sale',
            'variants' => [
                [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'size' => '42',
                    'color' => 'Đen',
                    'sale_price' => 360000,
                    'rental_price_per_day' => '',
                    'deposit_amount' => '',
                    'quantity' => 12,
                ],
            ],
        ])->assertRedirect(route('admin.products.edit', $product));

        $this->assertSame('Giày cập nhật mới', $product->fresh()->name);
        $this->assertSame(12, $variant->fresh()->stock->quantity_on_hand);
    }

    public function test_rental_product_quantity_creates_items_customers_can_book(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Vợt thuê kho',
            'category_id' => $category->id,
            'offer_mode' => 'rental',
            'variants' => [
                [
                    'sku' => 'RENT-KHO-'.uniqid(),
                    'size' => '',
                    'color' => '',
                    'sale_price' => '',
                    'rental_price_per_day' => 80000,
                    'deposit_amount' => 200000,
                    'quantity' => 3,
                ],
            ],
        ])->assertRedirect();

        $variant = ProductVariant::query()->where('sku', 'like', 'RENT-KHO-%')->first();
        $this->assertNotNull($variant);
        $this->assertSame(3, $variant->stock->quantity_on_hand);
        $this->assertSame(3, InventoryItem::query()->where('product_variant_id', $variant->id)->count());

        $this->get('/kho/thue?product_variant_id='.$variant->id.'&start_date=2026-09-01&end_date=2026-09-03')
            ->assertOk()
            ->assertJsonPath('quantity_available', 3)
            ->assertJsonPath('available', true);
    }

    public function test_updating_rental_quantity_fills_missing_items(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'offer_mode' => 'rental',
            'name' => 'Giày thuê thiếu món',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'RENT-MISS-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 50000,
            'deposit_amount' => 150000,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 4,
            'quantity_reserved' => 0,
        ]);

        $this->assertSame(0, $variant->items()->count());

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'category_id' => $category->id,
            'offer_mode' => 'rental',
            'variants' => [
                [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'sale_price' => '',
                    'rental_price_per_day' => 50000,
                    'deposit_amount' => 150000,
                    'quantity' => 4,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(4, $variant->items()->count());
        $this->get('/kho/thue?product_variant_id='.$variant->id.'&start_date=2026-09-01&end_date=2026-09-03')
            ->assertJsonPath('quantity_available', 4);
    }

    public function test_sale_product_quantity_does_not_create_rental_items(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Áo chỉ bán',
            'category_id' => $category->id,
            'offer_mode' => 'sale',
            'variants' => [
                [
                    'sku' => 'SALE-ONLY-'.uniqid(),
                    'size' => 'M',
                    'color' => 'Đỏ',
                    'sale_price' => 199000,
                    'rental_price_per_day' => '',
                    'deposit_amount' => '',
                    'quantity' => 7,
                ],
            ],
        ])->assertRedirect();

        $variant = ProductVariant::query()->where('sku', 'like', 'SALE-ONLY-%')->first();
        $this->assertSame(7, $variant->stock->quantity_on_hand);
        $this->assertSame(0, $variant->items()->count());
    }

    public function test_admin_can_create_store_staff_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Thêm tài khoản cửa hàng');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Thu ngân ca sáng',
            'email' => 'thungan@example.com',
            'phone' => '0903333444',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'staff',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'thungan@example.com',
            'role' => UserRole::Staff->value,
        ]);
    }

    public function test_staff_cannot_create_store_account(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Không được',
            'email' => 'khongduoc@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'staff',
        ])->assertForbidden();

        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.coupons.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_staff_can_confirm_pending_order(): void
    {
        $customer = User::factory()->create();
        $order = \App\Domain\Order\Models\Order::factory()->create([
            'user_id' => $customer->id,
            'status' => \App\Domain\Order\Enums\OrderStatus::Pending,
        ]);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();

        $this->assertSame('confirmed', $order->fresh()->status->value);
    }

    public function test_orders_index_shows_order_code_and_online_pending_count(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $online = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'channel' => OrderChannel::Online,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'channel' => OrderChannel::Pos,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Mã đơn')
            ->assertSee('#'.$online->id)
            ->assertSee('Đơn online chờ nhận: 1')
            ->assertDontSee('Đơn online chờ nhận: 2');
    }

    public function test_orders_index_defaults_to_today_and_can_open_a_past_day(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $yesterday = now()->subDay()->startOfDay();

        $todayPos = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'channel' => OrderChannel::Pos,
        ]);
        $todayCancelled = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Cancelled,
            'channel' => OrderChannel::Online,
        ]);
        $oldPending = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'channel' => OrderChannel::Online,
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);
        $oldPaid = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'channel' => OrderChannel::Pos,
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

        $todayPage = $this->actingAs($staff)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Đơn online chờ nhận: 1')
            ->getContent();
        $this->assertStringContainsString('#'.$todayPos->id.'</td>', $todayPage);
        $this->assertStringContainsString('#'.$todayCancelled->id.'</td>', $todayPage);
        $this->assertStringNotContainsString('#'.$oldPending->id.'</td>', $todayPage);
        $this->assertStringNotContainsString('#'.$oldPaid->id.'</td>', $todayPage);

        $pastPage = $this->get(route('admin.orders.index', ['date' => $yesterday->toDateString()]))
            ->assertOk()
            ->assertSee('Đơn online chờ nhận: 1')
            ->getContent();
        $this->assertStringContainsString('#'.$oldPending->id.'</td>', $pastPage);
        $this->assertStringContainsString('#'.$oldPaid->id.'</td>', $pastPage);
        $this->assertStringNotContainsString('#'.$todayPos->id.'</td>', $pastPage);

        $queuePage = $this->get(route('admin.orders.index', [
            'date' => 'all',
            'status' => 'pending',
            'channel' => 'online',
        ]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('#'.$oldPending->id.'</td>', $queuePage);
        $this->assertStringNotContainsString('#'.$todayPos->id.'</td>', $queuePage);
        $this->assertStringNotContainsString('#'.$oldPaid->id.'</td>', $queuePage);
    }

    public function test_staff_can_refund_paid_order_without_user_id(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create(['name' => 'Khách hoàn tiền']);
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt hoàn tiền',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'REFUND-RENT-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 80000,
        ]);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'grand_total' => 100000,
            'rental_total' => 100000,
        ]);
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'line_type' => LineType::Rental,
            'quantity' => 1,
            'line_total' => 100000,
        ]);
        RentalBooking::factory()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_variant_id' => $variant->id,
            'status' => BookingStatus::Returned,
        ]);
        \App\Domain\Payment\Models\Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'amount' => 100000,
            'status' => \App\Domain\Payment\Enums\PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hoàn tiền')
            ->assertDontSee('User ID');

        $this->post(route('admin.payments.refund'), [
            'order_id' => $order->id,
            'amount' => 40000,
            'note' => 'Hoàn một phần',
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'kind' => 'refund',
            'amount' => 40000,
        ]);

        $this->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Mã khoản')
            ->assertSee('Khách hoàn tiền')
            ->assertDontSee('User ID');
    }
}

<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\DetailSuratJalan;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\Notification;
use App\Models\Pengguna;
use App\Models\ResepMenu;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use App\Models\VarianMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    private int $notificationSequence = 0;

    public function test_manager_only_reads_and_marks_notifications_owned_by_the_authenticated_user(): void
    {
        $branch = $this->makeBranch('Cabang A');
        $otherBranch = $this->makeBranch('Cabang B');
        $manager = $this->makeUser('manager', $branch->cabang_id);
        $sameBranchManager = $this->makeUser('manager', $branch->cabang_id);
        $otherBranchManager = $this->makeUser('manager', $otherBranch->cabang_id);

        $owned = $this->makeNotification($manager, 'restock', $branch->cabang_id, 10);
        $sameBranch = $this->makeNotification($sameBranchManager, 'restock', $branch->cabang_id, 11);
        $otherBranchNotification = $this->makeNotification($otherBranchManager, 'restock', $otherBranch->cabang_id, 12);

        $response = $this->actingAs($manager)
            ->getJson(route('notifications.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $owned->notification_id)
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($manager)
            ->postJson(route('notifications.read', $sameBranch->notification_id))
            ->assertNotFound();

        $this->actingAs($manager)
            ->postJson(route('notifications.read', $owned->notification_id))
            ->assertOk()
            ->assertJsonPath('redirect_url', '/manager/alert?stok_cabang_id=10')
            ->assertJsonPath('unread_count', 0);

        $this->assertTrue($owned->fresh()->is_read);
        $this->assertFalse($sameBranch->fresh()->is_read);
        $this->assertFalse($otherBranchNotification->fresh()->is_read);
    }

    public function test_manager_approval_notifies_assigned_dc_users_and_links_to_the_order(): void
    {
        $branch = $this->makeBranch('Cabang Pesanan');
        $dc = $this->makeDc('DC Utama');
        $manager = $this->makeUser('manager', $branch->cabang_id);
        $assignedDcUser = $this->makeUser('dc', dcId: $dc->dc_id);
        $unassignedDcUser = $this->makeUser('dc');
        $ingredient = $this->makeIngredient();

        $response = $this->actingAs($manager)
            ->postJson(route('manager.restock.approve'), [
                'items' => [
                    ['bahan_id' => $ingredient->bahan_id, 'qty_diminta' => 1],
                ],
                'asal' => 'manual',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'disetujui');

        $draftId = $response->json('draft_id');
        $this->assertDatabaseHas('draft_instruksi', [
            'draft_id' => $draftId,
            'cabang_id' => $branch->cabang_id,
            'status' => 'disetujui',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignedDcUser->user_id,
            'type' => 'pesanan_masuk',
            'reference_type' => 'draft_instruksi',
            'reference_id' => $draftId,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $unassignedDcUser->user_id,
            'type' => 'pesanan_masuk',
            'reference_id' => $draftId,
        ]);

        $notification = Notification::query()
            ->where('user_id', $assignedDcUser->user_id)
            ->where('reference_id', $draftId)
            ->firstOrFail();

        $this->actingAs($assignedDcUser)
            ->postJson(route('notifications.read', $notification->notification_id))
            ->assertOk()
            ->assertJsonPath('redirect_url', '/dc/order?draft_id='.$draftId);

        $this->actingAs($unassignedDcUser)
            ->get(route('dc.order'))
            ->assertForbidden();
    }

    public function test_dc_dispatch_notifies_only_ic_users_in_the_destination_branch(): void
    {
        $branch = $this->makeBranch('Cabang Tujuan');
        $otherBranch = $this->makeBranch('Cabang Lain');
        $dc = $this->makeDc('DC Pengirim');
        $otherDc = $this->makeDc('DC Lain');
        $dcUser = $this->makeUser('dc', dcId: $dc->dc_id);
        $sameBranchIc = $this->makeUser('ic', $branch->cabang_id);
        $anotherSameBranchIc = $this->makeUser('ic', $branch->cabang_id);
        $otherBranchIc = $this->makeUser('ic', $otherBranch->cabang_id);
        $ownedShipment = $this->makeShipment($branch->cabang_id, $dc->dc_id);
        $otherDcShipment = $this->makeShipment($branch->cabang_id, $otherDc->dc_id);

        $this->actingAs($dcUser)
            ->postJson(route('dc.shipment.kirim', $otherDcShipment->surat_jalan_id))
            ->assertNotFound();

        $this->assertSame('disiapkan', $otherDcShipment->fresh()->status);

        $this->actingAs($dcUser)
            ->postJson(route('dc.shipment.kirim', $ownedShipment->surat_jalan_id))
            ->assertOk()
            ->assertJsonPath('status', 'menunggu');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $sameBranchIc->user_id,
            'type' => 'surat_jalan',
            'cabang_id' => $branch->cabang_id,
            'reference_id' => $ownedShipment->surat_jalan_id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $anotherSameBranchIc->user_id,
            'type' => 'surat_jalan',
            'reference_id' => $ownedShipment->surat_jalan_id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherBranchIc->user_id,
            'reference_id' => $ownedShipment->surat_jalan_id,
        ]);

        $notification = Notification::query()
            ->where('user_id', $sameBranchIc->user_id)
            ->where('reference_id', $ownedShipment->surat_jalan_id)
            ->firstOrFail();

        $this->actingAs($sameBranchIc)
            ->postJson(route('notifications.read', $notification->notification_id))
            ->assertOk()
            ->assertJsonPath(
                'redirect_url',
                '/ic/receiving?surat_jalan_id='.$ownedShipment->surat_jalan_id
            );
    }

    public function test_ic_receipt_notifies_users_assigned_to_the_surat_jalan_dc(): void
    {
        $branch = $this->makeBranch('Cabang Penerima');
        $dc = $this->makeDc('DC Tujuan');
        $otherDc = $this->makeDc('DC Lain');
        $ic = $this->makeUser('ic', $branch->cabang_id);
        $dcUser = $this->makeUser('dc', dcId: $dc->dc_id);
        $anotherDcUser = $this->makeUser('dc', dcId: $dc->dc_id);
        $otherDcUser = $this->makeUser('dc', dcId: $otherDc->dc_id);
        $ingredient = $this->makeIngredient();
        $shipment = $this->makeShipment($branch->cabang_id, $dc->dc_id, 'dikirim');

        DetailSuratJalan::query()->create([
            'surat_jalan_id' => $shipment->surat_jalan_id,
            'bahan_id' => $ingredient->bahan_id,
            'qty_dikirim' => 2,
            'qty_diterima' => null,
        ]);
        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);

        $this->actingAs($ic)
            ->postJson(route('ic.receiving.confirm', $shipment->surat_jalan_id), [
                'items' => [
                    ['bahan_id' => $ingredient->bahan_id, 'qty_diterima' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'diterima');

        foreach ([$dcUser, $anotherDcUser] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->user_id,
                'type' => 'surat_jalan',
                'dc_id' => $dc->dc_id,
                'reference_id' => $shipment->surat_jalan_id,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherDcUser->user_id,
            'reference_id' => $shipment->surat_jalan_id,
        ]);

        $notification = Notification::query()
            ->where('user_id', $dcUser->user_id)
            ->where('reference_id', $shipment->surat_jalan_id)
            ->firstOrFail();

        $this->actingAs($dcUser)
            ->postJson(route('notifications.read', $notification->notification_id))
            ->assertOk()
            ->assertJsonPath(
                'redirect_url',
                '/dc/shipment?surat_jalan_id='.$shipment->surat_jalan_id
            );
    }

    public function test_waste_and_low_stock_notifications_are_scoped_and_not_repeated_while_stock_stays_low(): void
    {
        $branch = $this->makeBranch('Cabang Waste');
        $otherBranch = $this->makeBranch('Cabang Lain');
        $ic = $this->makeUser('ic', $branch->cabang_id);
        $manager = $this->makeUser('manager', $branch->cabang_id);
        $otherManager = $this->makeUser('manager', $otherBranch->cabang_id);
        $sameBranchIc = $this->makeUser('ic', $branch->cabang_id);
        $ingredient = $this->makeIngredient();
        $stock = StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 6,
            'stok_minimum' => 5,
        ]);

        foreach ([2, 1] as $quantity) {
            $this->actingAs($ic)
                ->postJson(route('ic.waste.store'), [
                    'items' => [
                        [
                            'bahan_id' => $ingredient->bahan_id,
                            'qty' => $quantity,
                            'alasan' => 'Rusak',
                        ],
                    ],
                ])
                ->assertOk();
        }

        $this->assertSame(3.0, (float) $stock->fresh()->jumlah_stok);
        $this->assertSame(2, Notification::query()
            ->where('user_id', $manager->user_id)
            ->where('type', 'waste')
            ->count());
        $this->assertSame(1, Notification::query()
            ->where('user_id', $manager->user_id)
            ->where('type', 'restock')
            ->count());
        $this->assertSame(1, Notification::query()
            ->where('user_id', $sameBranchIc->user_id)
            ->where('type', 'stok_menipis')
            ->count());
        $this->assertSame(0, Notification::query()
            ->where('user_id', $otherManager->user_id)
            ->count());

        $wasteNotification = Notification::query()
            ->where('user_id', $manager->user_id)
            ->where('type', 'waste')
            ->firstOrFail();

        $this->actingAs($manager)
            ->postJson(route('notifications.read', $wasteNotification->notification_id))
            ->assertOk()
            ->assertJsonPath(
                'redirect_url',
                '/manager/alert?waste_id='.$wasteNotification->reference_id
            );

        $restockNotification = Notification::query()
            ->where('user_id', $manager->user_id)
            ->where('type', 'restock')
            ->firstOrFail();

        $this->actingAs($manager)
            ->postJson(route('notifications.read', $restockNotification->notification_id))
            ->assertOk()
            ->assertJsonPath(
                'redirect_url',
                '/manager/alert?stok_cabang_id='.$stock->stok_cabang_id
            );
    }

    public function test_sales_notify_manager_and_ic_once_when_stock_first_falls_below_minimum(): void
    {
        $branch = $this->makeBranch('Cabang Penjualan');
        $otherBranch = $this->makeBranch('Cabang Lain');
        $cashier = $this->makeUser('kasir', $branch->cabang_id);
        $manager = $this->makeUser('manager', $branch->cabang_id);
        $ic = $this->makeUser('ic', $branch->cabang_id);
        $otherManager = $this->makeUser('manager', $otherBranch->cabang_id);
        $ingredient = $this->makeIngredient();
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Minuman']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Menu Uji',
            'harga_jual' => 10000,
            'emoji' => '☕',
        ]);
        $variant = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
        ]);
        ResepMenu::query()->create([
            'varian_id' => $variant->varian_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_bahan' => 2,
        ]);
        $stock = StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 9,
            'stok_minimum' => 5,
        ]);

        for ($sale = 0; $sale < 4; $sale++) {
            $this->actingAs($cashier)
                ->postJson(route('kasir.pos'), [
                    'items' => [
                        ['menu' => $menu->menu_id, 'qty' => 1],
                    ],
                    'method' => 'qris',
                ])
                ->assertOk();
        }

        $this->assertSame(1.0, (float) $stock->fresh()->jumlah_stok);
        $this->assertSame(1, Notification::query()
            ->where('user_id', $manager->user_id)
            ->where('type', 'restock')
            ->count());
        $this->assertSame(1, Notification::query()
            ->where('user_id', $ic->user_id)
            ->where('type', 'stok_menipis')
            ->count());
        $this->assertSame(0, Notification::query()
            ->where('user_id', $otherManager->user_id)
            ->count());
    }

    public function test_notification_bell_is_visible_only_to_dc_manager_and_ic_roles(): void
    {
        $branch = $this->makeBranch('Cabang Header');
        $dc = $this->makeDc('DC Header');
        $roles = [
            ['admin', null, null, false],
            ['kasir', $branch->cabang_id, null, false],
            ['dc', null, $dc->dc_id, true],
            ['manager', $branch->cabang_id, null, true],
            ['ic', $branch->cabang_id, null, true],
        ];

        foreach ($roles as [$role, $branchId, $dcId, $hasBell]) {
            $user = $this->makeUser($role, $branchId, $dcId);
            $this->actingAs($user);

            $header = view('components.header', ['role' => $role])->render();

            if ($hasBell) {
                $this->assertStringContainsString('notification-button', $header);
            } else {
                $this->assertStringNotContainsString('notification-button', $header);
            }
        }
    }

    public function test_kasir_is_forbidden_from_notification_api(): void
    {
        $branch = $this->makeBranch('Cabang Kasir');
        $cashier = $this->makeUser('kasir', $branch->cabang_id);

        $this->actingAs($cashier)
            ->getJson(route('notifications.index'))
            ->assertForbidden();
    }

    private function makeBranch(string $name): Cabang
    {
        return Cabang::query()->create(['nama_cabang' => $name]);
    }

    private function makeDc(string $name): Dc
    {
        return Dc::query()->create(['nama_dc' => $name]);
    }

    private function makeUser(string $roleKey, ?int $branchId = null, ?int $dcId = null): Pengguna
    {
        $sequence = ++$this->userSequence;

        return Pengguna::query()->create([
            'nama_lengkap' => 'Pengguna '.$sequence,
            'username' => 'pengguna'.$sequence,
            'email' => 'pengguna'.$sequence.'@kopsync.test',
            'password_hash' => Hash::make('password'),
            'role' => config("menu.roles.{$roleKey}.db"),
            'cabang_id' => $branchId,
            'dc_id' => $dcId,
            'status' => 'aktif',
        ]);
    }

    private function makeIngredient(): BahanBaku
    {
        return BahanBaku::query()->create([
            'nama_bahan' => 'Bahan Uji',
            'kategori' => 'bahan utama',
            'satuan' => 'unit',
            'nama_kemasan' => 'Unit',
            'isi_per_kemasan' => 1,
            'harga_beli' => 1000,
            'minimum_order_qty' => 1,
        ]);
    }

    private function makeShipment(int $branchId, int $dcId, string $status = 'disiapkan'): SuratJalan
    {
        return SuratJalan::query()->create([
            'cabang_id' => $branchId,
            'dc_id' => $dcId,
            'tanggal_kirim' => now(),
            'status' => $status,
        ]);
    }

    private function makeNotification(Pengguna $user, string $type, int $branchId, int $referenceId): Notification
    {
        $sequence = ++$this->notificationSequence;

        return Notification::query()->create([
            'user_id' => $user->user_id,
            'cabang_id' => $branchId,
            'type' => $type,
            'title' => 'Notifikasi '.$sequence,
            'description' => 'Deskripsi notifikasi '.$sequence,
            'reference_type' => 'stok_cabang',
            'reference_id' => $referenceId,
            'dedupe_key' => 'test:'.$user->user_id.':'.$sequence,
            'is_read' => false,
        ]);
    }
}

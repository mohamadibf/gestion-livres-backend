<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->travelTo(now()->setTime(10, 0));
    }

    private function as(?User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $user ? $this->actingAs($user, 'web') : $this;
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_settings_are_public_and_fall_back_to_config_defaults(): void
    {
        $this->as(null)->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.library_name', 'Gestion de Livres')
            ->assertJsonPath('data.loan_duration_days', 21)
            ->assertJsonPath('data.max_active_loans', 1)
            ->assertJsonPath('data.max_renewals', 1)
            ->assertJsonPath('data.due_soon_days', 3);
    }

    public function test_only_admins_can_update_settings(): void
    {
        $this->as(null)->putJson('/api/settings', ['max_renewals' => 2])->assertUnauthorized();
        $this->as(User::factory()->create())->putJson('/api/settings', ['max_renewals' => 2])->assertForbidden();
    }

    public function test_admin_updates_and_values_persist(): void
    {
        $this->as($this->admin())->putJson('/api/settings', [
            'library_name' => 'Médiathèque Reliure',
            'loan_duration_days' => 14,
            'max_active_loans' => 3,
            'max_renewals' => 2,
            'due_soon_days' => 5,
        ])->assertOk()->assertJsonPath('data.loan_duration_days', 14);

        $this->as(null)->getJson('/api/settings')
            ->assertJsonPath('data.library_name', 'Médiathèque Reliure')
            ->assertJsonPath('data.max_active_loans', 3)
            ->assertJsonPath('data.max_renewals', 2)
            ->assertJsonPath('data.due_soon_days', 5);

        $this->assertDatabaseHas('settings', ['key' => 'loan_duration_days']);
    }

    public function test_partial_update_keeps_other_values(): void
    {
        $this->as($this->admin())->putJson('/api/settings', ['max_renewals' => 4])->assertOk();

        $this->getJson('/api/settings')->assertJsonPath('data.max_renewals', 4)->assertJsonPath('data.loan_duration_days', 21);
    }

    public function test_bounds_are_validated(): void
    {
        $this->as($this->admin());

        foreach ([
            ['loan_duration_days' => 0],
            ['loan_duration_days' => 366],
            ['max_active_loans' => -1],
            ['max_active_loans' => 51],
            ['max_renewals' => 11],
            ['due_soon_days' => 31],
            ['library_name' => ''],
            ['library_name' => str_repeat('x', 61)],
            ['loan_duration_days' => 'abc'],
        ] as $payload) {
            $this->putJson('/api/settings', $payload)->assertUnprocessable();
        }

        // 0 = illimité pour la limite d'emprunts, autorisé
        $this->putJson('/api/settings', ['max_active_loans' => 0])->assertOk();
    }

    public function test_duration_setting_drives_new_loans(): void
    {
        $this->as($this->admin())->putJson('/api/settings', ['loan_duration_days' => 7])->assertOk();

        $this->as(User::factory()->create())
            ->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')
            ->assertCreated()->assertJsonPath('data.days_left', 7);
    }

    public function test_max_loans_setting_and_unlimited(): void
    {
        $this->as($this->admin())->putJson('/api/settings', ['max_active_loans' => 2])->assertOk();
        $reader = User::factory()->create();

        $borrow = fn () => $this->as($reader)->postJson('/api/books/'.Book::factory()->create()->id.'/borrow');
        $borrow()->assertCreated();
        $borrow()->assertCreated();
        $borrow()->assertUnprocessable();

        $this->as($this->admin())->putJson('/api/settings', ['max_active_loans' => 0])->assertOk();
        $borrow()->assertCreated();
    }

    public function test_renewal_setting_and_duration(): void
    {
        $this->as($this->admin())->putJson('/api/settings', ['max_renewals' => 2, 'loan_duration_days' => 10])->assertOk();
        $reader = User::factory()->create();
        $id = $this->as($reader)->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->json('data.id');

        $this->postJson("/api/loans/{$id}/renew")->assertOk()->assertJsonPath('data.days_left', 20);
        $this->postJson("/api/loans/{$id}/renew")->assertOk()->assertJsonPath('data.days_left', 30);
        $this->postJson("/api/loans/{$id}/renew")->assertUnprocessable()->assertJsonPath('message', 'Cet emprunt a déjà été prolongé.');
    }

    public function test_zero_renewals_disables_renewal(): void
    {
        $this->as($this->admin())->putJson('/api/settings', ['max_renewals' => 0])->assertOk();
        $loan = Loan::factory()->active()->create(['user_id' => ($reader = User::factory()->create())->id]);

        $this->as($reader)->getJson('/api/loans?status=active')->assertJsonPath('data.0.can_renew', false);
        $this->postJson("/api/loans/{$loan->id}/renew")->assertUnprocessable();
    }

    public function test_due_soon_threshold(): void
    {
        $reader = User::factory()->create();
        Loan::factory()->active()->create(['user_id' => $reader->id, 'due_at' => now()->addDays(4)]);
        $this->as($reader);

        $this->getJson('/api/loans?status=active')->assertJsonPath('data.0.due_soon', false);

        $this->as($this->admin())->putJson('/api/settings', ['due_soon_days' => 5])->assertOk();
        $this->as($reader)->getJson('/api/loans?status=active')->assertJsonPath('data.0.due_soon', true);
    }
}

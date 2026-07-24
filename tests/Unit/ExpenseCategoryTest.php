<?php

namespace Tests\Unit;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_defaults_creates_five_categories(): void
    {
        $user = User::factory()->create();

        ExpenseCategory::ensureDefaultsFor($user);

        $this->assertEqualsCanonicalizing(
            ExpenseCategory::DEFAULTS,
            $user->expenseCategories()->pluck('name')->all(),
        );
    }

    public function test_ensure_defaults_is_idempotent(): void
    {
        $user = User::factory()->create();

        ExpenseCategory::ensureDefaultsFor($user);
        ExpenseCategory::ensureDefaultsFor($user);

        $this->assertCount(5, $user->expenseCategories()->get());
    }
}

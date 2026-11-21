<?php

use App\Filament\Parent\Resources\ChildProfiles\Pages\ManageChildProfiles;
use App\Games\GamePackage;
use App\Games\GamePackages;
use App\Http\Middleware\EnsureChildPlay;
use App\Models\ChildProfile;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

test('parent handoff and readiness come from installed game packages', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    app()->instance(GamePackages::class, new GamePackages([]));
    Livewire::test(ManageChildProfiles::class)
        ->assertActionDoesNotExist(TestAction::make('play')->table($child))
        ->assertActionDoesNotExist(TestAction::make('inspectPool')->table($child));
    $this->get('/parent/games')->assertOk()->assertDontSee('חידון הניצוצות');
    expect(session()->has('child_mode'))->toBeFalse();

    Route::middleware(['web', 'auth', 'verified', EnsureChildPlay::class])
        ->get('/{current_team}/fixture-game', function (Request $request, Team $current_team) {
            return response()->json(['child' => EnsureChildPlay::profile($request)->id, 'team' => $current_team->id]);
        })
        ->name('fixture-game.entry');
    Route::getRoutes()->refreshNameLookups();

    app()->instance(GamePackages::class, new GamePackages([
        new GamePackage(
            'fixture-game', 'Fixture Game', 'fixture-game.entry', 'Fixture Content',
            fn (ChildProfile $record): string => "Ready for {$record->name}",
        ),
    ]));

    $this->get(route('fixture-game.entry', $team))->assertForbidden();
    $inspector = Livewire::test(ManageChildProfiles::class)
        ->mountAction(TestAction::make('inspectPool')->table($child));
    expect($inspector->instance()->getMountedAction()?->getModalDescription())->toBe("Ready for {$child->name}");
    Livewire::test(ManageChildProfiles::class)
        ->callAction(TestAction::make('play')->table($child))
        ->assertRedirect(route('fixture-game.entry', $team));
    $this->get(route('fixture-game.entry', $team))->assertJson(['child' => $child->id, 'team' => $team->id]);
    $this->get(route('profile.edit'))->assertRedirect(route('child-mode.exit'));
});

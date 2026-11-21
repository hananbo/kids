<?php

namespace App\Filament\Parent\Resources\Games\Pages;

use App\Filament\Parent\Resources\Games\GameResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGames extends ManageRecords
{
    protected static string $resource = GameResource::class;
}

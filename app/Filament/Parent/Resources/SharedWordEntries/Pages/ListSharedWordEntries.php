<?php

namespace App\Filament\Parent\Resources\SharedWordEntries\Pages;

use App\Filament\Parent\Resources\SharedWordEntries\SharedWordEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListSharedWordEntries extends ListRecords
{
    protected static string $resource = SharedWordEntryResource::class;
}
